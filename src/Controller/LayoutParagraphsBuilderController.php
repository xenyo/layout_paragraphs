<?php

namespace Drupal\layout_paragraphs\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;

/**
 * Class LayoutParagraphsBuilderController.
 *
 * Builds a LayoutParagraphsBuilderForm, using Ajax commands if appropriate.
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
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The Layout Paragraphs Builder Access service.
   *
   * @var \Drupal\layout_paragraphs\Access\LayoutParagraphsBuilderAccess
   */
  protected $layoutParagraphsBuilderAccess;

  /**
   * {@inheritDoc}
   */
  public function __construct(LayoutParagraphsLayoutTempstoreRepository $tempstore, EntityDisplayRepositoryInterface $entity_display_repository, $layout_paragraphs_builder_access) {
    $this->tempstore = $tempstore;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->layoutParagraphsBuilderAccess = $layout_paragraphs_builder_access;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('entity_display.repository'),
      $container->get('layout_paragraphs.builder_access')
    );
  }

  /**
   * Builds the layout paragraphs builder form.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The parent entity that contains a layout.
   * @param string $field_name
   *   The matching name of the paragraphs reference field.
   * @param string $view_mode
   *   The view mode being rendered.
   *
   * @return mixed
   *   An ajax response or the form.
   */
  public function build(ContentEntityInterface $entity, $field_name, $view_mode) {

    $layout_paragraphs_layout = $this->getLayoutParagraphsLayout($entity, $field_name, $view_mode);
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

  /**
   * Access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The parent entity that contains a layout.
   * @param string $field_name
   *   The name of the reference field.
   * @param string $view_mode
   *   The view mode.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account, ContentEntityInterface $entity, $field_name, $view_mode) {
    return $this->layoutParagraphsBuilderAccess->access($account, $this->getLayoutParagraphsLayout($entity, $field_name, $view_mode));
  }

  /**
   * Returns a layout paragraphs layout.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The reference field name.
   * @param string $view_mode
   *   The view mode.
   *
   * @return \Drupal\layout_paragraphs\LayoutParagraphsLayout
   *   The layout.
   */
  protected function getLayoutParagraphsLayout(ContentEntityInterface $entity, string $field_name, string $view_mode) {
    $render_display = EntityViewDisplay::collectRenderDisplay($entity, $view_mode);
    $renderer = $render_display->getRenderer($field_name);
    $layout_paragraphs_settings = $renderer->getSettings() + ['reference_field_view_mode' => $view_mode];
    return new LayoutParagraphsLayout($entity->{$field_name}, $layout_paragraphs_settings);
  }

}
