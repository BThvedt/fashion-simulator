<?php

declare(strict_types=1);

namespace Drupal\fashion_video;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Selects the configured talking-head provider (HeyGen or D-ID) and delegates.
 *
 * The controller depends on this manager (service id `fashion_video.talking_head`)
 * rather than a concrete provider, so switching providers is a config change
 * (fashion_video.settings:talking_head_provider) with no code edits.
 */
final class TalkingHeadManager implements TalkingHeadInterface {

  public function __construct(
    private readonly TalkingHeadInterface $heygen,
    private readonly TalkingHeadInterface $did,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns the active provider based on config (defaults to HeyGen).
   */
  private function provider(): TalkingHeadInterface {
    $choice = (string) $this->configFactory->get('fashion_video.settings')->get('talking_head_provider');
    return $choice === 'did' ? $this->did : $this->heygen;
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigured(): bool {
    return $this->provider()->isConfigured();
  }

  /**
   * {@inheritdoc}
   */
  public function createTalk(string $sourceUrl, string $audioUrl): ?string {
    return $this->provider()->createTalk($sourceUrl, $audioUrl);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchStatus(string $talkId): array {
    return $this->provider()->fetchStatus($talkId);
  }

  /**
   * {@inheritdoc}
   */
  public function download(string $resultUrl): ?string {
    return $this->provider()->download($resultUrl);
  }

}
