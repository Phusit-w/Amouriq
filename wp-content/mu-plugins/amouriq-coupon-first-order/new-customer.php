<?php
/**
 * The New customer rule (CONTEXT.md), on plain data so it can be tested without WordPress.
 *
 * A New customer has no counted order: pending, on-hold, processing or completed.
 * Cancelled, failed, refunded (and anything else) do not count. Orders are matched
 * by account (customer_id) and by billing email, ignoring case.
 *
 * $orders: list of array( status, customer_id, billing_email )
 */

defined( 'ABSPATH' ) || exit;

function amouriq_counted_order_statuses() {
	return array( 'pending', 'on-hold', 'processing', 'completed' );
}

function amouriq_is_new_customer( $orders, $user_id, $email ) {
	$email = strtolower( trim( (string) $email ) );

	foreach ( $orders as $order ) {
		if ( ! in_array( $order['status'], amouriq_counted_order_statuses(), true ) ) {
			continue;
		}
		if ( $user_id && (int) $order['customer_id'] === (int) $user_id ) {
			return false;
		}
		if ( '' !== $email && strtolower( trim( (string) $order['billing_email'] ) ) === $email ) {
			return false;
		}
	}
	return true;
}
