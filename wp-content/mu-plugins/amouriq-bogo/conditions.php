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

/* ------------------------------------------------------- admin: form helpers */

/**
 * What the admin form offers for each Condition type: its Thai label, the operators
 * that make sense for it, and a hint for the value box. The first operator is the default.
 */
function amouriq_cond_catalog() {
	$numeric = array( 'gte' => 'อย่างน้อย', 'lte' => 'ไม่เกิน', 'eq' => 'เท่ากับ', 'between' => 'ระหว่าง' );
	return array(
		'logged_in'             => array( 'label' => 'ลูกค้าล็อกอินอยู่', 'ops' => array( 'is' => 'คือ' ), 'hint' => 'ใส่ ใช่ หรือ ไม่' ),
		'user_role'             => array( 'label' => 'บทบาทผู้ใช้', 'ops' => array( 'in' => 'เป็นหนึ่งใน' ), 'hint' => 'เช่น customer, wholesale (คั่นด้วยจุลภาค)' ),
		'email_in_list'         => array( 'label' => 'อีเมลลูกค้า', 'ops' => array( 'in' => 'อยู่ในรายการ' ), 'hint' => 'เช่น a@x.com, b@y.com (คั่นด้วยจุลภาค)' ),
		'orders_count'          => array( 'label' => 'จำนวนออเดอร์ที่เคยมี', 'ops' => $numeric, 'hint' => 'ตัวเลข หรือ 2-5 สำหรับ "ระหว่าง"' ),
		'total_spent'           => array( 'label' => 'ยอดซื้อสะสม (บาท)', 'ops' => $numeric, 'hint' => 'ตัวเลข หรือ 1000-5000 สำหรับ "ระหว่าง"' ),
		'days_since_last_order' => array( 'label' => 'วันนับจากออเดอร์ล่าสุด', 'ops' => $numeric, 'hint' => 'จำนวนวัน (คนที่ไม่เคยสั่งซื้อจะไม่ตรงเงื่อนไขนี้)' ),
		'weekday'               => array( 'label' => 'วันในสัปดาห์', 'ops' => array( 'in' => 'เป็นวัน' ), 'hint' => 'เช่น เสาร์, อาทิตย์ หรือ 6,7 (จันทร์=1 ... อาทิตย์=7)' ),
		'time_of_day'           => array( 'label' => 'ช่วงเวลาในวัน', 'ops' => array( 'between' => 'ระหว่าง' ), 'hint' => 'เช่น 18:00-21:00 (ข้ามเที่ยงคืนได้ เช่น 22:00-06:00)' ),
		'cart_items'            => array( 'label' => 'จำนวนรายการในตะกร้า', 'ops' => $numeric, 'hint' => 'ตัวเลข (รายการที่ต่างกัน)' ),
		'cart_quantity'         => array( 'label' => 'จำนวนชิ้นรวมในตะกร้า', 'ops' => $numeric, 'hint' => 'ตัวเลข' ),
		'cart_subtotal'         => array( 'label' => 'ยอดสินค้าในตะกร้า (บาท)', 'ops' => $numeric, 'hint' => 'ตามราคาที่ลูกค้าเห็น ก่อนส่วนลด ไม่รวมค่าส่ง' ),
		'cart_has_product'      => array( 'label' => 'มีสินค้าในตะกร้า', 'ops' => array( 'has_any' => 'มีอย่างน้อยหนึ่งอย่าง', 'has_all' => 'มีครบทุกอย่าง' ), 'hint' => 'รหัสสินค้า คั่นด้วยจุลภาค เช่น 2278, 2285' ),
		'cart_has_category'     => array( 'label' => 'มีสินค้าหมวดในตะกร้า', 'ops' => array( 'has_any' => 'มีอย่างน้อยหนึ่งหมวด', 'has_all' => 'มีครบทุกหมวด' ), 'hint' => 'slug หรือรหัสหมวด คั่นด้วยจุลภาค' ),
	);
}

/** Thai (and short) day names to ISO numbers, Monday = 1. */
function amouriq_cond_day_numbers() {
	return array(
		'จันทร์' => 1, 'อังคาร' => 2, 'พุธ' => 3, 'พฤหัส' => 4, 'พฤหัสบดี' => 4, 'ศุกร์' => 5, 'เสาร์' => 6, 'อาทิตย์' => 7,
	);
}

/** "a, b ,c" as a clean list (no empty items). */
function amouriq_cond_split( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/[,;\n]+/u', (string) $text ) ), 'strlen' ) );
}

/**
 * One form row (type, operator, the value box text) as a stored row, or an error
 * message. Returns array( 'row' => ... ) or array( 'error' => '...' ). Category
 * slugs are kept as typed (the WooCommerce side turns them into IDs).
 */
function amouriq_cond_parse_row( $type, $op, $text ) {
	$catalog = amouriq_cond_catalog();
	if ( ! isset( $catalog[ $type ] ) ) {
		return array( 'error' => 'ไม่รู้จักชนิดเงื่อนไข' );
	}
	$label = $catalog[ $type ]['label'];
	if ( ! isset( $catalog[ $type ]['ops'][ $op ] ) ) {
		$op = (string) key( $catalog[ $type ]['ops'] );
	}
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return array( 'error' => 'เงื่อนไข "' . $label . '" ยังไม่ได้ใส่ค่า' );
	}

	switch ( $type ) {
		case 'logged_in':
			$yes = in_array( mb_strtolower( $text ), array( 'ใช่', 'yes', 'y', '1', 'true' ), true );
			$no  = in_array( mb_strtolower( $text ), array( 'ไม่', 'ไม่ใช่', 'no', 'n', '0', 'false' ), true );
			if ( ! $yes && ! $no ) {
				return array( 'error' => 'เงื่อนไข "' . $label . '" ให้ใส่ ใช่ หรือ ไม่' );
			}
			return array( 'row' => array( 'type' => $type, 'op' => 'is', 'value' => $yes ) );

		case 'user_role':
			return array( 'row' => array( 'type' => $type, 'op' => 'in', 'value' => array_map( 'strtolower', amouriq_cond_split( $text ) ) ) );

		case 'email_in_list':
			$emails = array_map( 'strtolower', amouriq_cond_split( $text ) );
			foreach ( $emails as $email ) {
				if ( false === strpos( $email, '@' ) ) {
					return array( 'error' => 'อีเมลไม่ถูกต้อง: ' . $email );
				}
			}
			return array( 'row' => array( 'type' => $type, 'op' => 'in', 'value' => $emails ) );

		case 'weekday':
			$names = amouriq_cond_day_numbers();
			$days  = array();
			foreach ( amouriq_cond_split( $text ) as $item ) {
				if ( isset( $names[ $item ] ) ) {
					$days[] = $names[ $item ];
				} elseif ( ctype_digit( $item ) && (int) $item >= 1 && (int) $item <= 7 ) {
					$days[] = (int) $item;
				} else {
					return array( 'error' => 'วันไม่ถูกต้อง: ' . $item . ' (ใช้ชื่อวัน หรือ 1 ถึง 7)' );
				}
			}
			return array( 'row' => array( 'type' => $type, 'op' => 'in', 'value' => array_values( array_unique( $days ) ) ) );

		case 'time_of_day':
			if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)\s*[-–ถึง]+\s*([01]?\d|2[0-3]):([0-5]\d)$/u', $text, $m ) ) {
				return array( 'error' => 'ช่วงเวลาไม่ถูกต้อง ใช้รูปแบบ 18:00-21:00' );
			}
			return array( 'row' => array( 'type' => $type, 'op' => 'between', 'value' => array( sprintf( '%02d:%s', $m[1], $m[2] ), sprintf( '%02d:%s', $m[3], $m[4] ) ) ) );

		case 'cart_has_product':
			$ids = array();
			foreach ( amouriq_cond_split( $text ) as $item ) {
				if ( ! ctype_digit( $item ) || (int) $item < 1 ) {
					return array( 'error' => 'รหัสสินค้าต้องเป็นตัวเลข: ' . $item );
				}
				$ids[] = (int) $item;
			}
			return array( 'row' => array( 'type' => $type, 'op' => $op, 'value' => array_values( array_unique( $ids ) ) ) );

		case 'cart_has_category':
			return array( 'row' => array( 'type' => $type, 'op' => $op, 'value' => amouriq_cond_split( $text ) ) );
	}

	// The numeric types.
	if ( 'between' === $op ) {
		if ( ! preg_match( '/^(-?\d+(?:\.\d+)?)\s*[-–,]\s*(\d+(?:\.\d+)?)$/', $text, $m ) || (float) $m[1] > (float) $m[2] ) {
			return array( 'error' => 'เงื่อนไข "' . $label . '" แบบระหว่าง ให้ใส่ เช่น 2-5 (ค่าน้อยก่อน)' );
		}
		return array( 'row' => array( 'type' => $type, 'op' => 'between', 'value' => array( $m[1] + 0, $m[2] + 0 ) ) );
	}
	if ( ! is_numeric( $text ) || (float) $text < 0 ) {
		return array( 'error' => 'เงื่อนไข "' . $label . '" ต้องเป็นตัวเลขตั้งแต่ 0' );
	}
	return array( 'row' => array( 'type' => $type, 'op' => $op, 'value' => $text + 0 ) );
}

/** A stored row's value as the text the form box shows (the reverse of amouriq_cond_parse_row()). */
function amouriq_cond_format_value( $row ) {
	$value = isset( $row['value'] ) ? $row['value'] : '';
	$type  = isset( $row['type'] ) ? $row['type'] : '';
	if ( 'logged_in' === $type ) {
		return $value ? 'ใช่' : 'ไม่';
	}
	if ( 'weekday' === $type ) {
		$names = array_flip( amouriq_cond_day_numbers() );
		return implode( ', ', array_map( static function ( $d ) use ( $names ) {
			return isset( $names[ $d ] ) ? $names[ $d ] : (string) $d;
		}, (array) $value ) );
	}
	if ( 'time_of_day' === $type && is_array( $value ) && count( $value ) >= 2 ) {
		return array_values( $value )[0] . '-' . array_values( $value )[1];
	}
	if ( isset( $row['op'] ) && 'between' === $row['op'] && is_array( $value ) && count( $value ) >= 2 ) {
		return array_values( $value )[0] . '-' . array_values( $value )[1];
	}
	return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
}

/** One plain Thai sentence for a Condition set, for the rule list ("" when there is none). */
function amouriq_cond_describe( $set ) {
	if ( empty( $set['rows'] ) || ! is_array( $set['rows'] ) ) {
		return '';
	}
	$catalog = amouriq_cond_catalog();
	$parts   = array();
	foreach ( $set['rows'] as $row ) {
		$type = isset( $row['type'] ) ? $row['type'] : '';
		if ( ! isset( $catalog[ $type ] ) ) {
			continue;
		}
		$op_label = isset( $catalog[ $type ]['ops'][ $row['op'] ] ) ? $catalog[ $type ]['ops'][ $row['op'] ] : '';
		$parts[]  = trim( $catalog[ $type ]['label'] . ' ' . $op_label . ' ' . amouriq_cond_format_value( $row ) );
	}
	if ( ! $parts ) {
		return '';
	}
	return ( ( isset( $set['match'] ) && 'any' === $set['match'] ) ? 'ข้อใดข้อหนึ่ง: ' : 'ครบทุกข้อ: ' ) . implode( ' · ', $parts );
}
