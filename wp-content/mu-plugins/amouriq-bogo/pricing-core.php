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
 * The context:  today ('Y-m-d') and optionally now ('Y-m-d H:i'), applied_coupons (list of codes),
 *               customer_uses (int, or null when the customer is not yet known; may be
 *               a callable given the deal and returning either, so the lookup only runs when needed),
 *               prices (product or variation id => tax-exclusive unit price)
 *
 * Returns array( 'discount' => float, 'per_line' => array, 'reason' => string|null,
 * 'choices_left' => int ), `choices_left` being how many more items the customer
 * may pick for a buy-A-choose-B Deal (0 for every other Deal).
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
		return array( 'discount' => 0.0, 'per_line' => array(), 'reason' => $reason, 'choices_left' => 0, 'allowed' => 0 );
	}
	// A callable, so the caller only builds the cart lines for a deal that got this far.
	$lines = is_callable( $lines ) ? call_user_func( $lines ) : $lines;

	// Gifts are free lines: they never count toward a minimum spend or another Deal's quantities.
	$gifts = array_filter( $lines, 'amouriq_bogo_core_is_gift' );
	$lines = array_filter(
		$lines,
		static function ( $line ) {
			return ! amouriq_bogo_core_is_gift( $line );
		}
	);

	if ( isset( $deal['type'] ) && 'spend_gift' === $deal['type'] ) {
		$gift = amouriq_bogo_core_gift( $deal, $lines, $gifts );
		return array( 'discount' => 0.0, 'per_line' => array(), 'reason' => null, 'choices_left' => $gift['left'], 'allowed' => $gift['allowed'] );
	}

	// A buy-A-choose-B Deal also says how many B the customer may still pick.
	if ( amouriq_bogo_core_is_pair_choice( $deal ) ) {
		$pair = amouriq_bogo_core_pair_choice( $deal, $lines );
		return array( 'discount' => $pair['discount'], 'per_line' => $pair['per_line'], 'reason' => null, 'choices_left' => $pair['left'], 'allowed' => 0 );
	}

	$discount = (float) amouriq_bogo_core_discount( $deal, $lines, $ctx );
	return array(
		'discount'     => $discount,
		'per_line'     => $discount > 0 ? amouriq_bogo_core_split( $deal, $lines, $discount ) : array(),
		'reason'       => null,
		'choices_left' => 0,
		'allowed'      => 0,
	);
}

function amouriq_bogo_core_is_gift( $line ) {
	return ! empty( $line['is_gift'] );
}

/**
 * Spend-threshold gift: once the paid items reach `min_spend` (as the shopper sees
 * the prices, Sale Price items included) the customer may have `gift_qty` gifts
 * from `gift_ids`. Returns the number allowed and how many choices are left after
 * the gifts of this Deal already in the cart.
 */
function amouriq_bogo_core_gift( $deal, $paid_lines, $gift_lines ) {
	$spend = 0.0;
	foreach ( $paid_lines as $line ) {
		$shown  = amouriq_bogo_core_shown_price( $line );
		$spend += $line['quantity'] * $shown;
	}
	$allowed = round( $spend, 2 ) >= (float) ( isset( $deal['min_spend'] ) ? $deal['min_spend'] : 0 ) ? max( 0, (int) ( isset( $deal['gift_qty'] ) ? $deal['gift_qty'] : 1 ) ) : 0;

	$taken = 0;
	foreach ( $gift_lines as $line ) {
		if ( isset( $line['gift_deal'], $deal['id'] ) && $line['gift_deal'] === $deal['id'] ) {
			$taken += $line['quantity'];
		}
	}
	return array( 'allowed' => $allowed, 'left' => max( 0, $allowed - $taken ) );
}

/**
 * Gift lines the cart must drop: a gift whose Deal allows fewer gifts than are in
 * the cart (none when the Deal is gone, off, or the minimum is no longer met).
 * `$allowed` maps Deal id => gifts allowed. Returns line indexes.
 */
function amouriq_bogo_core_gifts_to_remove( $lines, $allowed ) {
	$kept   = array();
	$remove = array();
	foreach ( $lines as $i => $line ) {
		if ( ! amouriq_bogo_core_is_gift( $line ) ) {
			continue;
		}
		$deal_id = isset( $line['gift_deal'] ) ? $line['gift_deal'] : '';
		$room    = ( isset( $allowed[ $deal_id ] ) ? $allowed[ $deal_id ] : 0 ) - ( isset( $kept[ $deal_id ] ) ? $kept[ $deal_id ] : 0 );
		if ( $line['quantity'] > $room ) {
			$remove[] = $i;
			continue;
		}
		$kept[ $deal_id ] = ( isset( $kept[ $deal_id ] ) ? $kept[ $deal_id ] : 0 ) + $line['quantity'];
	}
	return $remove;
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
		if ( amouriq_bogo_core_is_gift( $line ) ) {
			continue;
		}
		$spend += $line['quantity'] * amouriq_bogo_core_shown_price( $line );
	}
	if ( round( $spend, 2 ) < $rule['min_spend'] ) {
		$none['reason'] = 'below_minimum';
		return $none;
	}

	$out     = $none;
	$regular = false;
	foreach ( $lines as $i => $line ) {
		if ( ! empty( $line['on_sale'] ) || amouriq_bogo_core_is_gift( $line ) ) {
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

	if ( 'spend_threshold' === $type || ( 'quantity_tier' === $type && ! empty( $deal['product_ids'] ) ) ) {
		$weights = array();
		foreach ( $lines as $i => $line ) {
			if ( amouriq_bogo_core_threshold_eligible( $line, $deal ) ) {
				$weights[ $i ] = $line['quantity'] * $line['unit_price'];
			}
		}
		return amouriq_bogo_core_spread( $discount, $weights );
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
	return amouriq_bogo_core_spread( $discount, $weights );
}

/** Spread an amount over lines in proportion to their weights (line index => weight). */
function amouriq_bogo_core_spread( $discount, $weights ) {
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

	if ( ! amouriq_bogo_core_in_window( $deal, $ctx ) ) {
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

/**
 * Whether the deal's start and end contain the moment in the context. Each is a
 * date ('Y-m-d': start of that day / end of that day) or a date and time
 * ('Y-m-d H:i'). The moment is ctx `now` ('Y-m-d H:i'); with only `today` the
 * middle of that day is used, which a whole-day window contains.
 */
function amouriq_bogo_core_in_window( $deal, $ctx ) {
	$now = isset( $ctx['now'] ) ? $ctx['now'] : $ctx['today'] . ' 12:00';
	if ( ! empty( $deal['valid_from'] ) && $now < amouriq_bogo_core_full_time( $deal['valid_from'], false ) ) {
		return false;
	}
	if ( ! empty( $deal['valid_to'] ) && $now > amouriq_bogo_core_full_time( $deal['valid_to'], true ) ) {
		return false;
	}
	return true;
}

/** A stored start/end as 'Y-m-d H:i'; a bare date means the start (00:00) or the end (23:59) of that day. */
function amouriq_bogo_core_full_time( $value, $is_end ) {
	return 10 === strlen( $value ) ? $value . ( $is_end ? ' 23:59' : ' 00:00' ) : $value;
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
		case 'spend_threshold':
			return amouriq_bogo_core_spend_threshold( $deal, $lines );
	}
	return 0.0;
}

/** True when this line is one the spend-threshold Deal discounts (not skipped, ticked, not excluded). */
function amouriq_bogo_core_threshold_eligible( $line, $deal ) {
	if ( amouriq_bogo_core_excluded( $line, $deal ) ) {
		return false;
	}
	if ( ! empty( $deal['product_ids'] ) && ! amouriq_bogo_core_line_is_any_of( $line, $deal['product_ids'] ) ) {
		return false;
	}
	return empty( $deal['excluded_ids'] ) || ! amouriq_bogo_core_line_is_any_of( $line, $deal['excluded_ids'] );
}

/** True when the line is one of these products or variations (a product ID also covers all its variations). */
function amouriq_bogo_core_line_is_any_of( $line, $ids ) {
	foreach ( (array) $ids as $id ) {
		if ( (int) $line['product_id'] === (int) $id || ( $line['variation_id'] && (int) $line['variation_id'] === (int) $id ) ) {
			return true;
		}
	}
	return false;
}

/** The price of one unit as the shopper sees it (tax included when the shop shows it so). */
function amouriq_bogo_core_shown_price( $line ) {
	return isset( $line['price_incl_tax'] ) ? (float) $line['price_incl_tax'] : (float) $line['unit_price'];
}

/**
 * "Spend X, get Y% or Y baht off". The minimum counts every item as the shopper
 * sees it (Sale Price items too, before any discount); the discount is taken
 * only from the eligible lines. A baht value is what the shopper sees, so it is
 * converted to the tax-exclusive amount a cart fee needs, and never exceeds the
 * eligible items.
 */
function amouriq_bogo_core_spend_threshold( $deal, $lines ) {
	$spend = 0.0;
	$ex    = 0.0;
	$incl  = 0.0;
	foreach ( $lines as $line ) {
		$shown  = amouriq_bogo_core_shown_price( $line );
		$spend += $line['quantity'] * $shown;
		if ( amouriq_bogo_core_threshold_eligible( $line, $deal ) ) {
			$ex   += $line['quantity'] * $line['unit_price'];
			$incl += $line['quantity'] * $shown;
		}
	}

	if ( $ex <= 0 || round( $spend, 2 ) < (float) ( isset( $deal['min_spend'] ) ? $deal['min_spend'] : 0 ) ) {
		return 0.0;
	}

	$value = isset( $deal['value'] ) ? (float) $deal['value'] : 0.0;
	if ( isset( $deal['mode'] ) && 'baht' === $deal['mode'] ) {
		return min( $ex, $value * $ex / $incl );
	}
	return $ex * min( 100.0, $value ) / 100;
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

/** True for a product-pair Deal that lists several choosable B items (`get_product_ids`). */
function amouriq_bogo_core_is_pair_choice( $deal ) {
	return isset( $deal['type'] ) && 'product_pair' === $deal['type'] && ! empty( $deal['get_product_ids'] ) && ! empty( $deal['buy_product_id'] );
}

/**
 * Buy `buy_qty` of A, then `get_qty` of the ticked B items (`get_product_ids`) get
 * `get_value` percent (or baht, `get_mode`) off. The B items are ordinary cart
 * lines the customer picked; the discount goes to the first units of B in cart
 * order, up to the allowance. Returns array( 'discount', 'per_line', 'left' ),
 * `left` being the choices the customer has not used yet.
 */
function amouriq_bogo_core_pair_choice( $deal, $lines ) {
	$buy_qty = max( 1, (int) ( isset( $deal['buy_qty'] ) ? $deal['buy_qty'] : 1 ) );
	$get_qty = max( 1, (int) ( isset( $deal['get_qty'] ) ? $deal['get_qty'] : 1 ) );
	$baht    = isset( $deal['get_mode'] ) && 'baht' === $deal['get_mode'];
	$value   = isset( $deal['get_value'] ) ? (float) $deal['get_value'] : 100.0;


	$buy_in_cart = 0;
	$b_lines     = array();
	foreach ( $lines as $i => $line ) {
		if ( amouriq_bogo_core_excluded( $line, $deal ) ) {
			continue;
		}
		if ( amouriq_bogo_core_line_is_any_of( $line, $deal['get_product_ids'] ) ) {
			$b_lines[ $i ] = $line;
		} elseif ( amouriq_bogo_core_line_is_any_of( $line, $deal['buy_product_id'] ) ) {
			$buy_in_cart += $line['quantity'];
		}
	}

	$allowed = amouriq_bogo_core_cap( intdiv( $buy_in_cart, $buy_qty ), $deal ) * $get_qty;
	$left    = $allowed;
	$out     = array( 'discount' => 0.0, 'per_line' => array(), 'left' => 0 );
	foreach ( $b_lines as $i => $line ) {
		if ( $left <= 0 ) {
			break;
		}
		$units = min( $left, $line['quantity'] );
		$left -= $units;

		$unit = (float) $line['unit_price'];
		if ( $baht ) {
			$shown = amouriq_bogo_core_shown_price( $line );
			$each  = $shown > 0 ? min( $unit, $value * $unit / $shown ) : 0.0;
		} else {
			$each = $unit * min( 100.0, $value ) / 100;
		}
		$out['per_line'][ $i ] = $units * $each;
		$out['discount']      += $units * $each;
	}
	$out['left'] = max( 0, $left );
	return $out;
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
 * Mix and match: the pieces of all ticked products and variations (`product_ids`,
 * minus `excluded_ids`, minus Sale Price items when the deal skips them) are
 * counted together. The highest tier reached gives its percent off every one of
 * those pieces at its own price; tiers never add up.
 */
function amouriq_bogo_core_mix_and_match( $deal, $lines ) {
	if ( empty( $deal['tiers'] ) || ! is_array( $deal['tiers'] ) ) {
		return 0.0;
	}

	$qty   = 0;
	$value = 0.0;
	foreach ( $lines as $line ) {
		if ( amouriq_bogo_core_threshold_eligible( $line, $deal ) ) {
			$qty   += $line['quantity'];
			$value += $line['quantity'] * $line['unit_price'];
		}
	}

	$percent = 0.0;
	foreach ( $deal['tiers'] as $tier ) {
		if ( isset( $tier['min'], $tier['percent'] ) && $qty >= (int) $tier['min'] ) {
			$percent = max( $percent, (float) $tier['percent'] );
		}
	}
	return $value * $percent / 100;
}

/**
 * Tiered quantity discount on one product: the highest tier reached gives its
 * percent off every unit of that product in the cart. Tiers look like
 * array( array( 'min' => 6, 'percent' => 10 ), array( 'min' => 12, 'percent' => 15 ) ).
 */
function amouriq_bogo_core_quantity_tier( $deal, $lines, $ctx ) {
	if ( ! empty( $deal['product_ids'] ) ) {
		return amouriq_bogo_core_mix_and_match( $deal, $lines );
	}
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
