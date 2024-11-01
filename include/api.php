<?php
/**
 * Super Payments API calls.
 *
 * @package super-payments
 */

/**
 * Get Super API URL.
 *
 * @param string $test_mode Test mode.
 *
 * @return string
 */
function wcsp_get_super_api_url( $test_mode ) {
	$plugin_config = WC_Super_Payments::plugin_config( $test_mode );

	return $plugin_config['api_url'];
}

/**
 * Call the v3 plugin offers POST endpoint to create an offer.
 *
 * @param string $publishable_api_key Publishable API key.
 * @param string $super_payments_cart_id Super Payments cart ID.
 * @param array  $cart_items Cart items.
 * @param int    $total_minor_unit Total amount in minor units.
 * @param string $page Page type.
 * @param string $payment_initiator_id Payment initiator id.
 * @param string $test_mode Test mode.
 *
 * @return array
 */
function wcsp_create_plugin_cart_offer( $publishable_api_key, $super_payments_cart_id, $cart_items, $total_minor_unit, $page, $payment_initiator_id, $test_mode ) {
	$offers_cache_group      = 'super_payments_offers';
	$cart_offer_cache_key    = 'cart_offer_' . $super_payments_cart_id . '_' . $page . '_' . $total_minor_unit;
	$cache_expiry_in_seconds = 5;

	$cached_offer = wp_cache_get( $cart_offer_cache_key, $offers_cache_group );

	if ( $cached_offer ) {
		return $cached_offer;
	}

	$plugin_cart_offer_headers = [
		'authorization'                => $publishable_api_key,
		'content-type'                 => 'application/json',
		'x-super-platform-type'        => 'woo-commerce',
		'x-super-platform-version'     => WC()->version,
		'x-super-page'                 => $page,
		'x-super-payment-initiator-id' => $payment_initiator_id,
	];

	$post_plugin_cart_offer_request = [
		'headers' => $plugin_cart_offer_headers,
		'body'    => wp_json_encode(
			[
				'cartTotalAmount' => round( $total_minor_unit ),
				'cart'            => (object) [
					'id'    => $super_payments_cart_id,
					'items' => $cart_items,
				],
			]
		),
	];

	$response = wp_remote_post( wcsp_get_super_api_url( $test_mode ) . '/v3/plugin-offers/cart/fragments', $post_plugin_cart_offer_request );
	wp_cache_set( $cart_offer_cache_key, $response, $offers_cache_group, $cache_expiry_in_seconds );

	return $response;
}

/**
 * Call the v3 referral POST endpoint to create a referral link.
 *
 * @param string $api_key Merchant API key.
 * @param string $location page or email.
 * @param string $test_mode Test mode.
 *
 * @return array
 */
function wcsp_create_referral_link( $api_key, $location, $test_mode ) {
	$post_referral_request = [
		'headers' => wcsp_get_super_headers( $api_key ),
		'body'    => wp_json_encode(
			[
				'location' => $location,
			]
		),
	];

	$response = wp_remote_post( wcsp_get_super_api_url( $test_mode ) . '/v3/referral/generate-referral-link/element', $post_referral_request );

	return $response;
}

/**
 * Get the business config.
 *
 * @param string $api_key Merchant API key.
 * @param string $test_mode Test mode.
 */
function wcsp_get_business_config( $api_key, $test_mode ) {
	$get_business_config_request = [
		'headers' => wcsp_get_super_headers( $api_key, 'authorization', null ),
	];

	$response = wp_remote_get( wcsp_get_super_api_url( $test_mode ) . '/business-config', $get_business_config_request );

	if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
		$parsed_json = json_decode( $response['body'], true );

		return $parsed_json;
	}

	return [];
}

/**
 * Create checkout session.
 *
 * @param string $api_key Merchant API key.
 * @param string $test_mode Test mode.
 *
 * @return array
 */
function wcsp_create_checkout_session( $api_key, $test_mode ) {
	$post_checkout_session_request = [
		'headers' => wcsp_get_super_headers( $api_key, 'authorization', null ),
	];

	$response = wp_remote_post( wcsp_get_super_api_url( $test_mode ) . '/v3/checkout-sessions', $post_checkout_session_request );

	return $response;
}

/**
 * Call the checkout sessions proceed POST endpoint to get a redirect URL.
 *
 * @param string $api_key Merchant API key.
 * @param string $checkout_session_id Checkout session ID.
 * @param int    $order_id Order ID.
 * @param int    $order_total Order total.
 * @param string $order_received_url Order received URL.
 * @param string $super_cart_id Super cart ID.
 * @param array  $order_items Order items.
 * @param string $email Email.
 * @param string $phone Phone.
 * @param string $test_mode Test mode.
 *
 * @return array
 */
function wcsp_proceed_checkout_session(
	$api_key,
	$checkout_session_id,
	$order_id,
	$order_total,
	$order_received_url,
	$super_cart_id,
	$order_items,
	$email,
	$phone,
	$test_mode
) {
	$checkout_sessions_proceed_request = [
		'timeout' => 30,
		'headers' => wcsp_get_super_headers( $api_key, 'authorization' ),
		'body'    => wp_json_encode(
			[
				'amount'            => $order_total,
				'cancelUrl'         => esc_url_raw( wc_get_checkout_url() ),
				'failureUrl'        => esc_url_raw( wc_get_checkout_url() ),
				'successUrl'        => $order_received_url,
				'externalReference' => strval( $order_id ),
				'cart'              => (object) [
					'id'    => $super_cart_id,
					'items' => $order_items,
				],
				'email'             => $email,
				'phone'             => $phone,
			]
		),
	];

	$response = wp_remote_post( wcsp_get_super_api_url( $test_mode ) . '/v3/checkout-sessions/' . $checkout_session_id . '/proceed', $checkout_sessions_proceed_request );

	return $response;
}

/**
 * Generate submit email guest token.
 *
 * @param string $api_key Merchant API key.
 * @param string $payment_intent_id Payment intent ID.
 * @param string $test_mode Test mode.
 */
function wcsp_generate_submit_email_guest_token( $api_key, $payment_intent_id, $test_mode ) {
	$post_submit_email_guest_token_request = [
		'headers' => wcsp_get_super_headers( $api_key, 'authorization', null ),
	];

	$response = wp_remote_post( wcsp_get_super_api_url( $test_mode ) . '/v3/pay/external/payment-intent/' . $payment_intent_id . '/guest-email-token', $post_submit_email_guest_token_request );

	if ( ! is_wp_error( $response ) && 201 === $response['response']['code'] ) {
		$parsed_json = json_decode( $response['body'], true );

		return $parsed_json;
	}

	return [];
}

/**
 * Generate the shared request headers.
 *
 * @param string $api_key Merchant API key.
 * @param string $auth_header Auth header name.
 * @param string $content_type Content type.
 */
function wcsp_get_super_headers( $api_key, $auth_header = 'checkout-api-key', $content_type = 'application/json' ) {
	$headers = [
		$auth_header     => $api_key,
		'plugin-version' => 'WooCommerce ' . PLUGIN_VERSION,
	];

	if ( ! empty( $content_type ) ) {
		$headers['content-type'] = $content_type;
	}

	return $headers;
}
