<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Access\AccessResult;
use Drupal\field_group\FormatterHelper;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutRefreshTrait;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\layout_paragraphs\DialogHelperTrait;

/**
 * Class LayoutParagraphsComponentFormBase.
 *
 * Base form for Layout Paragraphs component forms.
 */
abstract class ComponentFormBase extends FormBase {

  use AjaxFormHelperTrait;
  use LayoutParagraphsLayoutRefreshTrait;
  use DialogHelperTrait;

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
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    LayoutParagraphsLayoutTempstoreRepository $tempstore,
    EntityTypeManagerInterface $entity_type_manager,
    LayoutPluginManager $layout_plugin_manager,
    ModuleHandler $module_handler,
    EventDispatcherInterface $event_dispatcher,
    EntityRepositoryInterface $entity_repository
    ) {
    $this->tempstore = $tempstore;
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutPluginManager = $layout_plugin_manager;
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.core.layout'),
      $container->get('module_handler'),
      $container->get('event_dispatcher'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_component_form';
  }

  /**
   * Builds a component (paragraph) edit form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function buildComponentForm(
    array $form,
    FormStateInterface $form_state) {

    $this->initFormLangcodes($form_state);
    $display = EntityFormDisplay::collectRenderDisplay($this->paragraph, 'default');
    $display->buildForm($this->paragraph, $form, $form_state);
    $this->paragraphType = $this->paragraph->getParagraphType();

    $form += [
      '#title' => $this->formTitle(),
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
          '#attributes' => [
            'class' => ['lpb-btn--save'],
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
            'class' => [
              'dialog-cancel',
              'lpb-btn--cancel',
            ],
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
   * Validate the component form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $paragraph = $this->buildParagraphComponent($form, $form_state);
    $violations = $paragraph->validate();
    // Remove violations of inaccessible fields.
    $violations->filterByFieldAccess($this->currentUser());
    // The paragraph component was validated.
    $paragraph->setValidationRequired(FALSE);
    // Flag entity level violations.
    foreach ($violations->getEntityViolations() as $violation) {
      /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
      $form_state->setErrorByName('', $violation->getMessage());
    }
    $form['#display']->flagWidgetsErrorsFromViolations($violations, $form, $form_state);
  }

  /**
   * Saves the paragraph component.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->paragraph = $this->buildParagraphComponent($form, $form_state);
  }

  /**
   * Builds the paragraph component using submitted form values.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph entity.
   */
  protected function buildParagraphComponent(array $form, FormStateInterface $form_state) {
    /** @var Drupal\Core\Entity\Entity\EntityFormDisplay $display */
    $display = $form['#display'];

    $paragraph = clone $this->paragraph;

    $paragraphs_type = $paragraph->getParagraphType();
    if ($paragraphs_type->hasEnabledBehaviorPlugin('layout_paragraphs')) {
      $layout_paragraphs_plugin = $paragraphs_type->getEnabledBehaviorPlugins()['layout_paragraphs'];
      $subform_state = SubformState::createForSubform($form['layout_paragraphs'], $form, $form_state);
      $layout_paragraphs_plugin->submitBehaviorForm($paragraph, $form['layout_paragraphs'], $subform_state);
    }

    $paragraph->setNeedsSave(TRUE);
    $display->extractFormValues($paragraph, $form, $form_state);
    return $paragraph;
  }

  /**
   * Create the form title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The form title.
   */
  protected function formTitle() {
    return $this->t('Component form');
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
    $this->ajaxCloseForm($response);
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
   * Closes the form with ajax.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The ajax response.
   */
  protected function ajaxCloseForm(AjaxResponse &$response) {
    $selector = $this->dialogSelector($this->layoutParagraphsLayout);
    $response->addCommand(new CloseDialogCommand($selector));
  }

  /**
   * Renders a single Layout Paragraphs Layout paragraph entity.
   *
   * @param string $uuid
   *   The uuid of the paragraph entity to render.
   *
   * @return array
   *   The paragraph render array.
   */
  protected function renderParagraph(string $uuid) {
    return [
      '#type' => 'layout_paragraphs_builder',
      '#layout_paragraphs_layout' => $this->layoutParagraphsLayout,
      '#uuid' => $uuid,
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

  /**
   * Initializes form language code values.
   *
   * See Drupal\Core\Entity\ContentEntityForm.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function initFormLangcodes(FormStateInterface $form_state) {
    // Store the entity default language to allow checking whether the form is
    // dealing with the original entity or a translation.
    if (!$form_state
      ->has('entity_default_langcode')) {
      $form_state
        ->set('entity_default_langcode', $this->paragraph
          ->getUntranslated()
          ->language()
          ->getId());
    }

    // This value might have been explicitly populated to work with a particular
    // entity translation. If not we fall back to the most proper language based
    // on contextual information.
    if (!$form_state
      ->has('langcode')) {

      // Imply a 'view' operation to ensure users edit entities in the same
      // language they are displayed. This allows to keep contextual editing
      // working also for multilingual entities.
      $form_state
        ->set('langcode', $this->entityRepository
          ->getTranslationFromContext($this->paragraph)
          ->language()
          ->getId());
    }
  }

}
