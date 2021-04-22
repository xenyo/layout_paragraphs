<?php

namespace Drupal\layout_paragraphs\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityDisplayBase;
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

/**
 * Entity Reference with Layout field widget (refactored).
 *
 * @FieldWidget(
 *   id = "layout_paragraphs_refactor",
 *   label = @Translation("Layout Paragraphs - refactored"),
 *   description = @Translation("Layout builder for paragraphs."),
 *   multiple_values = TRUE,
 *   field_types = {
 *     "entity_reference_revisions"
 *   },
 * )
 */
class LayoutParagraphsRefactorWidget extends WidgetBase implements ContainerFactoryPluginInterface {

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
    EntityDisplayRepositoryInterface $entity_display_repository
    ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->tempstore = $tempstore;
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutPluginManager = $layout_plugin_manager;
    $this->formBuilder = $form_builder;
    $this->entityDisplayRepository = $entity_display_repository;

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
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $layout_paragraphs_layout = new LayoutParagraphsLayout($items);
    if (!$form_state->getUserInput()) {
      $this->tempstore->set($layout_paragraphs_layout);
    }
    else {
      $layout_paragraphs_layout = $this->tempstore->get($layout_paragraphs_layout);
    }
    $element += [
      'layout_paragraphs_builder' => [
        '#type' => 'layout_paragraphs_builder',
        '#layout_paragraphs_layout' => $layout_paragraphs_layout,
        // @todo derive options from the widget configuration settings.
        // See \Drupal\layout_paragraphs\Plugin\Field\FieldWidget\LayoutParagraphsWidget
        '#options' => [
          'nestingDepth' => $this->getSetting('nesting_depth'),
          'requireSections' => $this->getSetting('require_layouts'),
          'saveButtonIds' => [
            'edit-submit',
            'edit-preview',
            'edit-delete',
          ],
        ],
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
        $values[$delta] = [
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
      'preview_view_mode' => 'default',
      'nesting_depth' => 0,
      'require_layouts' => 0,
    ];

    return $defaults;
  }

}
