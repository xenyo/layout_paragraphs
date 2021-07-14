<?php

namespace Drupal\layout_paragraphs\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;

/**
 * Class definition for Layout Paragraphs Allowed Types event.
 */
class LayoutParagraphsUpdateLayoutEvent extends Event {

  const EVENT_NAME = 'layout_paragraphs_update_layout';

  /**
   * The origin layout object.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $originalLayout;

  /**
   * The updated layout object.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layout;

  /**
   * TRUE if the entire layout needs to be refreshed.
   *
   * @var bool
   */
  public $needsRefresh = FALSE;

  /**
   * Class cosntructor.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $original_layout
   *   The layout object.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout object.
   */
  public function __construct(LayoutParagraphsLayout $original_layout, LayoutParagraphsLayout $layout) {
    $this->originalLayout = $original_layout;
    $this->layout = $layout;
  }

  /**
   * Get the original layout.
   *
   * @return \Drupal\layout_paragraphs\LayoutParagraphsLayout
   *   The original layout.
   */
  public function getOriginalLayout() {
    return $this->originalLayout;
  }

  /**
   * Get the updated layout.
   *
   * @return \Drupal\layout_paragraphs\LayoutParagraphsLayout
   *   The updated layout.
   */
  public function getUpdatedLayout() {
    return $this->layout;
  }

}
