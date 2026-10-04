<?php
/**
 * Plugin Name: AMOURIQ: BOGO / bundle deals
 * Description: Buy-X-get-Y style deals that native WooCommerce coupons can't
 *              express (a coupon can discount the whole cart or restrict
 *              itself to a category, but it can't say "free item B when you
 *              buy item A"). Deals are managed in WooCommerce > ดีล BOGO
 *              (amouriq-bogo-admin.php), stored in the `amouriq_bogo_deals`
 *              option. Until the first save, deals come from
 *              amouriq-bogo/config.php. Applied as one negative cart fee per
 *              active deal on `woocommerce_cart_calculate_fees`, so it shows
 *              as its own "Deal discount" line at checkout without rewriting
 *              any product's displayed price. Each order records the discount
 *              per deal (meta `amouriq_bogo_discounts`), which drives the
 *              per-customer limit and the usage report.
 */

defined( 'ABSPATH' ) || exit;

function amouriq_bogo_deals() {
	static $deals = null;
	if ( null === $deals ) {
		$stored = get_option( 'amouriq_bogo_deals', null );
		if ( is_array( $stored ) ) {
			$deals = $stored;
		} else {
			$file  = __DIR__ . '/amouriq-bogo/config.php';
			$deals = file_exists( $file ) ? (array) include $file : array();
		}
		// Deals from config.php have no stored ID yet; give each one a stable one
		// so usage can be tracked against it.
		foreach ( $deals as $index => $deal ) {
			if ( empty( $deal['id'] ) ) {
				$deals[ $index ]['id'] = 'cfg' . $index;
			}
		}
	}
	return $deals;
}

add_action( 'woocommerce_cart_calculate_fees', 'amouriq_bogo_apply_deals' );

function amouriq_bogo_apply_deals( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	foreach ( amouriq_bogo_deals() as $index => $deal ) {
		$discount = amouriq_bogo_deal_discount( $deal, $cart );
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

/**
 * Discount this deal gives for the current cart, or 0 when it does not apply
 * right now (outside its date window, coupon missing, customer limit reached).
 */
function amouriq_bogo_deal_discount( $deal, $cart ) {
	if ( ! amouriq_bogo_deal_in_window( $deal ) ) {
		return 0.0;
	}
	if ( ! amouriq_bogo_deal_active( $deal, $cart ) ) {
		return 0.0;
	}
	if ( ! amouriq_bogo_deal_within_customer_limit( $deal ) ) {
		return 0.0;
	}
	return amouriq_bogo_calculate_discount( $deal, $cart );
}

function amouriq_bogo_deal_in_window( $deal ) {
	$today = current_time( 'Y-m-d' );
	if ( ! empty( $deal['valid_from'] ) && $today < $deal['valid_from'] ) {
		return false;
	}
	if ( ! empty( $deal['valid_to'] ) && $today > $deal['valid_to'] ) {
		return false;
	}
	return true;
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

/**
 * Statuses that count as "already used" for the per-customer limit. Cancelled,
 * failed and refunded orders do not count.
 */
function amouriq_bogo_counted_statuses() {
	return array( 'pending', 'on-hold', 'processing', 'completed' );
}

function amouriq_bogo_deal_within_customer_limit( $deal ) {
	$max = isset( $deal['max_uses_per_customer'] ) ? (int) $deal['max_uses_per_customer'] : 0;
	if ( $max <= 0 ) {
		return true;
	}

	// Limited deals need an account so the usage count is reliable. Guests
	// cannot be counted across orders (a changed email would reset the limit).
	if ( ! get_current_user_id() ) {
		return false;
	}

	$used = amouriq_bogo_customer_uses( $deal['id'] );
	if ( null === $used ) {
		return false;
	}
	return $used < $max;
}

/**
 * How many counted orders this customer already used the deal on. Logged-in
 * customers are matched by user ID, guests by billing email. Returns null when
 * the customer cannot be identified yet.
 */
function amouriq_bogo_customer_uses( $deal_id ) {
	if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
		return null;
	}

	$user_id = get_current_user_id();
	if ( $user_id ) {
		$customer = $user_id;
	} else {
		$customer = WC()->customer->get_billing_email();
		if ( ! $customer ) {
			return null;
		}
	}

	return amouriq_bogo_count_orders_using( $deal_id, $customer );
}

function amouriq_bogo_count_orders_using( $deal_id, $customer ) {
	$orders = wc_get_orders(
		array(
			'limit'      => -1,
			'status'     => amouriq_bogo_counted_statuses(),
			'customer'   => $customer,
			'meta_query' => array(
				array( 'key' => 'amouriq_bogo_discounts', 'compare' => 'EXISTS' ),
			),
		)
	);

	$count = 0;
	foreach ( $orders as $order ) {
		$used = $order->get_meta( 'amouriq_bogo_discounts' );
		if ( is_array( $used ) && isset( $used[ $deal_id ] ) ) {
			$count++;
		}
	}
	return $count;
}

/**
 * Store each applied deal's discount on the order, so usage and the report
 * can be counted later.
 */
add_action( 'woocommerce_checkout_create_order', 'amouriq_bogo_record_usage', 10, 1 );

function amouriq_bogo_record_usage( $order ) {
	$cart = WC()->cart;
	if ( ! $cart ) {
		return;
	}

	$used = array();
	foreach ( amouriq_bogo_deals() as $deal ) {
		$discount = amouriq_bogo_deal_discount( $deal, $cart );
		if ( $discount > 0 ) {
			$used[ $deal['id'] ] = round( $discount, 2 );
		}
	}
	if ( $used ) {
		$order->update_meta_data( 'amouriq_bogo_discounts', $used );
	}
}

function amouriq_bogo_calculate_discount( $deal, $cart ) {
	switch ( isset( $deal['type'] ) ? $deal['type'] : '' ) {
		case 'same_product':
			return amouriq_bogo_discount_same_product( $deal, $cart );
		case 'product_pair':
			return amouriq_bogo_discount_product_pair( $deal, $cart );
		case 'category_cheapest_free':
		case 'cheapest_free':
			return amouriq_bogo_discount_category_cheapest( $deal, $cart );
		case 'quantity_tier':
			return amouriq_bogo_discount_quantity_tier( $deal, $cart );
	}
	return 0.0;
}

/**
 * True when this cart line must be left out of the deal because the deal is set
 * to skip products already on sale.
 */
function amouriq_bogo_item_excluded( $cart_item, $deal ) {
	if ( empty( $deal['exclude_sale'] ) ) {
		return false;
	}
	$product = wc_get_product( $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'] );
	return $product && $product->is_on_sale();
}

/**
 * Cap on how many times a deal can apply to one cart (0 = no cap).
 */
function amouriq_bogo_cap_groups( $count, $deal ) {
	$max = isset( $deal['max_per_order'] ) ? (int) $deal['max_per_order'] : 0;
	return $max > 0 ? min( $count, $max ) : $count;
}

/**
 * Total quantity of a given product (or a specific variation) in the cart.
 */
function amouriq_bogo_qty_in_cart( $cart, $product_id, $deal = array() ) {
	$qty = 0;
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( (int) $cart_item['product_id'] === (int) $product_id
			|| (int) $cart_item['variation_id'] === (int) $product_id ) {
			if ( amouriq_bogo_item_excluded( $cart_item, $deal ) ) {
				continue;
			}
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
	$qty_in_cart = amouriq_bogo_qty_in_cart( $cart, $deal['product_id'], $deal );
	$groups      = amouriq_bogo_cap_groups( intdiv( $qty_in_cart, $group_size ), $deal );
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

	$buy_in_cart = amouriq_bogo_qty_in_cart( $cart, $deal['buy_product_id'], $deal );
	$get_in_cart = amouriq_bogo_qty_in_cart( $cart, $deal['get_product_id'], $deal );

	$triggers         = amouriq_bogo_cap_groups( intdiv( $buy_in_cart, $buy_qty ), $deal );
	$discounted_units = min( $triggers * $get_qty, $get_in_cart );
	if ( $discounted_units <= 0 ) {
		return 0.0;
	}

	return $discounted_units * amouriq_bogo_unit_price( $deal['get_product_id'] ) * ( $percent / 100 );
}

/**
 * Cheapest item of each group of buy_qty free. Limited to one category when
 * the deal sets `category`; otherwise any item in the cart counts.
 */
function amouriq_bogo_discount_category_cheapest( $deal, $cart ) {
	$buy_qty = max( 1, (int) ( $deal['buy_qty'] ?? 1 ) );
	$percent = isset( $deal['get_discount_percent'] ) ? (float) $deal['get_discount_percent'] : 100.0;

	$unit_prices = array();
	foreach ( $cart->get_cart() as $cart_item ) {
		$parent_id = $cart_item['product_id'];
		if ( ! empty( $deal['category'] ) && ! has_term( $deal['category'], 'product_cat', $parent_id ) ) {
			continue;
		}
		if ( amouriq_bogo_item_excluded( $cart_item, $deal ) ) {
			continue;
		}
		$unit_price = amouriq_bogo_unit_price(
			$cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id']
		);
		for ( $i = 0; $i < $cart_item['quantity']; $i++ ) {
			$unit_prices[] = $unit_price;
		}
	}

	$groups = amouriq_bogo_cap_groups( intdiv( count( $unit_prices ), $buy_qty ), $deal );
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
function amouriq_bogo_discount_quantity_tier( $deal, $cart ) {
	if ( empty( $deal['product_id'] ) || empty( $deal['tiers'] ) || ! is_array( $deal['tiers'] ) ) {
		return 0.0;
	}

	$qty     = amouriq_bogo_qty_in_cart( $cart, $deal['product_id'], $deal );
	$percent = 0.0;
	foreach ( $deal['tiers'] as $tier ) {
		if ( isset( $tier['min'], $tier['percent'] ) && $qty >= (int) $tier['min'] ) {
			$percent = max( $percent, (float) $tier['percent'] );
		}
	}
	if ( $percent <= 0 ) {
		return 0.0;
	}

	return $qty * amouriq_bogo_unit_price( $deal['product_id'] ) * ( $percent / 100 );
}
