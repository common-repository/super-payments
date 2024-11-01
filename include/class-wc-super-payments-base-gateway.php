<?php
/**
 * Abstract class for Super Payments payment gateways.
 *
 * @package super-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Super Payments base gateway class.
 */
class WC_Super_Payments_Base_Gateway extends WC_Payment_Gateway {
	/**
	 * Supported features
	 *
	 * @var array
	 */
	public $supports = [ 'products', 'refunds' ];


	/**
	 * Process payment.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( intval( $order_id ) );

		$checkout_session_token = isset( $_POST['super_payments_checkout_session_token'] ) ? sanitize_text_field( $_POST['super_payments_checkout_session_token'] ) : ''; //phpcs:ignore
		if ( ! $checkout_session_token ) {
			wc_add_notice( __( 'Something went wrong. Please refresh the page and try again.', 'super-payments' ), 'error' );
		}

		$token_payload       = json_decode( base64_decode( str_replace( '_', '/', str_replace( '-', '+', explode( '.', $checkout_session_token )[1] ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$checkout_session_id = $token_payload->sub;

		// translators: %1$s: Super checkout session id.
		$order->add_order_note( sprintf( __( 'Super Payments processing checkout session with id %1$s', 'super-payments' ), $checkout_session_id ) );
		$order->update_meta_data( 'super_checkout_session_id', esc_attr( $checkout_session_id ) );
		$order->save();

		$api_key            = $this->get_option( 'api_key' );
		$test_mode          = $this->get_option( 'test_mode' );
		$order_total        = round( $order->get_total() * 100 );
		$order_received_url = $order->get_checkout_order_received_url();

		$super_cart_id = wcsp_get_cart_id( $order );
		$order_items   = [];
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			$order_items[] = [
				'name'            => $product->get_name(),
				'url'             => get_permalink( $product->get_id() ),
				'quantity'        => $item->get_quantity(),
				'minorUnitAmount' => round( $item->get_total() * 100 ),
			];
		}

		$email = $order->get_billing_email();
		if ( ! $email && ! empty( $_POST['billing_email'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$email = sanitize_key( $_POST['billing_email'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$phone = '';
		if ( ! empty( $order->get_billing_phone() ) ) {
			$phone = $order->get_billing_phone();
		} elseif ( ! empty( $order->get_shipping_phone() ) ) {
			$phone = $order->get_shipping_phone();
		} elseif ( ! empty( $_POST['billing_phone'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$phone = sanitize_key( $_POST['billing_phone'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( ! empty( $_POST['shipping_phone'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$phone = sanitize_key( $_POST['shipping_phone'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$response = wcsp_proceed_checkout_session(
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
		);

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( $response['body'], true );

			if ( isset( $body['redirectUrl'] ) ) {
				$redirect_url            = wp_sanitize_redirect( $body['redirectUrl'] );
				$super_payment_intent_id = sanitize_key( $body['paymentIntentId'] );

				$order->update_meta_data( 'super_transaction_id', esc_attr( $super_payment_intent_id ) );
				$order->save();

				// translators: %1$s: Super payment intent id.
				$order->add_order_note( sprintf( __( 'Super Payments started a transaction with id %1$s', 'super-payments' ), $super_payment_intent_id ) );

				return [
					'result'   => 'success',
					'redirect' => esc_url_raw( $redirect_url ),
				];
			} else {
				$error_message = __( 'Something went wrong. Please refresh the page and try again.', 'super-payments' );

				wc_add_notice( $error_message, 'error' );
				return [ 'errorMessage' => $error_message ];
			}
		} else {
			wc_add_notice( __( 'Connection error.', 'super-payments' ), 'error' );
			return [ 'errorMessage' => __( 'Connection error.', 'super-payments' ) ];
		}
	}

	/**
	 * Super payment refund functionality.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $amount Refund amount.
	 * @param string $reason Refund reason.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_id() || ! $order->get_total() ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'super-payments' ) );
		}

		if ( $amount && $amount > $order->get_total() ) {
			return new WP_Error( 'invalid_refund_amount', __( 'Invalid refund amount.', 'super-payments' ) );
		}

		$super_transaction_id   = sanitize_key( $order->get_meta( 'super_transaction_id' ) );
		$order_total_minor_unit = round( $amount * 100 );
		$post_discount_request  = [
			'headers' => [
				'authorization'  => esc_attr( $this->get_option( 'api_key' ) ),
				'content-type'   => 'application/json',
				'plugin-version' => PLUGIN_VERSION,
			],
			'body'    => wp_json_encode(
				[
					'transactionId'     => $super_transaction_id,
					'amount'            => $order_total_minor_unit,
					'currency'          => $order->get_currency(),
					'externalReference' => strval( $order_id ),

				]
			),
		];

		$test_mode = $this->get_option( 'test_mode' );
		$response  = wp_remote_post( wcsp_get_super_api_url( $test_mode ) . '/2024-02-01/refunds', $post_discount_request );

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( $response['body'], true );

			if ( 200 === $response['response']['code'] || 201 === $response['response']['code'] ) {
				$super_refund_id        = sanitize_key( $body['transactionId'] );
				$super_refund_reference = strval( $body['transactionReference'] );

				$super_refunds = $order->get_meta( 'super_refunds', true );

				if ( empty( $super_refunds ) ) {
					$super_refunds = [];
				}

				$super_refund = [
					'refund_id'             => esc_attr( $super_refund_id ),
					'refund_reference'      => esc_attr( $super_refund_reference ),
					'matched_to_woo_refund' => false,
					'refund_amount'         => $amount,
				];
				array_push( $super_refunds, $super_refund );
				$order->update_meta_data( 'super_refunds', $super_refunds );
				$order->save();

				// translators: %1$s: Super transaction reference.
				$order->add_order_note( sprintf( __( 'Refund process started for payment with reference - %1$s', 'super-payments' ), $super_refund_reference ) );
				return true;
			} else {
				// translators: %1$s: Super transaction reference, %2$s: Response code.
				$error_response_message = sprintf( __( 'Super Payments failed to initiate refund for payment due to a %1$s response', 'super-payments' ), $response['response']['code'] );

				if ( ! empty( $body['message'] ) ) {
					$error_response_message = $error_response_message . ': ' . $body['message'];
				}

				$order->add_order_note( $error_response_message );
				return false;
			}
		} else {
			wc_add_notice( __( 'Connection error.', 'super-payments' ), 'error' );
			$order->add_order_note( __( 'Super Payments failed to initiate refund for payment due to a Connection error.', 'super-payments' ) );

			return false;
		}
	}
}
