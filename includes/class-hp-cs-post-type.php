<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HP_CS_Post_Type {
	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ], 10 );
	}

	public function register_post_type() {
		// Keep CPT name identical to reference plugin for drop-in compatibility.
		register_post_type(
			'wcs_ruleset',
			[
				'labels'              => [
					'name'          => __( 'Conditional Shipping Rulesets', 'hp-conditional-shipping' ),
					'singular_name' => __( 'Conditional Shipping Ruleset', 'hp-conditional-shipping' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'has_archive'         => false,
				'supports'            => [ 'title' ],
				'show_in_rest'        => false,
				'capability_type'     => 'post',
			]
		);
	}
}


