<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\Ajax\LayoutParagraphsBuilderInvokeHookCommand;

/**
 * Class InsertComponentForm.
 *
 * Builds the form for inserting a new component.
 */
class InsertComponentForm extends ComponentFormBase {

  /**
   * DOM element selector.
   *
   * @var string
   */
  protected $domSelector;

  /**
   * Whether to use "before", "after", or "append.".
   *
   * @var string
   */
  protected $method;

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
   * @param string $parent_uuid
   *   The parent component's uuid.
   * @param string $region
   *   The region to insert the new component into.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    LayoutParagraphsLayout $layout_paragraphs_layout = NULL,
    ParagraphsType $paragraph_type = NULL,
    string $parent_uuid = NULL,
    string $region = NULL
    ) {

    $this->layoutParagraphsLayout = $layout_paragraphs_layout;
    $this->paragraph = $this->newParagraph($paragraph_type);
    $this->method = 'prepend';

    if ($parent_uuid && $region) {
      $this->domSelector = '[data-region-uuid="' . $parent_uuid . '-' . $region . '"]';
      $this->layoutParagraphsLayout->insertIntoRegion($parent_uuid, $region, $this->paragraph);
    }
    else {
      $this->domSelector = '[data-layout-id="' . $this->layoutParagraphsLayout->id() . '"]';
      $this->layoutParagraphsLayout->appendComponent($this->paragraph);
    }

    return $this->buildComponentForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {

    $uuid = $this->paragraph->uuid();
    $rendered_item = $this->renderParagraph($uuid);

    $response = new AjaxResponse();

    switch ($this->method) {
      case 'before':
        $response->addCommand(new BeforeCommand($this->domSelector, $rendered_item));
        break;

      case 'after':
        $response->addCommand(new AfterCommand($this->domSelector, $rendered_item));
        break;

      case 'append':
        $response->addCommand(new AppendCommand($this->domSelector, $rendered_item));
        break;

      case 'prepend':
        $response->addCommand(new PrependCommand($this->domSelector, $rendered_item));
        break;
    }

    $response->addCommand(new LayoutParagraphsBuilderInvokeHookCommand(
      'component:insert',
      [
        'layoutId' => $this->layoutParagraphsLayout->id(),
        'componentUuid' => $uuid,
      ]
    ));
    $response->addCommand(new InvokeCommand("[data-uuid={$uuid}]", "focus"));
    $response->addCommand(new CloseModalDialogCommand());

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
    $display->extractFormValues($paragraph, $form, $form_state);
    $this->layoutParagraphsLayout->setComponent($paragraph);
    $this->tempstore->set($this->layoutParagraphsLayout);
  }

  /**
   * Creates a new, empty paragraph empty of the provided type.
   *
   * @param \Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The paragraph type.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The new paragraph.
   */
  protected function newParagraph(ParagraphsTypeInterface $paragraph_type) {
    $entity_type = $this->entityTypeManager->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $this->entityTypeManager->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);
    return $paragraph;
  }

}
