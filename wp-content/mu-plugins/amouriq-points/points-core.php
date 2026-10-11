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

/* ------------------------------------------------------------ earning rules */

/**
 * The owner's Earning rules as safe data. Shape:
 *   array( 'rules' => array( rule, ... ), 'min_order' => baht, 'max_per_order' => points (0 = no cap) )
 * A rule is one of
 *   kind 'rate'        baht (of paid line total per 1 point, above 0), scope (a Product scope)
 *   kind 'exclude'     scope: lines in it earn nothing
 *   kind 'multiplier'  factor (above 1, at most 10), from / to ('Y-m-d', optional), conditions (optional set)
 * each with `enabled` and an optional `label`. Anything else is dropped.
 */
function amouriq_points_core_earning_clean( $raw ) {
	$raw = is_array( $raw ) ? $raw : array();
	$out = array( 'rules' => array(), 'min_order' => 0.0, 'max_per_order' => 0 );

	if ( isset( $raw['min_order'] ) && is_numeric( $raw['min_order'] ) ) {
		$out['min_order'] = max( 0.0, (float) $raw['min_order'] );
	}
	if ( isset( $raw['max_per_order'] ) && is_numeric( $raw['max_per_order'] ) ) {
		$out['max_per_order'] = max( 0, (int) $raw['max_per_order'] );
	}

	foreach ( isset( $raw['rules'] ) && is_array( $raw['rules'] ) ? $raw['rules'] : array() as $rule ) {
		if ( ! is_array( $rule ) || ! isset( $rule['kind'] ) ) {
			continue;
		}
		$clean = array(
			'kind'    => (string) $rule['kind'],
			'enabled' => ! isset( $rule['enabled'] ) || (bool) $rule['enabled'],
			'label'   => isset( $rule['label'] ) ? trim( strip_tags( (string) $rule['label'] ) ) : '',
		);
		if ( 'rate' === $clean['kind'] || 'exclude' === $clean['kind'] ) {
			$clean['scope'] = isset( $rule['scope'] ) && is_array( $rule['scope'] ) ? $rule['scope'] : array();
			if ( 'rate' === $clean['kind'] ) {
				if ( ! isset( $rule['baht'] ) || ! is_numeric( $rule['baht'] ) || $rule['baht'] <= 0 ) {
					continue;
				}
				$clean['baht'] = (float) $rule['baht'];
			}
		} elseif ( 'multiplier' === $clean['kind'] ) {
			if ( ! isset( $rule['factor'] ) || ! is_numeric( $rule['factor'] ) || $rule['factor'] <= 1 ) {
				continue;
			}
			$clean['factor']     = min( 10.0, (float) $rule['factor'] );
			$clean['from']       = ! empty( $rule['from'] ) ? (string) $rule['from'] : '';
			$clean['to']         = ! empty( $rule['to'] ) ? (string) $rule['to'] : '';
			$clean['conditions'] = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
		} else {
			continue;
		}
		$out['rules'][] = $clean;
	}
	return $out;
}

/**
 * Points an order earns under the Earning rules. `$lines` are the order's item
 * lines: `paid` (what the line cost, tax included) plus the fields a Product scope
 * reads (product_id, variation_id, categories, tags, attributes, sku, on_sale).
 * `$eligible` is the paid item total the flat rule uses (after every discount and
 * points used); the lines' `paid` are scaled to add up to it, so order-level
 * discounts are shared by the lines. Each line earns at the first enabled `rate`
 * rule covering it (else the base rate) unless an `exclude` rule covers it; the
 * fractions add up, the largest valid multiplier applies, then the total rounds
 * down once. A minimum order total and a per-order cap come last.
 * `$ctx`: `now` ('Y-m-d H:i') plus anything the multiplier Conditions read.
 * With no rules and no limits this is exactly amouriq_points_core_earn().
 */
function amouriq_points_core_earn_order( $lines, $eligible, $earning, $settings, $ctx ) {
	if ( $eligible <= 0 ) {
		return 0;
	}
	if ( $eligible < (float) $earning['min_order'] ) {
		return 0;
	}

	$rules = array_filter(
		$earning['rules'],
		static function ( $rule ) {
			return $rule['enabled'];
		}
	);

	$total_paid = 0.0;
	foreach ( $lines as $line ) {
		$total_paid += max( 0.0, (float) $line['paid'] );
	}

	if ( ! $rules || $total_paid <= 0 ) {
		$points = amouriq_points_core_earn( $eligible, $settings );
	} else {
		$fraction = 0.0;
		foreach ( $lines as $line ) {
			$paid = max( 0.0, (float) $line['paid'] ) * $eligible / $total_paid;
			$baht = (float) $settings['earn_baht'];
			foreach ( $rules as $rule ) {
				if ( ! amouriq_points_core_rule_covers( $rule, $line ) ) {
					continue;
				}
				if ( 'exclude' === $rule['kind'] ) {
					$baht = 0.0;
					break;
				}
				if ( 'rate' === $rule['kind'] ) {
					$baht = (float) $rule['baht'];
					break;
				}
			}
			if ( $baht > 0 ) {
				$fraction += round( $paid / $baht, 8 );
			}
		}

		$factor = 1.0;
		foreach ( $rules as $rule ) {
			if ( 'multiplier' === $rule['kind'] && amouriq_points_core_multiplier_live( $rule, $ctx ) ) {
				$factor = max( $factor, (float) $rule['factor'] );
			}
		}
		$points = (int) floor( round( $fraction * $factor, 6 ) );
	}

	if ( $earning['max_per_order'] > 0 ) {
		$points = min( $points, (int) $earning['max_per_order'] );
	}
	return max( 0, $points );
}

/** Whether a rate or exclude rule's Product scope covers the line. A rule that cannot be judged covers nothing. */
function amouriq_points_core_rule_covers( $rule, $line ) {
	if ( 'rate' !== $rule['kind'] && 'exclude' !== $rule['kind'] ) {
		return false;
	}
	if ( ! function_exists( 'amouriq_bogo_core_in_scope' ) ) {
		return false;
	}
	// An empty scope would cover every line; an exclude with no scope is almost certainly a mistake, so it covers nothing.
	if ( empty( $rule['scope'] ) ) {
		return 'rate' === $rule['kind'];
	}
	return amouriq_bogo_core_in_scope( $line, $rule['scope'] );
}

/** Whether a multiplier is in its dates and its Conditions (if any) match. */
function amouriq_points_core_multiplier_live( $rule, $ctx ) {
	$today = substr( isset( $ctx['now'] ) ? $ctx['now'] : '', 0, 10 );
	if ( '' !== $rule['from'] && $today < $rule['from'] ) {
		return false;
	}
	if ( '' !== $rule['to'] && $today > $rule['to'] ) {
		return false;
	}
	if ( ! empty( $rule['conditions'] ) ) {
		return function_exists( 'amouriq_cond_evaluate' ) && 'match' === amouriq_cond_evaluate( $rule['conditions'], $ctx )['result'];
	}
	return true;
}

/**
 * Refund reversal for an order whose points came from Earning rules (so the flat
 * rule cannot be re-run on the smaller amount): the order keeps the same share of
 * its original points as the share of the money not refunded.
 */
function amouriq_points_core_refund_reversal_share( $eligible, $refunded, $original, $awarded_now ) {
	if ( $eligible <= 0 ) {
		return max( 0, (int) $awarded_now );
	}
	$left = max( 0.0, min( 1.0, ( $eligible - $refunded ) / $eligible ) );
	$kept = (int) floor( round( $original * $left, 6 ) );
	return max( 0, (int) $awarded_now - $kept );
}

/* ------------------------------------------------------------------ bonuses */

/** Thai names of the ledger entry types, for the customer's list and the admin list. */
function amouriq_points_core_type_names() {
	return array(
		'earn'         => 'ได้รับแต้ม',
		'use'          => 'ใช้แต้ม',
		'give_back'    => 'คืนแต้ม',
		'refund'       => 'หักแต้ม (คืนเงิน)',
		'adjust'       => 'ปรับแต้มโดยร้าน',
		'bonus_signup' => 'โบนัสสมัครสมาชิก',
		'bonus_first'  => 'โบนัสออเดอร์แรก',
		'bonus_review' => 'โบนัสรีวิวสินค้า',
	);
}

/** The three one-time bonuses, each off until the owner switches it on. */
function amouriq_points_core_bonus_defaults() {
	return array(
		'signup'      => array( 'enabled' => false, 'points' => 20 ),
		'first_order' => array( 'enabled' => false, 'points' => 30 ),
		'review'      => array( 'enabled' => false, 'points' => 10, 'per_year' => 5 ),
	);
}

/** Stored bonus settings with the defaults filled in; non-numbers and negatives fall back to the default. */
function amouriq_points_core_bonus_clean( $raw ) {
	$raw = is_array( $raw ) ? $raw : array();
	$out = amouriq_points_core_bonus_defaults();
	foreach ( $out as $kind => $settings ) {
		$given = isset( $raw[ $kind ] ) && is_array( $raw[ $kind ] ) ? $raw[ $kind ] : array();
		if ( isset( $given['enabled'] ) ) {
			$out[ $kind ]['enabled'] = ! in_array( $given['enabled'], array( false, 0, '0', 'no', '' ), true );
		}
		foreach ( array( 'points', 'per_year' ) as $number ) {
			if ( isset( $settings[ $number ] ) && isset( $given[ $number ] ) && is_numeric( $given[ $number ] ) && $given[ $number ] >= 0 ) {
				$out[ $kind ][ $number ] = (int) $given[ $number ];
			}
		}
	}
	return $out;
}

/**
 * Points for an approved verified-purchase review of $product_id: the review bonus
 * when it is on, the customer has not had it for this product before, and fewer than
 * `per_year` review bonuses were given in the last 365 days (0 = no yearly cap).
 * `$ledger` is the customer's ledger; review bonuses carry `ref` = the product ID.
 */
function amouriq_points_core_review_bonus( $bonus, $ledger, $product_id, $now ) {
	$review = $bonus['review'];
	if ( ! $review['enabled'] || $review['points'] <= 0 ) {
		return 0;
	}
	$recent = 0;
	foreach ( $ledger as $entry ) {
		if ( ! isset( $entry['type'] ) || 'bonus_review' !== $entry['type'] ) {
			continue;
		}
		if ( isset( $entry['ref'] ) && (int) $entry['ref'] === (int) $product_id ) {
			return 0;
		}
		if ( (int) $entry['time'] > $now - 365 * 86400 ) {
			$recent++;
		}
	}
	if ( $review['per_year'] > 0 && $recent >= $review['per_year'] ) {
		return 0;
	}
	return (int) $review['points'];
}
