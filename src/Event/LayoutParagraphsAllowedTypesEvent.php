<?php

namespace Drupal\layout_paragraphs\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;

/**
 * Class definition for Layout Paragraphs Allowed Types event.
 */
class LayoutParagraphsAllowedTypesEvent extends Event {

  /**
   * An array of component (paragraph) types.
   *
   * @var array
   */
  protected $types = [];

  /**
   * The layout object.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layout;

  /**
   * The parent uuid.
   *
   * @var string
   */
  protected $parentUuid;

  /**
   * The region name.
   *
   * @var string
   */
  protected $region;

  /**
   * Class cosntructor.
   *
   * @param array $types
   *   An array of paragraph types.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout object.
   * @param string $parent_uuid
   *   The parent uuid.
   * @param string $region
   *   The region.
   */
  public function __construct(array $types, LayoutParagraphsLayout $layout, string $parent_uuid, string $region) {
    $this->types = $types;
    $this->layout = $layout;
    $this->parentUuid = $parent_uuid;
    $this->region = $region;
  }

}
