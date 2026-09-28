<?php
/**
 * Plugin Name: AMOURIQ: BOGO / bundle deals
 * Description: Buy-X-get-Y style deals that native WooCommerce coupons can't
 *              express (a coupon can discount the whole cart or restrict
 *              itself to a category, but it can't say "free item B when you
 *              buy item A"). Deals are defined in amouriq-bogo/config.php —
 *              edit that file only, this one is the engine and normally
 *              needs no changes. Applied as one negative cart fee per active
 *              deal on `woocommerce_cart_calculate_fees`, so it shows as its
 *              own "Deal discount" line at checkout without rewriting any
 *              product's displayed price.
 */

defined( 'ABSPATH' ) || exit;

function amouriq_bogo_deals() {
	static $deals = null;
	if ( null === $deals ) {
		$file  = __DIR__ . '/amouriq-bogo/config.php';
		$deals = file_exists( $file ) ? (array) include $file : array();
	}
	return $deals;
}

add_action( 'woocommerce_cart_calculate_fees', 'amouriq_bogo_apply_deals' );

function amouriq_bogo_apply_deals( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	foreach ( amouriq_bogo_deals() as $index => $deal ) {
		if ( ! amouriq_bogo_deal_active( $deal, $cart ) ) {
			continue;
		}

		$discount = amouriq_bogo_calculate_discount( $deal, $cart );
		if ( $discount <= 0 ) {
			continue;
		}

		$label = ! empty( $deal['label'] ) ? $deal['label'] : __( 'Deal discount', 'amouriq' );
		// Suffix with the array index so two deals can never collide on the
		// fee's auto-generated ID (WooCommerce derives it from this name).
		// `taxable` here only matters for a positive fee, never a discount —
		// see the note on amouriq_bogo_unit_price() above.
		$cart->add_fee( $label . ' #' . ( $index + 1 ), -$discount, false );
	}
}

function amouriq_bogo_deal_active( $deal, $cart ) {
	$trigger = isset( $deal['trigger'] ) ? $deal['trigger'] : 'auto';
	if ( 'coupon' !== $trigger ) {
		return true;
	}

	$code = ! empty( $deal['coupon_code'] ) ? wc_format_coupon_code( $deal['coupon_code'] ) : '';
	if ( ! $code ) {
		return false;
	}

	foreach ( $cart->get_applied_coupons() as $applied ) {
		if ( wc_is_same_coupon( $applied, $code ) ) {
			return true;
		}
	}
	return false;
}

function amouriq_bogo_calculate_discount( $deal, $cart ) {
	switch ( isset( $deal['type'] ) ? $deal['type'] : '' ) {
		case 'same_product':
			return amouriq_bogo_discount_same_product( $deal, $cart );
		case 'product_pair':
			return amouriq_bogo_discount_product_pair( $deal, $cart );
		case 'category_cheapest_free':
			return amouriq_bogo_discount_category_cheapest( $deal, $cart );
	}
	return 0.0;
}

/**
 * Total quantity of a given product (or a specific variation) in the cart.
 */
function amouriq_bogo_qty_in_cart( $cart, $product_id ) {
	$qty = 0;
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( (int) $cart_item['product_id'] === (int) $product_id
			|| (int) $cart_item['variation_id'] === (int) $product_id ) {
			$qty += $cart_item['quantity'];
		}
	}
	return $qty;
}

/**
 * Tax-exclusive unit price, for feeding into WC_Cart::add_fee().
 *
 * A negative cart fee always gets taxed on top of whatever amount we hand
 * it (WooCommerce splits tax across a negative fee unconditionally — see
 * WC_Cart_Totals::get_fees_from_cart(), the `taxable` flag on add_fee() only
 * affects *positive* fees). This store has "prices entered with tax" on, so
 * get_price() is tax-inclusive; passing that straight to add_fee() made
 * WooCommerce add another 7% on top (confirmed live: a 369.00 discount
 * became -394.83). Passing the tax-exclusive price instead lets WC's own
 * gross-up land back on the intended tax-inclusive amount.
 */
function amouriq_bogo_unit_price( $product_id ) {
	$product = wc_get_product( $product_id );
	return $product ? (float) wc_get_price_excluding_tax( $product ) : 0.0;
}

function amouriq_bogo_discount_same_product( $deal, $cart ) {
	if ( empty( $deal['product_id'] ) ) {
		return 0.0;
	}

	$buy_qty = max( 1, (int) ( $deal['buy_qty'] ?? 1 ) );
	$get_qty = max( 1, (int) ( $deal['get_qty'] ?? 1 ) );
	$percent = isset( $deal['get_discount_percent'] ) ? (float) $deal['get_discount_percent'] : 100.0;

	$group_size  = $buy_qty + $get_qty;
	$qty_in_cart = amouriq_bogo_qty_in_cart( $cart, $deal['product_id'] );
	$groups      = intdiv( $qty_in_cart, $group_size );
	if ( $groups <= 0 ) {
		return 0.0;
	}

	$free_units = $groups * $get_qty;
	return $free_units * amouriq_bogo_unit_price( $deal['product_id'] ) * ( $percent / 100 );
}

function amouriq_bogo_discount_product_pair( $deal, $cart ) {
	if ( empty( $deal['buy_product_id'] ) || empty( $deal['get_product_id'] ) ) {
		return 0.0;
	}

	$buy_qty = max( 1, (int) ( $deal['buy_qty'] ?? 1 ) );
	$get_qty = max( 1, (int) ( $deal['get_qty'] ?? 1 ) );
	$percent = isset( $deal['get_discount_percent'] ) ? (float) $deal['get_discount_percent'] : 100.0;

	$buy_in_cart = amouriq_bogo_qty_in_cart( $cart, $deal['buy_product_id'] );
	$get_in_cart = amouriq_bogo_qty_in_cart( $cart, $deal['get_product_id'] );

	$triggers        = intdiv( $buy_in_cart, $buy_qty );
	$discounted_units = min( $triggers * $get_qty, $get_in_cart );
	if ( $discounted_units <= 0 ) {
		return 0.0;
	}

	return $discounted_units * amouriq_bogo_unit_price( $deal['get_product_id'] ) * ( $percent / 100 );
}

function amouriq_bogo_discount_category_cheapest( $deal, $cart ) {
	if ( empty( $deal['category'] ) ) {
		return 0.0;
	}

	$buy_qty = max( 1, (int) ( $deal['buy_qty'] ?? 1 ) );
	$percent = isset( $deal['get_discount_percent'] ) ? (float) $deal['get_discount_percent'] : 100.0;

	$unit_prices = array();
	foreach ( $cart->get_cart() as $cart_item ) {
		$parent_id = $cart_item['product_id'];
		if ( ! has_term( $deal['category'], 'product_cat', $parent_id ) ) {
			continue;
		}
		$unit_price = amouriq_bogo_unit_price(
			$cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id']
		);
		for ( $i = 0; $i < $cart_item['quantity']; $i++ ) {
			$unit_prices[] = $unit_price;
		}
	}

	$groups = intdiv( count( $unit_prices ), $buy_qty );
	if ( $groups <= 0 ) {
		return 0.0;
	}

	sort( $unit_prices );
	$discount = 0.0;
	for ( $i = 0; $i < $groups; $i++ ) {
		$discount += $unit_prices[ $i ] * ( $percent / 100 );
	}
	return $discount;
}
