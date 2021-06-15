<?php

namespace Drupal\layout_paragraphs\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;

/**
 * Class LayoutParagraphsBuilderController.
 *
 * Builds a LayoutParagraphsBuilderForm, using Ajax commands if appropriate.
 */
class LayoutParagraphsBuilderController extends ControllerBase {

  use AjaxHelperTrait;

  /**
   * Builds the layout paragraphs builder form.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs layout object.
   *
   * @return mixed
   *   An ajax response or the form.
   */
  public function build(LayoutParagraphsLayout $layout_paragraphs_layout) {
    $form = $this->formBuilder()->getForm(
      '\Drupal\layout_paragraphs\Form\LayoutParagraphsBuilderForm',
      $layout_paragraphs_layout
    );
    if ($this->isAjax()) {
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('[data-lpb-id="' . $layout_paragraphs_layout->id() . '"]', $form));
      return $response;
    }
    return $form;
  }

}
