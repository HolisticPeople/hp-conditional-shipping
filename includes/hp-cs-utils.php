<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bump internal ruleset cache version and WooCommerce shipping cache.
 */
function hp_cs_bump_cache_versions() {
	$ver = (int) get_option( 'hp_cs_ruleset_version', 1 );
	update_option( 'hp_cs_ruleset_version', $ver + 1, false );

	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}

/**
 * Get cache version integer.
 */
function hp_cs_get_ruleset_version() {
	return (int) get_option( 'hp_cs_ruleset_version', 1 );
}

/**
 * Get rulesets (HP drop-in compatible with wcs_ruleset CPT).
 *
 * @return HP_CS_Ruleset[]
 */
function hp_cs_get_rulesets( $only_enabled = false ) {
	static $req_cache = null;
	static $req_cache_ver = null;

	$ver = hp_cs_get_ruleset_version();
	if ( $req_cache !== null && $req_cache_ver === $ver ) {
		$rulesets = $req_cache;
		return $only_enabled ? array_values( array_filter( $rulesets, fn( $r ) => $r->get_enabled() ) ) : $rulesets;
	}

	$cache_key = 'rulesets_v' . $ver;
	$rulesets  = wp_cache_get( $cache_key, 'hp_cs' );
	if ( ! is_array( $rulesets ) ) {
		$posts = get_posts(
			[
				'post_status'    => [ 'publish' ],
				'post_type'      => 'wcs_ruleset',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			]
		);

		$rulesets = [];
		foreach ( $posts as $id ) {
			$rulesets[] = new HP_CS_Ruleset( $id );
		}

		// Apply stored ordering (same as reference plugin).
		$ordering = get_option( 'wcs_ruleset_order', false );
		if ( $ordering && is_array( $ordering ) ) {
			$order_end       = 999;
			$ordered_rulesets = [];

			foreach ( $rulesets as $ruleset ) {
				$rid = $ruleset->get_id();
				if ( isset( $ordering[ $rid ] ) && is_numeric( $ordering[ $rid ] ) ) {
					$ordered_rulesets[ (int) $ordering[ $rid ] ] = $ruleset;
				} else {
					$ordered_rulesets[ $order_end ] = $ruleset;
					$order_end++;
				}
			}

			ksort( $ordered_rulesets );
			$rulesets = array_values( $ordered_rulesets );
		}

		wp_cache_set( $cache_key, $rulesets, 'hp_cs', 300 );
	}

	$req_cache     = $rulesets;
	$req_cache_ver = $ver;

	return $only_enabled ? array_values( array_filter( $rulesets, fn( $r ) => $r->get_enabled() ) ) : $rulesets;
}

/**
 * Operators labels (kept compatible).
 */
function hp_cs_operators() {
	return [
		'gt'        => __( 'greater than', 'hp-conditional-shipping' ),
		'gte'       => __( 'greater than or equal', 'hp-conditional-shipping' ),
		'lt'        => __( 'less than', 'hp-conditional-shipping' ),
		'lte'       => __( 'less than or equal', 'hp-conditional-shipping' ),
		'e'         => __( 'equals', 'hp-conditional-shipping' ),
		'in'        => __( 'include', 'hp-conditional-shipping' ),
		'exclusive' => __( 'include only', 'hp-conditional-shipping' ),
		'notin'     => __( 'exclude', 'hp-conditional-shipping' ),
		'allin'     => __( 'include all', 'hp-conditional-shipping' ),
		'is'        => __( 'is', 'hp-conditional-shipping' ),
		'isnot'     => __( 'is not', 'hp-conditional-shipping' ),
		'exists'    => __( 'is not empty', 'hp-conditional-shipping' ),
		'notexists' => __( 'is empty', 'hp-conditional-shipping' ),
		'contains'  => __( 'contains', 'hp-conditional-shipping' ),
		'loggedin'  => __( 'logged in', 'hp-conditional-shipping' ),
		'loggedout' => __( 'logged out', 'hp-conditional-shipping' ),
	];
}

/**
 * Match shipping method by instance, all, or name match.
 */
function hp_cs_method_selected( $method_title, $instance_id, $action ) {
	$shipping_method_ids = isset( $action['shipping_method_ids'] ) ? (array) $action['shipping_method_ids'] : [];
	$names              = isset( $action['shipping_method_name_match'] ) ? $action['shipping_method_name_match'] : false;

	$shipping_method_ids = array_map( 'strval', $shipping_method_ids );

	$passes = [
		'all'        => in_array( '_all', $shipping_method_ids, true ),
		'name_match' => in_array( '_name_match', $shipping_method_ids, true ) && hp_cs_method_name_match( $method_title, $names ),
		'instance'   => ( $instance_id !== false && in_array( strval( $instance_id ), $shipping_method_ids, true ) ),
	];

	return in_array( true, $passes, true );
}

function hp_cs_method_name_match( $title, $names ) {
	$title = strtolower( trim( (string) $title ) );
	$names = array_filter( array_map( 'strtolower', array_map( 'wc_clean', explode( "\n", (string) $names ) ) ) );

	foreach ( $names as $name ) {
		if ( strpos( $name, '*' ) !== false ) {
			if ( function_exists( 'fnmatch' ) && fnmatch( $name, $title ) ) {
				return true;
			}
		} else {
			if ( $name === $title ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Get cart function safely.
 */
function hp_cs_get_cart_func( $func = 'get_cart' ) {
	$cart    = WC()->cart;
	$default = false;

	switch ( $func ) {
		case 'get_cart':
		case 'get_applied_coupons':
			$default = [];
			break;
		case 'display_prices_including_tax':
			$default = false;
			break;
		case 'get_displayed_subtotal':
		case 'get_discount_total':
		case 'get_discount_tax':
		case 'get_cart_contents_count':
			$default = 0;
			break;
	}

	return $cart ? call_user_func( [ $cart, $func ] ) : $default;
}


