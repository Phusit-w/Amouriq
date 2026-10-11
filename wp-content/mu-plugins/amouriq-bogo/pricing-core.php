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
 *               a callable given the deal and returning either, so the lookup only runs when needed),
 *               prices (product or variation id => tax-exclusive unit price)
 *
 * Returns array( 'discount' => float, 'per_line' => array, 'reason' => string|null ).
 * `per_line` splits the discount over the cart lines (line index => amount; the
 * amounts add up to `discount`). `reason` says why a Deal gave nothing when it was
 * stopped before the maths:
 * disabled, outside_window, coupon_missing, customer_limit, blocked_by_other_coupon.
 *
 * Member Privilege (ADR-0006) is amouriq_bogo_member_privilege() below; it
 * needs the per-line Deal discounts. amouriq_bogo_cart_discounts() runs every
 * Deal and Member Privilege together. The context may carry `is_member` (bool);
 * a line may carry `price_incl_tax` (the price the shopper sees, used for the
 * minimum spend; defaults to unit_price).
 */

defined( 'ABSPATH' ) || exit;

function amouriq_bogo_price( $deal, $lines, $ctx ) {
	$reason = amouriq_bogo_core_stop_reason( $deal, $ctx );
	if ( null !== $reason ) {
		return array( 'discount' => 0.0, 'per_line' => array(), 'reason' => $reason );
	}
	// A callable, so the caller only builds the cart lines for a deal that got this far.
	$lines    = is_callable( $lines ) ? call_user_func( $lines ) : $lines;
	$discount = (float) amouriq_bogo_core_discount( $deal, $lines, $ctx );
	return array(
		'discount' => $discount,
		'per_line' => $discount > 0 ? amouriq_bogo_core_split( $deal, $lines, $discount ) : array(),
		'reason'   => null,
	);
}

/** Member Privilege's rule: percent off Regular Price lines once the item total reaches min_spend. */
function amouriq_bogo_member_rule() {
	return array( 'percent' => 5.0, 'min_spend' => 299.0 );
}

/**
 * Run every Deal and then Member Privilege over one cart.
 * Returns array( 'deals' => array( deal index => amouriq_bogo_price() result ), 'member' => result ).
 */
function amouriq_bogo_cart_discounts( $deals, $lines, $ctx ) {
	// Build the lines once, and only if a Deal or Member Privilege needs them.
	$built = null;
	$get   = static function () use ( &$built, $lines ) {
		if ( null === $built ) {
			$built = is_callable( $lines ) ? call_user_func( $lines ) : $lines;
		}
		return $built;
	};

	$results  = array();
	$per_line = array();
	foreach ( $deals as $index => $deal ) {
		$results[ $index ] = amouriq_bogo_price( $deal, $get, $ctx );
		foreach ( $results[ $index ]['per_line'] as $i => $amount ) {
			$per_line[ $i ] = ( isset( $per_line[ $i ] ) ? $per_line[ $i ] : 0.0 ) + $amount;
		}
	}

	return array(
		'deals'  => $results,
		'member' => amouriq_bogo_member_privilege( $get, $per_line, $ctx ),
	);
}

/**
 * Member Privilege on top of the Deals' per-line discounts. Per line the larger
 * of the two applies, never both, so the extra discount it adds is only what it
 * has over the Deal on that line. Returns array( 'discount', 'per_line',
 * 'deal_won' (line indexes where a Deal gave at least as much), 'reason', 'winner' ).
 * `reason`: not_member, below_minimum, no_regular_lines, deal_larger, coupon_larger (null when it applied).
 * The context's `coupon_discount` is what the Coupons in the cart already take
 * off (tax-exclusive, like every amount here); see amouriq_bogo_member_vs_coupon().
 */
function amouriq_bogo_member_privilege( $lines, $deal_per_line, $ctx ) {
	$none = array( 'discount' => 0.0, 'per_line' => array(), 'deal_won' => array(), 'reason' => null, 'winner' => null );
	if ( empty( $ctx['is_member'] ) ) {
		$none['reason'] = 'not_member';
		return $none;
	}

	$lines = is_callable( $lines ) ? call_user_func( $lines ) : $lines;
	$rule  = amouriq_bogo_member_rule();

	// The minimum counts every item, Sale Price ones too, before any discount.
	$spend = 0.0;
	foreach ( $lines as $line ) {
		$price  = isset( $line['price_incl_tax'] ) ? $line['price_incl_tax'] : $line['unit_price'];
		$spend += $line['quantity'] * $price;
	}
	if ( round( $spend, 2 ) < $rule['min_spend'] ) {
		$none['reason'] = 'below_minimum';
		return $none;
	}

	$out     = $none;
	$regular = false;
	foreach ( $lines as $i => $line ) {
		if ( ! empty( $line['on_sale'] ) ) {
			continue;
		}
		$regular = true;
		$member  = $line['quantity'] * $line['unit_price'] * $rule['percent'] / 100;
		$deal_amount = isset( $deal_per_line[ $i ] ) ? $deal_per_line[ $i ] : 0.0;
		if ( $deal_amount >= $member ) {
			$out['deal_won'][] = $i;
			continue;
		}
		$out['per_line'][ $i ] = $member - $deal_amount;
		$out['discount']      += $member - $deal_amount;
	}

	if ( ! $regular ) {
		$out['reason'] = 'no_regular_lines';
	} elseif ( $out['discount'] <= 0 ) {
		$out['reason'] = 'deal_larger';
	} else {
		$out = amouriq_bogo_member_vs_coupon( $out, isset( $ctx['coupon_discount'] ) ? (float) $ctx['coupon_discount'] : 0.0 );
	}
	return $out;
}

/**
 * Whole-cart comparison with the Coupons in the cart (ADR-0006). WooCommerce has
 * already taken the coupon discount off, so Member Privilege only adds what it
 * has over it: the customer ends with the larger of the two, never both. A tie
 * goes to the coupon. `winner` is 'coupon' or 'member' when a coupon was in play.
 */
function amouriq_bogo_member_vs_coupon( $member, $coupon_discount ) {
	if ( $coupon_discount <= 0 ) {
		return $member;
	}
	if ( $coupon_discount >= round( $member['discount'], 2 ) ) {
		$member['discount'] = 0.0;
		$member['per_line'] = array();
		$member['reason']   = 'coupon_larger';
		$member['winner']   = 'coupon';
		return $member;
	}
	// The per-line split is no longer the amount charged; keep only the total.
	$member['discount'] = round( $member['discount'] - $coupon_discount, 2 );
	$member['per_line']  = array();
	$member['winner']    = 'member';
	return $member;
}

/** Split a deal's discount over the lines it came from (line index => amount). */
function amouriq_bogo_core_split( $deal, $lines, $discount ) {
	$type = isset( $deal['type'] ) ? $deal['type'] : '';
	if ( 'category_cheapest_free' === $type || 'cheapest_free' === $type ) {
		return amouriq_bogo_core_cheapest_split( $deal, $lines );
	}

	// The other types discount units of the product they name: spread the total
	// over those lines by line value.
	if ( 'product_pair' === $type ) {
		$id = isset( $deal['get_product_id'] ) ? $deal['get_product_id'] : 0;
	} else {
		$id = isset( $deal['product_id'] ) ? $deal['product_id'] : 0;
	}

	$weights = array();
	foreach ( $lines as $i => $line ) {
		$matches = (int) $line['product_id'] === (int) $id || (int) $line['variation_id'] === (int) $id;
		if ( $matches && ! amouriq_bogo_core_excluded( $line, $deal ) ) {
			$weights[ $i ] = $line['quantity'] * $line['unit_price'];
		}
	}
	$total = array_sum( $weights );
	if ( $total <= 0 ) {
		return array();
	}
	$split = array();
	foreach ( $weights as $i => $weight ) {
		$split[ $i ] = $discount * $weight / $total;
	}
	return $split;
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
		$uses = is_callable( $ctx['customer_uses'] ) ? call_user_func( $ctx['customer_uses'], $deal ) : $ctx['customer_uses'];
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
	return array_sum( amouriq_bogo_core_cheapest_split( $deal, $lines ) );
}

/** The cheapest-free discount per line (line index => amount). */
function amouriq_bogo_core_cheapest_split( $deal, $lines ) {
	$buy_qty = max( 1, (int) ( isset( $deal['buy_qty'] ) ? $deal['buy_qty'] : 1 ) );
	$percent = isset( $deal['get_discount_percent'] ) ? (float) $deal['get_discount_percent'] : 100.0;

	// One entry per unit: array( unit price, line index ).
	$units = array();
	foreach ( $lines as $i => $line ) {
		if ( ! empty( $deal['category'] ) && ! in_array( (string) $deal['category'], array_map( 'strval', $line['categories'] ), true ) ) {
			continue;
		}
		if ( amouriq_bogo_core_excluded( $line, $deal ) ) {
			continue;
		}
		for ( $n = 0; $n < $line['quantity']; $n++ ) {
			$units[] = array( (float) $line['unit_price'], $i );
		}
	}

	$groups = amouriq_bogo_core_cap( intdiv( count( $units ), $buy_qty ), $deal );
	if ( $groups <= 0 ) {
		return array();
	}

	// Sort most expensive first and cut into groups of buy_qty. The cheapest
	// item of each complete group is the last one in that group, so it sits at
	// index (group + 1) * buy_qty - 1. A leftover partial group gets nothing.
	usort(
		$units,
		static function ( $a, $b ) {
			return $b[0] <=> $a[0];
		}
	);
	$split = array();
	for ( $g = 0; $g < $groups; $g++ ) {
		$unit              = $units[ ( $g + 1 ) * $buy_qty - 1 ];
		$split[ $unit[1] ] = ( isset( $split[ $unit[1] ] ) ? $split[ $unit[1] ] : 0.0 ) + $unit[0] * ( $percent / 100 );
	}
	return $split;
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
