<?php
/**
 * Super Payments public scripts.
 *
 * @package super-payments
 */

/**
 * Adds the superjs sdk script to the page and adds another script to initialize it.
 */
function wcsp_super_js() {
	if ( class_exists( 'WC_Super_Payments' ) ) {
		$super_payments      = new WC_Super_Payments_Gateway();
		$publishable_api_key = $super_payments->get_option( 'publishable_api_key' );
		$integration_id      = $super_payments->get_option( 'integration_id' );
		$page                = wcsp_get_page_type();

		$test_mode     = $super_payments->get_option( 'test_mode' );
		$plugin_config = WC_Super_Payments::plugin_config( $test_mode );

		if ( ! empty( $publishable_api_key ) && ! empty( $integration_id ) ) {
			wp_enqueue_script(
				'super-payments-super-js',
				$plugin_config['js_sdk_url'],
				[],
				PLUGIN_VERSION,
				true
			);

			wp_enqueue_script(
				'super-payments-init-super-js',
				WC_Super_Payments::plugin_url() . 'assets/js/init-super.js',
				[ 'super-payments-super-js' ],
				PLUGIN_VERSION,
				true
			);

			$vars = [
				'publishable_api_key' => $publishable_api_key,
				'integration_id'      => $integration_id,
				'page'                => $page,
				'plugin_version'      => PLUGIN_VERSION,
				'woocommerce_version' => WC_VERSION,
			];
			wp_localize_script( 'super-payments-init-super-js', 'superPaymentsPublicVars', $vars );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'wcsp_super_js' );
add_action( 'admin_enqueue_scripts', 'wcsp_super_js' );

/**
 * Adds the checkout js sdk.
 */
function wcsp_super_checkout_js() {
	if ( class_exists( 'WC_Super_Payments' ) ) {
		$super_payments = new WC_Super_Payments_Gateway();
		$test_mode      = $super_payments->get_option( 'test_mode' );
		$plugin_config  = WC_Super_Payments::plugin_config( $test_mode );

		wp_enqueue_script(
			'super-payments-checkout-js',
			$plugin_config['checkout_js_sdk_url'],
			[],
			PLUGIN_VERSION,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'wcsp_super_checkout_js' );

/**
 * Adds the embedded checkout script to the page.
 */
function wcsp_embedded_checkout_script() {
	if ( class_exists( 'WC_Super_Payments' ) ) {
		wp_enqueue_script(
			'super-payments-embedded-checkout',
			WC_Super_Payments::plugin_url() . 'assets/js/embedded-checkout.js',
			[ 'jquery' ],
			PLUGIN_VERSION,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'wcsp_embedded_checkout_script' );
