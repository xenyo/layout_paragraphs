(($, Drupal) => {
  // Updates the "Close" button label when a layout is changed.
  Drupal.behaviors.layoutParagraphsBuilderForm = {
    attach: function attach(context) {
      const events = [
        'lpb-component:insert.lpb',
        'lpb-component:update.lpb',
        'lpb-component:move.lpb',
        'lpb-component:drop.lpb',
      ].join(' ');
      $('[data-lpb-id]', context)
        .once('lpb-builder-form')
        .on(events, e => {
          $(e.currentTarget)
            .closest('[data-lpb-form-id]')
            .find('[data-drupal-selector="edit-close"]')
            .val(Drupal.t('Cancel'));
        });
    },
  };
})(jQuery, Drupal);
