<?php

namespace Drupal\layout_paragraphs\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Serialization\Json;

/**
 * LayoutParagraphsEditor controller class.
 */
class LayoutParagraphsBuilderController extends ControllerBase {

  use AjaxHelperTrait;

  /**
   * The tempstore service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $tempstore;

  /**
   * Settings to pass to jQuery modal dialog.
   *
   * @var array
   */
  protected $modalSettings;

  /**
   * Construct a Layout Paragraphs Editor controller.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository $tempstore
   *   The tempstore service.
   */
  public function __construct(LayoutParagraphsLayoutTempstoreRepository $tempstore) {
    $this->tempstore = $tempstore;
    $this->modalSettings = [
      'width' => '70%',
      'minWidth' => 500,
      'draggable' => TRUE,
      'classes' => [
        'ui-dialog' => 'lpe-dialog',
      ],
      'modal' => TRUE,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository')
    );
  }

  /**
   * Returns a paragraph edit form as a dialog.
   *
   * @param Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The editor instance.
   * @param string $paragraph_uuid
   *   The uuid of the paragraph we are editing.
   *
   * @return AjaxCommand
   *   The dialog command with edit form.
   */
  public function editForm(LayoutParagraphsLayout $layout_paragraphs_layout, string $paragraph_uuid) {
    $response = new AjaxResponse();
    $paragraph = $layout_paragraphs_layout
      ->getComponentByUuid($paragraph_uuid)
      ->getEntity();
    $paragraph_type = $paragraph->getParagraphType();
    $label = $this->t('Edit @type', ['@type' => $paragraph_type->label()]);
    $form = $this->formBuilder()->getForm(
      '\Drupal\layout_paragraphs\Form\LayoutParagraphsComponentEditForm',
      $layout_paragraphs_layout,
      $paragraph,
    );

    $this->addFormResponse($response, $label, $form);
    return $response;
  }

  /**
   * Reorders a Layout Paragraphs Layout's components.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing a "layoutParagraphsState" POST parameter.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The Layout Paragraphs Layout object.
   */
  public function reorderComponents(Request $request, LayoutParagraphsLayout $layout_paragraphs_layout) {
    if ($ordered_components = Json::decode($request->request->get("layoutParagraphsState"))) {
      $layout_paragraphs_layout->reorderComponents($ordered_components);
      $this->tempstore->set($layout_paragraphs_layout);
    }
    return ['#markup' => 'success'];
  }

  /**
   * Deletes a Layout Paragraph Layout's component.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The Layout Paragraphs Layout object.
   * @param string $uuid
   *   The uuid of the paragraph to delete.
   */
  public function deleteComponent(LayoutParagraphsLayout $layout_paragraphs_layout, string $uuid) {
    $layout_paragraphs_layout->deleteComponent($uuid, TRUE);
    $this->tempstore->set($layout_paragraphs_layout);
    return ['#markup' => 'success'];
  }

  /**
   * Insert a sibling paragraph into the field.
   *
   * @param Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The Layout Paragraphs Layout object.
   * @param string $sibling_uuid
   *   The uuid of the existing sibling component.
   * @param Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The paragraph type for the new content being added.
   * @param string $proximity
   *   Whether to insert the new component "before" or "after" the sibling.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   Returns the edit form render array.
   */
  public function insertSibling(
    LayoutParagraphsLayout $layout_paragraphs_layout,
    string $sibling_uuid,
    ParagraphsTypeInterface $paragraph_type = NULL,
    string $proximity = 'after') {

    $entity_type = $this->entityTypeManager()->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    $label = $this->t('New @type', ['@type' => $paragraph_type->label()]);

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $this->entityTypeManager()->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);

    switch ($proximity) {
      case "before":
        $layout_paragraphs_layout->insertBeforeComponent($sibling_uuid, $paragraph);
        break;

      case "after":
        $layout_paragraphs_layout->insertAfterComponent($sibling_uuid, $paragraph);
        break;
    }

    $response = new AjaxResponse();
    $form = $this->formBuilder()->getForm(
      '\Drupal\layout_paragraphs\Form\LayoutParagraphsComponentAddForm',
      $layout_paragraphs_layout,
      $paragraph,
      '[data-uuid="' . $sibling_uuid . '"]',
      $proximity
    );
    $this->addFormResponse($response, $label, $form);
    return $response;

  }

  /**
   * Insert a new component into a section.
   *
   * @param Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The Layout Paragraphs Layout object.
   * @param string $parent_uuid
   *   The uuid of the parent section.
   * @param string $region
   *   The region to insert into.
   * @param Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The paragraph type to add.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   Returns an ajax response object with the add form dialog.
   */
  public function insertIntoRegion(
    LayoutParagraphsLayout $layout_paragraphs_layout,
    string $parent_uuid,
    string $region,
    ParagraphsTypeInterface $paragraph_type) {

    $entity_type = $this->entityTypeManager()->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    $label = $this->t('New @type', ['@type' => $paragraph_type->label()]);

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $this->entityTypeManager()->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);

    $layout_paragraphs_layout->insertIntoRegion($parent_uuid, $region, $paragraph);
    $response = new AjaxResponse();

    $form = $this->formBuilder()->getForm(
      '\Drupal\layout_paragraphs\Form\LayoutParagraphsComponentAddForm',
      $layout_paragraphs_layout,
      $paragraph,
      '[data-region-uuid="' . $parent_uuid . '-' . $region . '"]',
      'append'
    );
    $this->addFormResponse($response, $label, $form);
    return $response;
  }

  /**
   * Insert a new component into the layout.
   *
   * @param Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs editor from the tempstore.
   * @param Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The paragraph type for the new content being added.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   Returns an ajax response object with the add form dialog.
   */
  public function insertComponent(
    LayoutParagraphsLayout $layout_paragraphs_layout,
    ParagraphsTypeInterface $paragraph_type) {

    $entity_type = $this->entityTypeManager()->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    $label = $this->t('New @type', ['@type' => $paragraph_type->label()]);

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $this->entityTypeManager()->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);
    $layout_paragraphs_layout->appendComponent($paragraph);

    $response = new AjaxResponse();
    $form = $this->formBuilder()->getForm(
      '\Drupal\layout_paragraphs\Form\LayoutParagraphsComponentAddForm',
      $layout_paragraphs_layout,
      $paragraph,
      '[data-lp-builder-id="' . $layout_paragraphs_layout->id() . '"]',
      'append'
    );
    $this->addFormResponse($response, $label, $form);
    return $response;

  }

  /**
   * Adds the paragraph form to an ajax response.
   *
   * @param Drupal\Core\Ajax\AjaxResponse $response
   *   The ajax response object.
   * @param string $title
   *   The form title.
   * @param array $form
   *   The form array.
   */
  protected function addFormResponse(AjaxResponse &$response, string $title, array $form) {
    $selector = '#' . $form['#dialog_id'];
    $response->addCommand(new OpenDialogCommand($selector, $title, $form, $this->modalSettings));
  }

}
