(($, Drupal, drupalSettings, dragula) => {
  class LPBuilder {
    constructor(settings) {
      this._edited = false;
      this.settings = settings;
      this.options = settings.options || {};
      this.$element = $(settings.selector);
      this.componentMenu = settings.componentMenu;
      this.sectionMenu = settings.sectionMenu;
      this.controls = settings.controls;
      this.toggleButton = settings.toggleButton;
      this.emptyContainer = settings.emptyContainer;
      this.$actions = this.$element.find('.lpb-controls');
      this.trashBin = [];
      this._intervalId = 0;
      this._interval = 200;
      this._statusIntervalId = 0;
      this._statusInterval = 3000;

      if (this.$element.find('.lpb-component').length === 0) {
        this.isEmpty();
      }

      this.attachEventListeners();
      this.enableDragAndDrop();
      this.saved();
    }

    attachEventListeners() {
      this.$element.on(
        'focus.lp-builder',
        '.lpb-component',
        this.onFocusComponent.bind(this),
      );
      this.$element.on(
        'focus.lp-builder',
        '.lpb-region',
        this.onFocusRegion.bind(this),
      );

      this.$element.on('mousemove.lp-builder', this.onMouseMove.bind(this));

      this.$element.on(
        'click.lp-builder',
        '.lpb-edit',
        this.onClickEdit.bind(this),
      );
      this.$element.on(
        'click.lp-builder',
        '.lpb-delete',
        this.onClickDelete.bind(this),
      );
      this.$element.on(
        'click.lp-builder',
        '.lpb-toggle',
        this.onClickToggle.bind(this),
      );
      this.$element.on(
        'click.lp-builder',
        '.lpb-down',
        this.onClickDown.bind(this),
      );
      this.$element.on(
        'click.lp-builder',
        '.lpb-up',
        this.onClickUp.bind(this),
      );
      this.$element.on(
        'click.lp-builder',
        '.lpb-component-menu__action',
        this.onClickComponentAction.bind(this),
      );
      this.$element.on(
        'click.lp-builder',
        '.lpb-section-menu-button',
        this.onClickSectionAction.bind(this),
      );
      this.$element.on(
        'keyup.lp-builder',
        '.lpb-component-menu-search-input',
        this.onKeyPressSearch.bind(this),
      );
      this.options.saveButtonIds.forEach((id) => {
        $(document).on('click.lp-builder', `#${id}`, this.saved.bind(this));
      });
      this.onKeyPress = this.onKeyPress.bind(this);
      document.addEventListener('keydown', this.onKeyPress);
      this.onBeforeUnload = this.onBeforeUnload.bind(this);
      window.addEventListener('beforeunload', this.onBeforeUnload);
      $(window)
        .once('lpb-dialog')
        .on('dialog:aftercreate', () => {
          $('.lpb-dialog').css({
            zIndex: 1000,
            minWidth: '350px',
          });
        });
    }

    onMouseMove() {
      if (!this.$componentMenu) {
        this.startInterval();
      }
    }

    /**
     * On focus event handler for components.
     * @param {Event} e The event.
     */
    onFocusComponent(e) {
      this.$activeItem = $(e.currentTarget);
      e.stopPropagation();
    }

    /**
     * On focus event handler for regions.
     * @param {Event} e The event.
     */
    onFocusRegion(e) {
      if ($('.lpb-component', e.currentTarget)) {
        this.$activeItem = $(e.currentTarget);
        e.stopPropagation();
      }
    }

    /**
     * Interval handler.
     */
    onInterval() {
      const $hoveredItem = this.$element
        .find('.lpb-component:hover, .lpb-region:hover')
        .last();
      if ($hoveredItem.length > 0) {
        this.$activeItem = $hoveredItem;
      } else {
        this.$activeItem = false;
      }
      this.stopInterval();
    }

    startInterval() {
      if (!this._intervalId) {
        this._intervalId = setInterval(
          this.onInterval.bind(this),
          this._interval,
        );
      }
    }

    stopInterval() {
      clearInterval(this._intervalId);
      this._intervalId = 0;
    }

    /**
     * Edit component button click event handler.
     * @param {Event} e The triggering event.
     */
    onClickEdit(e) {
      e.currentTarget.classList.add('loading');
      this.editForm();
      e.preventDefault();
    }

    /**
     * Delete component button click event handler.
     * @param {Event} e The triggering event.
     */
    onClickDelete(e) {
      const deleteComponent = this.delete.bind(this);
      $.confirm({
        title: Drupal.t('Remove Component'),
        content: Drupal.t('Are you sure you want to remove this?'),
        useBootstrap: false,
        buttons: {
          confirm: () => {
            deleteComponent($(e.currentTarget).closest('.lpb-component'));
          },
          cancel: () => true,
        },
      });
      e.preventDefault();
    }

    /**
     * Toggle create menu button click event handler.
     * @param {Event} e The triggering event.
     */
    onClickToggle(e) {
      this.toggleComponentMenu($(e.currentTarget));
      e.preventDefault();
    }

    /**
     * Move component up button click event handler.
     * @param {Event} e The triggering event.
     */
    onClickUp(e) {
      this.move(-1);
      e.preventDefault();
    }

    /**
     * Move component down button click event handler.
     * @param {Event} e The triggering event.
     */
    onClickDown(e) {
      this.move(1);
      e.preventDefault();
    }

    onClickComponentAction(e) {
      const placement = this.$activeToggle.attr('data-placement');
      const region = this.$activeToggle.attr('data-region');
      const uuid = this.$activeToggle.attr('data-container-uuid');
      const type = $(e.currentTarget).attr('data-type');
      this.removeControls();
      switch (placement) {
        case 'insert':
          this.insertComponentIntoRegion(uuid, region, type);
          break;
        // If placement is "before" or "after".
        default:
          this.insertSiblingComponent(uuid, type, placement);
          break;
      }
      e.preventDefault();
    }

    onClickSectionAction(e) {
      const $button = $(e.currentTarget);
      const $sectionMenu = $button.closest('.js-lpb-section-menu');
      const placement = $sectionMenu.attr('data-placement');
      const uuid = $sectionMenu.attr('data-container-uuid');
      const type = $button.attr('data-type');
      if (uuid) {
        this.insertSiblingComponent(uuid, type, placement);
      } else {
        this.insertComponent(type);
      }
      e.preventDefault();
    }

    /**
     * Key press event handler.
     * @param {Event} e The triggering event.
     */
    onKeyPress(e) {
      if (e.code === 'Escape') {
        if (this.$componentMenu) {
          this.closeComponentMenu();
        }
      }
    }

    /**
     * Key press event handler.
     * @param {Event} e The triggering event.
     */
    onKeyPressSearch(e) {
      const text = e.currentTarget.value;
      const pattern = new RegExp(text, 'i');
      const $searchItems = this.$componentMenu.find(
        '.lpb-component-menu__item:not(.hidden)',
      );
      for (let i = 0; i < $searchItems.length; i++) {
        const item = $searchItems[i];
        if (pattern.test(item.innerText)) {
          item.removeAttribute('style');
        } else {
          item.style.display = 'none';
        }
      }
      this.positionComponentMenu(true);
    }

    /**
     * Before unload event handler.
     * @param {Event} e The triggering event.
     */
    onBeforeUnload(e) {
      if (this._edited) {
        e.preventDefault();
        e.returnValue = '';
      }
    }

    detachEventListeners() {
      this.$element.off('.lp-builder');
      this.$element.off('.lp-builder');
      clearInterval(this._intervalId);
      document.removeEventListener('keydown', this.onKeyPress);
      window.removeEventListener('onbeforeunload', this.onBeforeUnload);
    }

    getState() {
      return $('.lpb-component', this.$element)
        .get()
        .map((item) => {
          const $item = $(item);
          return {
            uuid: $item.attr('data-uuid'),
            parentUuid:
              $item.parents('.lpb-component').first().attr('data-uuid') || null,
            region:
              $item.parents('.lpb-region').first().attr('data-region') || null,
          };
        });
    }

    set $activeItem($item) {
      if (this.$componentMenu) {
        return;
      }
      // If item is already is already active, do nothing.
      if (
        $item.length &&
        this.$activeItem &&
        this.$activeItem[0] === $item[0]
      ) {
        return;
      }
      // If item is false and activeItem is also false, do nothing.
      if ($item === false && this._$activeItem === false) {
        return;
      }

      // Remove the current toggle or controls.
      if (this.$activeItem) {
        this.$activeItem.removeClass(['js-lpb-active-item', 'lpb-active-item']);
        this.removeControls();
      }
      // If $element exists and is not false, add the controls.
      if ($item) {
        if ($item.hasClass('lpb-component')) {
          this.insertControls($item);
        } else if (
          $item.hasClass('lpb-region') &&
          $item.find('.lpb-component').length === 0
        ) {
          this.insertToggle($item, 'insert', 'append');
        }
        $item.addClass(['js-lpb-active-item', 'lpb-active-item']);
      }
      this._$activeItem = $item;
    }

    get $activeItem() {
      return this._$activeItem;
    }

    removeToggle() {
      if (this.$componentMenu) {
        this.$componentMenu.remove();
      }
    }

    /**
     * Insertss a toggle button into a container.
     * @param {jQuery} $container The container.
     * @param {string} placement Placement - inside|after|before.
     * @param {string} method jQuery method - prepend|append
     */
    insertToggle($container, placement, method = 'prepend') {
      const $toggleButton = $(
        `<div class="js-lpb-toggle lpb-toggle__wrapper"></div>`,
      )
        .append(
          $(this.toggleButton).attr({
            'data-placement': placement,
            'data-region': $container.attr('data-region'),
            'data-container-uuid': $container
              .closest('[data-uuid]')
              .attr('data-uuid'),
          }),
        )
        .css({ position: 'absolute', zIndex: 1000 })
        .hide()
        [`${method}To`]($container)
        .fadeIn(100);

      const offset = $container.offset();
      const toggleHeight = $toggleButton.height();
      const toggleWidth = $toggleButton.outerWidth();
      const height = $container.outerHeight();
      const width = $container.outerWidth();
      const left = Math.floor(offset.left + width / 2 - toggleWidth / 2);

      let top = '';
      switch (placement) {
        case 'insert':
          top = Math.floor(offset.top + height / 2 - toggleHeight / 2);
          break;
        case 'after':
          top = Math.floor(offset.top + height - toggleHeight / 2) - 1;
          break;
        case 'before':
          top = Math.floor(offset.top - toggleHeight / 2) - 1;
          break;
        default:
          top = null;
      }

      $toggleButton.offset({ left, top });
    }

    insertSectionMenu($container, placement, method) {
      const offset = $container.offset();
      const width = $container.outerWidth();
      const $sectionMenu = $(
        `<div class="js-lpb-section-menu lpb-section-menu__wrapper">${this.sectionMenu}</div>`,
      )
        .attr({
          'data-placement': placement,
          'data-container-uuid': $container
            .closest('[data-uuid]')
            .attr('data-uuid'),
        })
        .css({ position: 'absolute' })
        [`${method}To`]($container);
      const sectionMenuWidth = $sectionMenu.outerWidth();
      const left = Math.floor(offset.left + width / 2 - sectionMenuWidth / 2);
      $sectionMenu.offset({ left });
    }

    removeControls() {
      if (this.$activeItem) {
        this.$activeItem.find('.js-lpb-controls').remove();
      }
      this.$element.find('.js-lpb-toggle').remove();
      this.$element.find('.js-lpb-section-menu').remove();
      this.$element.find('.lp-builder-controls-menu').remove();
      this.$componentMenu = false;
      this.$activeToggle = false;
    }

    insertControls($element) {
      const $controls = $(
        `<div class="js-lpb-controls lpb-controls__wrapper">${this.controls}</div>`,
      )
        .css({ position: 'absolute' })
        .hide();
      $controls
        .find('.lpb-controls-label')
        .text(this.settings.types[$element.attr('data-type')].name);
      const offset = $element.offset();
      this.$element.find('.js-lpb-controls').remove();
      $element.prepend($controls.fadeIn(200));
      if (
        $element.parents('.lpb-layout').length === 0 &&
        this.options.requireSections
      ) {
        this.insertSectionMenu($element, 'before', 'prepend');
        this.insertSectionMenu($element, 'after', 'append');
      } else {
        this.insertToggle($element, 'before', 'prepend');
        this.insertToggle($element, 'after', 'append');
      }
      $controls.offset(offset);
    }

    /**
     * Toggles the create content component menu.
     * @param {jQuery} $toggleButton The button that triggered the toggle.
     */
    toggleComponentMenu($toggleButton) {
      if (this.$componentMenu) {
        this.closeComponentMenu($toggleButton);
      } else {
        this.openComponentMenu($toggleButton);
      }
    }

    /**
     * Opens the component menu.
     * @param {jQuery} $toggleButton The toggle button that was pressed.
     */
    openComponentMenu($toggleButton) {
      this.$activeToggle = $toggleButton;
      this.$activeToggle.addClass('active');
      this.$element.find('.lpb-toggle, .js-lpb-controls').not('.active').hide();
      this.$componentMenu = $(
        `<div class="js-lpb-component-menu lpb-component-menu__wrapper">${this.componentMenu}</div>`,
      );
      if (this.options.nestedSections === false) {
        if (this.$activeToggle.parents('.lpb-layout').length > 0) {
          this.$componentMenu.find('.lpb-component-menu__group--layout').hide();
        }
      }
      this.$activeToggle.after(this.$componentMenu);
      if (this.$componentMenu.find('.lpb-component-menu__item').length > 6) {
        this.$componentMenu.find('.lpb-component-menu__search');
        this.$componentMenu.find('.lpb-component-menu-search-input').focus();
      } else {
        this.$componentMenu.find('.lpb-component-menu__search').hide();
      }
      this.positionComponentMenu();
      this.stopInterval();
    }

    /**
     * Position the component menu correctly.
     * @param {bool} keepOrientation If true, the menu will stay above/below no matter what.
     */
    positionComponentMenu(keepOrientation) {
      // Move the menu to correct spot.
      const btnOffset = this.$activeToggle.offset();
      const menuOffset = this.$componentMenu.offset();
      const viewportTop = $(window).scrollTop();
      const viewportBottom = viewportTop + $(window).height();
      const menuWidth = this.$componentMenu.outerWidth();
      const btnWidth = this.$activeToggle.outerWidth();
      const btnHeight = this.$activeToggle.height();
      const menuHeight = this.$componentMenu.outerHeight();

      // Accounts for rotation by calculating distance between points on 45 degree rotated square.
      const left = Math.floor(btnOffset.left + btnWidth / 2 - menuWidth / 2);

      // Default to positioning the menu beneath the button.
      let orientation = 'beneath';
      let top = Math.floor(btnOffset.top + btnHeight * 1.5);

      // The menu is above the button, keep it that way.
      if (keepOrientation === true && menuOffset.top < btnOffset.top) {
        orientation = 'above';
      }
      // The menu would go out of the viewport, so keep at top.
      if (top + menuHeight > viewportBottom) {
        orientation = 'above';
      }
      this.$componentMenu
        .removeClass('above')
        .removeClass('beneath')
        .addClass(orientation);

      if (orientation === 'above') {
        top = Math.floor(
          btnOffset.top -
            (menuHeight -
              parseInt(this.$componentMenu.css('padding-bottom'), 10)),
        );
      }
      this.$componentMenu.removeClass('hidden').addClass('fade-in');
      this.$componentMenu.offset({ top, left });
    }

    /**
     * Close the open component menu.
     */
    closeComponentMenu() {
      this.$componentMenu.remove();
      this.$element.find('.lpb-toggle.active').removeClass('active');
      this.$element.find('.lpb-toggle, .js-lpb-controls').show();
      this.$componentMenu = false;
      this.$activeToggle = false;
      this.startInterval();
    }

    /**
     * Loads an edit form.
     */
    editForm() {
      const uuid = this.$activeItem.attr('data-uuid');
      const endpoint = `${this.settings.baseUrl}/edit/${uuid}`;
      Drupal.ajax({
        url: endpoint,
        submit: {
          layoutParagraphsState: JSON.stringify(this.getState()),
        },
      })
        .execute()
        .done(() => {
          this.removeControls();
          this.removeToggle();
        });
    }

    /**
     * Delete a component.
     * @param {jQuery} $item The item to delete.
     */
    delete($item) {
      const uuid = $item.attr('data-uuid');
      $item.fadeOut(200, () => {
        this.request(`delete/${uuid}`, { success: () => this.edited() });
      });
    }

    /**
     * Inserts a new component next to an existing sibling component.
     * @param {string} siblingUuid The uuid of the existing component.
     * @param {string} type The component type for the component being inserted.
     * @param {string} placement Where the new component is to be added in comparison to sibling (before or after).
     */
    insertSiblingComponent(siblingUuid, type, placement) {
      this.request(`insert-sibling/${siblingUuid}/${placement}/${type}`);
    }

    /**
     * Inserts a new component and loads the edit form dialog.
     * @param {string} type The component type.
     */
    insertComponent(type) {
      this.request(`insert-component/${type}`);
    }

    /**
     * Saves the order and nested structure of components for the layout.
     */
    saveComponentOrder() {
      this.request('reorder', {
        data: {
          layoutParagraphsState: JSON.stringify(this.getState()),
        },
      });
    }

    /**
     * Inserts a new component into a region.
     * @param {string} parentUuid The parent component's uuid.
     * @param {string} region The region to insert into.
     * @param {string} type The type of component to insert.
     */
    insertComponentIntoRegion(parentUuid, region, type) {
      this.request(`insert-into-region/${parentUuid}/${region}/${type}`);
    }

    /**
     * Moves a component up or down.
     * @param {int} direction 1 (down) or -1 (up).
     * @return {void}
     */
    move(direction) {
      const instance = this;
      const $moveItem = this.$activeItem;
      const $sibling =
        direction === 1
          ? $moveItem.nextAll('.lpb-component:visible').first()
          : $moveItem.prevAll('.lpb-component:visible').first();
      const method = direction === 1 ? 'after' : 'before';
      const { scrollY } = window;
      const destScroll = scrollY + $sibling.outerHeight() * direction;
      const distance = Math.abs(destScroll - scrollY);

      if ($sibling.length === 0) {
        return false;
      }

      this.removeControls();
      this.stopInterval();

      $({ translateY: 0 }).animate(
        { translateY: 100 * direction },
        {
          duration: Math.max(100, Math.min(distance, 500)),
          easing: 'swing',
          step() {
            const a = $sibling.outerHeight() * (this.translateY / 100);
            const b = -$moveItem.outerHeight() * (this.translateY / 100);
            $moveItem.css({ transform: `translateY(${a}px)` });
            $sibling.css({ transform: `translateY(${b}px)` });
          },
          complete() {
            $moveItem.css({ transform: 'none' });
            $sibling.css({ transform: 'none' });
            $sibling[method]($moveItem);
            instance.$activeItem = $moveItem;
            instance.startInterval();
            instance.saveComponentOrder();
          },
        },
      );
      if (distance > 50) {
        $('html, body').animate({ scrollTop: destScroll });
      }
      this.edited();
    }

    /**
     * Initiates dragula drag/drop functionality.
     * @param {object} $widget ERL field item to attach drag/drop behavior to.
     * @param {object} widgetSettings The widget instance settings.
     */
    enableDragAndDrop() {
      // this.$element.addClass("dragula-enabled");
      // Turn on drag and drop if dragula function exists.
      if (typeof dragula !== 'undefined') {
        const instance = this;
        const items = this.$element
          .find('.lpb-region')
          .addBack()
          .not('.dragula-enabled')
          .addClass('dragula-enabled')
          .get();

        // Dragula is already initialized, add any new containers that may have been added.
        if (this.$element.data('drake')) {
          Object.values(items).forEach((item) => {
            if (this.$element.data('drake').containers.indexOf(item) === -1) {
              this.$element.data('drake').containers.push(item);
            }
          });
          return;
        }
        this.drake = dragula(items, {
          accepts(el, target, source, sibling) {
            // Returns false if any registered callback returns false.
            return (
              Drupal.lpBuilderInvokeCallbacks('accepts', {
                el,
                target,
                source,
                sibling,
              }).indexOf(false) === -1
            );
          },
          moves(el, source, handle) {
            const $handle = $(handle);
            if (
              $handle.closest(
                '.lpb-controls,.js-lpb-toggle,.lpb-status,.js-lpb-section-menu',
              ).length
            ) {
              return false;
            }
            return true;
          },
        });
        this.drake.on('drop', () => {
          instance.saveComponentOrder();
          instance.edited();
        });
        this.drake.on('drag', (el) => {
          instance.$activeItem = false;
          instance.$element.addClass('is-dragging');
          if (el.className.indexOf('lpb-layout') > -1) {
            instance.$element.addClass('is-dragging-layout');
          } else {
            instance.$element.addClass('is-dragging-item');
          }
        });
        this.drake.on('dragend', () => {
          instance.$element
            .removeClass('is-dragging')
            .removeClass('is-dragging-layout')
            .removeClass('is-dragging-item');
        });
        this.drake.on('over', (el, container) => {
          $(container).addClass('drag-target');
        });
        this.drake.on('out', (el, container) => {
          $(container).removeClass('drag-target');
        });
        this.$element.data('drake', this.drake);
      }
    }

    /**
     * Add new containers to the dragula instance.
     * @param {array} containers The containers to add.
     */
    addDragContainers(containers) {
      containers.forEach((value) => {
        if (this.drake.containers.indexOf(value) === -1) {
          this.drake.containers.push(value);
        }
      });
    }

    /**
     * Called after
     */
    saved() {
      this._edited = false;
    }

    edited() {
      if (this.$element.find('.lpb-component').length > 0) {
        this.isNotEmpty();
      } else {
        this.isEmpty();
      }
      this._edited = true;
    }

    isNotEmpty() {
      this.$element.find('.js-lpb-empty').remove();
    }

    isEmpty() {
      this.isNotEmpty();
      this.$activeItem = false;
      const $emptyContainer = $(
        `<div class="js-lpb-empty lpb-empty-container__wrapper">${this.emptyContainer}</div>`,
      ).appendTo(this.$element);
      if (this.options.requireSections) {
        this.insertSectionMenu($emptyContainer, 'insert', 'append');
      } else {
        this.insertToggle($emptyContainer, 'insert', 'append');
      }
    }

    /**
     * Make a Drupal Ajax request.
     * @param {string} apiUrl The request url.
     * @param {obj} settings (optional) Request settings.
     */
    request(apiUrl, settings = {}) {
      const url = `${this.settings.baseUrl}/${apiUrl}`;
      const { data, success } = settings;
      Drupal.ajax({
        url,
        submit: data,
      })
        .execute()
        .done(() => {
          if (success && typeof success === 'function') {
            success.call(this);
          }
        });
    }
  }
  /**
   * Respond to a component being updated or inserted by an AJAX request.
   * @param {string} layoutId The layout id.
   * @param {string} componentUuid The uuid of the updated component.
   */
  function componentUpdate(layoutId, componentUuid) {
    const builder = $(`[data-lp-builder-id="${layoutId}"`).data('lpbInstance');
    builder.removeControls();
    const $insertedComponent = $(`[data-uuid="${componentUuid}"]`);
    const dragContainers = $insertedComponent.find('.lpb-region').get();
    builder.addDragContainers(dragContainers);
    builder.edited();
    builder.saveComponentOrder();
    builder.$activeItem = $insertedComponent;
  }
  /**
   * Registers a callback to be called when a specific hook is invoked.
   * @param {String} hook The name of the hook.
   * @param {function} callback The function to call.
   */
  Drupal.lpBuilderRegisterCallback = (hook, callback) => {
    if (Drupal.lpBuilderCallbacks === undefined) {
      Drupal.lpBuilderCallbacks = [];
    }
    Drupal.lpBuilderCallbacks.push({ hook, callback });
  };
  /**
   * Removes a callback from the list.
   * @param {String} hook The name of the hook.
   */
  Drupal.lpBuilderUnRegisterCallback = (hook) => {
    Drupal.lpBuilderCallbacks = Drupal.lpBuilderCallbacks.filter(
      (item) => item.hook !== hook,
    );
  };
  /**
   * Invoke all callbacks for a specific hook.
   * @param {string} hook The name of the hook.
   * @param {object} param The parameter object which will be passed to the callback.
   * @return {array} an array of returned values from callback functions.
   */
  Drupal.lpBuilderInvokeCallbacks = (hook, param) => {
    const applicableCallbacks = Drupal.lpBuilderCallbacks.filter(
      (item) => item.hook.split('.')[0] === hook,
    );
    return applicableCallbacks.map((callback) =>
      typeof callback.callback === 'function' ? callback.callback(param) : null,
    );
  };
  Drupal.behaviors.layoutParagraphsBuilder = {
    attach: function attach(context, settings) {
      if (settings.layoutParagraphsBuilder) {
        Object.values(settings.layoutParagraphsBuilder).forEach(
          (builderSettings) => {
            $(builderSettings.selector)
              .once('lp-builder')
              .each((index, element) => {
                $(element)
                  .addClass('js-lpb-container')
                  .data('lpbInstance', new LPBuilder(builderSettings));
              });
          },
        );
      }
    },
  };
  Drupal.AjaxCommands.prototype.layoutParagraphsBuilderInvokeHook = (
    ajax,
    response,
  ) => {
    Drupal.lpBuilderInvokeCallbacks(response.hook, response.params);
  };
  Drupal.lpBuilderRegisterCallback('accepts', (params) => {
    const { el, target } = params;
    // Layout sections can only go at the root level.
    if (el.className.indexOf('lpb-layout') > -1) {
      return target.className.indexOf('lp-builder') > -1;
    }
    if (el.className.indexOf('lpb-component') > -1) {
      return target.className.indexOf('lpb-region') > -1;
    }
  });
  Drupal.lpBuilderRegisterCallback('save', (layoutId) => {
    $(`[data-lp-builder-id="${layoutId}"`).data('lpbInstance').saved();
  });
  Drupal.lpBuilderRegisterCallback('updateComponent', (params) => {
    const { layoutId, componentUuid } = params;
    componentUpdate(layoutId, componentUuid);
  });
  Drupal.lpBuilderRegisterCallback('insertComponent', (params) => {
    const { layoutId, componentUuid } = params;
    componentUpdate(layoutId, componentUuid);
  });
})(jQuery, Drupal, drupalSettings, dragula);