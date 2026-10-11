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

require_once __DIR__ . '/amouriq-bogo/pricing-core.php';


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

	$deals                = amouriq_bogo_deals();
	$context              = amouriq_bogo_price_context( $deals, $cart );
	$context['is_member'] = amouriq_bogo_is_member();
	// WooCommerce has already worked out the coupons by the time fees are calculated.
	$context['coupon_discount'] = array_sum( $cart->get_coupon_discount_totals() );
	$result               = amouriq_bogo_cart_discounts(
		$deals,
		static function () use ( $cart ) {
			return amouriq_bogo_cart_lines( $cart );
		},
		$context
	);

	foreach ( $deals as $index => $deal ) {
		$one = $result['deals'][ $index ];
		amouriq_bogo_notify_blocked( $deal, $one );
		if ( $one['discount'] <= 0 ) {
			continue;
		}

		$label = ! empty( $deal['label'] ) ? $deal['label'] : __( 'Deal discount', 'amouriq' );
		// Suffix with the array index so two deals can never collide on the
		// fee's auto-generated ID (WooCommerce derives it from this name).
		// `taxable` here only matters for a positive fee, never a discount —
		// see the note on amouriq_bogo_unit_price() above.
		$cart->add_fee( $label . ' #' . ( $index + 1 ), -$one['discount'], false );
	}

	// Member Privilege is its own fee line. The core already took off whatever a
	// larger Deal gives on the same item, so the two never add up (ADR-0006).
	$member = $result['member'];
	if ( $member['discount'] > 0 ) {
		// When a Deal gave more on some item, this fee is only the rest, so don't claim a flat 5%.
		if ( 'member' === $member['winner'] ) {
			$label = 'ส่วนลดสมาชิก 5% (มากกว่าคูปอง จึงเพิ่มเฉพาะส่วนต่าง)';
		} elseif ( $member['deal_won'] ) {
			$label = 'ส่วนลดสมาชิก (สินค้าที่ไม่อยู่ในดีล หรือดีลให้น้อยกว่า 5%)';
		} else {
			$label = 'ส่วนลดสมาชิก 5% (สินค้าราคาปกติ)';
		}
		$cart->add_fee( $label, -$member['discount'], false );
	} elseif ( 'below_minimum' === $member['reason'] ) {
		amouriq_bogo_notice( 'ซื้อครบ ฿299 รับส่วนลดสมาชิก 5% สำหรับสินค้าราคาปกติ', true );
	} elseif ( 'coupon_larger' === $member['reason'] ) {
		amouriq_bogo_notice( 'คูปองให้ส่วนลดมากกว่าหรือเท่ากับส่วนลดสมาชิก 5% จึงใช้ส่วนลดของคูปอง (ไม่ซ้อนกัน)', true );
	}
}

/**
 * Member Privilege is for a customer with an account: logged in, or creating one
 * in the checkout being filled in (or placed). A logged-out visitor on any
 * other page is not a member.
 */
function amouriq_bogo_is_member() {
	if ( is_user_logged_in() || amouriq_bogo_checkout_creates_account() ) {
		return true;
	}
	// With guest checkout off WooCommerce creates the account itself and the form
	// sends no "create account" field, so on the checkout (not the cart) a logged-out
	// shopper is about to register.
	return function_exists( 'is_checkout' ) && is_checkout() && WC()->checkout() && WC()->checkout()->is_registration_required();
}

/** Whether the checkout form being refreshed or placed ticks "create an account". */
function amouriq_bogo_checkout_creates_account( $set = null ) {
	static $creates = false;
	if ( null !== $set ) {
		$creates = (bool) $set;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
	return $creates || ! empty( $_POST['createaccount'] );
}

// A checkout refresh sends the form as post_data, not as $_POST fields.
add_action(
	'woocommerce_checkout_update_order_review',
	static function ( $post_data ) {
		if ( is_string( $post_data ) ) {
			parse_str( $post_data, $fields );
			amouriq_bogo_checkout_creates_account( ! empty( $fields['createaccount'] ) );
		}
	},
	5
);

/**
 * Show the shopper a cart notice once (the cart is recalculated many times per page).
 * With $cart_pages_only it is skipped elsewhere (adding to cart, mini-cart refreshes), so a
 * notice about a half-built cart is not queued and shown on a later page.
 */
function amouriq_bogo_notice( $message, $cart_pages_only = false ) {
	if ( $cart_pages_only && ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
		return;
	}
	if ( function_exists( 'wc_has_notice' ) && ! wc_has_notice( $message, 'notice' ) ) {
		wc_add_notice( $message, 'notice' );
	}
}

/** Tell the shopper why a Deal was held back by another coupon. */
function amouriq_bogo_notify_blocked( $deal, $result ) {
	if ( 'blocked_by_other_coupon' === $result['reason'] ) {
		amouriq_bogo_notice( sprintf( 'ดีล "%s" ไม่ใช้ร่วมกับคูปองอื่น จึงยังไม่ได้ส่วนลดดีลนี้', ! empty( $deal['label'] ) ? $deal['label'] : 'Deal discount' ) );
	}
}


/**
 * Discount this deal gives for the current cart, or 0 when it does not apply
 * right now (outside its date window, coupon missing, customer limit reached).
 * The maths lives in amouriq-bogo/pricing-core.php; this turns the WooCommerce
 * cart into plain data for it and says why a deal was held back.
 */
function amouriq_bogo_deal_discount( $deal, $cart ) {
	$result = amouriq_bogo_price(
		$deal,
		static function () use ( $cart ) {
			return amouriq_bogo_cart_lines( $cart );
		},
		amouriq_bogo_price_context( array( $deal ), $cart )
	);

	amouriq_bogo_notify_blocked( $deal, $result );

	return $result['discount'];
}

/**
 * Cart lines as plain data: ids, quantity, tax-exclusive unit price, whether the
 * item is on sale and its product_cat slugs and term IDs (a deal's `category`
 * may be either).
 */
function amouriq_bogo_cart_lines( $cart ) {
	$lines = array();
	foreach ( $cart->get_cart() as $cart_item ) {
		$item_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
		$product = wc_get_product( $item_id );
		$terms   = wp_get_post_terms( $cart_item['product_id'], 'product_cat' );
		$terms   = is_array( $terms ) ? $terms : array();

		$lines[] = array(
			'product_id'   => (int) $cart_item['product_id'],
			'variation_id' => (int) $cart_item['variation_id'],
			'quantity'     => (int) $cart_item['quantity'],
			'unit_price'     => amouriq_bogo_unit_price( $item_id ),
			// What the shopper sees per item (tax included when the shop shows it so); the minimum spend is judged on this.
			'price_incl_tax' =>$product ? (float) wc_get_price_to_display( $product ) : 0.0,
			'on_sale'      => $product && $product->is_on_sale(),
			'categories'   => array_merge( wp_list_pluck( $terms, 'slug' ), wp_list_pluck( $terms, 'term_id' ) ),
		);
	}
	return $lines;
}

/**
 * Everything besides the cart lines that the pricing core needs to decide: the
 * date, the coupons in the cart, how to look up how often this customer used the
 * deal (the core only calls it for a deal with a per-customer limit that is
 * otherwise live) and the price of each product the deal names.
 */
function amouriq_bogo_price_context( $deals, $cart ) {
	$prices = array();
	foreach ( $deals as $deal ) {
		foreach ( array( 'product_id', 'buy_product_id', 'get_product_id' ) as $key ) {
			if ( ! empty( $deal[ $key ] ) ) {
				$prices[ (int) $deal[ $key ] ] = amouriq_bogo_unit_price( $deal[ $key ] );
			}
		}
	}

	return array(
		'today'           => current_time( 'Y-m-d' ),
		'applied_coupons' => $cart->get_applied_coupons(),
		'customer_uses'   => static function ( $deal ) {
			return amouriq_bogo_customer_uses( $deal['id'] );
		},
		'prices'          => $prices,
	);
}

/**
 * Statuses that count as "already used" for the per-customer limit. Cancelled,
 * failed and refunded orders do not count.
 */
function amouriq_bogo_counted_statuses() {
	return array( 'pending', 'on-hold', 'processing', 'completed' );
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

/**
 * WooCommerce refreshes checkout totals when an address field changes but not
 * when the billing email does. A deal limited per customer depends on that
 * email, so ask for a refresh when it changes.
 */
add_action( 'wp_footer', 'amouriq_bogo_refresh_on_email_change' );

function amouriq_bogo_refresh_on_email_change() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	?>
	<script>
	jQuery(function ($) {
		$(document.body).on('change', 'input#billing_email', function () {
			$(document.body).trigger('update_checkout');
		});
	});
	</script>
	<?php
}

/**
 * WooCommerce's checkout refresh ignores the billing email in the form (it
 * only reads address fields), so hand it to the customer here. The per-customer
 * limit is counted by that email until the buyer is logged in.
 */
add_action( 'woocommerce_checkout_update_order_review', 'amouriq_bogo_read_checkout_email' );

function amouriq_bogo_read_checkout_email( $post_data ) {
	if ( ! WC()->customer || ! is_string( $post_data ) ) {
		return;
	}
	parse_str( $post_data, $fields );
	$email = isset( $fields['billing_email'] ) ? sanitize_email( $fields['billing_email'] ) : '';
	if ( $email && is_email( $email ) ) {
		WC()->customer->set_billing_email( $email );
	}
}
