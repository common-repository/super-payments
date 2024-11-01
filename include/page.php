<?php
/**
 * Super Payments page helper functions.
 *
 * @package super-payments
 */

/**
 * Check if we're on the order pay page.
 *
 * The order pay page is not the same as the checkout page. You can access a pay page
 * for any order that has not been paid for yet.
 *
 * @return bool
 */
function wcsp_is_order_pay_page() {
	if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return true;
	}

	return false;
}

/**
 * Get the page type.
 *
 * @return string page.
 */
function wcsp_get_page_type() {
	if ( is_front_page() ) {
		$page = 'home';
	} elseif ( is_shop() ) {
		$page = 'product-listing';
	} elseif ( is_cart() ) {
		$page = 'cart';
	} elseif ( is_product() ) {
		$page = 'product-detail';
	} elseif ( is_checkout() ) {
		$page = 'checkout';
	} elseif ( wcsp_is_order_pay_page() ) {
		$page = 'checkout';
	} else {
		// Sometimes the checkout page is not detected as a checkout page.
		// We fallback to "checkout" instead of "unknown" to ensure that marketing values are correctly shown as absolute.
		$page = 'checkout';
	}
	return $page;
}
