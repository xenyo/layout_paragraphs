<?php

namespace Drupal\layout_paragraphs\Element;

use Drupal\core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Renderer;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsSection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\layout_paragraphs\LayoutParagraphsComponent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;

/**
 * Defines a render element for building the Layout Builder UI.
 *
 * @RenderElement("layout_paragraphs_builder")
 *
 * @internal
 *   Plugin classes are internal.
 */
class LayoutParagraphsBuilder extends RenderElement implements ContainerFactoryPluginInterface {

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
   * The dialog options.
   *
   * @var array
   */
  protected $dialogOptions = [
    'width' => 1200,
    'height' => 500,
    'modal' => TRUE,
    'target' => 'lpb-component-menu',
  ];

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
    EntityTypeBundleInfo $entity_type_bundle_info) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tempstore = $tempstore_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutPluginManager = $layout_plugin_manager;
    $this->renderer = $renderer;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
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
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#layout_paragraphs_layout' => NULL,
      '#uuid' => NULL,
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
    /** @var \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout */
    $layout = $this->tempstore->get($element['#layout_paragraphs_layout']);
    $element_uuid = $element['#uuid'];
    $preview_view_mode = $layout->getSetting('preview_view_mode', 'default');

    $element['#components'] = [];
    if ($layout->getSetting('is_translating', FALSE)) {
      $element['translation_warning'] = $this->translationWarning();
    }
    // Build a flat list of component build arrays.
    foreach ($layout->getComponents() as $component) {
      /** @var \Drupal\layout_paragraphs\LayoutParagraphsComponent $component */
      $element['#components'][$component->getEntity()->uuid()] = $this->buildComponent($layout, $component, $preview_view_mode);
    }

    // Nest child components inside their respective sections and regions.
    foreach ($layout->getComponents() as $component) {
      /** @var \Drupal\layout_paragraphs\LayoutParagraphsComponent $component */
      $uuid = $component->getEntity()->uuid();
      if ($component->isLayout()) {
        $section = $layout->getLayoutSection($component->getEntity());
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
        'lp-builder-' . $layout->id(),
      ],
      'data-lpb-id' => $layout->id(),
    ];
    $element['#attached']['library'] = ['layout_paragraphs/builder'];
    $element['#attached']['drupalSettings']['lpBuilder'][$layout->id()] = $layout->getSettings();
    $element['#is_empty'] = $layout->isEmpty();
    $element['#empty_message'] = $layout->getSetting('empty_message', $this->t('Start adding content.'));
    if ($layout->getSetting('require_layouts', FALSE)) {
      $element['#insert_button'] = $this->insertSectionButton(['layout_paragraphs_layout' => $layout->id()]);
    }
    else {
      $element['#insert_button'] = $this->insertComponentButton(['layout_paragraphs_layout' => $layout->id()]);
    }
    $element['#root_components'] = [];
    foreach ($layout->getRootComponents() as $component) {
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
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   THe Layout Paragraphs Layout object.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsComponent $component
   *   The component to render.
   * @param string $preview_view_mode
   *   The view mode to use for rendering paragraphs.
   *
   * @return array
   *   The build array.
   */
  protected function buildComponent(LayoutParagraphsLayout $layout, LayoutParagraphsComponent $component, $preview_view_mode = 'default') {
    $entity = $component->getEntity();
    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    $build = $view_builder->view($entity, $preview_view_mode, $entity->language()->getId());

    $build['#attributes']['data-uuid'] = $entity->uuid();
    $build['#attributes']['data-type'] = $entity->bundle();
    $build['#attributes']['data-id'] = $entity->id();
    $build['#attributes']['class'][] = 'js-lpb-component';
    if ($entity->isNew()) {
      $build['#attributes']['class'][] = 'is_new';
    }
    $build['#attributes']['tabindex'] = '0';

    $url_params = [
      'layout_paragraphs_layout' => $layout->id(),
      'sibling_uuid' => $entity->uuid(),
    ];

    if ($this->editAccess($layout, $entity)) {
      $edit_url = Url::fromRoute('layout_paragraphs.builder.edit_item', [
        'layout_paragraphs_layout' => $layout->id(),
        'component_uuid' => $entity->uuid(),
      ]);
    }
    else {
      $edit_url = '';
    }

    if ($this->deleteAccess($layout, $entity)) {
      $delete_url = Url::fromRoute('layout_paragraphs.builder.delete_item', [
        'layout_paragraphs_layout' => $layout->id(),
        'component_uuid' => $entity->uuid(),
      ]);
    }
    else {
      $delete_url = '';
    }

    $build['actions'] = [
      '#theme' => 'layout_paragraphs_builder_controls',
      '#label' => $entity->getParagraphType()->label,
      '#edit_url' => $edit_url,
      '#delete_url' => $delete_url,
      '#dialog_options' => Json::encode($this->dialogOptions),
      '#weight' => -10001,
    ];

    if ($this->createAccess($layout)) {
      if (!$component->getParentUuid() && $layout->getSetting('require_layouts')) {
        $build['insert_before'] = $this->insertSectionButton($url_params + ['placement' => 'before'], -10000, ['before']);
        $build['insert_after'] = $this->insertSectionButton($url_params + ['placement' => 'after'], 10000, ['after']);
      }
      else {
        $build['insert_before'] = $this->insertComponentButton($url_params + ['placement' => 'before'], -10000, ['before']);
        $build['insert_after'] = $this->insertComponentButton($url_params + ['placement' => 'after'], 10000, ['after']);
      }
    }

    if ($component->isLayout()) {
      $section = $layout->getLayoutSection($entity);
      $layout_instance = $this->layoutPluginInstance($section);
      $region_names = $layout_instance->getPluginDefinition()->getRegionNames();

      $build['#attributes']['class'][] = 'lpb-layout';
      $build['#attributes']['data-layout'] = $section->getLayoutId();
      $build['#layout_plugin_instance'] = $layout_instance;
      $build['regions'] = [];
      foreach ($region_names as $region_name) {
        $url_params = [
          'layout_paragraphs_layout' => $layout->id(),
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
        if ($this->createAccess($layout)) {
          $build['regions'][$region_name]['insert_button'] = $this->insertComponentButton($url_params, 10000, ['center']);
        }
      }
    }
    return $build;
  }

  /**
   * Returns the render array for a insert component button.
   *
   * @param array[] $route_params
   *   The route parameters for the link.
   * @param int $weight
   *   The weight of the button element.
   * @param array[] $classes
   *   A list of classes to append to the container.
   *
   * @return array
   *   The render array.
   */
  protected function insertComponentButton(array $route_params = [], int $weight = 0, array $classes = []) {
    return [
      '#type' => 'link',
      '#title' => Markup::create('<span class="visually-hidden">' . $this->t('Choose component') . '</span>'),
      '#weight' => $weight,
      '#attributes' => [
        'class' => array_merge(['lpb-btn--add', 'use-ajax'], $classes),
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode($this->dialogOptions),
      ],
      '#url' => Url::fromRoute('layout_paragraphs.builder.choose_component', $route_params),
    ];
  }

  /**
   * Returns the render array for a create section button.
   *
   * @param array[] $route_params
   *   The route parameters for the link.
   * @param int $weight
   *   The weight of the button element.
   * @param array[] $classes
   *   A list of classes to append to the container.
   *
   * @return array
   *   The render array.
   */
  protected function insertSectionButton(array $route_params = [], int $weight = 0, array $classes = []) {
    return [
      '#type' => 'link',
      '#title' => Markup::create($this->t('Add section')),
      '#attributes' => [
        'class' => array_merge(['lpb-btn', 'use-ajax'], $classes),
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode($this->dialogOptions),
      ],
      '#url' => Url::fromRoute('layout_paragraphs.builder.choose_component', $route_params),
    ];
  }

  /**
   * Builds a translation warning message.
   *
   * @return array
   *   The build array.
   */
  protected function translationWarning(){
    if ($this->supportsAsymmetricTranslations()) {
      return [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['alert'],
          'class' => ['messages', 'messages--warning'],
        ],
        '#weight' => -10,
        'message' => [
          '#markup' => $this->t('You are in translation mode. Changes will only affect the current language.'),
        ],
      ];
    }
    else {
      return [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['messages', 'messages--warning'],
        ],
        '#weight' => -10,
        'message' => [
          '#markup' => $this->t('You are in translation mode. You cannot add or remove items while translating. Reordering items will affect all languages.'),
        ],
      ];
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
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout object.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return bool
   *   True if user can edit.
   */
  protected function editAccess(LayoutParagraphsLayout $layout, ParagraphInterface $paragraph) {
    return $paragraph->access('edit');
  }

  /**
   * Returns an AccessResult object.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout object.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return bool
   *   True if user can edit.
   */
  protected function deleteAccess(LayoutParagraphsLayout $layout, ParagraphInterface $paragraph) {
    if ($layout->getSetting('is_translating', FALSE) && !($this->supportsAsymmetricTranslations())) {
      $access = new AccessResultForbidden('Cannot delete paragraphs while in translation mode.');
      return $access->isAllowed();
    }
    return $paragraph->access('delete');
  }

  /**
   * Returns an AccessResult object.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   True if user can edit.
   */
  protected function createAccess(LayoutParagraphsLayout $layout) {
    $access = new AccessResultForbidden('Cannot add paragraphs while in translation mode.');
    if ($layout->getSetting('is_translating', FALSE) && !($this->supportsAsymmetricTranslations())) {
      $access = new AccessResultForbidden('Cannot add paragraphs while in translation mode.');
    }
    $access = new AccessResultAllowed();
    return $access->isAllowed();
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

}
