<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'hp-conditional-shipping-admin',
			HP_CS_URL . 'admin/js/hp-conditional-shipping-admin.js',
			[ 'jquery', 'wp-util', 'jquery-ui-sortable' ],
			HP_CS_VERSION,
			true
		);

		wp_localize_script(
			'hp-conditional-shipping-admin',
			'hp_cs_admin',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'hp-cs-toggle-ruleset' ),
			]
		);
	}

	public function output() {
		global $current_section;
		global $hide_save_button;

		if ( 'woo_conditional_shipping' !== $current_section ) {
			return;
		}

		$hide_save_button = true;

		$action    = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : false;
		$ruleset_id = isset( $_GET['ruleset_id'] ) ? wp_unslash( $_GET['ruleset_id'] ) : false;

		if ( $ruleset_id ) {
			if ( $ruleset_id === 'new' ) {
				$ruleset_id = false;
			} else {
				$ruleset_id = absint( $ruleset_id );
			}

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

			$ruleset = new HP_CS_Ruleset( $ruleset_id );
			include HP_CS_PATH . 'includes/admin/views/ruleset.php';
			return;
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

		$ruleset_order = isset( $_POST['wcs_ruleset_order'] ) ? (array) wc_clean( wp_unslash( $_POST['wcs_ruleset_order'] ) ) : [];
		$order = [];
		$loop  = 0;
		foreach ( $ruleset_order as $rid ) {
			$order[ esc_attr( $rid ) ] = $loop;
			$loop++;
		}
		update_option( 'wcs_ruleset_order', $order );

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

		$ruleset_id = sanitize_text_field( wp_unslash( $_POST['ruleset_id'] ) );
		$post = null;

		if ( $ruleset_id && $ruleset_id !== '0' ) {
			$post = get_post( absint( $ruleset_id ) );
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
			$decoded = json_decode( wp_unslash( $_POST['wcs_conditions_json'] ), true );
			$conditions = is_array( $decoded ) ? $decoded : [];
		} else {
			$conditions = isset( $_POST['wcs_conditions'] ) ? (array) $_POST['wcs_conditions'] : [];
		}
		$conditions = array_values( $this->sanitize_deep( $conditions ) );
		update_post_meta( $post->ID, '_wcs_conditions', $conditions );

		if ( isset( $_POST['wcs_actions_json'] ) && is_string( $_POST['wcs_actions_json'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['wcs_actions_json'] ), true );
			$actions = is_array( $decoded ) ? $decoded : [];
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

		$ruleset_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
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

	public function hide_default_settings( $settings, $section ) {
		if ( $section === 'woo_conditional_shipping' ) {
			return [];
		}
		return $settings;
	}
}


