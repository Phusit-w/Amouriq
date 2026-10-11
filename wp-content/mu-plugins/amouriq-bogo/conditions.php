<?php
/**
 * Conditions: tests on who the customer is, when it is, and what is in the cart,
 * on plain data (no WordPress or WooCommerce calls), so they can be tested without
 * either. A Deal (and later a Coupon or an Earning rule) holds a set:
 *
 *   array( 'match' => 'all'|'any', 'rows' => array( array( 'type' =>, 'op' =>, 'value' => ), ... ) )
 *
 * amouriq_cond_evaluate() answers 'match', 'no_match' or 'unknown' (an input is not
 * known yet, for example the customer's email before it is typed) and lists what is
 * missing. Unknown never counts as a match.
 *
 * The context is a plain array; any value may be a callable, run only when a row
 * needs it (so a database lookup is paid for only by a Deal that asks):
 *   now (local 'Y-m-d H:i'), logged_in (bool), user_roles (string[]), email (string),
 *   orders_count (int|null), total_spent (float|null),
 *   days_since_last_order (int, false when the customer never ordered, null when unknown),
 *   cart_items, cart_quantity, cart_subtotal, cart_product_ids, cart_category_ids.
 *
 * Types and operators:
 *   logged_in            is (bool)
 *   user_role            in (string[])
 *   email_in_list        in (string[], case-insensitive)
 *   orders_count, total_spent, days_since_last_order, cart_items, cart_quantity,
 *   cart_subtotal        eq / gte / lte (number), between (array( from, to ), inclusive)
 *   weekday              in (ISO numbers, Monday = 1 ... Sunday = 7)
 *   time_of_day          between (array( 'HH:MM', 'HH:MM' ); a window past midnight is fine)
 *   cart_has_product, cart_has_category   has_any / has_all (ids)
 */

defined( 'ABSPATH' ) || exit;

function amouriq_cond_evaluate( $set, $ctx ) {
	$rows = ( is_array( $set ) && ! empty( $set['rows'] ) && is_array( $set['rows'] ) ) ? $set['rows'] : array();
	if ( ! $rows ) {
		return array( 'result' => 'match', 'missing' => array() );
	}

	$any      = isset( $set['match'] ) && 'any' === $set['match'];
	$unknown  = array();
	$matched  = false;
	$rejected = false;

	foreach ( $rows as $row ) {
		$one = amouriq_cond_row( $row, $ctx );
		if ( 'match' === $one['result'] ) {
			$matched = true;
		} elseif ( 'no_match' === $one['result'] ) {
			$rejected = true;
		} else {
			$unknown = array_merge( $unknown, $one['missing'] );
		}
	}
	$unknown = array_values( array_unique( $unknown ) );

	if ( $any ) {
		if ( $matched ) {
			return array( 'result' => 'match', 'missing' => array() );
		}
		return $unknown ? array( 'result' => 'unknown', 'missing' => $unknown ) : array( 'result' => 'no_match', 'missing' => array() );
	}

	if ( $rejected ) {
		return array( 'result' => 'no_match', 'missing' => array() );
	}
	return $unknown ? array( 'result' => 'unknown', 'missing' => $unknown ) : array( 'result' => 'match', 'missing' => array() );
}

/** One row against the context. */
function amouriq_cond_row( $row, $ctx ) {
	$type  = isset( $row['type'] ) ? (string) $row['type'] : '';
	$op    = isset( $row['op'] ) ? (string) $row['op'] : '';
	$value = isset( $row['value'] ) ? $row['value'] : null;

	$yes     = array( 'result' => 'match', 'missing' => array() );
	$no      = array( 'result' => 'no_match', 'missing' => array() );
	$verdict = static function ( $ok ) use ( $yes, $no ) {
		return $ok ? $yes : $no;
	};
	$unknown = static function ( $what ) {
		return array( 'result' => 'unknown', 'missing' => array( $what ) );
	};
	$get = static function ( $key ) use ( $ctx ) {
		if ( ! array_key_exists( $key, $ctx ) ) {
			return null;
		}
		return is_callable( $ctx[ $key ] ) ? call_user_func( $ctx[ $key ] ) : $ctx[ $key ];
	};

	switch ( $type ) {
		case 'logged_in':
			$in = $get( 'logged_in' );
			return null === $in ? $unknown( 'login' ) : $verdict( (bool) $in === (bool) $value );

		case 'user_role':
			$in = $get( 'logged_in' );
			if ( null === $in ) {
				return $unknown( 'login' );
			}
			$roles = (array) $get( 'user_roles' );
			return $verdict( $in && (bool) array_intersect( $roles, (array) $value ) );

		case 'email_in_list':
			$email = strtolower( trim( (string) $get( 'email' ) ) );
			if ( '' === $email ) {
				return $unknown( 'email' );
			}
			return $verdict( in_array( $email, array_map( 'amouriq_cond_lower', (array) $value ), true ) );

		case 'orders_count':
		case 'total_spent':
			$have = $get( $type );
			return null === $have ? $unknown( 'customer' ) : $verdict( amouriq_cond_number( $have, $op, $value ) );

		case 'days_since_last_order':
			$have = $get( 'days_since_last_order' );
			if ( null === $have ) {
				return $unknown( 'customer' );
			}
			return false === $have ? $no : $verdict( amouriq_cond_number( $have, $op, $value ) );

		case 'cart_items':
		case 'cart_quantity':
		case 'cart_subtotal':
			$have = $get( $type );
			return null === $have ? $unknown( 'cart' ) : $verdict( amouriq_cond_number( $have, $op, $value ) );

		case 'weekday':
			$now = $get( 'now' );
			if ( ! $now ) {
				return $unknown( 'time' );
			}
			return $verdict( in_array( (int) date( 'N', strtotime( $now ) ), array_map( 'intval', (array) $value ), true ) );

		case 'time_of_day':
			$now = $get( 'now' );
			if ( ! $now || ! is_array( $value ) || count( $value ) < 2 ) {
				return $now ? $no : $unknown( 'time' );
			}
			$clock = date( 'H:i', strtotime( $now ) );
			$from  = array_values( $value )[0];
			$to    = array_values( $value )[1];
			$ok    = $from <= $to ? ( $clock >= $from && $clock <= $to ) : ( $clock >= $from || $clock <= $to );
			return $verdict( $ok );

		case 'cart_has_product':
		case 'cart_has_category':
			$have = $get( 'cart_has_product' === $type ? 'cart_product_ids' : 'cart_category_ids' );
			if ( null === $have ) {
				return $unknown( 'cart' );
			}
			$want = array_map( 'intval', (array) $value );
			$have = array_map( 'intval', (array) $have );
			if ( 'has_all' === $op ) {
				return $verdict( ! array_diff( $want, $have ) && $want );
			}
			return $verdict( (bool) array_intersect( $want, $have ) );
	}

	// A type this code does not know never matches, so a typo cannot hand out a discount.
	return $no;
}

function amouriq_cond_lower( $text ) {
	return strtolower( trim( (string) $text ) );
}

function amouriq_cond_number( $have, $op, $value ) {
	switch ( $op ) {
		case 'eq':
			return (float) $have === (float) $value;
		case 'gte':
			return (float) $have >= (float) $value;
		case 'lte':
			return (float) $have <= (float) $value;
		case 'between':
			$bounds = array_values( (array) $value );
			return count( $bounds ) >= 2 && (float) $have >= (float) $bounds[0] && (float) $have <= (float) $bounds[1];
	}
	return false;
}

/**
 * A customer's counted orders summed up for the history Conditions. `$orders` are
 * array( 'total' => baht still paid (refunds taken off), 'time' => unix time ), one per
 * order. Returns orders_count, total_spent and days_since_last_order (false when there
 * is no order yet, so "at least N days since the last order" does not match a
 * customer who never ordered).
 */
function amouriq_cond_history( $orders, $now ) {
	$count = 0;
	$total = 0.0;
	$last  = 0;
	foreach ( $orders as $order ) {
		$count++;
		$total += max( 0.0, (float) $order['total'] );
		$last   = max( $last, (int) $order['time'] );
	}
	return array(
		'orders_count'          => $count,
		'total_spent'           => round( $total, 2 ),
		'days_since_last_order' => $count ? (int) floor( max( 0, $now - $last ) / 86400 ) : false,
	);
}
