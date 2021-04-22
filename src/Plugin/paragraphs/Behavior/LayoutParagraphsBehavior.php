<?php

namespace Drupal\layout_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_paragraphs\LayoutParagraphsSection;
use Drupal\layout_paragraphs\LayoutParagraphsService;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Component\Utility\Html;

/**
 * Provides a way to define grid based layouts.
 *
 * @ParagraphsBehavior(
 *   id = "layout_paragraphs",
 *   label = @Translation("Layout Paragraphs"),
 *   description = @Translation("Integrates paragraphs with layout discovery and layout API."),
 *   weight = 0
 * )
 */
class LayoutParagraphsBehavior extends ParagraphsBehaviorBase {

  /**
   * The layout plugin manager service.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutPluginManager;

  /**
   * The entity type manager service.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   *   The entity type manager service.
   */
  protected $entityTypeManager;

  /**
   * The layout paragraphs service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsService
   */
  protected $layoutParagraphsService;

  /**
   * A reference to the paragraph instance.
   *
   * @var [type]
   */
  protected $paragraph;

  /**
   * ParagraphsLayoutPlugin constructor.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   This plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_plugin_manager
   *   The grid discovery service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The grid discovery service.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsService $layout_paragraphs_service
   *   The layout paragraphs service.
   */
  public function __construct(
      array $configuration,
      $plugin_id,
      $plugin_definition,
      EntityFieldManager $entity_field_manager,
      LayoutPluginManagerInterface $layout_plugin_manager,
      EntityTypeManagerInterface $entity_type_manager,
      LayoutParagraphsService $layout_paragraphs_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->layoutPluginManager = $layout_plugin_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutParagraphsService = $layout_paragraphs_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.core.layout'),
      $container->get('entity_type.manager'),
      $container->get('layout_paragraphs')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function buildBehaviorForm(
    ParagraphInterface $paragraph,
    array &$form,
    FormStateInterface $form_state
  ) {

    $layout_paragraphs_section = new LayoutParagraphsSection($paragraph);
    $layout_settings = $layout_paragraphs_section->getSetting('config');
    $available_layouts = $this->configuration['available_layouts'];
    $layout_id = $form_state->getValue('layout') ?? $layout_paragraphs_section->getLayoutId();
    $default_value = !empty($layout_id) ? $layout_id : key($available_layouts);
    $plugin_instance = $this->layoutPluginManager->createInstance($default_value, $layout_settings);
    $plugin_form = $this->getLayoutPluginForm($plugin_instance);

    $wrapper_id = Html::getUniqueId('layout-options');
    $form['layout'] = [
      '#title' => $this->t('Choose a layout:'),
      '#type' => 'layout_select',
      '#options' => $available_layouts,
      '#default_value' => $default_value,
      '#ajax' => [
        '#event' => 'onchange',
        '#wrapper' => $wrapper_id,
        '#callback' => [$this, 'ajaxUpdateOptions'],
      ],
    ];
    if ($plugin_form) {
      $form['config'] = [
        '#prefix' => '<div class="' . $wrapper_id . '">',
        '#suffix' => '</div>',
        '#type' => 'fieldset',
        '#title' => $this->t('Layout Options'),
      ];
      $form['config'] += $plugin_form->buildConfigurationForm([], $form_state);
    }

    return $form;
  }

  /**
   * Ajax callback - returns the updated layout options form.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The layout options form.
   */
  public function ajaxUpdateOptions(array $form, FormStateInterface $form_state) {
    if (isset($form['config'])) {
      return $form['config'];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'available_layouts' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = $this->layoutPluginManager->getLayoutOptions();
    $available_layouts = $this->configuration['available_layouts'];
    $form['available_layouts'] = [
      '#title' => $this->t('Available Layouts'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => $options,
      '#default_value' => array_keys($available_layouts),
      '#size' => count($options) < 8 ? count($options) * 2 : 10,
      '#required' => FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('available_layouts'))) {
      $form_state->setErrorByName('available_layouts', $this->t('You must select at least one layout.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $available_layouts = array_filter($form_state->getValue('available_layouts'));
    foreach ($available_layouts as $layout_name) {
      $layout = $this->layoutPluginManager->getDefinition($layout_name);
      $this->configuration['available_layouts'][$layout_name] = $layout->getLabel();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    if (empty($build['regions']) && LayoutParagraphsSection::isLayoutComponent($paragraph)) {
      $build['regions'] = $this->layoutParagraphsService->renderLayoutSection($build, $paragraph, $view_mode);
    }
  }

  /**
   * Retrieves the plugin form for a given layout.
   *
   * @param \Drupal\Core\Layout\LayoutInterface $layout
   *   The layout plugin.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface|null
   *   The plugin form for the layout.
   */
  protected function getLayoutPluginForm(LayoutInterface $layout) {
    if ($layout instanceof PluginWithFormsInterface) {
      try {
        return $this->pluginFormFactory->createInstance($layout, 'configure');
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('layout_paragraphs')->error('Erl, Layout Configuration', $e);
      }
    }

    if ($layout instanceof PluginFormInterface) {
      return $layout;
    }

    return NULL;
  }

}