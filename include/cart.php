<?php
/**
 * Super Payments cart helper functions.
 *
 * @package super-payments
 */

/**
 * Get cart ID from the order meta, or the session.
 * If neither exist, create a new cart ID.
 * Store the cart ID on the order meta if we have one. Otherwise, store it in the session.
 * Don't touch the session if we're on the order pay page which is different from the checkout.
 *
 * @param WC_Order $order Order object.
 *
 * @return int Cart ID.
 */
function wcsp_get_cart_id( $order = null ) {
	$super_cart_id = '';

	$is_order_pay_page = wcsp_is_order_pay_page();

	if ( empty( $order ) ) {
		$order = wcsp_get_active_order();
	}

	if ( ! empty( $order ) ) {
		$super_cart_id = $order->get_meta( 'super_cart_id' );
	}

	$is_session_super_cart_id_set = isset( WC()->session ) && WC()->session->get( 'super_cart_id' ) !== null;

	if (
	! $is_order_pay_page &&
	$is_session_super_cart_id_set &&
	empty( $super_cart_id )
	) {
		$super_cart_id = WC()->session->get( 'super_cart_id' );
	}

	// If we still don't have a cart ID, but there's no session available, return unknown to avoid repeatedly generating new cart IDs.
	if ( empty( $super_cart_id ) && ! isset( WC()->session ) ) {
		return 'unknown';
	}

	// Generate a new cart ID if one doesn't exist.
	if ( empty( $super_cart_id ) ) {
		$super_cart_id = wp_generate_uuid4();

		/**
		 * Action hook fired when a super cart ID is generated.
		 *
		 * @since 1.13.10
		 *
		 * @param string $super_cart_id Cart ID.
		 */
		do_action( 'wcsp_cart_id_generated', $super_cart_id );
	}

	wcsp_store_cart_id( $super_cart_id, $order );

	return $super_cart_id;
}

/**
 * Store the cart id on the order if we have one. Otherwise, store it in the session.
 * Don't touch the session if we're on the order pay page.
 *
 * @param string   $super_cart_id Cart ID.
 * @param WC_Order $order Order object.
 *
 * @return string Cart ID.
 */
function wcsp_store_cart_id( $super_cart_id, $order ) {
	$is_order_pay_page = wcsp_is_order_pay_page();

	if ( ! empty( $order ) ) {
		if ( ! $is_order_pay_page ) {
			WC()->session->set( 'super_cart_id', null );
		}

		$order->update_meta_data( 'super_cart_id', esc_attr( $super_cart_id ) );
		$order->save();
	} elseif ( isset( WC()->session ) && ! $is_order_pay_page ) {
		WC()->session->set( 'super_cart_id', $super_cart_id );
	}

	return $super_cart_id;
}


/**
 * Get cart items.
 */
function wcsp_get_cart_items() {
	$cart_items = [];

	if ( isset( WC()->cart ) && WC()->cart->total > 0 ) {
		foreach ( WC()->cart->get_cart() as $key => $item ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$item_total            = max( 0, (float) $item['line_subtotal'] );
			$item_total_minor_unit = round( $item_total * 100 );
			$product_id            = $item['product_id'];
			$product               = wc_get_product( $product_id );

			$cart_items[] = (object) [
				'name'            => $product->get_name(),
				'url'             => get_permalink( $product->get_id() ),
				'quantity'        => intval( $item['quantity'] ),
				'minorUnitAmount' => $item_total_minor_unit,
			];
		}
	}

	return $cart_items;
}

/**
 * Generate the cart details.
 */
function wcsp_get_cart() {
	$cart_id          = wcsp_get_cart_id();
	$cart_items       = wcsp_get_cart_items();
	$total_minor_unit = 0;

	if ( isset( WC()->cart ) ) {
		$cart_total = floatval( WC()->cart->total );

		if ( $cart_total >= 0 ) {
			$total_minor_unit = round( $cart_total * 100 );
		}
	}

	return [
		'id'               => $cart_id,
		'items'            => $cart_items,
		'total_minor_unit' => $total_minor_unit,
	];
}
