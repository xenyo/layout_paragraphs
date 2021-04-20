<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class LayoutParagraphsSectionsSettingsForm.
 */
class LayoutParagraphsUpdateStorageForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_update_storage';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update storage'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    layout_paragraphs_update_storage();
  }

}
