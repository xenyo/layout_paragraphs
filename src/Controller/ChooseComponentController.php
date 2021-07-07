<?php

namespace Drupal\layout_paragraphs\Controller;

use Drupal\Core\Url;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutRefreshTrait;
use Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent;
use Drupal\layout_paragraphs\DialogHelperTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * ChooseComponentController controller class.
 *
 * Returns a list of links for available component types that can
 * be added to a layout region.
 */
class ChooseComponentController extends ControllerBase {

  use AjaxHelperTrait;
  use LayoutParagraphsLayoutRefreshTrait;
  use DialogHelperTrait;

  /**
   * The entity type bundle info service.
   *
   * @var Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Construct a Layout Paragraphs Editor controller.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_type_bundle_info, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Builds the component menu.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs layout object.
   *
   * @return array
   *   The build array.
   */
  public function list(Request $request, LayoutParagraphsLayout $layout_paragraphs_layout) {

    $route_name = 'layout_paragraphs.builder.insert';
    $route_params = [
      'layout_paragraphs_layout' => $layout_paragraphs_layout->id(),
    ];
    $query_params = [
      'parent_uuid' => $request->query->get('parent_uuid', NULL),
      'region' => $request->query->get('region', NULL),
      'sibling_uuid' => $request->query->get('sibling_uuid', NULL),
      'placement' => $request->query->get('placement', NULL),
    ];
    $types = $this->getAllowedComponentTypes($layout_paragraphs_layout, $query_params['parent_uuid'], $query_params['region']);
    // If there is only one type to render,
    // return the component form instead of a list of links.
    if (count($types) === 1) {
      $type_name = key($types);
      $type = $this->entityTypeManager()->getStorage('paragraphs_type')->load($type_name);
      $response = $this->formBuilder()->getForm(
        '\Drupal\layout_paragraphs\Form\InsertComponentForm',
        $layout_paragraphs_layout,
        $type,
        $query_params['parent_uuid'],
        $query_params['region'],
        $query_params['sibling_uuid'],
        $query_params['placement']
      );
      return $response;
    }

    foreach ($types as &$type) {
      $url_route_params = $route_params + ['paragraph_type' => $type['id']];
      $url_options = ['query' => $query_params];
      $type['url'] = Url::fromRoute($route_name, $url_route_params, $url_options)->toString();
      $type['link_attributes'] = new Attribute([
        'class' => ['use-ajax'],
      ]);
    }

    $section_components = array_filter($types, function ($type) {
      return $type['is_section'] === TRUE;
    });
    $content_components = array_filter($types, function ($type) {
      return $type['is_section'] === FALSE;
    });
    $component_menu = [
      '#title' => $this->t('Choose a component'),
      '#theme' => 'layout_paragraphs_builder_component_menu',
      '#attributes' => [
        'class' => ['lpb-component-list'],
      ],
      '#types' => [
        'layout' => $section_components,
        'content' => $content_components,
      ],
      '#attached' => [
        'library' => [
          'layout_paragraphs/component_list',
        ],
      ],
    ];
    return $component_menu;

  }

  /**
   * Returns an array of allowed component types.
   *
   * Dispatches a LayoutParagraphsComponentMenuEvent object so the component
   * list can be manipulated based on the layout, the layout settings, the
   * parent uuid, and region.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout object.
   * @param string $parent_uuid
   *   The parent uuid of paragraph we are inserting a component into.
   * @param string $region
   *   The region we are inserting a component into.
   *
   * @return array[]
   *   Returns an array of allowed component types.
   */
  public function getAllowedComponentTypes(LayoutParagraphsLayout $layout, $parent_uuid = NULL, $region = NULL) {
    $component_types = $this->getComponentTypes($layout);
    $event = new LayoutParagraphsAllowedTypesEvent($component_types, $layout, $parent_uuid, $region);
    $this->eventDispatcher->dispatch(LayoutParagraphsAllowedTypesEvent::EVENT_NAME, $event);
    return $event->getTypes();
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
  public function getComponentTypes(LayoutParagraphsLayout $layout) {

    $items = $layout->getParagraphsReferenceField();
    $settings = $items->getSettings()['handler_settings'];
    $sorted_bundles = $this->getSortedAllowedTypes($settings);
    $storage = $this->entityTypeManager()->getStorage('paragraphs_type');
    foreach (array_keys($sorted_bundles) as $bundle) {
      /** @var \Drupal\paragraphs\Entity\ParagraphsType $paragraphs_type */
      $paragraphs_type = $storage->load($bundle);
      $plugins = $paragraphs_type->getEnabledBehaviorPlugins();
      $section_component = isset($plugins['layout_paragraphs']);
      $path = '';
      // Get the icon and pass to Javascript.
      if (method_exists($paragraphs_type, 'getIconUrl')) {
        $path = $paragraphs_type->getIconUrl();
      }
      $types[$bundle] = [
        'id' => $paragraphs_type->id(),
        'label' => $paragraphs_type->label(),
        'image' => $path,
        'description' => $paragraphs_type->getDescription(),
        'is_section' => $section_component,
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

}
