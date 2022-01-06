/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }

function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }

function _iterableToArrayLimit(arr, i) { var _i = arr == null ? null : typeof Symbol !== "undefined" && arr[Symbol.iterator] || arr["@@iterator"]; if (_i == null) return; var _arr = []; var _n = true; var _d = false; var _s, _e; try { for (_i = _i.call(arr); !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"] != null) _i["return"](); } finally { if (_d) throw _e; } } return _arr; }

function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }

(function ($, Drupal, debounce, dragula) {
  var idAttr = 'data-lpb-id';

  function attachUiElements($container, id, settings) {
    var lpbBuilderSettings = settings.lpBuilder || {};
    var uiElements = lpbBuilderSettings.uiElements || {};
    var containerUiElements = uiElements[id] || [];
    Object.entries(containerUiElements).forEach(function (_ref) {
      var _ref2 = _slicedToArray(_ref, 2),
          key = _ref2[0],
          uiElement = _ref2[1];

      var element = uiElement.element,
          method = uiElement.method;
      $container[method]($(element).addClass('js-lpb-ui'));
    });
    Drupal.behaviors.AJAX.attach($container[0], drupalSettings);
  }

  function repositionDialog($dialog) {
    var height = $dialog.outerHeight();

    if ($dialog.data('lpOriginalHeight') !== height) {
      var pos = $dialog.dialog('option', 'position');
      $dialog.dialog('option', 'position', pos);
      $dialog.data('lpOriginalHeight', height);
    }
  }

  function doReorderComponents($element) {
    var id = $element.attr(idAttr);
    var order = $('.js-lpb-component', $element).get().map(function (item) {
      var $item = $(item);
      return {
        uuid: $item.attr('data-uuid'),
        parentUuid: $item.parents('.js-lpb-component').first().attr('data-uuid') || null,
        region: $item.parents('.js-lpb-region').first().attr('data-region') || null
      };
    });
    Drupal.ajax({
      url: "".concat(drupalSettings.path.baseUrl).concat(drupalSettings.path.pathPrefix, "layout-paragraphs-builder/").concat(id, "/reorder"),
      submit: {
        components: JSON.stringify(order)
      },
      error: function error() {}
    }).execute();
  }

  var reorderComponents = debounce(doReorderComponents);

  function moveErrors(settings, el, target, source, sibling) {
    return Drupal._lpbMoveErrors.map(function (validator) {
      return validator.apply(null, [settings, el, target, source, sibling]);
    }).filter(function (errors) {
      return errors !== false && errors !== undefined;
    });
  }

  function updateMoveButtons($element) {
    $element.find('.lpb-up, .lpb-down').attr('tabindex', '0');
    $element.find('.js-lpb-component:first-of-type .lpb-up, .js-lpb-component:last-of-type .lpb-down').attr('tabindex', '-1');
  }

  function hideEmptyRegionButtons($element) {
    $element.find('.js-lpb-region').each(function (i, e) {
      var $e = $(e);

      if ($e.find('.js-lpb-component').length === 0) {
        $e.find('.lpb-btn--add.center').css('display', 'block');
      } else {
        $e.find('.lpb-btn--add.center').css('display', 'none');
      }
    });
  }

  function updateUi($element) {
    reorderComponents($element);
    updateMoveButtons($element);
    hideEmptyRegionButtons($element);
  }

  function move($moveItem, direction) {
    var $sibling = direction === 1 ? $moveItem.nextAll('.js-lpb-component').first() : $moveItem.prevAll('.js-lpb-component').first();
    var method = direction === 1 ? 'after' : 'before';
    var _window = window,
        scrollY = _window.scrollY;
    var destScroll = scrollY + $sibling.outerHeight() * direction;
    var distance = Math.abs(destScroll - scrollY);

    if ($sibling.length === 0) {
      return false;
    }

    $({
      translateY: 0
    }).animate({
      translateY: 100 * direction
    }, {
      duration: Math.max(100, Math.min(distance, 500)),
      easing: 'swing',
      step: function step() {
        var a = $sibling.outerHeight() * (this.translateY / 100);
        var b = -$moveItem.outerHeight() * (this.translateY / 100);
        $moveItem.css({
          transform: "translateY(".concat(a, "px)")
        });
        $sibling.css({
          transform: "translateY(".concat(b, "px)")
        });
      },
      complete: function complete() {
        $moveItem.css({
          transform: 'none'
        });
        $sibling.css({
          transform: 'none'
        });
        $sibling[method]($moveItem);
        $moveItem.closest("[".concat(idAttr, "]")).trigger('lpb-component:move', [$moveItem.attr('data-uuid')]);
      }
    });

    if (distance > 50) {
      $('html, body').animate({
        scrollTop: destScroll
      });
    }
  }

  function nav($item, dir, settings) {
    var $element = $item.closest("[".concat(idAttr, "]"));
    $item.addClass('lpb-active-item');

    if (dir === -1) {
      $('.js-lpb-region .lpb-btn--add.center, .lpb-layout:not(.lpb-active-item)', $element).before('<div class="lpb-shim"></div>');
    } else if (dir === 1) {
      $('.js-lpb-region', $element).prepend('<div class="lpb-shim"></div>');
      $('.lpb-layout:not(.lpb-active-item)', $element).after('<div class="lpb-shim"></div>');
    }

    var targets = $('.js-lpb-component, .lpb-shim', $element).toArray().filter(function (i) {
      return !$.contains($item[0], i);
    }).filter(function (i) {
      return i.className.indexOf('lpb-layout') === -1 || i === $item[0];
    });
    var currentElement = $item[0];
    var pos = targets.indexOf(currentElement);

    while (targets[pos + dir] !== undefined && moveErrors(settings, $item[0], targets[pos + dir].parentNode, null, $item.next().length ? $item.next()[0] : null).length > 0) {
      pos += dir;
    }

    if (targets[pos + dir] !== undefined) {
      $(targets[pos + dir])[dir === 1 ? 'after' : 'before']($item);
    }

    $('.lpb-shim', $element).remove();
    $item.removeClass('lpb-active-item').focus();
    $item.closest("[".concat(idAttr, "]")).trigger('lpb-component:move', [$item.attr('data-uuid')]);
  }

  function startNav($item) {
    var $msg = $("<div id=\"lpb-navigating-msg\" class=\"lpb-tooltiptext lpb-tooltiptext--visible js-lpb-tooltiptext\">".concat(Drupal.t('Use arrow keys to move. Press Return or Tab when finished.'), "</div>"));
    $item.closest('.lp-builder').addClass('is-navigating').find('.is-navigating').removeClass('is-navigating');
    $item.attr('aria-describedby', 'lpb-navigating-msg').addClass('is-navigating').prepend($msg);
    $item.before('<div class="lpb-navigating-placeholder"></div>');
  }

  function stopNav($item) {
    $item.removeClass('is-navigating').attr('aria-describedby', '').find('.js-lpb-tooltiptext').remove();
    $item.closest("[".concat(idAttr, "]")).removeClass('is-navigating').find('.lpb-navigating-placeholder').remove();
  }

  function cancelNav($item) {
    var $builder = $item.closest("[".concat(idAttr, "]"));
    $builder.find('.lpb-navigating-placeholder').replaceWith($item);
    updateUi($builder);
    stopNav($item);
  }

  function preventLostChanges($element) {
    var events = ['lpb-component:insert.lpb', 'lpb-component:update.lpb', 'lpb-component:move.lpb', 'lpb-component:drop.lpb'].join(' ');
    $element.on(events, function (e) {
      $(e.currentTarget).addClass('is_changed');
    });
    window.addEventListener('beforeunload', function (e) {
      if ($(".is_changed[".concat(idAttr, "]")).length) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
    $('.form-actions').find('input[type="submit"], a').click(function () {
      $element.removeClass('is_changed');
    });
  }

  function attachEventListeners($element, settings) {
    preventLostChanges($element);
    $element.on('click.lp-builder', '.lpb-up', function (e) {
      move($(e.target).closest('.js-lpb-component'), -1);
      return false;
    });
    $element.on('click.lp-builder', '.lpb-down', function (e) {
      move($(e.target).closest('.js-lpb-component'), 1);
      return false;
    });
    $element.on('click.lp-builder', '.js-lpb-component', function (e) {
      $(e.currentTarget).focus();
      return false;
    });
    $element.on('click.lp-builder', '.lpb-drag', function (e) {
      var $btn = $(e.currentTarget);
      startNav($btn.closest('.js-lpb-component'));
    });
    document.addEventListener('keydown', function (e) {
      var $item = $('.js-lpb-component.is-navigating');

      if ($item.length) {
        switch (e.code) {
          case 'ArrowUp':
          case 'ArrowLeft':
            nav($item, -1, settings);
            break;

          case 'ArrowDown':
          case 'ArrowRight':
            nav($item, 1, settings);
            break;

          case 'Enter':
          case 'Tab':
            stopNav($item);
            break;

          case 'Escape':
            cancelNav($item);
            break;

          default:
            break;
        }
      }
    });
  }

  function initDragAndDrop($element, settings) {
    var drake = dragula($element.find('.js-lpb-component-list, .js-lpb-region').not('.is-dragula-enabled').get(), {
      accepts: function accepts(el, target, source, sibling) {
        return moveErrors(settings, el, target, source, sibling).length === 0;
      },
      moves: function moves(el, source, handle) {
        var $handle = $(handle);

        if ($handle.closest('.lpb-drag').length) {
          return true;
        }

        if ($handle.closest('.lpb-controls').length) {
          return false;
        }

        return true;
      }
    });
    drake.on('drop', function (el) {
      var $el = $(el);

      if ($el.prev().is('a')) {
        $el.insertBefore($el.prev());
      }

      $element.trigger('lpb-component:drop', [$el.attr('data-uuid')]);
    });
    drake.on('drag', function (el) {
      $element.addClass('is-dragging');

      if (el.className.indexOf('lpb-layout') > -1) {
        $element.addClass('is-dragging-layout');
      } else {
        $element.addClass('is-dragging-item');
      }

      $element.trigger('lpb-component:drag', [$(el).attr('data-uuid')]);
    });
    drake.on('dragend', function () {
      $element.removeClass('is-dragging').removeClass('is-dragging-layout').removeClass('is-dragging-item');
    });
    drake.on('over', function (el, container) {
      $(container).addClass('drag-target');
    });
    drake.on('out', function (el, container) {
      $(container).removeClass('drag-target');
    });
    return drake;
  }

  Drupal._lpbMoveErrors = [];

  Drupal.registerLpbMoveError = function (f) {
    Drupal._lpbMoveErrors.push(f);
  };

  Drupal.registerLpbMoveError(function (settings, el, target) {
    if (el.classList.contains('lpb-layout') && $(target).parents('.lpb-layout').length > settings.nesting_depth) {
      return Drupal.t('Exceeds nesting depth of @depth.', {
        '@depth': settings.nesting_depth
      });
    }
  });
  Drupal.registerLpbMoveError(function (settings, el, target) {
    if (settings.require_layouts) {
      if (el.classList.contains('js-lpb-component') && !el.classList.contains('lpb-layout') && !target.classList.contains('js-lpb-region')) {
        return Drupal.t('Components must be added inside sections.');
      }
    }
  });

  Drupal.AjaxCommands.prototype.LayoutParagraphsEventCommand = function (ajax, response) {
    var layoutId = response.layoutId,
        componentUuid = response.componentUuid,
        eventName = response.eventName;
    var $element = $("[data-lpb-id=\"".concat(layoutId, "\"]"));
    $element.trigger("lpb-".concat(eventName), [componentUuid]);
  };

  Drupal.behaviors.layoutParagraphsBuilder = {
    attach: function attach(context, settings) {
      ["".concat(idAttr), 'data-uuid', 'data-region-uuid'].forEach(function (attr) {
        $("[".concat(attr, "]")).not('.lpb-formatter').not('.has-components').once('lpb-ui-elements').each(function (i, el) {
          attachUiElements($(el), el.getAttribute(attr), settings);
        });
      });
      var events = ['lpb-builder:init.lpb', 'lpb-component:insert.lpb', 'lpb-component:update.lpb', 'lpb-component:move.lpb', 'lpb-component:drop.lpb', 'lpb-component:delete.lpb'].join(' ');
      $('[data-lpb-id]').once('lpb-events').on(events, function (e) {
        var $element = $(e.currentTarget);
        updateUi($element);
      });
      $(".has-components[".concat(idAttr, "]")).each(function (index, element) {
        var $element = $(element);
        var id = $element.attr(idAttr);
        var lpbSettings = settings.lpBuilder[id];
        $element.once('lpb-enabled').each(function () {
          $element.data('drake', initDragAndDrop($element, lpbSettings));
          attachEventListeners($element, lpbSettings);
          $element.trigger('lpb-builder:init');
        });
        var drake = $element.data('drake');
        $element.find('.js-lpb-region').not('.is-dragula-enabled').addClass('.is-dragula-enabled').get().forEach(function (c) {
          drake.containers.push(c);
        });
      });
    }
  };
  $(window).on('dialog:aftercreate', function (event, dialog, $dialog) {
    if ($dialog.attr('id').indexOf('lpb-dialog-') === 0) {
      if ($dialog.dialog('option', 'buttons').length > 0) {
        return;
      }

      var buttons = [];
      var $buttons = $dialog.find('.layout-paragraphs-component-form > .form-actions input[type=submit], .layout-paragraphs-component-form > .form-actions a.button');
      $buttons.each(function (_i, el) {
        var $originalButton = $(el).css({
          display: 'none'
        });
        buttons.push({
          text: $originalButton.html() || $originalButton.attr('value'),
          class: $originalButton.attr('class'),
          click: function click(e) {
            if ($originalButton.is('a')) {
              $originalButton[0].click();
            } else {
              $originalButton.trigger('mousedown').trigger('mouseup').trigger('click');
              e.preventDefault();
            }
          }
        });
      });

      if (buttons.length) {
        $dialog.dialog('option', 'buttons', buttons);
      }
    }
  });
  $(window).on('dialog:aftercreate', function (event, dialog, $dialog) {
    if ($dialog[0].id.indexOf('lpb-dialog-') === 0) {
      $dialog.data('lpOriginalHeight', $dialog.outerHeight());
      $dialog.data('lpDialogInterval', setInterval(repositionDialog.bind(null, $dialog), 500));
    }
  });
  $(window).on('dialog:beforeclose', function (event, dialog, $dialog) {
    clearInterval($dialog.data('lpDialogInterval'));
  });
})(jQuery, Drupal, Drupal.debounce, dragula);
