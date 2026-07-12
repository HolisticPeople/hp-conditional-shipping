<?php
declare(strict_types=1);

/**
 * Regression test: postcode/city conditions must FAIL CLOSED for matching when
 * the destination value is unknown or empty. `filter_shipping_postcode`
 * returned false (= condition passes) whenever the postcode was unresolvable,
 * so an `is 07512` customer-blocklist rule (23449-family, live rule 23438)
 * silently disabled ALL shipping methods for every postcode-less destination
 * (AE/HK/QA) on guest REST checkouts, where WC()->customer is absent — the
 * QA shipping.ae-postcodeless.rates permanent false-red (2026-07-12).
 *
 * Runs standalone without WordPress: `php tests/postcodeless-destination-blocklist-contract-test.php`
 */

define( 'ABSPATH', __DIR__ . '/' );

// --- WP/WC stubs -----------------------------------------------------------
function WC() {
	// REST/guest context: no customer object at all.
	return new class() {
		public $customer = null;
	};
}

function wc_clean( $value ) {
	return is_string( $value ) ? trim( $value ) : $value;
}

function wc_postcode_location_matcher( $postcode, $objects, $object_id_key, $object_compare_key, $country = '' ) {
	$matches = [];
	foreach ( (array) $objects as $object ) {
		if ( strtoupper( (string) $postcode ) !== '' && strtoupper( (string) $postcode ) === strtoupper( (string) $object->{$object_compare_key} ) ) {
			$matches[ $object->{$object_id_key} ] = $object->{$object_compare_key};
		}
	}
	return $matches;
}

require dirname( __DIR__ ) . '/includes/class-hp-cs-filters-pro.php';

function hp_cs_assert( $cond, string $message ) {
	if ( ! $cond ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$blocklist_condition = [
	'type'      => 'shipping_postcode',
	'operator'  => 'is',
	'postcodes' => '07512',
];

// AE guest checkout: postcode-less destination, no WC customer fallback.
$ae_package = [
	'contents'    => [],
	'destination' => [
		'country'  => 'AE',
		'state'    => '',
		'postcode' => '',
		'city'     => 'Dubai',
	],
];

hp_cs_assert(
	HP_CS_Filters_Pro::filter_shipping_postcode( $blocklist_condition, $ae_package ) === true,
	'An empty postcode must NOT match a postcode blocklist: the `is` condition has to fail (destination-wide rate wipe for AE/HK/QA otherwise).'
);

// No destination at all (value unresolvable) — same rule must still fail.
hp_cs_assert(
	HP_CS_Filters_Pro::filter_shipping_postcode( $blocklist_condition, [ 'contents' => [] ] ) === true,
	'An unresolvable postcode must NOT match a postcode blocklist.'
);

// The blocklisted postcode itself still matches.
$blocked_package = $ae_package;
$blocked_package['destination'] = [ 'country' => 'US', 'state' => 'NJ', 'postcode' => '07512', 'city' => 'Paterson' ];
hp_cs_assert(
	HP_CS_Filters_Pro::filter_shipping_postcode( $blocklist_condition, $blocked_package ) === false,
	'The blocklisted postcode must still match the `is` condition.'
);

// `isnot` semantics: an unknown postcode is genuinely "not in the list".
$isnot_condition = $blocklist_condition;
$isnot_condition['operator'] = 'isnot';
hp_cs_assert(
	HP_CS_Filters_Pro::filter_shipping_postcode( $isnot_condition, $ae_package ) === false,
	'An empty postcode should satisfy `isnot <list>` (it is not in the list).'
);

// City condition: unknown city must match nothing as well.
$city_condition = [
	'type'     => 'shipping_city',
	'operator' => 'is',
	'cities'   => 'Paterson',
];
$no_city_package = [ 'contents' => [], 'destination' => [ 'country' => 'AE', 'city' => '' ] ];
hp_cs_assert(
	HP_CS_Filters_Pro::filter_shipping_city( $city_condition, $no_city_package ) === true,
	'An empty city must NOT match a city blocklist: the `is` condition has to fail.'
);

echo "postcodeless destination blocklist contract passed\n";
