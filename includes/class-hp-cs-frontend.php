<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HP_CS_Frontend {
	private static $instance = null;

	private array $passed_rule_ids = [];
	private array $notices = [];

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 10 );

		add_filter( 'woocommerce_package_rates', [ $this, 'filter_shipping_methods' ], 100, 2 );
		add_filter( 'hp_funnels_shipping_rates_result', [ $this, 'filter_funnel_shipping_rates' ], 10, 4 );

		// Store customer details into session so conditions relying on billing/shipping fields can work reliably.
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'store_customer_details' ], 10, 1 );

		// Messaging.
		add_filter( 'woocommerce_cart_no_shipping_available_html', [ $this, 'no_shipping_message' ], 100, 1 );
		add_filter( 'woocommerce_no_shipping_available_html', [ $this, 'no_shipping_message' ], 100, 1 );

		add_action( 'woocommerce_review_order_before_shipping', [ $this, 'shipping_notice' ], 100 );
		add_action( 'woocommerce_before_cart_totals', [ $this, 'shipping_notice' ], 100 );
		add_filter( 'woocommerce_cart_shipping_method_full_label', [ $this, 'show_zero_cost_rate_amount' ], 100, 2 );

		// Blocks/Store API: expose notices without relying on block assets.
		add_action( 'woocommerce_blocks_loaded', [ $this, 'register_store_api_data' ], 10 );
	}

	public function enqueue_scripts() {
		wp_enqueue_script(
			'hp-conditional-shipping-frontend',
			HP_CS_URL . 'frontend/js/hp-conditional-shipping.js',
			[ 'jquery' ],
			HP_CS_VERSION,
			true
		);
	}

	public function register_store_api_data() {
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) && class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema' ) ) {
			woocommerce_store_api_register_endpoint_data(
				[
					'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
					'namespace'       => 'hp-conditional-shipping',
					'data_callback'   => [ $this, 'store_api_data' ],
					'schema_callback' => [ $this, 'store_api_schema' ],
					'schema_type'     => ARRAY_A,
				]
			);
		}
	}

	public function store_api_data() {
		return [
			'notices' => array_values( array_unique( $this->notices ) ),
		];
	}

	public function store_api_schema() {
		return [
			'notices' => [
				'description' => __( 'Shipping notices', 'hp-conditional-shipping' ),
				'type'        => [ 'array', 'null' ],
				'readonly'    => true,
			],
		];
	}

	/**
	 * Filter shipping methods.
	 */
	public function filter_shipping_methods( $rates, $package ) {
		$result = $this->evaluate_rates( $rates, $package, 'classic' );
		$rates  = $result['rates'];

		// Persist passed rules so messaging works even when WC serves cached rates.
		if ( WC()->session ) {
			WC()->session->set( 'wcs_passed_rule_ids', $this->passed_rule_ids );
		}

		return $rates;
	}

	public function filter_funnel_shipping_rates( $result, $items, $address, $context ) {
		if ( ! is_array( $result ) ) {
			$result = [ 'rates' => [] ];
		}

		$rates   = isset( $result['rates'] ) && is_array( $result['rates'] ) ? $result['rates'] : [];
		$package = $this->build_funnel_package( (array) $items, (array) $address, (array) $context );
		$eval    = $this->evaluate_rates( $rates, $package, 'funnel' );

		$result['rates']                = $eval['rates'];
		$result['notices']              = array_values( array_unique( array_filter( array_merge( (array) ( $result['notices'] ?? [] ), $eval['notices'] ) ) ) );
		$result['restriction_messages'] = $result['notices'];
		$result['blocked']              = ! empty( $rates ) && empty( $eval['rates'] );
		$result['mode']                 = hp_cs_get_mode();

		return $result;
	}

	private function evaluate_rates( array $rates, array $package, string $surface ) {
		$mode                  = hp_cs_get_mode();
		$this->passed_rule_ids = [];
		$this->notices         = [];

		if ( $mode === 'disabled' ) {
			return [
				'rates'   => $rates,
				'notices' => [],
			];
		}

		$disable_keys = [];
		$enable_keys  = [];

		foreach ( hp_cs_get_rulesets( true ) as $ruleset ) {
			$passes = $ruleset->validate( $package );
			if ( $passes ) {
				$this->passed_rule_ids[] = $ruleset->get_id();
			}

			foreach ( $ruleset->get_actions() as $action ) {
				$type = $action['type'] ?? '';

				if ( $type === 'disable_shipping_methods' && $passes ) {
					foreach ( $rates as $key => $rate ) {
						if ( $this->rate_matches_action( $rate, $action, $surface ) ) {
							$disable_keys[ $key ] = true;
							unset( $enable_keys[ $key ] );
						}
					}
				}

				if ( $type === 'enable_shipping_methods' ) {
					foreach ( $rates as $key => $rate ) {
						if ( $this->rate_matches_action( $rate, $action, $surface ) ) {
							if ( $passes ) {
								$enable_keys[ $key ] = true;
								unset( $disable_keys[ $key ] );
							} else {
								$disable_keys[ $key ] = true;
								unset( $enable_keys[ $key ] );
							}
						}
					}
				}

				if ( $passes && $type === 'shipping_notice' ) {
					$this->notices[] = $this->render_notice( $action );
				}

				if ( $passes && $type === 'custom_error_msg' && ! empty( $action['error_msg'] ) ) {
					$this->notices[] = wp_kses_post( (string) $action['error_msg'] );
				}
			}
		}

		$filtered_rates = $rates;
		if ( $mode === 'enforce' ) {
			foreach ( $filtered_rates as $key => $rate ) {
				if ( isset( $disable_keys[ $key ] ) && ! isset( $enable_keys[ $key ] ) ) {
					unset( $filtered_rates[ $key ] );
				}
			}
			$filtered_rates = $this->apply_discount_rules( $filtered_rates, $package, $surface );
		}

		$this->log_evaluation( $surface, $mode, count( $rates ), count( $filtered_rates ), array_keys( $disable_keys ) );

		return [
			'rates'   => $filtered_rates,
			'notices' => array_values( array_unique( array_filter( $this->notices ) ) ),
		];
	}

	private function rate_matches_action( $rate, array $action, string $surface ) {
		if ( $surface === 'funnel' ) {
			$title       = is_array( $rate ) ? (string) ( $rate['serviceName'] ?? $rate['service_name'] ?? $rate['name'] ?? '' ) : '';
			$instance_id = is_array( $rate ) ? (string) ( $rate['serviceCode'] ?? $rate['service_code'] ?? $rate['code'] ?? '' ) : false;
			return hp_cs_method_selected( $title, $instance_id, $action );
		}

		$title = method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : '';
		return hp_cs_method_selected( $title, $this->get_rate_instance_id( $rate ), $action );
	}

	private function build_funnel_package( array $items, array $address, array $context ) {
		$contents                = [];
		$global_discount_percent = max( 0, min( 100, (float) ( $context['global_discount_percent'] ?? 0 ) ) );

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$product = $this->resolve_funnel_product( $item );
			if ( ! $product ) {
				continue;
			}

			$quantity = max( 1, (int) ( $item['quantity'] ?? $item['qty'] ?? 1 ) );
			$line_total = (float) wc_get_price_excluding_tax( $product, [ 'qty' => $quantity ] );
			$item_discount_percent = max( 0, min( 100, (float) ( $item['item_discount_percent'] ?? $item['itemDiscountPercent'] ?? 0 ) ) );
			if ( $item_discount_percent > 0 ) {
				$line_total *= ( 100 - $item_discount_percent ) / 100;
			}
			if ( empty( $item['exclude_global_discount'] ) && empty( $item['excludeGlobalDiscount'] ) && $global_discount_percent > 0 ) {
				$line_total *= ( 100 - $global_discount_percent ) / 100;
			}

			$contents[] = [
				'data'         => $product,
				'quantity'     => $quantity,
				'product_id'   => $product->get_parent_id() ?: $product->get_id(),
				'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
				'line_total'   => max( 0, $line_total ),
			];
		}

		return [
			'contents'    => $contents,
			'destination' => [
				'country'  => (string) ( $address['country'] ?? '' ),
				'state'    => (string) ( $address['state'] ?? '' ),
				'postcode' => (string) ( $address['postcode'] ?? $address['zip'] ?? '' ),
				'city'     => (string) ( $address['city'] ?? '' ),
			],
		];
	}

	private function resolve_funnel_product( array $item ) {
		$product_id = absint( $item['variation_id'] ?? $item['variationId'] ?? $item['product_id'] ?? $item['productId'] ?? $item['id'] ?? 0 );
		$product    = $product_id ? wc_get_product( $product_id ) : false;

		if ( ! $product && ! empty( $item['sku'] ) ) {
			$product_id = wc_get_product_id_by_sku( (string) $item['sku'] );
			$product    = $product_id ? wc_get_product( $product_id ) : false;
		}

		return $product instanceof WC_Product ? $product : false;
	}

	private function apply_discount_rules( array $rates, array $package, string $surface ) {
		foreach ( hp_cs_get_shipping_discount_rules() as $rule ) {
			if ( ( $rule['enabled'] ?? 'no' ) !== 'yes' || ! $this->discount_rule_matches_surface( $rule, $surface ) ) {
				continue;
			}

			$eligible_amount = $this->get_discount_rule_eligible_amount( $package, $rule );
			$min_amount      = max( 0, (float) ( $rule['min_amount'] ?? 0 ) );
			$percent         = max( 0, (float) ( $rule['percentage_discount'] ?? 0 ) );

			if ( $percent <= 0 || $eligible_amount < $min_amount ) {
				continue;
			}

			$discount = $eligible_amount * ( $percent / 100 );
			foreach ( $rates as $key => $rate ) {
				if ( $this->rate_matches_action( $rate, $rule, $surface ) ) {
					$rates[ $key ] = $this->discount_rate( $rate, $discount, $surface );
				}
			}
		}

		return $rates;
	}

	private function discount_rule_matches_surface( array $rule, string $surface ) {
		$rule_surface = (string) ( $rule['surface'] ?? 'classic' );
		return $rule_surface === 'both' || $rule_surface === $surface;
	}

	private function get_discount_rule_eligible_amount( array $package, array $rule ) {
		$shipping_class_raw = (string) ( $rule['shipping_class'] ?? '' );
		$shipping_class     = $shipping_class_raw === '_none' ? '_none' : sanitize_title( $shipping_class_raw );
		$total              = 0.0;

		foreach ( (array) ( $package['contents'] ?? [] ) as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}

			$product = $item['data'];
			$product_shipping_class = $product->get_shipping_class();
			if ( $shipping_class === '_none' && $product_shipping_class !== '' ) {
				continue;
			}
			if ( $shipping_class !== '' && $shipping_class !== '_none' && $product_shipping_class !== $shipping_class ) {
				continue;
			}

			$quantity = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$line     = isset( $item['line_total'] ) ? (float) $item['line_total'] : (float) wc_get_price_excluding_tax( $product, [ 'qty' => $quantity ] );
			$total   += max( 0, $line );
		}

		return $total;
	}

	private function discount_rate( $rate, float $discount, string $surface ) {
		if ( $discount <= 0 ) {
			return $rate;
		}

		if ( $surface === 'funnel' && is_array( $rate ) ) {
			return $this->discount_funnel_rate( $rate, $discount );
		}

		if ( is_object( $rate ) && method_exists( $rate, 'get_cost' ) && method_exists( $rate, 'set_cost' ) ) {
			$cost     = (float) $rate->get_cost();
			$new_cost = max( 0, $cost - $discount );
			$rate->set_cost( $new_cost );

			if ( method_exists( $rate, 'get_taxes' ) && method_exists( $rate, 'set_taxes' ) ) {
				$taxes = (array) $rate->get_taxes();
				if ( $cost > 0 && ! empty( $taxes ) ) {
					$ratio = $new_cost / $cost;
					foreach ( $taxes as $tax_id => $tax ) {
						$taxes[ $tax_id ] = (float) $tax * $ratio;
					}
					$rate->set_taxes( $taxes );
				}
			}
		}

		return $rate;
	}

	private function discount_funnel_rate( array $rate, float $discount ) {
		foreach ( [ 'shipmentCost', 'shipping_amount_raw', 'base_amount_raw', 'shipment_cost' ] as $field ) {
			if ( isset( $rate[ $field ] ) && is_numeric( $rate[ $field ] ) ) {
				$cost           = (float) $rate[ $field ];
				$rate[ $field ] = max( 0, $cost - $discount );
				$rate['shipmentCost'] = $rate[ $field ];
				return $rate;
			}
		}

		return $rate;
	}

	public function show_zero_cost_rate_amount( $label, $method ) {
		if ( ! is_object( $method ) || ! method_exists( $method, 'get_cost' ) ) {
			return $label;
		}

		$method_id = method_exists( $method, 'get_method_id' ) ? (string) $method->get_method_id() : '';
		if ( $method_id === 'free_shipping' || (float) $method->get_cost() > 0 || strpos( (string) $label, 'woocommerce-Price-amount' ) !== false ) {
			return $label;
		}

		return $label . ': ' . wc_price( 0 );
	}

	private function log_evaluation( string $surface, string $mode, int $before_count, int $after_count, array $disabled_keys ) {
		do_action(
			'hp_monitor_event',
			'conditional_shipping.evaluated',
			[
				'plugin'        => 'hp-conditional-shipping',
				'surface'       => $surface,
				'mode'          => $mode,
				'rates_before'  => $before_count,
				'rates_after'   => $after_count,
				'disabled_keys' => array_values( array_map( 'strval', $disabled_keys ) ),
				'notices_count' => count( $this->notices ),
			]
		);
	}

	public function get_rate_instance_id( $rate ) {
		$instance_id = false;

		if ( method_exists( $rate, 'get_instance_id' ) && strlen( (string) $rate->get_instance_id() ) > 0 ) {
			$instance_id = $rate->get_instance_id();
		} else {
			$ids = explode( ':', (string) $rate->id );
			if ( count( $ids ) >= 2 ) {
				$instance_id = $ids[1];
			}
		}

		return apply_filters( 'hp_cs_get_instance_id', $instance_id, $rate );
	}

	/**
	 * Store customer details from posted checkout data.
	 */
	public function store_customer_details( $post_data ) {
		if ( ! WC()->customer ) {
			return;
		}

		$data = [];
		parse_str( (string) $post_data, $data );

		$attrs = [
			'billing_first_name', 'billing_last_name', 'billing_company',
			'shipping_first_name', 'shipping_last_name', 'shipping_company',
			'billing_email', 'billing_phone',
			'shipping_city', 'shipping_postcode', 'shipping_country', 'shipping_state',
		];

		$same_addr = false;
		if ( ! isset( $data['ship_to_different_address'] ) || $data['ship_to_different_address'] != '1' ) {
			$same_addr = true;
			$attrs = [
				'billing_first_name', 'billing_last_name', 'billing_company',
				'billing_email', 'billing_phone',
				'billing_city', 'billing_postcode', 'billing_country', 'billing_state',
			];
		}

		foreach ( $attrs as $attr ) {
			WC()->customer->set_props(
				[
					$attr => isset( $data[ $attr ] ) ? wp_unslash( $data[ $attr ] ) : null,
				]
			);

			if ( $same_addr ) {
				$attr2 = str_replace( 'billing', 'shipping', $attr );
				WC()->customer->set_props(
					[
						$attr2 => isset( $data[ $attr ] ) ? wp_unslash( $data[ $attr ] ) : null,
					]
				);
			}
		}
	}

	/**
	 * Shipping notices output (classic).
	 */
	public function shipping_notice() {
		$notices = [];

		foreach ( $this->get_passed_rules() as $ruleset ) {
			foreach ( $ruleset->get_actions() as $action ) {
				if ( ( $action['type'] ?? '' ) === 'shipping_notice' ) {
					$notices[] = $this->render_notice( $action );
				}
			}
		}

		$notices = array_values( array_unique( array_filter( $notices ) ) );
		if ( empty( $notices ) ) {
			return;
		}

		echo sprintf( '<div id="wcs-notices-pending" style="display:none;">%s</div>', implode( "\n", $notices ) );
	}

	private function render_notice( $action ) {
		$notice = isset( $action['notice'] ) ? (string) $action['notice'] : '';
		if ( $notice === '' ) {
			return '';
		}
		$style = isset( $action['notice_style'] ) ? (string) $action['notice_style'] : '';
		$notice = do_shortcode( $notice );
		return sprintf( '<div class="conditional-shipping-notice conditional-shipping-notice-style-%s">%s</div>', esc_attr( $style ), $notice );
	}

	/**
	 * Custom \"no shipping available\" message.
	 */
	public function no_shipping_message( $orig_msg ) {
		$msgs = [];
		$i    = 1;

		foreach ( $this->get_passed_rules() as $ruleset ) {
			foreach ( $ruleset->get_actions() as $action ) {
				if ( ( $action['type'] ?? '' ) === 'custom_error_msg' ) {
					$error_msg = $action['error_msg'] ?? '';
					if ( $error_msg !== '' ) {
						$msgs[] = sprintf( '<div class="conditional-shipping-custom-error-msg i-%d">%s</div>', $i, wp_kses_post( $error_msg ) );
						$i++;
					}
				}
			}
		}

		return ! empty( $msgs ) ? implode( '', $msgs ) : $orig_msg;
	}

	/**
	 * Get passed rules from session.
	 *
	 * @return HP_CS_Ruleset[]
	 */
	private function get_passed_rules() {
		if ( ! WC()->session ) {
			return [];
		}

		$passed_rule_ids = (array) WC()->session->get( 'wcs_passed_rule_ids' );
		if ( empty( $passed_rule_ids ) ) {
			return [];
		}

		$passed_rules = [];
		foreach ( hp_cs_get_rulesets( true ) as $ruleset ) {
			if ( in_array( $ruleset->get_id(), $passed_rule_ids, true ) ) {
				$passed_rules[] = $ruleset;
			}
		}

		return $passed_rules;
	}
}

