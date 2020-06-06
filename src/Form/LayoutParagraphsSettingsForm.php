<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LayoutParagraphsSettingsForm.
 */
class LayoutParagraphsSettingsForm extends ConfigFormBase {

  /**
   * The typed config service.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager
  ) {
    parent::__construct($config_factory);
    $this->typedConfigManager = $typedConfigManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'layout_paragraphs.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('layout_paragraphs.settings');
    $erl_config_schema = $this->typedConfigManager->getDefinition('layout_paragraphs.settings') + ['mapping' => []];
    $erl_config_schema = $erl_config_schema['mapping'];

    $form['show_paragraph_labels'] = [
      '#type' => 'checkbox',
      '#title' => $erl_config_schema['show_paragraph_labels']['label'],
      '#description' => $erl_config_schema['show_paragraph_labels']['description'],
      '#default_value' => $config->get('show_paragraph_labels'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('layout_paragraphs.settings');
    $config->set('show_paragraph_labels', $form_state->getValue('show_paragraph_labels'));
    $config->save();
    // Confirmation on form submission.
    $this->messenger()->addMessage($this->t('The Entity Reference Layout settings have been saved.'));
  }

}
