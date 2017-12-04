<?php
/**
 * Jetpack_WooCommerce_Analytics is ported from the Jetpack_Google_Analytics code.
 *
 * @package Jetpack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_basename( 'classes/wp-woocommerce-analytics-utils.php' );
require_once plugin_basename( 'classes/wp-woocommerce-analytics-universal.php' );

/**
 * Class Jetpack_WooCommerce_Analytics
 * Instantiate WooCommerce Analytics
 */
class Jetpack_WooCommerce_Analytics {

	/**
	 * Instance of this class
	 *
	 * @var Jetpack_WooCommerce_Analytics - Static property to hold our singleton instance
	 */
	private static $instance = false;

	/**
	 * Instance of the Universal functions
	 *
	 * @var Static property to hold concrete analytics impl that does the work (universal or legacy)
	 */
	private static $analytics = false;

	/**
	 * This is our constructor, which is private to force the use of get_instance()
	 *
	 * @return void
	 */
	private function __construct() {
		$analytics = new Jetpack_WooCommerce_Analytics_Universal();
	}

	/**
	 * Function to instantiate our class and make it a singleton
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

global $jetpack_woocommerce_analytics;
$jetpack_woocommerce_analytics = Jetpack_WooCommerce_Analytics::get_instance();
