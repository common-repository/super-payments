<?php
/**
 * Super Payments order helper functions.
 *
 * @package super-payments
 */

/**
 * Get the order awaiting payment.
 *
 * WooCommerce creates an order at checkout and stores the order ID in the session
 * until it is paid for. This retrieves the order id stored in the session and
 * returns the order object.
 *
 * @return WC_Order|null
 */
function get_order_awaiting_payment() {
	if ( isset( WC()->session ) && WC()->session->get( 'order_awaiting_payment' ) !== null ) {
		$current_session_order_id = WC()->session->get( 'order_awaiting_payment' );
		$order                    = wc_get_order( $current_session_order_id );

		return $order;
	}

	return null;
}

/**
 * Get the order for the order pay page.
 *
 * The order pay page is not the same as the checkout page. You can access a pay page
 * for any order that has not been paid for yet.
 *
 * @return WC_Order|null
 */
function wcsp_get_order_pay_page_order() {
	if ( wcsp_is_order_pay_page() && isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id  = wc_get_order_id_by_order_key( $order_key );
		$order     = wc_get_order( $order_id );

		return $order;
	}

	return null;
}

/**
 * Get the active order.
 *
 * The active order is the order that is currently being paid for. This could be
 * the order pay page or the order awaiting payment.
 *
 * @return WC_Order|null
 */
function wcsp_get_active_order() {
	$order = wcsp_get_order_pay_page_order();

	if ( ! empty( $order ) ) {
		return $order;
	}

	$order = get_order_awaiting_payment();

	if ( ! empty( $order ) ) {
		return $order;
	}

	return null;
}

/**
 * Get order items from an order.
 *
 * @param WC_Order $order The order.
 */
function wcsp_get_order_items( $order ) {
	$items = [];

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		$items[] = [
			'name'            => $product->get_name(),
			'url'             => get_permalink( $product->get_id() ),
			'quantity'        => $item->get_quantity(),
			'minorUnitAmount' => round( $item->get_total() * 100 ),
		];
	}

	return $items;
}

/**
 * Overrides an orders get_payment_method_title() to return the correct title.
 *
 * @param string $value Payment gateway title.
 * @param object $order Order object.
 *
 * @return String
 */
function wcsp_override_order_payment_method_title( $value, $order ) {
	if ( $order->get_payment_method() === 'superpayments' ) {
		$value = __( 'Super Payments', 'super-payments' );
	}
	return $value;
}
add_filter( 'woocommerce_order_get_payment_method_title', 'wcsp_override_order_payment_method_title', 10, 2 );

/**
 * Adds an SP- prefix to the IDs of orders paid for with Super Payments.
 *
 * @param int      $order_id Order ID.
 * @param WC_Order $order Order object.
 *
 * @return String
 */
function wcsp_order_number_display_prefix( $order_id, $order ) {
	if (
		$order->get_payment_method() === 'superpayments' &&
		in_array( $order->get_status(), [ 'on-hold', 'processing', 'completed', 'refunded' ], true )
	) {
		$super_payments              = new WC_Super_Payments_Gateway();
		$display_order_number_prefix = $super_payments->get_option( 'display_order_number_prefix' );

		if ( 'yes' === $display_order_number_prefix ) {
			return 'SP-' . $order_id;
		}
	}

	return $order_id;
}
add_filter( 'woocommerce_order_number', 'wcsp_order_number_display_prefix', 10, 2 );

/**
 * Sets the order as awaiting payment in the session.
 *
 * @param WC_Order $order Order object.
 */
function wcsp_set_order_awaiting_payment( $order ) {
	if (
		isset( WC()->session ) &&
		WC()->session->get( 'order_awaiting_payment' ) === null &&
		$order->get_payment_method() === 'superpayments' &&
		$order->needs_payment()
	) {
		WC()->session->set( 'order_awaiting_payment', $order->get_id() );
	}
}
add_action( 'woocommerce_store_api_checkout_order_processed', 'wcsp_set_order_awaiting_payment', 10, 3 );
