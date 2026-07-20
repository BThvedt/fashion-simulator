<?php

declare(strict_types=1);

namespace Drupal\fashion_video;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;

/**
 * Guesses a fashion aesthetic from pose photos using an OpenAI vision model.
 *
 * Given the captured images, the model returns a structured guess: a well-known
 * aesthetic/era, a short description, a suggested accessory, and a couple of
 * on-theme props. The result is a plain associative array ready to be stored
 * as JSON on the node.
 */
final class AestheticGenerator {

  private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';
  private const MODEL = 'gpt-4o-mini';
  private const MAX_IMAGES = 4;

  private const PROMPT = <<<'TXT'
You are a playful but knowledgeable fashion historian analyzing photos of one
person striking poses. Judge only their clothing, styling, hair, and overall
vibe — never their identity, body, or personal attributes.

Identify the single closest well-known fashion aesthetic or trend from any
decade since the 1920s (e.g. flapper, 1950s greaser, 1970s hippie, 1980s power
suit, 1990s grunge, Y2K, cottagecore, normcore, etc.). Then suggest one fashion
accessory that would complete the look, and two or three fun, on-theme props
(e.g. a hippie -> lava lamp, peace-sign necklace; a farmer -> a cow, a pitchfork).

Keep it light and complimentary. Return your answer using the provided schema.
TXT;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Analyzes pose images and returns a structured aesthetic guess.
   *
   * @param array<int, array{0: string, 1: string}> $images
   *   A list of [binary, extension] pairs (extension: jpg|png|webp).
   *
   * @return array{aesthetic: string, era: string, description: string, accessory: string, props: array<int, string>}|null
   *   The analysis, or NULL if no API key is configured or the call failed.
   */
  public function analyze(array $images): ?array {
    $apiKey = Settings::get('openai.api_key', getenv('OPENAI_API_KEY') ?: '');
    if (!$apiKey || !$images) {
      return NULL;
    }

    $content = [['type' => 'text', 'text' => self::PROMPT]];
    foreach (array_slice($images, 0, self::MAX_IMAGES) as [$binary, $extension]) {
      $mime = $extension === 'jpg' ? 'image/jpeg' : 'image/' . $extension;
      $content[] = [
        'type' => 'image_url',
        'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode($binary)],
      ];
    }

    try {
      $response = $this->httpClient->request('POST', self::ENDPOINT, [
        'headers' => [
          'Authorization' => 'Bearer ' . $apiKey,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'model' => self::MODEL,
          'messages' => [['role' => 'user', 'content' => $content]],
          'max_tokens' => 500,
          'response_format' => $this->responseFormat(),
        ],
        'connect_timeout' => 10,
        'timeout' => 45,
      ]);

      $body = json_decode((string) $response->getBody(), TRUE);
      $json = $body['choices'][0]['message']['content'] ?? NULL;
      $parsed = is_string($json) ? json_decode($json, TRUE) : NULL;

      return $this->normalize($parsed);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Aesthetic analysis failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * The OpenAI structured-output schema for a fashion analysis.
   */
  private function responseFormat(): array {
    return [
      'type' => 'json_schema',
      'json_schema' => [
        'name' => 'fashion_analysis',
        'strict' => TRUE,
        'schema' => [
          'type' => 'object',
          'additionalProperties' => FALSE,
          'properties' => [
            'aesthetic' => ['type' => 'string'],
            'era' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'accessory' => ['type' => 'string'],
            'props' => [
              'type' => 'array',
              'items' => ['type' => 'string'],
            ],
          ],
          'required' => ['aesthetic', 'era', 'description', 'accessory', 'props'],
        ],
      ],
    ];
  }

  /**
   * Validates and coerces the model output into the expected shape.
   */
  private function normalize(mixed $parsed): ?array {
    if (!is_array($parsed) || !isset($parsed['aesthetic'])) {
      return NULL;
    }

    $props = [];
    foreach ((array) ($parsed['props'] ?? []) as $prop) {
      if (is_string($prop) && $prop !== '') {
        $props[] = $prop;
      }
    }

    return [
      'aesthetic' => (string) ($parsed['aesthetic'] ?? ''),
      'era' => (string) ($parsed['era'] ?? ''),
      'description' => (string) ($parsed['description'] ?? ''),
      'accessory' => (string) ($parsed['accessory'] ?? ''),
      'props' => $props,
    ];
  }

}
