// Browser test script for conditional shipping rules
// Run this in browser console on checkout page

async function testRule(ruleConfig) {
  const $ = window.jQuery;
  const form = document.querySelector('form.checkout');
  if (!form) return { ok: false, err: 'no_form' };

  const setField = (name, value) => {
    const els = form.querySelectorAll(`[name="${name}"]`);
    els.forEach((el) => {
      el.value = value;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    return els.length;
  };

  // Set address
  if (ruleConfig.address) {
    setField('shipping_country', ruleConfig.address.country);
    setField('billing_country', ruleConfig.address.country);
    await new Promise(r => setTimeout(r, 500));
    
    if (ruleConfig.address.state) setField('shipping_state', ruleConfig.address.state);
    if (ruleConfig.address.postcode) setField('shipping_postcode', ruleConfig.address.postcode);
    if (ruleConfig.address.city) setField('shipping_city', ruleConfig.address.city);
    if (ruleConfig.address.address1) setField('shipping_address_1', ruleConfig.address.address1);
  }

  // Trigger checkout refresh
  const post_data = $(form).serialize();
  const resp = await $.ajax({
    type: 'POST',
    url: '/?wc-ajax=update_order_review',
    data: { security: window.wc_checkout_params?.update_order_review_nonce || '', ...post_data },
    dataType: 'json'
  });

  await new Promise(r => setTimeout(r, 1000));

  // Check shipping methods
  const shippingMethods = Array.from(document.querySelectorAll('#shipping_method input[type="radio"], .wc-block-components-radio-group input[type="radio"], .woocommerce-shipping-methods input[type="radio"]'));
  const shippingLabels = Array.from(document.querySelectorAll('#shipping_method label, .wc-block-components-radio-group label, .woocommerce-shipping-methods label')).map(l => l.textContent.trim());

  // Check for notices/messages
  const notices = Array.from(document.querySelectorAll('.woocommerce-error, .woocommerce-info, .wc-block-components-notice-banner, .cfw-alert, [class*="notice"], [class*="error"]'))
    .map(el => el.textContent.trim()).filter(t => t && t.length < 500); // Filter out very long content

  return {
    ok: true,
    ruleId: ruleConfig.id,
    address: ruleConfig.address,
    shippingMethodsCount: shippingMethods.length,
    shippingLabels,
    notices,
    hasRates: resp.rates ? Object.keys(resp.rates).length > 0 : false,
    ratesCount: resp.rates ? Object.keys(resp.rates).length : 0
  };
}

