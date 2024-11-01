<?php
/**
 * Super Payments event calls.
 *
 * @package super-payments
 */

/**
 * Send event API call.
 *
 * @param string $event_name Event name.
 * @param array  $event_data Event data.
 * @param string $api_key API key.
 *
 * @return void
 */
function wcsp_send_event( $event_name, $event_data, $api_key = null ) {
	$super_payments = new WC_Super_Payments_Gateway();

	if ( empty( $api_key ) ) {
		$api_key = $super_payments->get_option( 'api_key' );
	}

	$integration_id = $super_payments->get_option( 'integration_id' );

	$send_event_request = [
		'headers' => wcsp_get_super_headers( $api_key, 'authorization' ),
		'body'    => wp_json_encode(
			[
				'event'    => $event_name,
				'payload'  => $event_data,
				'metadata' => [
					'platform'           => 'woo-commerce',
					'wordpressVersion'   => get_bloginfo( 'version' ),
					'phpVersion'         => phpversion(),
					'pluginVersion'      => PLUGIN_VERSION,
					'woocommerceVersion' => WC_VERSION,
					'siteUrl'            => get_site_url(),
					'integrationId'      => $integration_id,
				],
			]
		),
	];

	$test_mode        = $super_payments->get_option( 'test_mode' );
	$super_events_url = wcsp_get_super_api_url( $test_mode ) . '/custom-events';

	wp_remote_post( $super_events_url, $send_event_request );
}

/**
 * Send order status changed event.
 *
 * @param int    $order_id Order ID.
 * @param string $old_status Old order status.
 * @param string $new_status New order status.
 * @param object $order Order object.
 *
 * @return void.
 */
function wcsp_send_order_status_changed_event( $order_id, $old_status, $new_status, $order ) {
	wcsp_send_event(
		'OrderStatusChanged',
		[
			'orderId'                 => $order_id,
			'oldStatus'               => $old_status,
			'newStatus'               => $new_status,
			'orderAmount'             => $order->get_total(),
			'orderPaymentMethod'      => $order->get_payment_method(),
			'orderPaymentMethodTitle' => $order->get_payment_method_title(),
			'orderBillingPhone'       => $order->get_billing_phone(),
			'orderShippingPhone'      => $order->get_shipping_phone(),
			'orderDateCreated'        => empty( $order->get_date_created() ) ? null : $order->get_date_created()->date( DATE_ISO8601 ),
			'orderDateModified'       => empty( $order->get_date_modified() ) ? null : $order->get_date_modified()->date( DATE_ISO8601 ),
			'orderDatePaid'           => empty( $order->get_date_paid() ) ? null : $order->get_date_paid()->date( DATE_ISO8601 ),
			'orderDateCompleted'      => empty( $order->get_date_completed() ) ? null : $order->get_date_completed()->date( DATE_ISO8601 ),
			'orderMetadata'           => array_reduce(
				$order->get_meta_data(),
				function( $carry, $item ) {
					$carry[ $item->key ] = $item->value;
					return $carry;
				},
				[]
			),
			'superCartId'             => $order->get_meta( 'super_cart_id' ),
			'superTransactionId'      => $order->get_meta( 'super_transaction_id' ),
			'customerEmail'           => $order->get_billing_email(),
		]
	);
}
add_action( 'woocommerce_order_status_changed', 'wcsp_send_order_status_changed_event', 99, 4 );

/**
 * Send order created event.
 *
 * @param object $order Order object.
 *
 * @return void.
 */
function wcsp_send_order_created_event( $order ) {
	wcsp_send_event(
		'OrderCreated',
		[
			'orderId'                 => $order->get_id(),
			'orderAmount'             => $order->get_total(),
			'orderPaymentMethod'      => $order->get_payment_method(),
			'orderPaymentMethodTitle' => $order->get_payment_method_title(),
			'orderBillingPhone'       => $order->get_billing_phone(),
			'orderShippingPhone'      => $order->get_shipping_phone(),
			'orderDateCreated'        => empty( $order->get_date_created() ) ? null : $order->get_date_created()->date( DATE_ISO8601 ),
			'orderDateModified'       => empty( $order->get_date_modified() ) ? null : $order->get_date_modified()->date( DATE_ISO8601 ),
			'orderDatePaid'           => empty( $order->get_date_paid() ) ? null : $order->get_date_paid()->date( DATE_ISO8601 ),
			'orderDateCompleted'      => empty( $order->get_date_completed() ) ? null : $order->get_date_completed()->date( DATE_ISO8601 ),
			'orderMetadata'           => array_reduce(
				$order->get_meta_data(),
				function( $carry, $item ) {
					$carry[ $item->key ] = $item->value;
					return $carry;
				},
				[]
			),
			'orderItems'              => array_reduce(
				$order->get_items(),
				function( $carry, $item ) {
					if ( $item->is_type( 'line_item' ) ) {
						$carry[] = [
							'quantity'           => $item->get_quantity(),
							'price'              => $item->get_total(),
							'name'               => $item->get_name(),
							'productId'          => $item->get_product_id(),
							'productVariationId' => $item->get_variation_id(),
							'productSku'         => $item->get_product()->get_sku(),
							'productDescription' => $item->get_product()->get_description(),
						];
					}

					return $carry;
				},
				[]
			),
			'superCartId'             => $order->get_meta( 'super_cart_id' ),
			'customerEmail'           => $order->get_billing_email(),
		]
	);
}
add_action( 'woocommerce_checkout_order_created', 'wcsp_send_order_created_event', 99, 1 );

/**
 * Send super payments plugin settings updated event.
 *
 * @param array $old_value Old plugin settings value.
 * @param array $new_value New plugin settings value.
 * @param array $option_name Plugin settings option name.
 *
 * @return void.
 */
function wcsp_super_payments_settings_updated( $old_value, $new_value, $option_name ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

	if ( isset( WC()->payment_gateways ) ) {
		$payment_gateways         = WC()->payment_gateways->payment_gateways();
		$enabled_payment_gateways = array_keys(
			array_filter(
				$payment_gateways,
				function( $gateway ) {
					return 'yes' === $gateway->enabled;
				}
			)
		);
	} else {
		$enabled_payment_gateways = [];
	}

	wcsp_send_event(
		'PluginSettingsUpdated',
		[
			'paymentGatewayOrder' => $enabled_payment_gateways,
			'oldValue'            => [
				'enabled'                                  => ! empty( $old_value['enabled'] ) ? $old_value['enabled'] : null,
				'success_url'                              => ! empty( $old_value['success_url'] ) ? $old_value['success_url'] : null,
				'failure_url'                              => ! empty( $old_value['failure_url'] ) ? $old_value['failure_url'] : null,
				'cancel_url'                               => ! empty( $old_value['cancel_url'] ) ? $old_value['cancel_url'] : null,
				'enable_pdp'                               => ! empty( $old_value['enable_pdp'] ) ? $old_value['enable_pdp'] : null,
				'enable_bp'                                => ! empty( $old_value['enable_bp'] ) ? $old_value['enable_bp'] : null,
				'enable_banner_home'                       => ! empty( $old_value['enable_banner_home'] ) ? $old_value['enable_banner_home'] : null,
				'enable_banner_site'                       => ! empty( $old_value['enable_banner_site'] ) ? $old_value['enable_banner_site'] : null,
				'update_total'                             => ! empty( $old_value['update_total'] ) ? $old_value['update_total'] : null,
				'enable_order_received_page_referral_link' => ! empty( $old_value['enable_order_received_page_referral_link'] ) ? $old_value['enable_order_received_page_referral_link'] : null,
				'enable_order_email_referral_link'         => ! empty( $old_value['enable_order_email_referral_link'] ) ? $old_value['enable_order_email_referral_link'] : null,
				'set_as_default_payment_method'            => ! empty( $old_value['set_as_default_payment_method'] ) ? $old_value['set_as_default_payment_method'] : null,
			],
			'newValue'            => [
				'enabled'                                  => ! empty( $new_value['enabled'] ) ? $new_value['enabled'] : null,
				'success_url'                              => ! empty( $new_value['success_url'] ) ? $new_value['success_url'] : null,
				'failure_url'                              => ! empty( $new_value['failure_url'] ) ? $new_value['failure_url'] : null,
				'cancel_url'                               => ! empty( $new_value['cancel_url'] ) ? $new_value['cancel_url'] : null,
				'enable_pdp'                               => ! empty( $new_value['enable_pdp'] ) ? $new_value['enable_pdp'] : null,
				'enable_bp'                                => ! empty( $new_value['enable_bp'] ) ? $new_value['enable_bp'] : null,
				'enable_banner_home'                       => ! empty( $new_value['enable_banner_home'] ) ? $new_value['enable_banner_home'] : null,
				'enable_banner_site'                       => ! empty( $new_value['enable_banner_site'] ) ? $new_value['enable_banner_site'] : null,
				'update_total'                             => ! empty( $new_value['update_total'] ) ? $new_value['update_total'] : null,
				'enable_order_received_page_referral_link' => ! empty( $new_value['enable_order_received_page_referral_link'] ) ? $new_value['enable_order_received_page_referral_link'] : null,
				'enable_order_email_referral_link'         => ! empty( $new_value['enable_order_email_referral_link'] ) ? $new_value['enable_order_email_referral_link'] : null,
				'set_as_default_payment_method'            => ! empty( $new_value['set_as_default_payment_method'] ) ? $new_value['set_as_default_payment_method'] : null,
			],
		]
	);
}
add_action( 'update_option_woocommerce_superpayments_settings', 'wcsp_super_payments_settings_updated', 10, 3 );


/**
 * Send payment gateways order updated event.
 *
 * @param array $old_value Old payment gateways order value.
 * @param array $new_value New payment gateways order value.
 * @param array $option_name Payment gateways order option name.
 *
 * @return void.
 */
function wcsp_payment_gateways_order_updated( $old_value, $new_value, $option_name ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	$payment_gateways         = WC()->payment_gateways->payment_gateways();
	$enabled_payment_gateways = array_filter(
		$payment_gateways,
		function( $gateway ) {
			return 'yes' === $gateway->enabled;
		}
	);

	wcsp_send_event(
		'PaymentGatewaysOrderUpdated',
		[
			'oldValue' => $old_value,
			'newValue' => $new_value,
			'enabled'  => array_keys( $enabled_payment_gateways ),
		]
	);
}
add_action( 'update_option_woocommerce_gateway_order', 'wcsp_payment_gateways_order_updated', 10, 3 );

/**
 * Send cart id created event.
 *
 * @param string $super_cart_id ID of the cart.
 *
 * @return void
 */
function wcsp_send_cart_id_created_event( $super_cart_id ) {
	wcsp_send_event(
		'CartIdCreated',
		[
			'id'                => $super_cart_id,
			'cartIdDateCreated' => gmdate( DATE_ISO8601 ),
		]
	);
}
add_action( 'wcsp_cart_id_generated', 'wcsp_send_cart_id_created_event', 10, 1 );
