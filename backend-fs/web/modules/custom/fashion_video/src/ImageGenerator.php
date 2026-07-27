<?php

declare(strict_types=1);

namespace Drupal\fashion_video;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;

/**
 * Generates over-the-top "Balenciaga parody" runway images from pose photos.
 *
 * Each pose photo is fed to OpenAI's image model (gpt-image-1, edits endpoint)
 * as a reference, together with a prompt built from the aesthetic analysis, so
 * the same person/pose is restyled into an absurd high-fashion look. Three scene
 * variants distribute the required set pieces (catwalk, Greek statues/gargoyles,
 * wall torches, themed props, flash photographers) across the images.
 */
final class ImageGenerator {

  private const ENDPOINT = 'https://api.openai.com/v1/images/edits';
  private const MODEL = 'gpt-image-1';
  private const SIZE = '1024x1536';
  private const QUALITY = 'medium';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Whether image generation is configured (API key present).
   */
  public function isConfigured(): bool {
    return (bool) $this->apiKey();
  }

  /**
   * Builds three scene prompts from an aesthetic analysis.
   *
   * @param array{aesthetic?: string, era?: string, description?: string, accessory?: string, props?: array<int, string>} $analysis
   *   The stored aesthetic analysis.
   *
   * @return array<int, string>
   *   Exactly three prompts.
   */
  public function buildPrompts(array $analysis): array {
    $aesthetic = trim((string) ($analysis['aesthetic'] ?? 'eclectic'));
    $era = trim((string) ($analysis['era'] ?? ''));
    $accessory = trim((string) ($analysis['accessory'] ?? ''));
    $props = array_values(array_filter(
      (array) ($analysis['props'] ?? []),
      static fn ($p) => is_string($p) && $p !== '',
    ));
    $propList = $props ? implode(', ', $props) : 'absurd themed props';

    $base = sprintf(
      'Ultra-glossy high-fashion editorial photo, an intentionally absurd and '
      . 'humorous parody of an over-produced Balenciaga runway campaign — deadpan '
      . 'serious yet ridiculous. Keep the SAME person and the SAME pose as the '
      . 'reference image. Dress them in an extravagant, high-maintenance, slightly '
      . 'silly couture outfit inspired by the "%s" aesthetic%s, while keeping at '
      . 'least a few recognizable elements of their original outfit.%s',
      $aesthetic,
      $era !== '' ? sprintf(' (%s)', $era) : '',
      $accessory !== '' ? sprintf(' Accessorize with %s.', $accessory) : '',
    );

    // One hairstyle direction per image so the set isn't three giant wigs:
    //   0) close to the reference hair, 1) deliberately small but funny,
    //   2) the full over-the-top avant-garde look.
    $hair = [
      ' For the hair, stay CLOSE to the hairstyle in the reference photo — the '
      . 'same overall cut, length and color — just lightly restyled and polished '
      . 'for the runway; do NOT add a big wig or voluminous styling.',
      ' Give them a deliberately UNDERSTATED, decidedly NOT-big hairstyle — sleek '
      . 'and flat (severely slicked-back, a tiny neat bun, or plastered-down) — '
      . 'played completely straight so it reads as quietly funny.',
      ' Give them an outrageously BIG, elaborate, gravity-defying avant-garde '
      . 'hairstyle.',
    ];

    $finish = ' Dramatic runway lighting, cinematic, hyper-detailed, luxury '
      . 'magazine quality. Do NOT render any text, letters, words, numbers, '
      . 'captions, watermarks, logos, or signage anywhere in the image.';

    $scenes = [
      sprintf(
        ' The set is an obviously staged high-fashion photoshoot themed around %s: '
        . 'a long glossy runway CATWALK leads to the camera, with rows of '
        . 'PHOTOGRAPHERS crouched on both sides firing bright flash photos. A '
        . 'couple of white Greek marble STATUES flank the runway. The props (%s) '
        . 'are featured prominently on the set.',
        $aesthetic,
        $propList,
      ),
      sprintf(
        ' The staged set evokes %s but is clearly an over-the-top fashion set: '
        . 'flickering WALL TORCHES and stone GARGOYLES line the background, the '
        . 'themed props (%s) are displayed on pedestals, and PHOTOGRAPHERS with '
        . 'flashing cameras crowd the edges.',
        $aesthetic,
        $propList,
      ),
      sprintf(
        ' An obviously staged high-fashion set themed around %s: a CATWALK runway '
        . 'recedes into the background where Greek STATUES and lit WALL TORCHES are '
        . 'visible, the themed props (%s) are staged around, and a row of '
        . 'PHOTOGRAPHERS fire flash photos.',
        $aesthetic,
        $propList,
      ),
    ];

    return [
      $base . $hair[0] . $scenes[0] . $finish,
      $base . $hair[1] . $scenes[1] . $finish,
      $base . $hair[2] . $scenes[2] . $finish,
    ];
  }

  /**
   * Builds the prompt for the face-closeup beauty shot.
   *
   * Restyles the captured closeup into the same aesthetic as the runway looks
   * while copying the person's expression and gently sculpting the face.
   *
   * @param array{aesthetic?: string, era?: string, description?: string, accessory?: string, props?: array<int, string>} $analysis
   *   The stored aesthetic analysis.
   */
  public function buildCloseupPrompt(array $analysis): string {
    $aesthetic = trim((string) ($analysis['aesthetic'] ?? 'eclectic'));
    $accessory = trim((string) ($analysis['accessory'] ?? ''));

    return sprintf(
      'Ultra-glossy high-fashion BEAUTY CLOSEUP portrait, an intentionally absurd '
      . 'and humorous parody of an over-produced Balenciaga campaign — deadpan '
      . 'serious yet ridiculous. Keep the SAME person as the reference image and '
      . 'copy their facial EXPRESSION from the reference. Restyle them into the '
      . '"%s" aesthetic, borrowing the same couture styling and dramatic hair '
      . 'energy as the runway looks%s. Tight head-and-shoulders framing; subtly '
      . 'emphasize the cheekbones and jawline with sculpting contour and light so '
      . 'they look a bit sharper (flattering, not grotesque). Dramatic beauty '
      . 'lighting, cinematic, hyper-detailed, luxury magazine quality. Do NOT '
      . 'render any text, letters, words, numbers, captions, watermarks, logos, '
      . 'or signage anywhere in the image.',
      $aesthetic,
      $accessory !== '' ? sprintf(', accessorized with %s', $accessory) : '',
    );
  }

  /**
   * Generates one styled image from a reference pose image.
   *
   * @param string $prompt
   *   The full prompt for this image.
   * @param string $referenceBinary
   *   Raw bytes of the reference pose image.
   * @param string $referenceExtension
   *   Reference image extension (jpg|png|webp).
   *
   * @return string|null
   *   Raw PNG bytes of the generated image, or NULL on failure.
   */
  public function generate(string $prompt, string $referenceBinary, string $referenceExtension = 'jpg'): ?string {
    $apiKey = $this->apiKey();
    if (!$apiKey) {
      return NULL;
    }

    $mime = $referenceExtension === 'jpg' ? 'image/jpeg' : 'image/' . $referenceExtension;

    try {
      $response = $this->httpClient->request('POST', self::ENDPOINT, [
        'headers' => ['Authorization' => 'Bearer ' . $apiKey],
        'multipart' => [
          ['name' => 'model', 'contents' => self::MODEL],
          ['name' => 'prompt', 'contents' => $prompt],
          ['name' => 'size', 'contents' => self::SIZE],
          ['name' => 'quality', 'contents' => self::QUALITY],
          ['name' => 'n', 'contents' => '1'],
          [
            'name' => 'image',
            'contents' => $referenceBinary,
            'filename' => 'pose.' . $referenceExtension,
            'headers' => ['Content-Type' => $mime],
          ],
        ],
        'connect_timeout' => 10,
        'timeout' => 180,
      ]);

      $body = json_decode((string) $response->getBody(), TRUE);
      $b64 = $body['data'][0]['b64_json'] ?? NULL;
      if (!is_string($b64) || $b64 === '') {
        return NULL;
      }
      $binary = base64_decode($b64, TRUE);
      return $binary !== FALSE && $binary !== '' ? $binary : NULL;
    }
    catch (\Throwable $e) {
      $this->logger->warning('Image generation failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  private function apiKey(): string {
    return (string) Settings::get('openai.api_key', getenv('OPENAI_API_KEY') ?: '');
  }

}
