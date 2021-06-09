<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;

/**
 * Class InsertComponentSiblingForm.
 *
 * Builds the form for inserting a sibling component.
 */
class InsertComponentSiblingForm extends InsertComponentForm {

  /**
   * {@inheritDoc}
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs layout object.
   * @param \Drupal\paragraphs\Entity\ParagraphsType $paragraph_type
   *   The paragraph type.
   * @param string $sibling_uuid
   *   The uuid of the sibling component.
   * @param string $placement
   *   Where to place the new component - either "before" or "after".
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    LayoutParagraphsLayout $layout_paragraphs_layout = NULL,
    ParagraphsType $paragraph_type = NULL,
    string $sibling_uuid = NULL,
    string $placement = NULL
    ) {

    $this->layoutParagraphsLayout = $layout_paragraphs_layout;
    $this->paragraph = $this->newParagraph($paragraph_type);
    $this->method = $placement;
    $this->domSelector = '[data-uuid="' . $sibling_uuid . '"]';

    switch ($placement) {
      case 'before':
        $this->layoutParagraphsLayout->insertBeforeComponent($sibling_uuid, $this->paragraph);
        break;

      case 'after':
        $this->layoutParagraphsLayout->insertAfterComponent($sibling_uuid, $this->paragraph);
        break;
    }

    return $this->buildComponentForm($form, $form_state);
  }

}
