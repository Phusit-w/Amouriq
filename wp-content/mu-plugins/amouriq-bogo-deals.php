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


/** The Deals the owner saved (or config.php before the first save), never changed by other code: what the admin screen lists and saves. */
function amouriq_bogo_deals_stored() {
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

/**
 * The Deals in force for this request: the stored ones plus any that other code adds for the
 * cart (the points programme adds a gift Deal while a gift-reward coupon is in the cart).
 */
function amouriq_bogo_deals() {
	return apply_filters( 'amouriq_bogo_deals_runtime', amouriq_bogo_deals_stored() );
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
		amouriq_bogo_notify_hint( $deal, $one );
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
	$rule   = amouriq_bogo_member_rule();
	$pct    = amouriq_bogo_member_number( $rule['percent'] );
	if ( $member['discount'] > 0 ) {
		// When a Deal gave more on some item, this fee is only the rest, so don't claim a flat percent.
		if ( 'member' === $member['winner'] ) {
			$label = 'สิทธิพิเศษสมาชิก ' . $pct . '% (มากกว่าคูปอง จึงเพิ่มเฉพาะส่วนต่าง)';
		} elseif ( $member['deal_won'] ) {
			$label = 'สิทธิพิเศษสมาชิก (สินค้าที่ไม่อยู่ในดีล หรือดีลให้น้อยกว่า ' . $pct . '%)';
		} else {
			$label = 'สิทธิพิเศษสมาชิก ' . $pct . '% (สินค้าราคาปกติ)';
		}
		$cart->add_fee( $label, -$member['discount'], false );
	} elseif ( 'below_minimum' === $member['reason'] ) {
		amouriq_bogo_notice( 'ซื้อครบ ฿' . amouriq_bogo_member_number( $rule['min_spend'] ) . ' รับสิทธิพิเศษสมาชิก ' . $pct . '% สำหรับสินค้าราคาปกติ', true );
	} elseif ( 'coupon_larger' === $member['reason'] ) {
		amouriq_bogo_notice( 'คูปองให้ส่วนลดมากกว่าหรือเท่ากับสิทธิพิเศษสมาชิก ' . $pct . '% จึงใช้ส่วนลดของคูปอง (ไม่ซ้อนกัน)', true );
	} elseif ( 'deal_larger' === $member['reason'] ) {
		amouriq_bogo_notice( 'สินค้าในตะกร้าได้ส่วนลดจากดีลซึ่งมากกว่าสิทธิพิเศษสมาชิก ' . $pct . '% จึงใช้ส่วนลดของดีล (ไม่ซ้อนกัน)', true );
	}
}

/**
 * Tell the shopper why a live Deal has not applied yet and what would trigger it
 * (how much more to spend, or how many more pieces reach the next tier). Cart and
 * checkout pages only.
 */
function amouriq_bogo_notify_hint( $deal, $result ) {
	if ( empty( $result['hint'] ) ) {
		return;
	}
	$label = ! empty( $deal['label'] ) ? $deal['label'] : 'ดีลพิเศษ';
	$hint  = $result['hint'];

	if ( 'spend' === $hint['kind'] ) {
		$type    = isset( $deal['type'] ) ? $deal['type'] : '';
		$what    = 'spend_gift' === $type ? 'เลือกของแถมฟรี' : ( 'free_shipping' === $type ? 'ส่งฟรี' : 'รับส่วนลด' );
		$message = sprintf( 'ซื้อเพิ่มอีก ฿%s เพื่อ%s ("%s")', number_format( $hint['remaining'], 2 ), $what, $label );
	} else {
		$message = sprintf( 'เพิ่มสินค้าในดีล "%s" อีก %d ชิ้น เพื่อรับส่วนลด %s%%', $label, $hint['remaining'], rtrim( rtrim( number_format( $hint['percent'], 2 ), '0' ), '.' ) );
	}
	amouriq_bogo_notice( $message, true );
}

/**
 * Member Privilege is for a customer with an account: logged in, or creating one
 * in the checkout being filled in (or placed). A logged-out visitor on any
 * other page is not a member.
 */
function amouriq_bogo_is_member() {
	if ( ! amouriq_member_privilege_enabled() ) {
		return false;
	}
	if ( is_user_logged_in() || amouriq_bogo_checkout_creates_account() ) {
		return true;
	}
	// With guest checkout off WooCommerce creates the account itself and the form
	// sends no "create account" field, so on the checkout (not the cart) a logged-out
	// shopper is about to register.
	return function_exists( 'is_checkout' ) && is_checkout() && WC()->checkout() && WC()->checkout()->is_registration_required();
}

/**
 * The owner's switch for Member Privilege (WooCommerce > ดีล BOGO). It is off until
 * switched on, so uploading these files never changes a customer's price by itself.
 */
function amouriq_member_privilege_enabled() {
	return 'yes' === get_option( 'amouriq_member_privilege', 'no' );
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
	if ( $cart_pages_only ) {
		// Not during an AJAX refresh (the checkout refreshes its totals by AJAX and would show it again next to the one the page already printed).
		$on_cart_page = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) && ! wp_doing_ajax() && ! defined( 'WC_DOING_AJAX' );
		if ( ! $on_cart_page ) {
			return;
		}
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
	foreach ( $cart->get_cart() as $cart_key => $cart_item ) {
		$item_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
		$product = wc_get_product( $item_id );
		$terms   = wp_get_post_terms( $cart_item['product_id'], 'product_cat' );
		$terms   = is_array( $terms ) ? $terms : array();
		// A gift (spend-threshold gift Deal) is a free line: it keeps its Deal id and costs nothing.
		$is_gift = ! empty( $cart_item['amq_gift'] );

		$lines[] = array(
			'cart_key'       => $cart_key,
			'is_gift'        => $is_gift,
			'gift_deal'      => $is_gift ? $cart_item['amq_gift'] : '',
			'product_id'     => (int) $cart_item['product_id'],
			'variation_id'   => (int) $cart_item['variation_id'],
			'quantity'       => (int) $cart_item['quantity'],
			'unit_price'     => $is_gift ? 0.0 : amouriq_bogo_unit_price( $item_id ),
			// What the shopper sees per item (tax included when the shop shows it so); the minimum spend is judged on this.
			'price_incl_tax' => ( $product && ! $is_gift ) ? (float) wc_get_price_to_display( $product ) : 0.0,
			'on_sale'        => $product && $product->is_on_sale(),
			'categories'     => array_merge( wp_list_pluck( $terms, 'slug' ), wp_list_pluck( $terms, 'term_id' ) ),
			'tags'           => amouriq_bogo_line_tags( $cart_item['product_id'] ),
			'attributes'     => amouriq_bogo_line_attributes( $cart_item, $product ),
			'sku'            => $product ? (string) $product->get_sku() : '',
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
		'now'             => current_time( 'Y-m-d H:i' ),
		'applied_coupons' => $cart->get_applied_coupons(),
		'customer_uses'   => static function ( $deal ) {
			return amouriq_bogo_customer_uses( $deal['id'] );
		},
		'is_new_customer' => static function () {
			// Without the first-order plugin there is no way to tell, so a "new customers only" Deal stays off.
			return function_exists( 'amouriq_customer_has_previous_order' ) && ! amouriq_customer_has_previous_order();
		},
		'prices'          => $prices,
	) + amouriq_bogo_condition_context( $cart );
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
		} elseif ( amouriq_bogo_free_shipping_live( $deal, $cart ) ) {
			$used[ $deal['id'] ] = 0.0; // a free-shipping Deal discounts nothing but still counts as used
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

/** The product's tags (slugs and term IDs), for a Deal's Product scope. */
function amouriq_bogo_line_tags( $product_id ) {
	$terms = wp_get_post_terms( $product_id, 'product_tag' );
	$terms = is_array( $terms ) ? $terms : array();
	return array_merge( wp_list_pluck( $terms, 'slug' ), wp_list_pluck( $terms, 'term_id' ) );
}

/**
 * The line's attributes as taxonomy => slugs (for example pa_quantity => 250-ml):
 * the chosen variation attributes plus the product's own attribute terms.
 */
function amouriq_bogo_line_attributes( $cart_item, $product ) {
	$out = array();
	if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
		foreach ( $cart_item['variation'] as $key => $value ) {
			$taxonomy = preg_replace( '/^attribute_/', '', $key );
			if ( '' !== (string) $value ) {
				$out[ $taxonomy ][] = (string) $value;
			}
		}
	}
	if ( $product && method_exists( $product, 'get_attributes' ) ) {
		foreach ( (array) $product->get_attributes() as $taxonomy => $value ) {
			if ( is_string( $value ) && '' !== $value && ! isset( $out[ $taxonomy ] ) ) {
				$out[ $taxonomy ][] = $value;
			}
		}
	}
	return $out;
}

/**
 * What the Conditions (amouriq-bogo/conditions.php) can ask about the customer and
 * the cart. Each value is a callable run only when a Deal's condition needs it, and
 * remembers its answer for the rest of the request. `null` means "not known yet".
 */
function amouriq_bogo_condition_context( $cart ) {
	$lines = null;
	$paid  = static function () use ( &$lines, $cart ) {
		if ( null === $lines ) {
			$lines = array_filter(
				amouriq_bogo_cart_lines( $cart ),
				static function ( $line ) {
					return ! amouriq_bogo_core_is_gift( $line );
				}
			);
		}
		return $lines;
	};

	$history = null;
	$summary = static function () use ( &$history ) {
		if ( null === $history ) {
			$history = amouriq_bogo_customer_history();
		}
		return $history;
	};

	return array(
		'logged_in'             => static function () {
			return is_user_logged_in();
		},
		'user_roles'            => static function () {
			$user = wp_get_current_user();
			return $user && $user->exists() ? (array) $user->roles : array();
		},
		'email'                 => static function () {
			$user = wp_get_current_user();
			if ( $user && $user->exists() ) {
				return $user->user_email;
			}
			return ( function_exists( 'WC' ) && WC()->customer ) ? WC()->customer->get_billing_email() : '';
		},
		'orders_count'          => static function () use ( $summary ) {
			$h = $summary();
			return $h ? $h['orders_count'] : null;
		},
		'total_spent'           => static function () use ( $summary ) {
			$h = $summary();
			return $h ? $h['total_spent'] : null;
		},
		'days_since_last_order' => static function () use ( $summary ) {
			$h = $summary();
			return $h ? $h['days_since_last_order'] : null;
		},
		'cart_items'            => static function () use ( $paid ) {
			return count( $paid() );
		},
		'cart_quantity'         => static function () use ( $paid ) {
			return array_sum( array_column( $paid(), 'quantity' ) );
		},
		// As the shopper sees the prices, before any discount, shipping left out: the same measure as every minimum spend.
		'cart_subtotal'         => static function () use ( $paid ) {
			$sum = 0.0;
			foreach ( $paid() as $line ) {
				$sum += $line['quantity'] * amouriq_bogo_core_shown_price( $line );
			}
			return round( $sum, 2 );
		},
		'cart_product_ids'      => static function () use ( $paid ) {
			$ids = array();
			foreach ( $paid() as $line ) {
				$ids[] = (int) $line['product_id'];
				if ( $line['variation_id'] ) {
					$ids[] = (int) $line['variation_id'];
				}
			}
			return array_values( array_unique( $ids ) );
		},
		'cart_category_ids'     => static function () use ( $paid ) {
			$ids = array();
			foreach ( $paid() as $line ) {
				foreach ( $line['categories'] as $category ) {
					if ( is_int( $category ) || ctype_digit( (string) $category ) ) {
						$ids[] = (int) $category;
					}
				}
			}
			return array_values( array_unique( $ids ) );
		},
	);
}

/**
 * The customer's counted orders summed up (see amouriq_cond_history()), matched by
 * account and by billing email like a New customer, or null when the customer
 * cannot be identified yet (not logged in and no email typed).
 */
function amouriq_bogo_customer_history() {
	$user_id = get_current_user_id();
	$email   = '';
	if ( $user_id ) {
		$user  = get_userdata( $user_id );
		$email = $user ? $user->user_email : '';
	}
	if ( function_exists( 'WC' ) && WC()->customer && WC()->customer->get_billing_email() ) {
		$email = $email ? $email : WC()->customer->get_billing_email();
	}
	if ( ! $user_id && '' === $email ) {
		return null;
	}

	$statuses = amouriq_bogo_counted_statuses();
	$found    = array();
	if ( $user_id ) {
		foreach ( wc_get_orders( array( 'limit' => -1, 'customer' => $user_id, 'status' => $statuses ) ) as $order ) {
			$found[ $order->get_id() ] = $order;
		}
	}
	if ( '' !== $email ) {
		foreach ( wc_get_orders( array( 'limit' => -1, 'customer' => $email, 'status' => $statuses ) ) as $order ) {
			$found[ $order->get_id() ] = $order;
		}
	}

	$orders = array();
	foreach ( $found as $order ) {
		$date     = $order->get_date_created();
		$orders[] = array(
			'total' => (float) $order->get_total() - (float) $order->get_total_refunded(),
			'time'  => $date ? $date->getTimestamp() : 0,
		);
	}
	return amouriq_cond_history( $orders, time() );
}

/* -------------------------------------------------------------- free shipping */

add_filter( 'flexible-shipping/shipping-method/is-free-shipping', 'amouriq_bogo_free_shipping_filter' );

/**
 * Flexible Shipping asks whether shipping is free for the cart (it only checks its own
 * threshold and never reads the Deals). A live free-shipping Deal says yes. This runs while
 * the shipping rates are worked out, before the cart fees, so it judges the Deals itself.
 */
function amouriq_bogo_free_shipping_filter( $is_free_shipping ) {
	if ( $is_free_shipping || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $is_free_shipping;
	}
	foreach ( amouriq_bogo_deals() as $deal ) {
		if ( amouriq_bogo_free_shipping_live( $deal, WC()->cart ) ) {
			return true;
		}
	}
	return $is_free_shipping;
}

/** Whether this one Deal is a free-shipping Deal that is live for the cart. */
function amouriq_bogo_free_shipping_live( $deal, $cart ) {
	if ( empty( $deal['type'] ) || 'free_shipping' !== $deal['type'] ) {
		return false;
	}
	return amouriq_bogo_core_free_shipping(
		$deal,
		static function () use ( $cart ) {
			return amouriq_bogo_cart_lines( $cart );
		},
		amouriq_bogo_price_context( array( $deal ), $cart )
	);
}
