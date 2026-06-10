<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['hp_cs_test_options'] = [
    'hp_cs_wc_shipping_discount_imported' => 'yes',
    'woocommerce_wc-shipping-discount_settings' => [
        'enabled' => 'yes',
        'rules' => [
            'regular-hp-product' => [
                'enabled' => 'yes',
                'min_amount' => 1,
                'percentage_discount' => 6,
            ],
        ],
    ],
    'hp_cs_shipping_discount_rules' => [
        [
            'enabled' => 'no',
            'label' => 'Imported: regular-hp-product',
            'shipping_class' => 'regular-hp-product',
            'min_amount' => 1,
            'percentage_discount' => 6,
            'surface' => 'classic',
            'shipping_method_ids' => ['_all'],
            'order' => 0,
        ],
    ],
    'hp_cs_ruleset_version' => 1,
];

function get_option($key, $default = false) {
    return array_key_exists($key, $GLOBALS['hp_cs_test_options']) ? $GLOBALS['hp_cs_test_options'][$key] : $default;
}

function update_option($key, $value, $autoload = null) {
    unset($autoload);
    $GLOBALS['hp_cs_test_options'][$key] = $value;
    return true;
}

function sanitize_title($value): string {
    $value = strtolower(trim((string) $value));
    return preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
}

require dirname(__DIR__) . '/includes/hp-cs-utils.php';

hp_cs_maybe_import_wc_shipping_discount_rules();

$rules = get_option('hp_cs_shipping_discount_rules', []);
if (($rules[0]['enabled'] ?? '') !== 'yes') {
    fwrite(STDERR, "Imported HP shipping discount rules should resync enabled=yes from the legacy wc-shipping-discount setting.\n");
    exit(1);
}

if ((float) ($rules[0]['percentage_discount'] ?? 0) !== 6.0 || (float) ($rules[0]['min_amount'] ?? 0) !== 1.0) {
    fwrite(STDERR, "Imported HP shipping discount rules should resync percentage and minimum amount from the legacy setting.\n");
    exit(1);
}

if ((int) get_option('hp_cs_ruleset_version', 1) <= 1) {
    fwrite(STDERR, "Resyncing imported shipping discount rules should bump the ruleset cache version.\n");
    exit(1);
}

echo "shipping discount import sync contract passed.\n";
