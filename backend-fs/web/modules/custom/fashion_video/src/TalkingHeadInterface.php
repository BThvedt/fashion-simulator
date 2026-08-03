<?php

declare(strict_types=1);

namespace Drupal\fashion_video;

/**
 * Contract for a "talking head" (lip-sync) provider.
 *
 * Given a public image URL (the generated face closeup) and a public audio URL
 * (the user's recorded quote), the provider animates the face to speak the audio
 * and returns an MP4. Both URLs must be reachable by the provider for the
 * lifetime of the job — short-lived presigned S3 URLs work as long as they
 * outlast processing.
 *
 * The job is modelled as three small, non-blocking steps (createTalk /
 * fetchStatus / download) rather than one blocking call: providers queue jobs
 * (minutes), so the caller drives a poll loop across separate short HTTP
 * requests instead of holding a worker open.
 */
interface TalkingHeadInterface {

  /**
   * Whether the provider is configured (its API key is present).
   */
  public function isConfigured(): bool;

  /**
   * Creates a talk from a face image and an audio clip.
   *
   * @param string $sourceUrl
   *   Publicly reachable URL of the face image.
   * @param string $audioUrl
   *   Publicly reachable URL of the audio (wav/mp3/m4a/etc.).
   *
   * @return string|null
   *   The provider's job id, or NULL on failure.
   */
  public function createTalk(string $sourceUrl, string $audioUrl): ?string;

  /**
   * Fetches the current status of a talk.
   *
   * @param string $talkId
   *   The provider's job id.
   *
   * @return array{status: string, result_url: string|null}
   *   A normalized status — "done", "error", "rejected", or a "keep waiting"
   *   token (e.g. "processing"/"created"/"started") — plus, when done, its
   *   result URL. An empty status signals a transport error (treat as "keep
   *   waiting").
   */
  public function fetchStatus(string $talkId): array;

  /**
   * Downloads the finished MP4 bytes from a result URL.
   *
   * @param string $resultUrl
   *   The job's result URL.
   *
   * @return string|null
   *   Raw MP4 bytes, or NULL on failure.
   */
  public function download(string $resultUrl): ?string;

}
