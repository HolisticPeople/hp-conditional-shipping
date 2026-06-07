<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'HP_CS_RULESET_JSON_REQUEST_MAX_LENGTH' ) ) {
	define( 'HP_CS_RULESET_JSON_REQUEST_MAX_LENGTH', 65536 );
}

if ( ! defined( 'HP_CS_ADMIN_TEXT_REQUEST_MAX_LENGTH' ) ) {
	define( 'HP_CS_ADMIN_TEXT_REQUEST_MAX_LENGTH', 200 );
}

if ( ! defined( 'HP_CS_ADMIN_TEXTAREA_REQUEST_MAX_LENGTH' ) ) {
	define( 'HP_CS_ADMIN_TEXTAREA_REQUEST_MAX_LENGTH', 2000 );
}

class HP_CS_Admin {
	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'woocommerce_get_sections_shipping', [ $this, 'register_section' ], 10, 1 );
		add_action( 'woocommerce_settings_shipping', [ $this, 'output' ] );

		add_action( 'woocommerce_settings_save_shipping', [ $this, 'save_settings' ], 10 );
		add_action( 'woocommerce_settings_save_shipping', [ $this, 'save_ruleset' ], 20 );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_ajax_hp_cs_toggle_ruleset', [ $this, 'toggle_ruleset' ] );

		add_filter( 'plugin_action_links_' . plugin_basename( HP_CS_FILE ), [ $this, 'add_conditions_link' ] );
		add_filter( 'woocommerce_get_settings_shipping', [ $this, 'hide_default_settings' ], 100, 2 );
	}

	public function register_section( $sections ) {
		$sections['woo_conditional_shipping'] = __( 'Conditions', 'hp-conditional-shipping' );
		return $sections;
	}

	public function add_conditions_link( $links ) {
		$url  = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Conditions', 'hp-conditional-shipping' ) . '</a>';
		return array_merge( [ $link ], $links );
	}

	public function admin_enqueue_scripts() {
		if ( ! isset( $_GET['section'] ) || $_GET['section'] !== 'woo_conditional_shipping' ) {
			return;
		}

		do_action( 'hp_zen_enqueue_admin_surface', 'hp-conditional-shipping' );

		// jQuery UI dependencies for visual editor.
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-ui-autocomplete' );

		// Main admin script.
		wp_enqueue_script(
			'hp-conditional-shipping-admin',
			HP_CS_URL . 'admin/js/hp-conditional-shipping-admin.js',
			[ 'jquery', 'wp-util', 'jquery-ui-sortable', 'jquery-ui-datepicker', 'jquery-ui-autocomplete' ],
			HP_CS_VERSION,
			true
		);

		wp_localize_script(
			'hp-conditional-shipping-admin',
			'hp_cs_admin',
			[
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'hp-cs-toggle-ruleset' ),
				'nonces'    => [
					'ruleset_toggle' => wp_create_nonce( 'hp-cs-toggle-ruleset' ),
				],
				'ajax_urls' => [
					'toggle_ruleset' => admin_url( 'admin-ajax.php?action=hp_cs_toggle_ruleset' ),
				],
			]
		);

		// Admin CSS.
		wp_enqueue_style(
			'hp-conditional-shipping-admin',
			HP_CS_URL . 'admin/css/hp-conditional-shipping-admin.css',
			[],
			HP_CS_VERSION
		);
	}

	public function output() {
		global $current_section;
		global $hide_save_button;

		if ( 'woo_conditional_shipping' !== $current_section ) {
			return;
		}

		$hide_save_button = true;

		$action         = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : false;
		$ruleset_id_raw = isset( $_GET['ruleset_id'] ) ? wp_unslash( $_GET['ruleset_id'] ) : false;
		$is_new_ruleset = $ruleset_id_raw === 'new';
		$ruleset_id     = false;

		if ( $ruleset_id_raw ) {
			$ruleset_id = $is_new_ruleset ? false : $this->parse_positive_decimal_id( $ruleset_id_raw );

			// Delete ruleset.
			if ( $ruleset_id && $action === 'delete' && get_post_type( $ruleset_id ) === 'wcs_ruleset' ) {
				check_admin_referer( 'hp-cs-delete-ruleset' );
				wp_delete_post( $ruleset_id, false );
				hp_cs_bump_cache_versions();
				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping' ) );
				exit;
			}

			// Duplicate ruleset.
			if ( $ruleset_id && $action === 'duplicate' && get_post_type( $ruleset_id ) === 'wcs_ruleset' ) {
				check_admin_referer( 'hp-cs-duplicate-ruleset' );
				$cloned_id = $this->clone_ruleset( $ruleset_id );
				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping&ruleset_id=' . $cloned_id ) );
				exit;
			}

			if ( $is_new_ruleset || $ruleset_id ) {
				$ruleset = new HP_CS_Ruleset( $ruleset_id );
				include HP_CS_PATH . 'includes/admin/views/ruleset.php';
				return;
			}
		}

		$rulesets        = hp_cs_get_rulesets( false );
		$add_ruleset_url = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping&ruleset_id=new' );

		include HP_CS_PATH . 'includes/admin/views/settings.php';
	}

	public function save_settings() {
		global $current_section;

		if ( 'woo_conditional_shipping' !== $current_section ) {
			return;
		}

		if ( ! isset( $_POST['wcs_settings'] ) ) {
			return;
		}

		check_admin_referer( 'woocommerce-settings' );

		$mode = isset( $_POST['hp_cs_mode'] ) ? sanitize_key( wp_unslash( $_POST['hp_cs_mode'] ) ) : 'audit';
		update_option( 'hp_cs_mode', in_array( $mode, [ 'audit', 'enforce', 'disabled' ], true ) ? $mode : 'audit', false );

		$ruleset_order = isset( $_POST['wcs_ruleset_order'] ) ? (array) wc_clean( wp_unslash( $_POST['wcs_ruleset_order'] ) ) : [];
		$order = [];
		$loop  = 0;
		foreach ( $ruleset_order as $rid ) {
			$order[ esc_attr( $rid ) ] = $loop;
			$loop++;
		}
		update_option( 'wcs_ruleset_order', $order );
		update_option( 'hp_cs_shipping_discount_rules', $this->sanitize_discount_rules( $_POST['hp_cs_shipping_discount_rules'] ?? [] ), false );

		hp_cs_bump_cache_versions();
	}

	public function save_ruleset() {
		global $current_section;

		if ( 'woo_conditional_shipping' !== $current_section ) {
			return;
		}

		if ( ! isset( $_POST['ruleset_id'], $_POST['ruleset_name'] ) ) {
			return;
		}

		check_admin_referer( 'woocommerce-settings' );

		$ruleset_id_raw = sanitize_text_field( wp_unslash( $_POST['ruleset_id'] ) );
		$ruleset_id     = 0;
		$post           = null;

		if ( $ruleset_id_raw && $ruleset_id_raw !== '0' ) {
			$ruleset_id = $this->parse_positive_decimal_id( $ruleset_id_raw );
			if ( ! $ruleset_id ) {
				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping' ) );
				exit;
			}

			$post = get_post( $ruleset_id );
			if ( ! $post || get_post_type( $post ) !== 'wcs_ruleset' ) {
				$post = null;
			}
		}

		$title = wp_strip_all_tags( wp_unslash( $_POST['ruleset_name'] ) );

		if ( ! $post ) {
			$post_id = wp_insert_post(
				[
					'post_type'   => 'wcs_ruleset',
					'post_title'  => $title,
					'post_status' => 'publish',
				]
			);
			$post = get_post( $post_id );
		} else {
			$post->post_title = $title;
			wp_update_post( $post, false );
		}

		$operator = isset( $_POST['wcs_operator'] ) ? sanitize_text_field( wp_unslash( $_POST['wcs_operator'] ) ) : 'and';
		update_post_meta( $post->ID, '_wcs_operator', in_array( $operator, [ 'and', 'or' ], true ) ? $operator : 'and' );

		// v0.1 parity editor: accept JSON blobs (preferred) OR legacy array post structure.
		if ( isset( $_POST['wcs_conditions_json'] ) && is_string( $_POST['wcs_conditions_json'] ) ) {
			$conditions = $this->decode_ruleset_json_array( $_POST['wcs_conditions_json'] );
		} else {
			$conditions = isset( $_POST['wcs_conditions'] ) ? (array) $_POST['wcs_conditions'] : [];
		}
		$conditions = array_values( $this->sanitize_deep( $conditions ) );
		update_post_meta( $post->ID, '_wcs_conditions', $conditions );

		if ( isset( $_POST['wcs_actions_json'] ) && is_string( $_POST['wcs_actions_json'] ) ) {
			$actions = $this->decode_ruleset_json_array( $_POST['wcs_actions_json'] );
		} else {
			$actions = isset( $_POST['wcs_actions'] ) ? (array) $_POST['wcs_actions'] : [];
		}
		$actions = array_values( $this->sanitize_deep( $actions ) );
		update_post_meta( $post->ID, '_wcs_actions', $actions );

		$enabled = ( isset( $_POST['ruleset_enabled'] ) && $_POST['ruleset_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $post->ID, '_wcs_enabled', $enabled );

		hp_cs_bump_cache_versions();

		wp_safe_redirect(
			add_query_arg(
				[
					'ruleset_id' => $post->ID,
				],
				admin_url( 'admin.php?page=wc-settings&tab=shipping&section=woo_conditional_shipping' )
			)
		);
		exit;
	}

	public function toggle_ruleset() {
		check_ajax_referer( 'hp-cs-toggle-ruleset', 'security' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ], 403 );
		}

		$ruleset_id = isset( $_POST['id'] ) ? $this->parse_positive_decimal_id( wp_unslash( $_POST['id'] ) ) : 0;
		$post       = $ruleset_id ? get_post( $ruleset_id ) : null;

		if ( ! $post || get_post_type( $post ) !== 'wcs_ruleset' ) {
			wp_send_json_error( [ 'message' => 'Invalid ruleset' ], 422 );
		}

		$enabled    = get_post_meta( $post->ID, '_wcs_enabled', true ) === 'yes';
		$new_status = $enabled ? 'no' : 'yes';
		update_post_meta( $post->ID, '_wcs_enabled', $new_status );

		hp_cs_bump_cache_versions();

		wp_send_json_success(
			[
				'enabled' => ( get_post_meta( $post->ID, '_wcs_enabled', true ) === 'yes' ),
			]
		);
	}

	private function clone_ruleset( $ruleset_id ) {
		$ruleset = get_post( $ruleset_id );

		$post_id = wp_insert_post(
			[
				'post_type'   => 'wcs_ruleset',
				'post_title'  => sprintf( __( '%s (Clone)', 'hp-conditional-shipping' ), $ruleset->post_title ),
				'post_status' => 'publish',
			]
		);

		$meta_keys = [ '_wcs_operator', '_wcs_conditions', '_wcs_actions' ];
		foreach ( $meta_keys as $meta_key ) {
			$values = get_post_meta( $ruleset->ID, $meta_key, true );
			update_post_meta( $post_id, $meta_key, $values );
		}

		update_post_meta( $post_id, '_wcs_enabled', 'no' );

		hp_cs_bump_cache_versions();

		return $post_id;
	}

	private function parse_positive_decimal_id( $value ): int {
		if ( ! is_scalar( $value ) ) {
			return 0;
		}

		$value = trim( (string) $value );
		if ( ! preg_match( '/^[1-9][0-9]*$/', $value ) ) {
			return 0;
		}

		$max_int = (string) PHP_INT_MAX;
		if ( strlen( $value ) > strlen( $max_int ) || ( strlen( $value ) === strlen( $max_int ) && strcmp( $value, $max_int ) > 0 ) ) {
			return 0;
		}

		return (int) $value;
	}

	private function decode_ruleset_json_array( $value ): array {
		$value = wp_unslash( $value );
		if ( ! is_string( $value ) || strlen( $value ) > HP_CS_RULESET_JSON_REQUEST_MAX_LENGTH ) {
			return [];
		}

		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	private function sanitize_deep( $value ) {
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $k => $v ) {
				$out[ sanitize_key( (string) $k ) ] = $this->sanitize_deep( $v );
			}
			return $out;
		}

		if ( is_string( $value ) ) {
			return wc_clean( wp_unslash( $value ) );
		}

		if ( is_numeric( $value ) ) {
			return $value;
		}

		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}

		return $value;
	}

	private function sanitize_discount_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return [];
		}

		$sanitized = [];
		$order     = 0;

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$label              = $this->sanitize_request_text( $rule['label'] ?? '' );
			$shipping_class_raw = $this->sanitize_request_text( $rule['shipping_class'] ?? '' );
			$shipping_class     = $shipping_class_raw === '_none' ? '_none' : sanitize_title( $shipping_class_raw );
			$percent            = $this->parse_request_decimal( $rule['percentage_discount'] ?? 0, 100 );
			$min_amount         = $this->parse_request_decimal( $rule['min_amount'] ?? 0, 999999 );

			if ( $label === '' && $shipping_class === '' && $percent <= 0 && $min_amount <= 0 ) {
				continue;
			}

			$surface_raw = $this->sanitize_request_text( $rule['surface'] ?? 'classic' );
			$surface     = sanitize_key( $surface_raw );
			if ( $surface !== $surface_raw || ! in_array( $surface, [ 'classic', 'funnel', 'both' ], true ) ) {
				$surface = 'classic';
			}

			$method_ids = $this->sanitize_shipping_method_ids( $rule['shipping_method_ids'] ?? [ '_all' ] );
			if ( empty( $method_ids ) ) {
				$method_ids = [ '_all' ];
			}

			$sanitized[] = hp_cs_normalize_discount_rule(
				[
					'enabled'                    => ! empty( $rule['enabled'] ) ? 'yes' : 'no',
					'label'                      => $label,
					'shipping_class'             => $shipping_class,
					'min_amount'                 => max( 0.0, $min_amount ),
					'percentage_discount'        => max( 0.0, $percent ),
					'deduct_sale_discount'       => ! empty( $rule['deduct_sale_discount'] ) ? 'yes' : 'no',
					'deduct_coupon_discount'     => ! empty( $rule['deduct_coupon_discount'] ) ? 'yes' : 'no',
					'surface'                    => $surface,
					'shipping_method_ids'        => $method_ids,
					'shipping_method_name_match' => $this->sanitize_request_textarea( $rule['shipping_method_name_match'] ?? '' ),
					'order'                      => $order++,
				]
			);
		}

		return $sanitized;
	}

	private function parse_request_decimal( $value, float $max ): float {
		if ( ! is_scalar( $value ) ) {
			return 0.0;
		}

		$value = trim( (string) wp_unslash( $value ) );
		if ( $value === '' || strlen( $value ) > 32 || ! preg_match( '/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/', $value ) ) {
			return 0.0;
		}

		return min( $max, max( 0, (float) wc_format_decimal( $value ) ) );
	}

	private function sanitize_request_text( $value, int $max_length = HP_CS_ADMIN_TEXT_REQUEST_MAX_LENGTH ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) wp_unslash( $value ) );
		if ( strlen( $value ) > $max_length ) {
			$value = substr( $value, 0, $max_length );
		}

		return sanitize_text_field( $value );
	}

	private function sanitize_request_textarea( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) wp_unslash( $value ) );
		if ( strlen( $value ) > HP_CS_ADMIN_TEXTAREA_REQUEST_MAX_LENGTH ) {
			$value = substr( $value, 0, HP_CS_ADMIN_TEXTAREA_REQUEST_MAX_LENGTH );
		}

		return sanitize_textarea_field( $value );
	}

	private function sanitize_shipping_method_ids( $value ): array {
		$values = is_array( $value ) ? wp_unslash( $value ) : [ $value ];
		$out    = [];

		foreach ( $values as $method_id ) {
			if ( ! is_scalar( $method_id ) ) {
				continue;
			}

			$method_id = trim( (string) $method_id );
			if ( strlen( $method_id ) > HP_CS_ADMIN_TEXT_REQUEST_MAX_LENGTH ) {
				continue;
			}

			$method_id = sanitize_text_field( $method_id );
			if ( $method_id === '_all' || $method_id === '_name_match' || preg_match( '/^[A-Za-z0-9:_-]+$/', $method_id ) ) {
				$out[] = $method_id;
			}
		}

		return array_values( array_unique( array_filter( $out ) ) );
	}

	public function hide_default_settings( $settings, $section ) {
		if ( $section === 'woo_conditional_shipping' ) {
			return [];
		}
		return $settings;
	}
}
