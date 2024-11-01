<?php
/**
 * Refunds related functions.
 *
 * @package super-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Super refund id to WooCommerce refund.
 *
 * @param int   $refund_id refund id.
 * @param array $refund_details refund details.
 */
function wcsp_refund_created( $refund_id, $refund_details ) {
	$order_id      = $refund_details['order_id'];
	$order         = wc_get_order( $order_id );
	$super_refunds = $order->get_meta( 'super_refunds', true );

	if ( is_array( $super_refunds ) ) {
		$index = count( $super_refunds );
		while ( $index ) {
			$super_refund = $super_refunds[ --$index ];

			if ( ! $super_refund['matched_to_woo_refund'] ) {
				$refund = wc_get_order( $refund_id );
				$refund->update_meta_data( 'super_refund_id', $super_refund['refund_id'] );
				$refund->update_meta_data( 'super_refund_reference', $super_refund['refund_reference'] );
				$refund->save();

				$super_refunds[ $index ]['matched_to_woo_refund'] = true;
				$order->update_meta_data( 'super_refunds', $super_refunds );
				$order->save();

				break;
			}
		}
	}
}

add_action( 'woocommerce_refund_created', 'wcsp_refund_created', 10, 2 );
