<?php

declare(strict_types=1);

namespace Drupal\fashion_video;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;

/**
 * Thin client for the D-ID Talks API (lip-synced "talking head" clips).
 *
 * Given a public image URL (the generated face closeup) and a public audio URL
 * (the user's recorded quote), D-ID animates the face to speak the audio and
 * returns an MP4. Both URLs must be reachable by D-ID for the lifetime of the
 * job — short-lived presigned S3 URLs work as long as they outlast processing.
 *
 * The API is deliberately exposed as three small, non-blocking steps
 * (createTalk / fetchStatus / download) rather than one blocking call: D-ID
 * queues jobs (minutes on trial plans), so the caller drives a poll loop across
 * separate short HTTP requests instead of holding a worker open.
 */
final class TalkingHeadGenerator implements TalkingHeadInterface {

  private const CREATE_ENDPOINT = 'https://api.d-id.com/talks';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Whether the talking-head service is configured (D-ID API key present).
   */
  public function isConfigured(): bool {
    return (bool) $this->apiKey();
  }

  /**
   * Creates a talk from a face image and an audio clip.
   *
   * @param string $sourceUrl
   *   Publicly reachable URL of the face image.
   * @param string $audioUrl
   *   Publicly reachable URL of the audio (wav/mp3/m4a/etc.).
   *
   * @return string|null
   *   The D-ID talk id, or NULL on failure.
   */
  public function createTalk(string $sourceUrl, string $audioUrl): ?string {
    if (!$this->apiKey()) {
      return NULL;
    }

    try {
      $response = $this->httpClient->request('POST', self::CREATE_ENDPOINT, [
        'headers' => $this->headers(['Content-Type' => 'application/json']),
        'json' => [
          'source_url' => $sourceUrl,
          'script' => [
            'type' => 'audio',
            'audio_url' => $audioUrl,
          ],
          'config' => ['stitch' => TRUE],
        ],
        'connect_timeout' => 10,
        'timeout' => 30,
      ]);

      $body = json_decode((string) $response->getBody(), TRUE);
      $id = $body['id'] ?? NULL;
      if (!is_string($id) || $id === '') {
        $this->logger->warning('D-ID create talk returned no id: @body', [
          '@body' => substr((string) $response->getBody(), 0, 500),
        ]);
        return NULL;
      }
      return $id;
    }
    catch (\Throwable $e) {
      $this->logger->warning('D-ID create talk failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Fetches the current status of a talk.
   *
   * @param string $talkId
   *   The D-ID talk id.
   *
   * @return array{status: string, result_url: string|null}
   *   The talk status (e.g. "created", "started", "done", "error") and, when
   *   done, its result URL. An empty status signals a transport error (treat as
   *   "keep waiting").
   */
  public function fetchStatus(string $talkId): array {
    if (!$this->apiKey()) {
      return ['status' => 'error', 'result_url' => NULL];
    }

    try {
      $response = $this->httpClient->request('GET', self::CREATE_ENDPOINT . '/' . rawurlencode($talkId), [
        'headers' => $this->headers(),
        'connect_timeout' => 10,
        'timeout' => 30,
      ]);
      $body = json_decode((string) $response->getBody(), TRUE);
      $status = is_array($body) && is_string($body['status'] ?? NULL) ? $body['status'] : '';
      $url = is_array($body) && is_string($body['result_url'] ?? NULL) ? $body['result_url'] : NULL;
      return ['status' => $status, 'result_url' => $url];
    }
    catch (\Throwable $e) {
      $this->logger->warning('D-ID status check failed for @id: @msg', [
        '@id' => $talkId,
        '@msg' => $e->getMessage(),
      ]);
      return ['status' => '', 'result_url' => NULL];
    }
  }

  /**
   * Downloads the finished MP4 bytes from a result URL.
   *
   * @param string $resultUrl
   *   The talk's result URL.
   *
   * @return string|null
   *   Raw MP4 bytes, or NULL on failure.
   */
  public function download(string $resultUrl): ?string {
    try {
      $response = $this->httpClient->request('GET', $resultUrl, [
        'connect_timeout' => 10,
        'timeout' => 60,
      ]);
      $bytes = (string) $response->getBody();
      return $bytes !== '' ? $bytes : NULL;
    }
    catch (\Throwable $e) {
      $this->logger->warning('D-ID result download failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Builds request headers with authorization.
   *
   * @param array<string, string> $extra
   *   Additional headers to merge in.
   *
   * @return array<string, string>
   */
  private function headers(array $extra = []): array {
    return [
      'Authorization' => 'Basic ' . $this->apiKey(),
      'Accept' => 'application/json',
    ] + $extra;
  }

  private function apiKey(): string {
    return (string) Settings::get('did.api_key', getenv('DID_API_KEY') ?: '');
  }

}
