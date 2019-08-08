<?php

namespace Drupal\oe_multilingual_url_suffix\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NegotiationUrlSuffixForm.
 *
 * @package Drupal\oe_multilingual_url_suffix\Form
 */
class NegotiationUrlSuffixForm extends ConfigFormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new NegotiationUrlSuffixForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager) {
    parent::__construct($config_factory);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'language_negotiation_configure_url_suffix_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['language.negotiation'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    global $base_url;
    $config = $this->config('language.negotiation');

    $form['suffix'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => $this->t('Path suffix configuration'),
      '#open' => TRUE,
      '#description' => $this->t('Language codes or other custom text to use as a path suffix for URL language detection. For the selected fallback language, this value may be left blank. <strong>Modifying this value may break existing URLs. Use with caution in a production environment.</strong> Example: Specifying "deutsch" as the path suffix code for German results in URLs like "example.com/contact_deutsch".'),
    ];

    $languages = $this->languageManager->getLanguages();
    $suffixes = $config->get('url_suffixes');
    foreach ($languages as $langcode => $language) {
      $t_args = ['%language' => $language->getName(), '%langcode' => $language->getId()];
      $form['suffix'][$langcode] = [
        '#type' => 'textfield',
        '#title' => $language->isDefault() ? $this->t('%language (%langcode) path suffix (Default language)', $t_args) : $this->t('%language (%langcode) path suffix', $t_args),
        '#maxlength' => 64,
        '#default_value' => isset($suffixes[$langcode]) ? $suffixes[$langcode] : substr($langcode, 0, 2),
        '#field_prefix' => $base_url . '/index_',
      ];
    }

    $form_state->setRedirect('language.negotiation');

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $languages = $this->languageManager->getLanguages();

    // Count repeated values for uniqueness check.
    $count = array_count_values($form_state->getValue('suffix'));
    foreach ($languages as $langcode => $language) {
      $value = $form_state->getValue(['suffix', $langcode]);
      if ($value === '') {
        // Throw a form error if the suffix is blank for any language,
        // although it is required for selected negotiation type.
        $form_state->setErrorByName("suffix][$langcode", $this->t('The suffix may only be left blank for the <a href=":url">selected detection fallback language.</a>', [
          ':url' => $this->getUrlGenerator()->generate('language.negotiation_selected'),
        ]));
      }
      elseif (strpos($value, '/') !== FALSE) {
        // Throw a form error if the string contains a slash,
        // which would not work.
        $form_state->setErrorByName("suffix][$langcode", $this->t('The suffix may not contain a slash.'));
      }
      elseif (isset($count[$value]) && $count[$value] > 1) {
        // Throw a form error if there are two languages with the same suffix.
        $form_state->setErrorByName("suffix][$langcode", $this->t('The suffix for %language, %value, is not unique.', ['%language' => $language->getName(), '%value' => $value]));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save configured suffixes.
    $this->config('language.negotiation')
      ->set('url_suffixes', $form_state->getValue('suffix'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
