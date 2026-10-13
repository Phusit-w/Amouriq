<?php
/**
 * Plugin Name: AMOURIQ: points programme
 * Description: Our own loyalty points, built beside the Deal engine and independent of
 *              the Points & Rewards plugin (which stays off). A customer earns points
 *              when an order is Completed (default 1 point per ฿100 paid for items, not
 *              shipping), and can pay part of a later order with them at the cart or
 *              checkout (default 1 point = ฿1, at most 20% of the item total after every
 *              other discount, at least 50 points at a time). Points expire 12 months
 *              after they were earned. Everything is set under WooCommerce > แต้มสะสม and
 *              the programme is OFF until the owner switches it on there, so uploading
 *              this file never changes anything by itself. The rules are in
 *              amouriq-points/points-core.php (tested in tests/PointsTest.php); this file
 *              connects them to the cart, orders, My Account and the admin.
 *
 *              Storage: user meta `amouriq_points_ledger` (one entry per earn / use /
 *              reversal / adjustment). Order meta `_amouriq_points_*` makes each step
 *              happen once however often WooCommerce fires a hook.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/amouriq-bogo/pricing-core.php'; // Product scope and Conditions, shared with the Deals
require_once __DIR__ . '/amouriq-points/points-core.php';

/* ------------------------------------------------------------------ settings */

/** The owner's switch. Off until switched on. */
function amouriq_points_enabled() {
	return 'yes' === get_option( 'amouriq_points_enabled', 'no' );
}

function amouriq_points_settings() {
	return amouriq_points_core_settings( get_option( 'amouriq_points_settings', array() ) );
}

/* -------------------------------------------------------------------- ledger */

function amouriq_points_ledger( $user_id ) {
	$ledger = get_user_meta( (int) $user_id, 'amouriq_points_ledger', true );
	return is_array( $ledger ) ? $ledger : array();
}

/**
 * Record a change of $points (earned +, used -). Earned points (and restored or
 * adjusted-up ones) carry an expiry date.
 */
function amouriq_points_record( $user_id, $points, $type, $order_id = 0, $note = '', $by = 0, $ref = 0 ) {
	$points = (int) $points;
	if ( ! $user_id || 0 === $points ) {
		return;
	}
	$now      = time();
	$ledger   = amouriq_points_ledger( $user_id );
	$ledger[] = array(
		'time'     => $now,
		'points'   => $points,
		'expires'  => $points > 0 ? amouriq_points_core_expiry( $now, amouriq_points_settings() ) : 0,
		'type'     => $type,
		'order_id' => (int) $order_id,
		'note'     => $note,
	);
	if ( $by ) {
		$ledger[ count( $ledger ) - 1 ]['by'] = (int) $by; // the staff member who made an Adjustment
	}
	if ( $ref ) {
		$ledger[ count( $ledger ) - 1 ]['ref'] = (int) $ref; // what the entry is about (the reviewed product for a review bonus)
	}
	update_user_meta( (int) $user_id, 'amouriq_points_ledger', $ledger );
}

/** What the customer can spend now. */
function amouriq_points_balance( $user_id ) {
	return amouriq_points_core_balance( amouriq_points_ledger( $user_id ), time() );
}

/* ------------------------------------------------------------------ earning */

add_action( 'woocommerce_order_status_completed', 'amouriq_points_award', 10, 1 );

/** Paid item total of an order: what was charged, without shipping (points used and every discount are already off it). */
function amouriq_points_order_eligible( WC_Order $order ) {
	$eligible = max( 0.0, (float) $order->get_total() - (float) $order->get_shipping_total() - (float) $order->get_shipping_tax() );
	// The cash-on-delivery Payment fee is not purchase value.
	return function_exists( 'amouriq_payment_fee_of_order' ) ? amouriq_payment_fee_core_eligible( $eligible, amouriq_payment_fee_of_order( $order ) ) : $eligible;
}

function amouriq_points_award( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! amouriq_points_enabled() || ! $order->get_customer_id() || '' !== (string) $order->get_meta( '_amouriq_points_awarded', true ) ) {
		return;
	}

	$eligible = amouriq_points_order_eligible( $order );
	// The rules in force when the order was placed decide the points (a double-points weekend
	// still counts if the order is completed on Monday); orders without a plan are worked out now.
	$planned = $order->get_meta( '_amouriq_points_planned', true );
	$points  = '' !== (string) $planned ? (int) $planned : amouriq_points_earn_for_order( $order );
	$order->update_meta_data( '_amouriq_points_awarded', $points );
	$order->update_meta_data( '_amouriq_points_original', $points );
	$order->update_meta_data( '_amouriq_points_eligible', round( $eligible, 2 ) );
	$order->save();

	if ( $points > 0 ) {
		amouriq_points_record( $order->get_customer_id(), $points, 'earn', $order_id );
		$order->add_order_note( sprintf( 'ลูกค้าได้รับ %d แต้มสะสมจากออเดอร์นี้', $points ) );
	}
}

add_action( 'woocommerce_order_refunded', 'amouriq_points_reverse_for_refund', 10, 1 );

/** A refund takes back the points that the refunded money had earned. */
function amouriq_points_reverse_for_refund( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || '' === (string) $order->get_meta( '_amouriq_points_awarded', true ) ) {
		return;
	}

	$awarded  = (int) $order->get_meta( '_amouriq_points_awarded', true );
	$eligible = (float) $order->get_meta( '_amouriq_points_eligible', true );
	if ( 'yes' === $order->get_meta( '_amouriq_points_by_rules', true ) ) {
		$reverse = amouriq_points_core_refund_reversal_share( $eligible, (float) $order->get_total_refunded(), (int) $order->get_meta( '_amouriq_points_original', true ), $awarded );
	} else {
		$reverse = amouriq_points_core_refund_reversal( $eligible, (float) $order->get_total_refunded(), $awarded, amouriq_points_settings() );
	}
	if ( $reverse <= 0 ) {
		return;
	}
	$order->update_meta_data( '_amouriq_points_awarded', $awarded - $reverse );
	$order->save();
	amouriq_points_record( $order->get_customer_id(), -$reverse, 'refund', $order_id );
	$order->add_order_note( sprintf( 'หักแต้มสะสมคืน %d แต้ม เพราะมีการคืนเงิน', $reverse ) );
}

/* ----------------------------------------------------------------- redeeming */

/** The points the shopper asked to use (session). */
function amouriq_points_requested() {
	return ( function_exists( 'WC' ) && WC()->session ) ? (int) WC()->session->get( 'amouriq_points_use', 0 ) : 0;
}

/**
 * Item-total figures of the cart for the points rules, from the products rather than
 * from cart totals (those are not worked out yet while fees are being added):
 * the tax-exclusive total after coupons and the discount fees added so far, and
 * the factor that turns a tax-exclusive amount into what the shopper sees.
 */
function amouriq_points_cart_figures( $cart ) {
	$ex   = 0.0;
	$incl = 0.0;
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( ! empty( $cart_item['amq_gift'] ) ) {
			continue;
		}
		$product = $cart_item['data'];
		$qty     = (int) $cart_item['quantity'];
		$ex     += $qty * (float) wc_get_price_excluding_tax( $product );
		$incl   += $qty * (float) wc_get_price_to_display( $product );
	}
	$factor = $ex > 0 ? $incl / $ex : 1.0;

	$off = array_sum( $cart->get_coupon_discount_totals() );
	foreach ( $cart->get_fees() as $fee ) {
		if ( $fee->amount < 0 && 0 !== strpos( $fee->name, 'ใช้แต้มสะสม' ) ) {
			$off += -$fee->amount;
		}
	}
	return array(
		'base_incl' => max( 0.0, ( $ex - $off ) * $factor ),
		'factor'    => $factor,
	);
}

add_action( 'woocommerce_cart_calculate_fees', 'amouriq_points_apply_to_cart', 20 );

function amouriq_points_apply_to_cart( $cart ) {
	amouriq_points_applied( 0 );
	if ( ( is_admin() && ! defined( 'DOING_AJAX' ) ) || ! amouriq_points_enabled() || ! is_user_logged_in() ) {
		return;
	}
	$requested = amouriq_points_requested();
	if ( $requested <= 0 ) {
		return;
	}

	$settings = amouriq_points_settings();
	$figures  = amouriq_points_cart_figures( $cart );
	$balance  = amouriq_points_balance( get_current_user_id() );
	$limit    = amouriq_points_core_redeem_limit( $balance['points'], $figures['base_incl'], $settings );
	$use      = amouriq_points_core_redeem_apply( $requested, $limit, $settings );
	if ( $use['points'] <= 0 ) {
		return;
	}

	amouriq_points_applied( $use['points'] );
	// A negative fee is taxed on top by WooCommerce, so hand it the tax-exclusive
	// amount (see amouriq_bogo_unit_price()); it lands back on the baht shown.
	$cart->add_fee( sprintf( 'ใช้แต้มสะสม %d แต้ม', $use['points'] ), -( $use['baht'] / $figures['factor'] ), false );
}

/** Points applied by the last cart calculation (read when the order is created). */
function amouriq_points_applied( $set = null ) {
	static $applied = 0;
	if ( null !== $set ) {
		$applied = (int) $set;
	}
	return $applied;
}

add_action( 'woocommerce_checkout_process', 'amouriq_points_check_balance_at_checkout' );

/** Last check before the order is made: the points must still be there (a second tab may have spent them). */
function amouriq_points_check_balance_at_checkout() {
	$points = amouriq_points_applied();
	if ( $points > 0 && amouriq_points_balance( get_current_user_id() )['points'] < $points ) {
		wc_add_notice( 'แต้มสะสมไม่เพียงพอแล้ว กรุณารีเฟรชหน้านี้และเลือกจำนวนแต้มใหม่', 'error' );
	}
}

add_action( 'woocommerce_checkout_order_processed', 'amouriq_points_spend_for_order', 10, 1 );

function amouriq_points_spend_for_order( $order_id ) {
	$order  = wc_get_order( $order_id );
	$points = amouriq_points_applied();
	if ( ! $order || $points <= 0 || ! $order->get_customer_id() || '' !== (string) $order->get_meta( '_amouriq_points_used', true ) ) {
		return;
	}
	$order->update_meta_data( '_amouriq_points_used', $points );
	$order->save();
	amouriq_points_record( $order->get_customer_id(), -$points, 'use', $order_id );
	$order->add_order_note( sprintf( 'ลูกค้าใช้แต้มสะสม %d แต้ม', $points ) );
	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'amouriq_points_use', 0 );
	}
}

add_action( 'woocommerce_order_status_changed', 'amouriq_points_follow_order_status', 10, 3 );

/**
 * An order that is cancelled or failed gives the points used back; if it is paid later
 * (the customer retries from the pay page) they are used again. A full refund gives them back too.
 */
function amouriq_points_follow_order_status( $order_id, $from, $to ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! $order->get_customer_id() ) {
		return;
	}
	// An order that was completed and is then cancelled or refunded keeps no earned points.
	$awarded = (int) $order->get_meta( '_amouriq_points_awarded', true );
	if ( $awarded > 0 && in_array( $to, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
		$order->update_meta_data( '_amouriq_points_awarded', 0 );
		$order->save();
		amouriq_points_record( $order->get_customer_id(), -$awarded, 'refund', $order_id );
		$order->add_order_note( sprintf( 'หักแต้มสะสมคืน %d แต้ม เพราะออเดอร์ถูกยกเลิก/คืนเงิน', $awarded ) );
	}

	// A first-order bonus goes back with an order that is cancelled or refunded.
	$first_bonus = (int) $order->get_meta( '_amouriq_points_bonus_first', true );
	if ( $first_bonus > 0 && in_array( $to, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
		$order->update_meta_data( '_amouriq_points_bonus_first', 0 );
		$order->save();
		amouriq_points_record( $order->get_customer_id(), -$first_bonus, 'refund', $order_id, 'หักโบนัสออเดอร์แรกคืน' );
	}

	$used = (int) $order->get_meta( '_amouriq_points_used', true );
	if ( $used <= 0 ) {
		return;
	}
	$given_back = '' !== (string) $order->get_meta( '_amouriq_points_given_back', true );

	if ( in_array( $to, array( 'cancelled', 'failed', 'refunded' ), true ) && ! $given_back ) {
		$order->update_meta_data( '_amouriq_points_given_back', 'yes' );
		$order->save();
		amouriq_points_record( $order->get_customer_id(), $used, 'give_back', $order_id );
		$order->add_order_note( sprintf( 'คืนแต้มสะสม %d แต้มให้ลูกค้า', $used ) );
	} elseif ( in_array( $to, array( 'processing', 'on-hold', 'completed' ), true ) && $given_back ) {
		$order->delete_meta_data( '_amouriq_points_given_back' );
		$order->save();
		amouriq_points_record( $order->get_customer_id(), -$used, 'use', $order_id, 'ออเดอร์กลับมาชำระ' );
		$order->add_order_note( sprintf( 'ออเดอร์กลับมาใช้งาน หักแต้มสะสม %d แต้มอีกครั้ง', $used ) );
	}
}

/* ------------------------------------------------------- cart / checkout box */

add_action( 'woocommerce_before_cart_totals', 'amouriq_points_box' );
add_action( 'woocommerce_before_checkout_form', 'amouriq_points_box', 20 );

function amouriq_points_box() {
	if ( ! amouriq_points_enabled() || ! WC()->cart ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		return;
	}
	$user_id  = get_current_user_id();
	$settings = amouriq_points_settings();
	$balance  = amouriq_points_balance( $user_id );
	$figures  = amouriq_points_cart_figures( WC()->cart );
	$limit    = amouriq_points_core_redeem_limit( $balance['points'], $figures['base_incl'], $settings );
	$applied  = amouriq_points_applied();
	$earn     = amouriq_points_core_earn( max( 0.0, (float) WC()->cart->get_total( 'edit' ) - (float) WC()->cart->get_shipping_total() - (float) WC()->cart->get_shipping_tax() - ( function_exists( 'amouriq_payment_fee_in_cart' ) ? amouriq_payment_fee_in_cart( WC()->cart ) : 0.0 ) ), $settings );

	echo '<div class="amouriq-points-box" style="margin:0 0 1.5em;padding:1em 1.2em;border:1px solid #d9d4c7;border-radius:6px;background:#fbf8f2">';
	printf( '<p style="margin:0 0 .4em"><strong>แต้มสะสมของคุณ: %s แต้ม</strong></p>', esc_html( number_format_i18n( $balance['points'] ) ) );

	if ( $applied > 0 ) {
		printf( '<p style="margin:0 0 .6em">ใช้แต้มในออเดอร์นี้ %s แต้ม (ลด ฿%s)</p>', esc_html( number_format_i18n( $applied ) ), esc_html( number_format( $applied * $settings['redeem_baht'], 2 ) ) );
		amouriq_points_form( 'remove', 0, 'ยกเลิกการใช้แต้ม' );
	} elseif ( $limit['points'] > 0 ) {
		printf( '<p style="margin:0 0 .6em">ใช้ได้สูงสุด %s แต้ม (ลด ฿%s) ครั้งละอย่างน้อย %d แต้ม</p>', esc_html( number_format_i18n( $limit['points'] ) ), esc_html( number_format( $limit['points'] * $settings['redeem_baht'], 2 ) ), (int) $settings['min_redeem'] );
		amouriq_points_form( 'apply', $limit['points'], 'ใช้แต้ม', $settings['min_redeem'] );
	} elseif ( $balance['points'] > 0 ) {
		$why = ( 'below_minimum' === $limit['reason'] )
			? sprintf( 'ใช้แต้มได้เมื่อมีอย่างน้อย %d แต้ม', (int) $settings['min_redeem'] )
			: sprintf( 'ยอดสินค้ายังน้อยเกินไปที่จะใช้แต้มได้ (ใช้ได้ไม่เกิน %s%% ของยอดสินค้า และอย่างน้อย %d แต้มต่อครั้ง)', esc_html( rtrim( rtrim( number_format( $settings['max_percent'], 2 ), '0' ), '.' ) ), (int) $settings['min_redeem'] );
		echo '<p style="margin:0">' . esc_html( $why ) . '</p>';
	}
	if ( $earn > 0 ) {
		printf( '<p style="margin:.6em 0 0;font-size:.9em;opacity:.8">ออเดอร์นี้จะได้ประมาณ %s แต้ม เมื่อออเดอร์เสร็จสมบูรณ์</p>', esc_html( number_format_i18n( $earn ) ) );
	}
	echo '</div>';
}

/** A tiny form (outside the cart and checkout forms) that sets how many points to use. */
function amouriq_points_form( $do, $max, $button, $min = 0 ) {
	echo '<form method="post" style="margin:0">';
	wp_nonce_field( 'amouriq_points_use', 'amouriq_points_nonce' );
	echo '<input type="hidden" name="amouriq_points_do" value="' . esc_attr( $do ) . '">';
	if ( 'apply' === $do ) {
		printf( '<input type="number" name="amouriq_points_amount" min="%d" max="%d" value="%d" style="width:7em;margin-right:.5em"> ', (int) $min, (int) $max, (int) $max );
	}
	echo '<button type="submit" class="button">' . esc_html( $button ) . '</button></form>';
}

add_action( 'wp_loaded', 'amouriq_points_handle_form' );

function amouriq_points_handle_form() {
	if ( empty( $_POST['amouriq_points_do'] ) || ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	if ( ! isset( $_POST['amouriq_points_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['amouriq_points_nonce'] ) ), 'amouriq_points_use' ) || ! is_user_logged_in() || ! amouriq_points_enabled() ) {
		return;
	}
	$do = sanitize_key( wp_unslash( $_POST['amouriq_points_do'] ) );
	if ( 'remove' === $do ) {
		WC()->session->set( 'amouriq_points_use', 0 );
	} elseif ( 'apply' === $do ) {
		WC()->session->set( 'amouriq_points_use', isset( $_POST['amouriq_points_amount'] ) ? max( 0, (int) $_POST['amouriq_points_amount'] ) : 0 );
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}
	wp_safe_redirect( wp_get_raw_referer() ? wp_get_raw_referer() : wc_get_cart_url() );
	exit;
}

/* ---------------------------------------------------------------- My Account */

add_action( 'init', 'amouriq_points_account_endpoint' );

function amouriq_points_account_endpoint() {
	add_rewrite_endpoint( 'points', EP_ROOT | EP_PAGES );
	// The new address needs the rewrite rules rebuilt once.
	if ( '1' !== get_option( 'amouriq_points_rewrite_flushed' ) ) {
		flush_rewrite_rules( false );
		update_option( 'amouriq_points_rewrite_flushed', '1', false );
	}
}

add_filter(
	'woocommerce_account_menu_items',
	static function ( $items ) {
		if ( ! amouriq_points_enabled() ) {
			return $items;
		}
		$new = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new['points'] = 'แต้มสะสม';
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new['points'] ) ) {
			$new['points'] = 'แต้มสะสม';
		}
		return $new;
	}
);

/**
 * The header's account dropdown (Blocksy) has a "แต้มสะสมของฉัน" link kept in the theme settings, pointing at
 * /my-points/. The Points switch decides whether it shows, and it points at the real Points page in My Account.
 * Only the front end's copy of the setting is changed; nothing is written back to the theme settings.
 */
add_filter(
	'theme_mod_header_placements',
	static function ( $placements ) {
		if ( is_admin() || ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) || ! function_exists( 'wc_get_account_endpoint_url' ) ) {
			return $placements;
		}
		$walk = static function ( $node ) use ( &$walk ) {
			if ( ! is_array( $node ) ) {
				return $node;
			}
			if ( isset( $node['id'], $node['link'] ) && 'custom_link' === $node['id'] && is_string( $node['link'] ) && false !== strpos( $node['link'], '/my-points' ) ) {
				$node['enabled'] = amouriq_points_enabled();
				$node['link']    = wc_get_account_endpoint_url( 'points' );
				return $node;
			}
			foreach ( $node as $key => $child ) {
				$node[ $key ] = $walk( $child );
			}
			return $node;
		};
		return $walk( $placements );
	}
);

add_action( 'woocommerce_account_points_endpoint', 'amouriq_points_account_page' );

function amouriq_points_account_page() {
	if ( ! amouriq_points_enabled() ) {
		return;
	}
	$user_id  = get_current_user_id();
	$settings = amouriq_points_settings();
	$balance  = amouriq_points_balance( $user_id );
	$names    = amouriq_points_core_type_names();

	$num = static function ( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 2 ), '0' ), '.' );
	};

	amouriq_points_account_css();
	echo '<div class="amq-pts">';

	// Balance card.
	$worth = $balance['points'] * $settings['redeem_baht'];
	echo '<section class="amq-pts-hero"><div class="amq-pts-hero-label">แต้มสะสมคงเหลือ</div>';
	printf( '<div class="amq-pts-hero-num">%s<span> แต้ม</span></div>', esc_html( number_format_i18n( $balance['points'] ) ) );
	printf( '<div class="amq-pts-hero-worth">เทียบเท่าส่วนลดประมาณ ฿%s</div>', esc_html( number_format_i18n( $worth, $worth == floor( $worth ) ? 0 : 2 ) ) );
	if ( $balance['soonest_expiry'] ) {
		printf( '<div class="amq-pts-hero-pill">แต้มชุดที่หมดอายุเร็วที่สุด หมดอายุวันที่ %s</div>', esc_html( wp_date( 'j M Y', $balance['soonest_expiry'] ) ) );
	}
	echo '</section>';

	// How it works.
	echo '<div class="amq-pts-steps">';
	printf(
		'<div class="amq-pts-step"><b>สะสม</b><strong>฿%s = 1 แต้ม</strong><span>ทุกการชำระค่าสินค้า (ไม่รวมค่าส่ง) เมื่อออเดอร์เสร็จสมบูรณ์</span></div>',
		esc_html( number_format( $settings['earn_baht'], 0 ) )
	);
	printf(
		'<div class="amq-pts-step"><b>ใช้</b><strong>1 แต้ม = ฿%s</strong><span>ใช้เป็นส่วนลดที่ตะกร้า ครั้งละอย่างน้อย %d แต้ม และไม่เกิน %s%% ของยอดสินค้า</span></div>',
		esc_html( $num( $settings['redeem_baht'] ) ),
		(int) $settings['min_redeem'],
		esc_html( $num( $settings['max_percent'] ) )
	);
	printf(
		'<div class="amq-pts-step"><b>อายุแต้ม</b><strong>%s</strong><span>%s</span></div>',
		$settings['expiry_months'] ? esc_html( (int) $settings['expiry_months'] . ' เดือน' ) : 'ไม่มีวันหมดอายุ',
		$settings['expiry_months'] ? 'นับจากวันที่ได้รับแต้มแต่ละครั้ง' : 'แต้มที่สะสมไว้อยู่กับคุณตลอดไป'
	);
	echo '</div>';

	amouriq_points_account_rewards( $user_id, $balance['points'] );

	// History.
	echo '<h3 class="amq-pts-h">ประวัติแต้มสะสม</h3>';
	$ledger = array_reverse( amouriq_points_ledger( $user_id ) );
	if ( ! $ledger ) {
		echo '<div class="amq-pts-empty"><strong>ยังไม่มีรายการแต้มสะสม</strong><span>แต้มจะเริ่มสะสมเมื่อคุณสั่งซื้อและออเดอร์เสร็จสมบูรณ์</span>';
		printf( '<a class="amq-pts-btn" href="%s">เลือกซื้อสินค้า</a></div>', esc_url( wc_get_page_permalink( 'shop' ) ) );
		echo '</div>';
		return;
	}
	echo '<ul class="amq-pts-ledger">';
	foreach ( array_slice( $ledger, 0, 50 ) as $row ) {
		$type   = isset( $row['type'] ) ? $row['type'] : '';
		$plus   = $row['points'] > 0;
		$detail = wp_date( 'j M Y', $row['time'] ) . ( ! empty( $row['order_id'] ) ? ' · ออเดอร์ #' . $row['order_id'] : '' );
		printf(
			'<li><span class="amq-pts-dot %s">%s</span><span class="amq-pts-ledger-main"><strong>%s</strong><small>%s</small></span><span class="amq-pts-amt %s">%s</span></li>',
			$plus ? 'is-plus' : 'is-minus',
			$plus ? '+' : '−',
			esc_html( isset( $names[ $type ] ) ? $names[ $type ] : $type ),
			esc_html( $detail ),
			$plus ? 'is-plus' : 'is-minus',
			esc_html( ( $plus ? '+' : '' ) . number_format_i18n( $row['points'] ) )
		);
	}
	echo '</ul></div>';
}

/** The customer Points page's own styles (one block, printed with the page). Colours follow the theme palette. */
function amouriq_points_account_css() {
	echo '<style>
.amq-pts{--navy:var(--theme-palette-color-1,#1F3A55);--sky:var(--theme-palette-color-2,#8EBED6);--ink:#1d2733;--muted:#6b7785;--line:#e6ebf0;--soft:#f5f8fb;--good:#1e8a5a;--bad:#b9382b;max-width:780px;color:var(--ink)}
.amq-pts *{box-sizing:border-box}
.amq-pts-hero{position:relative;overflow:hidden;border-radius:20px;padding:30px 30px 26px;color:#fff;background:linear-gradient(135deg,var(--navy) 0%,#335b82 100%);box-shadow:0 10px 28px rgba(31,58,85,.22)}
.amq-pts-hero::before{content:"";position:absolute;right:-60px;top:-70px;width:220px;height:220px;border-radius:50%;background:rgba(142,190,214,.28)}
.amq-pts-hero::after{content:"";position:absolute;right:50px;bottom:-90px;width:170px;height:170px;border-radius:50%;background:rgba(255,255,255,.08)}
.amq-pts-hero>*{position:relative;z-index:1}
.amq-pts-hero-label{font-size:15px;opacity:.85;letter-spacing:.02em}
.amq-pts-hero-num{font-size:60px;line-height:1.1;font-weight:700;margin:6px 0 4px}
.amq-pts-hero-num span{font-size:20px;font-weight:500;opacity:.9}
.amq-pts-hero-worth{font-size:15px;opacity:.9}
.amq-pts-hero-pill{display:inline-block;margin-top:16px;padding:7px 14px;border-radius:999px;font-size:13px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.25)}
.amq-pts-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin:18px 0 6px}
.amq-pts-step{display:flex;flex-direction:column;gap:4px;padding:16px 18px;border-radius:14px;background:var(--soft);border:1px solid var(--line)}
.amq-pts-step b{font-size:12px;font-weight:600;color:var(--navy);text-transform:uppercase;letter-spacing:.06em}
.amq-pts-step strong{font-size:20px;color:var(--navy)}
.amq-pts-step span{font-size:13px;line-height:1.5;color:var(--muted)}
.amq-pts-h{margin:34px 0 14px;font-size:20px;color:var(--navy)}
.amq-pts-rewards{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px}
.amq-pts-reward{display:flex;flex-direction:column;gap:10px;margin:0;padding:18px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 2px 10px rgba(31,58,85,.05)}
.amq-pts-reward.is-ready{border-color:var(--sky);box-shadow:0 4px 16px rgba(142,190,214,.35)}
.amq-pts-reward-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
.amq-pts-reward-top strong{font-size:16px;line-height:1.35;color:var(--ink)}
.amq-pts-cost{flex:none;padding:4px 11px;border-radius:999px;font-size:13px;font-weight:600;color:var(--navy);background:rgba(142,190,214,.32)}
.amq-pts-reward small{font-size:13px;line-height:1.55;color:var(--muted)}
.amq-pts-bar{height:7px;border-radius:99px;background:var(--line);overflow:hidden}
.amq-pts-bar i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,var(--sky),var(--navy))}
.amq-pts-btn,.amq-pts button.amq-pts-btn{display:block;width:100%;padding:11px 16px;border:0;border-radius:10px;font:inherit;font-weight:600;text-align:center;text-decoration:none;cursor:pointer;color:#fff!important;background:var(--navy)}
.amq-pts-btn:hover{filter:brightness(1.12)}
.amq-pts .amq-pts-btn[disabled],.amq-pts .amq-pts-btn[disabled]:hover{cursor:not-allowed;filter:none;color:var(--muted)!important;background:var(--soft)!important;border:1px dashed #cdd6df!important;font-weight:500}
.amq-pts-reward .amq-pts-btn{margin-top:auto}
.amq-pts-coupons{display:grid;gap:10px;margin:0;padding:0;list-style:none}
.amq-pts-coupons li{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;padding:14px 16px;border:1px solid var(--line);border-radius:14px;background:#fff}
.amq-pts-coupons .grow{flex:1 1 180px;display:flex;flex-direction:column;gap:2px}
.amq-pts-coupons small{color:var(--muted);font-size:12px}
.amq-pts-code{padding:6px 12px;border:1px dashed var(--navy);border-radius:8px;font-family:ui-monospace,Consolas,monospace;font-size:14px;letter-spacing:.04em;color:var(--navy);background:var(--soft)}
.amq-pts-badge{padding:4px 11px;border-radius:999px;font-size:12px;font-weight:600;background:rgba(30,138,90,.12);color:var(--good)}
.amq-pts-badge.is-off{background:#eef0f2;color:var(--muted)}
.amq-pts-ledger{margin:0;padding:0;list-style:none;border:1px solid var(--line);border-radius:16px;overflow:hidden;background:#fff}
.amq-pts-ledger li{display:flex;align-items:center;gap:14px;padding:13px 16px;border-top:1px solid var(--line)}
.amq-pts-ledger li:first-child{border-top:0}
.amq-pts-dot{flex:none;display:grid;place-items:center;width:34px;height:34px;border-radius:50%;font-weight:700;font-size:17px}
.amq-pts-dot.is-plus{background:rgba(30,138,90,.12);color:var(--good)}
.amq-pts-dot.is-minus{background:rgba(185,56,43,.1);color:var(--bad)}
.amq-pts-ledger-main{flex:1;display:flex;flex-direction:column;gap:1px;min-width:0}
.amq-pts-ledger-main small{color:var(--muted);font-size:12px}
.amq-pts-amt{flex:none;font-size:17px;font-weight:700}
.amq-pts-amt.is-plus{color:var(--good)}.amq-pts-amt.is-minus{color:var(--bad)}
.amq-pts-empty{display:flex;flex-direction:column;align-items:center;gap:6px;padding:30px 20px;border:1px dashed var(--sky);border-radius:16px;background:var(--soft);text-align:center}
.amq-pts-empty span{color:var(--muted);font-size:14px;margin-bottom:10px}
.amq-pts-empty .amq-pts-btn{width:auto;min-width:200px}
@media (max-width:520px){.amq-pts-hero{padding:24px 20px 22px}.amq-pts-hero-num{font-size:48px}}
</style>';
}

/* --------------------------------------------------------------------- admin */

add_action( 'admin_menu', 'amouriq_points_admin_menu' );

function amouriq_points_admin_menu() {
	add_submenu_page( 'woocommerce', 'แต้มสะสม', 'แต้มสะสม', 'manage_woocommerce', 'amouriq-points', 'amouriq_points_admin_page' );
}

add_action( 'admin_init', 'amouriq_points_admin_handle' );

function amouriq_points_admin_url( $args = array() ) {
	if ( ! isset( $args['tab'] ) ) {
		// A member, a search or a page number belongs to the Members tab; anything else stays on the tab being shown.
		$args['tab'] = ( isset( $args['email'] ) || isset( $args['q'] ) || isset( $args['paged'] ) ) ? 'members' : amouriq_points_admin_tab();
	}
	return add_query_arg( array_merge( array( 'page' => 'amouriq-points' ), $args ), admin_url( 'admin.php' ) );
}

function amouriq_points_admin_handle() {
	if ( empty( $_POST['amouriq_points_admin'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	check_admin_referer( 'amouriq_points_admin', 'amouriq_points_admin_nonce' );
	$action = sanitize_key( wp_unslash( $_POST['amouriq_points_admin'] ) );
	$notice = '';
	$args   = array();

	if ( 'toggle' === $action ) {
		$on = ! amouriq_points_enabled();
		update_option( 'amouriq_points_enabled', $on ? 'yes' : 'no', false );
		$notice = $on ? 'เปิดระบบแต้มสะสมแล้ว' : 'ปิดระบบแต้มสะสมแล้ว';
	} elseif ( 'save' === $action ) {
		$in = array();
		foreach ( array( 'earn_baht', 'redeem_baht', 'max_percent', 'min_redeem', 'expiry_months' ) as $key ) {
			$in[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}
		update_option( 'amouriq_points_settings', amouriq_points_core_settings( $in ), false );
		$notice = 'บันทึกการตั้งค่าแล้ว';
	} elseif ( in_array( $action, array( 'reward_add', 'reward_toggle', 'reward_delete' ), true ) ) {
		$notice = amouriq_points_admin_reward_action( $action );
	} elseif ( 'expiry_mail_save' === $action ) {
		$notice = amouriq_points_admin_expiry_mail_save();
	} elseif ( 'bonus_save' === $action ) {
		$notice = amouriq_points_admin_bonus_save();
	} elseif ( in_array( $action, array( 'earn_add', 'earn_delete', 'earn_toggle', 'earn_limits' ), true ) ) {
		$notice = amouriq_points_admin_earning_action( $action );
	} elseif ( 'legacy_import' === $action ) {
		$notice = amouriq_points_admin_legacy_import();
	} elseif ( 'adjust' === $action ) {
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$user   = $email ? get_user_by( 'email', $email ) : false;
		$points = isset( $_POST['points'] ) ? (int) $_POST['points'] : 0;
		$note   = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		if ( ! $user ) {
			$notice = 'ไม่พบสมาชิกที่ใช้อีเมลนี้';
		} else {
			$problem = amouriq_points_core_check_adjustment( $points, $note, amouriq_points_balance( $user->ID )['points'] );
			if ( $problem ) {
				$notice = $problem;
			} else {
				amouriq_points_record( $user->ID, $points, 'adjust', 0, $note, get_current_user_id() );
				$notice = sprintf( 'ปรับแต้มของ %s แล้ว: %+d', $email, $points );
			}
		}
		$args['email'] = $email;
	}

	set_transient( 'amouriq_points_notice_' . get_current_user_id(), $notice, 60 );
	$args['tab'] = isset( $args['email'] ) ? 'members' : amouriq_points_admin_tab();
	wp_safe_redirect( amouriq_points_admin_url( $args ) );
	exit;
}

function amouriq_points_admin_form( $action, $inner, $button, $class = 'button button-primary', $confirm = '' ) {
	echo '<form method="post" style="display:inline"' . ( $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : '' ) . '>';
	wp_nonce_field( 'amouriq_points_admin', 'amouriq_points_admin_nonce' );
	echo '<input type="hidden" name="amouriq_points_tab" value="' . esc_attr( amouriq_points_admin_tab() ) . '">';
	echo '<input type="hidden" name="amouriq_points_admin" value="' . esc_attr( $action ) . '">' . $inner; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $button ) . '</button></form>';
}

/** The tabs of the points screen: key => Thai label. */
function amouriq_points_admin_tabs() {
	return array(
		'overview' => 'ภาพรวม',
		'settings' => 'ตั้งค่า',
		'rules'    => 'กติกาและโบนัส',
		'rewards'  => 'ของรางวัล',
		'members'  => 'สมาชิก',
		'report'   => 'รายงาน',
	);
}

/** The tab being shown (from the address, or from the form just posted). */
function amouriq_points_admin_tab() {
	// phpcs:disable WordPress.Security.NonceVerification -- read-only; a posted form is checked in amouriq_points_admin_handle()
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( isset( $_POST['amouriq_points_tab'] ) ? sanitize_key( wp_unslash( $_POST['amouriq_points_tab'] ) ) : 'overview' );
	// phpcs:enable
	return isset( amouriq_points_admin_tabs()[ $tab ] ) ? $tab : 'overview';
}

/** A plain sentence for each current rule, so the owner reads what the customer gets. */
function amouriq_points_admin_summary_lines() {
	$s       = amouriq_points_settings();
	$earning = amouriq_points_earning();
	$bonus   = amouriq_points_bonus();
	$mail    = amouriq_points_expiry_mail_settings();
	$n       = static function ( $v ) {
		return amouriq_bogo_member_number( $v );
	};
	$lines   = array();
	$lines[] = sprintf( 'ลูกค้าได้ 1 แต้มทุก ฿%s ที่จ่ายค่าสินค้า (ไม่รวมค่าส่ง) เมื่อออเดอร์เสร็จสมบูรณ์', $n( $s['earn_baht'] ) );
	$lines[] = sprintf( 'ใช้แต้มเป็นส่วนลดได้ 1 แต้ม = ฿%s ครั้งละอย่างน้อย %d แต้ม และไม่เกิน %s%% ของยอดสินค้า', $n( $s['redeem_baht'] ), (int) $s['min_redeem'], $n( $s['max_percent'] ) );
	$lines[] = $s['expiry_months'] ? sprintf( 'แต้มหมดอายุหลังได้รับ %d เดือน', (int) $s['expiry_months'] ) : 'แต้มไม่หมดอายุ';
	$rules   = count( array_filter( $earning['rules'], static function ( $r ) {
		return $r['enabled'];
	} ) );
	$lines[] = $rules ? sprintf( 'มีกติกาการได้แต้มพิเศษที่เปิดอยู่ %d ข้อ', $rules ) : 'ยังไม่มีกติกาการได้แต้มพิเศษ (ใช้กติกาพื้นฐานอย่างเดียว)';
	$on      = array();
	foreach ( array( 'signup' => 'สมัครสมาชิก', 'first_order' => 'ออเดอร์แรก', 'review' => 'รีวิวสินค้า' ) as $key => $label ) {
		if ( $bonus[ $key ]['enabled'] ) {
			$on[] = $label . ' ' . $bonus[ $key ]['points'] . ' แต้ม';
		}
	}
	$lines[] = $on ? 'โบนัสที่เปิดอยู่: ' . implode(', ', $on ) : 'ยังไม่เปิดโบนัสใด ๆ';
	$active_rewards = count( array_filter( amouriq_points_rewards(), static function ( $r ) {
		return $r['enabled'];
	} ) );
	$lines[] = $active_rewards ? sprintf( 'ลูกค้าแลกแต้มเป็นของรางวัลได้ %d รายการ', $active_rewards ) : 'ยังไม่มีของรางวัลให้แลก';
	$lines[] = $mail['enabled'] ? sprintf( 'ส่งอีเมลเตือนก่อนแต้มหมดอายุ %d วัน', $mail['days'] ) : 'ไม่ส่งอีเมลเตือนแต้มใกล้หมดอายุ';
	return $lines;
}

function amouriq_points_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$tab    = amouriq_points_admin_tab();
	$on     = amouriq_points_enabled();
	$notice = get_transient( 'amouriq_points_notice_' . get_current_user_id() );
	delete_transient( 'amouriq_points_notice_' . get_current_user_id() );

	echo '<style>
		.amq-pts-card { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:12px 18px; margin:14px 0; max-width:900px; }
		.amq-pts-card h2 { margin-top:4px; }
		.amq-pts-state { display:inline-block; padding:2px 12px; border-radius:12px; font-weight:600; }
		.amq-pts-state.on { background:#d6f5d6; color:#0a5c0a; } .amq-pts-state.off { background:#f0f0f1; color:#50575e; }
		.amq-pts-help { color:#50575e; max-width:900px; }
		.amq-pts-wrap h2 { margin-top:28px; } .amq-pts-wrap .form-table th { width:260px; }
		.amq-pts-stats { display:flex; gap:12px; flex-wrap:wrap; margin:10px 0; }
		.amq-pts-stat { background:#f6f7f7; border-radius:4px; padding:10px 16px; min-width:150px; } .amq-pts-stat b { display:block; font-size:22px; }
		@media (max-width:782px) { .amq-pts-wrap .form-table th { width:auto; } }
	</style>';
	echo '<div class="wrap amq-pts-wrap"><h1>แต้มสะสม <span class="amq-pts-state ' . ( $on ? 'on' : 'off' ) . '">' . ( $on ? 'เปิดอยู่' : 'ปิดอยู่' ) . '</span></h1>';
	if ( $notice ) {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}

	echo '<nav class="nav-tab-wrapper" style="margin-bottom:12px">';
	foreach ( amouriq_points_admin_tabs() as $key => $label ) {
		echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( amouriq_points_admin_url( array( 'tab' => $key ) ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';

	switch ( $tab ) {
		case 'settings':
			amouriq_points_admin_tab_settings();
			break;
		case 'rules':
			echo '<p class="amq-pts-help">กำหนดว่าลูกค้าจะได้แต้มเท่าไรและเมื่อไร นอกเหนือจากกติกาพื้นฐานในแท็บ "ตั้งค่า"</p>';
			amouriq_points_admin_earning_section();
			amouriq_points_admin_bonus_section();
			break;
		case 'rewards':
			echo '<p class="amq-pts-help">ของที่ลูกค้าเอาแต้มมาแลกได้ในหน้า "บัญชีของฉัน"</p>';
			amouriq_points_admin_rewards_section();
			break;
		case 'members':
			amouriq_points_admin_tab_members();
			break;
		case 'report':
			echo '<p class="amq-pts-help">ดูว่าแจกแต้มไปเท่าไร ใช้ไปเท่าไร และมีแต้มค้างอยู่เท่าไร</p>';
			amouriq_points_admin_report_section();
			break;
		default:
			amouriq_points_admin_tab_overview( $on );
	}
	echo '</div>';
}

function amouriq_points_admin_tab_overview( $on ) {
	echo '<div class="amq-pts-card"><h2>สถานะระบบ</h2><p>ตอนนี้ระบบ<strong>' . ( $on ? 'เปิดอยู่' : 'ปิดอยู่' ) . '</strong> ';
	echo $on ? '(ลูกค้าได้แต้มเมื่อออเดอร์เสร็จสมบูรณ์ และใช้แต้มได้ที่ตะกร้าและหน้าชำระเงิน)' : '(ยังไม่มีใครได้หรือใช้แต้ม ปิดอยู่จนกว่าจะกดเปิด)';
	echo '</p>';
	amouriq_points_admin_form( 'toggle', '', $on ? 'ปิดระบบแต้มสะสม' : 'เปิดระบบแต้มสะสม', $on ? 'button' : 'button button-primary', $on ? '' : 'เปิดระบบแต้มสะสมให้ลูกค้าทุกคนทันที ใช่ไหม?' );
	if ( ! $on ) {
		echo '<p class="description">ก่อนเปิด: ตรวจกติกาด้านล่าง และทดสอบสั่งซื้อจริงด้วยบัญชีทดสอบหนึ่งบัญชี (สั่งซื้อ > เปลี่ยนสถานะเป็นเสร็จสมบูรณ์ > ดูแต้มในบัญชีของฉัน)</p>';
	}
	echo '</div>';

	$report = amouriq_points_core_report( amouriq_points_all_ledgers(), time(), amouriq_points_site_offset() );
	$redeem = amouriq_points_settings()['redeem_baht'];
	echo '<div class="amq-pts-stats">';
	printf( '<div class="amq-pts-stat">แต้มคงเหลือของสมาชิกทั้งหมด<b>%s</b>มูลค่า ฿%s</div>', esc_html( number_format_i18n( $report['outstanding'] ) ), esc_html( number_format_i18n( $report['outstanding'] * $redeem, 2 ) ) );
	printf( '<div class="amq-pts-stat">จะหมดอายุภายใน 30 วัน<b>%s</b>แต้ม</div>', esc_html( number_format_i18n( $report['expiring_soon'] ) ) );
	printf( '<div class="amq-pts-stat">สมาชิกที่มีรายการแต้ม<b>%s</b>คน</div>', esc_html( number_format_i18n( count( amouriq_points_all_ledgers() ) ) ) );
	echo '</div>';

	echo '<div class="amq-pts-card"><h2>กติกาตอนนี้ (อ่านแบบที่ลูกค้าเห็น)</h2><ul style="list-style:disc;margin-left:22px">';
	foreach ( amouriq_points_admin_summary_lines() as $line ) {
		echo '<li>' . esc_html( $line ) . '</li>';
	}
	echo '</ul><p><a class="button" href="' . esc_url( amouriq_points_admin_url( array( 'tab' => 'settings' ) ) ) . '">แก้ตัวเลขพื้นฐาน</a> <a class="button" href="' . esc_url( amouriq_points_admin_url( array( 'tab' => 'rules' ) ) ) . '">แก้กติกาและโบนัส</a> <a class="button" href="' . esc_url( amouriq_points_admin_url( array( 'tab' => 'rewards' ) ) ) . '">แก้ของรางวัล</a></p></div>';
}

function amouriq_points_admin_tab_settings() {
	$settings = amouriq_points_settings();
	echo '<p class="amq-pts-help">ตัวเลขพื้นฐานของระบบ ใช้กับทุกออเดอร์ ถ้าอยากให้บางหมวดหรือบางช่วงเวลาได้แต้มต่างออกไป ไปที่แท็บ "กติกาและโบนัส"</p>';
	$fields = array(
		'earn_baht'     => array( 'ซื้อสินค้ากี่บาท ได้ 1 แต้ม', 'บาท (ยอดสินค้าที่จ่ายจริง ไม่รวมค่าส่ง)' ),
		'redeem_baht'   => array( '1 แต้มใช้เป็นส่วนลดกี่บาท', 'บาท' ),
		'max_percent'   => array( 'ใช้แต้มได้สูงสุด', '% ของยอดสินค้า (หลังหักดีล สิทธิพิเศษสมาชิก และคูปองแล้ว)' ),
		'min_redeem'    => array( 'ใช้แต้มครั้งละอย่างน้อย', 'แต้ม' ),
		'expiry_months' => array( 'แต้มหมดอายุหลังได้รับ', 'เดือน (0 = ไม่หมดอายุ) มีผลกับแต้มที่ได้หลังจากบันทึก' ),
	);
	$inner = '<table class="form-table">';
	foreach ( $fields as $key => $meta ) {
		$inner .= '<tr><th>' . esc_html( $meta[0] ) . '</th><td><input type="number" step="any" min="0" name="' . esc_attr( $key ) . '" value="' . esc_attr( $settings[ $key ] ) . '" style="width:8em"> ' . esc_html( $meta[1] ) . '</td></tr>';
	}
	$inner .= '</table>';
	echo '<div class="amq-pts-card"><h2>ตัวเลขพื้นฐาน</h2>';
	amouriq_points_admin_form( 'save', $inner, 'บันทึกการตั้งค่า' );
	echo '</div>';
	echo '<div class="amq-pts-card">';
	amouriq_points_admin_expiry_mail_section();
	echo '</div>';
}

function amouriq_points_admin_tab_members() {
	// phpcs:disable WordPress.Security.NonceVerification -- read-only
	$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
	// phpcs:enable
	$found = $email ? get_user_by( 'email', $email ) : false;

	if ( $email && ! $found ) {
		echo '<div class="notice notice-warning inline"><p>ไม่พบสมาชิกที่ใช้อีเมลนี้</p></div>';
	}
	if ( $found ) {
		$balance = amouriq_points_balance( $found->ID );
		echo '<div class="amq-pts-card"><h2>' . esc_html( $found->display_name ) . ' <small>(' . esc_html( $email ) . ')</small></h2>';
		printf( '<p style="font-size:16px"><strong>แต้มคงเหลือ %s แต้ม</strong>%s</p>', esc_html( number_format_i18n( $balance['points'] ) ), $balance['soonest_expiry'] ? esc_html( ' · ชุดที่หมดอายุเร็วสุดหมดวันที่ ' . wp_date( 'j M Y', $balance['soonest_expiry'] ) ) : '' );
		echo '<h3>เพิ่มหรือหักแต้มให้สมาชิกคนนี้</h3><p class="description">ใส่เลขบวกเพื่อเพิ่ม เลขลบเพื่อหัก เช่น ชดเชยหรือแก้ไขข้อผิดพลาด ต้องใส่เหตุผลทุกครั้ง และบันทึกชื่อผู้ปรับไว้</p>';
		$inner = '<input type="hidden" name="email" value="' . esc_attr( $email ) . '"><p><label>แต้ม <input type="number" name="points" style="width:7em" required></label> '
			. '<label>เหตุผล (จำเป็น) <input type="text" name="note" style="width:20em" required></label></p>';
		amouriq_points_admin_form( 'adjust', $inner, 'ปรับแต้ม' );
		echo '<h3>ประวัติแต้ม</h3><table class="widefat striped"><thead><tr><th>วันที่</th><th>รายการ</th><th>ออเดอร์</th><th>แต้ม</th><th>หมดอายุ</th><th>หมายเหตุ</th><th>โดย</th></tr></thead><tbody>';
		foreach ( array_reverse( amouriq_points_ledger( $found->ID ) ) as $row ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( wp_date( 'j M Y H:i', $row['time'] ) ),
				esc_html( isset( $row['type'] ) ? ( amouriq_points_core_type_names()[ $row['type'] ] ?? $row['type'] ) : '' ),
				! empty( $row['order_id'] ) ? esc_html( '#' . $row['order_id'] ) : '—',
				esc_html( ( $row['points'] > 0 ? '+' : '' ) . $row['points'] ),
				! empty( $row['expires'] ) ? esc_html( wp_date( 'j M Y', $row['expires'] ) ) : '—',
				esc_html( isset( $row['note'] ) ? $row['note'] : '' ),
				! empty( $row['by'] ) ? esc_html( ( get_userdata( $row['by'] ) ? get_userdata( $row['by'] )->display_name : '#' . $row['by'] ) ) : ''
			);
		}
		echo '</tbody></table><p><a href="' . esc_url( amouriq_points_admin_url( array( 'tab' => 'members' ) ) ) . '">&larr; กลับไปรายชื่อสมาชิก</a></p></div>';
		return;
	}

	amouriq_points_admin_legacy_section();
	echo '<p class="amq-pts-help">เลือกสมาชิกจากรายชื่อเพื่อดูประวัติแต้ม และเพิ่มหรือหักแต้มให้เป็นรายคน (ค้นหาด้วยชื่อหรืออีเมลได้)</p>';
	amouriq_points_admin_customer_list();
}

/* ----------------------------------------------------------- legacy import */

/**
 * The old Points & Rewards plugin keeps each member's balance in the user meta `wps_wpr_points`; it stays
 * in the database while that plugin is inactive. Returns user ID => balance for every member with one.
 */
function amouriq_points_legacy_balances() {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'wps_wpr_points' AND CAST( meta_value AS DECIMAL(12,2) ) > 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$out  = array();
	foreach ( (array) $rows as $row ) {
		$out[ (int) $row->user_id ] = $row->meta_value;
	}
	return $out;
}

/** The legacy plan (see amouriq_points_core_legacy_plan()): members already imported carry the meta `amouriq_points_legacy_imported`. */
function amouriq_points_legacy_plan() {
	$legacy = amouriq_points_legacy_balances();
	$done   = array();
	foreach ( array_keys( $legacy ) as $user_id ) {
		if ( get_user_meta( $user_id, 'amouriq_points_legacy_imported', true ) ) {
			$done[] = $user_id;
		}
	}
	return amouriq_points_core_legacy_plan( $legacy, $done );
}

/** Staff accounts are unticked by default in the import list (they are rarely real customers' balances). */
function amouriq_points_legacy_is_staff( $user_id ) {
	return user_can( $user_id, 'manage_woocommerce' ) || user_can( $user_id, 'manage_options' );
}

/**
 * Admin: bring the ticked members' old balance over as one "ยกยอดจากระบบเดิม" entry each (expiring by today's
 * rules). Only while the programme is off, once per member, and the balance is read again here, never taken
 * from the form. The old plugin's data is left as it is. Returns the notice.
 */
function amouriq_points_admin_legacy_import() {
	if ( amouriq_points_enabled() ) {
		return 'ยกยอดไม่ได้ขณะที่ระบบแต้มเปิดอยู่ ปิดระบบก่อน แล้วค่อยยกยอด';
	}
	// phpcs:ignore WordPress.Security.NonceVerification -- checked in amouriq_points_admin_handle()
	$ticked = isset( $_POST['legacy_users'] ) && is_array( $_POST['legacy_users'] ) ? array_map( 'intval', wp_unslash( $_POST['legacy_users'] ) ) : array();
	$count  = 0;
	$total  = 0;
	foreach ( amouriq_points_legacy_plan() as $row ) {
		if ( 'ready' !== $row['status'] || ! in_array( $row['user_id'], $ticked, true ) || ! get_userdata( $row['user_id'] ) ) {
			continue;
		}
		amouriq_points_record( $row['user_id'], $row['points'], 'import', 0, 'ยกยอดจากระบบแต้มเดิม (Points & Rewards)', get_current_user_id() );
		update_user_meta( $row['user_id'], 'amouriq_points_legacy_imported', time() );
		++$count;
		$total += $row['points'];
	}
	return $count ? sprintf( 'ยกยอดแล้ว %d คน รวม %s แต้ม', $count, number_format_i18n( $total ) ) : 'ไม่ได้เลือกสมาชิกคนใดที่ยังยกยอดได้';
}

/** The "bring over old points" box on the Members tab: only shown while there is something from the old plugin. */
function amouriq_points_admin_legacy_section() {
	$plan = amouriq_points_legacy_plan();
	if ( ! $plan ) {
		return;
	}
	$pending = array_filter(
		$plan,
		static function ( $row ) {
			return 'ready' === $row['status'];
		}
	);
	echo '<div class="amq-pts-card"><h2>ยกยอดแต้มจากระบบเดิม</h2>';
	echo '<p class="description">ระบบแต้มเดิม (Points &amp; Rewards) ยังมียอดแต้มเก็บไว้ของสมาชิกด้านล่าง ยกยอดมาเป็นแต้มของระบบนี้ได้ครั้งเดียวต่อคน โดยนับอายุแต้มใหม่ตามกติกาตอนนี้ ข้อมูลเดิมไม่ถูกลบหรือแก้ ปลั๊กอินเดิมต้องปิดไว้ตลอด</p>';

	// Every piece below is escaped as it is built; `$editable` adds the tick boxes (only while the programme is off).
	$table = static function ( $editable ) use ( $plan ) {
		$html = '';
		foreach ( $plan as $row ) {
			$user  = get_userdata( $row['user_id'] );
			$staff = $user && amouriq_points_legacy_is_staff( $row['user_id'] );
			$ready = 'ready' === $row['status'];
			$html .= sprintf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td style="text-align:right"><strong>%s</strong></td><td>%s</td></tr>',
				( $ready && $editable ) ? '<input type="checkbox" name="legacy_users[]" value="' . (int) $row['user_id'] . '"' . ( $staff ? '' : ' checked' ) . '>' : ( $ready ? '' : '✓' ),
				esc_html( $user ? $user->display_name : '#' . $row['user_id'] ),
				esc_html( $user ? $user->user_email : '' ),
				esc_html( number_format_i18n( $row['points'] ) ),
				$ready ? ( $staff ? 'บัญชีทีมงาน (ไม่ได้ติ๊กไว้ให้)' : 'ยังไม่ได้ยกยอด' ) : 'ยกยอดแล้ว'
			);
		}
		return '<table class="widefat striped" style="max-width:760px;margin:8px 0"><thead><tr><th style="width:36px"></th><th>สมาชิก</th><th>อีเมล</th><th style="text-align:right">แต้มเดิม</th><th>สถานะ</th></tr></thead><tbody>' . $html . '</tbody></table>';
	};

	if ( ! $pending ) {
		echo $table( false ) . '<p>ยกยอดครบทุกคนแล้ว</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
		return;
	}
	if ( amouriq_points_enabled() ) {
		echo $table( false ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
		echo '<p><strong>ปิดระบบแต้มสะสมก่อนจึงจะยกยอดได้</strong> (กันไม่ให้ยอดเปลี่ยนระหว่างที่ลูกค้ากำลังใช้งาน) ปิดที่แท็บ "ภาพรวม" แล้วกลับมาที่นี่</p></div>';
		return;
	}
	echo '<p>ติ๊กสมาชิกที่ต้องการยกยอด (บัญชีทีมงานไม่ได้ติ๊กไว้ให้) แล้วกดปุ่มด้านล่าง</p>';
	amouriq_points_admin_form( 'legacy_import', $table( true ), 'ยกยอดแต้มที่ติ๊กไว้', 'button button-primary', 'ยกยอดแต้มของสมาชิกที่ติ๊กไว้ ทำซ้ำกับคนเดิมไม่ได้ ยืนยันไหม?' );
	echo '</div>';
}

/**
 * Members who have anything in their ledger, with balance and the soonest expiry,
 * 20 to a page, newest ledger holders first by user ID. `q` searches name and email.
 * Each row links to that member's ledger below.
 */
function amouriq_points_admin_customer_list() {
	// phpcs:disable WordPress.Security.NonceVerification -- read-only listing
	$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	// phpcs:enable

	$args = array(
		'number'   => 20,
		'paged'    => $paged,
		'orderby'  => 'ID',
		'order'    => 'DESC',
		'fields'   => array( 'ID', 'user_email', 'display_name' ),
		'meta_key' => 'amouriq_points_ledger', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_compare' => 'EXISTS',
	);
	if ( '' !== $search ) {
		$args['search']         = '*' . $search . '*';
		$args['search_columns'] = array( 'user_email', 'display_name', 'user_login' );
	}
	$query = new WP_User_Query( $args );
	$total = (int) $query->get_total();

	echo '<h2>สมาชิกที่มีแต้ม</h2>';
	echo '<form method="get"><input type="hidden" name="page" value="amouriq-points"><input type="hidden" name="tab" value="members"><input type="search" name="q" value="' . esc_attr( $search ) . '" placeholder="ค้นหาชื่อหรืออีเมล"> <button class="button">ค้นหา</button></form>';
	echo '<table class="widefat striped" style="max-width:800px;margin-top:8px"><thead><tr><th>สมาชิก</th><th>อีเมล</th><th>แต้มคงเหลือ</th><th>ชุดที่หมดอายุเร็วสุด</th><th></th></tr></thead><tbody>';
	$rows = $query->get_results();
	if ( ! $rows ) {
		echo '<tr><td colspan="5">ยังไม่มีสมาชิกที่มีรายการแต้ม</td></tr>';
	}
	foreach ( $rows as $user ) {
		$balance = amouriq_points_balance( $user->ID );
		printf(
			'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">ดูรายการ / ปรับแต้ม</a></td></tr>',
			esc_html( $user->display_name ),
			esc_html( $user->user_email ),
			esc_html( number_format_i18n( $balance['points'] ) ),
			$balance['soonest_expiry'] ? esc_html( wp_date( 'j M Y', $balance['soonest_expiry'] ) ) : '—',
			esc_url( amouriq_points_admin_url( array( 'email' => $user->user_email ) ) )
		);
	}
	echo '</tbody></table>';

	$pages = (int) ceil( $total / 20 );
	if ( $pages > 1 ) {
		echo '<p>' . wp_kses_post(
			paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%', amouriq_points_admin_url( '' !== $search ? array( 'q' => $search ) : array() ) ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			)
		) . '</p>';
	}
}

/* ------------------------------------------------------------ earning rules */

/** The owner's Earning rules (rates, exclusions, multipliers, minimum order, cap), cleaned. */
function amouriq_points_earning() {
	return amouriq_points_core_earning_clean( get_option( 'amouriq_points_earning', array() ) );
}

/** Whether any rule or limit is in force, i.e. whether the flat rule alone no longer describes the points. */
function amouriq_points_earning_active( $earning ) {
	foreach ( $earning['rules'] as $rule ) {
		if ( $rule['enabled'] ) {
			return true;
		}
	}
	return $earning['min_order'] > 0 || $earning['max_per_order'] > 0;
}

/** The order's item lines as the points core wants them: what each cost and what a Product scope reads. */
function amouriq_points_order_lines( WC_Order $order ) {
	$lines = array();
	foreach ( $order->get_items( 'line_item' ) as $item ) {
		$product      = $item->get_product();
		$product_id   = (int) $item->get_product_id();
		$cats         = wp_get_post_terms( $product_id, 'product_cat' );
		$tags         = wp_get_post_terms( $product_id, 'product_tag' );
		$cats         = is_array( $cats ) ? $cats : array();
		$tags         = is_array( $tags ) ? $tags : array();
		$attributes   = array();
		if ( $product && $product->is_type( 'variation' ) ) {
			foreach ( (array) $product->get_attributes() as $taxonomy => $slug ) {
				if ( '' !== (string) $slug ) {
					$attributes[ $taxonomy ][] = (string) $slug;
				}
			}
		}
		$lines[] = array(
			'paid'         => (float) $item->get_total() + (float) $item->get_total_tax(),
			'product_id'   => $product_id,
			'variation_id' => (int) $item->get_variation_id(),
			'categories'   => array_merge( wp_list_pluck( $cats, 'slug' ), wp_list_pluck( $cats, 'term_id' ) ),
			'tags'         => array_merge( wp_list_pluck( $tags, 'slug' ), wp_list_pluck( $tags, 'term_id' ) ),
			'attributes'   => $attributes,
			'sku'          => $product ? (string) $product->get_sku() : '',
			'on_sale'      => $product ? $product->is_on_sale() : false,
		);
	}
	return $lines;
}

/**
 * Points this order earns under the settings and Earning rules right now. Remembers on the
 * order whether rules were in play (a refund then keeps the same share of points).
 */
function amouriq_points_earn_for_order( WC_Order $order ) {
	$earning  = amouriq_points_earning();
	$eligible = amouriq_points_order_eligible( $order );
	$ctx      = array(
		'now'           => current_time( 'Y-m-d H:i' ),
		'logged_in'     => (bool) $order->get_customer_id(),
		'email'         => $order->get_billing_email(),
		'cart_quantity' => array_sum( array_map( static function ( $item ) {
			return (int) $item->get_quantity();
		}, $order->get_items( 'line_item' ) ) ),
		'cart_subtotal' => $eligible,
	);
	$order->update_meta_data( '_amouriq_points_by_rules', amouriq_points_earning_active( $earning ) ? 'yes' : 'no' );
	return amouriq_points_core_earn_order( amouriq_points_order_lines( $order ), $eligible, $earning, amouriq_points_settings(), $ctx );
}

add_action( 'woocommerce_checkout_order_processed', 'amouriq_points_plan_earning', 20, 1 );

/** Fix the points an order will earn at the moment it is placed, so a later rule change or a rule that ends before it is completed does not change them. */
function amouriq_points_plan_earning( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! amouriq_points_enabled() || ! $order->get_customer_id() || '' !== (string) $order->get_meta( '_amouriq_points_planned', true ) ) {
		return;
	}
	$order->update_meta_data( '_amouriq_points_planned', amouriq_points_earn_for_order( $order ) );
	$order->save();
}

/* ------------------------------------------------ earning rules: admin screen */

/** Handle add / delete / toggle of an Earning rule and the order-level limits. Returns the notice. */
function amouriq_points_admin_earning_action( $action ) {
	$earning = amouriq_points_earning();
	// phpcs:disable WordPress.Security.NonceVerification -- checked in amouriq_points_admin_handle()
	$post   = wp_unslash( $_POST );
	$index  = isset( $post['index'] ) ? (int) $post['index'] : -1;
	$notice = '';

	if ( 'earn_limits' === $action ) {
		$earning['min_order']     = isset( $post['min_order'] ) ? $post['min_order'] : 0;
		$earning['max_per_order'] = isset( $post['max_per_order'] ) ? $post['max_per_order'] : 0;
		$notice                   = 'บันทึกเงื่อนไขการได้แต้มแล้ว';
	} elseif ( 'earn_delete' === $action && isset( $earning['rules'][ $index ] ) ) {
		array_splice( $earning['rules'], $index, 1 );
		$notice = 'ลบกติกาแล้ว';
	} elseif ( 'earn_toggle' === $action && isset( $earning['rules'][ $index ] ) ) {
		$earning['rules'][ $index ]['enabled'] = ! $earning['rules'][ $index ]['enabled'];
		$notice                                = $earning['rules'][ $index ]['enabled'] ? 'เปิดกติกาแล้ว' : 'ปิดกติกาแล้ว';
	} elseif ( 'earn_add' === $action ) {
		$kind  = isset( $post['kind'] ) ? sanitize_key( $post['kind'] ) : '';
		$scope = array( 'include' => array() );
		$cats  = isset( $post['categories'] ) ? array_map( 'sanitize_text_field', (array) $post['categories'] ) : array();
		$ids   = isset( $post['product_ids'] ) ? array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $post['product_ids'] ) ) ) : array();
		if ( $cats ) {
			$scope['include']['categories'] = array_values( $cats );
		}
		if ( $ids ) {
			$scope['include']['products'] = array_values( $ids );
		}
		$rule  = array(
			'kind'    => $kind,
			'enabled' => true,
			'label'   => isset( $post['label'] ) ? sanitize_text_field( $post['label'] ) : '',
			'scope'   => $scope,
			'baht'    => isset( $post['baht'] ) ? $post['baht'] : 0,
			'factor'  => isset( $post['factor'] ) ? $post['factor'] : 0,
			'from'    => isset( $post['from'] ) ? sanitize_text_field( $post['from'] ) : '',
			'to'      => isset( $post['to'] ) ? sanitize_text_field( $post['to'] ) : '',
		);
		$check = amouriq_points_core_earning_clean( array( 'rules' => array( $rule ) ) );
		if ( ! $check['rules'] ) {
			$notice = 'ไม่ได้เพิ่มกติกา: ตรวจค่าที่กรอก (อัตราต้องมากกว่า 0 บาทต่อแต้ม, ตัวคูณต้องมากกว่า 1 ถึง 10)';
		} elseif ( in_array( $kind, array( 'rate', 'exclude' ), true ) && ! $scope['include'] ) {
			$notice = 'ไม่ได้เพิ่มกติกา: เลือกหมวดหรือใส่รหัสสินค้าอย่างน้อยหนึ่งอย่าง';
		} else {
			$earning['rules'][] = $check['rules'][0];
			$notice             = 'เพิ่มกติกาแล้ว';
		}
	}
	// phpcs:enable

	update_option( 'amouriq_points_earning', amouriq_points_core_earning_clean( $earning ), false );
	return $notice;
}

function amouriq_points_rule_summary( $rule ) {
	$scope = '';
	if ( ! empty( $rule['scope']['include']['categories'] ) ) {
		$names = array_map(
			static function ( $c ) {
				$term = is_numeric( $c ) ? get_term( (int) $c, 'product_cat' ) : get_term_by( 'slug', $c, 'product_cat' );
				return $term && ! is_wp_error( $term ) ? $term->name : $c;
			},
			$rule['scope']['include']['categories']
		);
		$scope .= ' หมวด ' . implode( ', ', $names );
	}
	if ( ! empty( $rule['scope']['include']['products'] ) ) {
		$scope .= ' สินค้ารหัส ' . implode( ', ', $rule['scope']['include']['products'] );
	}
	$scope = '' === $scope ? ' ทุกสินค้า' : $scope;
	if ( 'rate' === $rule['kind'] ) {
		return sprintf( 'อัตราพิเศษ: ซื้อ %s บาท ได้ 1 แต้ม —%s', amouriq_bogo_member_number( $rule['baht'] ), $scope );
	}
	if ( 'exclude' === $rule['kind'] ) {
		return 'ไม่ให้แต้ม —' . $scope;
	}
	$dates = ( $rule['from'] || $rule['to'] ) ? sprintf( ' (%s ถึง %s)', $rule['from'] ? $rule['from'] : '…', $rule['to'] ? $rule['to'] : '…' ) : ' (ตลอดเวลา)';
	return sprintf( 'คูณแต้ม x%s%s%s', amouriq_bogo_member_number( $rule['factor'] ), $dates, ! empty( $rule['conditions'] ) ? ' + มีเงื่อนไข' : '' );
}

function amouriq_points_admin_earning_section() {
	$earning = amouriq_points_earning();
	echo '<h2>กติกาการได้แต้ม</h2><p>กติกาพื้นฐานคือซื้อครบตามที่ตั้งไว้ด้านบนได้ 1 แต้ม กติกาด้านล่างเป็นการปรับเพิ่ม: อัตราพิเศษเฉพาะหมวดหรือสินค้า, สินค้าที่ไม่ให้แต้ม และตัวคูณช่วงโปรโมชัน ใช้ตัวคูณที่มากที่สุดเพียงตัวเดียว (ไม่คูณซ้อน) แต้มจะถูกคำนวณและล็อกไว้ตอนลูกค้าสั่งซื้อ</p>';

	echo '<table class="widefat striped" style="max-width:800px"><thead><tr><th>กติกา</th><th>สถานะ</th><th></th></tr></thead><tbody>';
	if ( ! $earning['rules'] ) {
		echo '<tr><td colspan="3">ยังไม่มีกติกาเพิ่มเติม (ใช้กติกาพื้นฐานอย่างเดียว)</td></tr>';
	}
	foreach ( $earning['rules'] as $i => $rule ) {
		echo '<tr><td>' . ( '' !== $rule['label'] ? '<strong>' . esc_html( $rule['label'] ) . '</strong><br>' : '' ) . esc_html( amouriq_points_rule_summary( $rule ) ) . '</td>';
		echo '<td>' . ( $rule['enabled'] ? 'เปิดอยู่' : 'ปิดอยู่' ) . '</td><td>';
		$hidden = '<input type="hidden" name="index" value="' . (int) $i . '">';
		amouriq_points_admin_form( 'earn_toggle', $hidden, $rule['enabled'] ? 'ปิด' : 'เปิด', 'button' );
		amouriq_points_admin_form( 'earn_delete', $hidden, 'ลบ', 'button button-link-delete', 'ลบกติกานี้?' );
		echo '</td></tr>';
	}
	echo '</tbody></table>';

	$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	$opts = '';
	foreach ( is_array( $cats ) ? $cats : array() as $cat ) {
		$opts .= '<option value="' . esc_attr( $cat->slug ) . '">' . esc_html( $cat->name ) . '</option>';
	}
	$inner = '<table class="form-table"><tr><th>ชนิด</th><td><select name="kind"><option value="rate">อัตราพิเศษ (ซื้อกี่บาทได้ 1 แต้ม)</option><option value="exclude">ไม่ให้แต้ม</option><option value="multiplier">ตัวคูณแต้ม</option></select></td></tr>'
		. '<tr><th>ชื่อกติกา</th><td><input type="text" name="label" class="regular-text" placeholder="เช่น สบู่ได้แต้มเพิ่ม"></td></tr>'
		. '<tr data-for="rate"><th>ซื้อกี่บาทได้ 1 แต้ม</th><td><input type="number" step="any" min="0" name="baht" style="width:8em"> บาท (ใช้กับอัตราพิเศษ)</td></tr>'
		. '<tr data-for="rate exclude"><th>หมวดสินค้า</th><td><select name="categories[]" multiple size="5" style="min-width:16em">' . $opts . '</select><br><small>ใช้กับอัตราพิเศษและไม่ให้แต้ม (กด Ctrl เพื่อเลือกหลายหมวด)</small></td></tr>'
		. '<tr data-for="rate exclude"><th>รหัสสินค้า</th><td><input type="text" name="product_ids" class="regular-text" placeholder="เช่น 2278, 2285"><br><small>คั่นด้วยจุลภาค ถ้าเลือกทั้งหมวดและรหัส สินค้าต้องอยู่ในทั้งสองอย่าง</small></td></tr>'
		. '<tr data-for="multiplier"><th>ตัวคูณ</th><td>x <input type="number" step="any" min="1" max="10" name="factor" style="width:6em"> (ใช้กับตัวคูณแต้ม ระหว่าง 1 ถึง 10)</td></tr>'
		. '<tr data-for="multiplier"><th>ช่วงวันที่</th><td><input type="date" name="from"> ถึง <input type="date" name="to"> <small>เว้นว่าง = ไม่จำกัด (ใช้กับตัวคูณแต้ม)</small></td></tr></table>';
	echo '<h3>เพิ่มกติกา</h3>';
	amouriq_points_admin_form( 'earn_add', $inner, 'เพิ่มกติกา' );

	$inner = '<p><label>ยอดสั่งซื้อขั้นต่ำที่จะได้แต้ม <input type="number" step="any" min="0" name="min_order" value="' . esc_attr( $earning['min_order'] ) . '" style="width:8em"> บาท</label> '
		. '<label>แต้มสูงสุดต่อออเดอร์ <input type="number" min="0" name="max_per_order" value="' . esc_attr( $earning['max_per_order'] ) . '" style="width:7em"> แต้ม</label> <small>(0 = ไม่จำกัด)</small></p>';
	echo '<h3>ข้อจำกัดต่อออเดอร์</h3>';
	amouriq_points_admin_form( 'earn_limits', $inner, 'บันทึก' );
}

/* ------------------------------------------------------------------ bonuses */

/** The owner's one-time bonuses (sign-up, first order, verified review), each off until switched on. */
function amouriq_points_bonus() {
	return amouriq_points_core_bonus_clean( get_option( 'amouriq_points_bonus', array() ) );
}

add_action( 'user_register', 'amouriq_points_signup_bonus', 20, 1 );

/** Points for a new customer account, once per account. */
function amouriq_points_signup_bonus( $user_id ) {
	$bonus = amouriq_points_bonus();
	if ( ! amouriq_points_enabled() || ! $bonus['signup']['enabled'] || $bonus['signup']['points'] <= 0 ) {
		return;
	}
	$user = get_userdata( $user_id );
	if ( ! $user || ! in_array( 'customer', (array) $user->roles, true ) || get_user_meta( $user_id, '_amouriq_points_signup_bonus', true ) ) {
		return;
	}
	update_user_meta( $user_id, '_amouriq_points_signup_bonus', 1 );
	amouriq_points_record( $user_id, $bonus['signup']['points'], 'bonus_signup' );
}

add_action( 'woocommerce_order_status_completed', 'amouriq_points_first_order_bonus', 20, 1 );

/**
 * Points when a customer's first order is Completed: no other Completed order of theirs
 * exists, and the bonus was never given to them before (a refunded first order does not
 * make the next one "first" again).
 */
function amouriq_points_first_order_bonus( $order_id ) {
	$bonus = amouriq_points_bonus();
	$order = wc_get_order( $order_id );
	if ( ! $order || ! amouriq_points_enabled() || ! $bonus['first_order']['enabled'] || $bonus['first_order']['points'] <= 0 || ! $order->get_customer_id() ) {
		return;
	}
	$customer = $order->get_customer_id();
	if ( '' !== (string) $order->get_meta( '_amouriq_points_bonus_first', true ) || get_user_meta( $customer, '_amouriq_points_first_bonus_given', true ) ) {
		return;
	}
	$earlier = wc_get_orders( array( 'limit' => 1, 'return' => 'ids', 'customer' => $customer, 'status' => array( 'completed' ), 'exclude' => array( $order_id ) ) );
	if ( $earlier ) {
		return;
	}
	update_user_meta( $customer, '_amouriq_points_first_bonus_given', 1 );
	$order->update_meta_data( '_amouriq_points_bonus_first', (int) $bonus['first_order']['points'] );
	$order->save();
	amouriq_points_record( $customer, $bonus['first_order']['points'], 'bonus_first', $order_id );
	$order->add_order_note( sprintf( 'ลูกค้าได้รับโบนัสออเดอร์แรก %d แต้ม', $bonus['first_order']['points'] ) );
}

add_action(
	'comment_post',
	static function ( $comment_id, $approved ) {
		if ( 1 === (int) $approved ) {
			amouriq_points_review_bonus( get_comment( $comment_id ) );
		}
	},
	20,
	2
);

add_action(
	'transition_comment_status',
	static function ( $new, $old, $comment ) {
		if ( 'approved' === $new && 'approved' !== $old ) {
			amouriq_points_review_bonus( $comment );
		}
	},
	20,
	3
);

/** Points for an approved product review by a customer who bought the product; once per review and per product. */
function amouriq_points_review_bonus( $comment ) {
	if ( ! $comment || ! amouriq_points_enabled() || 'review' !== $comment->comment_type || '1' !== (string) $comment->comment_approved || ! $comment->user_id ) {
		return;
	}
	if ( get_comment_meta( $comment->comment_ID, '_amouriq_points_review_bonus', true ) ) {
		return;
	}
	$product_id = (int) $comment->comment_post_ID;
	if ( 'product' !== get_post_type( $product_id ) || ! wc_customer_bought_product( $comment->comment_author_email, (int) $comment->user_id, $product_id ) ) {
		return;
	}
	$points = amouriq_points_core_review_bonus( amouriq_points_bonus(), amouriq_points_ledger( $comment->user_id ), $product_id, time() );
	if ( $points <= 0 ) {
		return;
	}
	update_comment_meta( $comment->comment_ID, '_amouriq_points_review_bonus', $points );
	amouriq_points_record( (int) $comment->user_id, $points, 'bonus_review', 0, 'รีวิวสินค้า: ' . get_the_title( $product_id ), 0, $product_id );
}

/** Save the bonus form. Returns the notice. */
function amouriq_points_admin_bonus_save() {
	// phpcs:disable WordPress.Security.NonceVerification -- checked in amouriq_points_admin_handle()
	$post = wp_unslash( $_POST );
	$raw  = array();
	foreach ( array( 'signup', 'first_order', 'review' ) as $kind ) {
		$raw[ $kind ] = array(
			'enabled'  => ! empty( $post[ 'bonus_' . $kind . '_on' ] ),
			'points'   => isset( $post[ 'bonus_' . $kind . '_points' ] ) ? $post[ 'bonus_' . $kind . '_points' ] : '',
			'per_year' => isset( $post['bonus_review_per_year'] ) ? $post['bonus_review_per_year'] : '',
		);
	}
	// phpcs:enable
	update_option( 'amouriq_points_bonus', amouriq_points_core_bonus_clean( $raw ), false );
	return 'บันทึกโบนัสแต้มแล้ว';
}

function amouriq_points_admin_bonus_section() {
	$b = amouriq_points_bonus();
	echo '<h2>โบนัสแต้ม</h2><p>โบนัสแต่ละอย่างเปิดหรือปิดแยกกัน (ปิดอยู่จนกว่าจะติ๊ก) และให้ครั้งเดียวตามเงื่อนไข ปิดโบนัสแล้วแต้มที่ให้ไปแล้วไม่ถูกเอาคืน แต้มโบนัสหมดอายุเหมือนแต้มทั่วไป</p>';
	$row = static function ( $kind, $label, $extra = '' ) use ( $b ) {
		return '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="bonus_' . esc_attr( $kind ) . '_on" value="1" ' . checked( $b[ $kind ]['enabled'], true, false ) . '> เปิดใช้</label> '
			. 'ให้ <input type="number" min="0" name="bonus_' . esc_attr( $kind ) . '_points" value="' . esc_attr( $b[ $kind ]['points'] ) . '" style="width:6em"> แต้ม' . $extra . '</td></tr>';
	};
	$inner = '<table class="form-table">'
		. $row( 'signup', 'สมัครสมาชิก' )
		. $row( 'first_order', 'ออเดอร์แรกที่เสร็จสมบูรณ์', ' <small>(เมื่อออเดอร์แรกเปลี่ยนเป็น "เสร็จสมบูรณ์" ให้ครั้งเดียวต่อสมาชิก; ถ้าออเดอร์นั้นถูกยกเลิกหรือคืนเงินจะหักโบนัสคืน)</small>' )
		. $row( 'review', 'รีวิวสินค้าที่ซื้อแล้ว', ' ต่อสินค้าครั้งเดียว สูงสุด <input type="number" min="0" name="bonus_review_per_year" value="' . esc_attr( $b['review']['per_year'] ) . '" style="width:5em"> ครั้งใน 12 เดือน (0 = ไม่จำกัด) <small>(ให้เมื่อรีวิวได้รับอนุมัติ และผู้รีวิวเคยซื้อสินค้านั้น)</small>' )
		. '</table>';
	amouriq_points_admin_form( 'bonus_save', $inner, 'บันทึกโบนัส' );
}

/* ------------------------------------------------------------------- report */

/** Every member's ledger (user ID => ledger) for the report and the CSV. Fine at this store's size; no table of its own. */
function amouriq_points_all_ledgers() {
	$query = new WP_User_Query(
		array(
			'number'       => -1,
			'fields'       => 'ID',
			'meta_key'     => 'amouriq_points_ledger', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare' => 'EXISTS',
		)
	);
	$ledgers = array();
	foreach ( $query->get_results() as $user_id ) {
		$ledger = amouriq_points_ledger( $user_id );
		if ( $ledger ) {
			$ledgers[ (int) $user_id ] = $ledger;
		}
	}
	return $ledgers;
}

/** The site's UTC offset in seconds (Thailand has no daylight saving). */
function amouriq_points_site_offset() {
	return (int) round( (float) get_option( 'gmt_offset', 0 ) * 3600 );
}

function amouriq_points_admin_report_section() {
	$ledgers = amouriq_points_all_ledgers();
	$report  = amouriq_points_core_report( $ledgers, time(), amouriq_points_site_offset() );
	$redeem  = amouriq_points_settings()['redeem_baht'];
	$baht    = static function ( $points ) use ( $redeem ) {
		return '฿' . number_format_i18n( $points * $redeem, 2 );
	};

	echo '<h2>รายงานแต้มสะสม</h2>';
	printf(
		'<p><strong>แต้มคงเหลือของสมาชิกทั้งหมด:</strong> %s แต้ม (มูลค่า %s ตามอัตราแลก) · <strong>จะหมดอายุภายใน 30 วัน:</strong> %s แต้ม · <strong>สมาชิกที่มีรายการแต้ม:</strong> %d คน</p>',
		esc_html( number_format_i18n( $report['outstanding'] ) ),
		esc_html( $baht( $report['outstanding'] ) ),
		esc_html( number_format_i18n( $report['expiring_soon'] ) ),
		count( $ledgers )
	);

	echo '<table class="widefat striped" style="max-width:800px"><thead><tr><th>เดือน</th><th>ได้รับ</th><th>ใช้</th><th>ร้านปรับ</th><th>หักคืน/คืนแต้ม</th><th>หมดอายุ</th></tr></thead><tbody>';
	$months = array_reverse( $report['months'], true );
	if ( ! $months ) {
		echo '<tr><td colspan="6">ยังไม่มีรายการแต้ม</td></tr>';
	}
	foreach ( array_slice( $months, 0, 12, true ) as $key => $m ) {
		printf(
			'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
			esc_html( $key ),
			esc_html( number_format_i18n( $m['earned'] ) ),
			esc_html( number_format_i18n( $m['used'] ) ),
			esc_html( ( $m['adjusted'] > 0 ? '+' : '' ) . number_format_i18n( $m['adjusted'] ) ),
			esc_html( number_format_i18n( $m['taken'] ) ),
			esc_html( number_format_i18n( $m['expired'] ) )
		);
	}
	echo '</tbody></table><p class="description">แสดง 12 เดือนล่าสุด "หักคืน/คืนแต้ม" คือแต้มที่ถูกหักเพราะคืนเงินหรือยกเลิก (ลบ) รวมกับแต้มที่คืนให้เมื่อออเดอร์ถูกยกเลิก (บวก)</p>';

	if ( $report['top_earners'] ) {
		echo '<h3>สมาชิกที่ได้รับแต้มสะสมสูงสุด</h3><table class="widefat striped" style="max-width:600px"><thead><tr><th>สมาชิก</th><th>แต้มที่ได้รับตลอดเวลา</th></tr></thead><tbody>';
		foreach ( $report['top_earners'] as $user_id => $points ) {
			$user = get_userdata( $user_id );
			printf(
				'<tr><td>%s</td><td>%s</td></tr>',
				$user ? '<a href="' . esc_url( amouriq_points_admin_url( array( 'email' => $user->user_email ) ) ) . '">' . esc_html( $user->user_email ) . '</a>' : esc_html( '#' . $user_id ),
				esc_html( number_format_i18n( $points ) )
			);
		}
		echo '</tbody></table>';
	}

	echo '<h3>ส่งออกรายการแต้ม (CSV)</h3><form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
	echo '<input type="hidden" name="page" value="amouriq-points"><input type="hidden" name="amouriq_points_csv" value="1">';
	wp_nonce_field( 'amouriq_points_csv', '_wpnonce', false );
	echo '<label>ตั้งแต่ <input type="date" name="from"></label> <label>ถึง <input type="date" name="to"></label> ';
	echo '<button class="button">ดาวน์โหลด CSV</button> <span class="description">เว้นว่าง = ทั้งหมด ไฟล์เปิดใน Excel ได้ (UTF-8) มีหนึ่งแถวต่อหนึ่งรายการ</span></form>';
}

add_action( 'admin_init', 'amouriq_points_csv_export' );

/** Stream the ledger as CSV for the dates asked (site time, both ends included). Nothing is kept on the server. */
function amouriq_points_csv_export() {
	// phpcs:disable WordPress.Security.NonceVerification -- verified below
	if ( empty( $_GET['amouriq_points_csv'] ) || empty( $_GET['page'] ) || 'amouriq-points' !== $_GET['page'] ) {
		return;
	}
	// phpcs:enable
	if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'amouriq_points_csv' ) ) {
		wp_die( 'ไม่มีสิทธิ์ส่งออกข้อมูลนี้', '', array( 'response' => 403 ) );
	}

	$offset = amouriq_points_site_offset();
	$from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
	$to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
	$start  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ? strtotime( $from . ' 00:00:00 UTC' ) - $offset : 0;
	$end    = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ? strtotime( $to . ' 23:59:59 UTC' ) - $offset : PHP_INT_MAX;

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="amouriq-points-' . gmdate( 'Ymd', time() + $offset ) . '.csv"' );
	echo "\xEF\xBB\xBF"; // lets Excel read the Thai text as UTF-8
	echo amouriq_points_core_csv_line( array( 'วันที่', 'รหัสสมาชิก', 'อีเมล', 'ชื่อ', 'รายการ', 'ออเดอร์', 'แต้ม', 'หมดอายุ', 'หมายเหตุ', 'ปรับโดย' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	$names = amouriq_points_core_type_names();
	foreach ( amouriq_points_all_ledgers() as $user_id => $ledger ) {
		$user = get_userdata( $user_id );
		foreach ( $ledger as $row ) {
			if ( $row['time'] < $start || $row['time'] > $end ) {
				continue;
			}
			$by = ! empty( $row['by'] ) ? get_userdata( $row['by'] ) : false;
			echo amouriq_points_core_csv_line( // phpcs:ignore WordPress.Security.EscapeOutput
				array(
					gmdate( 'Y-m-d H:i', $row['time'] + $offset ),
					$user_id,
					$user ? $user->user_email : '',
					$user ? $user->display_name : '',
					isset( $names[ $row['type'] ] ) ? $names[ $row['type'] ] : $row['type'],
					! empty( $row['order_id'] ) ? $row['order_id'] : '',
					$row['points'],
					! empty( $row['expires'] ) ? gmdate( 'Y-m-d', $row['expires'] + $offset ) : '',
					isset( $row['note'] ) ? $row['note'] : '',
					$by ? $by->display_name : '',
				)
			);
		}
	}
	exit;
}

/* ------------------------------------------------------------------ rewards */

/** The owner's Rewards catalogue (cleaned). */
function amouriq_points_rewards() {
	return amouriq_points_core_rewards_clean( get_option( 'amouriq_points_rewards', array() ) );
}

/** The customer's redemptions, newest last: time, reward_id, label, cost, coupon_id, code. */
function amouriq_points_redemptions( $user_id ) {
	$list = get_user_meta( (int) $user_id, 'amouriq_points_redemptions', true );
	return is_array( $list ) ? $list : array();
}

/** "฿100 off" or "10% off" for a reward, in Thai. */
function amouriq_points_reward_text( $reward ) {
	if ( isset( $reward['kind'] ) && 'gift' === $reward['kind'] ) {
		$product = wc_get_product( $reward['product_id'] );
		return 'ของแถมฟรี: ' . ( $product ? $product->get_name() : 'สินค้า #' . (int) $reward['product_id'] ) . ' (เลือกในตะกร้า) · ใช้ได้ ' . (int) $reward['days_valid'] . ' วัน · ใช้ได้ 1 ครั้ง';
	}
	$what = 'percent' === $reward['type'] ? amouriq_bogo_member_number( $reward['value'] ) . '%' : '฿' . amouriq_bogo_member_number( $reward['value'] );
	$text = 'ส่วนลด ' . $what;
	if ( $reward['min_spend'] > 0 ) {
		$text .= ' เมื่อซื้อครบ ฿' . amouriq_bogo_member_number( $reward['min_spend'] );
	}
	return $text . ' · ใช้ได้ ' . (int) $reward['days_valid'] . ' วัน · ใช้ได้ 1 ครั้ง';
}

/**
 * Redeem a Reward for a member: take the points and create a single-use coupon only they can
 * use. All or nothing: the coupon is made first and the points are taken only when it exists.
 * `$token` is the form's one-time token so a double click or a second tab cannot redeem twice.
 * Returns array( 'ok' => bool, 'message' => Thai text, 'code' => coupon code when ok ).
 */
function amouriq_points_redeem( $user_id, $reward_id, $token ) {
	$fail = static function ( $message ) {
		return array( 'ok' => false, 'message' => $message, 'code' => '' );
	};
	$user = get_userdata( $user_id );
	if ( ! $user || ! amouriq_points_enabled() ) {
		return $fail( 'ระบบแต้มสะสมยังไม่เปิดใช้งาน' );
	}
	$reward = null;
	foreach ( amouriq_points_rewards() as $candidate ) {
		if ( $candidate['id'] === $reward_id ) {
			$reward = $candidate;
		}
	}
	if ( ! $reward ) {
		return $fail( 'ไม่พบของรางวัลนี้' );
	}
	if ( 'gift' === $reward['kind'] ) {
		$gift_product = wc_get_product( $reward['product_id'] );
		if ( ! $gift_product || ! $gift_product->is_purchasable() || ! $gift_product->is_in_stock() ) {
			return $fail( 'ของแถมนี้หมดสต็อกชั่วคราว ยังไม่หักแต้ม กรุณาลองใหม่ภายหลัง' );
		}
	}

	// One redemption at a time per member, and one per form.
	$lock = get_user_meta( $user_id, '_amouriq_points_redeem_lock', true );
	if ( $lock && (int) $lock > time() - 30 ) {
		return $fail( 'กำลังดำเนินการแลกแต้มอยู่ กรุณารอสักครู่' );
	}
	update_user_meta( $user_id, '_amouriq_points_redeem_lock', time() );
	$release = static function () use ( $user_id ) {
		delete_user_meta( $user_id, '_amouriq_points_redeem_lock' );
	};
	$token_key = 'amouriq_points_tok_' . md5( $user_id . '|' . $token );
	if ( '' === (string) $token || get_transient( $token_key ) ) {
		$release();
		return $fail( 'รายการนี้ถูกดำเนินการไปแล้ว กรุณาดูคูปองในรายการด้านล่าง' );
	}

	$problem = amouriq_points_core_check_redeem( $reward, amouriq_points_balance( $user_id )['points'] );
	if ( $problem ) {
		$release();
		return $fail( $problem );
	}

	$code = '';
	do {
		$code = 'PTS-' . strtoupper( wp_generate_password( 8, false, false ) );
	} while ( wc_get_coupon_id_by_code( $code ) );
	$spec   = amouriq_points_core_coupon_spec( $reward, time(), $user->user_email, $code );
	$coupon = new WC_Coupon();
	$coupon->set_code( $spec['code'] );
	$coupon->set_discount_type( $spec['discount_type'] );
	$coupon->set_amount( $spec['amount'] );
	$coupon->set_minimum_amount( $spec['minimum_amount'] );
	$coupon->set_usage_limit( $spec['usage_limit'] );
	$coupon->set_usage_limit_per_user( $spec['usage_limit_per_user'] );
	$coupon->set_email_restrictions( $spec['email_restrictions'] );
	$coupon->set_date_expires( $spec['date_expires'] );
	$coupon->set_free_shipping( $spec['free_shipping'] );
	$coupon->set_description( sprintf( 'แลกด้วยแต้มสะสม: %s (สมาชิก #%d)', $reward['label'], $user_id ) );
	$coupon_id = $coupon->save();
	if ( ! $coupon_id ) {
		$release();
		return $fail( 'สร้างคูปองไม่สำเร็จ ไม่ได้หักแต้ม กรุณาลองใหม่' );
	}
	update_post_meta( $coupon_id, 'amouriq_points_user', $user_id );
	update_post_meta( $coupon_id, 'amouriq_points_reward', $reward['id'] );
	if ( 'gift' === $reward['kind'] ) {
		update_post_meta( $coupon_id, 'amouriq_points_gift_product', (int) $reward['product_id'] );
		update_post_meta( $coupon_id, 'amouriq_points_gift_label', $reward['label'] );
	}

	amouriq_points_record( $user_id, -$reward['cost'], 'redeem', 0, 'แลก: ' . $reward['label'] . ' (' . $code . ')', 0, $coupon_id );
	$list   = amouriq_points_redemptions( $user_id );
	$list[] = array( 'time' => time(), 'reward_id' => $reward['id'], 'label' => $reward['label'], 'cost' => $reward['cost'], 'coupon_id' => $coupon_id, 'code' => $code );
	update_user_meta( $user_id, 'amouriq_points_redemptions', $list );
	set_transient( $token_key, 1, HOUR_IN_SECONDS );
	$release();

	return array( 'ok' => true, 'message' => sprintf( 'แลกสำเร็จ รหัสคูปองของคุณคือ %s (ใช้ได้ถึงวันที่ %s)', $code, wp_date( 'j M Y', $spec['date_expires'] ) ), 'code' => $code );
}

add_action( 'wp_loaded', 'amouriq_points_handle_redeem' );

function amouriq_points_handle_redeem() {
	if ( empty( $_POST['amouriq_points_redeem'] ) || ! function_exists( 'wc_add_notice' ) || ! is_user_logged_in() ) {
		return;
	}
	if ( ! isset( $_POST['amouriq_points_redeem_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['amouriq_points_redeem_nonce'] ) ), 'amouriq_points_redeem' ) ) {
		return;
	}
	$result = amouriq_points_redeem(
		get_current_user_id(),
		sanitize_text_field( wp_unslash( $_POST['amouriq_points_redeem'] ) ),
		isset( $_POST['amouriq_points_token'] ) ? sanitize_text_field( wp_unslash( $_POST['amouriq_points_token'] ) ) : ''
	);
	wc_add_notice( $result['message'], $result['ok'] ? 'success' : 'error' );
	wp_safe_redirect( wc_get_account_endpoint_url( 'points' ) );
	exit;
}

/** My Account: the Rewards the member can redeem and the coupons they already redeemed. */
function amouriq_points_account_rewards( $user_id, $balance ) {
	$rewards = array_filter(
		amouriq_points_rewards(),
		static function ( $reward ) {
			return $reward['enabled'];
		}
	);
	if ( $rewards ) {
		echo '<h3 class="amq-pts-h">แลกของรางวัล</h3><div class="amq-pts-rewards">';
		foreach ( $rewards as $reward ) {
			$can = $balance >= $reward['cost'];
			$pct = min( 100, max( 0, (int) floor( 100 * $balance / $reward['cost'] ) ) );
			echo '<form method="post" class="amq-pts-reward' . ( $can ? ' is-ready' : '' ) . '" onsubmit="return confirm(' . esc_attr( wp_json_encode( amouriq_points_core_redeem_confirm( $reward ) ) ) . ');">';
			wp_nonce_field( 'amouriq_points_redeem', 'amouriq_points_redeem_nonce' );
			echo '<input type="hidden" name="amouriq_points_redeem" value="' . esc_attr( $reward['id'] ) . '">';
			echo '<input type="hidden" name="amouriq_points_token" value="' . esc_attr( wp_generate_password( 16, false, false ) ) . '">';
			echo '<div class="amq-pts-reward-top"><strong>' . esc_html( $reward['label'] ) . '</strong><span class="amq-pts-cost">' . esc_html( number_format_i18n( $reward['cost'] ) ) . ' แต้ม</span></div>';
			echo '<small>' . esc_html( amouriq_points_reward_text( $reward ) ) . '</small>';
			echo '<div class="amq-pts-bar" aria-hidden="true"><i style="width:' . (int) $pct . '%"></i></div>';
			if ( $can ) {
				echo '<button type="submit" class="amq-pts-btn">แลกเลย</button>';
			} else {
				echo '<button type="button" class="amq-pts-btn" disabled>ขาดอีก ' . esc_html( number_format_i18n( $reward['cost'] - $balance ) ) . ' แต้ม</button>';
			}
			echo '</form>';
		}
		echo '</div>';
	}

	$redeemed = array_reverse( amouriq_points_redemptions( $user_id ) );
	if ( $redeemed ) {
		echo '<h3 class="amq-pts-h">คูปองที่แลกแล้ว</h3><ul class="amq-pts-coupons">';
		foreach ( array_slice( $redeemed, 0, 20 ) as $row ) {
			$coupon  = new WC_Coupon( (int) $row['coupon_id'] );
			$expires = $coupon->get_date_expires();
			$usable  = true;
			if ( $coupon->get_usage_count() > 0 ) {
				$status = 'ใช้แล้ว';
				$usable = false;
			} elseif ( $expires && $expires->getTimestamp() < time() ) {
				$status = 'หมดอายุแล้ว';
				$usable = false;
			} else {
				$status = 'ใช้ได้' . ( $expires ? ' ถึง ' . wp_date( 'j M Y', $expires->getTimestamp() ) : '' );
			}
			printf(
				'<li><span class="grow"><strong>%s</strong><small>แลกเมื่อ %s</small></span><code class="amq-pts-code">%s</code><span class="amq-pts-badge%s">%s</span></li>',
				esc_html( $row['label'] ),
				esc_html( wp_date( 'j M Y', $row['time'] ) ),
				esc_html( $row['code'] ),
				$usable ? '' : ' is-off',
				esc_html( $status )
			);
		}
		echo '</ul>';
	}
}

/** Admin: add / toggle / delete a Reward. Returns the notice. */
function amouriq_points_admin_reward_action( $action ) {
	$rewards = amouriq_points_rewards();
	// phpcs:disable WordPress.Security.NonceVerification -- checked in amouriq_points_admin_handle()
	$post   = wp_unslash( $_POST );
	$index  = isset( $post['index'] ) ? (int) $post['index'] : -1;
	$notice = '';
	if ( 'reward_delete' === $action && isset( $rewards[ $index ] ) ) {
		array_splice( $rewards, $index, 1 );
		$notice = 'ลบของรางวัลแล้ว (คูปองที่ลูกค้าแลกไปแล้วยังใช้ได้ตามเดิม)';
	} elseif ( 'reward_toggle' === $action && isset( $rewards[ $index ] ) ) {
		$rewards[ $index ]['enabled'] = ! $rewards[ $index ]['enabled'];
		$notice                       = $rewards[ $index ]['enabled'] ? 'เปิดของรางวัลแล้ว' : 'ปิดของรางวัลแล้ว';
	} elseif ( 'reward_add' === $action ) {
		$new   = array(
			'label'      => isset( $post['label'] ) ? sanitize_text_field( $post['label'] ) : '',
			'cost'       => isset( $post['cost'] ) ? $post['cost'] : 0,
			'kind'       => ( isset( $post['kind'] ) && 'gift' === $post['kind'] ) ? 'gift' : 'coupon',
			'product_id' => isset( $post['product_id'] ) ? $post['product_id'] : 0,
			'type'       => isset( $post['type'] ) && 'percent' === $post['type'] ? 'percent' : 'fixed',
			'value'      => isset( $post['value'] ) ? $post['value'] : 0,
			'min_spend'  => isset( $post['min_spend'] ) ? $post['min_spend'] : 0,
			'days_valid' => isset( $post['days_valid'] ) ? $post['days_valid'] : 30,
			'enabled'    => true,
		);
		$check = amouriq_points_core_rewards_clean( array( $new ) );
		if ( 'gift' === $new['kind'] && ! wc_get_product( (int) $new['product_id'] ) ) {
			$notice = 'ไม่ได้เพิ่มของรางวัล: ไม่พบสินค้ารหัสนี้ (ดูรหัสได้ในหน้ารายการสินค้า รหัสของตัวเลือกก็ใช้ได้)';
		} elseif ( ! $check ) {
			$notice = 'ไม่ได้เพิ่มของรางวัล: ใส่ชื่อ แต้มที่ใช้ (มากกว่า 0) และสำหรับคูปองใส่มูลค่าส่วนลด (มากกว่า 0 ถ้าเป็น % ต้องไม่เกิน 100) หรือสำหรับของแถมใส่รหัสสินค้า';
		} else {
			$rewards[] = $check[0];
			$notice    = 'เพิ่มของรางวัลแล้ว';
		}
	}
	// phpcs:enable
	update_option( 'amouriq_points_rewards', amouriq_points_core_rewards_clean( $rewards ), false );
	return $notice;
}

function amouriq_points_admin_rewards_section() {
	$rewards = amouriq_points_rewards();
	echo '<h2>ของรางวัล (แลกแต้มเป็นคูปอง)</h2><p>ลูกค้าแลกในหน้าบัญชีของฉัน > แต้มสะสม แต้มถูกหักทันที ระบบสร้างคูปองใช้ครั้งเดียวที่ใช้ได้เฉพาะอีเมลของลูกค้าคนนั้น ถ้าคูปองหมดอายุโดยไม่ได้ใช้ ไม่คืนแต้ม (ข้อความนี้แสดงบนปุ่มยืนยัน) คูปองที่ได้เป็นคูปอง WooCommerce ธรรมดา จึงเทียบกับสิทธิพิเศษสมาชิกตามกติกา "ส่วนลดที่มากกว่าชนะ"</p>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>ของรางวัล</th><th>ใช้แต้ม</th><th>รายละเอียด</th><th>สถานะ</th><th></th></tr></thead><tbody>';
	if ( ! $rewards ) {
		echo '<tr><td colspan="5">ยังไม่มีของรางวัล</td></tr>';
	}
	foreach ( $rewards as $i => $reward ) {
		echo '<tr><td><strong>' . esc_html( $reward['label'] ) . '</strong></td><td>' . esc_html( number_format_i18n( $reward['cost'] ) ) . '</td><td>' . esc_html( amouriq_points_reward_text( $reward ) ) . '</td><td>' . ( $reward['enabled'] ? 'เปิดอยู่' : 'ปิดอยู่' ) . '</td><td>';
		$hidden = '<input type="hidden" name="index" value="' . (int) $i . '">';
		amouriq_points_admin_form( 'reward_toggle', $hidden, $reward['enabled'] ? 'ปิด' : 'เปิด', 'button' );
		amouriq_points_admin_form( 'reward_delete', $hidden, 'ลบ', 'button button-link-delete', 'ลบของรางวัลนี้?' );
		echo '</td></tr>';
	}
	echo '</tbody></table>';

	$inner = '<table class="form-table"><tr><th>ชนิด</th><td><select name="kind"><option value="coupon">คูปองส่วนลด</option><option value="gift">ของแถมฟรี 1 ชิ้น</option></select></td></tr>'
		. '<tr><th>ชื่อของรางวัล</th><td><input type="text" name="label" class="regular-text" placeholder="เช่น คูปองส่วนลด ฿100"></td></tr>'
		. '<tr><th>ใช้กี่แต้ม</th><td><input type="number" min="1" name="cost" style="width:8em"> แต้ม</td></tr>'
		. '<tr data-for="gift"><th>รหัสสินค้าที่แถม</th><td><input type="number" min="1" name="product_id" style="width:8em"> (ใช้กับ "ของแถมฟรี" เท่านั้น: รหัสสินค้าหรือตัวเลือก ดูได้ในหน้ารายการสินค้า ลูกค้าเลือกรับในตะกร้า ตัดสต็อกจริง)</td></tr>'
		. '<tr data-for="coupon"><th>ส่วนลด</th><td><input type="number" step="any" min="0" name="value" style="width:8em"> <select name="type"><option value="fixed">บาท</option><option value="percent">%</option></select></td></tr>'
		. '<tr data-for="coupon"><th>ยอดซื้อขั้นต่ำ</th><td><input type="number" step="any" min="0" name="min_spend" value="0" style="width:8em"> บาท (0 = ไม่มี)</td></tr>'
		. '<tr><th>คูปองใช้ได้กี่วัน</th><td><input type="number" min="1" max="365" name="days_valid" value="30" style="width:6em"> วัน นับจากวันที่แลก</td></tr></table>';
	echo '<h3>เพิ่มของรางวัล</h3>';
	amouriq_points_admin_form( 'reward_add', $inner, 'เพิ่มของรางวัล' );

	// Which coupon belongs to which redemption.
	$rows  = array();
	$query = new WP_User_Query( array( 'number' => -1, 'fields' => 'ID', 'meta_key' => 'amouriq_points_redemptions', 'meta_compare' => 'EXISTS' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $query->get_results() as $user_id ) {
		foreach ( amouriq_points_redemptions( $user_id ) as $row ) {
			$row['user_id'] = (int) $user_id;
			$rows[]         = $row;
		}
	}
	usort(
		$rows,
		static function ( $a, $b ) {
			return $b['time'] <=> $a['time'];
		}
	);
	echo '<h3>การแลกล่าสุด</h3><table class="widefat striped" style="max-width:900px"><thead><tr><th>วันที่</th><th>สมาชิก</th><th>ของรางวัล</th><th>แต้ม</th><th>คูปอง</th><th>สถานะ</th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="6">ยังไม่มีการแลก</td></tr>';
	}
	foreach ( array_slice( $rows, 0, 20 ) as $row ) {
		$user   = get_userdata( $row['user_id'] );
		$coupon = new WC_Coupon( (int) $row['coupon_id'] );
		printf(
			'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s"><code>%s</code></a></td><td>%s</td></tr>',
			esc_html( wp_date( 'j M Y H:i', $row['time'] ) ),
			$user ? '<a href="' . esc_url( amouriq_points_admin_url( array( 'email' => $user->user_email ) ) ) . '">' . esc_html( $user->user_email ) . '</a>' : esc_html( '#' . $row['user_id'] ),
			esc_html( $row['label'] ),
			esc_html( number_format_i18n( $row['cost'] ) ),
			esc_url( get_edit_post_link( (int) $row['coupon_id'] ) ),
			esc_html( $row['code'] ),
			esc_html( $coupon->get_usage_count() > 0 ? 'ใช้แล้ว' : 'ยังไม่ได้ใช้' )
		);
	}
	echo '</tbody></table>';
}

/* ------------------------------------------------------- gift reward in the cart */

add_filter( 'amouriq_bogo_deals_runtime', 'amouriq_points_gift_deals' );

/**
 * While a coupon from a gift Reward is in the cart (and its owner is the signed-in member), the
 * Deal engine gets a gift Deal for it: the customer picks the gift in the cart with the same choice
 * box as any gift Deal, the line is free and takes real stock. A paid item must be in the cart
 * (minimum spend ฿1), so the gift is never the whole order. When the coupon leaves the cart the
 * Deal disappears and the gift line is dropped with a notice, as for any gift Deal.
 */
function amouriq_points_gift_deals( $deals ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! amouriq_points_enabled() ) {
		return $deals;
	}
	foreach ( WC()->cart->get_applied_coupons() as $code ) {
		$coupon_id = wc_get_coupon_id_by_code( $code );
		$product   = $coupon_id ? (int) get_post_meta( $coupon_id, 'amouriq_points_gift_product', true ) : 0;
		if ( ! $product ) {
			continue;
		}
		$deals[] = array(
			'id'          => 'pts' . $coupon_id,
			'type'        => 'spend_gift',
			'label'       => 'ของรางวัลจากแต้ม: ' . get_post_meta( $coupon_id, 'amouriq_points_gift_label', true ),
			'trigger'     => 'coupon',
			'coupon_code' => $code,
			'min_spend'   => 1,
			'gift_ids'    => array( $product ),
			'gift_qty'    => 1,
		);
	}
	return $deals;
}

add_action( 'woocommerce_checkout_order_processed', 'amouriq_points_note_gift_reward', 30, 1 );

/** Say on the order that a gift came from points. */
function amouriq_points_note_gift_reward( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	foreach ( $order->get_coupon_codes() as $code ) {
		$coupon_id = wc_get_coupon_id_by_code( $code );
		if ( $coupon_id && get_post_meta( $coupon_id, 'amouriq_points_gift_product', true ) ) {
			$order->add_order_note( sprintf( 'ลูกค้าใช้ของรางวัลที่แลกด้วยแต้มสะสม: %s (คูปอง %s) ของแถมอยู่ในรายการสินค้าเป็นสินค้าราคา ฿0', get_post_meta( $coupon_id, 'amouriq_points_gift_label', true ), $code ) );
		}
	}
}

/* ------------------------------------------------------------ expiry reminder */

function amouriq_points_expiry_mail_settings() {
	return amouriq_points_core_expiry_mail_settings( get_option( 'amouriq_points_expiry_mail', array() ) );
}

add_action( 'init', 'amouriq_points_expiry_schedule' );

/** Keep the daily check scheduled only while the reminder and the programme are both on. */
function amouriq_points_expiry_schedule() {
	$on   = amouriq_points_expiry_mail_settings()['enabled'] && amouriq_points_enabled();
	$next = wp_next_scheduled( 'amouriq_points_expiry_cron' );
	if ( $on && ! $next ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'amouriq_points_expiry_cron' );
	} elseif ( ! $on && $next ) {
		wp_unschedule_event( $next, 'amouriq_points_expiry_cron' );
	}
}

add_action( 'amouriq_points_expiry_cron', 'amouriq_points_expiry_run' );

/**
 * Email each member whose points expire within the chosen days, once per batch (the soonest
 * expiry date of the batch is remembered in user meta). Returns how many emails were sent.
 */
function amouriq_points_expiry_run() {
	$settings = amouriq_points_expiry_mail_settings();
	if ( ! $settings['enabled'] || ! amouriq_points_enabled() || ! function_exists( 'WC' ) ) {
		return 0;
	}
	$sent   = 0;
	$offset = amouriq_points_site_offset();
	foreach ( amouriq_points_all_ledgers() as $user_id => $ledger ) {
		$expiring = amouriq_points_core_expiring( $ledger, time(), $settings['days'] );
		$last     = (int) get_user_meta( $user_id, '_amouriq_points_expiry_notified', true );
		$user     = get_userdata( $user_id );
		if ( ! $user || ! amouriq_points_core_should_notify( $expiring, $last ) ) {
			continue;
		}
		$text   = amouriq_points_core_expiry_message( $expiring['points'], $expiring['soonest'] + $offset, wc_get_account_endpoint_url( 'points' ) );
		$mailer = WC()->mailer();
		$html   = $mailer->wrap_message( $text['subject'], nl2br( esc_html( $text['body'] ) ) );
		if ( $mailer->send( $user->user_email, $text['subject'], $html ) ) {
			update_user_meta( $user_id, '_amouriq_points_expiry_notified', $expiring['soonest'] );
			$sent++;
		}
	}
	return $sent;
}

function amouriq_points_admin_expiry_mail_save() {
	// phpcs:disable WordPress.Security.NonceVerification -- checked in amouriq_points_admin_handle()
	$post = wp_unslash( $_POST );
	// phpcs:enable
	update_option(
		'amouriq_points_expiry_mail',
		amouriq_points_core_expiry_mail_settings(
			array(
				'enabled' => ! empty( $post['expiry_mail_on'] ),
				'days'    => isset( $post['expiry_mail_days'] ) ? $post['expiry_mail_days'] : '',
			)
		),
		false
	);
	return 'บันทึกการแจ้งเตือนแต้มใกล้หมดอายุแล้ว';
}

function amouriq_points_admin_expiry_mail_section() {
	$s = amouriq_points_expiry_mail_settings();
	echo '<h2>อีเมลเตือนแต้มใกล้หมดอายุ</h2><p>ส่งอีเมลภาษาไทยให้สมาชิกที่มีแต้มจะหมดอายุภายในจำนวนวันที่ตั้งไว้ ตรวจวันละครั้ง และเตือนครั้งเดียวต่อชุดแต้มที่ใกล้หมดอายุ (ไม่เตือนซ้ำทุกวัน) แต้มที่หมดอายุไปแล้วจะไม่ถูกเอ่ยถึง ปิดอยู่จนกว่าจะติ๊กเปิด และทำงานเมื่อระบบแต้มสะสมเปิดอยู่เท่านั้น อีเมลไม่มีราคาหรือรายละเอียดออเดอร์</p>';
	$inner = '<p><label><input type="checkbox" name="expiry_mail_on" value="1" ' . checked( $s['enabled'], true, false ) . '> เปิดอีเมลเตือน</label> เตือนล่วงหน้า <input type="number" min="1" max="90" name="expiry_mail_days" value="' . esc_attr( $s['days'] ) . '" style="width:5em"> วัน</p>';
	amouriq_points_admin_form( 'expiry_mail_save', $inner, 'บันทึก' );
}

add_action(
	'admin_footer',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification -- read-only
		if ( ! isset( $_GET['page'] ) || 'amouriq-points' !== $_GET['page'] ) {
			return;
		}
		?>
		<script>
		(function () {
			// In the "add rule" and "add reward" forms show only the fields that belong to the chosen kind.
			['earn_add', 'reward_add'].forEach(function (action) {
				var marker = document.querySelector('input[name=amouriq_points_admin][value=' + action + ']');
				if (!marker) { return; }
				var form = marker.form;
				var select = form.querySelector('select[name=kind]');
				if (!select) { return; }
				function sync() {
					form.querySelectorAll('tr[data-for]').forEach(function (row) {
						var show = row.getAttribute('data-for').split(' ').indexOf(select.value) !== -1;
						row.hidden = !show;
						row.querySelectorAll('input, select, textarea').forEach(function (field) { field.disabled = !show; });
					});
				}
				select.addEventListener('change', sync);
				sync();
			});
		})();
		</script>
		<?php
	}
);
