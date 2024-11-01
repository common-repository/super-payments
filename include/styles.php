<?php
/**
 * Register and enqueue styles.
 *
 * @package super-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue scripts.
 */
function wcsp_super_payments_css() {
	wp_enqueue_style(
		'super-payments',
		WC_Super_Payments::plugin_url() . 'assets/css/super-payments.css',
		'',
		PLUGIN_VERSION
	);
}

add_action( 'wp_enqueue_scripts', 'wcsp_super_payments_css' );
