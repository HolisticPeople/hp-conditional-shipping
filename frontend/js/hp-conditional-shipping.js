jQuery(function ($) {
  function injectNotices() {
    const noticesEl = $('#wcs-notices-pending');
    if (noticesEl.length === 0) return;

    $('#wcs-notices').remove();
    const shippingMethods = $('.woocommerce-shipping-totals ul.woocommerce-shipping-methods');
    if (shippingMethods.length > 0) {
      shippingMethods.after(noticesEl);
      noticesEl.css('display', 'block').attr('id', 'wcs-notices');
    }
  }

  $(document.body).on('updated_checkout', injectNotices);
  $(document.body).on('updated_cart_totals', injectNotices);
  injectNotices();
});


