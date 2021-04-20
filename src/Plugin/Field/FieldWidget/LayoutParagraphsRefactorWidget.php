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
    FormBuilder $form_builder
    ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->tempstore = $tempstore;
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutPluginManager = $layout_plugin_manager;
    $this->formBuilder = $form_builder;

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
      $container->get('form_builder')
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
          'nestedSections' => FALSE,
          'requireSections' => TRUE,
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

}
