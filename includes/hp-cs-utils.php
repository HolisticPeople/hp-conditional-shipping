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

/**
 * Get condition filter groups for admin UI.
 */
function hp_cs_filter_groups() {
	return apply_filters(
		'hp_cs_filter_groups',
		[
			'cart'              => [
				'title'   => __( 'Cart', 'hp-conditional-shipping' ),
				'filters' => [
					'subtotal'     => [
						'title'     => __( 'Subtotal', 'hp-conditional-shipping' ),
						'operators' => [ 'gt', 'gte', 'lt', 'lte', 'e' ],
					],
					'products'     => [
						'title'     => __( 'Products', 'hp-conditional-shipping' ),
						'operators' => [ 'in', 'notin', 'exclusive', 'allin' ],
					],
					'weight'       => [
						'title'     => sprintf( __( 'Total Weight (%s)', 'hp-conditional-shipping' ), get_option( 'woocommerce_weight_unit', 'lb' ) ),
						'operators' => [ 'gt', 'gte', 'lt', 'lte', 'e' ],
					],
					'items'        => [
						'title'     => __( 'Number of Items', 'hp-conditional-shipping' ),
						'operators' => [ 'gt', 'gte', 'lt', 'lte', 'e' ],
					],
					'category'     => [
						'title'     => __( 'Categories', 'hp-conditional-shipping' ),
						'operators' => [ 'in', 'notin', 'exclusive', 'allin' ],
					],
					'product_tags' => [
						'title'     => __( 'Product Tags', 'hp-conditional-shipping' ),
						'operators' => [ 'in', 'exclusive', 'notin' ],
					],
				],
			],
			'shipping_address' => [
				'title'   => __( 'Shipping Address', 'hp-conditional-shipping' ),
				'filters' => [
					'shipping_country'  => [
						'title'     => __( 'Country (shipping)', 'hp-conditional-shipping' ),
						'operators' => [ 'is', 'isnot' ],
					],
					'shipping_postcode' => [
						'title'     => __( 'Postcode (shipping)', 'hp-conditional-shipping' ),
						'operators' => [ 'is', 'isnot' ],
					],
					'shipping_city'     => [
						'title'     => __( 'City (shipping)', 'hp-conditional-shipping' ),
						'operators' => [ 'is', 'isnot' ],
					],
				],
			],
			'customer'         => [
				'title'   => __( 'Customer', 'hp-conditional-shipping' ),
				'filters' => [
					'customer_authenticated' => [
						'title'     => __( 'Logged in / out', 'hp-conditional-shipping' ),
						'operators' => [ 'loggedin', 'loggedout' ],
					],
					'customer_role'          => [
						'title'     => __( 'Role', 'hp-conditional-shipping' ),
						'operators' => [ 'is', 'isnot' ],
					],
					'orders'                 => [
						'title'     => __( 'Previous orders', 'hp-conditional-shipping' ),
						'operators' => [ 'gt', 'gte', 'lt', 'lte', 'e' ],
					],
				],
			],
		]
	);
}

/**
 * Get action groups for admin UI.
 */
function hp_cs_action_groups() {
	$actions = hp_cs_actions();
	$groups  = [
		'availability' => [
			'title'   => __( 'Availability', 'hp-conditional-shipping' ),
			'actions' => [],
		],
		'messages'     => [
			'title'   => __( 'Messages', 'hp-conditional-shipping' ),
			'actions' => [],
		],
		'other'        => [
			'title'   => __( 'Other', 'hp-conditional-shipping' ),
			'actions' => [],
		],
	];

	foreach ( $actions as $key => $action ) {
		$group = $action['group'] ?? 'other';
		if ( isset( $groups[ $group ] ) ) {
			$groups[ $group ]['actions'][ $key ] = $action;
		} else {
			$groups['other']['actions'][ $key ] = $action;
		}
	}

	return apply_filters( 'hp_cs_action_groups', $groups );
}

/**
 * Get available actions.
 */
function hp_cs_actions() {
	return apply_filters(
		'hp_cs_actions',
		[
			'disable_shipping_methods' => [
				'title' => __( 'Disable shipping methods', 'hp-conditional-shipping' ),
				'group' => 'availability',
			],
			'enable_shipping_methods'  => [
				'title' => __( 'Enable shipping methods', 'hp-conditional-shipping' ),
				'group' => 'availability',
			],
			'custom_error_msg'         => [
				'title' => __( 'Set custom no shipping message', 'hp-conditional-shipping' ),
				'group' => 'messages',
			],
			'shipping_notice'          => [
				'title' => __( 'Set shipping notice', 'hp-conditional-shipping' ),
				'group' => 'messages',
			],
		]
	);
}

/**
 * Get country options.
 */
function hp_cs_country_options() {
	$countries_obj = new WC_Countries();
	return $countries_obj->get_countries();
}

/**
 * Get state options.
 */
function hp_cs_state_options() {
	$countries_obj = new WC_Countries();
	$countries     = $countries_obj->get_countries();
	$states        = array_filter( $countries_obj->get_states() );

	$options = [];
	foreach ( $states as $country_id => $state_list ) {
		$options[ $country_id ] = [
			'states'  => $state_list,
			'country' => $countries[ $country_id ] ?? $country_id,
		];
	}

	// Move US first as commonly used.
	if ( isset( $options['US'] ) ) {
		$us = $options['US'];
		unset( $options['US'] );
		$options = [ 'US' => $us ] + $options;
	}

	return $options;
}

/**
 * Get category options (hierarchical).
 */
function hp_cs_category_options() {
	$categories = get_terms(
		[
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		]
	);

	if ( is_wp_error( $categories ) || empty( $categories ) ) {
		return [];
	}

	$sorted  = [];
	hp_cs_sort_terms_hierarchically( $categories, $sorted );

	$options = [];
	hp_cs_flatten_terms( $options, $sorted );

	return $options;
}

/**
 * Sort terms hierarchically.
 */
function hp_cs_sort_terms_hierarchically( array &$cats, array &$into, $parent_id = 0 ) {
	foreach ( $cats as $i => $cat ) {
		if ( $cat->parent == $parent_id ) {
			$into[ $cat->term_id ] = $cat;
			unset( $cats[ $i ] );
		}
	}

	foreach ( $into as $top_cat ) {
		$top_cat->children = [];
		hp_cs_sort_terms_hierarchically( $cats, $top_cat->children, $top_cat->term_id );
	}
}

/**
 * Flatten hierarchical terms for select options.
 */
function hp_cs_flatten_terms( array &$options, array $cats, $depth = 0 ) {
	foreach ( $cats as $cat ) {
		if ( $depth > 0 ) {
			$prefix                    = str_repeat( ' - ', $depth );
			$options[ $cat->term_id ] = "{$prefix} {$cat->name}";
		} else {
			$options[ $cat->term_id ] = $cat->name;
		}

		if ( isset( $cat->children ) && ! empty( $cat->children ) ) {
			hp_cs_flatten_terms( $options, $cat->children, $depth + 1 );
		}
	}
}

/**
 * Get shipping method options.
 */
function hp_cs_shipping_method_options() {
	$shipping_zones = WC_Shipping_Zones::get_zones();
	$shipping_zones[] = new WC_Shipping_Zone( 0 );

	$zones_count = count( $shipping_zones );
	$options     = [];

	// General options (always available).
	$options['_all'] = [
		'title'   => __( 'General', 'hp-conditional-shipping' ),
		'options' => [
			'_all'        => [
				'title' => __( 'All shipping methods', 'hp-conditional-shipping' ),
			],
			'_name_match' => [
				'title' => __( 'Match by name', 'hp-conditional-shipping' ),
			],
		],
	];

	foreach ( $shipping_zones as $shipping_zone ) {
		if ( is_array( $shipping_zone ) && isset( $shipping_zone['zone_id'] ) ) {
			$shipping_zone = WC_Shipping_Zones::get_zone( $shipping_zone['zone_id'] );
		} elseif ( ! is_object( $shipping_zone ) ) {
			continue;
		}

		$zone_id = $shipping_zone->get_id();

		if ( isset( $options[ $zone_id ] ) ) {
			continue; // Already added.
		}

		$options[ $zone_id ] = [
			'title'   => $shipping_zone->get_zone_name(),
			'options' => [],
		];

		foreach ( $shipping_zone->get_shipping_methods() as $instance_id => $shipping_method ) {
			if ( $zones_count > 1 ) {
				$title = sprintf( '%s (%s)', $shipping_method->get_title(), $shipping_zone->get_zone_name() );
			} else {
				$title = $shipping_method->get_title();
			}

			$options[ $zone_id ]['options'][ $instance_id ] = [
				'title' => $title,
			];
		}
	}

	// Remove zones with no shipping methods.
	$options = array_filter(
		$options,
		function( $option ) {
			return ! empty( $option['options'] );
		}
	);

	return apply_filters( 'hp_cs_shipping_method_options', $options );
}

/**
 * Get role options.
 */
function hp_cs_role_options() {
	global $wp_roles;

	$options = [
		'guest' => __( 'Guest', 'hp-conditional-shipping' ),
	];

	if ( is_a( $wp_roles, 'WP_Roles' ) && isset( $wp_roles->roles ) ) {
		foreach ( $wp_roles->roles as $role => $details ) {
			$options[ $role ] = translate_user_role( $details['name'] );
		}
	}

	return $options;
}

/**
 * Escape HTML for use in JavaScript templates (escapes curly braces).
 */
function hp_cs_esc_html( $text ) {
	// Escape curly braces because they will be interpreted as JS variables.
	$text = str_replace( '{', '&#123;', $text );
	$text = str_replace( '}', '&#125;', $text );
	return esc_html( $text );
}

/**
 * Get control title (condition or action).
 */
function hp_cs_get_control_title( $control ) {
	if ( isset( $control['pro'] ) && $control['pro'] ) {
		return sprintf( __( '%s (Pro)', 'hp-conditional-shipping' ), $control['title'] );
	}
	return $control['title'] ?? '';
}

/**
 * Get notice styles.
 */
function hp_cs_get_notice_styles() {
	return [
		'blank'   => __( 'No styling', 'hp-conditional-shipping' ),
		'success' => __( 'Success', 'hp-conditional-shipping' ),
		'warning' => __( 'Warning', 'hp-conditional-shipping' ),
		'error'   => __( 'Error', 'hp-conditional-shipping' ),
	];
}

/**
 * Get order status options.
 */
function hp_cs_order_status_options() {
	if ( ! function_exists( 'wc_get_order_statuses' ) ) {
		return [];
	}
	return wc_get_order_statuses();
}

/**
 * Get stock status options.
 */
function hp_cs_get_stock_status_options() {
	return [
		'instock'    => __( 'In stock', 'hp-conditional-shipping' ),
		'backorders' => __( 'Backorders', 'hp-conditional-shipping' ),
		'outofstock' => __( 'Out of stock', 'hp-conditional-shipping' ),
	];
}

/**
 * Get shipping class options.
 */
function hp_cs_get_shipping_class_options() {
	$options = [];
	foreach ( WC()->shipping->get_shipping_classes() as $shipping_class ) {
		$options[ $shipping_class->term_id ] = $shipping_class->name;
	}
	$options['0'] = __( 'No shipping class', 'hp-conditional-shipping' );
	return $options;
}

/**
 * Subset filters (empty for now, can be extended).
 */
function hp_cs_subset_filters() {
	return apply_filters( 'hp_cs_subset_filters', [] );
}

/**
 * Product attribute options (stub - not supported yet).
 */
function hp_cs_product_attr_options() {
	return [];
}

/**
 * Weekdays options (stub - not supported yet).
 */
function hp_cs_weekdays_options() {
	return [];
}

/**
 * Time hours options (stub - not supported yet).
 */
function hp_cs_time_hours_options() {
	return [];
}

/**
 * Time minutes options (stub - not supported yet).
 */
function hp_cs_time_mins_options() {
	return [];
}

/**
 * PMS plan options (stub - not supported).
 */
function hp_cs_pms_plan_options() {
	return [];
}

/**
 * Price modes (stub - not supported yet).
 */
function hp_cs_get_price_modes() {
	return [];
}

/**
 * Price per options (stub - not supported yet).
 */
function hp_cs_get_price_per_options() {
	return [];
}

/**
 * Currency options.
 */
function hp_cs_currency_options() {
	return function_exists( 'get_woocommerce_currencies' ) ? get_woocommerce_currencies() : [];
}
