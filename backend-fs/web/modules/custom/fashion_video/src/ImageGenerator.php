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

    // Match the reference person's facial hair rather than inventing or removing
    // it — applied to every look so beards/stubble/clean-shaven stay consistent.
    $facialHair = ' Match the reference photo\'s FACIAL HAIR exactly: if they '
      . 'have a beard, moustache, goatee or stubble, give them the same '
      . 'facial-hair shape, length AND COLOR — the facial hair must be the same '
      . 'natural color it is in the reference photo; if they are clean-shaven, '
      . 'keep them clean-shaven.';

    $base = sprintf(
      'Ultra-glossy high-fashion editorial photo, an intentionally absurd and '
      . 'humorous parody of an over-produced Balenciaga runway campaign — deadpan '
      . 'serious yet ridiculous. Keep the SAME person and the SAME pose as the '
      . 'reference image. Dress them in an extravagant, high-maintenance, slightly '
      . 'silly couture outfit inspired by the "%s" aesthetic%s, but keep it '
      . 'clearly grounded in their ORIGINAL outfit: preserve the original '
      . 'clothing\'s dominant COLORS, PATTERNS/prints, fabrics and overall '
      . 'silhouette and carry them through the restyle, so the result reads as a '
      . 'heightened version of what they are already wearing rather than a '
      . 'brand-new outfit — stay largely faithful and deviate only modestly.%s%s',
      $aesthetic,
      $era !== '' ? sprintf(' (%s)', $era) : '',
      $accessory !== '' ? sprintf(' Accessorize with %s.', $accessory) : '',
      $facialHair,
    );

    // One hairstyle direction per image so the set isn't three giant wigs:
    //   0) close to the reference hair, 1) deliberately small but funny,
    //   2) one wild look picked at random from a menu of big/weird cuts.
    $wildStyles = [
      'an outrageously BIG, elaborate, gravity-defying avant-garde hairstyle',
      'long dramatic DREADLOCKS sculpted into an avant-garde high-fashion shape',
      'an enormous, perfectly round AFRO picked out to maximum volume',
      'a crisp, geometric high-top FLAT TOP with sharp fade lines',
      'an iconic 1980s "Flock of Seagulls" new-wave swoop — a dramatic '
      . 'side-swept fringe cascading down over one eye',
      'a towering, sharply spiked punk MOHAWK',
      'a completely BALD, gleaming head',
      'an ironic SKULLET — bald and shiny on top with long flowing hair at the '
      . 'back and sides',
      'a proudly absurd MULLET, business in the front and party in the back',
      'a closely SHAVED head, either fully buzzed or with a bold geometric '
      . 'pattern shaved into the stubble',
    ];
    $hair = [
      ' For the hair, stay CLOSE to the hairstyle in the reference photo — the '
      . 'same overall cut, length and color — just lightly restyled and polished '
      . 'for the runway; do NOT add a big wig or voluminous styling. This is the '
      . 'SAME hairstyle used in the matching beauty-closeup portrait, so keep them '
      . 'consistent.',
      ' Give them a deliberately UNDERSTATED, decidedly NOT-big hairstyle — sleek '
      . 'and flat (severely slicked-back, a tiny neat bun, or plastered-down) — '
      . 'played completely straight so it reads as quietly funny.',
      ' Give them ' . $wildStyles[array_rand($wildStyles)] . ', played completely '
      . 'deadpan and serious.',
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

    $prompts = [
      $base . $hair[0] . $scenes[0] . $finish,
      $base . $hair[1] . $scenes[1] . $finish,
      $base . $hair[2] . $scenes[2] . $finish,
    ];

    // Bold, unnatural dyed hair colors for the random "bright color" flourish.
    $hairColors = [
      'hot pink',
      'electric blue',
      'platinum blond',
      'bright snow-white',
      'multicolored rainbow streaks',
      'a two-tone duotone split of two contrasting bright colors',
    ];

    // 80% of the time exactly one image gets a bright dyed color; the other 20%
    // two images do. When two are colored they share the same color 35% of the
    // time and use two different colors the rest.
    $colorCount = random_int(1, 100) <= 80 ? 1 : 2;

    // Which image(s) get colored — distinct, random. Image 0 is excluded so its
    // hair keeps the reference cut AND natural color, staying consistent with
    // the matching beauty-closeup portrait; the dye only lands on 1 and/or 2.
    $targets = [1, 2];
    shuffle($targets);
    $targets = array_slice($targets, 0, $colorCount);

    if ($colorCount === 1) {
      $colors = [$hairColors[array_rand($hairColors)]];
    }
    elseif (random_int(1, 100) <= 35) {
      $same = $hairColors[array_rand($hairColors)];
      $colors = [$same, $same];
    }
    else {
      $keys = (array) array_rand($hairColors, 2);
      shuffle($keys);
      $colors = [$hairColors[$keys[0]], $hairColors[$keys[1]]];
    }

    foreach ($targets as $slot => $target) {
      $prompts[$target] .= sprintf(
        ' The hair ON THEIR HEAD is dyed a bold, unnatural color — %s — which '
        . 'overrides the reference head-hair color. Any FACIAL HAIR is NOT dyed '
        . 'and keeps the subject\'s natural reference color.',
        $colors[$slot],
      );
    }

    return $prompts;
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
    $props = array_values(array_filter(
      array_map('trim', (array) ($analysis['props'] ?? [])),
      static fn ($p) => $p !== '',
    ));
    $propsHint = $props !== []
      ? sprintf(' or that appears among the suggested props (%s)', implode(', ', $props))
      : '';

    return sprintf(
      'Ultra-glossy high-fashion BEAUTY CLOSEUP portrait, an intentionally absurd '
      . 'and humorous parody of an over-produced Balenciaga campaign — deadpan '
      . 'serious yet ridiculous. Keep the SAME person as the reference image and '
      . 'copy their facial EXPRESSION from the reference. Match their FACIAL HAIR '
      . 'exactly — same beard/moustache/goatee/stubble shape, length and color, '
      . 'or keep them clean-shaven if they have none. For the HAIR, keep it clearly '
      . 'recognizable as the same person — similar cut, length and natural color — '
      . 'but you may deviate a little more than a plain copy and STYLE IT UP for the '
      . 'runway with extra polish, shape and editorial flair (still no giant wig or '
      . 'extreme volume). If the reference photo includes a hat or head covering, '
      . 'keep it and restyle it to fit the aesthetic; a chic hat or headpiece is also '
      . 'welcome if it suits the look%s. Restyle them into the '
      . '"%s" aesthetic, borrowing the same couture styling as the runway '
      . 'looks%s. Tight head-and-shoulders framing; subtly '
      . 'emphasize the cheekbones and jawline with sculpting contour and light so '
      . 'they look a bit sharper (flattering, not grotesque). Dramatic beauty '
      . 'lighting, cinematic, hyper-detailed, luxury magazine quality. Do NOT '
      . 'render any text, letters, words, numbers, captions, watermarks, logos, '
      . 'or signage anywhere in the image.',
      $propsHint,
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
