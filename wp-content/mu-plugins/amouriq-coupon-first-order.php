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
		throw new Exception( 'คูปองนี้ใช้ได้เฉพาะลูกค้าใหม่' );
	}
	return $valid;
}

/**
 * True when the current customer already has a counted order. Logged-in users
 * are checked by account and by billing email (covers earlier guest orders).
 * A guest with no email entered yet is not blocked here; the check runs again
 * when the order is placed.
 */
function amouriq_customer_has_previous_order() {
	$statuses = array( 'pending', 'on-hold', 'processing', 'completed' );

	$user_id = get_current_user_id();
	$email   = WC()->customer ? WC()->customer->get_billing_email() : '';
	if ( ! $email && $user_id ) {
		$user  = get_userdata( $user_id );
		$email = $user ? $user->user_email : '';
	}

	if ( $user_id ) {
		$by_account = wc_get_orders( array( 'limit' => 1, 'return' => 'ids', 'status' => $statuses, 'customer' => $user_id ) );
		if ( ! empty( $by_account ) ) {
			return true;
		}
	}
	if ( $email ) {
		$by_email = wc_get_orders( array( 'limit' => 1, 'return' => 'ids', 'status' => $statuses, 'customer' => $email ) );
		if ( ! empty( $by_email ) ) {
			return true;
		}
	}
	return false;
}
