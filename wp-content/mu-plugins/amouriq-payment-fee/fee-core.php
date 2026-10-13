<?php
/**
 * AMOURIQ Payment fee core (ค่าธรรมเนียมเก็บเงินปลายทาง): the decision on plain data.
 *
 * No WordPress or WooCommerce functions are called, so it can be tested without either.
 * See CONTEXT.md "Payment fee". The WooCommerce side is amouriq-payment-fee.php.
 */

defined( 'ABSPATH' ) || exit;

/** The WooCommerce payment gateway id of cash on delivery. */
const AMOURIQ_PAYMENT_FEE_METHOD = 'cod';

/** Default charge, in baht, when the owner has not set one. */
const AMOURIQ_PAYMENT_FEE_DEFAULT = 30.0;

/**
 * Owner settings from the stored option values (null when never saved):
 * on by default, ฿30 by default; an amount that is not a number falls back to the default.
 */
function amouriq_payment_fee_core_settings( $enabled, $amount ) {
	$amount = is_string( $amount ) ? str_replace( ',', '', trim( $amount ) ) : $amount;
	return array(
		'enabled' => 'no' !== $enabled,
		'amount'  => is_numeric( $amount ) ? (float) $amount : AMOURIQ_PAYMENT_FEE_DEFAULT,
	);
}

/**
 * The fee for this cart, or null when none applies.
 *
 * $settings: enabled (bool), amount (baht).
 * $ctx:      has_items (bool); free_shipping (bool) is accepted but changes nothing, as the fee
 *            is charged even when shipping is free.
 * Returns array( 'name', 'amount', 'taxable' ). `taxable` is always false: the customer pays
 * exactly the amount, with no tax on top.
 */
function amouriq_payment_fee_core( $method, $settings, $ctx ) {
	if ( AMOURIQ_PAYMENT_FEE_METHOD !== $method || empty( $settings['enabled'] ) || empty( $ctx['has_items'] ) ) {
		return null;
	}
	$amount = round( (float) $settings['amount'], 2 );
	if ( $amount <= 0 ) {
		return null;
	}
	return array(
		'name'    => 'ค่าธรรมเนียมเก็บเงินปลายทาง',
		'amount'  => $amount,
		'taxable' => false,
	);
}

/** What counts as purchase value (for Points) once the Payment fee is taken off an eligible total. */
function amouriq_payment_fee_core_eligible( $eligible, $fee ) {
	return max( 0.0, round( (float) $eligible - (float) $fee, 2 ) );
}
