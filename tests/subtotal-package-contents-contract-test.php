<?php
declare(strict_types=1);

/**
 * Regression test: subtotal conditions must evaluate against HP-built package
 * contents (funnel / HP Checkout surfaces), not the Woo session cart — which
 * is empty behind HP Checkout requests. Live-verified inert rules 2026-07-11
 * (a $755 cart to AU passed the $700 cap because the Woo cart read as $0).
 *
 * Runs standalone without WordPress: `php tests/subtotal-package-contents-contract-test.php`
 */

define( 'ABSPATH', __DIR__ . '/' );

// --- WP/WC stubs -----------------------------------------------------------
$GLOBALS['stub_cart_subtotal'] = 0.0;

function wc_get_price_decimals() {
	return 2;
}

function hp_cs_get_cart_func( $func = 'get_cart' ) {
	// Simulates the classic surface Woo cart.
	if ( $func === 'get_displayed_subtotal' ) {
		return $GLOBALS['stub_cart_subtotal'];
	}
	if ( $func === 'get_cart' ) {
		return [];
	}
	return false;
}

function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function wp_unslash( $value ) {
	return $value;
}
function wc_clean( $value ) {
	return is_string( $value ) ? trim( $value ) : $value;
}
function __( $text, $domain = 'default' ) {
	return $text;
}

require dirname( __DIR__ ) . '/includes/class-hp-cs-filters.php';
require dirname( __DIR__ ) . '/includes/class-hp-cs-frontend.php';

function hp_cs_assert( $cond, string $message ) {
	if ( ! $cond ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

// --- HP-built package (HP Checkout / funnel surface) ------------------------
$hp_package = [
	'contents' => [
		[ 'line_total' => 400.51, 'quantity' => 1 ],
		[ 'line_total' => 354.50, 'quantity' => 2 ],
		[ 'line_total' => 'not-a-number' ],   // ignored
		[ 'line_total' => -25 ],              // clamped, never lowers the subtotal
		'not-an-array',                       // ignored
	],
	'hp_cs_contents_subtotal' => true,
	'destination' => [ 'country' => 'AU' ],
];

hp_cs_assert(
	HP_CS_Filters::get_package_subtotal( $hp_package ) === 755.01,
	'HP package subtotal must be the rounded sum of numeric, non-negative line totals (expected 755.01).'
);

// A $755 cart against a "subtotal gt 700" condition: the condition is MET, so
// filter_subtotal must return false (false = condition passed = rule triggers).
$over_cap = [ 'type' => 'subtotal', 'value' => '700', 'operator' => 'gt' ];
hp_cs_assert(
	HP_CS_Filters::filter_subtotal( $over_cap, $hp_package ) === false,
	'A $755 HP Checkout package must trip a "subtotal gt 700" condition (rule 43730 regression).'
);

// An under-cap HP package must NOT trip the condition.
$small_package = [
	'contents'                => [ [ 'line_total' => 120.0, 'quantity' => 1 ] ],
	'hp_cs_contents_subtotal' => true,
];
hp_cs_assert(
	HP_CS_Filters::filter_subtotal( $over_cap, $small_package ) === true,
	'A $120 HP Checkout package must not trip a "subtotal gt 700" condition.'
);

// --- Classic surface fallback ------------------------------------------------
// Without the HP flag the Woo cart subtotal must still be authoritative, even
// if the package carries contents (classic WC packages do).
$GLOBALS['stub_cart_subtotal'] = 800.0;
$classic_package = [
	'contents' => [ [ 'line_total' => 10.0, 'quantity' => 1 ] ],
];
hp_cs_assert(
	HP_CS_Filters::get_package_subtotal( $classic_package ) === 800.0,
	'Unflagged (classic) packages must keep reading the Woo cart subtotal.'
);
hp_cs_assert(
	HP_CS_Filters::filter_subtotal( $over_cap, $classic_package ) === false,
	'Classic surface subtotal conditions must still evaluate the Woo cart subtotal.'
);

// --- Frontend wiring ----------------------------------------------------------
// build_funnel_package() must actually emit the flag, otherwise HP Checkout /
// funnel evaluation silently falls back to the empty Woo cart again.
$frontend = HP_CS_Frontend::instance();
$method   = new ReflectionMethod( HP_CS_Frontend::class, 'build_funnel_package' );
$built    = $method->invoke( $frontend, [], [ 'country' => 'AU' ], [] );

hp_cs_assert(
	is_array( $built ) && ! empty( $built['hp_cs_contents_subtotal'] ),
	'build_funnel_package() must flag its packages so subtotal conditions read package contents.'
);
hp_cs_assert(
	HP_CS_Filters::get_package_subtotal( $built ) === 0.0,
	'An empty HP-built package must evaluate as a $0 subtotal, not fall back to the Woo cart.'
);

echo "Subtotal package-contents contract passed.\n";
