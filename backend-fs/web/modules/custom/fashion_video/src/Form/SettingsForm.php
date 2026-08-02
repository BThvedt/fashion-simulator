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
    $max = (int) $this->config(self::CONFIG_NAME)->get('max_encodes');

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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::CONFIG_NAME)
      ->set('max_encodes', (int) $form_state->getValue('max_encodes'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
