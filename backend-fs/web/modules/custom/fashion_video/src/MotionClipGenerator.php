<?php

declare(strict_types=1);

namespace Drupal\fashion_video;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;

/**
 * Thin client for fal.ai's Kling 2.5 Turbo image-to-video model.
 *
 * Turns a single still (a generated runway look) into a short "motion clip" —
 * subtle catwalk motion and a cinematic camera move — driven by a text prompt.
 * fal jobs are asynchronous, so this mirrors the talking-head contract: submit
 * once (returns a status + response URL pair), poll the status, then fetch the
 * result URL and download the MP4. The caller drives the poll loop across short
 * requests.
 *
 * This is currently an experiment/debug path (kept out of the main assembly
 * pipeline); the resulting clip is stored on the node as an intermediate so it
 * can be eyeballed.
 */
final class MotionClipGenerator {

  /**
   * Kling 2.5 Turbo Pro image-to-video, via fal's queue API.
   *
   * The "pro" tier is 1080p/48fps; swap to `.../standard/...` for the cheaper
   * tier. 5s is the model's minimum billable clip.
   */
  private const MODEL = 'fal-ai/kling-video/v2.5-turbo/pro/image-to-video';
  private const QUEUE_BASE = 'https://queue.fal.run/';

  /** Default motion prompt for a runway still. */
  private const DEFAULT_PROMPT = 'High-fashion runway show: the model struts forward taking a few confident catwalk steps toward the camera, stops and strikes a fierce dramatic pose, then turns. A dynamic cinematic camera sweeps and pans with a slow push-in following the movement. On both sides of the runway, dense crowds of paparazzi photographers erupt in a frenzy of pictures — an intense, relentless storm of brilliant white camera flashes firing constantly from every direction, blinding bursts and strobing pops that flare and bloom across the frame and dramatically light up the model, casting hard flickering highlights and sharp moving shadows. Editorial high-fashion lighting, glamorous dramatic atmosphere, fabric and hair moving naturally. No text, no captions, no watermark.';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Whether the motion-clip service is configured (fal key present).
   */
  public function isConfigured(): bool {
    return (bool) $this->apiKey();
  }

  /**
   * Submits an image-to-video job.
   *
   * @param string $imageUrl
   *   Publicly reachable URL of the still to animate.
   * @param int $seconds
   *   Requested clip length (Kling supports 5 or 10; 5 is the floor).
   * @param string|null $prompt
   *   Optional motion prompt; falls back to a runway default.
   *
   * @return array{status_url: string, response_url: string, request_id: string}|null
   *   The queue handles for polling, or NULL on failure.
   */
  public function submit(string $imageUrl, int $seconds = 5, ?string $prompt = NULL): ?array {
    if (!$this->apiKey()) {
      return NULL;
    }

    try {
      $response = $this->httpClient->request('POST', self::QUEUE_BASE . self::MODEL, [
        'headers' => $this->headers(['Content-Type' => 'application/json']),
        'json' => [
          'image_url' => $imageUrl,
          'prompt' => $prompt ?: self::DEFAULT_PROMPT,
          'duration' => (string) ($seconds >= 10 ? 10 : 5),
        ],
        'connect_timeout' => 10,
        'timeout' => 30,
      ]);

      $body = json_decode((string) $response->getBody(), TRUE);
      $statusUrl = $body['status_url'] ?? NULL;
      $responseUrl = $body['response_url'] ?? NULL;
      $requestId = $body['request_id'] ?? NULL;
      if (!is_string($statusUrl) || !is_string($responseUrl) || !is_string($requestId)) {
        $this->logger->warning('fal submit returned no handles: @body', [
          '@body' => substr((string) $response->getBody(), 0, 500),
        ]);
        return NULL;
      }
      return [
        'status_url' => $statusUrl,
        'response_url' => $responseUrl,
        'request_id' => $requestId,
      ];
    }
    catch (\Throwable $e) {
      $this->logger->warning('fal submit failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Polls a job's status.
   *
   * @param string $statusUrl
   *   The status URL returned by ::submit().
   *
   * @return string
   *   A normalized token: "done", "error", or "processing" (keep polling; also
   *   returned on a transient transport error).
   */
  public function status(string $statusUrl): string {
    if (!$this->apiKey()) {
      return 'error';
    }

    try {
      $response = $this->httpClient->request('GET', $statusUrl, [
        'headers' => $this->headers(),
        'connect_timeout' => 10,
        'timeout' => 30,
      ]);
      $body = json_decode((string) $response->getBody(), TRUE);
      $raw = is_array($body) && is_string($body['status'] ?? NULL) ? $body['status'] : '';
      return match ($raw) {
        'COMPLETED' => 'done',
        'IN_QUEUE', 'IN_PROGRESS' => 'processing',
        default => $raw === '' ? 'processing' : 'error',
      };
    }
    catch (\Throwable $e) {
      $this->logger->warning('fal status check failed: @msg', ['@msg' => $e->getMessage()]);
      return 'processing';
    }
  }

  /**
   * Fetches the completed job's result and returns the output video URL.
   *
   * @param string $responseUrl
   *   The response URL returned by ::submit().
   *
   * @return string|null
   *   The generated video URL, or NULL on failure.
   */
  public function resultUrl(string $responseUrl): ?string {
    if (!$this->apiKey()) {
      return NULL;
    }

    try {
      $response = $this->httpClient->request('GET', $responseUrl, [
        'headers' => $this->headers(),
        'connect_timeout' => 10,
        'timeout' => 30,
      ]);
      $body = json_decode((string) $response->getBody(), TRUE);
      $url = $body['video']['url'] ?? NULL;
      return is_string($url) && $url !== '' ? $url : NULL;
    }
    catch (\Throwable $e) {
      $this->logger->warning('fal result fetch failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Downloads the finished MP4 bytes from a result URL.
   */
  public function download(string $resultUrl): ?string {
    try {
      $response = $this->httpClient->request('GET', $resultUrl, [
        'connect_timeout' => 10,
        'timeout' => 120,
      ]);
      $bytes = (string) $response->getBody();
      return $bytes !== '' ? $bytes : NULL;
    }
    catch (\Throwable $e) {
      $this->logger->warning('fal result download failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * @param array<string, string> $extra
   *
   * @return array<string, string>
   */
  private function headers(array $extra = []): array {
    return [
      'Authorization' => 'Key ' . $this->apiKey(),
      'Accept' => 'application/json',
    ] + $extra;
  }

  private function apiKey(): string {
    return (string) Settings::get('fal.key', getenv('FAL_KEY') ?: '');
  }

}
