<?php
/**
 * Refunds webhook registration and handling.
 *
 * @package super-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WC_Super_Payments::plugin_abspath() . 'include/webhooks/utils.php';

/**
 * Handle refund status webhook events.
 */
function refunds_webhook() {
	$request_body = file_get_contents( 'php://input' );
	$parsed_json  = json_decode( $request_body );

	$super_refund_reference = filter_var( $parsed_json->transactionReference, FILTER_SANITIZE_STRING ); //phpcs:ignore WordPress.NamingConventions
	$super_refund_id        = $parsed_json->transactionId; //phpcs:ignore WordPress.NamingConventions
	$super_refund_status    = strval( $parsed_json->transactionStatus ); //phpcs:ignore WordPress.NamingConventions

	$args = [
		'meta_key' => 'super_refund_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	'meta_value'   => $super_refund_id, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	];

	$woo_refunds = wc_get_orders( $args );

	if ( empty( $woo_refunds ) || ! is_array( $woo_refunds ) ) {
		header( 'HTTP/1.1 404 Not Found' );
		echo esc_html__( 'Refund Order not found for id - ', 'super-payments' ) . esc_attr( $super_refund_id );
		exit;
	}

	$woo_refund    = $woo_refunds[0];
	$woo_refund_id = $woo_refund->get_id();
	$order_id      = $woo_refund->get_parent_id();

	if ( ! $order_id ) {
		exit;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order ) {
		exit;
	}

	$super_payments = new WC_Super_Payments_Gateway();

	$signature_header       = filter_var( $_SERVER['HTTP_SUPER_SIGNATURE'] ); //phpcs:ignore WordPress.Security
	$signature_header_parts = explode( ',', $signature_header );
	$timestamp_parts        = explode( ':', $signature_header_parts[0] );
	$signature_parts        = explode( ':', $signature_header_parts[1] );
	$generated_signature    = wcsp_generate_webhook_signature( $request_body, $timestamp_parts[1], $super_payments->get_option( 'signing_key' ) );

	if ( $generated_signature !== $signature_parts[1] ) {
		// translators: %1$s: Super refund reference.
		$order->add_order_note( sprintf( __( 'Super Payments tried to refund the order with refund status information for %1$s but your confirmation ID was incorrect.', 'super-payments' ), $super_refund_reference ) );
		header( 'HTTP/1.1 401 Unauthorized' );
		exit;
	}

	switch ( $super_refund_status ) {
		case 'RefundSuccess':
			// translators: %1$s: Super refund reference.
			$order->add_order_note( sprintf( __( 'Refund %1$s completed with Super Payments', 'super-payments' ), $super_refund_reference ) );
			break;
		case 'RefundFailed':
			$woo_refund->delete( true );
              // phpcs:disable
		  do_action( 'woocommerce_refund_deleted', $woo_refund_id, $order_id );
              // phpcs:enable
			// translators: %1$s: Super refund reference.
			$order->add_order_note( sprintf( __( 'Refund %1$s failed with Super Payments', 'super-payments' ), $super_refund_reference ) );
			break;
		case 'RefundAbandoned':
			// translators: %1$s: Super refund reference.
			$order->add_order_note( sprintf( __( 'Refund %1$s abandoned with Super Payments', 'super-payments' ), $super_refund_reference ) );
			break;
		default:
			// translators: %1$s: Super refund status.
			$order->add_order_note( sprintf( __( 'Super Payments tried to refund with an unrecognised status of %1$s.', 'super-payments' ), $super_refund_status ) );
			header( 'HTTP/1.1 400 Bad Request' );
			echo esc_html__( 'Invalid value for property "transaction_status"', 'super-payments' );
			exit;
	}
}

add_action( 'woocommerce_api_' . WC_Super_Payments::plugin_base_id() . '/refunds', 'refunds_webhook' );
