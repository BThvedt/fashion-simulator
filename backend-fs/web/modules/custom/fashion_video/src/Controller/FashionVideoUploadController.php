<?php

declare(strict_types=1);

namespace Drupal\fashion_video\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\fashion_video\AestheticGenerator;
use Drupal\fashion_video\FashionVideoUploader;
use Drupal\fashion_video\ImageGenerator;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Receives captured pose images and stores them on a fashion_video node.
 */
final class FashionVideoUploadController extends ControllerBase {

  /** Maximum images accepted in a single request. */
  private const MAX_IMAGES = 20;

  /** Map of accepted image mime types to file extensions. */
  private const EXTENSIONS = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
  ];

  /** Map of accepted audio mime types to file extensions. */
  private const AUDIO_EXTENSIONS = [
    'audio/webm' => 'webm',
    'audio/ogg' => 'ogg',
    'audio/mp4' => 'm4a',
    'audio/x-m4a' => 'm4a',
    'audio/mpeg' => 'mp3',
    'audio/wav' => 'wav',
    'audio/x-wav' => 'wav',
  ];

  /** Maximum AI runway images to generate per video. */
  private const MAX_AI_IMAGES = 3;

  public function __construct(
    private readonly FashionVideoUploader $uploader,
    private readonly AestheticGenerator $stylist,
    private readonly ImageGenerator $imageGenerator,
    private readonly LockBackendInterface $lock,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('fashion_video.uploader'),
      $container->get('fashion_video.stylist'),
      $container->get('fashion_video.image_generator'),
      $container->get('lock'),
    );
  }

  /**
   * POST /fashion-video/{uuid}/pose-images
   *
   * Body: {"images": ["data:image/jpeg;base64,....", ...]}
   */
  public function poseImages(Request $request, string $uuid): JsonResponse {
    $node = $this->loadOwnedNode($uuid);

    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload) || !isset($payload['images']) || !is_array($payload['images'])) {
      throw new BadRequestHttpException('Expected a JSON body with an "images" array.');
    }

    $images = $payload['images'];
    if (count($images) > self::MAX_IMAGES) {
      throw new BadRequestHttpException('Too many images in one request.');
    }

    $created = [];
    $binaries = [];
    foreach ($images as $image) {
      if (!is_string($image)) {
        continue;
      }
      [$binary, $extension] = $this->decodeImage($image);
      $media = $this->uploader->addImage($node, $binary, $extension);
      $node->get('field_pose_images')->appendItem(['target_id' => $media->id()]);
      $created[] = $media->uuid();
      $binaries[] = [$binary, $extension];
    }

    if ($created) {
      $node->save();
    }

    // Persist the capture assets (song filename + voice recording). Both are
    // best-effort: the pose images are already saved, so a bad/oversized clip
    // shouldn't fail the whole request.
    $this->storeCaptureAssets($node, $payload);

    // Best-effort aesthetic analysis. Images are already saved, so a failure
    // here (missing API key, timeout, etc.) just leaves the node without an
    // analysis rather than losing the upload.
    $analysis = $this->stylist->analyze($binaries);
    if ($analysis && $node->hasField('field_style_analysis')) {
      $node->set('field_style_analysis', json_encode($analysis));
      $node->save();
    }

    return new JsonResponse([
      'status' => 'ok',
      'node' => $node->uuid(),
      'created' => $created,
      'analysis' => $analysis,
    ], 201);
  }

  /**
   * Stores the background song filename and voice recording on the node.
   *
   * @param array<string, mixed> $payload
   *   The decoded request body; may contain "song" (string) and "voice" (a
   *   base64 data URL of the recorded audio).
   */
  private function storeCaptureAssets(NodeInterface $node, array $payload): void {
    $changed = FALSE;

    $song = $payload['song'] ?? NULL;
    if (is_string($song) && $song !== '' && $node->hasField('field_song')) {
      $node->set('field_song', mb_substr($song, 0, 255));
      $changed = TRUE;
    }

    $voice = $payload['voice'] ?? NULL;
    if (is_string($voice) && $voice !== '' && $node->hasField('field_voice')) {
      try {
        [$binary, $extension] = $this->decodeAudio($voice);
        $file = $this->uploader->addFile($node, $binary, $extension, 'voice-');
        $node->set('field_voice', ['target_id' => $file->id()]);
        $changed = TRUE;
      }
      catch (\Throwable $e) {
        // Non-fatal: keep the pose images even if the clip can't be stored.
        $this->getLogger('fashion_video')->warning('Voice upload failed: @msg', ['@msg' => $e->getMessage()]);
      }
    }

    if ($changed) {
      $node->save();
    }
  }

  /**
   * GET /fashion-video/{uuid}/media
   *
   * Returns the node title plus short-lived presigned URLs for its pose images,
   * which the browser can load directly from S3.
   */
  public function media(string $uuid): JsonResponse {
    $node = $this->loadOwnedNode($uuid);

    $analysis = NULL;
    if ($node->hasField('field_style_analysis') && !$node->get('field_style_analysis')->isEmpty()) {
      $decoded = json_decode((string) $node->get('field_style_analysis')->value, TRUE);
      if (is_array($decoded)) {
        $analysis = $decoded;
      }
    }

    $song = NULL;
    if ($node->hasField('field_song') && !$node->get('field_song')->isEmpty()) {
      $song = $node->get('field_song')->value;
    }

    $voice = NULL;
    if ($node->hasField('field_voice') && !$node->get('field_voice')->isEmpty()) {
      $file = $node->get('field_voice')->entity;
      if ($file) {
        $voice = $this->uploader->presignedUrl($file);
      }
    }

    return new JsonResponse([
      'title' => $node->getTitle(),
      'poses' => $this->presignedImages($node, 'field_pose_images'),
      'aiImages' => $this->presignedImages($node, 'field_ai_images'),
      'analysis' => $analysis,
      'song' => $song,
      'voice' => $voice,
    ]);
  }

  /**
   * POST /fashion-video/{uuid}/generate-images
   *
   * Generates the AI runway images for a node from its pose photos. Idempotent
   * (skips if images already exist) and locked (skips if a run is in progress).
   * Runs the slow generation in-request; the caller fires this and polls
   * ::media() for the resulting images.
   */
  public function generateImages(string $uuid): JsonResponse {
    $node = $this->loadOwnedNode($uuid);

    if (!$node->get('field_ai_images')->isEmpty()) {
      return new JsonResponse(['status' => 'exists']);
    }
    if (!$this->imageGenerator->isConfigured()) {
      return new JsonResponse(['status' => 'not_configured'], 503);
    }

    $lockId = 'fashion_video_image_gen:' . $node->id();
    if (!$this->lock->acquire($lockId, 300)) {
      return new JsonResponse(['status' => 'in_progress']);
    }

    // Keep working even if the client disconnects while polling.
    ignore_user_abort(TRUE);
    @set_time_limit(600);

    try {
      $analysis = [];
      if ($node->hasField('field_style_analysis') && !$node->get('field_style_analysis')->isEmpty()) {
        $decoded = json_decode((string) $node->get('field_style_analysis')->value, TRUE);
        if (is_array($decoded)) {
          $analysis = $decoded;
        }
      }

      $prompts = $this->imageGenerator->buildPrompts($analysis);
      $poses = $node->get('field_pose_images')->referencedEntities();
      $bodyCount = min(count($poses), self::MAX_AI_IMAGES);

      $usedPrompts = [];
      $created = 0;

      // Runway looks from the body poses.
      for ($i = 0; $i < $bodyCount; $i++) {
        $prompt = $prompts[$i % count($prompts)];
        if ($this->generateFromPose($node, $poses[$i], $prompt)) {
          $usedPrompts[] = 'Image ' . ($i + 1) . ":\n" . $prompt;
          $created++;
        }
      }

      // The capture flow appends a single face closeup after the body poses, so
      // any pose beyond the first three is the closeup — give it its own beauty
      // shot that copies the expression and borrows the runway styling.
      if (count($poses) > self::MAX_AI_IMAGES) {
        $closeupPrompt = $this->imageGenerator->buildCloseupPrompt($analysis);
        if ($this->generateFromPose($node, $poses[self::MAX_AI_IMAGES], $closeupPrompt)) {
          $usedPrompts[] = "Closeup:\n" . $closeupPrompt;
          $created++;
        }
      }

      if ($created > 0) {
        if ($node->hasField('field_image_prompt')) {
          $node->set('field_image_prompt', implode("\n\n---\n\n", $usedPrompts));
        }
        $node->save();
      }

      return new JsonResponse(['status' => 'done', 'created' => $created]);
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  /**
   * Generates one styled image from a pose media entity.
   *
   * On success the new image media is appended to field_ai_images.
   *
   * @return bool
   *   TRUE if an image was generated and attached.
   */
  private function generateFromPose(NodeInterface $node, MediaInterface $pose, string $prompt): bool {
    $file = $pose->get('field_media_image')->entity;
    if (!$file) {
      return FALSE;
    }
    $binary = @file_get_contents($file->getFileUri());
    if ($binary === FALSE || $binary === '') {
      return FALSE;
    }
    $ext = pathinfo((string) $file->getFilename(), PATHINFO_EXTENSION) ?: 'jpg';

    $image = $this->imageGenerator->generate($prompt, $binary, $ext);
    if ($image === NULL) {
      return FALSE;
    }

    $media = $this->uploader->addImage($node, $image, 'png', 'ai-', 'AI-generated fashion image');
    $node->get('field_ai_images')->appendItem(['target_id' => $media->id()]);
    return TRUE;
  }

  /**
   * Returns presigned URLs for the image media referenced by a node field.
   *
   * @return array<int, string>
   */
  private function presignedImages(NodeInterface $node, string $field): array {
    $urls = [];
    foreach ($node->get($field)->referencedEntities() as $media) {
      $file = $media->get('field_media_image')->entity;
      if ($file) {
        $url = $this->uploader->presignedUrl($file);
        if ($url) {
          $urls[] = $url;
        }
      }
    }
    return $urls;
  }

  /**
   * Loads a fashion_video node by UUID, enforcing per-owner access.
   */
  private function loadOwnedNode(string $uuid): NodeInterface {
    $nodes = $this->entityTypeManager()->getStorage('node')->loadByProperties([
      'uuid' => $uuid,
      'type' => 'fashion_video',
    ]);
    /** @var \Drupal\node\NodeInterface|false $node */
    $node = reset($nodes);
    if (!$node) {
      throw new NotFoundHttpException('Fashion video not found.');
    }

    $account = $this->currentUser();
    $isOwner = (int) $node->getOwnerId() === (int) $account->id();
    if (!$isOwner && !$account->hasPermission('bypass node access')) {
      throw new AccessDeniedHttpException('You may only add images to your own fashion videos.');
    }

    return $node;
  }

  /**
   * Decodes a data-URL (or bare base64) image into [binary, extension].
   *
   * @return array{0: string, 1: string}
   */
  private function decodeImage(string $image): array {
    $mime = 'image/jpeg';
    $data = $image;
    if (preg_match('#^data:(?<mime>[\w/+.-]+);base64,(?<data>.+)$#s', $image, $m)) {
      $mime = strtolower($m['mime']);
      $data = $m['data'];
    }

    if (!isset(self::EXTENSIONS[$mime])) {
      throw new BadRequestHttpException('Unsupported image type: ' . $mime);
    }

    $binary = base64_decode($data, TRUE);
    if ($binary === FALSE || $binary === '') {
      throw new BadRequestHttpException('Invalid base64 image data.');
    }

    return [$binary, self::EXTENSIONS[$mime]];
  }

  /**
   * Decodes an audio data-URL into [binary, extension].
   *
   * The mime type is normalized so codec suffixes (e.g. "audio/webm;codecs=opus")
   * still match.
   *
   * @return array{0: string, 1: string}
   */
  private function decodeAudio(string $audio): array {
    $mime = 'audio/webm';
    $data = $audio;
    if (preg_match('#^data:(?<mime>[\w/+.-]+)(?<params>;[^,]*)?;base64,(?<data>.+)$#s', $audio, $m)) {
      $mime = strtolower($m['mime']);
      $data = $m['data'];
    }

    if (!isset(self::AUDIO_EXTENSIONS[$mime])) {
      throw new BadRequestHttpException('Unsupported audio type: ' . $mime);
    }

    $binary = base64_decode($data, TRUE);
    if ($binary === FALSE || $binary === '') {
      throw new BadRequestHttpException('Invalid base64 audio data.');
    }

    return [$binary, self::AUDIO_EXTENSIONS[$mime]];
  }

}
