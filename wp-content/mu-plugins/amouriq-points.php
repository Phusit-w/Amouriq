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
function amouriq_points_record( $user_id, $points, $type, $order_id = 0, $note = '', $by = 0 ) {
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
	$points   = amouriq_points_core_earn( $eligible, amouriq_points_settings() );
	$order->update_meta_data( '_amouriq_points_awarded', $points );
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
	$reverse  = amouriq_points_core_refund_reversal( (float) $order->get_meta( '_amouriq_points_eligible', true ), (float) $order->get_total_refunded(), $awarded, amouriq_points_settings() );
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
	$names    = array( 'earn' => 'ได้รับแต้ม', 'use' => 'ใช้แต้ม', 'give_back' => 'คืนแต้ม', 'refund' => 'หักแต้ม (คืนเงิน)', 'adjust' => 'ปรับแต้มโดยร้าน' );

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
				esc_html( isset( $row['type'] ) ? $row['type'] : '' ),
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
