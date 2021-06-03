<?php

namespace Drupal\layout_paragraphs\Element;

use Drupal\core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Renderer;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Entity\EntityTypeBundleInfo;
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
      '#preview_view_mode' => 'default',
      '#options' => [],
      '#uuid' => NULL,
      '#pre_render' => [
        [$this, 'preRender'],
      ],
    ];
  }

  /**
   * Pre-render callback: Renders the UI.
   */
  public function preRender($element) {
    if ($element['#layout_paragraphs_layout']) {
      $element['layout_paragraphs_layout'] = $this->renderLayout(
        $element['#layout_paragraphs_layout'],
        $element['#options'],
        $element['#preview_view_mode'],
        $element['#uuid']
      );
    }
    return $element;
  }

  /**
   * Renders the Layout Paragraphs Builder UI.
   *
   * Note that the render element attempts to load the layout
   * from the tempstore. It is up to the environment using the
   * render element to create, delete, reset, or otherwise manipulate
   * the layout stored in the tempstore.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraph_layout
   *   The layout paragraphs layout domain object.
   * @param array $options
   *   Additional options to pass to the Layout Paragraphs Builder JS.
   * @param string $preview_view_mode
   *   The view mode to use for rendering paragraphs.
   * @param string $element_uuid
   *   If provided, returns the render array for a single component.
   *
   * @return array
   *   The rendered layout paragraphs builder UI.
   */
  protected function renderLayout(
    LayoutParagraphsLayout $layout_paragraph_layout,
    array $options,
    string $preview_view_mode = 'default',
    string $element_uuid = NULL) {

    $output = [
      '#components' => [],
      'build' => [],
    ];
    /** @var \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout */
    $layout = $this->tempstore->get($layout_paragraph_layout);

    // Build a flat list of component build arrays.
    foreach ($layout->getComponents() as $component) {
      /** @var \Drupal\layout_paragraphs\LayoutParagraphsComponent $component */
      $output['#components'][$component->getEntity()->uuid()] = $this->buildComponent($layout, $component, $preview_view_mode);
    }

    // Nest child components inside their respective sections and regions.
    foreach ($layout->getComponents() as $component) {
      /** @var \Drupal\layout_paragraphs\LayoutParagraphsComponent $component */
      $uuid = $component->getEntity()->uuid();
      if ($component->isLayout()) {
        $section = $layout->getLayoutSection($component->getEntity());
        $layout_plugin_instance = $this->layoutPluginInstance($section);
        foreach (array_keys($output['#components'][$uuid]['regions']) as $region_name) {
          foreach ($section->getComponentsForRegion($region_name) as $child_component) {
            $child_uuid = $child_component->getEntity()->uuid();
            $output['#components'][$uuid]['regions'][$region_name][$child_uuid] =& $output['#components'][$child_uuid];
          }
        }
        $output['#components'][$uuid]['regions'] = $layout_plugin_instance->build($output['#components'][$uuid]['regions']);
        $output['#components'][$uuid]['regions']['#weight'] = 1000;
      }
    }

    // If a element #uuid is provided, render the matching element.
    // This is used in cases where a single component needs
    // to be rendered - for example, as part of an AJAX response.
    if ($element_uuid) {
      if (isset($output['#components'][$element_uuid])) {
        return $output['#components'][$element_uuid];
      }
    }

    // Build the complete Layout Paragraphs Builder UI with attached libraries.
    $entity = $layout->getEntity();
    $base_url = sprintf(
      '/layout-paragraphs-builder/%s/%s/%d/%s/%s/%s',
      $entity->getEntityTypeId(), $entity->bundle(),
      $entity->id(),
      $layout->getFieldName(),
      $layout->id(),
      $preview_view_mode
    );
    $allowed_types = $this->getComponentTypes($layout);
    $field_definition = $layout_paragraph_layout
      ->getParagraphsReferenceField()
      ->getFieldDefinition();
    $description = $field_definition->getDescription();

    $component_menu = [
      '#theme' => 'layout_paragraphs_builder_component_menu',
      '#types' => $allowed_types,
    ];
    $section_menu = [
      '#theme' => 'layout_paragraphs_builder_section_menu',
      '#types' => $allowed_types['layout'] ?? [],
    ];
    $controls = [
      '#theme' => 'layout_paragraphs_builder_controls',
    ];
    $empty_container = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'lpb-empty-container',
        ],
      ],
    ];
    if ($description) {
      $empty_container['message'] = ['#markup' => $description];
    }
    $layouts = [];
    foreach ($allowed_types['layout'] as $name => $type) {
      $layouts = array_merge($layouts, $this->getAvailableLayouts($name));
    }

    $js_settings = [
      'id' => $layout->id(),
      'selector' => '.js-' . $layout->id(),
      'componentMenu' => $this->renderer->render($component_menu),
      'sectionMenu' => $this->renderer->render($section_menu),
      'controls' => $this->renderer->render($controls),
      'toggleButton' => '<button class="lpb-toggle"><span class="visually-hidden">' . $this->t('Create Content') . '</span></button>',
      'baseUrl' => $base_url,
      'emptyContainer' => $this->renderer->render($empty_container),
      'types' => array_merge($allowed_types['layout'], $allowed_types['content']),
      'layouts' => $layouts,
      'options' => $options,
    ];

    // Render root level items.
    $output['build'] += [
      '#type' => 'container',
      '#attached' => [
        'library' => ['layout_paragraphs/layout_paragraphs_builder'],
        'drupalSettings' => [
          'layoutParagraphsBuilder' => [
            $layout->id() => $js_settings,
          ],
        ],
      ],
      '#attributes' => [
        'class' => [
          'lp-builder',
          'js-' . $layout->id(),
        ],
        'data-lp-builder-id' => $layout->id(),
      ],
      'build' => [],
    ];
    foreach ($layout->getComponents() as $component) {
      /** @var \Drupal\layout_paragraphs\LayoutParagraphsComponent $component */
      if ($component->isRoot()) {
        $uuid = $component->getEntity()->uuid();
        $output['build'][$uuid] =& $output['#components'][$uuid];
      }
    }
    return $output;
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
    $build['#attributes']['class'][] = 'lpb-component';
    $build['#attributes']['tabindex'] = 0;

    $url_params = [
      'layout_paragraphs_layout' => $layout->id(),
      'sibling_uuid' => $entity->uuid(),
    ];

    $build['actions'] = [
      '#theme' => 'layout_paragraphs_builder_controls',
      '#label' => $entity->getParagraphType()->label,
      '#edit_url' => Url::fromRoute('layout_paragraphs.builder.edit_item', [
        'layout_paragraphs_layout' => $layout->id(),
        'paragraph_uuid' => $entity->uuid(),
      ]),
      '#delete_url' => Url::fromRoute('layout_paragraphs.builder.delete_item', [
        'layout_paragraphs_layout' => $layout->id(),
        'paragraph_uuid' => $entity->uuid(),
      ]),
      '#weight' => -10001,
    ];

    if (!$component->getParentUuid() && $layout->getSetting('require_layouts')) {
      $build['insert_before'] = $this->insertSectionButton($url_params + ['placement' => 'before'], -10000, ['before']);
      $build['insert_after'] = $this->insertSectionButton($url_params + ['placement' => 'after'], 10000, ['after']);
    }
    else {
      $build['insert_before'] = $this->insertComponentButton($url_params + ['placement' => 'before'], -10000, ['before']);
      $build['insert_after'] = $this->insertComponentButton($url_params + ['placement' => 'after'], 10000, ['after']);
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
            'class' => ['lpb-region'],
            'data-region' => $region_name,
            'data-region-uuid' => $entity->uuid() . '-' . $region_name,
            'tabindex' => 0,
          ],
          'insert_button' => $this->insertComponentButton($url_params, 10000, ['center']),
        ];
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
        'data-dialog-type' => 'dialog',
        'data-dialog-options' => Json::encode([
          'width' => 1200,
          'height' => 500,
          'modal' => TRUE,
          'target' => 'lpb-component-menu',
        ]),
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
        'data-dialog-type' => 'dialog',
        'data-dialog-options' => Json::encode([
          'width' => 1200,
          'height' => 500,
          'modal' => TRUE,
          'target' => 'lpb-component-menu',
        ]),
      ],
      '#url' => Url::fromRoute('layout_paragraphs.builder.choose_component', $route_params),
    ];
  }

  /**
   * Returns an array of available component types.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout paragraphs layout.
   *
   * @return array
   *   An array of available component types.
   */
  protected function getComponentTypes(LayoutParagraphsLayout $layout) {

    $all_bundle_info = $this->entityTypeBundleInfo->getBundleInfo('paragraph');
    $items = $layout->getParagraphsReferenceField();
    $settings = $items->getSettings()['handler_settings'];
    $sorted_bundles = $this->getSortedAllowedTypes($settings, $all_bundle_info);
    $storage = $this->entityTypeManager->getStorage('paragraphs_type');
    $types = [
      // Layout component types.
      'layout' => [],
      // Content component types.
      'content' => [],
    ];
    foreach (array_keys($sorted_bundles) as $bundle) {
      /** @var \Drupal\paragraphs\Entity\ParagraphsType $paragraphs_type */
      $paragraphs_type = $storage->load($bundle);
      $plugins = $paragraphs_type->getEnabledBehaviorPlugins();
      $has_layout = isset($plugins['layout_paragraphs']);
      $path = '';
      // Get the icon and pass to Javascript.
      if (method_exists($paragraphs_type, 'getIconUrl')) {
        $path = $paragraphs_type->getIconUrl();
      }
      $bundle_info = $all_bundle_info[$bundle];
      $types[($has_layout ? 'layout' : 'content')][$bundle] = [
        'id' => $bundle,
        'name' => $bundle_info['label'],
        'image' => $path,
        'title' => $this->t('New @name', ['@name' => $bundle_info['label']]),
      ];
    }
    return $types;
  }

  /**
   * Returns an array of sorted allowed component / paragraph types.
   *
   * @param array $settings
   *   The handler settings.
   *
   * @return array
   *   An array of sorted, allowed paragraph bundles.
   */
  protected function getSortedAllowedTypes(array $settings) {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo('paragraph');
    if (!empty($settings['target_bundles'])) {
      if (isset($settings['negate']) && $settings['negate'] == '1') {
        $bundles = array_diff_key($bundles, $settings['target_bundles']);
      }
      else {
        $bundles = array_intersect_key($bundles, $settings['target_bundles']);
      }
    }

    // Support for the paragraphs reference type.
    if (!empty($settings['target_bundles_drag_drop'])) {
      $drag_drop_settings = $settings['target_bundles_drag_drop'];
      $max_weight = count($bundles);

      foreach ($drag_drop_settings as $bundle_info) {
        if (isset($bundle_info['weight']) && $bundle_info['weight'] && $bundle_info['weight'] > $max_weight) {
          $max_weight = $bundle_info['weight'];
        }
      }

      // Default weight for new items.
      $weight = $max_weight + 1;
      foreach ($bundles as $machine_name => $bundle) {
        $return_bundles[$machine_name] = [
          'label' => $bundle['label'],
          'weight' => isset($drag_drop_settings[$machine_name]['weight']) ? $drag_drop_settings[$machine_name]['weight'] : $weight,
        ];
        $weight++;
      }
    }
    else {
      $weight = 0;

      foreach ($bundles as $machine_name => $bundle) {
        $return_bundles[$machine_name] = [
          'label' => $bundle['label'],
          'weight' => $weight,
        ];

        $weight++;
      }
    }
    uasort($return_bundles, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    return $return_bundles;
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
   * Returns a list of available layouts.
   *
   * @param string $bundle_name
   *   The paragraph bundle name.
   *
   * @return array
   *   A list of layouts.
   */
  protected function getAvailableLayouts(string $bundle_name) {
    /** @var \Drupal\paragraphs\ParagraphsTypeInterface $paragraphs_type */
    $paragraphs_type = $this->entityTypeManager->getStorage('paragraphs_type')->load($bundle_name);
    if ($paragraphs_type->hasEnabledBehaviorPlugin('layout_paragraphs')) {
      $plugin = $paragraphs_type->getBehaviorPlugin('layout_paragraphs');
      return $plugin->getConfiguration()['available_layouts'];
    }
    return [];
  }

}
