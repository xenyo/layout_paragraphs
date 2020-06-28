(($, Drupal) => {
  /**
   * Sets the state of layout-paragraphs field to loading and adds loading indicator to element.
   * @param {jQuery} $element The jQuery object to set loading state for.
   */
  function setLoading($element) {
    $element
      .addClass("layout-paragraphs-loading")
      .prepend(
        '<div class="loading"><div class="spinner">Loading...</div></div>'
      )
      .closest(".layout-paragraphs-field")
      .data("isLoading", true);
  }
  /**
   * Sets the state of layout-paragraphs field to loaded and removes loading indicator.
   * @param {jQuery} $layoutParagraphsField The jQuery object to set loading state for.
   */
  function setLoaded($layoutParagraphsField) {
    $layoutParagraphsField
      .data("isLoading", false)
      .find(".layout-paragraphs-loading")
      .removeClass("layout-paragraphs-loading")
      .find(".loading")
      .remove();
  }
  /**
   * Returns true if the layout-paragraphsField is loading (i.e. waiting for an Ajax response.)
   * @param {jQuery} $layoutParagraphsField The layout-paragraphs jQuery DOM object.
   * @return {bool} True if state is loading.
   */
  function isLoading($layoutParagraphsField) {
    return $layoutParagraphsField.data("isLoading");
  }
  /**
   * Ajax Command to set state to loaded.
   * @param {object} ajax The ajax object.
   * @param {object} response The response object.
   */
  Drupal.AjaxCommands.prototype.resetLayoutParagraphsState = (
    ajax,
    response
  ) => {
    setLoaded($(response.data.id));
  };
  /**
   * Ajax Command to insert or update a paragraph element.
   * @param {object} ajax The ajax object.
   * @param {object} response The response object.
   */
  Drupal.AjaxCommands.prototype.layoutParagraphsInsert = (ajax, response) => {
    const { settings, content } = response;
    const weight = Math.floor(settings.weight);
    const $container = settings.parent_selector
      ? $(settings.parent_selector, settings.wrapper_selector)
      : $(".active-items", settings.wrapper_selector);
    const $sibling = $container.find(
      `.layout-paragraphs-weight option[value="${weight}"]:selected`
    );

    if ($(settings.selector, settings.wrapper_selector).length) {
      $(settings.selector, settings.wrapper_selector).replaceWith(content);
    } else if ($sibling.length) {
      $sibling.closest(".layout-paragraphs-item").after(content);
    } else {
      $container.prepend(content);
    }

  };
  /**
   * The main layout-paragraphs Widget behavior.
   */
  Drupal.behaviors.layoutParagraphsWidget = {
    attach: function attach(context, settings) {
      /**
       * Returns the region name closes to $el.
       * @param {jQuery} $el The jQuery element.
       * @return {string} The name of the region.
       */
      function getRegion($el) {
        const regEx = /layout-paragraphs-layout-region--([a-z0-9A-Z_]*)/;
        const $container = $el.is(".layout-paragraphs-layout-region")
          ? $el
          : $el.parents(".layout-paragraphs-layout-region");
        let regionName;
        if ($container.length) {
          const matches = $container[0].className.match(regEx);
          if (matches && matches.length >= 2) {
            [, regionName] = matches;
          }
        } else if (
          $el.closest(".layout-paragraphs-disabled-items").length > 0
        ) {
          regionName = "_disabled";
        }
        return regionName;
      }
      /**
       * Updates all field weights and region names based on current state of dom.
       * @param {jQuery} $container The jQuery layout-paragraphs Field container.
       */
      function updateFields($container) {
        // Set deltas:
        let delta = Number($container.find(".layout-paragraphs-weight option:first-child").val());
        $container
          .find(".layout-paragraphs-weight")
          .each((index, item) => {
            if ($(item).hasClass("layout-paragraphs-weight")) {
              delta += 1;
            }
            // If the options don't go high enough, add one.
            if ($(`[value=${delta}]`, item).length == 0) {
              $(item).append(`<option value=${delta}>`);
            }
            $(item).val(`${delta}`);
          });
        $container
          .find("input.layout-paragraphs-region")
          .each((index, item) => {
            $(item).val(getRegion($(item)));
          });
        $container.find(".layout-paragraphs-item").each((index, item) => {
          const $item = $(item);
          const $parentUuidInput = $item.find(".layout-paragraphs-parent-uuid");
          $parentUuidInput.val(
            $item
              .parent()
              .closest(".layout-paragraphs-layout")
              .find(".layout-paragraphs-uuid")
              .val()
          );
        });
      }
      /**
       * Hides the disabled container when there are no layout-paragraphs items.
       * @param {jQuery} $container The disabled items jQuery container.
       */
      function updateDisabled($container) {
        if (
          $container.find(
            ".layout-paragraphs-disabled-items .layout-paragraphs-item"
          ).length > 0
        ) {
          $container
            .find(".layout-paragraphs-disabled-items__description")
            .hide();
        } else {
          $container
            .find(".layout-paragraphs-disabled-items__description")
            .show();
        }
      }
      /**
       * Moves an layout-paragraphs item up.
       * @param {event} e DOM Event (i.e. click).
       * @return {bool} Returns false if state is still loading.
       */
      function moveUp(e) {
        const $btn = $(e.currentTarget);
        const $item = $btn.parents(".layout-paragraphs-item:first");
        const $container = $item.parent();

        if (isLoading($item)) {
          return false;
        }

        // We're first, jump up to next available region.
        if ($item.prev(".layout-paragraphs-item").length === 0) {
          // Previous region, same layout.
          if ($container.prev(".layout-paragraphs-layout-region").length) {
            $container.prev(".layout-paragraphs-layout-region").append($item);
          }
          // Otherwise jump to last region in previous layout.
          else if (
            $container
              .closest(".layout-paragraphs-layout")
              .prev()
              .find(".layout-paragraphs-layout-region:last-child").length
          ) {
            $container
              .closest(".layout-paragraphs-layout")
              .prev()
              .find(
                ".layout-paragraphs-layout-region:last-child .layout-paragraphs-add-content__container"
              )
              .before($item);
          }
        } else {
          $item.after($item.prev());
        }
        updateFields($container.closest(".layout-paragraphs-field"));
      }
      /**
       * Moves an layout-paragraphs item down.
       * @param {event} e DOM Event (i.e. click).
       * @return {bool} Returns false if state is still loading.
       */
      function moveDown(e) {
        const $btn = $(e.currentTarget);
        const $item = $btn.parents(".layout-paragraphs-item:first");
        const $container = $item.parent();

        if (isLoading($item)) {
          return false;
        }

        // We're first, jump down to next available region.
        if ($item.next(".layout-paragraphs-item").length === 0) {
          // Next region, same layout.
          if ($container.next(".layout-paragraphs-layout-region").length) {
            $container.next(".layout-paragraphs-layout-region").prepend($item);
          }
          // Otherwise jump to first region in next layout.
          else if (
            $container
              .closest(".layout-paragraphs-layout")
              .next()
              .find(".layout-paragraphs-layout-region:first-child").length
          ) {
            $container
              .closest(".layout-paragraphs-layout")
              .next()
              .find(
                ".layout-paragraphs-layout-region:first-child .layout-paragraphs-add-content__container"
              )
              .before($item);
          }
        } else {
          $item.before($item.next());
        }
        updateFields($container.closest(".layout-paragraphs-field"));
      }
      /**
       * Initiates dragula drag/drop functionality.
       * @param {object} item ERL field item to attach drag/drop behavior to.
       */
      function dragulaBehaviors(layoutParagraphField) {
        $(layoutParagraphField).addClass("dragula-enabled");
        // Turn on drag and drop if dragula function exists.
        if (typeof dragula !== "undefined") {
          const items = $(
            ".active-items, .layout-paragraphs-layout-region, .layout-paragraphs-disabled-items__items",
            layoutParagraphField
          ).not(".dragula-enabled").addClass("dragula-enabled").get();

          // Dragula is already initialized, add any new containers that may have been added.
          if ($(layoutParagraphField).data('drake')) {
            for (var i in items) {
              if ($(layoutParagraphField).data('drake').containers.indexOf(items[i]) === -1) {
                $(layoutParagraphField).data('drake').containers.push(items[i]);
              }
            }
            return;
          }

          const drake = dragula(items, {
            moves(el, container, handle) {
              return handle.className.toString().indexOf("layout-handle") >= 0;
            },
            accepts(el, target, source, sibling) {
              if (settings.paragraphsLayoutWidget.requireLayouts) {
                if (
                  !$(el).is(".layout-paragraphs-layout") &&
                  !$(target).parents(".layout-paragraphs-layout").length &&
                  !$(target).parents(".layout-paragraphs-disabled-items").length
                ) {
                  return false;
                }
              }
              if (settings.paragraphsLayoutWidget.maxDepth) {
                if (
                  $(el).is(".layout-paragraphs-layout") &&
                  $(target).parents(".layout-paragraphs-layout").length >
                    settings.paragraphsLayoutWidget.maxDepth
                ) {
                  return false;
                }
              }
              if (
                $(target).parents(".layout-paragraphs-disabled-items").length
              ) {
                if (
                  $(sibling).is(
                    ".layout-paragraphs-disabled-items__description"
                  )
                ) {
                  return false;
                }
              }
              return true;
            }
          });
          drake.on("drop", el => {
            updateFields($(el).closest(".layout-paragraphs-field"));
            updateDisabled($(el).closest(".layout-paragraphs-field"));
          });
          $(layoutParagraphField).data('drake', drake);
        }
      }
      /**
       * Closes the "add paragraph item" menu.
       * @param {jQuery} $btn The clicked button.
       */
      function closeAddItemMenu($btn) {
        const $widget = $btn.parents(".layout-paragraphs-field");
        const $menu = $widget.find(".layout-paragraphs-add-more-menu");
        $menu.addClass("hidden").removeClass("fade-in");
        $btn.removeClass("active");
      }
      /**
       * Responds to click outside of the menu.
       * @param {event} e DOM event (i.e. click)
       */
      function handleClickOutsideMenu(e) {
        if (
          $(e.target).closest(".layout-paragraphs-add-more-menu").length === 0
        ) {
          const $btn = $(".layout-paragraphs-add-content__toggle.active");
          if ($btn.length) {
            closeAddItemMenu($btn);
            window.removeEventListener("click", handleClickOutsideMenu);
          }
        }
      }
      /**
       * Position the menu correctly.
       * @param {jQuery} $menu The menu jQuery DOM object.
       * @param {bool} keepOrientation If true, the menu will stay above/below no matter what.
       */
      function positionMenu($menu, keepOrientation) {
        const $btn = $menu.data("activeButton");
        // Move the menu to correct spot.
        const btnOffset = $btn.offset();
        const menuOffset = $menu.offset();
        const viewportTop = $(window).scrollTop();
        const viewportBottom = viewportTop + $(window).height();
        const menuWidth = $menu.outerWidth();
        const btnWidth = $btn.outerWidth();
        const btnHeight = $btn.height();
        const menuHeight = $menu.outerHeight();
        // Account for rotation with slight padding.
        const left =
          7 + Math.floor(btnOffset.left + btnWidth / 2 - menuWidth / 2);

        // Default to positioning the menu beneath the button.
        let orientation = "beneath";
        let top = Math.floor(btnOffset.top + btnHeight + 15);

        // The menu is above the button, keep it that way.
        if (keepOrientation === true && menuOffset.top < btnOffset.top) {
          orientation = "above";
        }
        // The menu would go out of the viewport, so keep at top.
        if (top + menuHeight > viewportBottom) {
          orientation = "above";
        }
        $menu
          .removeClass("above")
          .removeClass("beneath")
          .addClass(orientation);
        if (orientation === "above") {
          top = Math.floor(btnOffset.top - 5 - menuHeight);
        }

        $menu.removeClass("hidden").addClass("fade-in");
        $menu.offset({ top, left });
      }
      /**
       * Opens the "add pragraph item" menu.
       * @param {jQuery} $btn The button clicked to open the menu.
       */
      function openAddItemMenu($btn) {
        const $widget = $btn.parents(".layout-paragraphs-field");
        const $regionInput = $widget.find(".layout-paragraphs-new-item-region");
        const $parentWeightInput = $widget.find(
          ".layout-paragraphs-new-item-weight"
        );
        const $parentUuidInput = $widget.find(
          ".layout-paragraphs-new-item-parent-uuid"
        );
        const parentUuidSelector = '.' + $btn.attr('data-parent-uuid-class');
        const $menu = $widget.find(".layout-paragraphs-add-more-menu");
        const region = getRegion(
          $btn.closest(".layout-paragraphs-layout-region")
        );
        const depth = region
          ? $btn.parents(".layout-paragraphs-layout").length
          : 0;
        const parentUuid = parentUuidSelector
          ? $btn
              .closest('.layout-paragraphs-item')
              .find(parentUuidSelector)
              .val()
          : "";
        const parentWeight =
          0.5 +
          Number(
            $btn
              .closest(".layout-paragraphs-item")
              .find(".layout-paragraphs-weight")
              .val() || -1
          );
        // Hide layout items if we're already at max depth.
        if (depth > settings.paragraphsLayoutWidget.maxDepth) {
          $menu.find(".layout-paragraph").addClass("hidden");
        } else {
          $menu.find(".layout-paragraph").removeClass("hidden");
        }
        // Hide non-layout items if we're at zero depth and layouts are requried.
        if (settings.paragraphsLayoutWidget.requireLayouts && depth === 0) {
          $menu
            .find(
              ".layout-paragraphs-add-more-menu__item:not(.layout-paragraph)"
            )
            .addClass("hidden");
        } else {
          $menu
            .find(
              ".layout-paragraphs-add-more-menu__item:not(.layout-paragraph)"
            )
            .removeClass("hidden");
        }
        // Hide search if fewer than 7 visible items.
        if (
          $menu.find(".layout-paragraphs-add-more-menu__item:not(.hidden)")
            .length < 7
        ) {
          $menu
            .find(".layout-paragraphs-add-more-menu__search")
            .addClass("hidden");
        } else {
          $menu
            .find(".layout-paragraphs-add-more-menu__search")
            .removeClass("hidden");
        }
        if (
          !$menu
            .find(".layout-paragraphs-add-more-menu__search")
            .hasClass("hidden")
        ) {
          $menu
            .find('.layout-paragraphs-add-more-menu__search input[type="text"]')
            .focus();
        }
        $menu.data("activeButton", $btn);
        // Make other buttons inactive.
        $widget
          .find("button.layout-paragraphs-add-content__toggle")
          .removeClass("active");
        // Hide the menu, for transition effect.
        $menu.addClass("hidden").removeClass("fade-in");
        $menu.find('input[type="text"]').val("");
        $menu.find(".layout-paragraphs-add-more-menu__item").attr("style", "");
        $btn.addClass("active");

        // Sets the values in the form items
        // for where a new item should be inserted.
        $regionInput.val(region);
        $parentWeightInput.val(parentWeight);
        $parentUuidInput.val(parentUuid);
        // console.log(region);
        // console.log(parentWeight);
        // console.log(parentUuid);
        setTimeout(() => {
          positionMenu($menu);
        }, 100);
        window.addEventListener("click", handleClickOutsideMenu);
      }
      /**
       * Enhances the radio button select for choosing a layout.
       * @param {Object} layoutList The list of layout items.
       */
      function enhanceRadioSelect(layoutList) {
        const $layoutRadioItem = $(".layout-select--list-item", layoutList);
        $layoutRadioItem.click(e => {
          const $radioItem = $(e.currentTarget);
          const $layoutParagraphsField = $radioItem.closest(
            ".layout-paragraphs-field"
          );
          if (isLoading($layoutParagraphsField)) {
            return false;
          }
          setLoading($radioItem.closest(".ui-dialog"));
          $radioItem
            .find("input[type=radio]")
            .prop("checked", true)
            .trigger("change");
          $radioItem.siblings().removeClass("active");
          $radioItem.addClass("active");
        });
        $layoutRadioItem.each((radioIndex, radioItem) => {
          const $radioItem = $(radioItem);
          if ($radioItem.find("input[type=radio]").prop("checked")) {
            $radioItem.addClass("active");
          }
        });
      }
      /**
       * Set state to "loading" on layout-paragraphs field when action buttons are press.
       */
      $('.layout-paragraphs-actions input[type="submit"]')
        .once("layout-paragraphs-actions-loaders")
        .each((index, btn) => {
          $(btn).on("mousedown", e => {
            if (isLoading($(btn).closest(".layout-paragraphs-field"))) {
              e.stopImmediatePropagation();
              return false;
            }
            setLoading($(e.currentTarget).closest(".layout-paragraphs-item"));
          });
          // Ensure our listener happens first.
          $._data(btn, "events").mousedown.reverse();
        });
      /**
       * Click handler for "add paragraph item" toggle buttons.
       */
      $("button.layout-paragraphs-add-content__toggle")
        .once("layout-paragraphs-add-content-toggle")
        .click(e => {
          const $btn = $(e.target);
          if ($btn.hasClass("active")) {
            closeAddItemMenu($btn);
          } else {
            openAddItemMenu($btn);
          }
          return false;
        });
      /**
       * Click handlers for adding new paragraph items.
       */
      $(".layout-paragraphs-add-more-menu__item a", context)
        .once("layout-paragraphs-add-more-menu-buttons")
        .click(e => {
          const $btn = $(e.currentTarget);
          const $widget = $btn.closest(".layout-paragraphs-field");
          const $menu = $btn.closest(".layout-paragraphs-add-more-menu");
          const $select = $widget.find("select.layout-paragraphs-item-type");
          const $submit = $widget.find(
            'input[type="submit"].layout-paragraphs-add-item'
          );
          const type = $btn.attr("data-type");
          if (isLoading($widget)) {
            return false;
          }
          $select.val(type);
          $submit.trigger("mousedown").trigger("click");
          setLoading($menu);
          return false;
        });
      /**
       * Search behavior for search box on "add paragraph item" menu.
       */
      $(".layout-paragraphs-add-more-menu__search", context)
        .once("layout-paragraphs-search-input")
        .each((index, searchContainer) => {
          const $searchContainer = $(searchContainer);
          const $searchInput = $searchContainer.find('input[type="text"]');
          const $menu = $searchContainer.closest(
            ".layout-paragraphs-add-more-menu"
          );
          const $searchItems = $menu.find(
            ".layout-paragraphs-add-more-menu__item:not(.hidden)"
          );

          // Search query
          $searchInput.on("keyup", ev => {
            const text = ev.target.value;
            const pattern = new RegExp(text, "i");
            for (let i = 0; i < $searchItems.length; i++) {
              const item = $searchItems[i];
              if (pattern.test(item.innerText)) {
                item.removeAttribute("style");
              } else {
                item.style.display = "none";
              }
            }
            positionMenu($menu, true);
          });
        });
      /**
       * Click handlers for "Add New Section" buttons.
       */
      $(".layout-paragraphs-field", context)
        .once("layout-paragraphs-add-section")
        .each((index, layoutParagraphsField) => {
          const $widgetContainer = $(layoutParagraphsField);
          const $submitButton = $widgetContainer.find(
            "input.layout-paragraphs-add-section"
          );
          const $regionInput = $widgetContainer.find(
            ".layout-paragraphs-new-item-region"
          );
          const $parentInput = $widgetContainer.find(
            ".layout-paragraphs-new-item-parent"
          );

          $(
            "button.layout-paragraphs-add-section",
            layoutParagraphsField
          ).click(e => {
            if (isLoading($widgetContainer)) {
              return false;
            }
            const $btn = $(e.currentTarget);
            const parent = $btn
              .closest(".layout-paragraphs-layout")
              .find(".layout-paragraphs-weight")
              .val();
            $parentInput.val(parent);
            // Sections don't go in regions.
            $regionInput.val("");
            $submitButton.trigger("mousedown").trigger("click");
            setLoading($btn.parent());
            return false;
          });
        });
      /**
       * Add drag/drop/move controls.
       */
      $(".layout-paragraphs-item", context)
        .once("layout-paragraphs-controls")
        .each((layoutParagraphsItemIndex, layoutParagraphsItem) => {
          $('<div class="layout-controls">')
            .append($('<div class="layout-handle">'))
            .append($('<div class="layout-up">').click(moveUp))
            .append($('<div class="layout-down">').click(moveDown))
            .prependTo(layoutParagraphsItem);
        });
      /**
       * Enhance radio buttons.
       */
      $(".layout-select--list", context)
        .once("layout-select-enhance-radios")
        .each((index, layoutList) => {
          enhanceRadioSelect(layoutList);
        });
      /**
       * Drag and drop with dragula.
       * Runs every time DOM is updated.
       */
      $(".layout-paragraphs-field", context)
        .each((index, item) => {
          const checkDragulaInterval = setInterval(() => {
            if (typeof dragula !== "undefined") {
              clearInterval(checkDragulaInterval);
              dragulaBehaviors(item);
            }
          }, 50);
        });
      /**
       * Only show disabled items if there are items in the field.
       * Runs every time DOM is updated.
       */
      $(".layout-paragraphs-field", context)
        .each((index, field) => {
          if ($('.layout-paragraphs-item', field).length == 0) {
            $('.layout-paragraphs-disabled-items', field).hide();
          }
          else {
            $('.layout-paragraphs-disabled-items', field).show();
          }
        });
      /**
       * Update weights, regions, and disabled area on load.
       * Runs every time DOM is updated.
       */
      $(".layout-paragraphs-field", context)
        .each((index, item) => {
          updateFields($(item));
          updateDisabled($(item));
        });
      /**
       * Dialog close buttons should trigger the "Cancel" action.
       */
      $(".layout-paragraphs-dialog .ui-dialog-titlebar-close", context).mousedown(e => {
        $(e.target)
          .closest(".layout-paragraphs-dialog")
          .find(".layout-paragraphs-cancel")
          .trigger("mousedown")
          .trigger("click");
        return false;
      });
    }
  };
})(jQuery, Drupal);
