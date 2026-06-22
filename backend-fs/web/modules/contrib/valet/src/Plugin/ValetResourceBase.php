<?php

namespace Drupal\valet\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for Valet Resource plugins.
 */
abstract class ValetResourceBase extends PluginBase implements ValetResourceInterface {
  use RefinableCacheableDependencyTrait;

  /**
   * Gets the base configuration defaults.
   */
  protected function baseConfigurationDefaults() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * Gets the default configuration with base defaults merged in.
   */
  public function getDefaultConfiguration() {
    return NestedArray::mergeDeep(
      $this->baseConfigurationDefaults(),
      $this->defaultConfiguration()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function &getConfiguration() {
    return $this->configuration;
  }

  /**
   * Gets a configuration value.
   */
  public function &getConfigurationValue($key, $default = NULL) {
    $exists = NULL;
    $value = &NestedArray::getValue($this->getConfiguration(), (array) $key, $exists);
    if (!$exists) {
      $value = $default;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $values) {
    $existing_values = &$this->getConfiguration();
    $existing_values = NestedArray::mergeDeep(
      $this->getDefaultConfiguration(),
      $values
    );
    return $this;
  }

  /**
   * Sets a configuration value.
   */
  public function setConfigurationValue($key, $value) {
    NestedArray::setValue($this->getConfiguration(), (array) $key, $value, TRUE);
    return $this;
  }

  /**
   * Unsets a configuration value.
   */
  public function unsetConfigurationValue($key) {
    NestedArray::unsetValue($this->getConfiguration(), (array) $key);
    return $this;
  }

  /**
   * Checks if a configuration value exists.
   */
  public function hasConfigurationValue($key) {
    $exists = NULL;
    $value = NestedArray::getValue($this->getConfiguration(), (array) $key, $exists);
    return $exists && isset($value);
  }

  /**
   * Checks if a configuration value is empty.
   */
  public function isConfigurationValueEmpty($key) {
    $exists = NULL;
    $value = NestedArray::getValue($this->getConfiguration(), (array) $key, $exists);
    return !$exists || empty($value);
  }

  /**
   * {@inheritdoc}
   */
  public function getResults() {
    return $this->prepareResults();
  }

  /**
   * Build out all results.
   *
   * @return \Drupal\valet\Plugin\ValetItem[]
   *   An array of items.
   */
  protected function prepareResults() {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * Creates a generic configuration form for all configuration types.
   * Individual configuration plugins can add elements to this form by
   * overriding ConfigurableFormTrait::configurationForm(). Most configuration
   * plugins should not override this method unless they need to alter the
   * generic form elements.
   *
   * @see \Drupal\valet\Plugin\ValetResourceBase::configurationForm()
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form += $this->configurationForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * Most configuration plugins should not override this method. To add
   * validation for a specific configuration type, override
   * ConfigurableFormTrait::configurationValidate().
   *
   * @see \Drupal\valet\Plugin\ValetResourceBase::configurationValidate()
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configurationValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function configurationValidate(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   *
   * Most configuration plugins should not override this method. To add
   * submission handling for a specific configuration type, override
   * ConfigurableFormTrait::configurationSubmit().
   *
   * @see \Drupal\valet\Plugin\ValetResourceBase::configurationSubmit()
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configurationSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSubmit(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  protected function getIcon($string) {
    if (function_exists('exo_icon')) {
      if ($icon = exo_icon($string)->match([
        'valet',
        'admin',
        'local_task',
      ])->getIcon()) {
        /** @var \Drupal\exo_icon\ExoIcon|\Drupal\neo_icon\Icon $icon */
        return $icon->getSelector();
      }
    }
    elseif (function_exists('neo_icon')) {
      $neoIcon = neo_icon($string, NULL, NULL, [
        'valet',
        'admin',
      ]);
      if ($icon = $neoIcon->getIcon()) {
        return $icon->getSelector();
      }
    }
    return '';
  }

}
