<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$frontend = file_get_contents($root . '/includes/class-hp-cs-frontend.php') ?: '';
$admin = file_get_contents($root . '/includes/class-hp-cs-admin.php') ?: '';
$settings = file_get_contents($root . '/includes/admin/views/settings.php') ?: '';

foreach ([
    "add_filter( 'hp_checkout_shipping_rates_result'" => 'Conditional shipping must hook HP Checkout shipping-rate results.',
    'filter_hp_checkout_shipping_rates' => 'Conditional shipping must expose a dedicated HP Checkout shipping filter callback.',
    "evaluate_rates( \$rates, \$package, 'hp_checkout' )" => 'HP Checkout shipping rates must be evaluated with an HP Checkout surface.',
    "in_array( \$surface, [ 'funnel', 'hp_checkout' ], true )" => 'Array rate matching must support HP Checkout rate arrays.',
    "\$rate['service_key']" => 'HP Checkout rate matching must prefer carrier-qualified service identities.',
    "strpos( \$carrier, 'fedex' )" => 'HP Checkout rate matching must normalize FedEx carrier aliases.',
    "discount_funnel_rate( \$rate, \$discount )" => 'HP Checkout array rates must use the array-rate discount path.',
    "'amount', 'cost', 'rate'" => 'HP Checkout array-rate discounts must support common raw amount fields.',
    "\$item['line_total']" => 'HP Checkout shipping discount packages must preserve HP-owned item line totals when provided.',
] as $needle => $message) {
    if (!str_contains($frontend, $needle)) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

foreach ([
    "'hp_checkout'" => 'Admin sanitization must allow the HP Checkout shipping discount surface.',
] as $needle => $message) {
    if (!str_contains($admin, $needle)) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

foreach ([
    'HP Checkout' => 'Settings UI must expose an HP Checkout shipping discount surface.',
] as $needle => $message) {
    if (!str_contains($settings, $needle)) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

echo "HP Checkout conditional shipping filter contract passed.\n";
