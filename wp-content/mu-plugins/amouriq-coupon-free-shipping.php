<?php
/**
 * Plugin Name: AMOURIQ: Coupon-triggered free shipping
 * Description: Flexible Shipping only checks its own order-total threshold and never
 *              reads WooCommerce's native "Allow free shipping" coupon checkbox, so a
 *              coupon with that box checked (e.g. AMOURIQ2025) had no effect on the
 *              actual shipping cost at checkout. This restores that behavior.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flexible-shipping/shipping-method/is-free-shipping', 'amouriq_coupon_triggers_free_shipping' );

function amouriq_coupon_triggers_free_shipping( $is_free_shipping ) {
	if ( $is_free_shipping || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $is_free_shipping;
	}

	foreach ( WC()->cart->get_applied_coupons() as $code ) {
		$coupon = new WC_Coupon( $code );
		if ( $coupon->get_free_shipping() ) {
			return true;
		}
	}

	return $is_free_shipping;
}
