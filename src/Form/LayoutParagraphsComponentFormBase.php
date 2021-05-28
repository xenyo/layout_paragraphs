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
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Layout\LayoutPluginManager;

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
   * The layout plugin manager service.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManager
   */
  protected $layoutPluginManager;

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
   * The view mode to use for rendering paragraphs.
   *
   * @var string
   */
  protected $previewViewMode;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    LayoutParagraphsLayoutTempstoreRepository $tempstore,
    EntityTypeManagerInterface $entity_type_manager,
    LayoutPluginManager $layout_plugin_manager,
    ModuleHandler $module_handler) {
    $this->tempstore = $tempstore;
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutPluginManager = $layout_plugin_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.core.layout'),
      $container->get('module_handler')
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
    $paragraph = NULL,
    $preview_view_mode = '') {

    $this->layoutParagraphsLayout = $layout_paragraphs_layout;
    $this->paragraph = $paragraph;
    $this->previewViewMode = $preview_view_mode;

    $display = EntityFormDisplay::collectRenderDisplay($this->paragraph, 'default');
    $display->buildForm($this->paragraph, $form, $form_state);
    $this->paragraphType = $this->paragraph->getParagraphType();

    $form += [
      '#paragraph' => $this->paragraph,
      '#display' => $display,
      '#tree' => TRUE,
      '#after_build' => [
        [$this, 'afterBuild'],
      ],
      'actions' => [
        '#weight' => 20,
        '#type' => 'actions',
        'submit' => [
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
    if ($this->moduleHandler->moduleExists('field_group')) {
      $context = [
        'entity_type' => $this->paragraph->getEntityTypeId(),
        'bundle' => $this->paragraph->bundle(),
        'entity' => $this->paragraph,
        'context' => 'form',
        'display_context' => 'form',
        'mode' => $display->getMode(),
      ];
      // phpcs:ignore
      field_group_attach_groups($form, $context);
      if (method_exists(FormatterHelper::class, 'formProcess')) {
        $form['#process'][] = [FormatterHelper::class, 'formProcess'];
      }
      elseif (function_exists('field_group_form_pre_render')) {
        $form['#pre_render'][] = 'field_group_form_pre_render';
      }
      elseif (function_exists('field_group_form_process')) {
        $form['#process'][] = 'field_group_form_process';
      }
    }
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
  public function layoutParagraphsBehaviorForm(array $element, FormStateInterface $form_state, array &$form) {

    $layout_paragraphs_plugin = $this->paragraphType->getEnabledBehaviorPlugins()['layout_paragraphs'];
    $subform_state = SubformState::createForSubform($element, $form, $form_state);
    if ($layout_paragraphs_plugin_form = $layout_paragraphs_plugin->buildBehaviorForm($this->paragraph, $element, $subform_state)) {
      $element = $layout_paragraphs_plugin_form;
      // Adjust the Ajax callback to include the entire layout paragraphs form.
      $element_id = Html::getId('layout-paragraphs-element');
      $element['#prefix'] = '<div id="' . $element_id . '">';
      $element['#suffix'] = '</div>';
      $element['layout']['#ajax']['callback'] = [$this, 'ajaxCallback'];
      $element['layout']['#ajax']['wrapper'] = $element_id;
    }
    return $element;
  }

  /**
   * Ajax form callback.
   *
   * Returns the layout paragraphs behavior form,
   * which includes the orphaned items element when necessary.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The render array.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    return $form['layout_paragraphs'];
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
      '#preview_view_mode' => $this->previewViewMode,
    ];
  }

  /**
   * Returns an array of region names for a given layout.
   *
   * @param string $layout_id
   *   The layout id.
   *
   * @return array
   *   An array of regions.
   */
  protected function getLayoutRegionNames($layout_id) {
    return array_map(function ($region) {
      return $region['label'];
    }, $this->getLayoutRegions($layout_id));
  }

  /**
   * Returns an array of regions for a given layout.
   *
   * @param string $layout_id
   *   The layout id.
   *
   * @return array
   *   An array of regions.
   */
  protected function getLayoutRegions($layout_id) {
    if (!$layout_id) {
      return [];
    }
    $instance = $this->layoutPluginManager->createInstance($layout_id);
    $definition = $instance->getPluginDefinition();
    return $definition->getRegions();
  }

}
