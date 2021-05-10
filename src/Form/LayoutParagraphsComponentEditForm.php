<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\SubformState;
use Drupal\layout_paragraphs\Ajax\LayoutParagraphsBuilderInvokeHookCommand;

/**
 * Class LayoutParagraphsComponentEditForm.
 *
 * Builds the edit form for an existing layout paragraphs paragraph entity.
 */
class LayoutParagraphsComponentEditForm extends LayoutParagraphsComponentFormBase {

  /**
   * {@inheritDoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $layout_paragraphs_layout = NULL,
    $paragraph = NULL,
    $preview_view_mode = '') {

    $form = parent::buildForm($form, $form_state, $layout_paragraphs_layout, $paragraph, $preview_view_mode);
    if ($selected_layout = $form_state->getValue(['layout_paragraphs', 'layout'])) {
      $section = $this->layoutParagraphsLayout->getLayoutSection($this->paragraph);
      if ($section && $selected_layout != $section->getLayoutId()) {
        $form['layout_paragraphs']['move_items'] = [
          '#old_layout' => $section->getLayoutId(),
          '#new_layout' => $selected_layout,
          '#weight' => 5,
          '#process' => [
            [$this, 'orphanedItemsElement'],
          ],
        ];
      }
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {

    $uuid = $this->paragraph->uuid();
    $rendered_item = $this->renderParagraph($uuid);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("[data-uuid={$uuid}]", $rendered_item));
    $response->addCommand(new LayoutParagraphsBuilderInvokeHookCommand(
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
   * Form #process callback.
   *
   * Builds the orphaned items form element for when a new layout's
   * regions do not match the previous one's.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $form
   *   The complete form.
   *
   * @return array
   *   The form element.
   */
  public function orphanedItemsElement(array $element, FormStateInterface $form_state, array &$form) {

    $old_regions = $this->getLayoutRegionNames($element['#old_layout']);
    $new_regions = $this->getLayoutRegionNames($element['#new_layout']);
    $section = $this->layoutParagraphsLayout->getLayoutSection($this->paragraph);
    $has_orphans = FALSE;

    foreach ($old_regions as $region_name => $region) {
      if ($section->getComponentsForRegion($region_name) && empty($new_regions[$region_name])) {
        $has_orphans = TRUE;
        $element[$region_name] = [
          '#type' => 'select',
          '#options' => $new_regions,
          '#wrapper_attributes' => ['class' => ['container-inline']],
          '#title' => $this->t('Move items from "@region" to', ['@region' => $region]),
        ];
      }
    }
    if ($has_orphans) {
      $element += [
        '#type' => 'fieldset',
        '#title' => $this->t('Move Orphaned Items'),
        '#description' => $this->t('Choose where to move items for missing regions.'),
      ];
      $form['#submit'][] = [$this, 'moveItemsSubmit'];
    }
    return $element;
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
    $display->extractFormValues($paragraph, $form, $form_state);

    $this->layoutParagraphsLayout->setComponent($paragraph);
    $this->tempstore->set($this->layoutParagraphsLayout);
  }

  /**
   * Form #submit callback.
   *
   * Moves items from removed regions into designated new ones.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function moveItemsSubmit(array &$form, FormStateInterface $form_state) {
    if ($move_items = $form_state->getValue(['layout_paragraphs', 'move_items'])) {
      $section = $this->layoutParagraphsLayout->getLayoutSection($this->paragraph);
      foreach ($move_items as $source => $destination) {
        $components = $section->getComponentsForRegion($source);
        foreach ($components as $component) {
          $component->setSettings(['region' => $destination]);
          $this->layoutParagraphsLayout->setComponent($component->getEntity());
        }
      }
      $this->tempstore->set($this->layoutParagraphsLayout);
    }
  }

}
