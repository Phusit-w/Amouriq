<?php
/**
 * Plugin Name: AMOURIQ: first-order-only coupons
 * Description: Adds a "ลูกค้าใหม่เท่านั้น" option to WooCommerce coupons. The
 *              coupon is created and managed as usual under Marketing > Coupons;
 *              when this option is ticked it only applies to customers with no
 *              earlier order that is still counted (not cancelled, failed or
 *              refunded). Logged-in customers are matched by account, guests by
 *              billing email.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/amouriq-coupon-first-order/new-customer.php';

add_action( 'woocommerce_coupon_options_usage_restriction', 'amouriq_first_order_coupon_field' );

function amouriq_first_order_coupon_field() {
	woocommerce_wp_checkbox(
		array(
			'id'          => 'amouriq_first_order_only',
			'label'       => 'ลูกค้าใหม่เท่านั้น',
			'description' => 'ใช้ได้เฉพาะลูกค้าที่ยังไม่เคยมีออเดอร์ (ออเดอร์ที่ถูกยกเลิก ล้มเหลว หรือคืนเงินไม่นับ)',
		)
	);
}

add_action( 'woocommerce_coupon_options_save', 'amouriq_first_order_coupon_save' );

function amouriq_first_order_coupon_save( $post_id ) {
	update_post_meta( $post_id, 'amouriq_first_order_only', isset( $_POST['amouriq_first_order_only'] ) ? 'yes' : 'no' );
}

add_filter( 'woocommerce_coupon_is_valid', 'amouriq_first_order_coupon_check', 10, 2 );

function amouriq_first_order_coupon_check( $valid, $coupon ) {
	if ( ! $valid || 'yes' !== $coupon->get_meta( 'amouriq_first_order_only' ) ) {
		return $valid;
	}
	if ( amouriq_customer_has_previous_order() ) {
		// WooCommerce shows the message of the exception to the customer.
		throw new Exception( 'คูปองนี้ใช้ได้เฉพาะลูกค้าใหม่ที่ยังไม่เคยสั่งซื้อ (บัญชีหรืออีเมลนี้เคยมีออเดอร์แล้ว)' );
	}
	return $valid;
}


/**
 * True when the current customer already has a counted order (see
 * amouriq-coupon-first-order/new-customer.php for the rule). Matched by account
 * and by billing email, which covers earlier guest orders and a shopper who is
 * registering in this checkout. A guest with no email entered yet is not blocked
 * here; the check runs again when the order is placed.
 */
function amouriq_customer_has_previous_order() {
	$user_id = get_current_user_id();
	$email   = WC()->customer ? WC()->customer->get_billing_email() : '';
	if ( ! $email && $user_id ) {
		$user  = get_userdata( $user_id );
		$email = $user ? $user->user_email : '';
	}

	// Candidate orders by account and by email, then let the rule decide.
	$found = array();
	if ( $user_id ) {
		$found = array_merge( $found, wc_get_orders( array( 'limit' => -1, 'customer' => $user_id, 'status' => amouriq_counted_order_statuses() ) ) );
	}
	if ( $email ) {
		$found = array_merge( $found, wc_get_orders( array( 'limit' => -1, 'customer' => $email, 'status' => amouriq_counted_order_statuses() ) ) );
	}

	$orders = array();
	foreach ( $found as $order ) {
		$orders[] = array(
			'status'        => $order->get_status(),
			'customer_id'   => $order->get_customer_id(),
			'billing_email' => $order->get_billing_email(),
		);
	}
	return ! amouriq_is_new_customer( $orders, $user_id, $email );
}
