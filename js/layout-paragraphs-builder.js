(($, Drupal, debounce, drupalSettings, dragula) => {
  const idAttr = 'data-lp-builder-id';
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
   * @param {Element} el The element being moved.
   * @param {Element} target The distination
   * @param {Element} settings The builder settings.
   * @return {Array} An array of errors.
   */
  function lpbMoveErrors(el, target, settings) {
    return Drupal._lpbMoveErrors
      .map(validator => validator.apply(null, [el, target, settings]))
      .filter(errors => errors !== false && errors !== undefined);
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
  Drupal.registerLpbMoveError((el, target, settings) => {
    if (el.className.indexOf('lpb-layout') > -1) {
      return $(target).parents('.lpb-layout').length > settings.nesting_depth;
    }
  });
  // If layout is required, prevents component from being placed outside a layout.
  Drupal.registerLpbMoveError((el, target, settings) => {
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
      $('[data-lp-builder-id]', context)
        .once('lp-builder')
        .each((index, element) => {
          const $element = $(element);
          const id = $element.attr(idAttr);
          const lpbSettings = settings.lpBuilder[id];
          const dragContainers = $element
            .find('.lpb-components, .lpb-region')
            .get();
          const drake = dragula(dragContainers, {
            accepts(el, target) {
              // Returns false if any registered validator returns a value.
              // @see addMoveValidator()
              return lpbMoveErrors(el, target, lpbSettings).length === 0;
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
          drake.on('drop', () => {
            reorderComponents($element);
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
        });
    },
  };
})(jQuery, Drupal, Drupal.debounce, drupalSettings, dragula);
