<?php

declare(strict_types=1);

namespace Drupal\fashion_video\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for the Fashion Video module.
 */
final class SettingsForm extends ConfigFormBase {

  private const CONFIG_NAME = 'fashion_video.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'fashion_video_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::CONFIG_NAME);
    $max = (int) $config->get('max_encodes');

    $form['max_encodes'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum concurrent video encodes'),
      '#description' => $this->t('How many final videos ffmpeg may assemble at the same time across the whole site. ffmpeg is CPU/RAM heavy, so keep this low on small servers. Additional requests wait and retry.'),
      '#default_value' => $max >= 1 ? $max : 1,
      '#min' => 1,
      '#max' => 16,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['talking_head_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Talking-head (lip sync) provider'),
      '#description' => $this->t('Which service animates the generated face to speak the recorded voice line. The matching API key must be configured (DID_API_KEY / HEYGEN_API_KEY).'),
      '#options' => [
        'heygen' => $this->t('HeyGen'),
        'did' => $this->t('D-ID'),
      ],
      '#default_value' => $config->get('talking_head_provider') ?: 'heygen',
      '#required' => TRUE,
    ];

    $form['heygen_engine'] = [
      '#type' => 'select',
      '#title' => $this->t('HeyGen rendering engine'),
      '#description' => $this->t('Only used when the provider is HeyGen. Avatar IV animates an arbitrary image directly; Avatar III is a photo-avatar pipeline and requires a pre-created avatar (not yet supported here).'),
      '#options' => [
        'avatar_iv' => $this->t('Avatar IV'),
        'avatar_iii' => $this->t('Avatar III'),
      ],
      '#default_value' => $config->get('heygen_engine') ?: 'avatar_iv',
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="talking_head_provider"]' => ['value' => 'heygen'],
        ],
      ],
    ];

    $form['include_ken_burns'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include Ken Burns still montage in the final video'),
      '#description' => $this->t('When on, the runway stills are pan/zoomed around the talking clip. When off, the final video is just the (normalized) talking clip with the ducked song bed.'),
      '#default_value' => (bool) $config->get('include_ken_burns'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::CONFIG_NAME)
      ->set('max_encodes', (int) $form_state->getValue('max_encodes'))
      ->set('talking_head_provider', (string) $form_state->getValue('talking_head_provider'))
      ->set('heygen_engine', (string) $form_state->getValue('heygen_engine'))
      ->set('include_ken_burns', (bool) $form_state->getValue('include_ken_burns'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
