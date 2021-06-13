<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;

/**
 * Class LayoutParagraphsBuilderForm.
 *
 * Base form for layout paragraphs paragraph forms.
 */
class FrontendBuilderForm extends FormBase {

  /**
   * A layout paragraphs layout object.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layoutParagraphsLayout;

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_frontend_builder_form';
  }

  /**
   * Builds the layout paragraphs builder form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs layout object.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    LayoutParagraphsLayout $layout_paragraphs_layout = NULL) {
    $this->layoutParagraphsLayout = $layout_paragraphs_layout;
    $form['layout_paragraphs_builder_ui'] = [
      '#type' => 'layout_paragraphs_builder',
      '#layout_paragraphs_layout' => $this->layoutParagraphsLayout,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'save' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#submit' => [[$this, 'saveLayout']],
        '#ajax' => [
          'callback' => [$this, 'closeBuilder'],
        ],
      ],
      'cancel' => [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#submit' => [[$this, 'closeBuilder']],
        '#ajax' => [
          'callback' => [$this, 'closeBuilder'],
        ],
      ],
    ];
    return $form;
  }

  /**
   * Saves the layout to its parent entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function saveLayout(array &$form, FormStateInterface $form_state) {
    $entity = $this->layoutParagraphsLayout->getEntity();
    $field_name = $this->layoutParagraphsLayout->getFieldName();
    $entity->$field_name = $this->layoutParagraphsLayout->getParagraphsReferenceField();
    $entity->save();
  }

  /**
   * Closes the builder and returns the rendered layout.
   */
  public function closeBuilder() {
    $rendered_layout = $this->layoutParagraphsLayout->getParagraphsReferenceField()->view();
    return $rendered_layout;
  }

  public function refreshLayout($form, $form_state) {
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
