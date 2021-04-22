<?php

namespace Drupal\layout_paragraphs\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field_group\FormatterHelper;
use Drupal\Core\Form\SubformState;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Render\Renderer;

/**
 * Class LayoutParagraphsComponentFormBase.
 *
 * Base form for layout paragraphs paragraph forms.
 */
abstract class LayoutParagraphsComponentFormBase extends FormBase {

  use AjaxFormHelperTrait;

  /**
   * The tempstore service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $tempstore;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The layout object.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layoutParagraphsLayout;

  /**
   * The paragraph type.
   *
   * @var \Drupal\paragraphs\Entity\ParagraphsType
   */
  protected $paragraphType;

  /**
   * The paragraph.
   *
   * @var \Drupal\paragraphs\Entity\Paragraph
   */
  protected $paragraph;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    LayoutParagraphsLayoutTempstoreRepository $tempstore,
    EntityTypeManagerInterface $entity_type_manager,
    Renderer $renderer) {
    $this->tempstore = $tempstore;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_component_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $layout_paragraphs_layout = NULL,
    $paragraph = NULL) {

    $this->layoutParagraphsLayout = $layout_paragraphs_layout;
    $this->paragraph = $paragraph;

    $display = EntityFormDisplay::collectRenderDisplay($this->paragraph, 'default');
    $this->paragraphType = $this->paragraph->getParagraphType();

    $form = [
      '#paragraph' => $this->paragraph,
      '#display' => $display,
      '#tree' => TRUE,
      '#after_build' => [
        [$this, 'afterBuild'],
      ],
      'entity_form' => [
        '#weight' => 10,
        '#parents' => ['entity_form'],
      ],
      'actions' => [
        '#weight' => 20,
        '#type' => 'actions',
        'save' => [
          '#type' => 'submit',
          '#value' => $this->t('Save'),
          '#ajax' => [
            'callback' => '::ajaxSubmit',
            'progress' => 'none',
          ],
        ],
        'cancel' => [
          '#type' => 'button',
          '#value' => $this->t('Cancel'),
          '#ajax' => [
            'callback' => '::cancel',
            'progress' => 'none',
          ],
          '#attributes' => [
            'class' => ['dialog-cancel'],
          ],
        ],
      ],
    ];

    if ($this->paragraphType->hasEnabledBehaviorPlugin('layout_paragraphs')) {
      $form['layout_paragraphs'] = [
        '#process' => [
          [$this, 'layoutParagraphsBehaviorForm'],
        ],
      ];
    }

    // Support for Field Group module based on Paragraphs module.
    // @todo Remove as part of https://www.drupal.org/node/2640056
    if (\Drupal::moduleHandler()->moduleExists('field_group')) {
      $context = [
        'entity_type' => $this->paragraph->getEntityTypeId(),
        'bundle' => $this->paragraph->bundle(),
        'entity' => $this->paragraph,
        'context' => 'form',
        'display_context' => 'form',
        'mode' => $display->getMode(),
      ];
      // phpcs:ignore
      field_group_attach_groups($form['entity_form'], $context);
      if (method_exists(FormatterHelper::class, 'formProcess')) {
        $form['entity_form']['#process'][] = [FormatterHelper::class, 'formProcess'];
      }
      elseif (function_exists('field_group_form_pre_render')) {
        $form['entity_form']['#pre_render'][] = 'field_group_form_pre_render';
      }
      elseif (function_exists('field_group_form_process')) {
        $form['entity_form']['#process'][] = 'field_group_form_process';
      }
    }

    $display->buildForm($this->paragraph, $form['entity_form'], $form_state);
    return $form;
  }

  /**
   * After build callback fixes issues with data-drupal-selector.
   *
   * See https://www.drupal.org/project/drupal/issues/2897377
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function afterBuild(array $element, FormStateInterface $form_state) {
    $parents = array_merge($element['#parents'], [$this->getFormId()]);
    $unprocessed_id = 'edit-' . implode('-', $parents);
    $element['#attributes']['data-drupal-selector'] = Html::getId($unprocessed_id);
    $element['#dialog_id'] = $unprocessed_id . '-dialog';
    return $element;
  }

  /**
   * Form #process callback.
   *
   * Renders the layout paragraphs behavior form for layout selection.
   *
   * @param array $element
   *   The form element.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The complete form array.
   *
   * @return array
   *   The processed element.
   */
  public function layoutParagraphsBehaviorForm(array $element, FormStateInterface $form_state, array $form) {

    $layout_paragraphs_plugin = $this->paragraphType->getEnabledBehaviorPlugins()['layout_paragraphs'];
    $subform_state = SubformState::createForSubform($element, $form, $form_state);
    if ($layout_paragraphs_plugin_form = $layout_paragraphs_plugin->buildBehaviorForm($this->paragraph, $element, $subform_state)) {
      $element = $layout_paragraphs_plugin_form;
    }
    return $element;
  }

  /**
   * Form #ajax callback.
   *
   * Cancels the edit operation and closes the dialog.
   *
   * @param array $form
   *   The form array.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function cancel(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand('#' . $form['#dialog_id']));
    return $response;
  }

  /**
   * Access check.
   *
   * @todo Actually check something.
   *
   * @return bool
   *   True if access.
   */
  public function access() {
    return AccessResult::allowed();
  }

  /**
   * Renders a single Layout Paragraphs Layout paragraph entity.
   *
   * @param string $uuid
   *   The uuid of the paragraph entity to render.
   *
   * @return \Drupal\Core\Render\Markup
   *   The rendered paragraph.
   */
  protected function renderParagraph(string $uuid) {
    return [
      '#type' => 'layout_paragraphs_builder',
      '#layout_paragraphs_layout' => $this->layoutParagraphsLayout,
      '#uuid' => $uuid,
    ];
  }

}
