<?php
/**
 * Webhook utils.
 *
 * @package super-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate a signature for super payments webhooks.
 *
 * @param string $message message.
 * @param string $timestamp timestamp.
 * @param string $secret secret.
 */
function wcsp_generate_webhook_signature( $message, $timestamp, $secret ) {
	$payload = $timestamp . $message;
  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	$signature = base64_encode(
		hash_hmac( 'sha256', $payload, $secret, true )
	);
	return $signature;
}
