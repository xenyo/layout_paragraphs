<?php

namespace Drupal\layout_paragraphs;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\CloseDialogCommand;

/**
 * Defines a dialog id helper trait.
 */
trait DialogHelperTrait {

  /**
   * Generates a dialog id for a given layout.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout paragraphs object.
   *
   * @return string
   *   The id.
   */
  protected function dialogId(LayoutParagraphsLayout $layout) {
    return Html::getId('lpb-dialog-' . $layout->id());
  }

  /**
   * Generates a dialog selector for a given layout.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout paragraphs layout object.
   *
   * @return string
   *   The dom selector for the dialog.
   */
  protected function dialogSelector(LayoutParagraphsLayout $layout) {
    return '#' . $this->dialogId($layout);
  }

  /**
   * Returns a CloseDialogComand with the correct selector.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout paragraphs layout object.
   *
   * @return \Drupal\Core\Ajax\CommandInterface
   *   The close command.
   */
  protected function closeDialogCommand(LayoutParagraphsLayout $layout) {
    return new CloseDialogCommand($this->dialogSelector($layout));
  }

}
