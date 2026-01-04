jQuery(function ($) {
  // Enable toggle
  $(document).on('click', '[data-hp-cs-toggle="1"]', function () {
    const $el = $(this);
    const id = $el.data('id');

    $.post(hp_cs_admin.ajax_url, {
      action: 'hp_cs_toggle_ruleset',
      security: hp_cs_admin.nonce,
      id: id,
    }).done(function (resp) {
      if (!resp || !resp.success) return;
      const enabled = !!resp.data.enabled;
      $el.toggleClass('woocommerce-input-toggle--enabled', enabled);
      $el.toggleClass('woocommerce-input-toggle--disabled', !enabled);
    });
  });

  // Drag/drop ordering
  const $tbody = $('.hp-cs-ruleset-rows');
  if ($tbody.length) {
    $tbody.sortable({
      items: 'tr',
      handle: '.hp-cs-sort',
      axis: 'y',
    });
  }
});


