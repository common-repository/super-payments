<?php
/**
 * Plugin Name: Super Payments
 * Plugin URI: https://wordpress.org/plugins/super-payments/
 * Description: Take payments on your store, eliminate payment fees and give your customers cash rewards.
 * Author: Super Payments
 * Author URI: https://discover.superpayments.com/business/
 * Version: 1.25.8
 * Text Domain: super-payments
 *
 *  @package super-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'PLUGIN_VERSION' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$plugin_data    = get_plugin_data( __FILE__ );
	$plugin_version = $plugin_data['Version'];
	define( 'PLUGIN_VERSION', $plugin_version );
}

// Required version constants.
define( 'REQUIRED_PHP_VERSION', '7.2.0' );
define( 'REQUIRED_WP_VERSION', '5.0.0' );
define( 'REQUIRED_WC_VERSION', '4.0.0' );

register_activation_hook( __FILE__, 'wcsp_on_activate' );

/**
 * On activation of the super payments plugin.
 */
function wcsp_on_activate() {
	// Add super payments gateway to the top of the list of available payment gateways.
	$gateway_order = get_option( 'woocommerce_gateway_order', [] );

	$new_order = [
		'superpayments' => 0,
	];

	if ( is_array( $gateway_order ) && count( $gateway_order ) > 0 ) {
		$loop = 1;

		foreach ( $gateway_order as $gateway_id => $order ) {
			if ( 'superpayments' === $gateway_id ) {
				continue;
			}

			$new_order[ esc_attr( $gateway_id ) ] = $loop;
			$loop++;
		}
	}

	update_option( 'woocommerce_gateway_order', $new_order );
}

/**
 * Show admin notice.
 *
 * @param string $message Message to show.
 */
function wcsp_admin_notice( $message ) {
	echo '<div class="error"><p><strong>' . esc_attr( $message ) . '</strong></p></div>';
}

// phpcs:disable WordPress.Files.FileName

/**
 * WC Super Payments plugin class.
 *
 * @class WC_Super_Payments
 */
class WC_Super_Payments {

	/**
	 * Plugin bootstrapping.
	 */
	public static function init() {
		if ( ! self::check_requirements() ) {
			return;
		}

		self::init_payment_gateway();

		add_action( 'woocommerce_blocks_loaded', [ __CLASS__, 'register_woocommerce_blocks_support' ] );
	}

	/**
	 * Check that the plugin requirements are met.
	 *
	 * @return bool
	 */
	public static function check_requirements() {
		$requirements_fulfilled = true;

		$requirements = [
			'php_version' => [
				'checked' => version_compare( PHP_VERSION, REQUIRED_PHP_VERSION, '>=' ),
				// translators: %s: Required PHP version.
				'error'   => sprintf( __( 'Super Payments requires PHP version %s+, plugin is currently NOT RUNNING.', 'super-payments' ), REQUIRED_PHP_VERSION ),
			],
			'wp_version'  => [
				'checked' => version_compare( get_bloginfo( 'version' ), REQUIRED_WP_VERSION, '>=' ),
				// translators: %s: Required WordPress version.
				'error'   => sprintf( __( 'Super Payments requires WordPress version %s+, plugin is currently NOT RUNNING.', 'super-payments' ), REQUIRED_WP_VERSION ),
			],
			'wc_version'  => [
				'checked' => defined( 'WC_VERSION' ) && version_compare( WC_VERSION, REQUIRED_WC_VERSION, '>=' ),
				// translators: %s: Required WooCommerce version.
				'error'   => sprintf( __( 'Super Payments requires WooCommerce version %s+, plugin is currently NOT RUNNING.', 'super-payments' ), REQUIRED_WC_VERSION ),
			],
		];

		foreach ( $requirements as $requirement ) {
			if ( ! $requirement['checked'] ) {
				$message = $requirement['error'];

				add_action(
					'admin_notices',
					function() use ( $message ) {
						wcsp_admin_notice( $message );
					}
				);

				$requirements_fulfilled = false;
			}
		}

		return $requirements_fulfilled;
	}

	/**
	 * Initialize the Super Payments payment gateway.
	 */
	public static function init_payment_gateway() {
		// Load Super Payments gateway class.
		require_once 'include/class-wc-super-payments-gateway.php';

		// Make the Super Payments gateway available to WC.
		add_filter( 'woocommerce_payment_gateways', [ __CLASS__, 'add_gateway' ] );
	}

	/**
	 * Add the Super Payment gateway to the list of available gateways.
	 *
	 * @param array $gateways List of available gateways.
	 *
	 * @return array
	 */
	public static function add_gateway( $gateways ) {
		$gateways[] = 'WC_Super_Payments_Gateway';
		return $gateways;
	}

	/**
	 * Plugin base id.
	 *
	 * @return string
	 */
	public static function plugin_base_id() {
		return 'superpayments';
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return trailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin abspath.
	 *
	 * @return string
	 */
	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Plugin basename.
	 *
	 * @return string
	 */
	public static function plugin_basename() {
		return plugin_basename( __FILE__ );
	}

	/**
	 * Plugin config.
	 *
	 * @param string $test_mode Test mode.
	 *
	 * @return array
	 */
	public static function plugin_config( $test_mode ) {
		$super_env = getenv( 'SUPER_ENV' );

		if ( empty( $super_env ) && 'yes' === $test_mode ) {
			$super_env = 'test';
		}

		$default_api_url             = 'test' === $super_env ? 'https://api.test.superpayments.com' : 'https://api.superpayments.com';
		$default_js_sdk_url          = 'test' === $super_env ? 'https://cdn.superpayments.com/js/test/super.js' : 'https://cdn.superpayments.com/js/super.js';
		$default_checkout_js_sdk_url = 'test' === $super_env ? 'https://cdn.superpayments.com/js/test/payment.js' : 'https://cdn.superpayments.com/js/payment.js';

		$super_api_url             = empty( getenv( 'SUPER_API_URL' ) ) ? $default_api_url : getenv( 'SUPER_API_URL' );
		$super_js_sdk_url          = empty( getenv( 'SUPER_JS_SDK_URL' ) ) ? $default_js_sdk_url : getenv( 'SUPER_JS_SDK_URL' );
		$super_checkout_js_sdk_url = empty( getenv( 'SUPER_CHECKOUT_JS_SDK_URL' ) ) ? $default_checkout_js_sdk_url : getenv( 'SUPER_CHECKOUT_JS_SDK_URL' );

		return [
			'api_url'             => $super_api_url,
			'js_sdk_url'          => $super_js_sdk_url,
			'checkout_js_sdk_url' => $super_checkout_js_sdk_url,
		];
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 */
	public static function register_woocommerce_blocks_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once 'include/blocks/class-wc-super-payments-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Super_Payments_Blocks() );
				}
			);
		}
	}
}

add_action( 'plugins_loaded', 'WC_Super_Payments::init' );

// Declare compatibility with WooCommerce Custom Order Tables.
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
