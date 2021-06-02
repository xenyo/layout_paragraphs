<?php

namespace Drupal\layout_paragraphs\Controller;

use Drupal\Core\Url;
use Drupal\Core\Routing\RouteProvider;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Symfony\Component\HttpFoundation\Request;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsBuilderService;
use Drupal\layout_paragraphs\Element\LayoutParagraphsBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Layout paragraphs builder choose component controller.
 */
class ChooseComponentController extends ControllerBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  protected $routeProvider;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    EntityTypeBundleInfo $entity_type_bundle_info,
    EventDispatcherInterface $event_dispatcher,
    LayoutParagraphsBuilderService $layout_paragraphs_builder,
    RouteProvider $route_provider) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->eventDispatcher = $event_dispatcher;
    $this->layoutParagraphsBuilder = $layout_paragraphs_builder;
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('event_dispatcher'),
      $container->get('layout_paragraphs.builder'),
      $container->get('router.route_provider')
    );
  }

  /**
   * Build the ajax response for the choose component menu.
   */
  public function build(
    Request $request,
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
      $parent_uuid = $layout_paragraphs_layout->getComponentByUuid($sibling_uuid)->getParentUuid();
      $region = $layout_paragraphs_layout->getComponentByUuid($sibling_uuid)->getRegion();
    }
    else {
      $route_name = 'layout_paragraphs.builder.insert';
    }

    $types = $this->layoutParagraphsBuilder->getAllowedComponentTypes($layout_paragraphs_layout, $parent_uuid, $region);
    foreach ($types as &$type) {
      $type['url'] = Url::fromRoute($route_name, $route_params + ['paragraph_type' => $type['id']]);
    }

    if (count($types) === 1) {
      $route = $this->routeProvider->getRouteByName($route_name);
      $post_data = $request->request->all();
      $options = [
        'query' => [
          '_wrapper_format' => 'drupal_dialog',
          '_drupal_ajax' => TRUE,
        ] + $post_data,
      ];
      return $this->redirect($route_name, $route_params + ['paragraph_type' => key($types)], $options);
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

}
