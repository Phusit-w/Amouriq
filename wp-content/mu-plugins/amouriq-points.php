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
	return max( 0.0, (float) $order->get_total() - (float) $order->get_shipping_total() - (float) $order->get_shipping_tax() );
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
	$earn     = amouriq_points_core_earn( max( 0.0, (float) WC()->cart->get_total( 'edit' ) - (float) WC()->cart->get_shipping_total() - (float) WC()->cart->get_shipping_tax() ), $settings );

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

add_action( 'woocommerce_account_points_endpoint', 'amouriq_points_account_page' );

function amouriq_points_account_page() {
	if ( ! amouriq_points_enabled() ) {
		return;
	}
	$user_id  = get_current_user_id();
	$settings = amouriq_points_settings();
	$balance  = amouriq_points_balance( $user_id );
	$names    = amouriq_points_core_type_names();

	printf( '<p><strong>แต้มสะสมคงเหลือ: %s แต้ม</strong></p>', esc_html( number_format_i18n( $balance['points'] ) ) );
	if ( $balance['soonest_expiry'] ) {
		printf( '<p>แต้มชุดที่หมดอายุเร็วที่สุดหมดอายุวันที่ %s</p>', esc_html( wp_date( 'j M Y', $balance['soonest_expiry'] ) ) );
	}
	printf(
		'<p>ทุก ฿%s ที่ชำระค่าสินค้า (ไม่รวมค่าส่ง) ได้ 1 แต้ม เมื่อออเดอร์เสร็จสมบูรณ์ ใช้แต้มเป็นส่วนลดได้ 1 แต้ม = ฿%s ครั้งละอย่างน้อย %d แต้ม และไม่เกิน %s%% ของยอดสินค้า%s</p>',
		esc_html( number_format( $settings['earn_baht'], 0 ) ),
		esc_html( rtrim( rtrim( number_format( $settings['redeem_baht'], 2 ), '0' ), '.' ) ),
		(int) $settings['min_redeem'],
		esc_html( rtrim( rtrim( number_format( $settings['max_percent'], 2 ), '0' ), '.' ) ),
		$settings['expiry_months'] ? sprintf( ' แต้มมีอายุ %d เดือนนับจากวันที่ได้รับ', (int) $settings['expiry_months'] ) : ''
	);

	$ledger = array_reverse( amouriq_points_ledger( $user_id ) );
	if ( ! $ledger ) {
		echo '<p>ยังไม่มีรายการแต้มสะสม</p>';
		return;
	}
	echo '<table class="shop_table shop_table_responsive"><thead><tr><th>วันที่</th><th>รายการ</th><th>ออเดอร์</th><th>แต้ม</th></tr></thead><tbody>';
	foreach ( array_slice( $ledger, 0, 50 ) as $row ) {
		$type = isset( $row['type'] ) ? $row['type'] : '';
		printf(
			'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
			esc_html( wp_date( 'j M Y', $row['time'] ) ),
			esc_html( isset( $names[ $type ] ) ? $names[ $type ] : $type ),
			! empty( $row['order_id'] ) ? esc_html( '#' . $row['order_id'] ) : '—',
			esc_html( ( $row['points'] > 0 ? '+' : '' ) . $row['points'] )
		);
	}
	echo '</tbody></table>';
}

/* --------------------------------------------------------------------- admin */

add_action( 'admin_menu', 'amouriq_points_admin_menu' );

function amouriq_points_admin_menu() {
	add_submenu_page( 'woocommerce', 'แต้มสะสม', 'แต้มสะสม', 'manage_woocommerce', 'amouriq-points', 'amouriq_points_admin_page' );
}

add_action( 'admin_init', 'amouriq_points_admin_handle' );

function amouriq_points_admin_url( $args = array() ) {
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
	} elseif ( 'bonus_save' === $action ) {
		$notice = amouriq_points_admin_bonus_save();
	} elseif ( in_array( $action, array( 'earn_add', 'earn_delete', 'earn_toggle', 'earn_limits' ), true ) ) {
		$notice = amouriq_points_admin_earning_action( $action );
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
	wp_safe_redirect( amouriq_points_admin_url( $args ) );
	exit;
}

function amouriq_points_admin_form( $action, $inner, $button, $class = 'button button-primary', $confirm = '' ) {
	echo '<form method="post" style="display:inline"' . ( $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : '' ) . '>';
	wp_nonce_field( 'amouriq_points_admin', 'amouriq_points_admin_nonce' );
	echo '<input type="hidden" name="amouriq_points_admin" value="' . esc_attr( $action ) . '">' . $inner; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $button ) . '</button></form>';
}

function amouriq_points_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$settings = amouriq_points_settings();
	$on       = amouriq_points_enabled();
	$notice   = get_transient( 'amouriq_points_notice_' . get_current_user_id() );
	delete_transient( 'amouriq_points_notice_' . get_current_user_id() );
	$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$found = $email ? get_user_by( 'email', $email ) : false;

	echo '<div class="wrap"><h1>แต้มสะสม</h1>';
	if ( $notice ) {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}

	echo '<h2>สถานะระบบ</h2><p>ตอนนี้ระบบ<strong>' . ( $on ? 'เปิดอยู่' : 'ปิดอยู่' ) . '</strong> ';
	echo $on ? '(ลูกค้าได้แต้มเมื่อออเดอร์เสร็จสมบูรณ์ และใช้แต้มได้ที่ตะกร้า/ชำระเงิน)' : '(ยังไม่มีใครได้หรือใช้แต้ม ปิดอยู่จนกว่าจะกดเปิด)';
	echo '</p>';
	amouriq_points_admin_form( 'toggle', '', $on ? 'ปิดระบบแต้มสะสม' : 'เปิดระบบแต้มสะสม', $on ? 'button' : 'button button-primary', $on ? '' : 'เปิดระบบแต้มสะสมให้ลูกค้าทุกคนทันที ใช่ไหม?' );

	echo '<h2>การตั้งค่า</h2>';
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
	amouriq_points_admin_form( 'save', $inner, 'บันทึกการตั้งค่า' );

	echo '<h2>ปรับแต้มด้วยมือ</h2><p>เพิ่มแต้มให้สมาชิก (หรือใส่เลขลบเพื่อหัก) เช่น ชดเชยหรือแก้ไขข้อผิดพลาด</p>';
	$inner = '<p><label>อีเมลสมาชิก <input type="email" name="email" value="' . esc_attr( $email ) . '" required></label> '
		. '<label>แต้ม <input type="number" name="points" style="width:7em" required></label> '
		. '<label>เหตุผล (จำเป็น) <input type="text" name="note" style="width:18em" required></label></p>';
	amouriq_points_admin_form( 'adjust', $inner, 'ปรับแต้ม' );

	amouriq_points_admin_earning_section();
	amouriq_points_admin_bonus_section();
	amouriq_points_admin_report_section();

	amouriq_points_admin_customer_list();

	echo '<h2>ดูแต้มของสมาชิก</h2><form method="get"><input type="hidden" name="page" value="amouriq-points"><input type="email" name="email" value="' . esc_attr( $email ) . '" placeholder="อีเมลสมาชิก"> <button class="button">ค้นหา</button></form>';
	if ( $email && ! $found ) {
		echo '<p>ไม่พบสมาชิกที่ใช้อีเมลนี้</p>';
	} elseif ( $found ) {
		$balance = amouriq_points_balance( $found->ID );
		printf( '<p><strong>%s</strong>: %s แต้ม%s</p>', esc_html( $email ), esc_html( number_format_i18n( $balance['points'] ) ), $balance['soonest_expiry'] ? esc_html( ' (ชุดที่หมดอายุเร็วสุด: ' . wp_date( 'j M Y', $balance['soonest_expiry'] ) . ')' ) : '' );
		echo '<table class="widefat striped" style="max-width:800px"><thead><tr><th>วันที่</th><th>ประเภท</th><th>ออเดอร์</th><th>แต้ม</th><th>หมดอายุ</th><th>หมายเหตุ</th><th>โดย</th></tr></thead><tbody>';
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
		echo '</tbody></table>';
	}
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
	echo '<form method="get"><input type="hidden" name="page" value="amouriq-points"><input type="search" name="q" value="' . esc_attr( $search ) . '" placeholder="ค้นหาชื่อหรืออีเมล"> <button class="button">ค้นหา</button></form>';
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
		. '<tr><th>ซื้อกี่บาทได้ 1 แต้ม</th><td><input type="number" step="any" min="0" name="baht" style="width:8em"> บาท (ใช้กับอัตราพิเศษ)</td></tr>'
		. '<tr><th>หมวดสินค้า</th><td><select name="categories[]" multiple size="5" style="min-width:16em">' . $opts . '</select><br><small>ใช้กับอัตราพิเศษและไม่ให้แต้ม (กด Ctrl เพื่อเลือกหลายหมวด)</small></td></tr>'
		. '<tr><th>รหัสสินค้า</th><td><input type="text" name="product_ids" class="regular-text" placeholder="เช่น 2278, 2285"><br><small>คั่นด้วยจุลภาค ถ้าเลือกทั้งหมวดและรหัส สินค้าต้องอยู่ในทั้งสองอย่าง</small></td></tr>'
		. '<tr><th>ตัวคูณ</th><td>x <input type="number" step="any" min="1" max="10" name="factor" style="width:6em"> (ใช้กับตัวคูณแต้ม ระหว่าง 1 ถึง 10)</td></tr>'
		. '<tr><th>ช่วงวันที่</th><td><input type="date" name="from"> ถึง <input type="date" name="to"> <small>เว้นว่าง = ไม่จำกัด (ใช้กับตัวคูณแต้ม)</small></td></tr></table>';
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
