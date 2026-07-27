<?php

declare(strict_types=1);

namespace Drupal\fashion_video;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\s3fs\S3fsServiceInterface;

/**
 * Stores images for a Fashion Video node as private (S3-backed) media.
 *
 * Files are written under a per-user / per-video prefix:
 *   private://users/{owner-uid}/videos/{title-timestamp}/{filename}
 *
 * The video folder mirrors the node title (a date + timestamp such as
 * "2026-07-20 16:19:32"), sanitized to key-safe characters.
 *
 * With s3fs taking over the private stream, that path maps to the matching key
 * in the S3 bucket. s3fs never presigns private files (it routes them through
 * Drupal), so we mint short-lived presigned GET URLs ourselves via
 * ::presignedUrl() for browser display.
 */
final class FashionVideoUploader {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ?S3fsServiceInterface $s3fs,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Writes one image and wraps it in an image media entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The fashion_video node the image belongs to (used for the folder path
   *   and ownership).
   * @param string $binary
   *   The raw (already base64-decoded) image bytes.
   * @param string $extension
   *   File extension without the dot, e.g. "jpg", "png", "webp".
   * @param string $prefix
   *   Filename prefix, e.g. "pose-" or "ai-".
   * @param string $alt
   *   Alt text for the image media.
   *
   * @return \Drupal\media\MediaInterface
   *   The saved, published image media entity.
   */
  public function addImage(NodeInterface $node, string $binary, string $extension = 'jpg', string $prefix = 'pose-', string $alt = 'Fashion video pose image'): MediaInterface {
    $uid = (int) $node->getOwnerId();
    $file = $this->writeFile($node, $binary, $extension, $prefix);

    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => 'image',
      'uid' => $uid,
      'name' => $file->getFilename(),
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $alt,
      ],
    ]);
    $media->setPublished();
    $media->save();

    return $media;
  }

  /**
   * Writes a raw file (e.g. the voice recording) into the per-video folder.
   *
   * Unlike ::addImage() this stores the bytes as a plain managed file with no
   * surrounding media entity, suitable for a node `file` field such as
   * field_voice.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The fashion_video node the file belongs to.
   * @param string $binary
   *   The raw (already base64-decoded) file bytes.
   * @param string $extension
   *   File extension without the dot, e.g. "webm", "m4a".
   * @param string $prefix
   *   Filename prefix, e.g. "voice-".
   *
   * @return \Drupal\file\FileInterface
   *   The saved, permanent file entity.
   */
  public function addFile(NodeInterface $node, string $binary, string $extension, string $prefix): FileInterface {
    return $this->writeFile($node, $binary, $extension, $prefix);
  }

  /**
   * Writes bytes to the node's per-video folder and returns the file entity.
   */
  private function writeFile(NodeInterface $node, string $binary, string $extension, string $prefix): FileInterface {
    $directory = $this->videoDirectory($node);
    $this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    );

    $filename = uniqid($prefix, TRUE) . '.' . $extension;
    return $this->fileRepository->writeData(
      $binary,
      $directory . '/' . $filename,
      FileExists::Rename,
    );
  }

  /**
   * Returns the per-user / per-video private directory for a node.
   *
   * Uses the node title (a date + timestamp) as the per-video folder name,
   * reduced to key-safe characters, falling back to the UUID if the title is
   * somehow empty.
   */
  private function videoDirectory(NodeInterface $node): string {
    $uid = (int) $node->getOwnerId();
    $slug = trim((string) preg_replace('/[^0-9A-Za-z_-]+/', '-', (string) $node->getTitle()), '-');
    if ($slug === '') {
      $slug = $node->uuid();
    }
    return sprintf('private://users/%d/videos/%s', $uid, $slug);
  }

  /**
   * Mints a short-lived presigned GET URL for a private (S3) file.
   *
   * Private s3fs files are normally served through Drupal's authenticated
   * download route, which a token-authenticated SPA browser can't reach. A
   * presigned URL is self-authenticating and lets the browser load the object
   * from S3 directly for a limited time.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   * @param string $expires
   *   A strtotime-compatible lifetime, e.g. "+15 minutes".
   *
   * @return string|null
   *   The presigned URL, a plain Drupal URL when S3 isn't configured, or NULL.
   */
  public function presignedUrl(FileInterface $file, string $expires = '+15 minutes'): ?string {
    $uri = $file->getFileUri();
    if ($uri === NULL || $uri === '') {
      return NULL;
    }

    $config = $this->configFactory->get('s3fs.settings')->get();
    $isS3 = str_starts_with($uri, 'private://') || str_starts_with($uri, 'public://');
    if ($this->s3fs === NULL || !$isS3 || empty($config['bucket'])) {
      // No S3 (s3fs disabled, or a non-S3 environment) — fall back to the
      // normal URL.
      return $this->fileUrlGenerator->generateAbsoluteString($uri);
    }

    // Build the S3 key exactly as s3fs does when writing: prefix with the
    // public/private folder (defaults s3fs-public / s3fs-private), then the
    // optional root_folder. See S3fsStream::convertUriToKeyedPath().
    $scheme = StreamWrapperManager::getScheme($uri);
    $target = trim(StreamWrapperManager::getTarget($uri), '\\/');
    $publicFolder = !empty($config['public_folder']) ? $config['public_folder'] : 's3fs-public';
    $privateFolder = !empty($config['private_folder']) ? $config['private_folder'] : 's3fs-private';
    $key = match ($scheme) {
      'public' => "$publicFolder/$target",
      'private' => "$privateFolder/$target",
      default => $target,
    };
    if (!empty($config['root_folder'])) {
      $key = trim($config['root_folder'], '/') . '/' . $key;
    }

    $client = $this->s3fs->getAmazonS3Client($config);
    $command = $client->getCommand('GetObject', [
      'Bucket' => $config['bucket'],
      'Key' => $key,
    ]);
    $request = $client->createPresignedRequest($command, $expires);

    return (string) $request->getUri();
  }

}
