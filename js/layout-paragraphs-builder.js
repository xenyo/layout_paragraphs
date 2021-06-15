(($, Drupal, debounce, drupalSettings, dragula) => {
  const idAttr = 'data-lpb-id';
  const reorderComponents = debounce($element => {
    const id = $element.attr(idAttr);
    const order = $('.lpb-component', $element)
      .get()
      .map(item => {
        const $item = $(item);
        return {
          uuid: $item.attr('data-uuid'),
          parentUuid:
            $item
              .parents('.lpb-component')
              .first()
              .attr('data-uuid') || null,
          region:
            $item
              .parents('.lpb-region')
              .first()
              .attr('data-region') || null,
        };
      });
    Drupal.ajax({
      url: `/layout-paragraphs-builder/${id}/reorder`,
      submit: {
        layoutParagraphsState: JSON.stringify(order),
      },
    }).execute();
  });
  /**
   * Returns a list of errors for the attempted move, or an empty array if there are no errors.
   * @param {Element} settings The builder settings.
   * @param {Element} el The element being moved.
   * @param {Element} target The destination
   * @param {Element} source The source
   * @param {Element} sibling The next sibling element
   * @return {Array} An array of errors.
   */
  function moveErrors(settings, el, target, source, sibling) {
    return Drupal._lpbMoveErrors
      .map(validator =>
        validator.apply(null, [settings, el, target, source, sibling]),
      )
      .filter(errors => errors !== false && errors !== undefined);
  }
  function updateMoveButtons($element) {
    $element.find('.lpb-up, .lpb-down').attr('tabindex', '0');
    $element
      .find(
        '.lpb-component:first-of-type .lpb-up, .lpb-component:last-of-type .lpb-down',
      )
      .attr('tabindex', '-1');
  }
  function updateUi($element) {
    reorderComponents($element);
    updateMoveButtons($element);
  }
  /**
   * Moves a component up or down within a simple list of components.
   * @param {jQuery} $moveItem The item to move.
   * @param {int} direction 1 (down) or -1 (up).
   * @return {void}
   */
  function move($moveItem, direction) {
    const $sibling =
      direction === 1
        ? $moveItem.nextAll('.lpb-component').first()
        : $moveItem.prevAll('.lpb-component').first();
    const method = direction === 1 ? 'after' : 'before';
    const { scrollY } = window;
    const destScroll = scrollY + $sibling.outerHeight() * direction;
    const distance = Math.abs(destScroll - scrollY);

    if ($sibling.length === 0) {
      return false;
    }

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
          updateUi($moveItem.closest(`[${idAttr}]`));
        },
      },
    );
    if (distance > 50) {
      $('html, body').animate({ scrollTop: destScroll });
    }
  }
  /**
   * Moves the focused component up or down the DOM to the next valid position
   * when an arrow key is pressed. Unlike move(), nav()can fully navigate
   * components to any valid position in an entire layout.
   * @param {jQuery} $item The jQuery item to move.
   * @param {int} dir The direction to move (1 == down, -1 == up).
   * @param {Object} settings The builder ui settings.
   */
  function nav($item, dir, settings) {
    const $element = $item.closest(`[${idAttr}]`);
    $item.addClass('lpb-active-item');
    // Add shims as target elements.
    if (dir === -1) {
      $(
        '.lpb-region .lpb-btn--add, .lpb-layout:not(.lpb-active-item)',
        $element,
      ).before('<div class="lpb-shim"></div>');
    } else if (dir === 1) {
      $('.lpb-region', $element).prepend('<div class="lpb-shim"></div>');
      $('.lpb-layout:not(.lpb-active-item)', $element).after(
        '<div class="lpb-shim"></div>',
      );
    }
    // Build a list of possible targets, or move destinatons.
    const targets = $('.lpb-component, .lpb-shim', $element)
      .toArray()
      // Remove child components from possible targets.
      .filter(i => !$.contains($item[0], i))
      // Remove layout elements that are not self from possible targets.
      .filter(i => i.className.indexOf('lpb-layout') === -1 || i === $item[0]);
    const currentElement = $item[0];
    let pos = targets.indexOf(currentElement);
    // Check to see if the next position is allowed by calling the 'accepts' callback.
    while (
      targets[pos + dir] !== undefined &&
      moveErrors(
        settings,
        $item[0],
        targets[pos + dir].parentNode,
        null,
        $item.next().length ? $item.next()[0] : null,
      ).length > 0
    ) {
      pos += dir;
    }
    if (targets[pos + dir] !== undefined) {
      // Move after or before the target based on direction.
      $(targets[pos + dir])[dir === 1 ? 'after' : 'before']($item);
    }
    // Remove the shims and save the order.
    $('.lpb-shim', $element).remove();
    updateUi($element);
    $item.removeClass('lpb-active-item').focus();
  }

  function attachEventListeners($element, settings) {
    $element.on('click.lp-builder', '.lpb-up', e => {
      move($(e.target).closest('.lpb-component'), -1);
      return false;
    });
    $element.on('click.lp-builder', '.lpb-down', e => {
      move($(e.target).closest('.lpb-component'), 1);
      return false;
    });
    $element.on('click.lp-builder', '.lpb-component', e => {
      $(e.currentTarget).focus();
      return false;
    });
    document.addEventListener('keydown', e => {
      const $item = $('.lpb-component:focus');
      if ($item.length) {
        if (e.code === 'ArrowDown' && $item) {
          nav($item, 1, settings);
        }
        if (e.code === 'ArrowUp' && $item) {
          nav($item, -1, settings);
        }
      }
    });
  }
  Drupal._lpbMoveErrors = [];
  /**
   * Registers a move validation function.
   * @param {Funciton} f The validator function.
   */
  Drupal.registerLpbMoveError = f => {
    Drupal._lpbMoveErrors.push(f);
  };
  // Checks nesting depth.
  Drupal.registerLpbMoveError((settings, el, target) => {
    if (el.className.indexOf('lpb-layout') > -1) {
      return $(target).parents('.lpb-layout').length > settings.nesting_depth;
    }
  });
  // If layout is required, prevents component from being placed outside a layout.
  Drupal.registerLpbMoveError((settings, el, target) => {
    if (settings.require_layouts) {
      if (
        el.className.indexOf('lpb-component') > -1 &&
        el.className.indexOf('lpb-layout') === -1
      ) {
        return target.className.indexOf('lpb-region') === -1;
      }
    }
  });
  Drupal.behaviors.layoutParagraphsBuilder = {
    attach: function attach(context, settings) {
      // Run only once - initialize the editor ui.
      $(`.has-components[${idAttr}]`)
        .once('lpb-enabled')
        .each((index, element) => {
          const $element = $(element);
          const id = $element.attr(idAttr);
          const lpbSettings = settings.lpBuilder[id];
          const drake = dragula({
            isContainer: el =>
              el.classList.contains('lpb-component-list') ||
              el.classList.contains('lpb-region'),
            accepts(el, target, source, sibling) {
              // Returns false if any registered validator returns a value.
              // @see addMoveValidator()
              return (
                moveErrors(lpbSettings, el, target, source, sibling).length ===
                0
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
          drake.on('drop', el => {
            const $el = $(el);
            if ($el.prev().is('a')) {
              $el.insertBefore($el.prev());
            }
            updateUi($element);
          });
          drake.on('drag', el => {
            $element.addClass('is-dragging');
            if (el.className.indexOf('lpb-layout') > -1) {
              $element.addClass('is-dragging-layout');
            } else {
              $element.addClass('is-dragging-item');
            }
          });
          drake.on('dragend', () => {
            $element
              .removeClass('is-dragging')
              .removeClass('is-dragging-layout')
              .removeClass('is-dragging-item');
          });
          drake.on('over', (el, container) => {
            $(container).addClass('drag-target');
          });
          drake.on('out', (el, container) => {
            $(container).removeClass('drag-target');
          });
          $element.data('drake', drake);
          updateMoveButtons($element);
          attachEventListeners($element, lpbSettings);
        });
      // Run every time the behavior is attached.
      if (context.classList && context.classList.contains('lpb-component')) {
        $(context)
          .closest('[data-lpb-id]')
          .each((index, element) => {
            updateMoveButtons($(element));
          });
      }
    },
  };
})(jQuery, Drupal, Drupal.debounce, drupalSettings, dragula);
