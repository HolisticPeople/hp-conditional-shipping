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

		// Store customer details into session so conditions relying on billing/shipping fields can work reliably.
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'store_customer_details' ], 10, 1 );

		// Messaging.
		add_filter( 'woocommerce_cart_no_shipping_available_html', [ $this, 'no_shipping_message' ], 100, 1 );
		add_filter( 'woocommerce_no_shipping_available_html', [ $this, 'no_shipping_message' ], 100, 1 );

		add_action( 'woocommerce_review_order_before_shipping', [ $this, 'shipping_notice' ], 100 );
		add_action( 'woocommerce_before_cart_totals', [ $this, 'shipping_notice' ], 100 );

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
		$rulesets            = hp_cs_get_rulesets( true );
		$this->passed_rule_ids = [];
		$this->notices         = [];

		$disable_keys = [];
		$enable_keys  = [];

		foreach ( $rulesets as $ruleset ) {
			$passes = $ruleset->validate( $package );
			if ( $passes ) {
				$this->passed_rule_ids[] = $ruleset->get_id();
			}

			foreach ( $ruleset->get_actions() as $action ) {
				$type = $action['type'] ?? '';

				if ( $type === 'disable_shipping_methods' ) {
					if ( $passes ) {
						foreach ( $rates as $key => $rate ) {
							$instance_id  = $this->get_rate_instance_id( $rate );
							$method_title = is_callable( [ $rate, 'get_label' ] ) ? $rate->get_label() : false;
							if ( hp_cs_method_selected( $method_title, $instance_id, $action ) ) {
								$disable_keys[ $key ] = true;
								unset( $enable_keys[ $key ] );
							}
						}
					}
				}

				if ( $type === 'enable_shipping_methods' ) {
					foreach ( $rates as $key => $rate ) {
						$instance_id  = $this->get_rate_instance_id( $rate );
						$method_title = is_callable( [ $rate, 'get_label' ] ) ? $rate->get_label() : false;
						if ( hp_cs_method_selected( $method_title, $instance_id, $action ) ) {
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

				if ( $type === 'shipping_notice' ) {
					if ( $passes ) {
						$this->notices[] = $this->render_notice( $action );
					}
				}
			}
		}

		foreach ( $rates as $key => $rate ) {
			if ( isset( $disable_keys[ $key ] ) && ! isset( $enable_keys[ $key ] ) ) {
				unset( $rates[ $key ] );
			}
		}

		// Persist passed rules so messaging works even when WC serves cached rates.
		if ( WC()->session ) {
			WC()->session->set( 'wcs_passed_rule_ids', $this->passed_rule_ids );
		}

		return $rates;
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


