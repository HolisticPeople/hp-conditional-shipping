<?php

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

require_once __DIR__ . '/../includes/class-hp-cs-admin.php';

$admin           = ( new ReflectionClass( HP_CS_Admin::class ) )->newInstanceWithoutConstructor();
$id_method       = new ReflectionMethod( HP_CS_Admin::class, 'parse_positive_decimal_id' );
$json_method     = new ReflectionMethod( HP_CS_Admin::class, 'decode_ruleset_json_array' );
$id_method->setAccessible( true );
$json_method->setAccessible( true );

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

echo "request boundary tests passed\n";
