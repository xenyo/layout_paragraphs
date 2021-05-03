(($, Drupal, drupalSettings, dragula) => {
  class LPBuilder {
    constructor(settings) {
      this._edited = false;
      this.settings = settings;
      this.options = settings.options || {};
      if (this.options.movable === undefined) this.options.movable = true;
      if (this.options.draggable === undefined) this.options.draggable = true;
      if (this.options.createContent === undefined)
        this.options.createContent = true;
      if (this.options.createLayouts === undefined)
        this.options.createLayouts = true;
      if (this.options.deleteContent === undefined)
        this.options.deleteContent = true;
      if (this.options.deleteLayouts === undefined)
        this.options.deleteLayouts = true;
      this.options.nestingDepth = Number(this.options.nestingDepth);
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
      this._events = {};

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

      // Respond to attempt to move an element.
      this.on('accepts', (params) => {
        const { el, target } = params;
        // Ensure correct nesting depth.
        if (el.className.indexOf('lpb-layout') > -1) {
          return (
            $(target).parents('.lpb-layout').length <= this.options.nestingDepth
          );
        }
        if (this.options.requireLayouts) {
          if (el.className.indexOf('lpb-component') > -1) {
            return target.className.indexOf('lpb-region') > -1;
          }
        }
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
      const $component = $(e.currentTarget).closest('.lpb-component');
      const type = $component.attr('data-type');
      const typeName = this.settings.types[type].name || Drupal.t('Component');
      this.confirm($component, {
        content: Drupal.t('Really delete this @name?', { '@name': typeName }),
        confirmText: Drupal.t('Delete'),
        confirm: (_e) => {
          deleteComponent($component);
          _e.preventDefault();
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
          if (uuid) {
            this.insertComponentIntoRegion(uuid, region, type);
          } else {
            this.insertComponent(type);
          }
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
      if (e.code === 'ArrowDown' && this.$activeItem) {
        this.navDown(this.$activeItem);
      }
      if (e.code === 'ArrowUp' && this.$activeItem) {
        this.navUp(this.$activeItem);
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
      this.emit('focus', $item);
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
     * @param {Object} offset An optional object with top or left offset values.
     */
    insertToggle($container, placement, method = 'prepend', offset = {}) {
      if (!this.options.createContent) {
        return;
      }
      const offsetTop = offset.top || 0;
      const offsetLeft = offset.left || 0;
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

      const containerOffset = $container.offset();
      const toggleHeight = $toggleButton.height();
      const toggleWidth = $toggleButton.outerWidth();
      const height = $container.outerHeight();
      const width = $container.outerWidth();
      const left = Math.floor(
        containerOffset.left + width / 2 - toggleWidth / 2 + offsetLeft,
      );

      let top = '';
      switch (placement) {
        case 'insert':
          top = Math.floor(containerOffset.top + height / 2 - toggleHeight / 2);
          break;
        case 'after':
          top = Math.floor(containerOffset.top + height - toggleHeight / 2) - 1;
          break;
        case 'before':
          top = Math.floor(containerOffset.top - toggleHeight / 2) - 1;
          break;
        default:
          top = null;
      }
      top += offsetTop;
      $toggleButton.offset({ left, top });
      this.emit('drawtoggle', this);
    }

    insertSectionMenu($container, placement, method) {
      if (!this.options.createLayouts) {
        return;
      }
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
      this.emit('openmenu', $sectionMenu);
    }

    removeControls() {
      if (this.$activeItem) {
        this.$activeItem.find('.js-lpb-controls').remove();
        this.$activeItem.find('.lpb-layout-label').remove();
      }
      this.$element.find('.js-lpb-toggle').remove();
      this.$element.find('.js-lpb-section-menu').remove();
      this.$element.find('.lp-builder-controls-menu').remove();
      this.$componentMenu = false;
      this.$activeToggle = false;
      this.emit('removecontrols');
    }

    insertControls($element) {
      const $controls = $(
        `<div class="js-lpb-controls lpb-controls__wrapper">${this.controls}</div>`,
      )
        .css({ position: 'absolute' })
        .hide();
      const $label = $controls.find('.lpb-controls-label');
      const labelText = [];
      if (this.options.showTypeLabels) {
        labelText.push(this.settings.types[$element.attr('data-type')].name);
      }
      if (this.options.showLayoutLabels && $element.attr('data-layout')) {
        labelText.push(this.settings.layouts[$element.attr('data-layout')]);
      }
      if (labelText.length) {
        $label.text(labelText.join(' - '));
      } else {
        $label.remove();
      }
      const offset = $element.offset();
      if (!this.options.movable) {
        $('.lpb-up, .lpb-down', $controls).remove();
      }
      if (!this.options.deleteContent) {
        $('.lpb-delete', $controls).remove();
      }
      this.$element.find('.js-lpb-controls').remove();
      $element.prepend($controls.fadeIn(200));
      if (
        $element.parents('.lpb-layout').length === 0 &&
        this.options.requireLayouts
      ) {
        this.insertSectionMenu($element, 'before', 'prepend');
        this.insertSectionMenu($element, 'after', 'append');
      } else {
        this.insertToggle($element, 'before', 'prepend');
        this.insertToggle($element, 'after', 'append');
      }
      $controls.offset(offset);
      this.emit('insertcontrols', $controls);
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
      if (this.options.nestingSections === false) {
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
      // Remove layout items if nesting depth has been reached.
      if (
        this.$componentMenu.parents('.lpb-layout').length >
        this.options.nestingDepth
      ) {
        $('.lpb-component-menu__group--layout', this.$componentMenu).remove();
      }
      this.positionComponentMenu();
      Drupal.lpBuilderInvokeCallbacks('componentMenu', {
        $menu: this.$componentMenu,
        lpBuilder: this,
      });
      this.stopInterval();
      this.emit('openmenu', this.$componentMenu);
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
      this.emit('closemenu');
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
          this.emit('editform', uuid);
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
      // @todo Should we pass more meaningful data?
      this.emit('delete', uuid);
    }

    /**
     * A configurable confirmation dialog.
     * @param {jQuery} $container The container for this confirm dialog.
     * @param {Object} options The confirmation options.
     * @return {Object} this
     */
    confirm($container, options) {
      const { content } = options;
      const confirmText = options.confirmText || Drupal.t('Confirm');
      const cancelText = options.cancelText || Drupal.t('Cancel');
      const $content = $(`<div class="lpb-confirm">
        <div class="lpb-confirm-container">
          <div class="lpb-confirm-wrapper">
            <div class="lpb-confirm__content">${content}</div>
            <div class="lpb-confirm__actions">
              <button class="lpb-confirm-btn">${confirmText}</button>
              <button class="lpb-cancel-btn">${cancelText}</button>
            </div>
          </div>
        </div>
      </div>`);
      $content.on('click.lpb-confirm', '.lpb-confirm-btn', options.confirm);
      $content.on('click.lpb-confirm', '.lpb-cancel-btn', (e) => {
        this.cancel();
        e.preventDefault();
      });
      this.$element.on('keyup.lpb-confirm', '.lpb-confirm', (e) => {
        if (e.code === 'Escape') {
          this.cancel();
          e.preventDefault();
        }
      });
      $container.prepend($content);
      $('.lpb-confirm-btn', $content).focus();
      return this;
    }

    cancel() {
      $('.lpb-confirm', this.$element).off('.lpb-confirm').remove();
      return this;
    }

    /**
     * Inserts a new component next to an existing sibling component.
     * @param {string} siblingUuid The uuid of the existing component.
     * @param {string} type The component type for the component being inserted.
     * @param {string} placement Where the new component is to be added in comparison to sibling (before or after).
     */
    insertSiblingComponent(siblingUuid, type, placement) {
      this.request(`insert-sibling/${siblingUuid}/${placement}/${type}`);
      this.emit('insertsibling', siblingUuid, placement, type);
    }

    /**
     * Inserts a new component and loads the edit form dialog.
     * @param {string} type The component type.
     */
    insertComponent(type) {
      this.request(`insert-component/${type}`);
      this.emit('insert', type);
    }

    /**
     * Saves the order and nested structure of components for the layout.
     */
    saveComponentOrder() {
      const state = JSON.stringify(this.getState());
      this.request('reorder', {
        data: {
          layoutParagraphsState: state,
        },
      });
      this.emit('saveorder', state);
    }

    /**
     * Inserts a new component into a region.
     * @param {string} parentUuid The parent component's uuid.
     * @param {string} region The region to insert into.
     * @param {string} type The type of component to insert.
     */
    insertComponentIntoRegion(parentUuid, region, type) {
      this.request(`insert-into-region/${parentUuid}/${region}/${type}`);
      this.emit('insertintoregion', parentUuid, region, type);
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
     * Moves the active component down the DOM when an arrow key is pressed.
     */
    navDown() {
      this.nav(1);
    }

    /**
     * Moves the active component up the DOM when an arrow key is pressed.
     */
    navUp() {
      this.nav(-1);
    }

    /**
     * Moves the active component up or down the DOM when an arrow key is pressed.
     * @param {int} dir The direction to move (1 == down, -1 == up).
     */
    nav(dir) {
      if (!this.$activeItem) return;
      this.removeControls();
      // We need to stop listening to hover events to prevent another
      // item from immediately becoming the active one. Hover will presume
      // when the mouse is moved.
      this.stopInterval();
      // Add shims as target elements.
      $('.lpb-region', this.$element)[dir === 1 ? 'prepend' : 'append'](
        '<div class="lpb-shim"></div>',
      );
      $('.lpb-layout:not(.lpb-active-item)', this.$element)[
        dir === 1 ? 'after' : 'before'
      ]('<div class="lpb-shim"></div>');
      // Build a list of possible targets, or move destinatons.
      const targets = $('.lpb-component, .lpb-shim', this.$element)
        .toArray()
        // Remove child components from possible targets.
        .filter((i) => !$.contains(this.$activeItem[0], i))
        // Remove layout elements that are not self from possible targets.
        .filter(
          (i) =>
            i.className.indexOf('lpb-layout') === -1 ||
            i === this.$activeItem[0],
        );
      const currentElement = this.$activeItem[0];
      let pos = targets.indexOf(currentElement);
      // Check to see if the next position is allowed by calling the 'accepts' callback.
      while (
        targets[pos + dir] !== undefined &&
        this.emit('accepts', {
          el: this.$activeItem[0],
          target: targets[pos + dir].parentNode,
        }).indexOf(false) !== -1
      ) {
        pos += dir;
      }
      if (targets[pos + dir] !== undefined) {
        // Move after or before the target based on direction.
        $(targets[pos + dir])[dir === 1 ? 'after' : 'before'](this.$activeItem);
      }
      // Remove the shims and save the order.
      $('.lpb-shim', this.$element).remove();
      this.saveComponentOrder();
    }

    addShims() {
      this.removeShims();
      $('.lpb-region', this.$element).prepend('<div class="lpb-shim"></div>');
    }

    removeShims() {
      $('.lpb-shim', this.$element).remove();
    }

    /**
     * Initiates dragula drag/drop functionality.
     * @param {object} $widget ERL field item to attach drag/drop behavior to.
     * @param {object} widgetSettings The widget instance settings.
     */
    enableDragAndDrop() {
      if (!this.options.draggable) {
        return;
      }
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
              instance
                .emit('accepts', {
                  el,
                  target,
                })
                .indexOf(false) === -1
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
      if (this.drake === undefined) return;
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
      if (this.options.requireLayouts) {
        this.insertSectionMenu($emptyContainer, 'insert', 'append');
      } else {
        this.insertToggle($emptyContainer, 'insert', 'append', {
          top: $('.fieldset-wrapper', $emptyContainer).height(),
        });
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

    /**
     * Add an event listener.
     * @param {string} type The event type.
     * @param {function} fn The callback function.
     * @return {LPBuilder} The LPBuilder instance.
     */
    on(type, fn) {
      if (!this._events[type]) {
        this._events[type] = [fn];
      } else {
        this._events[type].push(fn);
      }
      return this;
    }

    /**
     * Add an event listener to fire only once.
     * @param {string} type The event type.
     * @param {function} fn The callback function.
     * @return {LPBuilder} The LPBuilder instance.
     */
    once(type, fn) {
      fn._once = true; // thing.off(fn) still works!
      this.on(type, fn);
      return this;
    }

    /**
     * Remove an event listener.
     * @param {string} type The event type.
     * @param {function} fn The callback function.
     * @return {LPBuilder} The LPBuilder instance.
     */
    off(type = false, fn = false) {
      if (type && !fn) {
        delete this._events[type];
      } else if (!type && !fn) {
        Object.keys(this._events).forEach((key) => {
          delete this._events[key];
        });
      } else {
        const et = this._events[type];
        if (!et) {
          return this;
        }
        et.splice(et.indexOf(fn), 1);
      }
      return this;
    }

    /**
     * Emit an event listener.
     * @param {*} type The event type.
     * @return {array} An array of returned results from listeners.
     */
    emit(...args) {
      const type = args.shift();
      const et = (this._events[type] || []).slice(0);
      const results = [];
      et.forEach((listener) => {
        results.push(listener.apply(this, args));
        if (listener._once) {
          this.off(type, listener);
        }
      });
      return results;
    }
  } // End LPBuilder class.

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

  Drupal.lpBuilder = (settings) => {
    const instance = new LPBuilder(settings);
    Drupal.lpBuilder.instances.push(instance);
    return instance;
  };
  Drupal.lpBuilder.instances = [];
  Drupal.lpBuilder.get = (id) => {
    return Drupal.lpBuilder.filter((lpBuilder) => lpBuilder.id === id).pop();
  };

  Drupal.behaviors.layoutParagraphsBuilder = {
    attach: function attach(context, settings) {
      if (settings.layoutParagraphsBuilder !== undefined) {
        Object.values(settings.layoutParagraphsBuilder).forEach(
          (builderSettings) => {
            $(builderSettings.selector)
              .once('lp-builder')
              .each((index, element) => {
                const builder = Drupal.lpBuilder(builderSettings);
                $(element)
                  .addClass('js-lpb-container')
                  .data('lpbInstance', builder);
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
