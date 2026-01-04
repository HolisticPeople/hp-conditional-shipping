<?php
/*
Plugin Name: HP Conditional Shipping
Plugin URI:  https://holisticpeople.com
Description: Drop-in replacement for Woo Conditional Shipping Pro. Filters shipping methods based on rulesets stored in the wcs_ruleset CPT.
Version:     0.2.9
Author:      HolisticPeople
Text Domain: hp-conditional-shipping
Domain Path: /languages
WC requires at least: 8.0.0
WC tested up to: 10.0.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'HP_CS_VERSION' ) ) {
	define( 'HP_CS_VERSION', '0.2.9' );
}

if ( ! defined( 'HP_CS_FILE' ) ) {
	define( 'HP_CS_FILE', __FILE__ );
}

if ( ! defined( 'HP_CS_PATH' ) ) {
	define( 'HP_CS_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'HP_CS_URL' ) ) {
	define( 'HP_CS_URL', plugin_dir_url( __FILE__ ) );
}

require_once HP_CS_PATH . 'includes/hp-cs-utils.php';
require_once HP_CS_PATH . 'includes/class-hp-cs-post-type.php';
require_once HP_CS_PATH . 'includes/class-hp-cs-ruleset.php';
require_once HP_CS_PATH . 'includes/class-hp-cs-filters.php';
require_once HP_CS_PATH . 'includes/class-hp-cs-filters-pro.php';
require_once HP_CS_PATH . 'includes/class-hp-cs-frontend.php';
require_once HP_CS_PATH . 'includes/class-hp-cs-admin.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Bootstrap plugin.
 */
function hp_cs_init() {
	// WooCommerce not active, abort.
	if ( ! defined( 'WC_VERSION' ) ) {
		return;
	}

	HP_CS_Post_Type::instance();
	HP_CS_Frontend::instance();

	if ( is_admin() ) {
		HP_CS_Admin::instance();
	}
}
add_action( 'plugins_loaded', 'hp_cs_init', 10 );


