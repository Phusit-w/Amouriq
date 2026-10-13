<?php
/**
 * Plugin Name: AMOURIQ: Payment fee (cash on delivery)
 * Description: Adds the Payment fee (ค่าธรรมเนียมเก็บเงินปลายทาง) as a cart fee when the
 *              customer picks cash on delivery. Added after every discount (Deals, Member
 *              Privilege, Points), not taxable, and kept out of Points earning. The decision is in
 *              amouriq-payment-fee/fee-core.php; the owner's switch and amount are edited in the
 *              Deals screen (tab ค่าธรรมเนียม).
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/amouriq-payment-fee/fee-core.php';

/** The owner's switch and amount (on, ฿30 until changed). */
function amouriq_payment_fee_settings() {
	return amouriq_payment_fee_core_settings( get_option( 'amouriq_payment_fee_enabled', null ), get_option( 'amouriq_payment_fee_amount', null ) );
}

// Priority 30: after the Deals (10) and the points redemption (20), so no discount touches the fee.
add_action( 'woocommerce_cart_calculate_fees', 'amouriq_payment_fee_apply', 30 );

function amouriq_payment_fee_apply( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	$method = WC()->session ? WC()->session->get( 'chosen_payment_method' ) : '';
	if ( ! $method && function_exists( 'is_checkout' ) && is_checkout() && WC()->payment_gateways() ) {
		// Nothing chosen yet: the checkout form preselects the first available gateway.
		$method = (string) array_key_first( WC()->payment_gateways()->get_available_payment_gateways() );
	}
	$fee    = amouriq_payment_fee_core(
		$method,
		amouriq_payment_fee_settings(),
		array(
			'has_items'     => ! $cart->is_empty(),
			'free_shipping' => $cart->show_shipping() && 0.0 === (float) $cart->get_shipping_total(),
		)
	);
	if ( $fee ) {
		// Not taxable: the customer pays exactly this amount.
		$cart->add_fee( $fee['name'], $fee['amount'], $fee['taxable'] );
	}
}

// The order remembers the fee, so Points can leave it out of what was paid for goods.
add_action(
	'woocommerce_checkout_create_order',
	static function ( $order ) {
		$total = 0.0;
		foreach ( $order->get_fees() as $fee ) {
			if ( 'ค่าธรรมเนียมเก็บเงินปลายทาง' === $fee->get_name() ) {
				$total += (float) $fee->get_total() + (float) $fee->get_total_tax();
			}
		}
		if ( $total > 0 ) {
			$order->update_meta_data( '_amouriq_payment_fee', (string) round( $total, 2 ) );
		}
	},
	20
);

/** The Payment fee an order paid (0 when none). */
function amouriq_payment_fee_of_order( $order ) {
	return (float) $order->get_meta( '_amouriq_payment_fee', true );
}

/** The Payment fee currently on a cart (0 when none), tax included. */
function amouriq_payment_fee_in_cart( $cart ) {
	$total = 0.0;
	foreach ( $cart->get_fees() as $fee ) {
		if ( 'ค่าธรรมเนียมเก็บเงินปลายทาง' === $fee->name ) {
			$total += (float) $fee->amount + (float) $fee->tax;
		}
	}
	return $total;
}
