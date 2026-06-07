<?php

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $value ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', trim( (string) $value ) ) );
	}
}

if ( ! function_exists( 'wc_clean' ) ) {
	function wc_clean( $value ) {
		return is_array( $value ) ? array_map( 'wc_clean', $value ) : trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wc_format_decimal' ) ) {
	function wc_format_decimal( $value ) {
		return preg_replace( '/[^0-9.]/', '', (string) $value );
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		private int $id;
		private int $parent_id;
		private bool $variation;

		public function __construct( int $id, int $parent_id = 0, bool $variation = false ) {
			$this->id        = $id;
			$this->parent_id = $parent_id;
			$this->variation = $variation;
		}

		public function get_id() {
			return $this->id;
		}

		public function get_parent_id() {
			return $this->parent_id;
		}

		public function is_type( $type ) {
			return $type === 'variation' && $this->variation;
		}
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $id ) {
		$products = [
			12 => new WC_Product( 12 ),
			34 => new WC_Product( 34, 12, true ),
		];

		return $products[ (int) $id ] ?? false;
	}
}

if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
	function wc_get_product_id_by_sku( $sku ) {
		return $sku === 'SKU-12' ? 12 : 0;
	}
}

if ( ! function_exists( 'wc_get_price_excluding_tax' ) ) {
	function wc_get_price_excluding_tax( $product, $args = [] ) {
		return 10 * (int) ( $args['qty'] ?? 1 );
	}
}

if ( ! class_exists( 'HP_CS_Test_Customer' ) ) {
	class HP_CS_Test_Customer {
		public array $props = [];

		public function set_props( array $props ) {
			$this->props = array_merge( $this->props, $props );
		}
	}
}

if ( ! function_exists( 'WC' ) ) {
	function WC() {
		static $wc = null;
		if ( $wc === null ) {
			$wc = (object) [ 'customer' => new HP_CS_Test_Customer() ];
		}

		return $wc;
	}
}

require_once __DIR__ . '/../includes/hp-cs-utils.php';
require_once __DIR__ . '/../includes/class-hp-cs-admin.php';
require_once __DIR__ . '/../includes/class-hp-cs-frontend.php';

$admin           = ( new ReflectionClass( HP_CS_Admin::class ) )->newInstanceWithoutConstructor();
$id_method       = new ReflectionMethod( HP_CS_Admin::class, 'parse_positive_decimal_id' );
$json_method     = new ReflectionMethod( HP_CS_Admin::class, 'decode_ruleset_json_array' );
$discount_method = new ReflectionMethod( HP_CS_Admin::class, 'sanitize_discount_rules' );
$id_method->setAccessible( true );
$json_method->setAccessible( true );
$discount_method->setAccessible( true );

$cases = [
	'plain positive integer' => [ '123', 123 ],
	'leading zero text'     => [ '00123', 0 ],
	'trailing text'         => [ '123abc', 0 ],
	'leading text'          => [ 'abc123', 0 ],
	'negative text'         => [ '-123', 0 ],
	'zero text'             => [ '0', 0 ],
	'empty text'            => [ '', 0 ],
	'array value'           => [ [ '123' ], 0 ],
	'max integer text'      => [ (string) PHP_INT_MAX, PHP_INT_MAX ],
	'overflow integer text' => [ (string) PHP_INT_MAX . '0', 0 ],
];

foreach ( $cases as $label => [ $input, $expected ] ) {
	$actual = $id_method->invoke( $admin, $input );
	if ( $actual !== $expected ) {
		fwrite(
			STDERR,
			sprintf(
				"%s failed: expected %s, got %s\n",
				$label,
				var_export( $expected, true ),
				var_export( $actual, true )
			)
		);
		exit( 1 );
	}
}

$json_cases = [
	'valid conditions json' => [
		json_encode( [ [ 'type' => 'shipping_country', 'operator' => 'is', 'countries' => [ 'US' ] ] ] ),
		[ [ 'type' => 'shipping_country', 'operator' => 'is', 'countries' => [ 'US' ] ] ],
	],
	'invalid json' => [ '{"type":', [] ],
	'scalar json'  => [ '"text"', [] ],
	'overlong json' => [
		'[' . str_repeat( '{"type":"shipping_country","operator":"is","countries":["US"]},', 1200 ) . '{}]',
		[],
	],
];

foreach ( $json_cases as $label => [ $input, $expected ] ) {
	$actual = $json_method->invoke( $admin, $input );
	if ( $actual !== $expected ) {
		fwrite(
			STDERR,
			sprintf(
				"%s failed: expected %s, got %s\n",
				$label,
				var_export( $expected, true ),
				var_export( $actual, true )
			)
		);
		exit( 1 );
	}
}

$discount_rules = $discount_method->invoke(
	$admin,
	[
		[
			'enabled'                    => '1',
			'label'                      => str_repeat( 'L', 250 ),
			'shipping_class'             => [ 'freight' ],
			'percentage_discount'        => '10abc',
			'min_amount'                 => '0010.50',
			'surface'                    => 'funnel bad',
			'shipping_method_ids'        => [ '_all', 'flat_rate:3', [ 'nested' ], str_repeat( 'x', 201 ), 'bad id!' ],
			'shipping_method_name_match' => str_repeat( 'N', 2500 ),
		],
		[
			'label'               => 'Valid',
			'percentage_discount' => '105',
			'min_amount'          => '10.5000',
			'surface'             => 'both',
		],
	]
);

$discount_expectations = [
	'discount rule count'           => [ count( $discount_rules ), 2 ],
	'discount label bounded'        => [ strlen( $discount_rules[0]['label'] ), HP_CS_ADMIN_TEXT_REQUEST_MAX_LENGTH ],
	'array shipping class rejected' => [ $discount_rules[0]['shipping_class'], '' ],
	'malformed percent rejected'    => [ $discount_rules[0]['percentage_discount'], 0.0 ],
	'leading zero amount rejected'  => [ $discount_rules[0]['min_amount'], 0.0 ],
	'invalid surface defaulted'     => [ $discount_rules[0]['surface'], 'classic' ],
	'method IDs filtered'           => [ $discount_rules[0]['shipping_method_ids'], [ '_all', 'flat_rate:3' ] ],
	'textarea bounded'              => [ strlen( $discount_rules[0]['shipping_method_name_match'] ), HP_CS_ADMIN_TEXTAREA_REQUEST_MAX_LENGTH ],
	'percent clamped'               => [ $discount_rules[1]['percentage_discount'], 100.0 ],
	'valid min amount kept'         => [ $discount_rules[1]['min_amount'], 10.5 ],
];

foreach ( $discount_expectations as $label => [ $actual, $expected ] ) {
	if ( $actual !== $expected ) {
		fwrite(
			STDERR,
			sprintf(
				"%s failed: expected %s, got %s\n",
				$label,
				var_export( $expected, true ),
				var_export( $actual, true )
			)
		);
		exit( 1 );
	}
}

$frontend              = ( new ReflectionClass( HP_CS_Frontend::class ) )->newInstanceWithoutConstructor();
$package_method        = new ReflectionMethod( HP_CS_Frontend::class, 'build_funnel_package' );
$resolve_method        = new ReflectionMethod( HP_CS_Frontend::class, 'resolve_funnel_product' );
$checkout_field_method = new ReflectionMethod( HP_CS_Frontend::class, 'get_checkout_field_value' );
$package_method->setAccessible( true );
$resolve_method->setAccessible( true );
$checkout_field_method->setAccessible( true );

$resolved_product = $resolve_method->invoke( $frontend, [ 'productId' => '12abc', 'sku' => 'SKU-12' ] );
if ( ! $resolved_product instanceof WC_Product || $resolved_product->get_id() !== 12 ) {
	fwrite( STDERR, "funnel malformed product ID should fall back to bounded SKU lookup\n" );
	exit( 1 );
}

$unresolved_product = $resolve_method->invoke( $frontend, [ 'productId' => '0012', 'sku' => [ 'SKU-12' ] ] );
if ( $unresolved_product !== false ) {
	fwrite( STDERR, "funnel malformed product ID and array SKU should not resolve a product\n" );
	exit( 1 );
}

$package = $package_method->invoke(
	$frontend,
	[
		[
			'productId'                  => '12',
			'quantity'                   => '2abc',
			'item_discount_percent'      => '5oops',
			'exclude_global_discount'    => '',
			'excludeGlobalDiscount'      => '',
		],
		[
			'variationId'           => '34',
			'qty'                   => '3',
			'itemDiscountPercent'   => '7.5',
			'excludeGlobalDiscount' => true,
		],
	],
	[
		'country'  => [ 'US' ],
		'state'    => 'CA',
		'postcode' => str_repeat( '9', 250 ),
		'city'     => '<b>LA</b>',
	],
	[
		'global_discount_percent' => '10bad',
	]
);

$package_expectations = [
	'funnel package item count'         => [ count( $package['contents'] ), 2 ],
	'malformed quantity defaults to 1'  => [ $package['contents'][0]['quantity'], 1 ],
	'valid quantity preserved'          => [ $package['contents'][1]['quantity'], 3 ],
	'array country rejected'            => [ $package['destination']['country'], '' ],
	'overlong postcode rejected'        => [ $package['destination']['postcode'], '' ],
	'city cleaned'                      => [ $package['destination']['city'], 'LA' ],
];

foreach ( $package_expectations as $label => [ $actual, $expected ] ) {
	if ( $actual !== $expected ) {
		fwrite(
			STDERR,
			sprintf(
				"%s failed: expected %s, got %s\n",
				$label,
				var_export( $expected, true ),
				var_export( $actual, true )
			)
		);
		exit( 1 );
	}
}

$checkout_cases = [
	'checkout scalar kept'       => [ [ 'billing_city' => ' Boston ' ], 'billing_city', 'Boston' ],
	'checkout html cleaned'      => [ [ 'billing_city' => '<b>Boston</b>' ], 'billing_city', 'Boston' ],
	'checkout array rejected'    => [ [ 'billing_city' => [ 'Boston' ] ], 'billing_city', '' ],
	'checkout overlong rejected' => [ [ 'billing_city' => str_repeat( 'B', 250 ) ], 'billing_city', '' ],
	'checkout missing is null'   => [ [], 'billing_city', null ],
];

foreach ( $checkout_cases as $label => [ $data, $attr, $expected ] ) {
	$actual = $checkout_field_method->invoke( $frontend, $data, $attr );
	if ( $actual !== $expected ) {
		fwrite(
			STDERR,
			sprintf(
				"%s failed: expected %s, got %s\n",
				$label,
				var_export( $expected, true ),
				var_export( $actual, true )
			)
		);
		exit( 1 );
	}
}

WC()->customer->props = [];
$frontend->store_customer_details( http_build_query( [ 'billing_city' => 'Boston', 'billing_postcode' => '02110' ] ) );
if ( ( WC()->customer->props['shipping_city'] ?? null ) !== 'Boston' || ( WC()->customer->props['shipping_postcode'] ?? null ) !== '02110' ) {
	fwrite( STDERR, "checkout same-address fields should mirror bounded billing values\n" );
	exit( 1 );
}

$before = WC()->customer->props;
$frontend->store_customer_details( str_repeat( 'a', HP_CS_CHECKOUT_POST_DATA_MAX_LENGTH + 1 ) );
if ( WC()->customer->props !== $before ) {
	fwrite( STDERR, "overlong checkout post_data should not update customer props\n" );
	exit( 1 );
}

echo "request boundary tests passed\n";
