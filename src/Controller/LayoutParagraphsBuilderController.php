<?php

namespace Drupal\layout_paragraphs\Controller;

use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Template\Attribute;

/**
 * LayoutParagraphsEditor controller class.
 */
class LayoutParagraphsBuilderController extends ControllerBase {

  use AjaxHelperTrait;

  /**
   * The tempstore service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $tempstore;

  /**
   * Settings to pass to jQuery modal dialog.
   *
   * @var array
   */
  protected $dialogOptions;

  /**
   * The layout paragraphs builder service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsBuilderService
   */
  protected $layoutParagraphsBuilderService;

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
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository $tempstore
   *   The tempstore service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(LayoutParagraphsLayoutTempstoreRepository $tempstore, EntityTypeBundleInfoInterface $entity_type_bundle_info, EventDispatcherInterface $event_dispatcher) {
    $this->tempstore = $tempstore;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->eventDispatcher = $event_dispatcher;
    $this->dialogOptions = [
      'width' => '70%',
      'minWidth' => 500,
      'maxWidth' => 1000,
      'draggable' => TRUE,
      'classes' => [
        'ui-dialog' => 'lpe-dialog',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Build the ajax response for the choose component menu.
   */
  public function chooseComponent(
    LayoutParagraphsLayout $layout_paragraphs_layout,
    $sibling_uuid = '',
    $placement = '',
    $region = '',
    $parent_uuid = ''
    ) {

    $route_params = [
      'layout_paragraphs_layout' => $layout_paragraphs_layout->id(),
    ];
    if ($region && $parent_uuid) {
      $route_name = 'layout_paragraphs.builder.insert_into_region';
      $route_params += [
        'parent_uuid' => $parent_uuid,
        'region' => $region,
      ];
    }
    elseif ($sibling_uuid && $placement) {
      $route_name = 'layout_paragraphs.builder.insert_sibling';
      $route_params += [
        'placement' => $placement,
        'sibling_uuid' => $sibling_uuid,
      ];
      $component = $layout_paragraphs_layout->getComponentByUuid($sibling_uuid);
      $parent_uuid = $component->getParentUuid();
      $region = $component->getRegion();
    }
    else {
      $route_name = 'layout_paragraphs.builder.insert';
    }

    // If there is only one type to render,
    // return the component form instead of a list of links.
    $types = $this->getAllowedComponentTypes($layout_paragraphs_layout, $parent_uuid, $region);
    if (count($types) === 1) {
      $type_name = key($types);
      $type = $this->entityTypeManager()->getStorage('paragraphs_type')->load($type_name);
      switch ($route_name) {
        case 'layout_paragraphs.builder.insert_into_region':
          $response = $this->formBuilder()->getForm('\Drupal\layout_paragraphs\Form\InsertComponentForm', $layout_paragraphs_layout, $type, $parent_uuid, $region);
          break;

        case 'layout_paragraphs.builder.insert':
          $response = $this->formBuilder()->getForm('\Drupal\layout_paragraphs\Form\InsertComponentForm', $layout_paragraphs_layout, $type);
          break;

        case 'layout_paragraphs.builder.insert_sibling':
          $response = $this->formBuilder()->getForm('\Drupal\layout_paragraphs\Form\InsertComponentSiblingForm', $layout_paragraphs_layout, $type, $sibling_uuid, $placement);
          break;
      }
      return $response;
    }

    foreach ($types as &$type) {
      $type['url'] = Url::fromRoute($route_name, $route_params + ['paragraph_type' => $type['id']])->toString();
      $type['link_attributes'] = new Attribute([
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode($this->dialogOptions),
      ]);
    }

    $section_components = array_filter($types, function ($type) {
      return $type['is_section'] === TRUE;
    });
    $content_components = array_filter($types, function ($type) {
      return $type['is_section'] === FALSE;
    });
    $component_menu = [
      '#theme' => 'layout_paragraphs_builder_component_menu',
      '#types' => [
        'layout' => $section_components,
        'content' => $content_components,
      ],
    ];
    return $component_menu;

  }

  /**
   * Reorders a Layout Paragraphs Layout's components.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing a "layoutParagraphsState" POST parameter.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The Layout Paragraphs Layout object.
   */
  public function reorderComponents(Request $request, LayoutParagraphsLayout $layout_paragraphs_layout) {
    if ($ordered_components = Json::decode($request->request->get("layoutParagraphsState"))) {
      $layout_paragraphs_layout->reorderComponents($ordered_components);
      $this->tempstore->set($layout_paragraphs_layout);
    }
    return ['#markup' => 'success'];
  }

  /**
   * Deletes a Layout Paragraph Layout's component.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The Layout Paragraphs Layout object.
   * @param string $paragraph_uuid
   *   The uuid of the paragraph to delete.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax reponse with command to remove deleted item.
   */
  public function deleteComponent(LayoutParagraphsLayout $layout_paragraphs_layout, string $paragraph_uuid) {
    $layout_paragraphs_layout->deleteComponent($paragraph_uuid, TRUE);
    $this->tempstore->set($layout_paragraphs_layout);
    $response = new AjaxResponse();
    $response->addCommand(new RemoveCommand('[data-uuid="' . $paragraph_uuid . '"]'));
    return $response;
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
