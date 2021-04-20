<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\SubformState;
use Drupal\layout_paragraphs_editor\Ajax\LayoutParagraphsEditorInvokeHookCommand;

/**
 * Class LayoutParagraphsComponentEditForm.
 *
 * Builds the edit form for an existing layout paragraphs paragraph entity.
 */
class LayoutParagraphsComponentEditForm extends LayoutParagraphsComponentFormBase {

  /**
   * {@inheritDoc}
   */
  public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {

    $uuid = $this->paragraph->uuid();
    $rendered_item = $this->renderParagraph($uuid);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("[data-uuid={$uuid}]", $rendered_item));
    $response->addCommand(new LayoutParagraphsEditorInvokeHookCommand(
      'updateComponent',
      [
        'layoutId' => $this->layoutParagraphsLayout->id(),
        'componentUuid' => $uuid,
      ]
    ));

    $response->addCommand(new InvokeCommand("[data-uuid={$uuid}]", "focus"));
    $response->addCommand(new CloseDialogCommand('#' . $form['#dialog_id']));

    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $form['#paragraph'];
    /** @var Drupal\Core\Entity\Entity\EntityFormDisplay $display */
    $display = $form['#display'];

    $paragraphs_type = $paragraph->getParagraphType();
    if ($paragraphs_type->hasEnabledBehaviorPlugin('layout_paragraphs')) {
      $layout_paragraphs_plugin = $paragraphs_type->getEnabledBehaviorPlugins()['layout_paragraphs'];
      $subform_state = SubformState::createForSubform($form['layout_paragraphs'], $form, $form_state);
      $layout_paragraphs_plugin->submitBehaviorForm($paragraph, $form['layout_paragraphs'], $subform_state);
    }

    $paragraph->setNeedsSave(TRUE);
    $display->extractFormValues($paragraph, $form['entity_form'], $form_state);

    $this->layoutParagraphsLayout->setComponent($paragraph);
    $this->tempstore->set($this->layoutParagraphsLayout);
  }

}
