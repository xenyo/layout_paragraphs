<?php

namespace Drupal\layout_paragraphs\Routing;

use Symfony\Component\Routing\Route;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;

/**
 * Layout paragraphs param converter service.
 *
 * @internal
 *   Tagged services are internal.
 */
class LayoutParagraphsParamConverter implements ParamConverterInterface {

  /**
   * The layout paragraphs layout tempstore.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $layoutParagraphsLayoutTempstore;

  /**
   * Constructs a new LayoutParagraphsEditorTempstoreParamConverter.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository $layout_paragraphs_layout_tempstore
   *   The layout tempstore repository.
   */
  public function __construct(LayoutParagraphsLayoutTempstoreRepository $layout_paragraphs_layout_tempstore) {
    $this->layoutParagraphsLayoutTempstore = $layout_paragraphs_layout_tempstore;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (empty($defaults['entity']) | empty($defaults['field_name'])) {
      return NULL;
    }
    $entity = $defaults['entity'];
    $field_name = $defaults['field_name'];
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
