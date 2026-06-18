<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['hp_cs_test_options'] = [
    'hp_cs_shipping_discount_rules' => [
        [
            'enabled' => 'yes',
            'label' => 'Regular HP product',
            'shipping_class' => '',
            'min_amount' => 50,
            'percentage_discount' => 6,
            'surface' => 'classic',
            'shipping_method_ids' => ['_all'],
            'order' => 0,
        ],
    ],
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

// Minimal WooCommerce stubs sufficient for the order-based discount estimator.
class WC_Product {
    private string $shipping_class;
    public function __construct(string $shipping_class = '') {
        $this->shipping_class = $shipping_class;
    }
    public function get_shipping_class(): string {
        return $this->shipping_class;
    }
}

class WC_Order_Item_Product {
    private WC_Product $product;
    private float $total;
    public function __construct(WC_Product $product, float $total) {
        $this->product = $product;
        $this->total = $total;
    }
    public function get_product(): WC_Product {
        return $this->product;
    }
    public function get_total(): float {
        return $this->total;
    }
}

class WC_Order {
    /** @var array<int, object> */
    private array $items;
    public function __construct(array $items) {
        $this->items = $items;
    }
    public function get_items(): array {
        return $this->items;
    }
}

require dirname(__DIR__) . '/includes/hp-cs-utils.php';

function hp_cs_test_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

// A 'classic' rule must apply when emulating the 'hp_checkout' surface
// (matching storefront behavior where classic/funnel fold into hp_checkout).
$rule = hp_cs_normalize_discount_rule($GLOBALS['hp_cs_test_options']['hp_cs_shipping_discount_rules'][0]);
hp_cs_test_assert(
    hp_cs_discount_rule_matches_surface($rule, 'hp_checkout') === true,
    "A classic-surface rule must match when emulating hp_checkout."
);

// Eligible order ($100 >= $50 min): 6% of $100 = $6.00 discount.
$eligibleOrder = new WC_Order([
    new WC_Order_Item_Product(new WC_Product(''), 60.0),
    new WC_Order_Item_Product(new WC_Product(''), 40.0),
]);
$discount = hp_cs_calculate_order_shipping_discount($eligibleOrder, 'UPS Ground Saver', 'hp_checkout');
hp_cs_test_assert(
    $discount === 6.0,
    'Eligible order should receive 6% standard shipping discount ($6.00); got $' . $discount . '.'
);

// Below-threshold order ($40 < $50 min) earns no discount.
$smallOrder = new WC_Order([
    new WC_Order_Item_Product(new WC_Product(''), 40.0),
]);
hp_cs_test_assert(
    hp_cs_calculate_order_shipping_discount($smallOrder, 'UPS Ground Saver', 'hp_checkout') === 0.0,
    'Order below the rule minimum must receive no standard shipping discount.'
);

// Disabled rules never contribute.
$GLOBALS['hp_cs_test_options']['hp_cs_shipping_discount_rules'][0]['enabled'] = 'no';
hp_cs_test_assert(
    hp_cs_calculate_order_shipping_discount($eligibleOrder, 'UPS Ground Saver', 'hp_checkout') === 0.0,
    'A disabled discount rule must not contribute to the standard shipping discount.'
);

echo "order shipping discount contract passed.\n";
