<?php
/**
 * Super Payments WooCommerce Blocks integration
 *
 * @package WooCommerce_Super_Payments
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Super Payments WooCommerce Blocks integration
 *
 * @since 1.0.3
 */
final class WC_Super_Payments_Blocks extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Super_Payments_Gateway
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'superpayments';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_superpayments_settings', [] );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$scripts = [
			'wc-super-payments-blocks',
			'wc-super-cart-blocks',
		];

		// Loop over the scripts array and register the scripts.
		foreach ( $scripts as $script ) {
			$script_path       = "assets/js/{$script}.js";
			$script_asset_path = WC_Super_Payments::plugin_abspath() . "assets/js/{$script}.asset.php";
			$script_asset      = file_exists( $script_asset_path )
				? require $script_asset_path
				: [
					'dependencies' => [],
					'version'      => PLUGIN_VERSION,
				];
			$script_url        = WC_Super_Payments::plugin_url() . $script_path;

			wp_register_script(
				$script,
				$script_url,
				$script_asset['dependencies'],
				$script_asset['version'],
				true
			);

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( $script, 'super-payments', WC_Super_Payments::plugin_abspath() . 'languages/' );
			}
		}

		return $scripts;
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$api_key   = $this->gateway->get_option( 'api_key' );
		$test_mode = $this->gateway->get_option( 'test_mode' );

		$response               = wcsp_create_checkout_session( $api_key, $test_mode );
		$response_body          = json_decode( $response['body'], true );
		$checkout_session_token = $response_body['checkoutSessionToken'];

		return [
			'enable_bp'            => $this->get_setting( 'enable_bp' ),
			'supports'             => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
			'checkoutSessionToken' => $checkout_session_token,
		];
	}
}
