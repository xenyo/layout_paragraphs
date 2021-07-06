<?php

namespace Drupal\layout_paragraphs\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\DialogHelperTrait;

/**
 * Class definition for ComponentFormController.
 */
class ComponentFormController extends ControllerBase {

  use AjaxHelperTrait;
  use DialogHelperTrait;

  /**
   * The options to use for opening the dialog.
   *
   * @var array
   */
  protected $dialogOptions = [
    'modal' => TRUE,
    'width' => '70%',
    'minWidth' => 500,
    'maxWidth' => 1000,
    'draggable' => TRUE,
    'classes' => [
      'ui-dialog' => 'lpe-dialog',
    ],
  ];

  /**
   * Responds with a component insert form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs layout object.
   * @param \Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The Paragraph Type to insert.
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   *   A build array or Ajax respone.
   */
  public function insertForm(Request $request, LayoutParagraphsLayout $layout_paragraphs_layout, ParagraphsTypeInterface $paragraph_type) {

    $parent_uuid = $request->query->get('parent_uuid');
    $region = $request->query->get('region');
    $sibling_uuid = $request->query->get('sibling_uuid');
    $placement = $request->query->get('placement');

    $form = $this->formBuilder()->getForm('\Drupal\layout_paragraphs\Form\InsertComponentForm', $layout_paragraphs_layout, $paragraph_type, $parent_uuid, $region, $sibling_uuid, $placement);
    return $this->openForm($form, $layout_paragraphs_layout);
  }

  /**
   * Returns the form, with ajax if appropriate.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs layout object.
   *
   * @return array|AjaxResponse
   *   The form or ajax response.
   */
  protected function openForm(array $form, LayoutParagraphsLayout $layout_paragraphs_layout) {
    if ($this->isAjax()) {
      $response = new AjaxResponse();
      $selector = $this->dialogSelector($layout_paragraphs_layout);
      $modal_settings = $this->config('layout_paragraphs.modal_settings');
      $dialog_options = [
        'modal' => TRUE,
        'width' => $modal_settings->get('width'),
        'height' => $modal_settings->get('width'),
        'autoResize' => $modal_settings->get('autoresize'),
      ];
      $response->addCommand(new OpenDialogCommand($selector, $form['#title'], $form, $dialog_options));
      return $response;
    }
    return $form;
  }

}
