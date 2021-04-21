<?php

namespace Drupal\layout_paragraphs\Routing;

use Symfony\Component\Routing\Route;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;

/**
 * Layout paragraphs tempstore param converter service.
 *
 * @internal
 *   Tagged services are internal.
 */
class LayoutParagraphsTempstoreParamConverter implements ParamConverterInterface {

  /**
   * The layout paragraphs layout tempstore.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $layoutParagraphsLayoutTempstore;

  /**
   * The Entity Type Manager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface;
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LayoutParagraphsEditorTempstoreParamConverter.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository $layout_paragraphs_layout_tempstore
   *   The layout tempstore repository.
   */
  public function __construct(LayoutParagraphsLayoutTempstoreRepository $layout_paragraphs_layout_tempstore, EntityTypeManagerInterface $entity_type_manager) {
    $this->layoutParagraphsLayoutTempstore = $layout_paragraphs_layout_tempstore;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (empty($defaults['entity_type']) || empty($defaults['field_name']) || !isset($defaults['entity_id']) || empty($defaults['bundle'])) {
      return NULL;
    }

    $entity_id = $defaults['entity_id'];
    $field_name = $defaults['field_name'];
    $entity_type = $defaults['entity_type'];
    $bundle = $defaults['bundle'];
    $definition = $this->entityTypeManager->getDefinition($entity_type);

    if ($entity_id) {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    }
    else {
      $entity = $this->entityTypeManager->getStorage($entity_type)->create([$definition->getKey('bundle') => $bundle]);
    }
    $layout = new LayoutParagraphsLayout($entity->$field_name);
    return $this->layoutParagraphsLayoutTempstore->get($layout);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['layout_paragraphs_layout_tempstore']);
  }

}
