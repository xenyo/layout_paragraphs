<?php

namespace Drupal\layout_paragraphs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\layout_paragraphs\Element\LayoutParagraphsBuilder;
use Drupal\layout_paragraphs\LayoutParagraphsBuilderService;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
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

  /**
   * {@inheritDoc}
   */
  public function __construct(
    EntityTypeBundleInfo $entity_type_bundle_info,
    EventDispatcherInterface $event_dispatcher,
    LayoutParagraphsBuilderService $layout_paragraphs_builder) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->eventDispatcher = $event_dispatcher;
    $this->layoutParagraphsBuilder = $layout_paragraphs_builder;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('event_dispatcher'),
      $container->get('layout_paragraphs.builder')
    );
  }

  /**
   * Build the ajax response for the choose component menu.
   */
  public function build(
    LayoutParagraphsLayout $layout_paragraphs_layout,
    $sibling_uuid = '',
    $proximity = '',
    $region = '',
    $parent_uuid = ''
  ) {
    $types = $this->layoutParagraphsBuilder->getAllowedComponentTypes($layout_paragraphs_layout, $parent_uuid, $region);

    $base_url = '/layout-paragraphs/' . $layout_paragraphs_layout->id() . '/';
    if ($region && $parent_uuid) {
      $base_url .= 'insert-into-region/' . $parent_uuid . '/' . $region . '/';
    }
    elseif ($sibling_uuid && $proximity) {
      $base_url .= 'insert-sibling/' . $sibling_uuid . '/' . $proximity . '/';
    }
    else {
      $base_url .= 'insert-component/';
    }

    if (count($types) === 1) {
      // Only one type, so redirect to the specific type creation route.
    }
    $section_components = array_filter($types, function ($type) {
      return $type['section_component'] === TRUE;
    });
    $content_components = array_filter($types, function ($type) {
      return $type['section_component'] === FALSE;
    });
    $component_menu = [
      '#theme' => 'layout_paragraphs_builder_component_menu',
      '#base_url' => $base_url,
      '#types' => [
        'layout' => $section_components,
        'content' => $content_components,
      ],
    ];
    return $component_menu;
  }

}
