<?php

namespace Drupal\layout_paragraphs\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Layout paragraphs widget.
 *
 * @FieldWidget(
 *   id = "layout_paragraphs",
 *   label = @Translation("Layout Paragraphs"),
 *   description = @Translation("Layout builder for paragraphs."),
 *   multiple_values = TRUE,
 *   field_types = {
 *     "entity_reference_revisions"
 *   },
 * )
 */
class LayoutParagraphsWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The tempstore.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $tempstore;

  /**
   * The Entity Type Manager service property.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Layouts Manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManager
   */
  protected $layoutPluginManager;

  /**
   * The layout paragraphs layout.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layoutParagraphsLayout;

  /**
   * The layout paragraphs layout tempstore storage key.
   *
   * @var string
   */
  protected $storageKey;

  /**
   * The form builder service.
   *
   * @var Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    LayoutParagraphsLayoutTempstoreRepository $tempstore,
    EntityTypeManagerInterface $entity_type_manager,
    LayoutPluginManager $layout_plugin_manager,
    FormBuilder $form_builder,
    EntityDisplayRepositoryInterface $entity_display_repository,
    ConfigFactoryInterface $config_factory
    ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->tempstore = $tempstore;
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutPluginManager = $layout_plugin_manager;
    $this->formBuilder = $form_builder;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->config = $config_factory->get('layout_paragraphs.settings');

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.core.layout'),
      $container->get('form_builder'),
      $container->get('entity_display.repository'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $parents = array_merge($form['#parents'], [
      $this->fieldDefinition->getName(),
      'layout_paragraphs_storage_key',
    ]);
    $layout_paragraphs_storage_key = NestedArray::getValue($input, $parents);
    // If the form is being rendered for the first time, create a new Layout
    // Paragraphs Layout instance, save it to tempstore, and store the key.
    if (empty($layout_paragraphs_storage_key)) {
      $this->layoutParagraphsLayout = new LayoutParagraphsLayout($items, $this->getSettings());
      $this->tempstore->set($this->layoutParagraphsLayout);
      $layout_paragraphs_storage_key = $this->tempstore->getStorageKey($this->layoutParagraphsLayout);
    }
    // On subsequent form renders, this loads the correct Layout Paragraphs
    // Layout from the tempstore using the storage key.
    else {
      $this->layoutParagraphsLayout = $this->tempstore->getWithStorageKey($layout_paragraphs_storage_key);
    }
    $element += [
      '#type' => 'fieldset',
      '#title' => $this->fieldDefinition->getLabel(),
      'layout_paragraphs_builder' => [
        '#type' => 'layout_paragraphs_builder',
        '#layout_paragraphs_layout' => $this->layoutParagraphsLayout,
      ],
      // Stores the Layout Paragraphs Layout storage key.
      'layout_paragraphs_storage_key' => [
        '#type' => 'hidden',
        '#default_value' => $layout_paragraphs_storage_key,
      ],
    ];
    if ($source = $form_state->get(['content_translation', 'source'])) {
      $element['layout_paragraphs_builder']['#source_langcode'] = $source->getId();
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    // Load the correct layout paragraphs layout instnace using the value
    // passed in the layout_instance_id hidden field.
    $path = array_merge($form['#parents'], [$field_name]);
    $layout_paragraphs_storage_key = $form_state->getValue(array_merge($path, ['layout_paragraphs_storage_key']));
    if (!empty($layout_paragraphs_storage_key)) {
      $layout_paragraphs_layout = $this->tempstore->getWithStorageKey($layout_paragraphs_storage_key);
      $values = [];
      foreach ($layout_paragraphs_layout->getParagraphsReferenceField() as $item) {
        if ($item->entity) {
          $entity = $item->entity;
          $entity->setNeedsSave(TRUE);
          $values[] = [
            'entity' => $entity,
            'target_id' => $entity->id(),
            'target_revision_id' => $entity->getRevisionId(),
          ];
        }
      }
      $form_state->setValue($path, $values);
    }
    return parent::extractFormValues($items, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $entity_type_id = $this->getFieldSetting('target_type');
    $element = parent::settingsForm($form, $form_state);
    $element['preview_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Preview view mode'),
      '#default_value' => $this->getSetting('preview_view_mode'),
      '#options' => $this->entityDisplayRepository->getViewModeOptions($entity_type_id),
      '#description' => $this->t('View mode for the referenced entity preview on the edit form. Automatically falls back to "default", if it is not enabled in the referenced entity type displays.'),
    ];
    $element['nesting_depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Maximum nesting depth'),
      '#options' => range(0, 10),
      '#default_value' => $this->getSetting('nesting_depth'),
      '#description' => $this->t('Choosing 0 will prevent nesting layouts within other layouts.'),
    ];
    $element['require_layouts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require paragraphs to be added inside a layout'),
      '#default_value' => $this->getSetting('require_layouts'),
    ];
    $element['empty_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder message to display when field is empty'),
      '#default_value' => $this->getSetting('empty_message'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Preview view mode: @preview_view_mode', ['@preview_view_mode' => $this->getSetting('preview_view_mode')]);
    $summary[] = $this->t('Maximum nesting depth: @max_depth', ['@max_depth' => $this->getSetting('nesting_depth')]);
    if ($this->getSetting('require_layouts')) {
      $summary[] = $this->t('Paragraphs <b>must be</b> added within layouts.');
    }
    else {
      $summary[] = $this->t('Layouts are optional.');
    }
    $summary[] = $this->t('Maximum nesting depth: @max_depth', ['@max_depth' => $this->getSetting('nesting_depth')]);
    return $summary;
  }

  /**
   * Default settings for widget.
   *
   * @return array
   *   The default settings array.
   */
  public static function defaultSettings() {
    $defaults = parent::defaultSettings();
    $defaults += [
      'empty_message' => '',
      'preview_view_mode' => 'default',
      'nesting_depth' => 0,
      'require_layouts' => 0,
    ];
    return $defaults;
  }

}
