<?php

namespace Drupal\layout_paragraphs\Element;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Template\Attribute;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\layout_paragraphs\LayoutParagraphsSection;
use Drupal\layout_paragraphs\LayoutParagraphsComponent;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\layout_paragraphs\DialogHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a render element for building the Layout Builder UI.
 *
 * @RenderElement("layout_paragraphs_builder")
 *
 * @internal
 *   Plugin classes are internal.
 */
class LayoutParagraphsBuilder extends RenderElement implements ContainerFactoryPluginInterface {

  use DialogHelperTrait;

  /**
   * The layout paragraphs tempstore service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $tempstore;

  /**
   * The entity type manager service.
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
   * The Renderer service property.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * Indicates whether the element is in translation mode.
   *
   * @var bool
   */
  protected $isTranslating;

  /**
   * The source translation language id.
   *
   * @var string
   */
  protected $sourceLangcode;

  /**
   * The layout paragraphs layout object.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layoutParagraphsLayout;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The language code.
   *
   * @var string
   */
  protected $langcode;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LayoutParagraphsLayoutTempstoreRepository $tempstore_repository,
    EntityTypeManagerInterface $entity_type_manager,
    LayoutPluginManager $layout_plugin_manager,
    Renderer $renderer,
    EntityTypeBundleInfo $entity_type_bundle_info,
    EntityRepositoryInterface $entity_repository) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tempstore = $tempstore_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutPluginManager = $layout_plugin_manager;
    $this->renderer = $renderer;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.core.layout'),
      $container->get('renderer'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Properties:
   * - #layout_paragraphs_layout: a LayoutParagraphsLayout instance.
   * - #uuid: if provided, the uuid of the single paragraph to render.
   * - #sounce_langcode: if provided, the source entity language.
   */
  public function getInfo() {
    return [
      '#layout_paragraphs_layout' => NULL,
      '#uuid' => NULL,
      '#source_langcode' => NULL,
      '#theme' => 'layout_paragraphs_builder',
      '#pre_render' => [
        [$this, 'preRender'],
      ],
    ];
  }

  /**
   * Pre-render callback: Renders the UI.
   */
  public function preRender($element) {
    $this->layoutParagraphsLayout = $this->tempstore->get($element['#layout_paragraphs_layout']);
    $this->langcode = $this->entityRepository
      ->getTranslationFromContext($this->layoutParagraphsLayout->getEntity())
      ->language()
      ->getId();

    $this->sourceLangcode = $element['#source_langcode'];
    $element_uuid = $element['#uuid'];
    $preview_view_mode = $this->layoutParagraphsLayout->getSetting('preview_view_mode', 'default');

    $element['#components'] = [];
    if ($this->isTranslating()) {
      $this->initTranslations();
      $element['#translation_warning'] = $this->translationWarning();
    }
    // Build a flat list of component build arrays.
    foreach ($this->layoutParagraphsLayout->getComponents() as $component) {
      /** @var \Drupal\layout_paragraphs\LayoutParagraphsComponent $component */
      $element['#components'][$component->getEntity()->uuid()] = $this->buildComponent($component, $preview_view_mode);
    }

    // Nest child components inside their respective sections and regions.
    foreach ($this->layoutParagraphsLayout->getComponents() as $component) {
      /** @var \Drupal\layout_paragraphs\LayoutParagraphsComponent $component */
      $uuid = $component->getEntity()->uuid();
      if ($component->isLayout()) {
        $section = $this->layoutParagraphsLayout->getLayoutSection($component->getEntity());
        $layout_plugin_instance = $this->layoutPluginInstance($section);
        foreach (array_keys($element['#components'][$uuid]['regions']) as $region_name) {
          foreach ($section->getComponentsForRegion($region_name) as $child_component) {
            $child_uuid = $child_component->getEntity()->uuid();
            $element['#components'][$uuid]['regions'][$region_name][$child_uuid] =& $element['#components'][$child_uuid];
          }
        }
        $element['#components'][$uuid]['regions'] = $layout_plugin_instance->build($element['#components'][$uuid]['regions']);
        $element['#components'][$uuid]['regions']['#weight'] = 1000;
      }
    }

    // If a element #uuid is provided, render the matching element.
    // This is used in cases where a single component needs
    // to be rendered - for example, as part of an AJAX response.
    if ($element_uuid) {
      if (isset($element['#components'][$element_uuid])) {
        return [
          'build' => $element['#components'][$element_uuid],
        ];
      }
    }

    $element['#attributes'] = [
      'class' => [
        'lp-builder',
        'lp-builder-' . $this->layoutParagraphsLayout->id(),
      ],
      'data-lpb-id' => $this->layoutParagraphsLayout->id(),
    ];
    $element['#attached']['library'] = ['layout_paragraphs/builder'];
    $element['#attached']['drupalSettings']['lpBuilder'][$this->layoutParagraphsLayout->id()] = $this->layoutParagraphsLayout->getSettings();
    $element['#is_empty'] = $this->layoutParagraphsLayout->isEmpty();
    $element['#empty_message'] = $this->layoutParagraphsLayout->getSetting('empty_message', $this->t('Start adding content.'));
    if ($this->layoutParagraphsLayout->getSetting('require_layouts', FALSE)) {
      $element['#insert_button'] = $this->insertSectionButton(['layout_paragraphs_layout' => $this->layoutParagraphsLayout->id()]);
    }
    else {
      $element['#insert_button'] = $this->insertComponentButton(['layout_paragraphs_layout' => $this->layoutParagraphsLayout->id()]);
    }
    $element['#root_components'] = [];
    foreach ($this->layoutParagraphsLayout->getRootComponents() as $component) {
      /** @var \Drupal\layout_paragraphs\LayoutParagraphsComponent $component */
      $uuid = $component->getEntity()->uuid();
      $element['#root_components'][$uuid] =& $element['#components'][$uuid];
    }
    if (count($element['#root_components'])) {
      $element['#attributes']['class'][] = 'has-components';
    }
    return $element;
  }

  /**
   * Returns the build array for a single layout component.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsComponent $component
   *   The component to render.
   * @param string $preview_view_mode
   *   The view mode to use for rendering paragraphs.
   *
   * @return array
   *   The build array.
   */
  protected function buildComponent(LayoutParagraphsComponent $component, $preview_view_mode = 'default') {
    $entity = $component->getEntity();
    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    $build = $view_builder->view($entity, $preview_view_mode, $entity->language()->getId());
    $build['#post_render'] = [
      [$this, 'postRenderComponent'],
    ];
    $build['#attributes']['data-uuid'] = $entity->uuid();
    $build['#attributes']['data-type'] = $entity->bundle();
    $build['#attributes']['data-id'] = $entity->id();
    $build['#attributes']['class'][] = 'js-lpb-component';
    if ($entity->isNew()) {
      $build['#attributes']['class'][] = 'is_new';
    }

    $url_params = [
      'layout_paragraphs_layout' => $this->layoutParagraphsLayout->id(),
    ];
    $query_params = [
      'sibling_uuid' => $entity->uuid(),
    ];

    if ($this->editAccess($entity)) {
      $edit_attributes = new Attribute([
        'href' => Url::fromRoute('layout_paragraphs.builder.edit_item', [
          'layout_paragraphs_layout' => $this->layoutParagraphsLayout->id(),
          'component_uuid' => $entity->uuid(),
        ])->toString(),
        'class' => [
          'lpb-edit',
          'use-ajax',
        ],
        'data-dialog-type' => 'dialog',
        'data-dialog-options' => Json::encode($this->dialogSettings($this->layoutParagraphsLayout)),
      ]);
    }
    else {
      $edit_attributes = [];
    }

    if ($this->deleteAccess($entity)) {
      $delete_attributes = new Attribute([
        'href' => Url::fromRoute('layout_paragraphs.builder.delete_item', [
          'layout_paragraphs_layout' => $this->layoutParagraphsLayout->id(),
          'component_uuid' => $entity->uuid(),
        ])->toString(),
        'class' => [
          'lpb-delete',
          'use-ajax',
        ],
        'data-dialog-type' => 'dialog',
        'data-dialog-options' => Json::encode([
          'modal' => TRUE,
          'target' => $this->dialogId($this->layoutParagraphsLayout),
        ]),
      ]);
    }
    else {
      $delete_attributes = [];
    }

    $build['controls'] = [
      '#theme' => 'layout_paragraphs_builder_controls',
      '#attributes' => [
        'class' => [
          'lpb-controls',
        ],
      ],
      '#label' => $entity->getParagraphType()->label,
      '#edit_attributes' => $edit_attributes,
      '#delete_attributes' => $delete_attributes,
      '#weight' => -10001,
    ];
    if ($component->isLayout()) {
      $build['controls']['#attributes']['class'][] = 'is-layout';
    }

    if ($this->createAccess()) {
      if (!$component->getParentUuid() && $this->layoutParagraphsLayout->getSetting('require_layouts')) {
        $build['insert_before'] = $this->insertSectionButton($url_params, $query_params + ['placement' => 'before'], -10000, ['before']);
        $build['insert_after'] = $this->insertSectionButton($url_params, $query_params + ['placement' => 'after'], 10000, ['after']);
      }
      else {
        $build['insert_before'] = $this->insertComponentButton($url_params, $query_params + ['placement' => 'before'], -10000, ['before']);
        $build['insert_after'] = $this->insertComponentButton($url_params, $query_params + ['placement' => 'after'], 10000, ['after']);
      }
    }

    if ($component->isLayout()) {
      $section = $this->layoutParagraphsLayout->getLayoutSection($entity);
      $layout_instance = $this->layoutPluginInstance($section);
      $region_names = $layout_instance->getPluginDefinition()->getRegionNames();

      $build['#attributes']['class'][] = 'lpb-layout';
      $build['#attributes']['data-layout'] = $section->getLayoutId();
      $build['#layout_plugin_instance'] = $layout_instance;
      $build['regions'] = [];
      foreach ($region_names as $region_name) {
        $url_params = [
          'layout_paragraphs_layout' => $this->layoutParagraphsLayout->id(),
        ];
        $query_params = [
          'parent_uuid' => $entity->uuid(),
          'region' => $region_name,
        ];
        $build['regions'][$region_name] = [
          '#attributes' => [
            'class' => [
              'js-lpb-region',
            ],
            'data-region' => $region_name,
            'data-region-uuid' => $entity->uuid() . '-' . $region_name,
          ],
        ];
        if ($this->createAccess()) {
          $build['regions'][$region_name]['insert_button'] = $this->insertComponentButton($url_params, $query_params, 10000, ['center']);
        }
      }
    }
    return $build;
  }

  /**
   * Filters problematic markup from rendered component.
   *
   * @param mixed $content
   *   The rendered content.
   * @param array $element
   *   The render element array.
   *
   * @return mixed
   *   The filtered content.
   */
  public function postRenderComponent($content, array $element) {
    if (strpos($content, '<form') !== FALSE) {
      // Rendering forms within the paragraph preview will break the parent
      // form, so we need to strip form tags, "required" attributes,
      // "form_token", and "form_id" to prevent the previewed form from
      // being processed.
      $search = [
        '<form',
        '</form>',
        'required="required"',
        'name="form_token"',
        'name="form_id"',
      ];
      $replace = [
        '<div',
        '</div>',
        '',
        '',
        '',
      ];
      $content = str_replace($search, $replace, $content);
    }
    return $content;
  }

  /**
   * Returns the render array for a insert component button.
   *
   * @param array[] $route_params
   *   The route parameters for the link.
   * @param array[] $query_params
   *   The query paramaters for the link.
   * @param int $weight
   *   The weight of the button element.
   * @param array[] $classes
   *   A list of classes to append to the container.
   *
   * @return array
   *   The render array.
   */
  protected function insertComponentButton(array $route_params = [], array $query_params = [], int $weight = 0, array $classes = []) {
    return [
      '#type' => 'link',
      '#title' => Markup::create('<span class="visually-hidden">' . $this->t('Choose component') . '</span>'),
      '#weight' => $weight,
      '#attributes' => [
        'class' => array_merge(['lpb-btn--add', 'use-ajax'], $classes),
        'data-dialog-type' => 'dialog',
        'data-dialog-options' => Json::encode([
          'target' => $this->dialogId($this->layoutParagraphsLayout),
          'modal' => TRUE,
        ]),
      ],
      '#url' => Url::fromRoute('layout_paragraphs.builder.choose_component', $route_params, ['query' => $query_params]),
    ];
  }

  /**
   * Returns the render array for a create section button.
   *
   * @param array[] $route_params
   *   The route parameters for the link.
   * @param array[] $query_params
   *   The query parameters for the link.
   * @param int $weight
   *   The weight of the button element.
   * @param array[] $classes
   *   A list of classes to append to the container.
   *
   * @return array
   *   The render array.
   */
  protected function insertSectionButton(array $route_params = [], array $query_params = [], int $weight = 0, array $classes = []) {
    return [
      '#type' => 'link',
      '#title' => Markup::create($this->t('Add section')),
      '#attributes' => [
        'class' => array_merge(['lpb-btn', 'use-ajax'], $classes),
        'data-dialog-type' => 'dialog',
        'data-dialog-options' => Json::encode([
          'modal' => TRUE,
          'target' => $this->dialogId($this->layoutParagraphsLayout),
        ]),
      ],
      '#url' => Url::fromRoute('layout_paragraphs.builder.choose_component', $route_params, ['query' => $query_params]),
    ];
  }

  /**
   * Returns an array of dialog options.
   */
  protected function dialogOptions() {
    return [
      'modal' => TRUE,
      'target' => $this->dialogId($this->layoutParagraphsLayout),
    ];
  }

  /**
   * Builds a translation warning message.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translation warning.
   */
  protected function translationWarning() {
    if ($this->isTranslating()) {
      if ($this->supportsAsymmetricTranslations()) {
        return $this->t('You are in translation mode. Changes will only affect the current language.');
      }
      else {
        return $this->t('You are in translation mode. You cannot add or remove items while translating. Reordering items will affect all languages.');
      }
    }
  }

  /**
   * Loads a layout plugin instance for a layout paragraph section.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsSection $section
   *   The section.
   */
  protected function layoutPluginInstance(LayoutParagraphsSection $section) {
    $layout_id = $section->getLayoutId();
    $layout_config = $section->getLayoutConfiguration();
    $layout_instance = $this->layoutPluginManager->createInstance($layout_id, $layout_config);
    return $layout_instance;
  }

  /**
   * Returns an AccessResult object.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return bool
   *   True if user can edit.
   */
  protected function editAccess(ParagraphInterface $paragraph) {
    return $paragraph->access('edit');
  }

  /**
   * Returns an AccessResult object.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return bool
   *   True if user can edit.
   */
  protected function deleteAccess(ParagraphInterface $paragraph) {
    if ($this->isTranslating() && !($this->supportsAsymmetricTranslations())) {
      $access = new AccessResultForbidden('Cannot delete paragraphs while in translation mode.');
      return $access->isAllowed();
    }
    return $paragraph->access('delete');
  }

  /**
   * Returns an AccessResult object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   True if user can edit.
   */
  protected function createAccess() {
    $access = new AccessResultAllowed();
    if ($this->isTranslating() && !($this->supportsAsymmetricTranslations())) {
      $access = new AccessResultForbidden('Cannot add paragraphs while in translation mode.');
    }
    return $access->isAllowed();
  }

  /**
   * Returns TRUE if in translation context.
   *
   * @return bool
   *   TRUE if translating.
   */
  protected function isTranslating() {
    if (is_null($this->isTranslating)) {
      $this->isTranslating = FALSE;
      /** @var \Drupal\Core\Entity\ContentEntityInterface $host */
      $host = $this->layoutParagraphsLayout->getEntity();
      $default_langcode_key = $host->getEntityType()->getKey('default_langcode');
      if (
        $host->hasTranslation($this->langcode)
        && $host->getTranslation($this->langcode)->get($default_langcode_key)->value == 0
      ) {
        $this->isTranslating = TRUE;
      }
    }
    return $this->isTranslating;
  }

  /**
   * Whether or not to support asymmetric translations.
   *
   * @see https://www.drupal.org/project/paragraphs/issues/2461695
   * @see https://www.drupal.org/project/paragraphs/issues/2904705
   * @see https://www.drupal.org/project/paragraphs_asymmetric_translation_widgets
   *
   * @return bool
   *   True if asymmetric tranlation is supported.
   */
  protected function supportsAsymmetricTranslations() {
    return $this->layoutParagraphsLayout->getParagraphsReferenceField()->getFieldDefinition()->isTranslatable();
  }

  /**
   * Initialize translations for item list.
   *
   * Makes sure all components have a translation for the current
   * language and creates them if necessary.
   */
  protected function initTranslations() {
    $items = $this->layoutParagraphsLayout->getParagraphsReferenceField();
    /** @var \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem $item */
    foreach ($items as $delta => $item) {
      if (!empty($item->entity) && $item->entity instanceof ParagraphInterface) {
        if (!$this->isTranslating()) {
          // Set the langcode if we are not translating.
          $langcode_key = $item->entity->getEntityType()->getKey('langcode');
          if ($item->entity->get($langcode_key)->value != $this->langcode) {
            // If a translation in the given language already exists,
            // switch to that. If there is none yet, update the language.
            if ($item->entity->hasTranslation($this->langcode)) {
              $item->entity = $item->entity->getTranslation($this->langcode);
            }
            else {
              $item->entity->set($langcode_key, $this->langcode);
            }
          }
        }
        else {
          // Add translation if missing for the target language.
          if (!$item->entity->hasTranslation($this->langcode)) {
            // Get the selected translation of the paragraph entity.
            $entity_langcode = $item->entity->language()->getId();
            $source_langcode = $this->sourceLangcode ?? $entity_langcode;
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
              $item->entity->addTranslation($this->langcode, $entity->toArray());
              $translation = $item->entity->getTranslation($this->langcode);
              $manager = \Drupal::service('content_translation.manager');
              $manager->getTranslationMetadata($translation)
                ->setSource($item->entity->language()->getId());
            }
          }
          // If any paragraphs type is translatable do not switch.
          if ($item->entity->hasField('content_translation_source')) {
            // Switch the paragraph to the translation.
            $item->entity = $item->entity->getTranslation($this->langcode);
          }
        }
        $items[$delta]->entity = $item->entity;
      }
    }
    $this->tempstore->set($this->layoutParagraphsLayout);
  }

}
