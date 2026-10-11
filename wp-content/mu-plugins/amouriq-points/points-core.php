<?php
/**
 * Pure rules of the AMOURIQ points programme. No WordPress, WooCommerce or
 * database calls, so tests/PointsTest.php can run them as they are. The glue
 * that reads the cart, the orders and the settings is amouriq-points.php.
 *
 * Settings (all amounts in baht, tax included, as the shopper sees them):
 *   earn_baht      baht of paid item total that earn 1 point
 *   redeem_baht    baht one point is worth when used
 *   max_percent    most of the item total (after other discounts) points may pay for
 *   min_redeem     fewest points that may be used in one order
 *   expiry_months  months a point lasts from the day it was earned (0 = never)
 *
 * A ledger entry is  array( 'time' => unix time, 'points' => int (earned +, used -),
 * 'expires' => unix time or 0 ). Earned entries are lots that expire; a negative
 * entry takes from the lots that expire first (and are still valid on its date).
 */

function amouriq_points_core_defaults() {
	return array(
		'earn_baht'     => 100.0,
		'redeem_baht'   => 1.0,
		'max_percent'   => 20.0,
		'min_redeem'    => 50,
		'expiry_months' => 12,
	);
}

/** Settings with the defaults filled in and nonsense (zero rates, negatives) replaced by the default. */
function amouriq_points_core_settings( $saved ) {
	$settings = amouriq_points_core_defaults();
	foreach ( (array) $saved as $key => $value ) {
		if ( isset( $settings[ $key ] ) && is_numeric( $value ) ) {
			$settings[ $key ] = $value + 0;
		}
	}
	foreach ( array( 'earn_baht', 'redeem_baht' ) as $key ) {
		if ( $settings[ $key ] <= 0 ) {
			$settings[ $key ] = amouriq_points_core_defaults()[ $key ];
		}
	}
	$settings['max_percent']   = min( 100.0, max( 0.0, (float) $settings['max_percent'] ) );
	$settings['min_redeem']    = max( 0, (int) $settings['min_redeem'] );
	$settings['expiry_months'] = max( 0, (int) $settings['expiry_months'] );
	return $settings;
}

/** Points earned by an order whose paid item total (no shipping, after every discount and points used) is $eligible baht. */
function amouriq_points_core_earn( $eligible, $settings ) {
	if ( $eligible <= 0 ) {
		return 0;
	}
	return (int) floor( round( $eligible, 2 ) / $settings['earn_baht'] );
}

/** When points earned at $time stop being valid; 0 means never. */
function amouriq_points_core_expiry( $time, $settings ) {
	if ( empty( $settings['expiry_months'] ) ) {
		return 0;
	}
	$date = ( new DateTimeImmutable( '@' . (int) $time ) )->modify( '+' . (int) $settings['expiry_months'] . ' months' );
	return $date->getTimestamp();
}

/**
 * What the customer can spend at $now: the points left in lots that have not
 * expired, after every use and reversal has taken from the lots expiring first.
 * Returns the points and the soonest expiry among them (0 when none expires).
 */
function amouriq_points_core_balance( $ledger, $now ) {
	usort(
		$ledger,
		static function ( $a, $b ) {
			return $a['time'] <=> $b['time'];
		}
	);

	$lots = array();
	foreach ( $ledger as $entry ) {
		$points = (int) $entry['points'];
		if ( $points > 0 ) {
			$lots[] = array( 'left' => $points, 'expires' => (int) $entry['expires'] );
			continue;
		}
		$take = -$points;
		// Soonest expiry first; "never" (0) last.
		usort(
			$lots,
			static function ( $a, $b ) {
				$ea = $a['expires'] ? $a['expires'] : PHP_INT_MAX;
				$eb = $b['expires'] ? $b['expires'] : PHP_INT_MAX;
				return $ea <=> $eb;
			}
		);
		foreach ( $lots as &$lot ) {
			if ( $take <= 0 ) {
				break;
			}
			if ( $lot['expires'] && $lot['expires'] <= $entry['time'] ) {
				continue; // already expired on the day of this entry
			}
			$used        = min( $lot['left'], $take );
			$lot['left'] -= $used;
			$take        -= $used;
		}
		unset( $lot );
	}

	$points = 0;
	$soonest = 0;
	foreach ( $lots as $lot ) {
		if ( $lot['left'] <= 0 || ( $lot['expires'] && $lot['expires'] <= $now ) ) {
			continue;
		}
		$points += $lot['left'];
		if ( $lot['expires'] && ( ! $soonest || $lot['expires'] < $soonest ) ) {
			$soonest = $lot['expires'];
		}
	}
	return array( 'points' => $points, 'soonest_expiry' => $soonest );
}

/**
 * Most points this cart may use. $base is the item total in baht after Deals,
 * Member Privilege and coupons, without shipping. Returns 'points' (0 when none
 * may be used) and a 'reason' when that is 0.
 */
function amouriq_points_core_redeem_limit( $balance, $base, $settings ) {
	if ( $balance <= 0 ) {
		return array( 'points' => 0, 'reason' => 'no_points' );
	}
	$by_cap = (int) floor( max( 0.0, $base ) * $settings['max_percent'] / 100 / $settings['redeem_baht'] + 1e-9 );
	$max    = min( (int) $balance, $by_cap );
	if ( $max < $settings['min_redeem'] || $max <= 0 ) {
		return array( 'points' => 0, 'reason' => ( $balance < $settings['min_redeem'] ) ? 'below_minimum' : 'cart_too_small' );
	}
	return array( 'points' => $max, 'reason' => '' );
}

/** What the customer asked to use, brought into the allowed range: 0 when below the minimum. */
function amouriq_points_core_redeem_apply( $requested, $limit, $settings ) {
	$points = min( max( 0, (int) $requested ), (int) $limit['points'] );
	if ( $points < $settings['min_redeem'] || $points <= 0 ) {
		return array( 'points' => 0, 'baht' => 0.0 );
	}
	return array( 'points' => $points, 'baht' => round( $points * $settings['redeem_baht'], 2 ) );
}

/**
 * Points to take back after a refund. $eligible is the order's paid item total,
 * $refunded the money refunded so far and $awarded the points the order has kept
 * so far; the order keeps what the smaller paid amount would have earned.
 */
function amouriq_points_core_refund_reversal( $eligible, $refunded, $awarded, $settings ) {
	$kept = amouriq_points_core_earn( $eligible - $refunded, $settings );
	return max( 0, (int) $awarded - $kept );
}

/**
 * Whether a staff Adjustment may be made: null when fine, otherwise the Thai message
 * to show. A reason is required, zero is meaningless, and a deduction cannot take
 * more than the customer can spend now.
 */
function amouriq_points_core_check_adjustment( $points, $reason, $balance ) {
	$points = (int) $points;
	if ( 0 === $points ) {
		return 'ใส่จำนวนแต้มที่ต้องการเพิ่ม (หรือหักด้วยเครื่องหมายลบ)';
	}
	if ( '' === trim( (string) $reason ) ) {
		return 'ต้องใส่เหตุผลในการปรับแต้ม';
	}
	if ( $points < 0 && -$points > (int) $balance ) {
		return sprintf( 'หักได้ไม่เกินแต้มคงเหลือของสมาชิก (%d แต้ม)', (int) $balance );
	}
	return null;
}
