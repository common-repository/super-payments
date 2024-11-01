<?php
/**
 * Super Payments checkout modifications.
 *
 * @package super-payments
 */

/**
 * Disable Super payment gateway for non GBP currencies.
 *
 * @param array $available_gateways Available gateways.
 *
 * @return array
 */
function wcsp_disable_super_gateway_for_non_gbp_currencies( $available_gateways ) {
	if ( is_admin() ) {
		return $available_gateways;
	}

	$currency = get_woocommerce_currency();

	if ( 'GBP' !== $currency ) {
		unset( $available_gateways['superpayments'] );
	}

	return $available_gateways;
}

add_filter( 'woocommerce_available_payment_gateways', 'wcsp_disable_super_gateway_for_non_gbp_currencies' );

