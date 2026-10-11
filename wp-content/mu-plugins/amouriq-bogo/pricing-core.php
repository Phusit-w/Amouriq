<?php
/**
 * AMOURIQ BOGO pricing core: the maths of what a Deal discounts, on plain data.
 *
 * No WordPress or WooCommerce functions are called here, so it can be tested
 * without either (only the ABSPATH guard below is WordPress-aware). The WooCommerce side (amouriq-bogo-deals.php) turns the cart into
 * the plain data below, calls amouriq_bogo_price(), and applies the result.
 *
 * The lines may also be a callable returning them. A cart line:  product_id, variation_id, quantity, unit_price (tax-exclusive),
 *               on_sale (bool), categories (the line's product_cat slugs and term IDs)
 * The context:  today ('Y-m-d'), applied_coupons (list of codes),
 *               customer_uses (int, or null when the customer is not yet known; may be
 *               a callable returning either, so the lookup only runs when needed),
 *               prices (product or variation id => tax-exclusive unit price)
 *
 * Returns array( 'discount' => float, 'reason' => string|null ). `reason` says
 * why a Deal gave nothing when it was stopped before the maths:
 * disabled, outside_window, coupon_missing, customer_limit, blocked_by_other_coupon.
 */

defined( 'ABSPATH' ) || exit;

function amouriq_bogo_price( $deal, $lines, $ctx ) {
	$reason = amouriq_bogo_core_stop_reason( $deal, $ctx );
	if ( null !== $reason ) {
		return array( 'discount' => 0.0, 'reason' => $reason );
	}
	// A callable, so the caller only builds the cart lines for a deal that got this far.
	$lines = is_callable( $lines ) ? call_user_func( $lines ) : $lines;
	return array( 'discount' => (float) amouriq_bogo_core_discount( $deal, $lines, $ctx ), 'reason' => null );
}

/**
 * The first rule that stops this deal before any maths, or null when none does.
 * The order matters: the "not combinable" notice only shows for a deal that
 * would otherwise have applied.
 */
function amouriq_bogo_core_stop_reason( $deal, $ctx ) {
	// A deal saved before the on/off switch existed has no `enabled` key and stays on.
	if ( isset( $deal['enabled'] ) && ! $deal['enabled'] ) {
		return 'disabled';
	}

	if ( ! empty( $deal['valid_from'] ) && $ctx['today'] < $deal['valid_from'] ) {
		return 'outside_window';
	}
	if ( ! empty( $deal['valid_to'] ) && $ctx['today'] > $deal['valid_to'] ) {
		return 'outside_window';
	}

	$own = '';
	if ( isset( $deal['trigger'] ) && 'coupon' === $deal['trigger'] ) {
		$own = amouriq_bogo_core_code( isset( $deal['coupon_code'] ) ? $deal['coupon_code'] : '' );
		if ( '' === $own || ! in_array( $own, array_map( 'amouriq_bogo_core_code', $ctx['applied_coupons'] ), true ) ) {
			return 'coupon_missing';
		}
	}

	$max = isset( $deal['max_uses_per_customer'] ) ? (int) $deal['max_uses_per_customer'] : 0;
	if ( $max > 0 ) {
		// A callable, so the (database) lookup only runs for a deal that got this far.
		$uses = is_callable( $ctx['customer_uses'] ) ? call_user_func( $ctx['customer_uses'] ) : $ctx['customer_uses'];
		if ( null === $uses || $uses >= $max ) {
			return 'customer_limit';
		}
	}

	if ( ! empty( $deal['no_combine'] ) ) {
		foreach ( $ctx['applied_coupons'] as $applied ) {
			if ( amouriq_bogo_core_code( $applied ) !== $own ) {
				return 'blocked_by_other_coupon';
			}
		}
	}

	return null;
}

/** Coupon codes compare case-insensitively, as WooCommerce does. */
function amouriq_bogo_core_code( $code ) {
	return strtolower( trim( (string) $code ) );
}

function amouriq_bogo_core_discount( $deal, $lines, $ctx ) {
	switch ( isset( $deal['type'] ) ? $deal['type'] : '' ) {
		case 'same_product':
			return amouriq_bogo_core_same_product( $deal, $lines, $ctx );
		case 'product_pair':
			return amouriq_bogo_core_product_pair( $deal, $lines, $ctx );
		case 'category_cheapest_free':
		case 'cheapest_free':
			return amouriq_bogo_core_category_cheapest( $deal, $lines );
		case 'quantity_tier':
			return amouriq_bogo_core_quantity_tier( $deal, $lines, $ctx );
	}
	return 0.0;
}

/** True when this line is left out of the deal because the deal skips sale items. */
function amouriq_bogo_core_excluded( $line, $deal ) {
	return ! empty( $deal['exclude_sale'] ) && ! empty( $line['on_sale'] );
}

/** Cap on how many times a deal can apply to one cart (0 = no cap). */
function amouriq_bogo_core_cap( $count, $deal ) {
	$max = isset( $deal['max_per_order'] ) ? (int) $deal['max_per_order'] : 0;
	return $max > 0 ? min( $count, $max ) : $count;
}

/** Total quantity of a product (or a specific variation) across the cart lines. */
function amouriq_bogo_core_qty( $lines, $product_id, $deal ) {
	$qty = 0;
	foreach ( $lines as $line ) {
		if ( (int) $line['product_id'] === (int) $product_id || (int) $line['variation_id'] === (int) $product_id ) {
			if ( amouriq_bogo_core_excluded( $line, $deal ) ) {
				continue;
			}
			$qty += $line['quantity'];
		}
	}
	return $qty;
}

function amouriq_bogo_core_price_of( $product_id, $ctx ) {
	return isset( $ctx['prices'][ $product_id ] ) ? (float) $ctx['prices'][ $product_id ] : 0.0;
}

function amouriq_bogo_core_same_product( $deal, $lines, $ctx ) {
	if ( empty( $deal['product_id'] ) ) {
		return 0.0;
	}

	$buy_qty = max( 1, (int) ( isset( $deal['buy_qty'] ) ? $deal['buy_qty'] : 1 ) );
	$get_qty = max( 1, (int) ( isset( $deal['get_qty'] ) ? $deal['get_qty'] : 1 ) );
	$percent = isset( $deal['get_discount_percent'] ) ? (float) $deal['get_discount_percent'] : 100.0;

	$groups = amouriq_bogo_core_cap( intdiv( amouriq_bogo_core_qty( $lines, $deal['product_id'], $deal ), $buy_qty + $get_qty ), $deal );
	if ( $groups <= 0 ) {
		return 0.0;
	}

	return $groups * $get_qty * amouriq_bogo_core_price_of( $deal['product_id'], $ctx ) * ( $percent / 100 );
}

function amouriq_bogo_core_product_pair( $deal, $lines, $ctx ) {
	if ( empty( $deal['buy_product_id'] ) || empty( $deal['get_product_id'] ) ) {
		return 0.0;
	}

	$buy_qty = max( 1, (int) ( isset( $deal['buy_qty'] ) ? $deal['buy_qty'] : 1 ) );
	$get_qty = max( 1, (int) ( isset( $deal['get_qty'] ) ? $deal['get_qty'] : 1 ) );
	$percent = isset( $deal['get_discount_percent'] ) ? (float) $deal['get_discount_percent'] : 100.0;

	$buy_in_cart = amouriq_bogo_core_qty( $lines, $deal['buy_product_id'], $deal );
	$get_in_cart = amouriq_bogo_core_qty( $lines, $deal['get_product_id'], $deal );

	$triggers         = amouriq_bogo_core_cap( intdiv( $buy_in_cart, $buy_qty ), $deal );
	$discounted_units = min( $triggers * $get_qty, $get_in_cart );
	if ( $discounted_units <= 0 ) {
		return 0.0;
	}

	return $discounted_units * amouriq_bogo_core_price_of( $deal['get_product_id'], $ctx ) * ( $percent / 100 );
}

/**
 * Cheapest item of each group of buy_qty free. Limited to one category when
 * the deal sets `category` (a product_cat slug or ID); otherwise any item counts.
 */
function amouriq_bogo_core_category_cheapest( $deal, $lines ) {
	$buy_qty = max( 1, (int) ( isset( $deal['buy_qty'] ) ? $deal['buy_qty'] : 1 ) );
	$percent = isset( $deal['get_discount_percent'] ) ? (float) $deal['get_discount_percent'] : 100.0;

	$unit_prices = array();
	foreach ( $lines as $line ) {
		if ( ! empty( $deal['category'] ) && ! in_array( (string) $deal['category'], array_map( 'strval', $line['categories'] ), true ) ) {
			continue;
		}
		if ( amouriq_bogo_core_excluded( $line, $deal ) ) {
			continue;
		}
		for ( $i = 0; $i < $line['quantity']; $i++ ) {
			$unit_prices[] = (float) $line['unit_price'];
		}
	}

	$groups = amouriq_bogo_core_cap( intdiv( count( $unit_prices ), $buy_qty ), $deal );
	if ( $groups <= 0 ) {
		return 0.0;
	}

	// Sort most expensive first and cut into groups of buy_qty. The cheapest
	// item of each complete group is the last one in that group, so it sits at
	// index (group + 1) * buy_qty - 1. A leftover partial group gets nothing.
	rsort( $unit_prices );
	$discount = 0.0;
	for ( $g = 0; $g < $groups; $g++ ) {
		$discount += $unit_prices[ ( $g + 1 ) * $buy_qty - 1 ] * ( $percent / 100 );
	}
	return $discount;
}

/**
 * Tiered quantity discount on one product: the highest tier reached gives its
 * percent off every unit of that product in the cart. Tiers look like
 * array( array( 'min' => 6, 'percent' => 10 ), array( 'min' => 12, 'percent' => 15 ) ).
 */
function amouriq_bogo_core_quantity_tier( $deal, $lines, $ctx ) {
	if ( empty( $deal['product_id'] ) || empty( $deal['tiers'] ) || ! is_array( $deal['tiers'] ) ) {
		return 0.0;
	}

	$qty     = amouriq_bogo_core_qty( $lines, $deal['product_id'], $deal );
	$percent = 0.0;
	foreach ( $deal['tiers'] as $tier ) {
		if ( isset( $tier['min'], $tier['percent'] ) && $qty >= (int) $tier['min'] ) {
			$percent = max( $percent, (float) $tier['percent'] );
		}
	}
	if ( $percent <= 0 ) {
		return 0.0;
	}

	return $qty * amouriq_bogo_core_price_of( $deal['product_id'], $ctx ) * ( $percent / 100 );
}
