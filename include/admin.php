<?php
/**
 * Super Payments admin functionality.
 *
 * @package super-payments
 */

/**
 * Adds the Super Payments cash reward amount to the order totals on the admin order page.
 *
 * @param int $order_id The order ID.
 */
function wcsp_admin_order_totals_after_shipping( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( empty( $order ) ) {
		return;
	}

	if ( 'superpayments' === $order->get_payment_method() ) {
		$super_cash_reward_amount = $order->get_meta( 'super_merchant_funded_amount' );

		// This is a fallback for older versions of the plugin that didn't save the cash reward amount in the order meta.
		if ( empty( $super_cash_reward_amount ) ) {
			$manual_order_total = (float) $order->get_subtotal() - (float) $order->get_total_discount() + (float) $order->get_total_fees() + (float) $order->get_shipping_total() + (float) $order->get_total_tax();
			$order_total        = $order->get_total();

			$super_cash_reward_amount = $manual_order_total - $order_total;
		}

		if ( is_numeric( $super_cash_reward_amount ) && $super_cash_reward_amount > 0 ) {
			$super_cash_reward_label = __( 'Super Payment Cash Reward: ', 'super-payments' );
			$order_currency          = $order->get_currency();

			?>
			<tr>
				<td class="label"><?php echo esc_html( $super_cash_reward_label ); ?></td>
				<td width="1%"></td>
				<td class="total">-<?php echo wc_price( $super_cash_reward_amount, [ 'currency' => $order_currency ] ); ?></td>
			</tr>
			<?php
		}
	}
}
add_action( 'woocommerce_admin_order_totals_after_tax', 'wcsp_admin_order_totals_after_shipping', 10, 1 );

/**
 * Adds settings and support links on the installed plugins page.
 *
 * @param array $actions plugin action links.
 */
function wcsp_add_action_links( $actions ) {
	$setting_link = add_query_arg(
		[
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => 'superpayments',
		],
		admin_url( 'admin.php' )
	);

	$custom_actions = [
		'settings' => '<a href="' . esc_url( $setting_link ) . '" aria-label="' . esc_attr__( 'View Super Payments Settings', 'super-payments' ) . '">' . __( 'Settings', 'super-payments' ) . '</a>',
		'support'  => '<a href="https://support.superpayments.com/" aria-label="' . esc_attr__( 'Get Super Payments Support', 'super-payments' ) . '">' . __( 'Support', 'super-payments' ) . '</a>',
	];

	return $custom_actions + $actions;
}
add_filter( 'plugin_action_links_' . WC_Super_Payments::plugin_basename(), 'wcsp_add_action_links' );

/**
 * Adds a notice to the Super Payments settings page, to whitelist Super's public IP addresses if Cloudflare is detected.
 */
function wcsp_cloudflare_notice() {
	global $current_tab, $current_section;

	if ( 'checkout' === $current_tab && 'superpayments' === $current_section ) {
		$cloudflare_detected = false;
		$cloudflare_headers  = [
			'CF-CONNECTING-IP',
			'CF-CONNECTING-IPV6',
			'CF-IPCOUNTRY',
			'CF-RAY',
			'CF-VISITOR',
			'CF-WORKER',
		];

		// Loop throught the headers to check if Cloudflare is being used.
		foreach ( $cloudflare_headers as $header ) {
			$header_key = 'HTTP_' . $header;

			if ( isset( $_SERVER[ $header_key ] ) ) {
				$cloudflare_detected = true;
				break;
			}
		}

		if ( $cloudflare_detected ) {
			?>
				<div class="notice notice-warning">
					<p>
						<?php
						/* translators: %1$s: opening a tag, %2$s: closing a tag */
						echo wp_kses_post( sprintf( __( 'It looks like you might be using Cloudflare, please ensure you whitelist Super\'s <a href="%1$s" target="_blank">public IP addresses</a> in your Cloudflare firewall settings.', 'super-payments' ), 'https://docs.superpayments.com/docs/external-ips' ) );
						?>
					</p>
				</div>
			<?php
		}
	}
}
add_action( 'admin_notices', 'wcsp_cloudflare_notice' );

/**
 * Adds a notice to the Super Payments settings page, to remind the user that the plugin is in test mode.
 */
function wcsp_test_mode_notice() {
	$super_payments = new WC_Super_Payments_Gateway();
	$test_mode      = $super_payments->get_option( 'test_mode' );

	if ( 'yes' === $test_mode ) {
		?>
		<div class="notice notice-warning">
			<p>
				<?php echo esc_html__( 'Super Payments is currently in test mode.', 'super-payments' ); ?>
			</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'wcsp_test_mode_notice' );
