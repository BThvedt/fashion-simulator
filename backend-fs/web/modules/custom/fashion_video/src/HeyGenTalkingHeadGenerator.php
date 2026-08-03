<?php

declare(strict_types=1);

namespace Drupal\fashion_video;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Thin client for the HeyGen v3 Videos API (lip-synced "talking head" clips).
 *
 * Uses the "animate an arbitrary image" path: POST /v3/videos with
 * type:"image", the face image URL, and a pre-recorded audio URL to lip-sync
 * to. This is the Avatar IV pipeline (the default engine) — the only one that
 * animates an arbitrary generated image directly. Avatar III is a photo-avatar
 * pipeline that requires a pre-created avatar and is intentionally not wired up
 * here; selecting it in config still sends the engine hint but the image path
 * is what does the work.
 *
 * Implements the same three-step contract as the D-ID client (createTalk /
 * fetchStatus / download) so the caller's poll loop is provider-agnostic.
 */
final class HeyGenTalkingHeadGenerator implements TalkingHeadInterface {

  private const BASE = 'https://api.heygen.com/v3';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerChannelInterface $logger,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isConfigured(): bool {
    return (bool) $this->apiKey();
  }

  /**
   * {@inheritdoc}
   */
  public function createTalk(string $sourceUrl, string $audioUrl): ?string {
    if (!$this->apiKey()) {
      return NULL;
    }

    // The image-to-video path animates with Avatar IV implicitly, and its
    // documented request shape does not carry an `engine` field. Only send one
    // for a non-default engine selection (e.g. avatar_iii), which requires the
    // separate photo-avatar flow and isn't supported here.
    $payload = [
      'type' => 'image',
      'image' => ['type' => 'url', 'url' => $sourceUrl],
      // Pre-recorded audio to lip-sync to (mutually exclusive with script).
      'audio_url' => $audioUrl,
      'title' => 'Fashion Simulator talk',
      // Force portrait to match the 720x1280 assembly canvas. HeyGen defaults
      // to 16:9 when omitted; 9:16 @ 720p renders exactly 720x1280, so the
      // downstream normalize is a no-op instead of letterboxing a landscape
      // clip.
      'aspect_ratio' => '9:16',
      'resolution' => '720p',
    ];
    if ($this->engine() !== 'avatar_iv') {
      $payload['engine'] = ['type' => $this->engine()];
    }

    try {
      $response = $this->httpClient->request('POST', self::BASE . '/videos', [
        'headers' => $this->headers(['Content-Type' => 'application/json']),
        'json' => $payload,
        'connect_timeout' => 10,
        'timeout' => 30,
      ]);

      $body = json_decode((string) $response->getBody(), TRUE);
      $id = $body['data']['video_id'] ?? NULL;
      if (!is_string($id) || $id === '') {
        $this->logger->warning('HeyGen create video returned no id: @body', [
          '@body' => substr((string) $response->getBody(), 0, 500),
        ]);
        return NULL;
      }
      return $id;
    }
    catch (RequestException $e) {
      // Guzzle truncates the response body in the exception message; log the
      // full body so the exact invalid_parameter is visible in the report.
      $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
      $this->logger->warning('HeyGen create video failed: @msg | response: @body', [
        '@msg' => $e->getMessage(),
        '@body' => substr($body, 0, 1500),
      ]);
      return NULL;
    }
    catch (\Throwable $e) {
      $this->logger->warning('HeyGen create video failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fetchStatus(string $talkId): array {
    if (!$this->apiKey()) {
      return ['status' => 'error', 'result_url' => NULL];
    }

    try {
      $response = $this->httpClient->request('GET', self::BASE . '/videos/' . rawurlencode($talkId), [
        'headers' => $this->headers(),
        'connect_timeout' => 10,
        'timeout' => 30,
      ]);
      $body = json_decode((string) $response->getBody(), TRUE);
      $data = is_array($body) && is_array($body['data'] ?? NULL) ? $body['data'] : [];
      $raw = is_string($data['status'] ?? NULL) ? $data['status'] : '';
      $url = is_string($data['video_url'] ?? NULL) ? $data['video_url'] : NULL;

      // Normalize HeyGen's status enum (pending/processing/completed/failed)
      // onto the shared vocabulary the caller understands.
      $status = match ($raw) {
        'completed' => 'done',
        'failed' => 'error',
        default => 'processing',
      };
      if ($status === 'error') {
        $this->logger->warning('HeyGen video @id failed: @msg', [
          '@id' => $talkId,
          '@msg' => (string) ($data['failure_message'] ?? $data['failure_code'] ?? 'unknown'),
        ]);
      }
      return ['status' => $status, 'result_url' => $url];
    }
    catch (RequestException $e) {
      $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
      $this->logger->warning('HeyGen status check failed for @id: @msg | response: @body', [
        '@id' => $talkId,
        '@msg' => $e->getMessage(),
        '@body' => substr($body, 0, 1000),
      ]);
      return ['status' => '', 'result_url' => NULL];
    }
    catch (\Throwable $e) {
      $this->logger->warning('HeyGen status check failed for @id: @msg', [
        '@id' => $talkId,
        '@msg' => $e->getMessage(),
      ]);
      return ['status' => '', 'result_url' => NULL];
    }
  }

  /**
   * {@inheritdoc}
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
      $this->logger->warning('HeyGen result download failed: @msg', ['@msg' => $e->getMessage()]);
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
      'X-Api-Key' => $this->apiKey(),
      'Accept' => 'application/json',
    ] + $extra;
  }

  /**
   * The configured HeyGen rendering engine (defaults to Avatar IV).
   */
  private function engine(): string {
    $engine = (string) $this->configFactory->get('fashion_video.settings')->get('heygen_engine');
    return $engine !== '' ? $engine : 'avatar_iv';
  }

  private function apiKey(): string {
    return (string) Settings::get('heygen.api_key', getenv('HEYGEN_API_KEY') ?: '');
  }

}
