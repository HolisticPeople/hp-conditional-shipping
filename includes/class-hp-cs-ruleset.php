<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HP_CS_Ruleset {
	private int $post_id;
	private ?WP_Post $post = null;

	public function __construct( $post_id ) {
		$this->post_id = absint( $post_id );
	}

	public function get_id() {
		return $this->post_id;
	}

	public function get_title( $context = 'view' ) {
		$post = $this->get_post();
		if ( $post && $post->post_title ) {
			return $post->post_title;
		}

		return $context === 'edit' ? '' : __( 'Ruleset', 'hp-conditional-shipping' );
	}

	public function get_post() {
		if ( $this->post === null ) {
			$this->post = get_post( $this->post_id );
		}
		return $this->post;
	}

	public function get_enabled() {
		$enabled        = get_post_meta( $this->post_id, '_wcs_enabled', true );
		$enabled_exists = metadata_exists( 'post', $this->post_id, '_wcs_enabled' );
		if ( ! $enabled_exists ) {
			return true;
		}
		return $enabled === 'yes';
	}

	public function get_conditions() {
		$conditions = get_post_meta( $this->post_id, '_wcs_conditions', true );
		return is_array( $conditions ) ? array_values( $conditions ) : [];
	}

	public function get_actions() {
		$actions = get_post_meta( $this->post_id, '_wcs_actions', true );
		return is_array( $actions ) ? array_values( $actions ) : [];
	}

	public function get_conditions_operator() {
		$operator = get_post_meta( $this->post_id, '_wcs_operator', true );
		return in_array( $operator, [ 'and', 'or' ], true ) ? $operator : 'and';
	}

	/**
	 * Validate ruleset conditions for a given package.
	 *
	 * Important: compatibility with reference plugin:
	 * - Filter callbacks return TRUE when condition FAILS, FALSE when it PASSES.
	 */
	public function validate( $package ) {
		if ( ! is_array( $package ) ) {
			$package = [];
		}
		if ( ! isset( $package['contents'] ) || ! is_array( $package['contents'] ) ) {
			$package['contents'] = [];
		}
		if ( empty( $package['contents'] ) && WC()->cart ) {
			$package['contents'] = WC()->cart->get_cart();
		}

		$results = [];
		foreach ( $this->get_conditions() as $index => $condition ) {
			if ( ! is_array( $condition ) || empty( $condition['type'] ) ) {
				continue;
			}
			$type     = (string) $condition['type'];
			$callable = HP_CS_Filters::get_callable_for_type( $type );
			$results[ $index ] = (bool) call_user_func( $callable, $condition, $package );
		}

		// OR: enough that one condition passed (i.e., one FALSE in results).
		if ( $this->get_conditions_operator() === 'or' ) {
			return in_array( false, $results, true ) === true;
		}

		// AND: all conditions must pass (i.e., no TRUE in results).
		return in_array( true, $results, true ) === false;
	}
}


