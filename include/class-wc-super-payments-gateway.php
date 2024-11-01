<?php
/**
 * Register and handle the Super Payments class.
 *
 * @package super-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WC_Super_Payments::plugin_abspath() . 'include/class-wc-super-payments-base-gateway.php';

require_once WC_Super_Payments::plugin_abspath() . 'include/admin.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/api.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/cart.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/marketing-assets.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/order.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/page.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/public-scripts.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/events.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/checkout.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/refunds.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/styles.php';
// Webhooks.
require_once WC_Super_Payments::plugin_abspath() . 'include/webhooks/payments.php';
require_once WC_Super_Payments::plugin_abspath() . 'include/webhooks/refunds.php';

/**
 * WC_Super_Payments_Gateway class.
 */
class WC_Super_Payments_Gateway extends WC_Super_Payments_Base_Gateway {

	/**
	 * Initiate the class
	 */
	public function __construct() {
		$this->id                 = WC_Super_Payments::plugin_base_id();
		$this->icon               = 'https://cdn.superpayments.com/integrations/super-wordmark-orange-bg-white-text.svg';
		$this->has_fields         = true;
		$this->title              = __( 'Pay with Super', 'super-payments' );
		$this->method_title       = __( 'Super Payments', 'super-payments' );
		$this->method_description = __( 'Take payments on your store, avoid costly card fees and give your customers cash rewards.', 'super-payments' );

		$this->init_form_fields();
		$this->init_settings();

		if ( $this->get_option( 'set_as_default_payment_method' ) === 'yes' ) {
			$this->set_payment_method();
		}

		$this->enabled = $this->get_option( 'enabled' );

		$settings_version = $this->get_option( 'settings_version' );
		if ( $this->enabled && ( empty( $settings_version ) || PLUGIN_VERSION !== $settings_version ) ) {
			$this->update_settings();
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'save_order_payment_type_meta_data' ], 10, 2 );
		add_filter( 'woocommerce_gateway_title', [ $this, 'get_payment_method_title' ], 100, 2 );
		add_filter( 'woocommerce_gateway_icon', [ $this, 'get_payment_method_icon' ], 100, 2 );
	}

	/**
	 * Update the admin options.
	 */
	public function process_admin_options() {
		parent::process_admin_options();

		$this->update_settings();
	}

	/**
	 * Auto update settings. Useful for migrating settings from older plugin versions.
	 */
	public function update_settings() {
		// Make sure deprecated settings are updated.
		$this->update_option( 'enable_plp', 'no' );

		// update_total 'yes' should be mapped to update_total 'order_total'.
		$update_total = $this->get_option( 'update_total' );
		if ( ! empty( $update_total ) && 'yes' === $update_total ) {
			$this->update_option( 'update_total', 'order_total' );
		}

		// publishable_api_key is hidden from the user and should be updated automatically.
		$api_key = $this->get_option( 'api_key' );

		if ( ! empty( $api_key ) ) {
			$test_mode = $this->get_option( 'test_mode' );
			$response  = wcsp_get_business_config( $api_key, $test_mode );

			if ( is_wp_error( $response ) || empty( $response['publishableKeys'] ) || empty( $response['currentIntegration'] ) ) {
				return;
			}

			$publishable_api_key = $response['publishableKeys'][0]['key'];
			$integration_id      = $response['currentIntegration'];

			if ( empty( $publishable_api_key ) ) {
				return;
			}

			$this->update_option( 'publishable_api_key', $publishable_api_key );
			$this->update_option( 'integration_id', $integration_id );
			$this->update_option( 'settings_version', PLUGIN_VERSION );
		}
	}

	/**
	 * Sets Super Payments as the chosen payment method if nothing is set.
	 */
	public function set_payment_method() {
		if (
			isset( WC()->session ) &&
			( ! isset( WC()->session->chosen_payment_method ) || WC()->session->chosen_payment_method === '' || ! is_checkout() )
		) {
			WC()->session->set( 'chosen_payment_method', 'superpayments' );
		}
	}

	/**
	 * Init setting form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled'                                  => [
				'title'   => __( 'Enable/Disable', 'super-payments' ),
				'label'   => __( 'Enable Super Payments Gateway', 'super-payments' ),
				'type'    => 'checkbox',
				'default' => 'no',
			],
			'secrets'                                  => [
				'title'       => __( 'API Keys', 'super-payments' ),
				'type'        => 'title',
				'description' => __( 'You can generate your API keys from the <a href="https://business.superpayments.com/" target="_blank">Super Payments Dashboard</a>.', 'super-payments' ),
			],
			'api_key'                                  => [
				'title'   => __( 'API Key', 'super-payments' ),
				'type'    => 'password',
				'default' => getenv( 'SUPER_API_KEY' ),
			],
			'signing_key'                              => [
				'title'   => __( 'Confirmation ID', 'super-payments' ),
				'type'    => 'password',
				'default' => getenv( 'SUPER_SIGNING_KEY' ),
			],
			'on_site_messaging'                        => [
				'title'       => __( 'On-site Messaging', 'super-payments' ),
				'type'        => 'title',
				'description' => __( 'On-site messaging is a great way to inform your customers about the benefits of using Super Payments.', 'super-payments' ),
			],
			'enable_pdp'                               => [
				'title'   => __( 'Product Detail Page Asset', 'super-payments' ),
				'label'   => __( 'Show a Super Payments asset on product detail page', 'super-payments' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			'enable_bp'                                => [
				'title'   => __( 'Basket/Cart Page Asset', 'super-payments' ),
				'label'   => __( 'Show a Super Payments asset on basket/cart page', 'super-payments' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			'enable_banner_home'                       => [
				'title'   => __( 'Home Page Banner', 'super-payments' ),
				'label'   => __( 'Show a Super Payments banner on the home page', 'super-payments' ),
				'type'    => 'checkbox',
				'default' => 'no',
			],
			'enable_banner_site'                       => [
				'title'   => __( 'Site Wide Banner', 'super-payments' ),
				'label'   => __( 'Show a Super Payments banner across the entire site', 'super-payments' ),
				'type'    => 'checkbox',
				'default' => 'no',
			],
			'banner_style'                             => [
				'title'   => __( 'Super payments banner colour', 'super-payments' ),
				'label'   => __( 'Set the colour of the banner', 'super-payments' ),
				'type'    => 'select',
				'options' => [
					'yellow'       => __( 'Yellow', 'super-payments' ),
					'orange'       => __( 'Orange', 'super-payments' ),
					'black-white'  => __( 'White on Black', 'super-payments' ),
					'black-orange' => __( 'Orange on Black', 'super-payments' ),
					'white-orange' => __( 'Orange on White', 'super-payments' ),
					'white-black'  => __( 'Black on White', 'super-payments' ),
				],
			],
			'enable_order_received_page_referral_link' => [
				'title'   => __( 'Enable Order Received Page Referral Link', 'super-payments' ),
				'label'   => __( 'Show a Super Payments referral link on the order received page', 'super-payments' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			'enable_order_email_referral_link'         => [
				'title'   => __( 'Enable Order Email Referral Link', 'super-payments' ),
				'label'   => __( 'Show a Super Payments referral link in the order email', 'super-payments' ),
				'type'    => 'checkbox',
				'default' => 'no',
			],
			'extra_configuration'                      => [
				'title' => __( 'Extra Configuration', 'super-payments' ),
				'type'  => 'title',
			],
			'update_total'                             => [
				'title'   => __( 'Displaying Cash Rewards', 'super-payments' ),
				'label'   => __( 'How to show a cash reward used by a customer', 'super-payments' ),
				'type'    => 'select',
				'default' => 'order_total',
				'options' => [
					'no'          => __( 'Do not show cash rewards', 'super-payments' ),
					'order_total' => __( 'Apply cash reward by updating the order total', 'super-payments' ),
					'coupon'      => __( 'Apply cash reward as a coupon on the order', 'super-payments' ),
				],
			],
			'set_as_default_payment_method'            => [
				'title'   => __( 'Default Payment Method', 'super-payments' ),
				'label'   => __( 'Sets Super Payments as the selected payment method, if no payment method is selected', 'super-payments' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			'display_order_number_prefix'              => [
				'title'   => __( 'Display Super Prefix On Order IDs', 'super-payments' ),
				'label'   => __( 'Includes an "SP-" prefix on the IDs of orders paid for with Super', 'super-payments' ),
				'type'    => 'checkbox',
				'default' => 'no',
			],
			'test_mode'                                => [
				'title'       => __( 'Enable Test Mode', 'super-payments' ),
				'label'       => __( 'Requires a test api key and confirmation id', 'super-payments' ),
				'description' => __( 'Do not enable this on a live customer facing site', 'super-payments' ),
				'default'     => 'no',
				'type'        => 'checkbox',
			],
		];
	}

	/**
	 * Validate the API key field.
	 *
	 * @param string $key Settings key for the API key.
	 * @param string $value Value of the API key.
	 *
	 * @return string
	 */
	public function validate_api_key_field( $key, $value ) {
		if ( empty( $value ) ) {
			WC_Admin_Settings::add_error( __( 'Please enter your API key.', 'super-payments' ) );
		} elseif ( ! str_starts_with( $value, 'PSK_' ) ) {
			WC_Admin_Settings::add_error( __( 'Please enter a valid API key. A valid API key starts with PSK_.', 'super-payments' ) );
			$value = '';
		}

		return $value;
	}

	/**
	 * Validate the signing key field.
	 *
	 * @param string $key Settings key for the signing key.
	 * @param string $value Value of the signing key.
	 *
	 * @return string
	 */
	public function validate_signing_key_field( $key, $value ) {
		if ( empty( $value ) ) {
			WC_Admin_Settings::add_error( __( 'Please enter your Confirmation ID.', 'super-payments' ) );
		} elseif ( ! str_starts_with( $value, 'PWH_' ) ) {
			WC_Admin_Settings::add_error( __( 'Please enter a valid Confirmation ID. A valid Confirmation ID starts with PWH_.', 'super-payments' ) );
			$value = '';
		}

		return $value;
	}

	/**
	 * Get payment method title.
	 *
	 * @param string $payment_method_title Payment method title.
	 * @param string $gateway_id Gateway ID.
	 *
	 * @return string
	 */
	public function get_payment_method_title( $payment_method_title, $gateway_id ) {
		if ( $this->id !== $gateway_id ) {
			return $payment_method_title;
		}

		$fragments   = $this->get_payment_method_fragments();
		$icon_url    = empty( $fragments['icon'] ) ? $this->icon : $fragments['icon'];
		$this->icon  = '<img src="' . WC_HTTPS::force_https_url( $icon_url ) . '" alt="' . esc_attr( $fragments['title'] ) . '" />';
		$this->title = $fragments['title'];

		return $this->title;
	}

	/**
	 * Get payment method icon.
	 *
	 * @param string $payment_method_icon Payment method icon.
	 * @param string $gateway_id Gateway ID.
	 *
	 * @return string
	 */
	public function get_payment_method_icon( $payment_method_icon, $gateway_id ) {
		if ( $this->id !== $gateway_id ) {
			return $payment_method_icon;
		}

		$fragments   = $this->get_payment_method_fragments();
		$icon_url    = empty( $fragments['icon'] ) ? $this->icon : $fragments['icon'];
		$this->icon  = '<img src="' . WC_HTTPS::force_https_url( $icon_url ) . '" alt="' . esc_attr( $fragments['title'] ) . '" />';
		$this->title = $fragments['title'];

		return $this->icon;
	}

	/**
	 * Get payment method fragments (Title and icon url).
	 *
	 * @return array
	 */
	public function get_payment_method_fragments() {
		$payment_method_title = $this->method_title;
		$payment_method_icon  = $this->icon;

		if ( ! is_admin() ) {

			if ( wcsp_is_order_pay_page() ) {
				$order = wcsp_get_order_pay_page_order();

				$total_minor_units = $order->get_total() * 100;
				$cart_id           = wcsp_get_cart_id();
				$items             = wcsp_get_order_items( $order );
			} else {
				$cart = wcsp_get_cart();

				$total_minor_units = $cart['total_minor_unit'];
				$cart_id           = $cart['id'];
				$items             = $cart['items'];
			}

			if ( $total_minor_units > 0 ) {
				try {
					$response = wcsp_create_plugin_cart_offer(
						$this->get_option( 'publishable_api_key' ),
						$cart_id,
						$items,
						$total_minor_units,
						'checkout',
						$this->get_option( 'integration_id' ),
						$this->get_option( 'test_mode' )
					);

					if ( is_wp_error( $response ) || $response['response']['code'] >= 500 ) {
						$payment_method_title = __( 'Super currently unavailable', 'super-payments' );
					} elseif ( 200 === $response['response']['code'] || 201 === $response['response']['code'] ) {
						$parsed_json          = json_decode( $response['body'], true );
						$payment_method_title = $parsed_json['paymentMethodTitle'];
						$payment_method_icon  = $parsed_json['paymentMethodIcon'];
					} else {
						$payment_method_title = __( 'Super integration failed', 'super-payments' );
					}
				} catch ( Exception $e ) {
					echo 'Caught exception: ',  esc_attr( $e->getMessage() ), "\n";
				}
			}
		}

		return [
			'title' => $payment_method_title,
			'icon'  => $payment_method_icon,
		];
	}

	/**
	 * Renders the description for the payment option.
	 */
	public function payment_fields() {
		if ( wcsp_is_order_pay_page() ) {
			$order             = wcsp_get_order_pay_page_order();
			$total_minor_units = $order->get_total() * 100;
		} else {
			$cart              = wcsp_get_cart();
			$total_minor_units = $cart['total_minor_unit'];
		}

		$test_mode = $this->get_option( 'test_mode' );
		if ( 'yes' === $test_mode ) {
			echo '<div class="woocommerce-error">' . esc_html__( 'Super Payments is in test mode.', 'super-payments' ) . '</div>';
		}

		$response = wcsp_create_checkout_session(
			$this->get_option( 'api_key' ),
			$this->get_option( 'test_mode' )
		);

		$response_body          = json_decode( $response['body'], true );
		$checkout_session_token = $response_body['checkoutSessionToken'];

		echo '<super-checkout checkout-session-token="' . esc_attr( $checkout_session_token ) . '" amount="' . esc_attr( $total_minor_units ) . '"></super-checkout>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="super_payments_checkout_session_token" value="' . esc_attr( $checkout_session_token ) . '">';
		echo '<input type="hidden" name="super_payments_call_embedded_component_submit" value="true">';
	}


	/**
	 * Save the super_cart_id to the order meta data.
	 * Triggered before process_payment
	 *
	 * @param object $order WC Order.
	 * @param array  $data Data.
	 */
	public function save_order_payment_type_meta_data( $order, $data ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( empty( $order->get_meta( 'super_cart_id' ) ) ) {
			$cart_id = wcsp_get_cart_id();
			$order->update_meta_data( 'super_cart_id', esc_attr( $cart_id ) );
		}

		if ( isset( WC()->session ) ) {
			WC()->session->set( 'super_cart_id', null );
		}
	}
}
