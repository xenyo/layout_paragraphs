<?php

namespace Drupal\layout_paragraphs\Plugin\Field\FieldWidget;

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
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\ParagraphInterface;
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
   * Indicates whether the current widget instance is in translation.
   *
   * @var bool
   */
  protected $isTranslating;

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

    // Detect if we are translating.
    $this->initIsTranslating($form_state, $items->getEntity());
    $this->initTranslations($items, $form_state);

    $this->layoutParagraphsLayout = new LayoutParagraphsLayout($items, $this->getSettings() + ['is_translating' => $this->isTranslating]);
    if (!$form_state->getUserInput()) {
      $this->tempstore->set($this->layoutParagraphsLayout);
    }
    else {
      $this->layoutParagraphsLayout = $this->tempstore->get($this->layoutParagraphsLayout);
    }
    $element += [
      '#type' => 'fieldset',
      '#title' => $this->fieldDefinition->getLabel(),
      'layout_paragraphs_builder' => [
        '#type' => 'layout_paragraphs_builder',
        '#layout_paragraphs_layout' => $this->layoutParagraphsLayout,
        '#langcode' => $form_state->get('langcode'),
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $path = array_merge($form['#parents'], [$field_name]);
    $layout_paragraphs_layout = $this->tempstore->get(new LayoutParagraphsLayout($items));
    $values = [];
    foreach ($layout_paragraphs_layout->getParagraphsReferenceField() as $delta => $item) {
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

  /**
   * Determine if widget is in translation.
   *
   * Initializes $this->isTranslating.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\ContentEntityInterface $host
   *   The host entity.
   */
  protected function initIsTranslating(FormStateInterface $form_state, ContentEntityInterface $host) {
    if ($this->isTranslating != NULL) {
      return;
    }
    $this->isTranslating = FALSE;
    if (!$host->isTranslatable()) {
      return;
    }
    if (!$host->getEntityType()->hasKey('default_langcode')) {
      return;
    }
    $default_langcode_key = $host->getEntityType()->getKey('default_langcode');
    if (!$host->hasField($default_langcode_key)) {
      return;
    }

    if (!empty($form_state->get('content_translation'))) {
      // Adding a language through the ContentTranslationController.
      $this->isTranslating = TRUE;
    }
    $langcode = $form_state->get('langcode');
    if ($host->hasTranslation($langcode) && $host->getTranslation($langcode)->get($default_langcode_key)->value == 0) {
      // Editing a translation.
      $this->isTranslating = TRUE;
    }
  }

  /**
   * Initialize translations for item list.
   *
   * Makes sure all components have a translation for the current
   * language and creates them if necessary.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The paragraphs reference field.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function initTranslations(FieldItemListInterface &$items, FormStateInterface $form_state) {
    $langcode = $form_state->get('langcode');
    /** @var \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem $item */
    foreach ($items as $delta => $item) {
      if (!empty($item->entity) && $item->entity instanceof ParagraphInterface) {
        if (!$this->isTranslating) {
          // Set the langcode if we are not translating.
          $langcode_key = $item->entity->getEntityType()->getKey('langcode');
          if ($item->entity->get($langcode_key)->value != $langcode) {
            // If a translation in the given language already exists,
            // switch to that. If there is none yet, update the language.
            if ($item->entity->hasTranslation($langcode)) {
              $item->entity = $item->entity->getTranslation($langcode);
            }
            else {
              $item->entity->set($langcode_key, $langcode);
            }
          }
        }
        else {
          // Add translation if missing for the target language.
          if (!$item->entity->hasTranslation($langcode)) {
            // Get the selected translation of the paragraph entity.
            $entity_langcode = $item->entity->language()->getId();
            $source = $form_state->get(['content_translation', 'source']);
            $source_langcode = $source ? $source->getId() : $entity_langcode;
            // Make sure the source language version is used if available.
            // Fetching the translation without this check could lead valid
            // scenario to have no paragraphs items in the source version of
            // to an exception.
            if ($item->entity->hasTranslation($source_langcode)) {
              $entity = $item->entity->getTranslation($source_langcode);
            }
            // The paragraphs entity has no content translation source field
            // if no paragraph entity field is translatable,
            // even if the host is.
            if ($item->entity->hasField('content_translation_source')) {
              // Initialise the translation with source language values.
              $item->entity->addTranslation($langcode, $entity->toArray());
              $translation = $item->entity->getTranslation($langcode);
              $manager = \Drupal::service('content_translation.manager');
              $manager->getTranslationMetadata($translation)
                ->setSource($item->entity->language()->getId());
            }
          }
          // If any paragraphs type is translatable do not switch.
          if ($item->entity->hasField('content_translation_source')) {
            // Switch the paragraph to the translation.
            $item->entity = $item->entity->getTranslation($langcode);
          }
        }
        $items[$delta]->entity = $item->entity;
      }
    }
  }

}
