<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HP_CS_Filters_Pro {
	/**
	 * Customer logged in / out.
	 */
	public static function filter_customer_authenticated( $condition ) {
		switch ( $condition['operator'] ?? '' ) {
			case 'loggedin':
				return ! is_user_logged_in();
			case 'loggedout':
				return is_user_logged_in();
		}
		return false;
	}

	/**
	 * Customer role (supports 'guest').
	 */
	public static function filter_customer_role( $condition ) {
		$user_roles = isset( $condition['user_roles'] ) ? (array) $condition['user_roles'] : [];
		if ( empty( $user_roles ) ) {
			return false;
		}

		$op = $condition['operator'] ?? 'is';

		if ( ! is_user_logged_in() ) {
			$guests_on_list = in_array( 'guest', $user_roles, true );
			$is_operator    = ( $op === 'is' );
			if ( ( $guests_on_list && $is_operator ) || ( ! $is_operator && ! $guests_on_list ) ) {
				return false;
			}
			return true;
		}

		$user  = wp_get_current_user();
		$roles = array_values( array_filter( (array) $user->roles ) );

		// Normalize to group operators like reference.
		if ( $op === 'is' ) {
			$op = 'in';
		} elseif ( $op === 'isnot' ) {
			$op = 'notin';
		}

		return ! HP_CS_Filters::group_comparison( $roles, $user_roles, $op );
	}

	/**
	 * Shipping country (from customer session).
	 */
	public static function filter_shipping_country( $condition ) {
		$countries = isset( $condition['countries'] ) ? (array) $condition['countries'] : [];
		if ( empty( $countries ) ) {
			return false;
		}

		$value = WC()->customer ? WC()->customer->get_shipping_country() : null;
		return ! HP_CS_Filters::is_array_comparison( (string) $value, $countries, $condition['operator'] ?? 'is' );
	}

	/**
	 * Shipping postcode (supports ranges & wildcards via wc_postcode_location_matcher).
	 */
	public static function filter_shipping_postcode( $condition ) {
		$value   = WC()->customer ? WC()->customer->get_shipping_postcode() : null;
		$country = WC()->customer ? WC()->customer->get_shipping_country() : null;

		if ( $value === null ) {
			return false;
		}

		$postcodes_raw = isset( $condition['postcodes'] ) ? trim( (string) $condition['postcodes'] ) : '';
		if ( $postcodes_raw === '' ) {
			return false;
		}

		$postcodes = array_filter( array_map( 'strtoupper', array_map( 'wc_clean', explode( "\n", $postcodes_raw ) ) ) );
		$postcodes_obj = [];
		$i = 1;
		foreach ( $postcodes as $postcode ) {
			$postcodes_obj[] = (object) [
				'id'    => $i,
				'value' => $postcode,
			];
			$i++;
		}

		$matches = wc_postcode_location_matcher( (string) $value, $postcodes_obj, 'id', 'value', (string) $country );
		$op      = $condition['operator'] ?? 'is';

		if ( $op === 'is' ) {
			return empty( $matches );
		}
		if ( $op === 'isnot' ) {
			return ! empty( $matches );
		}

		return false;
	}

	/**
	 * Shipping city (supports wildcard).
	 */
	public static function filter_shipping_city( $condition ) {
		$value = WC()->customer ? WC()->customer->get_shipping_city() : null;
		$value = $value !== null ? trim( strtolower( (string) $value ) ) : null;

		$cities_raw = isset( $condition['cities'] ) ? trim( (string) $condition['cities'] ) : '';
		if ( $value === null || $cities_raw === '' ) {
			return false;
		}

		$cities = array_filter( array_map( 'strtolower', array_map( 'wc_clean', explode( "\n", $cities_raw ) ) ) );

		$matches = [];
		foreach ( $cities as $city ) {
			if ( strpos( $city, '*' ) !== false ) {
				if ( function_exists( 'fnmatch' ) && fnmatch( $city, $value ) ) {
					$matches[] = $city;
				}
			} else {
				if ( $city === $value ) {
					$matches[] = $city;
				}
			}
		}

		$op = $condition['operator'] ?? 'is';
		if ( $op === 'is' ) {
			return empty( $matches );
		}
		if ( $op === 'isnot' ) {
			return ! empty( $matches );
		}

		return false;
	}

	/**
	 * Items count.
	 */
	public static function filter_items( $condition, $package ) {
		$items = isset( $package['contents'] ) && is_array( $package['contents'] ) ? $package['contents'] : [];
		$count = 0;
		foreach ( $items as $item ) {
			if ( ! empty( $condition['items_unique_only'] ) ) {
				$count++;
			} else {
				$count += (int) ( $item['quantity'] ?? 0 );
			}
		}

		if ( isset( $condition['value'] ) && $condition['value'] !== '' ) {
			$target = (float) str_replace( ',', '.', (string) $condition['value'] );
			return ! HP_CS_Filters::compare_numeric_value( (float) $count, $target, $condition['operator'] ?? 'e' );
		}

		return false;
	}

	/**
	 * Category restriction.
	 */
	public static function filter_category( $condition, $package ) {
		$category_ids = isset( $condition['category_ids'] ) ? array_map( 'intval', (array) $condition['category_ids'] ) : [];
		if ( empty( $category_ids ) ) {
			return false;
		}

		$op = $condition['operator'] ?? 'in';

		$cat_ids = self::get_cart_product_cat_ids( $package );
		return ! HP_CS_Filters::group_comparison( $cat_ids, $category_ids, $op );
	}

	/**
	 * Product tags restriction.
	 */
	public static function filter_product_tags( $condition, $package ) {
		$tag_ids = isset( $condition['product_tags'] ) ? array_map( 'intval', (array) $condition['product_tags'] ) : [];
		if ( empty( $tag_ids ) ) {
			return false;
		}

		$op = $condition['operator'] ?? 'in';
		$cart_tag_ids = self::get_cart_product_tag_ids( $package );
		return ! HP_CS_Filters::group_comparison( $cart_tag_ids, $tag_ids, $op );
	}

	/**
	 * Previous orders condition (not used in audit yet; implemented with caching).
	 */
	public static function filter_orders( $condition ) {
		$op = $condition['operator'] ?? 'gte';
		$target = isset( $condition['value'] ) ? (int) $condition['value'] : 0;

		$match_by = [];
		if ( is_user_logged_in() ) {
			$match_by['customer_id'] = get_current_user_id();
		} else {
			if ( ! empty( $condition['orders_match_guests_by_email'] ) ) {
				$email = WC()->customer ? WC()->customer->get_billing_email() : '';
				if ( $email ) {
					$match_by['billing_email'] = $email;
				}
			}
		}

		if ( empty( $match_by ) ) {
			return true; // Fail (compat): if cannot match, treat as not passing.
		}

		$statuses = [ 'processing', 'completed' ];
		if ( ! empty( $condition['orders_status'] ) && is_array( $condition['orders_status'] ) ) {
			$statuses = $condition['orders_status'];
		}

		$cache_key = 'orders_' . md5( wp_json_encode( [ $match_by, $statuses, $op, $target ] ) );
		$cached = wp_cache_get( $cache_key, 'hp_cs' );
		if ( is_int( $cached ) ) {
			return ! HP_CS_Filters::compare_numeric_value( $cached, $target, $op );
		}

		// Performance: count/early-exit.
		$params = array_merge(
			$match_by,
			[
				'status' => $statuses,
				'limit'  => 1,
				'return' => 'ids',
			]
		);

		$count = 0;
		$orders = wc_get_orders( $params );
		if ( ! empty( $orders ) ) {
			// We only know >=1; do a cheap count only if threshold needs it.
			if ( in_array( $op, [ 'e', 'lt', 'lte' ], true ) || $target > 1 ) {
				$params['limit'] = -1;
				$count = count( wc_get_orders( $params ) );
			} else {
				$count = 1;
			}
		}

		wp_cache_set( $cache_key, $count, 'hp_cs', 600 );
		return ! HP_CS_Filters::compare_numeric_value( $count, $target, $op );
	}

	private static function get_cart_product_cat_ids( $package ) {
		$products = HP_CS_Filters::get_cart_products( $package );
		$cat_ids = [];
		foreach ( $products as $product ) {
			if ( ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}
			$ids = self::get_product_cats( $product->get_id() );
			$cat_ids = array_merge( $cat_ids, $ids );
		}
		return array_values( array_unique( array_map( 'intval', $cat_ids ) ) );
	}

	private static function get_cart_product_tag_ids( $package ) {
		$products = HP_CS_Filters::get_cart_products( $package );
		$tag_ids = [];
		foreach ( $products as $product ) {
			if ( ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}
			$ids = self::get_product_tags( $product->get_id() );
			$tag_ids = array_merge( $tag_ids, $ids );
		}
		return array_values( array_unique( array_map( 'intval', $tag_ids ) ) );
	}

	private static function get_product_cats( $product_id ) {
		$cat_ids = [];
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return [];
		}

		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( $terms ) {
			foreach ( $terms as $term ) {
				$cat_ids[ $term->term_id ] = true;
			}
		}

		if ( $product->get_parent_id() ) {
			$terms = get_the_terms( $product->get_parent_id(), 'product_cat' );
			if ( $terms ) {
				foreach ( $terms as $term ) {
					$cat_ids[ $term->term_id ] = true;
				}
			}
		}

		foreach ( array_keys( $cat_ids ) as $term_id ) {
			$ancestors = (array) get_ancestors( $term_id, 'product_cat', 'taxonomy' );
			foreach ( $ancestors as $ancestor_id ) {
				$cat_ids[ $ancestor_id ] = true;
			}
		}

		return array_keys( $cat_ids );
	}

	private static function get_product_tags( $product_id ) {
		$tag_ids = [];
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return [];
		}

		$terms = get_the_terms( $product->get_id(), 'product_tag' );
		if ( $terms ) {
			foreach ( $terms as $term ) {
				$tag_ids[ $term->term_id ] = true;
			}
		}

		if ( $product->get_parent_id() ) {
			$terms = get_the_terms( $product->get_parent_id(), 'product_tag' );
			if ( $terms ) {
				foreach ( $terms as $term ) {
					$tag_ids[ $term->term_id ] = true;
				}
			}
		}

		return array_keys( $tag_ids );
	}
}


