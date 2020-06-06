<?php

namespace Drupal\layout_paragraphs\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Renderer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Html;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\layout_paragraphs\Ajax\LayoutParagraphsStateResetCommand;

/**
 * Entity Reference with Layout field widget.
 *
 * @FieldWidget(
 *   id = "layout_paragraphs",
 *   label = @Translation("Layout Paragraphs"),
 *   description = @Translation("Layout builder for paragraphs."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   },
 * )
 */
class LayoutParagraphsWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Entity Type Manager service property.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Layouts Manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManager
   */
  protected $layoutPluginManager;

  /**
   * The entity that contains this field.
   *
   * @var \Drupal\Core\Entity\Entity
   */
  protected $host;

  /**
   * The name of the field.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The Html Id of the wrapper element.
   *
   * @var string
   */
  protected $wrapperId;

  /**
   * The Html Id of the item form wrapper element.
   *
   * @var string
   */
  protected $itemFormWrapperId;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Core renderer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Core entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Layout\LayoutPluginManager $layout_plugin_manager
   *   Core layout plugin manager service.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   Core language manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    Renderer $renderer,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    LayoutPluginManager $layout_plugin_manager,
    LanguageManager $language_manager,
    AccountProxyInterface $current_user,
    EntityDisplayRepositoryInterface $entity_display_repository) {

    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->layoutPluginManager = $layout_plugin_manager;
    $this->fieldName = $this->fieldDefinition->getName();
    $this->languageManager = $language_manager;
    $this->currentUser = $current_user;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.core.layout'),
      $container->get('language_manager'),
      $container->get('current_user'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * Builds the main widget form array container/wrapper.
   *
   * Form elements for individual items are built by formElement().
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {

    $parents = $form['#parents'];
    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);

    $this->wrapperId = Html::getId(implode('-', $parents) . $this->fieldName . '-wrapper');
    $this->itemFormWrapperId = Html::getId(implode('-', $parents) . $this->fieldName . '-form');

    $handler_settings = $items->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];

    if (!empty($handler_settings['negate'])) {
      $target_bundles_options = array_keys($handler_settings['target_bundles_drag_drop']);
      $target_bundles = array_diff($target_bundles_options, $target_bundles);
    }
    $title = $this->fieldDefinition->getLabel();
    $description = FieldFilteredMarkup::create(\Drupal::token()->replace($this->fieldDefinition->getDescription()));

    // Save items to widget state when the form first loads.
    if (empty($widget_state['items'])) {
      $widget_state['items'] = [];
      $widget_state['open_form'] = FALSE;
      $widget_state['remove_item'] = FALSE;
      /** @var \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem $item */
      foreach ($items as $delta => $item) {
        if ($paragraph = $item->entity) {
          $widget_state['items'][$delta] = [
            'entity' => $paragraph,
            'weight' => $delta,
          ];
        }
      }
    }
    // Handle asymmetric translation if field is translatable
    // by duplicating items for enabled languages.
    if ($items->getFieldDefinition()->isTranslatable()) {
      $langcode = $this->languageManager
        ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
        ->getId();

      foreach ($widget_state['items'] as $delta => $item) {
        if (empty($item['entity']) || $item['entity']->get('langcode')->value == $langcode) {
          continue;
        }
        $duplicate = $item['entity']->createDuplicate();
        $duplicate->set('langcode', $langcode);
        $widget_state['items'][$delta]['entity'] = $duplicate;
      }
    }
    static::setWidgetState($parents, $this->fieldName, $form_state, $widget_state);
    $elements = parent::formMultipleElements($items, $form, $form_state);
    unset($elements['#theme']);
    $paragraph_items_count = 0;
    foreach (Element::children($elements) as $index) {
      $element =& $elements[$index];
      if (isset($element['_weight'])) {
        $element['_weight']['#wrapper_attributes']['class'] = ['hidden'];
        $element['_weight']['#attributes']['class'] = ['layout-paragraphs-weight'];
        // Move to top of container to ensure items are given the correct delta.
        $element['_weight']['#weight'] = -1000;
      }
      if (isset($element['#entity'])) {
        $paragraph_items_count++;
      }
    }
    $elements['#after_build'][] = [$this, 'buildLayouts'];

    if (isset($elements['#prefix'])) {
      unset($elements['#prefix']);
    }
    if (isset($elements['#suffix'])) {
      unset($elements['#suffix']);
    }
    $elements += [
      '#title' => $title,
      '#description' => $description,
      '#attributes' => ['class' => ['layout-paragraphs-field']],
      '#type' => 'fieldset',
    ];
    $elements['#id'] = $this->wrapperId;

    // Button to add new section and other paragraphs.
    $elements['add_more'] = [
      'actions' => [
        '#attributes' => ['class' => ['js-hide']],
        '#type' => 'container',
      ],
    ];
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo('paragraph');
    $options = [];
    $types = [
      'layout' => [],
      'content' => [],
    ];
    $bundle_ids = $target_bundles;
    $target_type = $items->getSetting('target_type');
    $definition = $this->entityTypeManager->getDefinition($target_type);
    $storage = $this->entityTypeManager->getStorage($definition->getBundleEntityType());
    foreach ($bundle_ids as $bundle_id) {
      $type = $storage->load($bundle_id);
      $plugins = $type->getEnabledBehaviorPlugins();
      $has_layout = isset($plugins['layout_paragraphs']);

      $path = '';
      // Get the icon and pass to Javascript.
      if (method_exists($type, 'getIconFile')) {
        if ($icon = $type->getIconFile()) {
          $path = $icon->url();
        }
      }
      $options[$bundle_id] = $bundle_info[$bundle_id]['label'];
      $types[($has_layout ? 'layout' : 'content')][] = [
        'id' => $bundle_id,
        'name' => $bundle_info[$bundle_id]['label'],
        'image' => $path,
      ];
    }
    $elements['add_more']['actions']['type'] = [
      '#title' => $this->t('Choose type'),
      '#type' => 'select',
      '#options' => $options,
      '#attributes' => ['class' => ['layout-paragraphs-item-type']],
    ];
    $elements['add_more']['actions']['item'] = [
      '#type' => 'submit',
      '#host' => $items->getEntity(),
      '#value' => $this->t('Create New'),
      '#submit' => [[$this, 'newItemSubmit']],
      '#limit_validation_errors' => [array_merge($parents, [$this->fieldName])],
      '#attributes' => ['class' => ['layout-paragraphs-add-item']],
      '#ajax' => [
        'callback' => [$this, 'elementAjax'],
        'wrapper' => $this->wrapperId,
      ],
      '#name' => implode('_', $parents) . '_add_item',
      '#element_parents' => $parents,
    ];
    // Add region and parent_delta hidden items only in this is a new entity.
    // Prefix with underscore to prevent namespace collisions.
    $elements['add_more']['actions']['_region'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['layout-paragraphs-new-item-region']],
    ];
    $elements['add_more']['actions']['_new_item_weight'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['layout-paragraphs-new-item-weight']],
    ];
    $elements['add_more']['actions']['_parent_uuid'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['layout-paragraphs-new-item-parent-uuid']],
    ];
    // Template for javascript behaviors.
    $elements['add_more']['menu'] = [
      '#type' => 'inline_template',
      '#template' => '
        <div class="layout-paragraphs-add-more-menu hidden">
          <h4 class="visually-hidden">Add Item</h4>
          <div class="layout-paragraphs-add-more-menu__search hidden">
            <input type="text" placeholder="{{ search_text }}" />
          </div>
          <div class="layout-paragraphs-add-more-menu__group">
            {% if types.layout %}
            <div class="layout-paragraphs-add-more-menu__group--layout">
            {% endif %}
            {% for type in types.layout %}
              <div class="layout-paragraphs-add-more-menu__item paragraph-type-{{type.id}} layout-paragraph">
                <a data-type="{{ type.id }}" href="#{{ type.id }}">
                {% if type.image %}
                <img src="{{ type.image }}" alt ="" />
                {% endif %}
                <div>{{ type.name }}</div>
                </a>
              </div>
            {% endfor %}
            {% if types.layout %}
            </div>
            {% endif %}
            {% if types.content %}
            <div class="layout-paragraphs-add-more-menu__group--content">
            {% endif %}
            {% for type in types.content %}
              <div class="layout-paragraphs-add-more-menu__item paragraph-type-{{type.id}}">
                <a data-type="{{ type.id }}" href="#{{ type.id }}">
                {% if type.image %}
                <img src="{{ type.image }}" alt ="" />
                {% endif %}
                <div>{{ type.name }}</div>
                </a>
              </div>
            {% endfor %}
            {% if types.content %}
            </div>
            {% endif %}
          </div>
        </div>',
      '#context' => [
        'types' => $types,
        'search_text' => $this->t('Search'),
      ],
    ];
    $elements['toggle_button'] = $this->toggleButton();
    if ($widget_state['open_form'] !== FALSE) {
      $elements['#process'][] = [$this, 'entityForm'];
    }

    // Add remove confirmation form if we're removing.
    if ($widget_state['remove_item'] !== FALSE) {
      $elements['#process'][] = [$this, 'removeForm'];
    }

    // Container for disabled / orphaned items.
    if ($paragraph_items_count) {
      $elements['disabled'] = [
        '#type' => 'fieldset',
        '#attributes' => ['class' => ['layout-paragraphs-disabled-items']],
        '#weight' => 999,
        '#title' => $this->t('Disabled Items'),
        '#wrapper_attributes' => ['class' => ['test']],
        'items' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'layout-paragraphs-disabled-items__items',
            ],
          ],
          'description' => [
            '#markup' => '<div class="layout-paragraphs-disabled-items__description">' . $this->t('Drop items here that you want to keep disabled / hidden, without removing them permanently.') . '</div>',
          ],
        ],
      ];
    }

    $elements['#attached']['drupalSettings']['paragraphsLayoutWidget']['maxDepth'] = $this->getSetting('nesting_depth');
    $elements['#attached']['drupalSettings']['paragraphsLayoutWidget']['requireLayouts'] = $this->getSetting('require_layouts');
    $elements['#attached']['library'][] = 'layout_paragraphs/layout_paragraphs_widget';
    return $elements;
  }

  /**
   * Builds the widget form array for an individual item.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $parents = $form['#parents'];
    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);
    if (isset($widget_state['items'][intval($delta)])) {
      $widget_state_item = $widget_state['items'][intval($delta)];
    }
    else {
      return [];
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $entity */
    $entity = $widget_state_item['entity'];
    $layout_settings = $entity->getAllBehaviorSettings()['layout_paragraphs'] ?? [];
    $layout = $layout_settings['layout'] ?? '';
    $config = $layout_settings['config'] ?? [];

    // These fields are manipulated via JS and interacting with the DOM.
    // We have to check the submitted form for their values.
    $region = $this->extractInput($form, $form_state, $delta, 'region', $layout_settings['region']);
    $parent_uuid = $this->extractInput($form, $form_state, $delta, 'parent_uuid', $layout_settings['parent_uuid']);
    $weight = $this->extractInput($form, $form_state, $delta, '_weight', $widget_state_item['weight']);

    $layout_instance = $layout
      ? $this->layoutPluginManager->createInstance($layout, $config)
      : FALSE;

    // Build the preview and render it in the form.
    $preview = [];
    if (isset($entity)) {
      $preview_view_mode = $this->getSetting('preview_view_mode');
      $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
      $preview = $view_builder->view($entity, $preview_view_mode);
      $preview['#cache']['max-age'] = 0;
    }

    $element = [
      '#widget_item' => TRUE,
      '#type' => 'container',
      '#delta' => $delta,
      '#entity' => $entity,
      '#layout' => $layout,
      '#region' => $region,
      '#parent_uuid' => $parent_uuid,
      '#weight' => $weight,
      '#attributes' => [
        'class' => [
          'layout-paragraphs-item',
        ],
        'id' => [
          $this->fieldName . '--item-' . $delta,
        ],
      ],
      'preview' => $preview,
      'region' => [
        '#type' => 'hidden',
        '#attributes' => ['class' => ['layout-paragraphs-region']],
        '#default_value' => $region,
      ],
      // Used by DOM to set parent uuids for nested items.
      'uuid' => [
        '#type' => 'hidden',
        '#attributes' => ['class' => ['layout-paragraphs-uuid']],
        '#value' => $entity->uuid(),
        // Must be at top for JS to work correctly.
        '#weight' => -999,
      ],
      'parent_uuid' => [
        '#type' => 'hidden',
        '#attributes' => ['class' => ['layout-paragraphs-parent-uuid']],
        '#value' => $parent_uuid,
      ],
      'entity' => [
        '#type' => 'value',
        '#value' => $entity,
      ],
      'toggle_button' => $this->toggleButton(),
      // Edit and remove button.
      'actions' => [
        '#type' => 'container',
        '#weight' => -1000,
        '#attributes' => ['class' => ['layout-paragraphs-actions']],
        'edit' => [
          '#type' => 'submit',
          '#name' => 'edit_' . $this->fieldName . '_' . $delta,
          '#value' => $this->t('Edit'),
          '#attributes' => ['class' => ['layout-paragraphs-edit']],
          '#limit_validation_errors' => [array_merge($parents, [$this->fieldName])],
          '#submit' => [[$this, 'editItemSubmit']],
          '#delta' => $delta,
          '#ajax' => [
            'callback' => [$this, 'elementAjax'],
            'wrapper' => $this->wrapperId,
            'progress' => 'none',
          ],
          '#element_parents' => $parents,
        ],
        'remove' => [
          '#type' => 'submit',
          '#name' => 'remove_' . $this->fieldName . '_' . $delta,
          '#value' => $this->t('Remove'),
          '#attributes' => ['class' => ['layout-paragraphs-remove']],
          '#limit_validation_errors' => [array_merge($parents, [$this->fieldName])],
          '#submit' => [[$this, 'removeItemSubmit']],
          '#delta' => $delta,
          '#ajax' => [
            'callback' => [$this, 'elementAjax'],
            'wrapper' => $this->wrapperId,
            'progress' => 'none',
          ],
          '#element_parents' => $parents,
        ],
      ],
    ];

    // Nested elements for regions.
    if ($layout_instance) {
      $element['#layout_instance'] = $layout_instance;
      $element['#attributes']['class'][] = 'layout-paragraphs-layout';
      foreach ($layout_instance->getPluginDefinition()->getRegionNames() as $region_name) {
        $element['preview']['regions'][$region_name] = [
          '#attributes' => [
            'class' => [
              'layout-paragraphs-layout-region',
              'layout-paragraphs-layout-region--' . $region_name,
            ],
          ],
          'toggle_button' => $this->toggleButton(),
        ];
      }
    }

    // New items are rendered in layout but hidden.
    // This way we can track their weights, region names, etc.
    if (!empty($widget_state['items'][$delta]['is_new'])) {
      $element['#attributes']['class'][] = 'js-hide';
    }

    return $element;
  }

  /**
   * Restructures $elements array into layout.
   *
   * @param array $elements
   *   The widget elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The restructured with layouts.
   */
  public function buildLayouts(array $elements, FormStateInterface $form_state) {
    $tree = [];
    $paragraph_elements = [];
    foreach (Element::children($elements) as $index) {
      $element = $elements[$index];
      if (!empty($element['#widget_item'])) {
        $paragraph_elements[] = $element;
        unset($elements[$index]);
      }
    }
    // Sort items by weight to make sure we're processing the correct order.
    uasort($paragraph_elements, function ($a, $b) {
      if ($a['#weight'] == $b['#weight']) {
        return 0;
      }
      return $a['#weight'] < $b['#weight'] ? -1 : 1;
    });
    while ($element = array_shift($paragraph_elements)) {
      /* @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $element['#entity'];
      if ($element['#layout']) {
        $tree[$entity->uuid()] = $this->buildLayout($element, $paragraph_elements);
      }
      else {
        $tree[$entity->uuid()] = $element;
      }
      // Move disabled items to disabled region.
      if ($element['#region'] == '_disabled') {
        $elements['disabled']['items'][$entity->uuid()] = $tree[$entity->uuid()];
        unset($tree[$entity->uuid()]);
      }
    }
    $elements['active_items'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['active-items']],
      'items' => $tree,
    ];
    return $elements;
  }

  /**
   * Builds a single layout element.
   */
  public function buildLayout($layout_element, &$elements) {
    /* @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $layout_element['#entity'];
    $uuid = $entity->uuid();
    $layout_element['preview']['regions'] = ['#weight' => 100] + $layout_element['#layout_instance']->build($layout_element['preview']['regions']);
    foreach ($elements as $index => $element) {
      if ($element['#parent_uuid'] == $uuid) {
        /* @var \Drupal\Core\Entity\EntityInterface $child_entity */
        $child_entity = $element['#entity'];
        $child_uuid = $child_entity->uuid();
        $region = $element['#region'];
        // Recursive processing for layouts within layouts.
        if ($element['#layout']) {
          $sub_element = $this->buildLayout($element, $elements);
        }
        else {
          $sub_element = $element;
        }
        unset($elements[$index]);
        $layout_element['preview']['regions'][$region][$child_uuid] = $sub_element;
      }
    }
    return $layout_element;

  }

  /**
   * Returns a flat array of layout labels keyed by layout ids.
   *
   * @param array $layout_groups
   *   Nested array of layout groups.
   *
   * @return array
   *   Flat array of layout labels.
   */
  private function layoutLabels(array $layout_groups) {
    $layouts = $this->layoutPluginManager->getSortedDefinitions();
    $layout_info = [];
    foreach ($layout_groups as $group) {
      foreach ($group as $layout_id => $layout_name) {
        $layout_info[$layout_id] = $layouts[$layout_id]->getLabel();
      }
    }
    return $layout_info;
  }

  /**
   * Returns markup for the toggle menu button.
   *
   * @return array
   *   Returns the render array for an "add more" toggle button.
   */
  protected function toggleButton() {
    return [
      '#markup' => '<button class="layout-paragraphs-add-content__toggle">+<span class="visually-hidden">Add Item</span></button>',
      '#allowed_tags' => ['button', 'span'],
      '#weight' => 10000,
    ];
  }

  /**
   * Builds an entity form for a paragraph item.
   *
   * Add form components in this rendering order:
   * 1. Layout selection, if this is a layout paragraph.
   * 2. The entity's fields from the form display.
   * 3. The layout plugin config form, if exists.
   * 4. The paragraph behaviors plugins form, if any exist.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state object.
   * @param array $form
   *   The form array.
   *
   * @return array
   *   The entity form element.
   */
  public function entityForm(array &$element, FormStateInterface $form_state, array &$form) {

    $parents = $form['#parents'];
    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);
    $delta = $widget_state['open_form'];

    /** @var \Drupal\paragraphs\Entity\Paragraph $entity */
    $entity = $widget_state['items'][$delta]['entity'];
    $display = EntityFormDisplay::collectRenderDisplay($entity, 'default');
    $bundle_label = $entity->type->entity->label();
    $element['entity_form'] = [
      '#entity' => $entity,
      '#prefix' => '<div class="layout-paragraphs-form entity-type-' . $entity->bundle() . '">',
      '#suffix' => '</div>',
      '#type' => 'container',
      '#parents' => array_merge($parents, [
        $this->fieldName,
        $delta,
        'entity_form',
      ]),
      '#weight' => 1000,
      '#delta' => $delta,
      '#display' => $display,
      '#attributes' => [
        'data-dialog-title' => [
          $entity->id() ? $this->t('Edit @type', ['@type' => $bundle_label]) : $this->t('Create new @type', ['@type' => $bundle_label]),
        ],
      ],
    ];

    // Support for Field Group module based on Paragraphs module.
    // @todo Remove as part of https://www.drupal.org/node/2640056
    if (\Drupal::moduleHandler()->moduleExists('field_group')) {
      $context = [
        'entity_type' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
        'entity' => $entity,
        'context' => 'form',
        'display_context' => 'form',
        'mode' => $display->getMode(),
      ];
      field_group_attach_groups($element['entity_form'], $context);
      if (function_exists('field_group_form_pre_render')) {
        $element['entity_form']['#pre_render'][] = 'field_group_form_pre_render';
      }
      if (function_exists('field_group_form_process')) {
        $element['entity_form']['#process'][] = 'field_group_form_process';
      }
    }

    $display->buildForm($entity, $element['entity_form'], $form_state);

    // Add layout selection form if "paragraphs layout" behavior is enabled.
    $paragraphs_type = $entity->getParagraphType();
    $plugins = $paragraphs_type->getEnabledBehaviorPlugins();
    if (isset($plugins['layout_paragraphs'])) {
      $layout_paragraphs_plugin = $paragraphs_type->getBehaviorPlugin('layout_paragraphs');
      $config = $layout_paragraphs_plugin->getConfiguration();

      $layout = $entity->getBehaviorSetting('layout_paragraphs', 'layout');
      $layout_plugin_config = $entity->getBehaviorSetting('layout_paragraphs', 'config') ?? [];

      $element['entity_form']['layout_selection'] = [
        '#type' => 'container',
        '#weight' => -1000,
        'layout' => [
          '#type' => 'radios',
          '#title' => $this->t('Select a layout:'),
          '#options' => $config['available_layouts'],
          '#default_value' => $layout,
          '#attributes' => [
            'class' => ['layout-paragraphs-layout-select'],
          ],
          '#required' => TRUE,
          '#after_build' => [[$this, 'buildLayoutRadios']],
        ],
        'update' => [
          '#type' => 'button',
          '#value' => $this->t('Update'),
          '#name' => 'update_layout',
          '#delta' => $delta,
          '#limit_validation_errors' => [
            array_merge($parents, [
              $this->fieldName,
              $delta,
              'entity_form',
              'layout_selection',
            ]),
          ],
          '#attributes' => [
            'class' => ['js-hide'],
          ],
          '#element_parents' => $parents,
        ],
      ];

      // Switching layouts should change the layout plugin options form
      // with Ajax for users with adequate permissions.
      if ($this->currentUser->hasPermission('edit entity reference layout plugin config')) {
        $element['entity_form']['layout_selection']['layout']['#ajax'] = [
          'event' => 'change',
          'callback' => [$this, 'buildLayoutConfigurationFormAjax'],
          'trigger_as' => ['name' => 'update_layout'],
          'wrapper' => 'layout-config',
          'progress' => 'none',
        ];
        $element['entity_form']['layout_selection']['update']['#ajax'] = [
          'callback' => [$this, 'buildLayoutConfigurationFormAjax'],
          'wrapper' => 'layout-config',
          'progress' => 'none',
        ];
      }
      $element['entity_form']['layout_plugin_form'] = [
        '#prefix' => '<div id="layout-config">',
        '#suffix' => '</div>',
        '#access' => $this->currentUser->hasPermission('edit entity reference layout plugin config'),
      ];
      // Add the layout configuration form if applicable.
      if (!empty($layout)) {
        $layout_instance = $this->layoutPluginManager->createInstance($layout, $layout_plugin_config);
        if ($layout_plugin = $this->getLayoutPluginForm($layout_instance)) {
          $element['entity_form']['layout_plugin_form'] += [
            '#type' => 'details',
            '#title' => $this->t('Layout Configuration'),
            '#weight' => 999,
          ];
          $element['entity_form']['layout_plugin_form'] += $layout_plugin->buildConfigurationForm([], $form_state);
        }
      }
    }

    // Add behavior forms if applicable.
    $paragraphs_type = $entity->getParagraphType();
    // @todo: Check translation functionality.
    if ($paragraphs_type &&
      $this->currentUser->hasPermission('edit behavior plugin settings') &&
      (!$this->isTranslating || !$entity->isDefaultTranslationAffectedOnly()) &&
      $behavior_plugins = $paragraphs_type->getEnabledBehaviorPlugins()) {
      $has_behavior_plugin_form = FALSE;
      $element['entity_form']['behavior_plugins'] = [
        '#type' => 'details',
        '#title' => $this->t('Behaviors'),
        '#element_validate' => [[$this, 'validateBehaviors']],
        '#entity' => $entity,
        '#weight' => 1000,
      ];
      foreach ($behavior_plugins as $plugin_id => $plugin) {
        $element['entity_form']['behavior_plugins'][$plugin_id] = ['#type' => 'container'];
        $subform_state = SubformState::createForSubform($element['entity_form']['behavior_plugins'][$plugin_id], $form, $form_state);
        $plugin_form = $plugin->buildBehaviorForm($entity, $element['entity_form']['behavior_plugins'][$plugin_id], $subform_state);
        if (!empty(Element::children($plugin_form))) {
          $element['entity_form']['behavior_plugins'][$plugin_id] = $plugin_form;
          $has_behavior_plugin_form = TRUE;
        }
      }
      if (!$has_behavior_plugin_form) {
        unset($element['entity_form']['behavior_plugins']);
      }
    }

    // Add save, cancel, etc.
    $element['entity_form'] += [
      'actions' => [
        '#weight' => 1000,
        '#type' => 'container',
        '#attributes' => ['class' => ['layout-paragraphs-item-form-actions']],
        'save_item' => [
          '#type' => 'submit',
          '#name' => 'save',
          '#value' => $this->t('Save'),
          '#delta' => $delta,
          '#limit_validation_errors' => [array_merge($parents, [$this->fieldName])],
          '#submit' => [
            [$this, 'saveItemSubmit'],
          ],
          '#ajax' => [
            'callback' => [$this, 'elementAjax'],
            'wrapper' => $this->wrapperId,
            'progress' => 'none',
          ],
          '#element_parents' => $parents,
        ],
        'cancel' => [
          '#type' => 'submit',
          '#name' => 'cancel',
          '#value' => $this->t('Cancel'),
          '#limit_validation_errors' => [],
          '#delta' => $delta,
          '#submit' => [
            [$this, 'cancelItemSubmit'],
          ],
          '#attributes' => [
            'class' => ['layout-paragraphs-cancel', 'button--danger'],
          ],
          '#ajax' => [
            'callback' => [$this, 'elementAjax'],
            'wrapper' => $this->wrapperId,
            'progress' => 'none',
          ],
          '#element_parents' => $parents,
        ],
      ],
    ];

    return $element;
  }

  /**
   * Builds the remove item confirmation form.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state object.
   * @param array $form
   *   The form array.
   *
   * @return array
   *   The entity form element.
   */
  public function removeForm(array &$element, FormStateInterface $form_state, array &$form) {

    $parents = $form['#parents'];
    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);
    $delta = $widget_state['remove_item'];
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph_entity */
    $entity = $widget_state['items'][$delta]['entity'];
    $element['remove_form'] = [
      '#prefix' => '<div class="layout-paragraphs-form">',
      '#suffix' => '</div>',
      '#type' => 'container',
      '#attributes' => ['data-dialog-title' => [$this->t('Confirm removal')]],
      'message' => [
        '#type' => 'markup',
        '#markup' => $this->t('Are you sure you want to permanently remove this <b>@type?</b><br />This action cannot be undone.', ['@type' => $entity->type->entity->label()]),
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['layout-paragraphs-item-form-actions']],
        'confirm' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#delta' => $delta,
          '#submit' => [[$this, 'removeItemConfirmSubmit']],
          '#ajax' => [
            'callback' => [$this, 'elementAjax'],
            'wrapper' => $this->wrapperId,
            'progress' => 'none',
          ],
          '#element_parents' => $parents,
        ],
        'cancel' => [
          '#type' => 'submit',
          '#value' => $this->t('Cancel'),
          '#delta' => $delta,
          '#submit' => [[$this, 'removeItemCancelSubmit']],
          '#attributes' => [
            'class' => ['layout-paragraphs-cancel', 'button--danger'],
          ],
          '#ajax' => [
            'callback' => [$this, 'elementAjax'],
            'wrapper' => $this->wrapperId,
          ],
          '#element_parents' => $parents,
        ],
      ],
      '#delta' => $delta,
    ];
    return $element;
  }

  /**
   * Add theme wrappers to layout selection radios.
   *
   * Theme function injects layout icons into radio buttons.
   */
  public function buildLayoutRadios($element) {
    foreach (Element::children($element) as $key) {
      $layout_name = $key;
      $definition = $this->layoutPluginManager->getDefinition($layout_name);
      $icon = $definition->getIcon(40, 60, 1, 0);
      $rendered_icon = $this->renderer->render($icon);
      $radio_element = $element[$key];
      $radio_with_icon_element = [
        '#type' => 'container',
        '#attributes' => ['class' => ['layout-select--list-item']],
        'icon' => [
          '#type' => 'inline_template',
          '#template' => '<div class="layout-icon-wrapper">{{ img }}</div>',
          '#context' => ['img' => $rendered_icon],
        ],
        'radio' => $radio_element,
      ];
      $element[$key] = $radio_with_icon_element;
    }
    $element['#wrapper_attributes'] = ['class' => ['layout-select--list']];
    return $element;
  }

  /**
   * Extracts field value from form_state if it exists.
   */
  protected function extractInput(array $form, FormStateInterface $form_state, int $delta, $element_name, $default_value = '') {

    $input = $form_state->getUserInput();
    $parents = $form['#parents'];
    $key_exists = NULL;

    if (is_array($element_name)) {
      $element_path = array_merge($parents, [
        $this->fieldName,
        $delta,
      ],
      $element_name);
    }
    else {
      $element_path = array_merge($parents, [
        $this->fieldName,
        $delta,
        $element_name,
      ]);
    }

    $val = NestedArray::getValue($input, $element_path, $key_exists);
    if ($key_exists) {
      return Html::escape($val);
    }
    return $default_value;
  }

  /**
   * Validate paragraph behavior form plugins.
   *
   * @param array $element
   *   The element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The complete form array.
   */
  public function validateBehaviors(array $element, FormStateInterface $form_state, array $form) {

    /** @var \Drupal\paragraphs\ParagraphInterface $entity */
    $entity = $element['#entity'];

    // Validate all enabled behavior plugins.
    $paragraphs_type = $entity->getParagraphType();
    if ($this->currentUser->hasPermission('edit behavior plugin settings')) {
      foreach ($paragraphs_type->getEnabledBehaviorPlugins() as $plugin_id => $plugin_values) {
        if (!empty($element[$plugin_id])) {
          $subform_state = SubformState::createForSubform($element[$plugin_id], $form_state->getCompleteForm(), $form_state);
          $plugin_values->validateBehaviorForm($entity, $element[$plugin_id], $subform_state);
        }
      }
    }
  }

  /**
   * Form submit handler - adds a new item and opens its edit form.
   */
  public function newItemSubmit(array $form, FormStateInterface $form_state) {

    $element = $form_state->getTriggeringElement();
    $parents = $element['#element_parents'];
    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);

    if (!empty($element['#bundle_id'])) {
      $bundle_id = $element['#bundle_id'];
    }
    else {
      $element_parents = $element['#parents'];
      array_splice($element_parents, -1, 1, 'type');
      $bundle_id = $form_state->getValue($element_parents);
    }

    $entity_type = $this->entityTypeManager->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph_entity = $this->entityTypeManager->getStorage('paragraph')->create([
      $bundle_key => $bundle_id,
    ]);
    $paragraph_entity->setParentEntity($element['#host'], $this->fieldDefinition->getName());

    $path = array_merge($parents, [
      $this->fieldDefinition->getName(),
      'add_more',
      'actions',
    ]);
    $behavior_values = [
      'region' => $form_state->getValue(array_merge($path, ['_region'])),
      'parent_uuid' => $form_state->getValue(array_merge($path, ['_parent_uuid'])),
    ];
    $paragraph_entity->setBehaviorSettings('layout_paragraphs', $behavior_values);
    $widget_state['items'][] = [
      'entity' => $paragraph_entity,
      'weight' => $form_state->getValue(array_merge($path, ['_new_item_weight'])),
      'is_new' => TRUE,
    ];
    $widget_state['open_form'] = $widget_state['items_count'];
    $widget_state['items_count']++;

    static::setWidgetState($parents, $this->fieldName, $form_state, $widget_state);
    $form_state->setRebuild();
  }

  /**
   * Form submit handler - opens the edit form for an existing item.
   */
  public function editItemSubmit($form, $form_state) {

    $element = $form_state->getTriggeringElement();
    $parents = $element['#element_parents'];
    $delta = $element['#delta'];

    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);
    $widget_state['open_form'] = $delta;

    static::setWidgetState($parents, $this->fieldName, $form_state, $widget_state);
    $form_state->setRebuild();
  }

  /**
   * Form submit handler - opens confirm removal form for an item.
   */
  public function removeItemSubmit($form, $form_state) {

    $element = $form_state->getTriggeringElement();
    $parents = $element['#element_parents'];
    $delta = $element['#delta'];

    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);
    $widget_state['remove_item'] = $delta;

    static::setWidgetState($parents, $this->fieldName, $form_state, $widget_state);
    $form_state->setRebuild();
  }

  /**
   * Form submit handler - removes/deletes an item.
   */
  public function removeItemConfirmSubmit($form, $form_state) {

    $element = $form_state->getTriggeringElement();
    $parents = $element['#element_parents'];
    $delta = $element['#delta'];

    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);

    unset($widget_state['items'][$delta]);
    $widget_state['remove_item'] = FALSE;

    static::setWidgetState($parents, $this->fieldName, $form_state, $widget_state);
    $form_state->setRebuild();
  }

  /**
   * Form submit handler - cancels item removal and closes confirmation form.
   */
  public function removeItemCancelSubmit($form, $form_state) {

    $element = $form_state->getTriggeringElement();
    $parents = $element['#element_parents'];

    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);

    unset($widget_state['remove_item']);

    static::setWidgetState($parents, $this->fieldName, $form_state, $widget_state);
    $form_state->setRebuild();
  }

  /**
   * Form submit handler - saves an item.
   */
  public function saveItemSubmit($form, $form_state) {

    $element = $form_state->getTriggeringElement();
    $parents = $element['#element_parents'];
    $delta = $element['#delta'];
    $element_array_parents = $element['#array_parents'];
    $item_array_parents = array_splice($element_array_parents, 0, -2);

    $item_form = NestedArray::getValue($form, $item_array_parents);
    $display = $item_form['#display'];
    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);

    // Remove is_new flag since we're saving the entity.
    unset($widget_state['items'][$delta]['is_new']);

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph_entity = $widget_state['items'][$delta]['entity'];

    // Save field values to entity.
    $display->extractFormValues($paragraph_entity, $item_form, $form_state);

    // Submit behavior forms.
    $paragraphs_type = $paragraph_entity->getParagraphType();
    if ($this->currentUser->hasPermission('edit behavior plugin settings')) {
      foreach ($paragraphs_type->getEnabledBehaviorPlugins() as $plugin_id => $plugin_values) {
        if (!empty($item_form['behavior_plugins'][$plugin_id])) {
          $subform_state = SubformState::createForSubform($item_form['behavior_plugins'][$plugin_id], $form_state->getCompleteForm(), $form_state);
          $plugin_values->submitBehaviorForm($paragraph_entity, $item_form['behavior_plugins'][$plugin_id], $subform_state);
        }
      }
    }

    // Save layout settings.
    if (!empty($item_form['layout_selection']['layout'])) {

      $layout = $form_state->getValue($item_form['layout_selection']['layout']['#parents']);
      $new_behavior_settings = ['layout' => $layout];

      // Save layout config:
      if (!empty($item_form['layout_plugin_form'])) {
        $layout_instance = $this->layoutPluginManager->createInstance($layout);
        if ($this->getLayoutPluginForm($layout_instance)) {
          $subform_state = SubformState::createForSubform($item_form['layout_plugin_form'], $form_state->getCompleteForm(), $form_state);
          $layout_instance->submitConfigurationForm($item_form['layout_plugin_form'], $subform_state);
          $layout_config = $layout_instance->getConfiguration();
          $new_behavior_settings['config'] = $layout_config;
        }
      }
      // Merge new behavior settings for layout/config into existing settings.
      $behavior_settings = $paragraph_entity->getAllBehaviorSettings()['layout_paragraphs'] ?? [];
      $paragraph_entity->setBehaviorSettings('layout_paragraphs', $new_behavior_settings + $behavior_settings);
    }

    // Close the entity form.
    $widget_state['open_form'] = FALSE;

    static::setWidgetState($parents, $this->fieldName, $form_state, $widget_state);
    $form_state->setRebuild();
  }

  /**
   * Form submit handler - cancels editing an item and closes form.
   */
  public function cancelItemSubmit($form, $form_state) {

    $element = $form_state->getTriggeringElement();
    $parents = $element['#element_parents'];
    $delta = $element['#delta'];
    $widget_state = static::getWidgetState($parents, $this->fieldName, $form_state);

    // If canceling an item that hasn't been created yet, remove it.
    if (!empty($widget_state['items'][$delta]['is_new'])) {
      array_splice($widget_state['items'], $delta, 1);
      $widget_state['items_count'] = count($widget_state['items']);
    }
    $widget_state['open_form'] = FALSE;
    static::setWidgetState($parents, $this->fieldName, $form_state, $widget_state);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback to return the entire ERL element.
   */
  public function elementAjax(array $form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    $parents = $element['#element_parents'];
    $field_state = static::getWidgetState($parents, $this->fieldName, $form_state);
    $widget_field = NestedArray::getValue($form, $field_state['array_parents']);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $this->wrapperId, $widget_field));
    $response->addCommand(new LayoutParagraphsStateResetCommand('#' . $this->wrapperId));
    return $response;
  }

  /**
   * Ajax callback to return a layout plugin configuration form.
   */
  public function buildLayoutConfigurationFormAjax(array $form, FormStateInterface $form_state) {

    $element = $form_state->getTriggeringElement();
    $parents = $element['#array_parents'];
    $parents = array_splice($parents, 0, -2);
    $parents = array_merge($parents, ['layout_plugin_form']);
    if ($layout_plugin_form = NestedArray::getValue($form, $parents)) {
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#layout-config', $layout_plugin_form));
      $response->addCommand(new LayoutParagraphsStateResetCommand('#' . $this->wrapperId));
      return $response;
    }
    else {
      return [];
    }
  }

  /**
   * Field instance settings form.
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $entity_type_id = $this->getFieldSetting('target_type');
    $form['preview_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Preview view mode'),
      '#default_value' => $this->getSetting('preview_view_mode'),
      '#options' => $this->entityDisplayRepository->getViewModeOptions($entity_type_id),
      '#required' => TRUE,
      '#description' => $this->t('View mode for the referenced entity preview on the edit form. Automatically falls back to "default", if it is not enabled in the referenced entity type displays.'),
    ];
    $form['nesting_depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Maximum nesting depth'),
      '#options' => range(0, 10),
      '#default_value' => $this->getSetting('nesting_depth'),
      '#description' => $this->t('Choosing 0 will prevent nesting layouts within other layouts.'),
    ];
    $form['require_layouts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require paragraphs to be added inside a layout'),
      '#default_value' => $this->getSetting('nesting_depth'),
      '#default_value' => $this->getSetting('require_layouts'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $entity_type = $this->getFieldSetting('target_type');
    $handler_settings = $this->getFieldSetting('handler_settings');
    $bundles = array_keys($handler_settings["target_bundles_drag_drop"]);
    $selected_bundles = !empty($handler_settings['target_bundles']) ? $handler_settings['target_bundles'] : [];
    if (!$handler_settings["negate"]) {
      $selected_bundles = empty($selected_bundles) ? [] : $selected_bundles;
      $target_bundles = array_intersect($bundles, $selected_bundles);
    }
    else {
      $target_bundles = array_diff($bundles, $selected_bundles);
    }
    $definition = $this->entityTypeManager->getDefinition($entity_type);
    $storage = $this->entityTypeManager->getStorage($definition->getBundleEntityType());
    $has_layout = FALSE;
    try {
      if (!empty($target_bundles)) {
        foreach ($target_bundles as $target_bundle) {
          /** @var \Drupal\paragraphs\ParagraphsTypeInterface $type */
          $type = $storage->load($target_bundle);
          $plugins = $type->getEnabledBehaviorPlugins();
          if (isset($plugins['layout_paragraphs'])) {
            $has_layout = TRUE;
            break;
          }
        }
      }
    }
    catch (\Exception $e) {
      watchdog_exception('paragraphs layout widget, behaviour plugin', $e);
    }
    if (!$has_layout) {
      $field_name = $this->fieldDefinition->getLabel();
      $message = $this->t('To use layouts with the "@field_name" field, make sure you have enabled the "Paragraphs Layout" behavior for at least one target paragraph type.', ['@field_name' => $field_name]);
      $this->messenger()->addMessage($message, $this->messenger()::TYPE_WARNING);
    }
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Preview view mode: @preview_view_mode', ['@preview_view_mode' => $this->getSetting('preview_view_mode')]);
    $summary[] = $this->t('Maximum nesting depth: @max_depth', ['@max_depth' => $this->getSetting('nesting_depth')]);
    if ($this->getSetting('require_layouts')) {
      $summary[] = $this->t('Paragraphs <b>must be</b> added within layouts.');
    }
    else {
      $summary[] = $this->t('Layouts are optional.');
    }
    $summary[] = $this->t('Maximum nesting depth: @max_depth', ['@max_depth' => $this->getSetting('nesting_depth')]);
    return $summary;
  }

  /**
   * Default settings for widget.
   */
  public static function defaultSettings() {
    $defaults = parent::defaultSettings();
    $defaults += [
      'preview_view_mode' => 'default',
      'nesting_depth' => 0,
      'require_layouts' => 0,
    ];

    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => &$item) {
      unset($values[$delta]['actions']);
      if ($item['entity'] instanceof ParagraphInterface) {
        /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph_entity */
        $paragraph_entity = $item['entity'];

        // Merge region and parent uuid into paragraph behavior settings.
        $behavior_settings = $paragraph_entity->getAllBehaviorSettings()['layout_paragraphs'] ?? [];
        $new_behavior_settings = [
          'region' => $item['region'],
          'parent_uuid' => $item['parent_uuid'],
        ];
        $paragraph_entity->setBehaviorSettings('layout_paragraphs', $new_behavior_settings + $behavior_settings);

        $paragraph_entity->setNeedsSave(TRUE);
        $item['target_id'] = $paragraph_entity->id();
        $item['target_revision_id'] = $paragraph_entity->getRevisionId();
      }
    }
    return $values;
  }

  /**
   * Retrieves the plugin form for a given layout.
   *
   * @param \Drupal\Core\Layout\LayoutInterface $layout
   *   The layout plugin.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface
   *   The plugin form for the layout.
   */
  protected function getLayoutPluginForm(LayoutInterface $layout) {
    if ($layout instanceof PluginWithFormsInterface) {
      return $this->pluginFormFactory->createInstance($layout, 'configure');
    }

    if ($layout instanceof PluginFormInterface) {
      return $layout;
    }

    return FALSE;
  }

}
