<?php
/**
 * Super Payments marketing assets.
 *
 * @package super-payments
 */

/**
 * Generic hooks that come built-in to WooCommerce.
 */

/**
 * Add super product callout on product detail page.
 *
 * @return void
 */
function wcsp_explainer_text() {
	$super_payments = new WC_Super_Payments_Gateway();
	$enable_pdp     = $super_payments->get_option( 'enable_pdp' );

	if ( isset( $enable_pdp ) && 'yes' === $enable_pdp ) {
		$product_id = get_the_ID();
		$product    = wc_get_product( $product_id );

		if ( empty( $product ) ) {
			return;
		}

		$cart      = wcsp_get_cart();
		$page_type = ! empty( wcsp_get_page_type() ) ? wcsp_get_page_type() : 'unknown';

		$product_price_minor_units = round( floatval( wc_get_price_to_display( $product ) ) * 100 );

		echo '<super-product-callout productAmount="' . esc_attr( $product_price_minor_units ) . '" page="' . esc_attr( $page_type ) . '" cartId="' . esc_attr( $cart['id'] ) . '"></super-product-callout>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

add_action( 'woocommerce_before_add_to_cart_form', 'wcsp_explainer_text' );

/**
 * Add super cart callout on cart page.
 */
function wcsp_explainer_text_on_cart() {
	$super_payments = new WC_Super_Payments_Gateway();
	$enable_bp      = $super_payments->get_option( 'enable_bp' );

	if ( isset( $enable_bp ) && 'yes' === $enable_bp ) {
		$cart      = wcsp_get_cart();
		$page_type = ! empty( wcsp_get_page_type() ) ? wcsp_get_page_type() : 'unknown';

		echo '<super-cart-callout cartAmount="' . esc_attr( $cart['total_minor_unit'] ) . '" page="' . esc_attr( $page_type ) . '" cartId="' . esc_attr( $cart['id'] ) . '" cartItems="' . esc_attr( wp_json_encode( $cart['items'] ) ) . '"></super-cart-callout>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

add_filter( 'woocommerce_before_cart_totals', 'wcsp_explainer_text_on_cart', 10 );

/**
 * Add super banner on home page or entire site.
 */
function wcsp_load_banner() {
	if ( ! class_exists( 'WC_Super_Payments_Gateway' ) || ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$super_payments     = new WC_Super_Payments_Gateway();
	$enable_banner_home = ! empty( $super_payments->get_option( 'enable_banner_home' ) ) ? $super_payments->get_option( 'enable_banner_home' ) : 'no';
	$enable_banner_site = ! empty( $super_payments->get_option( 'enable_banner_site' ) ) ? $super_payments->get_option( 'enable_banner_site' ) : 'no';

	if ( 'yes' === $enable_banner_site || ( 'yes' === $enable_banner_home && is_home() ) ) {
		if ( wcsp_is_order_pay_page() ) {
			$order                   = wcsp_get_order_pay_page_order();
			$cart_id                 = wcsp_get_cart_id( $order );
			$items                   = wcsp_get_order_items( $order );
			$total_amount_minor_unit = round( $order->get_total() * 100 );
		} else {
			$cart                    = wcsp_get_cart();
			$cart_id                 = $cart['id'];
			$items                   = $cart['items'];
			$total_amount_minor_unit = $cart['total_minor_unit'];
		}

		$banner_style = ! empty( $super_payments->get_option( 'banner_style' ) ) ? $super_payments->get_option( 'banner_style' ) : 'orange';
		$page_type    = ! empty( wcsp_get_page_type() ) ? wcsp_get_page_type() : 'unknown';

		echo '<super-banner cartAmount="' . esc_attr( $total_amount_minor_unit ) . '" page="' . esc_attr( $page_type ) . '" cartId="' . esc_attr( $cart_id ) . '" colorScheme="' . esc_attr( $banner_style ) . '" cartItems="' . esc_attr( wp_json_encode( $items ) ) . '"></super-banner>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

add_action( 'wp_body_open', 'wcsp_load_banner' );

/**
 * Add super referral link on thank you page.
 *
 * @param int $order_id Order ID.
 */
function wcsp_thank_you_referral_link( $order_id ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	if ( ! class_exists( 'WC_Super_Payments_Gateway' ) || ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$super_payments                           = new WC_Super_Payments_Gateway();
	$enable_order_received_page_referral_link = ! empty( $super_payments->get_option( 'enable_order_received_page_referral_link' ) ) ? $super_payments->get_option( 'enable_order_received_page_referral_link' ) : 'no';

	if ( 'yes' === $enable_order_received_page_referral_link ) {
		echo '<super-referral-callout></super-referral-callout>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

add_action( 'woocommerce_thankyou_superpayments', 'wcsp_thank_you_referral_link', 20, 1 );

/**
 * Add super payments referral link in order email.
 *
 * @param object $order Order object.
 * @param bool   $sent_to_admin Sent to admin.
 * @param bool   $plain_text Plain text.
 * @param object $email Email object.
 */
function wcsp_order_email_referral_link( $order, $sent_to_admin, $plain_text, $email ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	if ( ! class_exists( 'WC_Super_Payments_Gateway' ) || ! class_exists( 'WooCommerce' ) || $plain_text || $sent_to_admin ) {
		return;
	}

	$super_payments                   = new WC_Super_Payments_Gateway();
	$enable_order_email_referral_link = ! empty( $super_payments->get_option( 'enable_order_email_referral_link' ) ) ? $super_payments->get_option( 'enable_order_email_referral_link' ) : 'no';

	if ( 'yes' === $enable_order_email_referral_link ) {
		$response = wcsp_create_referral_link(
			$super_payments->get_option( 'api_key' ),
			'email',
			$super_payments->get_option( 'test_mode' )
		);

		if ( ! is_wp_error( $response ) && ( 200 === $response['response']['code'] || 201 === $response['response']['code'] ) ) {
			$parsed_json = json_decode( $response['body'], true );
			$content     = $parsed_json['content'];
			echo $content; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

add_action( 'woocommerce_email_order_details', 'wcsp_order_email_referral_link', 10, 4 );

/**
 * Add super payment confirmation on thank you page.
 *
 * @param int $order_id Order ID.
 */
function wcsp_thank_you_payment_confirmation( $order_id ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	if ( ! class_exists( 'WC_Super_Payments_Gateway' ) || ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$order         = wc_get_order( $order_id );
	$billing_email = $order->get_billing_email();

	$super_payments      = new WC_Super_Payments_Gateway();
	$publishable_api_key = $super_payments->get_option( 'publishable_api_key' );

	$api_key           = $super_payments->get_option( 'api_key' );
	$test_mode         = $super_payments->get_option( 'test_mode' );
	$payment_intent_id = $order->get_meta( 'super_transaction_id' );

	$body                     = wcsp_generate_submit_email_guest_token( $api_key, $payment_intent_id, $test_mode );
	$submit_email_guest_token = $body['guestToken'];

	echo '<super-payment-confirmation payment-intent-id="' . esc_attr( $payment_intent_id ) . '" customer-email="' . esc_attr( $billing_email ) . '" submit-email-guest-token="' . esc_attr( $submit_email_guest_token ) . '" publishable-api-key="' . esc_attr( $publishable_api_key ) . '"></super-payment-confirmation>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

add_action( 'woocommerce_thankyou_superpayments', 'wcsp_thank_you_payment_confirmation', 20, 1 );

/**
 * Custom hooks that are exposed by plugins our merchants are using.
 */

/**
 * Add super cart callout on XootiX cart drawer.
 *
 * Plugin URL: https://wordpress.org/plugins/side-cart-woocommerce/
 */
function wcsp_explainer_text_on_xootix_cart_drawer() {
	$super_payments = new WC_Super_Payments_Gateway();
	$enable_bp      = $super_payments->get_option( 'enable_bp' );

	if ( isset( $enable_bp ) && 'yes' === $enable_bp ) {
		$cart      = wcsp_get_cart();
		$page_type = ! empty( wcsp_get_page_type() ) ? wcsp_get_page_type() : 'unknown';

		echo '<super-cart-callout cartAmount="' . esc_attr( $cart['total_minor_unit'] ) . '" page="' . esc_attr( $page_type ) . '" cartId="' . esc_attr( $cart['id'] ) . '" cartItems="' . esc_attr( wp_json_encode( $cart['items'] ) ) . '"></super-cart-callout>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'xoo_wsc_footer_start', 'wcsp_explainer_text_on_xootix_cart_drawer', 10 );
