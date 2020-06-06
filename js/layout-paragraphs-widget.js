(($, Drupal, dragula) => {
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
        let delta = -1;
        $container
          .find(".layout-paragraphs-weight, .layout-paragraphs-new-item-delta")
          .each((index, item) => {
            if ($(item).hasClass("layout-paragraphs-weight")) {
              delta += 1;
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
      function dragulaBehaviors(item) {
        $(item).addClass("dragula-enabled");
        // Turn on drag and drop if dragula function exists.
        if (typeof dragula !== "undefined") {
          // Add layout handles.
          $(".layout-paragraphs-item").each(
            (layoutParagraphsItemIndex, layoutParagraphsItem) => {
              $('<div class="layout-controls">')
                .append($('<div class="layout-handle">'))
                .append($('<div class="layout-up">').click(moveUp))
                .append($('<div class="layout-down">').click(moveDown))
                .prependTo(layoutParagraphsItem);
            }
          );
          const items = $(
            ".active-items, .layout-paragraphs-layout-wrapper, .layout-paragraphs-layout-region, .layout-paragraphs-disabled-items__items",
            item
          ).get();
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
        const $menu = $widget.find(".layout-paragraphs-add-more-menu");
        const region = getRegion(
          $btn.closest(".layout-paragraphs-layout-region")
        );
        const depth = region
          ? $btn.parents(".layout-paragraphs-layout").length
          : 0;
        const parentUuid = region
          ? $btn
              .closest(".layout-paragraphs-layout")
              .find(".layout-paragraphs-uuid")
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
        setTimeout(() => {
          positionMenu($menu);
        }, 100);
        window.addEventListener("click", handleClickOutsideMenu);
      }
      /**
       * Enhances the radio button select for choosing a layout.
       */
      function enhanceRadioSelect() {
        const $layoutRadioItem = $(".layout-select--list-item");
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
       * Click hanlders for "Add New Section" buttons.
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
       * Load entity form in dialog.
       */
      $(".layout-paragraphs-field .layout-paragraphs-form", context)
        .once("layout-paragraphs-dialog")
        .each((index, layoutParagraphsForm) => {
          const buttons = [];
          const $layoutParagraphsForm = $(layoutParagraphsForm);
          $(
            '.layout-paragraphs-item-form-actions input[type="submit"]',
            layoutParagraphsForm
          ).each((btnIndex, btn) => {
            buttons.push({
              text: btn.value,
              class: btn.className,
              click() {
                if (
                  isLoading(
                    $layoutParagraphsForm.closest(".layout-paragraphs-field")
                  )
                ) {
                  return false;
                }
                setLoading($layoutParagraphsForm.closest(".ui-dialog"));
                $(btn)
                  .trigger("mousedown")
                  .trigger("click");
              }
            });
            btn.style.display = "none";
          });
          const dialogConfig = {
            width: "800px",
            title: $layoutParagraphsForm
              .find("[data-dialog-title]")
              .attr("data-dialog-title"),
            maxHeight: Math.max(400, $(window).height() * 0.8),
            minHeight: Math.min($layoutParagraphsForm.outerHeight(), 400),
            appendTo: $(".layout-paragraphs-form").parent(),
            draggable: true,
            autoResize: true,
            modal: true,
            buttons,
            open() {
              enhanceRadioSelect();
            },
            beforeClose(event) {
              if (
                isLoading($(event.target).closest(".layout-paragraphs-field"))
              ) {
                return false;
              }
              setLoading($(event.target).closest(".ui-dialog"));
              $(event.target)
                .find(".layout-paragraphs-cancel")
                .trigger("mousedown")
                .trigger("click");
              return false;
            }
          };
          $layoutParagraphsForm.dialog(dialogConfig);
        });

      /**
       * Drag and drop with dragula.
       */
      $(".layout-paragraphs-field", context)
        .once("layout-paragraphs-drag-drop")
        .each((index, item) => {
          const checkDragulaInterval = setInterval(() => {
            if (typeof dragula !== "undefined") {
              clearInterval(checkDragulaInterval);
              dragulaBehaviors(item);
            }
          }, 50);
        });

      $(".layout-paragraphs-field", context)
        .once("layout-paragraphs-drag-drop")
        .each((index, item) => {});
      /**
       * Update weights, regions, and disabled area on load.
       */
      $(".layout-paragraphs-field", context)
        .once("layout-paragraphs-update-fields")
        .each((index, item) => {
          updateFields($(item));
          updateDisabled($(item));
        });
    }
  };
})(jQuery, Drupal, dragula);
