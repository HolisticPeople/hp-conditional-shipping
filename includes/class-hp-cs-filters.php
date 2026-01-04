<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HP_CS_Filters {
	/**
	 * Map condition type to callable.
	 */
	public static function get_callable_for_type( string $type ) {
		$fn = "filter_{$type}";
		if ( method_exists( __CLASS__, $fn ) ) {
			return [ __CLASS__, $fn ];
		}
		if ( method_exists( 'HP_CS_Filters_Pro', $fn ) ) {
			return [ 'HP_CS_Filters_Pro', $fn ];
		}
		// Unknown condition type -> treat as pass (do not block shipping).
		return function () {
			return false;
		};
	}

	public static function filter_weight( $condition, $package ) {
		$package_weight = self::calculate_package_weight( $package );

		if ( isset( $condition['value'] ) ) {
			$weight = self::parse_number( $condition['value'] );
			return ! self::compare_numeric_value( $package_weight, $weight, $condition['operator'] );
		}

		return false;
	}

	public static function filter_subtotal( $condition, $package ) {
		$cart_subtotal = self::get_cart_subtotal( $condition );

		if ( isset( $condition['value'] ) && $condition['value'] !== '' ) {
			$subtotal = self::parse_number( $condition['value'] );
			return ! self::compare_numeric_value( $cart_subtotal, $subtotal, $condition['operator'] );
		}

		return false;
	}

	public static function filter_products( $condition, $package ) {
		if ( isset( $condition['product_ids'] ) && ! empty( $condition['product_ids'] ) ) {
			$condition_product_ids = self::merge_product_children_ids( (array) $condition['product_ids'] );
			$products              = self::get_cart_products( $package );

			if ( ! empty( $products ) ) {
				$product_ids = array_keys( $products );
				return ! self::group_comparison( $product_ids, $condition_product_ids, $condition['operator'] );
			}
		}

		return false;
	}

	public static function get_cart_products( $package = false ) {
		$products = [];
		$items    = hp_cs_get_cart_func( 'get_cart' );

		if ( $package !== false && isset( $package['contents'] ) && is_array( $package['contents'] ) ) {
			$items = $package['contents'];
		}

		foreach ( $items as $item ) {
			if ( ! isset( $item['data'] ) ) {
				continue;
			}

			if ( ! empty( $item['variation_id'] ) ) {
				$products[ (int) $item['variation_id'] ] = $item['data'];
			} elseif ( ! empty( $item['product_id'] ) ) {
				$products[ (int) $item['product_id'] ] = $item['data'];
			}
		}

		return $products;
	}

	private static function merge_product_children_ids( array $product_ids ) {
		$args = [
			'post_type'      => [ 'product_variation' ],
			'post_parent__in'=> $product_ids,
			'fields'         => 'ids',
			'posts_per_page' => -1,
		];
		$children_ids = get_posts( $args );
		return array_values( array_unique( array_merge( $children_ids, $product_ids ) ) );
	}

	public static function get_cart_subtotal( $condition = false ) {
		$total = hp_cs_get_cart_func( 'get_displayed_subtotal' );

		if ( $condition && ! empty( $condition['subtotal_includes_coupons'] ) && method_exists( WC()->cart, 'get_discount_total' ) ) {
			$total -= (float) hp_cs_get_cart_func( 'get_discount_total' );
			if ( hp_cs_get_cart_func( 'display_prices_including_tax' ) ) {
				$total -= (float) hp_cs_get_cart_func( 'get_discount_tax' );
			}
		}

		return round( (float) $total, wc_get_price_decimals() );
	}

	private static function calculate_package_weight( $package ) {
		$items        = isset( $package['contents'] ) && is_array( $package['contents'] ) ? $package['contents'] : [];
		$total_weight = 0.0;

		foreach ( $items as $data ) {
			$product = $data['data'] ?? null;
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}
			if ( ! $product->needs_shipping() ) {
				continue;
			}

			$item_weight = (float) $product->get_weight();
			if ( $item_weight ) {
				$total_weight += $item_weight * (int) ( $data['quantity'] ?? 1 );
			}
		}

		return (float) $total_weight;
	}

	private static function parse_number( $number ) {
		$number = str_replace( ',', '.', (string) $number );
		return is_numeric( $number ) ? (float) $number : false;
	}

	public static function compare_numeric_value( $a, $b, $operator ) {
		switch ( $operator ) {
			case 'e':
				return $a == $b;
			case 'gt':
				return $a > $b;
			case 'gte':
				return $a >= $b;
			case 'lt':
				return $a < $b;
			case 'lte':
				return $a <= $b;
		}
		return null;
	}

	public static function group_comparison( $a, $b, $operator ) {
		$a = array_unique( array_map( 'strval', (array) $a ) );
		$b = array_unique( array_map( 'strval', (array) $b ) );

		switch ( $operator ) {
			case 'in':
				return count( array_intersect( $a, $b ) ) > 0;
			case 'notin':
				return count( array_intersect( $a, $b ) ) === 0;
			case 'exclusive':
				return count( array_diff( $a, $b ) ) === 0;
			case 'allin':
				return count( array_diff( $b, $a ) ) === 0;
		}

		return null;
	}

	public static function is_array_comparison( $needle, $haystack, $operator ) {
		if ( $operator === 'is' ) {
			return in_array( $needle, (array) $haystack, true );
		}
		if ( $operator === 'isnot' ) {
			return ! in_array( $needle, (array) $haystack, true );
		}
		return null;
	}
}


