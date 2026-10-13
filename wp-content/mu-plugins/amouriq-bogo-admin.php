<?php
/**
 * Plugin Name: AMOURIQ: BOGO deals admin
 * Description: WooCommerce > ดีล BOGO screen to create, edit, list and delete
 *              BOGO deals without editing code, plus a usage report. Deals are
 *              stored in the `amouriq_bogo_deals` option; the engine in
 *              amouriq-bogo-deals.php reads that option, and falls back to
 *              amouriq-bogo/config.php only until the first save.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/amouriq-bogo/coupon-core.php';

// How many Condition rows the form offers.
const AMOURIQ_BOGO_CONDITION_ROWS = 5;

add_action( 'admin_menu', 'amouriq_bogo_admin_menu' );
add_action( 'admin_init', 'amouriq_bogo_admin_handle' );

function amouriq_bogo_admin_menu() {
	add_submenu_page(
		'woocommerce',
		'ดีล BOGO',
		'ดีล BOGO',
		'manage_woocommerce',
		'amouriq-bogo',
		'amouriq_bogo_admin_page'
	);
}

function amouriq_bogo_type_labels() {
	return array(
		'same_product'           => 'ซื้อ X แถม Y (สินค้าเดียวกัน)',
		'product_pair'           => 'ซื้อ A แล้วเลือก B ลดราคา',
		'category_cheapest_free' => 'ครบ N ชิ้นในหมวด ชิ้นถูกสุดลด',
		'cheapest_free'          => 'ครบ N ชิ้นในตะกร้า (ทุกหมวด) ชิ้นถูกสุดลด',
		'quantity_tier'          => 'ซื้อตามจำนวน ลดเป็นขั้น (รวมหลายสินค้า)',
		'spend_threshold'        => 'ซื้อครบ X บาท ลด Y% หรือ Y บาท',
		'spend_gift'             => 'ซื้อครบ X บาท เลือกของแถมฟรี',
		'fixed_price_item'       => 'ราคาต่อชิ้นคงที่ (สินค้าที่เลือกเหลือชิ้นละ ฿Y)',
		'amount_off_item'        => 'ลดบาทต่อชิ้น (ทุกชิ้นที่เลือกลดชิ้นละ ฿Y)',
		'bundle_price'           => 'ราคาชุด (ซื้อ N ชิ้นในราคา ฿Y)',
		'free_shipping'          => 'ส่งฟรีตามเงื่อนไข (ไม่ลดราคาสินค้า)',
	);
}

/**
 * A date ('Y-m-d') or a date and time ('Y-m-d H:i', or the 'T' form a
 * datetime-local field sends). Returns it normalised, '' when empty, false when invalid.
 */
function amouriq_bogo_valid_date( $value ) {
	$value = str_replace( 'T', ' ', trim( (string) $value ) );
	if ( '' === $value ) {
		return '';
	}
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})( ([01]\d|2[0-3]):([0-5]\d))?$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return false;
	}
	return $value;
}

/** The ticked product/variation IDs posted under $key, as a clean list of positive integers. */
function amouriq_bogo_posted_ids( array $in, $key ) {
	return isset( $in[ $key ] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) $in[ $key ] ) ) ) ) : array();
}


/**
 * The Condition rows posted by the form (`cond_type_N`, `cond_op_N`, `cond_value_N`, N = 1 to 5)
 * as a stored set, null when no row is used, or a string with the error to show.
 */
function amouriq_bogo_build_conditions( array $in ) {
	$rows = array();
	for ( $n = 1; $n <= AMOURIQ_BOGO_CONDITION_ROWS; $n++ ) {
		$type = isset( $in[ 'cond_type_' . $n ] ) ? sanitize_key( wp_unslash( $in[ 'cond_type_' . $n ] ) ) : '';
		if ( '' === $type ) {
			continue;
		}
		$op     = isset( $in[ 'cond_op_' . $n ] ) ? sanitize_key( wp_unslash( $in[ 'cond_op_' . $n ] ) ) : '';
		$text   = isset( $in[ 'cond_value_' . $n ] ) ? sanitize_textarea_field( wp_unslash( $in[ 'cond_value_' . $n ] ) ) : '';
		$parsed = amouriq_cond_parse_row( $type, $op, $text );
		if ( isset( $parsed['error'] ) ) {
			return 'เงื่อนไขข้อ ' . $n . ': ' . $parsed['error'];
		}
		$row = $parsed['row'];
		if ( 'cart_has_category' === $row['type'] ) {
			$ids = array();
			foreach ( $row['value'] as $token ) {
				$term = ctype_digit( (string) $token ) ? get_term( (int) $token, 'product_cat' ) : get_term_by( 'slug', sanitize_title( $token ), 'product_cat' );
				if ( ! $term || is_wp_error( $term ) ) {
					return 'เงื่อนไขข้อ ' . $n . ': ไม่พบหมวดสินค้า "' . $token . '"';
				}
				$ids[] = (int) $term->term_id;
			}
			$row['value'] = array_values( array_unique( $ids ) );
		}
		$rows[] = $row;
	}
	if ( ! $rows ) {
		return null;
	}
	return array( 'match' => ( isset( $in['cond_match'] ) && 'any' === $in['cond_match'] ) ? 'any' : 'all', 'rows' => $rows );
}

/** The stored Condition set as the form shows it: category IDs back to slugs. */
function amouriq_bogo_conditions_for_form( $set ) {
	if ( empty( $set['rows'] ) ) {
		return array( 'match' => 'all', 'rows' => array() );
	}
	foreach ( $set['rows'] as $i => $row ) {
		if ( 'cart_has_category' === $row['type'] ) {
			$set['rows'][ $i ]['value'] = array_map(
				static function ( $id ) {
					$term = get_term( (int) $id, 'product_cat' );
					return ( $term && ! is_wp_error( $term ) ) ? $term->slug : (string) $id;
				},
				(array) $row['value']
			);
		}
	}
	return $set;
}


/** The Deal types whose lines are picked by a Product scope (and the ticked lists). */
function amouriq_bogo_scope_types() {
	return array( 'spend_threshold', 'quantity_tier', 'fixed_price_item', 'amount_off_item', 'bundle_price' );
}

/**
 * The Product scope posted by the form as a stored scope, null when empty, or an error string.
 * Include: categories, tags, one attribute with its values, SKUs, "only items on sale".
 * Exclude: categories, tags, SKUs. (Ticked products are the separate product_ids / excluded_ids.)
 */
function amouriq_bogo_build_scope( array $in ) {
	$include = array();
	$exclude = array();

	foreach ( array( 'include' => &$include, 'exclude' => &$exclude ) as $side => &$target ) {
		$cats = isset( $in[ 'scope_' . $side . '_categories' ] ) ? array_map( 'sanitize_title', (array) wp_unslash( $in[ 'scope_' . $side . '_categories' ] ) ) : array();
		foreach ( $cats as $slug ) {
			if ( '' !== $slug && ! term_exists( $slug, 'product_cat' ) ) {
				return 'ไม่พบหมวดสินค้า slug นี้: ' . $slug;
			}
		}
		$cats = array_values( array_filter( $cats ) );
		if ( $cats ) {
			$target['categories'] = $cats;
		}
		$tags = isset( $in[ 'scope_' . $side . '_tags' ] ) ? array_map( 'sanitize_title', amouriq_cond_split( wp_unslash( $in[ 'scope_' . $side . '_tags' ] ) ) ) : array();
		if ( array_filter( $tags ) ) {
			$target['tags'] = array_values( array_filter( $tags ) );
		}
		$skus = isset( $in[ 'scope_' . $side . '_skus' ] ) ? array_map( 'sanitize_text_field', amouriq_cond_split( wp_unslash( $in[ 'scope_' . $side . '_skus' ] ) ) ) : array();
		if ( $skus ) {
			$target['skus'] = array_values( $skus );
		}
	}
	unset( $target );

	$taxonomy = isset( $in['scope_include_attribute'] ) ? sanitize_key( wp_unslash( $in['scope_include_attribute'] ) ) : '';
	$values   = isset( $in['scope_include_attribute_values'] ) ? array_map( 'sanitize_title', amouriq_cond_split( wp_unslash( $in['scope_include_attribute_values'] ) ) ) : array();
	if ( '' !== $taxonomy && array_filter( $values ) ) {
		if ( ! taxonomy_exists( $taxonomy ) || 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return 'ไม่พบคุณสมบัติสินค้า (attribute) นี้: ' . $taxonomy;
		}
		$include['attributes'] = array( $taxonomy => array_values( array_filter( $values ) ) );
	} elseif ( '' !== $taxonomy xor (bool) array_filter( $values ) ) {
		return 'คุณสมบัติสินค้า: ต้องเลือกทั้งชนิดและใส่ค่า (เช่น 250-ml) ให้ครบ หรือเว้นว่างทั้งสองช่อง';
	}
	if ( ! empty( $in['scope_include_on_sale_only'] ) ) {
		$include['on_sale_only'] = true;
	}

	$scope = array();
	if ( $include ) {
		$scope['include'] = $include;
	}
	if ( $exclude ) {
		$scope['exclude'] = $exclude;
	}
	return $scope ? $scope : null;
}

/** A stored Product scope as short Thai text for the rule list ("" when none). */
function amouriq_bogo_scope_text( $scope ) {
	if ( empty( $scope ) ) {
		return '';
	}
	$parts = array();
	$names = static function ( $slugs ) {
		return implode( ', ', array_map( 'amouriq_bogo_cat_name', (array) $slugs ) );
	};
	$inc = isset( $scope['include'] ) ? $scope['include'] : array();
	$exc = isset( $scope['exclude'] ) ? $scope['exclude'] : array();
	if ( ! empty( $inc['categories'] ) ) {
		$parts[] = 'หมวด ' . $names( $inc['categories'] );
	}
	if ( ! empty( $inc['tags'] ) ) {
		$parts[] = 'แท็ก ' . implode( ', ', $inc['tags'] );
	}
	if ( ! empty( $inc['attributes'] ) ) {
		foreach ( $inc['attributes'] as $taxonomy => $values ) {
			$parts[] = str_replace( 'pa_', '', $taxonomy ) . ' ' . implode( '/', $values );
		}
	}
	if ( ! empty( $inc['skus'] ) ) {
		$parts[] = 'SKU ' . implode( ', ', $inc['skus'] );
	}
	if ( ! empty( $inc['on_sale_only'] ) ) {
		$parts[] = 'เฉพาะสินค้าลดราคา';
	}
	if ( ! empty( $exc['categories'] ) ) {
		$parts[] = 'ยกเว้นหมวด ' . $names( $exc['categories'] );
	}
	if ( ! empty( $exc['tags'] ) ) {
		$parts[] = 'ยกเว้นแท็ก ' . implode( ', ', $exc['tags'] );
	}
	if ( ! empty( $exc['skus'] ) ) {
		$parts[] = 'ยกเว้น SKU ' . implode( ', ', $exc['skus'] );
	}
	return implode( ' · ', $parts );
}

/**
 * Validate the submitted form. Returns a clean deal array, or a string with
 * the error message to show the admin.
 */
function amouriq_bogo_build_deal( array $in ) {
	$types = amouriq_bogo_type_labels();
	$type  = isset( $in['type'] ) ? sanitize_key( $in['type'] ) : '';
	if ( ! isset( $types[ $type ] ) ) {
		return 'เลือกประเภทดีลไม่ถูกต้อง';
	}

	$trigger = ( isset( $in['trigger'] ) && 'coupon' === $in['trigger'] ) ? 'coupon' : 'auto';
	$label   = isset( $in['label'] ) ? sanitize_text_field( wp_unslash( $in['label'] ) ) : '';

	$deal = array( 'type' => $type, 'label' => $label, 'trigger' => $trigger );

	$scope = in_array( $type, amouriq_bogo_scope_types(), true ) ? amouriq_bogo_build_scope( $in ) : null;
	if ( is_string( $scope ) ) {
		return $scope;
	}

	if ( 'coupon' === $trigger ) {
		$code = isset( $in['coupon_code'] ) ? wc_format_coupon_code( wp_unslash( $in['coupon_code'] ) ) : '';
		// Whether the code is usable (taken by something unrelated, invalid) is decided once, by amouriq_bogo_coupon_prepare().
		if ( '' === $code ) {
			return 'เลือกให้ทำงานด้วยคูปองแต่ยังไม่ได้ใส่รหัสคูปอง';
		}
		$deal['coupon_code'] = $code;
	}

	$from = amouriq_bogo_valid_date( $in['valid_from'] ?? '' );
	$to   = amouriq_bogo_valid_date( $in['valid_to'] ?? '' );
	if ( false === $from || false === $to ) {
		return 'รูปแบบวันที่/เวลาไม่ถูกต้อง';
	}
	if ( $from && $to && amouriq_bogo_core_full_time( $from, false ) > amouriq_bogo_core_full_time( $to, true ) ) {
		return 'เวลาเริ่มต้องไม่เกินเวลาสิ้นสุด';
	}
	if ( $from ) {
		$deal['valid_from'] = $from;
	}
	if ( $to ) {
		$deal['valid_to'] = $to;
	}

	$int = function ( $key, $default = 0 ) use ( $in ) {
		return isset( $in[ $key ] ) && '' !== $in[ $key ] ? absint( $in[ $key ] ) : $default;
	};

	$max_customer = $int( 'max_uses_per_customer', 0 );
	if ( $max_customer > 0 ) {
		$deal['max_uses_per_customer'] = $max_customer;
	}
	$max_order = $int( 'max_per_order', 0 );
	if ( $max_order > 0 ) {
		$deal['max_per_order'] = $max_order;
	}

	$percent = isset( $in['get_discount_percent'] ) && '' !== $in['get_discount_percent']
		? (float) $in['get_discount_percent'] : 100;
	if ( $percent < 0 || $percent > 100 ) {
		return 'ส่วนลดต้องอยู่ระหว่าง 0 ถึง 100';
	}
	$deal['get_discount_percent'] = $percent;

	if ( 'same_product' === $type ) {
		$pid = $int( 'product_id' );
		if ( ! $pid || ! wc_get_product( $pid ) ) {
			return 'ไม่พบสินค้า/variation ID นี้: ' . $pid;
		}
		$deal['product_id'] = $pid;
		$deal['buy_qty']    = max( 1, $int( 'buy_qty', 1 ) );
		$deal['get_qty']    = max( 1, $int( 'get_qty', 1 ) );
	}

	if ( 'product_pair' === $type ) {
		$buy = $int( 'buy_product_id' );
		if ( ! $buy || ! wc_get_product( $buy ) ) {
			return 'ไม่พบสินค้า A (buy) ID นี้: ' . $buy;
		}
		$get_ids = amouriq_bogo_posted_ids( $in, 'get_product_ids' );
		if ( ! $get_ids ) {
			return 'ติ๊กเลือกสินค้า B ที่ให้ลูกค้าเลือกอย่างน้อย 1 รายการ';
		}
		$mode  = ( isset( $in['get_mode'] ) && 'baht' === $in['get_mode'] ) ? 'baht' : 'percent';
		$value = isset( $in['get_value'] ) && '' !== $in['get_value'] ? (float) $in['get_value'] : 0;
		if ( $value <= 0 || ( 'percent' === $mode && $value > 100 ) ) {
			return 'ส่วนลดของสินค้า B ต้องมากกว่า 0 (เป็น % ต้องไม่เกิน 100)';
		}
		$deal['buy_product_id']  = $buy;
		$deal['get_product_ids'] = $get_ids;
		$deal['buy_qty']         = max( 1, $int( 'buy_qty', 1 ) );
		$deal['get_qty']         = max( 1, $int( 'get_qty', 1 ) );
		$deal['get_mode']        = $mode;
		$deal['get_value']       = $value;
		// Replaced by get_mode / get_value.
		unset( $deal['get_discount_percent'] );
	}

	if ( 'category_cheapest_free' === $type ) {
		$slug = isset( $in['category'] ) ? sanitize_title( wp_unslash( $in['category'] ) ) : '';
		if ( '' === $slug || ! term_exists( $slug, 'product_cat' ) ) {
			return 'ไม่พบหมวดสินค้า slug นี้: ' . $slug;
		}
		$deal['category'] = $slug;
		$deal['buy_qty']  = max( 1, $int( 'buy_qty', 3 ) );
	}

	if ( 'cheapest_free' === $type ) {
		// Category is optional here: empty means any item in the cart.
		$slug = isset( $in['category'] ) ? sanitize_title( wp_unslash( $in['category'] ) ) : '';
		if ( '' !== $slug ) {
			if ( ! term_exists( $slug, 'product_cat' ) ) {
				return 'ไม่พบหมวดสินค้า slug นี้: ' . $slug;
			}
			$deal['category'] = $slug;
		}
		$deal['buy_qty'] = max( 1, $int( 'buy_qty', 3 ) );
	}

	if ( 'quantity_tier' === $type ) {
		$product_ids = amouriq_bogo_posted_ids( $in, 'product_ids' );
		if ( ! $product_ids && ! $scope ) {
			return 'ติ๊กเลือกสินค้าที่ร่วมรายการอย่างน้อย 1 รายการ หรือกำหนดขอบเขตสินค้า (หมวด แท็ก ขนาด SKU)';
		}
		$tiers = array();
		for ( $n = 1; $n <= 5; $n++ ) {
			$min = $int( 'tier_min_' . $n, 0 );
			$pct = isset( $in[ 'tier_pct_' . $n ] ) ? (float) $in[ 'tier_pct_' . $n ] : 0;
			if ( ! $min && '' === ( $in[ 'tier_pct_' . $n ] ?? '' ) ) {
				continue;
			}
			if ( $min < 1 || $pct <= 0 || $pct > 100 ) {
				return 'ขั้นที่ ' . $n . ' ต้องมีจำนวนขั้นต่ำมากกว่า 0 และส่วนลด 1–100%';
			}
			$tiers[] = array( 'min' => $min, 'percent' => $pct );
		}
		if ( empty( $tiers ) ) {
			return 'ต้องกรอกอย่างน้อย 1 ขั้น';
		}
		usort( $tiers, function ( $a, $b ) { return $a['min'] <=> $b['min']; } );
		if ( $product_ids ) {
			$deal['product_ids'] = $product_ids;
		}
		$deal['tiers'] = $tiers;
		$excluded = amouriq_bogo_posted_ids( $in, 'excluded_ids' );
		if ( $excluded ) {
			$deal['excluded_ids'] = $excluded;
		}
		// The percent field shared by the other types does not apply here.
		unset( $deal['get_discount_percent'] );
	}

	if ( 'free_shipping' === $type ) {
		$min = ( isset( $in['min_spend'] ) && '' !== $in['min_spend'] ) ? (float) $in['min_spend'] : 0.0;
		if ( $min < 0 ) {
			return 'ยอดซื้อขั้นต่ำต้องไม่ติดลบ';
		}
		if ( $min > 0 ) {
			$deal['min_spend'] = $min;
		}
		unset( $deal['get_discount_percent'] );
	}

	if ( 'spend_gift' === $type ) {
		$min = isset( $in['min_spend'] ) ? (float) $in['min_spend'] : 0;
		if ( $min <= 0 ) {
			return 'ใส่ยอดซื้อขั้นต่ำมากกว่า 0 บาท';
		}
		$gift_ids = amouriq_bogo_posted_ids( $in, 'gift_ids' );
		if ( ! $gift_ids ) {
			return 'ติ๊กเลือกของแถมอย่างน้อย 1 รายการ';
		}
		$deal['min_spend'] = $min;
		$deal['gift_ids']  = $gift_ids;
		$deal['gift_qty']  = max( 1, $int( 'gift_qty', 1 ) );
		unset( $deal['get_discount_percent'] );
	}

	if ( 'spend_threshold' === $type ) {
		$min = isset( $in['min_spend'] ) ? (float) $in['min_spend'] : 0;
		if ( $min <= 0 ) {
			return 'ใส่ยอดซื้อขั้นต่ำมากกว่า 0 บาท';
		}
		$mode  = ( isset( $in['mode'] ) && 'baht' === $in['mode'] ) ? 'baht' : 'percent';
		$value = isset( $in['value'] ) ? (float) $in['value'] : 0;
		if ( $value <= 0 || ( 'percent' === $mode && $value > 100 ) ) {
			return 'ส่วนลดต้องมากกว่า 0 (เป็น % ต้องไม่เกิน 100)';
		}
		$deal['min_spend'] = $min;
		$deal['mode']      = $mode;
		$deal['value']     = $value;
		if ( amouriq_bogo_posted_ids( $in, 'product_ids' ) ) {
			$deal['product_ids'] = amouriq_bogo_posted_ids( $in, 'product_ids' );
		}
		if ( amouriq_bogo_posted_ids( $in, 'excluded_ids' ) ) {
			$deal['excluded_ids'] = amouriq_bogo_posted_ids( $in, 'excluded_ids' );
		}
		// The percent field shared by the other types does not apply here.
		unset( $deal['get_discount_percent'] );
	}

	if ( in_array( $type, array( 'fixed_price_item', 'amount_off_item', 'bundle_price' ), true ) ) {
		$money = static function ( $key ) use ( $in ) {
			return isset( $in[ $key ] ) && '' !== $in[ $key ] ? (float) $in[ $key ] : 0.0;
		};
		if ( 'amount_off_item' === $type ) {
			if ( $money( 'off_value' ) <= 0 ) {
				return 'ใส่จำนวนบาทที่ลดต่อชิ้นมากกว่า 0';
			}
			$deal['value'] = $money( 'off_value' );
		} else {
			if ( $money( 'price' ) <= 0 ) {
				return 'ใส่ราคามากกว่า 0 บาท';
			}
			$deal['price'] = $money( 'price' );
		}
		if ( 'bundle_price' === $type ) {
			$deal['bundle_qty'] = $int( 'bundle_qty', 0 );
			if ( $deal['bundle_qty'] < 2 ) {
				return 'ชุดต้องมีอย่างน้อย 2 ชิ้น';
			}
		}
		if ( amouriq_bogo_posted_ids( $in, 'product_ids' ) ) {
			$deal['product_ids'] = amouriq_bogo_posted_ids( $in, 'product_ids' );
		}
		if ( amouriq_bogo_posted_ids( $in, 'excluded_ids' ) ) {
			$deal['excluded_ids'] = amouriq_bogo_posted_ids( $in, 'excluded_ids' );
		}
		unset( $deal['get_discount_percent'] );
	}

	if ( $scope ) {
		$deal['scope'] = $scope;
	}

	$conditions = amouriq_bogo_build_conditions( $in );
	if ( is_string( $conditions ) ) {
		return $conditions;
	}
	if ( $conditions ) {
		$deal['conditions'] = $conditions;
	}

	if ( ! empty( $in['exclude_sale'] ) ) {
		$deal['exclude_sale'] = true;
	}
	if ( ! empty( $in['no_combine'] ) ) {
		$deal['no_combine'] = true;
	}
	if ( ! empty( $in['new_customer_only'] ) ) {
		$deal['new_customer_only'] = true;
	}
	if ( ! empty( $in['exclusive'] ) ) {
		$deal['exclusive'] = true;
	}

	if ( 'spend_gift' === $type ) {
		// A gift is a free line, not a discount: sale items, per-customer and per-order limits do not apply to it.
		unset( $deal['exclude_sale'], $deal['max_uses_per_customer'], $deal['max_per_order'], $deal['exclusive'] );
	}

	if ( '' === $label ) {
		$deal['label'] = 'Deal discount';
	}

	return $deal;
}

/** Meta on a coupon that a Deal created (its value is the Deal id): only such a coupon is edited from the Deal form. */
const AMOURIQ_BOGO_COUPON_META = '_amouriq_created_by_deal';

/** Whether a coupon code is taken: null, or array( 'id', 'status' ) for the coupon post, trash included. */
function amouriq_bogo_coupon_lookup( $code ) {
	$ids = get_posts(
		array(
			'post_type'   => 'shop_coupon',
			'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'title'       => $code,
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	if ( ! $ids ) {
		return null;
	}
	$owner = '';
	if ( get_post_meta( $ids[0], 'amouriq_points_user', true ) || get_post_meta( $ids[0], 'amouriq_points_reward', true ) ) {
		$owner = 'points';
	} else {
		$owner = (string) get_post_meta( $ids[0], AMOURIQ_BOGO_COUPON_META, true );
	}
	return array( 'id' => (int) $ids[0], 'status' => get_post_status( $ids[0] ), 'owner' => $owner );
}

/**
 * Check the coupon part of a Deal form before anything is saved (ADR-0009). Returns a Thai
 * error string, null when the Deal has no coupon, or the plan array( 'decision', 'expires', 'limit' )
 * for amouriq_bogo_coupon_commit(). Nothing is written here.
 */
function amouriq_bogo_coupon_prepare( array $deal, array $in, $deal_id = '' ) {
	if ( empty( $deal['trigger'] ) || 'coupon' !== $deal['trigger'] ) {
		return null;
	}
	$decision = amouriq_bogo_core_coupon_decision( isset( $deal['coupon_code'] ) ? $deal['coupon_code'] : '', 'amouriq_bogo_coupon_lookup', 'save', $deal_id );
	if ( 'refuse' === $decision['action'] ) {
		return $decision['message'];
	}
	$expires = amouriq_bogo_valid_date( isset( $in['coupon_expires'] ) ? $in['coupon_expires'] : '' );
	if ( false === $expires || ( $expires && strlen( $expires ) > 10 ) ) {
		return 'วันหมดอายุของคูปองต้องเป็นวันที่ (ปปปป-ดด-วว)';
	}
	$limit = isset( $in['coupon_limit'] ) ? trim( (string) $in['coupon_limit'] ) : '';
	if ( '' !== $limit && ( ! ctype_digit( $limit ) || (int) $limit > 1000000 ) ) {
		return 'จำนวนครั้งที่ใช้คูปองได้ต้องเป็นจำนวนเต็ม 0 ขึ้นไป (เว้นว่าง = ไม่จำกัด)';
	}
	return array(
		'decision' => $decision,
		'expires'  => $expires,
		'limit'    => '' === $limit ? 0 : (int) $limit,
		'posted'   => isset( $in['coupon_expires'] ) || isset( $in['coupon_limit'] ),
	);
}

/**
 * Do the plan: create the empty coupon, or link to the existing one. The expiry and usage limit
 * are written only to a coupon this Deal (or a Deal before it) created; a coupon made elsewhere is
 * left exactly as it is.
 */
function amouriq_bogo_coupon_commit( array $deal, array $plan ) {
	$decision = $plan['decision'];
	if ( 'create' === $decision['action'] ) {
		$coupon = new WC_Coupon();
		$coupon->set_code( $decision['code'] );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( 0 );
		$coupon->add_meta_data( AMOURIQ_BOGO_COUPON_META, $deal['id'], true );
	} else {
		$coupon = new WC_Coupon( $decision['coupon_id'] );
		if ( ! $coupon->get_meta( AMOURIQ_BOGO_COUPON_META ) || ! $plan['posted'] ) {
			return;
		}
	}
	$coupon->set_date_expires( $plan['expires'] ? $plan['expires'] : null );
	$coupon->set_usage_limit( $plan['limit'] );
	$coupon->save();
}

/** For the form: expiry and usage limit of the Deal's coupon, and whether the form may edit them. */
function amouriq_bogo_coupon_form_values( $deal ) {
	$none = array( 'editable' => true, 'expires' => '', 'limit' => '', 'exists' => false );
	if ( empty( $deal['coupon_code'] ) ) {
		return $none;
	}
	$found = amouriq_bogo_coupon_lookup( $deal['coupon_code'] );
	if ( ! $found ) {
		return $none;
	}
	$coupon  = new WC_Coupon( $found['id'] );
	$expires = $coupon->get_date_expires();
	return array(
		'editable' => (bool) $coupon->get_meta( AMOURIQ_BOGO_COUPON_META ),
		'expires'  => $expires ? $expires->date( 'Y-m-d' ) : '',
		'limit'    => $coupon->get_usage_limit() ? (string) $coupon->get_usage_limit() : '',
		'exists'   => true,
		'edit_url' => get_edit_post_link( $found['id'], 'raw' ),
	);
}

/**
 * A copied Deal gets its own new empty coupon (code plus -2, -3 ...), with the original coupon's
 * expiry and usage limit. Returns the new code, or '' when the Deal has no coupon.
 */
function amouriq_bogo_coupon_copy( array $copy, array $original ) {
	if ( empty( $copy['trigger'] ) || 'coupon' !== $copy['trigger'] || empty( $original['coupon_code'] ) ) {
		return '';
	}
	$decision = amouriq_bogo_core_coupon_decision( $original['coupon_code'], 'amouriq_bogo_coupon_lookup', 'copy' );
	if ( 'create' !== $decision['action'] ) {
		return '';
	}
	$source = amouriq_bogo_coupon_lookup( $original['coupon_code'] );
	$old    = $source ? new WC_Coupon( $source['id'] ) : null;
	$new    = new WC_Coupon();
	$new->set_code( $decision['code'] );
	$new->set_discount_type( 'fixed_cart' );
	$new->set_amount( 0 );
	$new->add_meta_data( AMOURIQ_BOGO_COUPON_META, $copy['id'], true );
	if ( $old ) {
		$new->set_date_expires( $old->get_date_expires() );
		$new->set_usage_limit( $old->get_usage_limit() );
	}
	$new->save();
	return $decision['code'];
}

function amouriq_bogo_admin_handle() {
	if ( ! isset( $_POST['amouriq_bogo_action'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	check_admin_referer( 'amouriq_bogo_save' );

	$deals  = amouriq_bogo_deals_stored();
	$action = sanitize_key( $_POST['amouriq_bogo_action'] );
	$uid    = get_current_user_id();
	$index  = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;
	$back   = admin_url( 'admin.php?page=amouriq-bogo' );

	// A second browser tab can hold an older list: act only on the Deal the page showed.
	if ( in_array( $action, array( 'delete', 'toggle_deal', 'update', 'duplicate', 'move_up', 'move_down' ), true ) && isset( $_POST['deal_id'] ) ) {
		if ( ! isset( $deals[ $index ] ) || ( isset( $deals[ $index ]['id'] ) ? $deals[ $index ]['id'] : '' ) !== sanitize_text_field( wp_unslash( $_POST['deal_id'] ) ) ) {
			set_transient( 'amouriq_bogo_error_' . $uid, 'รายการดีลเปลี่ยนไปแล้ว (อาจมีการแก้ไขหรือลบจากหน้าอื่น) ไม่ได้ทำรายการ กรุณาดูรายการล่าสุดแล้วลองใหม่', 60 );
			wp_safe_redirect( $back );
			exit;
		}
	}

	if ( 'delete' === $action ) {
		if ( isset( $deals[ $index ] ) ) {
			unset( $deals[ $index ] );
			set_transient( 'amouriq_bogo_notice_' . $uid, 'ลบดีลแล้ว', 60 );
		}
	} elseif ( 'toggle_deal' === $action ) {
		if ( isset( $deals[ $index ] ) ) {
			$on = ! isset( $deals[ $index ]['enabled'] ) || $deals[ $index ]['enabled'];
			$deals[ $index ]['enabled'] = ! $on;
			set_transient( 'amouriq_bogo_notice_' . $uid, $on ? 'ปิดดีลแล้ว' : 'เปิดดีลแล้ว', 60 );
		}
	} elseif ( 'duplicate' === $action && isset( $deals[ $index ] ) ) {
		$copy            = $deals[ $index ];
		$copy['id']      = 'b' . wp_generate_password( 10, false, false );
		$copy['label']   = ( isset( $copy['label'] ) ? $copy['label'] : '' ) . ' (สำเนา)';
		$copy['enabled'] = false; // a copy starts off, so it cannot change prices before it is checked
		$new_code        = amouriq_bogo_coupon_copy( $copy, $deals[ $index ] );
		if ( '' !== $new_code ) {
			$copy['coupon_code'] = $new_code;
		}
		array_splice( $deals, $index + 1, 0, array( $copy ) );
		set_transient( 'amouriq_bogo_notice_' . $uid, 'ทำสำเนาดีลแล้ว (ปิดอยู่ ตรวจแล้วค่อยเปิด)' . ( '' !== $new_code ? ' สร้างคูปองใหม่ "' . $new_code . '" ให้แล้ว' : '' ), 60 );
	} elseif ( in_array( $action, array( 'move_up', 'move_down' ), true ) && isset( $deals[ $index ] ) ) {
		$to = 'move_up' === $action ? $index - 1 : $index + 1;
		if ( isset( $deals[ $to ] ) ) {
			$swap             = $deals[ $to ];
			$deals[ $to ]     = $deals[ $index ];
			$deals[ $index ]  = $swap;
			set_transient( 'amouriq_bogo_notice_' . $uid, 'เปลี่ยนลำดับดีลแล้ว', 60 );
		}
	} elseif ( 'toggle_member' === $action ) {
		$turn_on = ! amouriq_member_privilege_enabled();
		update_option( 'amouriq_member_privilege', $turn_on ? 'yes' : 'no', false );
		set_transient( 'amouriq_bogo_notice_' . $uid, $turn_on ? 'เปิดสิทธิพิเศษสมาชิกแล้ว' : 'ปิดสิทธิพิเศษสมาชิกแล้ว', 60 );
		wp_safe_redirect( $back . '&tab=member' );
		exit;
	} elseif ( 'save_member' === $action ) {
		$percent = isset( $_POST['member_percent'] ) ? str_replace( ',', '', sanitize_text_field( wp_unslash( $_POST['member_percent'] ) ) ) : '';
		$min     = isset( $_POST['member_min_spend'] ) ? str_replace( ',', '', sanitize_text_field( wp_unslash( $_POST['member_min_spend'] ) ) ) : '';
		$from    = amouriq_bogo_valid_date( isset( $_POST['member_valid_from'] ) ? wp_unslash( $_POST['member_valid_from'] ) : '' );
		$to      = amouriq_bogo_valid_date( isset( $_POST['member_valid_to'] ) ? wp_unslash( $_POST['member_valid_to'] ) : '' );
		$scope   = amouriq_bogo_build_scope( $_POST );
		$conds   = amouriq_bogo_build_conditions( $_POST );
		$problem = '';
		if ( ! is_numeric( $percent ) || $percent <= 0 || $percent > 100 || ! is_numeric( $min ) || $min < 0 ) {
			$problem = 'เปอร์เซ็นต์ต้องมากกว่า 0 ถึง 100 และยอดขั้นต่ำต้องไม่ติดลบ';
		} elseif ( false === $from || false === $to ) {
			$problem = 'รูปแบบวันที่/เวลาไม่ถูกต้อง';
		} elseif ( $from && $to && amouriq_bogo_core_full_time( $from, false ) > amouriq_bogo_core_full_time( $to, true ) ) {
			$problem = 'เวลาเริ่มต้องไม่เกินเวลาสิ้นสุด';
		} elseif ( is_string( $scope ) ) {
			$problem = $scope;
		} elseif ( is_string( $conds ) ) {
			$problem = $conds;
		}
		if ( '' !== $problem ) {
			set_transient( 'amouriq_bogo_error_' . $uid, 'สิทธิพิเศษสมาชิก: ' . $problem . ' ไม่ได้บันทึก', 60 );
		} else {
			update_option( 'amouriq_member_percent', (string) round( (float) $percent, 2 ), false );
			update_option( 'amouriq_member_min_spend', (string) round( (float) $min, 2 ), false );
			update_option(
				'amouriq_member_extra',
				amouriq_bogo_member_extra_clean(
					array(
						'include_sale' => ! empty( $_POST['member_include_sale'] ),
						'label'        => isset( $_POST['member_label'] ) ? sanitize_text_field( wp_unslash( $_POST['member_label'] ) ) : '',
						'scope'        => $scope ? $scope : array(),
						'conditions'   => $conds ? $conds : array(),
						'valid_from'   => $from,
						'valid_to'     => $to,
					)
				),
				false
			);
			set_transient( 'amouriq_bogo_notice_' . $uid, 'บันทึกสิทธิพิเศษสมาชิกแล้ว: ' . amouriq_bogo_member_summary(), 60 );
		}
		wp_safe_redirect( $back . '&tab=member' );
		exit;
	} elseif ( 'save_payment_fee' === $action ) {
		$amount = isset( $_POST['fee_amount'] ) ? str_replace( ',', '', sanitize_text_field( wp_unslash( $_POST['fee_amount'] ) ) ) : '';
		if ( ! is_numeric( $amount ) || $amount <= 0 ) {
			set_transient( 'amouriq_bogo_error_' . $uid, 'ค่าธรรมเนียมเก็บเงินปลายทาง: จำนวนเงินต้องเป็นตัวเลขมากกว่า 0 ไม่ได้บันทึก', 60 );
		} else {
			update_option( 'amouriq_payment_fee_enabled', ! empty( $_POST['fee_enabled'] ) ? 'yes' : 'no', false );
			update_option( 'amouriq_payment_fee_amount', (string) round( (float) $amount, 2 ), false );
			set_transient( 'amouriq_bogo_notice_' . $uid, 'บันทึกค่าธรรมเนียมเก็บเงินปลายทางแล้ว', 60 );
		}
		wp_safe_redirect( $back . '&tab=fee' );
		exit;
	} elseif ( 'toggle_coupon' === $action ) {
		$post = get_post( isset( $_POST['coupon_id'] ) ? (int) $_POST['coupon_id'] : 0 );
		if ( $post && 'shop_coupon' === $post->post_type && in_array( $post->post_status, array( 'publish', 'draft' ), true ) ) {
			$turn_on = 'draft' === $post->post_status;
			wp_update_post( array( 'ID' => $post->ID, 'post_status' => $turn_on ? 'publish' : 'draft' ) );
			set_transient( 'amouriq_bogo_notice_' . $uid, $turn_on ? 'เปิดคูปองแล้ว' : 'ปิดคูปองแล้ว (เปลี่ยนเป็นฉบับร่าง)', 60 );
		}
		wp_safe_redirect( $back . '&tab=shipping' );
		exit;
	} elseif ( 'add' === $action ) {
		$result = amouriq_bogo_build_deal( $_POST );
		if ( is_string( $result ) ) {
			set_transient( 'amouriq_bogo_error_' . $uid, $result, 60 );
			wp_safe_redirect( $back );
			exit;
		}
		$plan = amouriq_bogo_coupon_prepare( $result, $_POST );
		if ( is_string( $plan ) ) {
			set_transient( 'amouriq_bogo_error_' . $uid, $plan, 60 );
			wp_safe_redirect( $back );
			exit;
		}
		$result['id'] = 'b' . wp_generate_password( 10, false, false );
		if ( $plan ) {
			amouriq_bogo_coupon_commit( $result, $plan );
		}
		$deals[]      = $result;
		set_transient( 'amouriq_bogo_notice_' . $uid, 'เพิ่มดีลแล้ว: ' . amouriq_bogo_summary( $result ), 60 );
	} elseif ( 'update' === $action ) {
		if ( ! isset( $deals[ $index ] ) ) {
			set_transient( 'amouriq_bogo_error_' . $uid, 'ไม่พบดีลที่จะแก้ไข', 60 );
			wp_safe_redirect( $back );
			exit;
		}
		$result = amouriq_bogo_build_deal( $_POST );
		if ( is_string( $result ) ) {
			set_transient( 'amouriq_bogo_error_' . $uid, $result, 60 );
			wp_safe_redirect( $back . '&edit=' . $index );
			exit;
		}
		$plan = amouriq_bogo_coupon_prepare( $result, $_POST, $deals[ $index ]['id'] );
		if ( is_string( $plan ) ) {
			set_transient( 'amouriq_bogo_error_' . $uid, $plan, 60 );
			wp_safe_redirect( $back . '&edit=' . $index );
			exit;
		}
		$result['id'] = $deals[ $index ]['id'];
		if ( $plan ) {
			amouriq_bogo_coupon_commit( $result, $plan );
		}
		if ( isset( $deals[ $index ]['enabled'] ) ) {
			$result['enabled'] = $deals[ $index ]['enabled'];
		}
		$deals[ $index ] = $result;
		set_transient( 'amouriq_bogo_notice_' . $uid, 'บันทึกการแก้ไขแล้ว: ' . amouriq_bogo_summary( $result ), 60 );
	}

	update_option( 'amouriq_bogo_deals', array_values( $deals ), false );
	wp_safe_redirect( $back );
	exit;
}

/**
 * Usage per deal from orders that count (not cancelled/failed/refunded).
 * Returns id => array( 'orders' => int, 'total' => float ). Total is the
 * discount before tax, as the engine computed it at checkout.
 */
function amouriq_bogo_report() {
	$report = array();
	$orders = wc_get_orders(
		array(
			'limit'      => -1,
			'status'     => amouriq_bogo_counted_statuses(),
			'meta_query' => array(
				array( 'key' => 'amouriq_bogo_discounts', 'compare' => 'EXISTS' ),
			),
		)
	);
	foreach ( $orders as $order ) {
		$used = $order->get_meta( 'amouriq_bogo_discounts' );
		if ( ! is_array( $used ) ) {
			continue;
		}
		foreach ( $used as $id => $amount ) {
			if ( ! isset( $report[ $id ] ) ) {
				$report[ $id ] = array( 'orders' => 0, 'total' => 0.0 );
			}
			$report[ $id ]['orders']++;
			$report[ $id ]['total'] += (float) $amount;
		}
	}
	return $report;
}

/**
 * Product or variation name for display, so the admin reads names, not IDs.
 */
function amouriq_bogo_pname( $id ) {
	$product = $id ? wc_get_product( $id ) : false;
	return $product ? $product->get_name() : 'สินค้า #' . (int) $id . ' (ไม่พบ)';
}

function amouriq_bogo_cat_name( $slug ) {
	$term = $slug ? get_term_by( 'slug', $slug, 'product_cat' ) : false;
	return $term ? $term->name : (string) $slug;
}

/**
 * One plain sentence describing what the deal does.
 */
function amouriq_bogo_detail( $deal ) {
	$type = isset( $deal['type'] ) ? $deal['type'] : '';
	$pct  = isset( $deal['get_discount_percent'] ) ? $deal['get_discount_percent'] : 100;
	$off  = ( 100 == $pct ) ? 'ฟรี' : 'ลด ' . $pct . '%';

	if ( 'same_product' === $type ) {
		return 'ซื้อ ' . $deal['buy_qty'] . ' แถม ' . $deal['get_qty'] . ' (' . $off . '): ' . amouriq_bogo_pname( $deal['product_id'] );
	}
	if ( 'product_pair' === $type ) {
		if ( ! empty( $deal['get_product_ids'] ) ) {
			$what = 'percent' === ( $deal['get_mode'] ?? 'percent' ) ? 'ลด ' . $deal['get_value'] . '%' : 'ลด ฿' . $deal['get_value'];
			return 'ซื้อ ' . amouriq_bogo_pname( $deal['buy_product_id'] ) . ' × ' . ( $deal['buy_qty'] ?? 1 ) . ' เลือก B ได้ ' . ( $deal['get_qty'] ?? 1 ) . ' ชิ้น (' . $what . ') จาก ' . count( $deal['get_product_ids'] ) . ' รายการ';
		}
		return 'ซื้อ ' . amouriq_bogo_pname( $deal['buy_product_id'] ) . ' × ' . ( $deal['buy_qty'] ?? 1 ) . ' ได้ ' . amouriq_bogo_pname( $deal['get_product_id'] ) . ' × ' . ( $deal['get_qty'] ?? 1 ) . ' (' . $off . ')';
	}
	if ( 'category_cheapest_free' === $type ) {
		return 'หมวด ' . amouriq_bogo_cat_name( $deal['category'] ) . ' ครบ ' . $deal['buy_qty'] . ' ชิ้น ชิ้นถูกสุด' . $off;
	}
	if ( 'cheapest_free' === $type ) {
		$scope = ! empty( $deal['category'] ) ? 'หมวด ' . amouriq_bogo_cat_name( $deal['category'] ) : 'ทุกหมวด';
		return $scope . ' ครบ ' . $deal['buy_qty'] . ' ชิ้น ชิ้นถูกสุด' . $off;
	}
	if ( 'spend_gift' === $type ) {
		return 'ซื้อครบ ฿' . $deal['min_spend'] . ' เลือกของแถมฟรีได้ ' . ( $deal['gift_qty'] ?? 1 ) . ' ชิ้น จาก ' . count( $deal['gift_ids'] ?? array() ) . ' รายการ';
	}
	if ( 'spend_threshold' === $type ) {
		$what  = 'percent' === ( $deal['mode'] ?? 'percent' ) ? $deal['value'] . '%' : '฿' . $deal['value'];
		$scope = ! empty( $deal['product_ids'] ) ? 'เฉพาะสินค้าที่เลือก ' . count( $deal['product_ids'] ) . ' รายการ' : 'ทุกสินค้า';
		$skip  = ! empty( $deal['excluded_ids'] ) ? ' (ยกเว้น ' . count( $deal['excluded_ids'] ) . ' รายการ)' : '';
		return 'ซื้อครบ ฿' . $deal['min_spend'] . ' ลด ' . $what . ' · ' . $scope . $skip;
	}
	if ( 'quantity_tier' === $type ) {
		$steps = array();
		foreach ( $deal['tiers'] ?? array() as $tier ) {
			$steps[] = $tier['min'] . ' ชิ้นลด ' . $tier['percent'] . '%';
		}
		if ( ! empty( $deal['product_ids'] ) ) {
			$what = 'สินค้าที่เลือก ' . count( $deal['product_ids'] ) . ' รายการ (นับรวมกัน)';
		} elseif ( ! empty( $deal['scope'] ) ) {
			$what = 'สินค้าตามขอบเขตที่กำหนด (นับรวมกัน)';
		} else {
			$what = amouriq_bogo_pname( isset( $deal['product_id'] ) ? $deal['product_id'] : 0 );
		}
		return $what . ': ' . implode( ' / ', $steps );
	}
	if ( 'free_shipping' === $type ) {
		return 'ส่งฟรี' . ( ! empty( $deal['min_spend'] ) ? ' เมื่อซื้อครบ ฿' . $deal['min_spend'] : '' ) . ' (ไม่ลดราคาสินค้า)';
	}
	if ( 'fixed_price_item' === $type ) {
		return 'ราคาต่อชิ้นเหลือ ฿' . ( $deal['price'] ?? 0 ) . ' (สินค้าที่แพงกว่านี้เท่านั้น)' . amouriq_bogo_scope_note( $deal );
	}
	if ( 'amount_off_item' === $type ) {
		return 'ลดชิ้นละ ฿' . ( $deal['value'] ?? 0 ) . amouriq_bogo_scope_note( $deal );
	}
	if ( 'bundle_price' === $type ) {
		return 'ซื้อ ' . ( $deal['bundle_qty'] ?? 0 ) . ' ชิ้น ในราคา ฿' . ( $deal['price'] ?? 0 ) . ' (คิดจากชิ้นที่แพงที่สุดก่อน)' . amouriq_bogo_scope_note( $deal );
	}
	return 'ไม่รู้จักประเภทนี้';
}

/** " · ทุกสินค้า" or a short note that the Deal covers a chosen scope, for the list. */
function amouriq_bogo_scope_note( $deal ) {
	if ( ! empty( $deal['scope'] ) || ! empty( $deal['product_ids'] ) ) {
		return ' · เฉพาะสินค้าที่กำหนด';
	}
	return ' · ทุกสินค้า';
}

/**
 * Short chips for the deal's options (trigger, limits, dates).
 */
function amouriq_bogo_chips( $deal ) {
	$chips = array();
	$chips[] = ( isset( $deal['trigger'] ) && 'coupon' === $deal['trigger'] ) ? 'ต้องใส่คูปอง ' . ( $deal['coupon_code'] ?? '' ) : 'อัตโนมัติ';
	if ( ! empty( $deal['exclude_sale'] ) ) {
		$chips[] = 'ไม่รวมสินค้าลดราคา';
	}
	if ( ! empty( $deal['no_combine'] ) ) {
		$chips[] = 'ไม่ใช้ร่วมกับคูปองอื่น';
	}
	if ( ! empty( $deal['new_customer_only'] ) ) {
		$chips[] = 'ลูกค้าใหม่เท่านั้น';
	}
	if ( ! empty( $deal['exclusive'] ) ) {
		$chips[] = 'Exclusive: ดีลถัดไปไม่ซ้อนบนสินค้าเดียวกัน';
	}
	if ( ! empty( $deal['scope'] ) ) {
		$chips[] = 'ขอบเขต: ' . amouriq_bogo_scope_text( $deal['scope'] );
	}
	if ( ! empty( $deal['conditions'] ) ) {
		$chips[] = amouriq_cond_describe( amouriq_bogo_conditions_for_form( $deal['conditions'] ) );
	}
	if ( ! empty( $deal['valid_from'] ) || ! empty( $deal['valid_to'] ) ) {
		$chips[] = ( $deal['valid_from'] ?? '…' ) . ' ถึง ' . ( $deal['valid_to'] ?? '…' );
	}
	if ( ! empty( $deal['max_uses_per_customer'] ) ) {
		$chips[] = 'ต่อลูกค้า ' . $deal['max_uses_per_customer'] . ' ครั้ง';
	}
	if ( ! empty( $deal['max_per_order'] ) ) {
		$chips[] = 'ต่อออเดอร์ ' . $deal['max_per_order'] . ' ครั้ง';
	}
	return $chips;
}

/**
 * Products and variations grouped by product, for the picker. Each option is
 * variation ID => label (size and price), so nobody has to look up an ID.
 */
function amouriq_bogo_product_groups() {
	$groups   = array();
	$products = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'type' => array( 'simple', 'variable' ) ) );
	foreach ( $products as $product ) {
		$name = $product->get_name();
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( ! $child ) {
					continue;
				}
				$label = $child->get_name();
				$groups[ $name ][ $child->get_id() ] = $label . ' — ฿' . wc_format_decimal( $child->get_price(), 0 );
			}
		} else {
			$groups[ $name ][ $product->get_id() ] = $name . ' — ฿' . wc_format_decimal( $product->get_price(), 0 );
		}
	}
	return $groups;
}

/** A stored start/end as the value of a datetime-local field. */
function amouriq_bogo_datetime_value( $value, $is_end ) {
	if ( '' === (string) $value ) {
		return '';
	}
	return str_replace( ' ', 'T', amouriq_bogo_core_full_time( $value, $is_end ) );
}

/** Stock label per product/variation ID, for the tick lists. */
function amouriq_bogo_stock_labels() {
	$labels = array();
	$names  = array( 'instock' => 'มีของ', 'outofstock' => 'หมด', 'onbackorder' => 'สั่งล่วงหน้า' );
	foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'type' => array( 'simple', 'variable' ) ) ) as $product ) {
		$ids = $product->is_type( 'variable' ) ? $product->get_children() : array( $product->get_id() );
		foreach ( $ids as $id ) {
			$item = wc_get_product( $id );
			if ( $item ) {
				$status         = $item->get_stock_status();
				$labels[ $id ] = isset( $names[ $status ] ) ? $names[ $status ] : $status;
			}
		}
	}
	return $labels;
}

/**
 * Tick list of products and variations, read live from WooCommerce, with each
 * item's stock status. A ticked ID that no longer exists stays visible and
 * ticked with the "not found" warning, so it can be unticked.
 */
function amouriq_bogo_product_checklist( $field, $selected, $groups, $stock, $hint ) {
	$selected = array_map( 'intval', (array) $selected );
	$known    = array();
	?>
	<div class="amq-check">
		<input type="search" class="amq-check-filter" placeholder="ค้นหาสินค้า…" aria-label="ค้นหาสินค้า">
		<div class="amq-check-list">
			<?php foreach ( $groups as $pname => $options ) : ?>
				<div class="amq-check-group">
					<strong><?php echo esc_html( $pname ); ?></strong>
					<?php foreach ( $options as $vid => $label ) :
						$known[] = (int) $vid;
						$out     = isset( $stock[ $vid ] ) && 'มีของ' !== $stock[ $vid ];
					?>
						<label class="<?php echo $out ? 'amq-out' : ''; ?>">
							<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[]" value="<?php echo esc_attr( $vid ); ?>" <?php checked( in_array( (int) $vid, $selected, true ) ); ?>>
							<?php echo esc_html( $label ); ?> <em>[<?php echo esc_html( $stock[ $vid ] ?? '?' ); ?>]</em>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
			<?php foreach ( array_diff( $selected, $known ) as $missing ) : ?>
				<label class="amq-missing">
					<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[]" value="<?php echo esc_attr( $missing ); ?>" checked>
					<?php echo esc_html( amouriq_bogo_pname( $missing ) ); ?> (ไม่พบในรายการ ติ๊กออกเพื่อลบ)
				</label>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php echo esc_html( $hint ); ?></p>
	</div>
	<?php
}

function amouriq_bogo_product_select( $field, $selected, $groups ) {
	$selected = (int) $selected;
	$found    = false;
	?>
	<select name="<?php echo esc_attr( $field ); ?>" class="amq-wide">
		<option value="">— เลือกสินค้า —</option>
		<?php foreach ( $groups as $pname => $options ) : ?>
			<optgroup label="<?php echo esc_attr( $pname ); ?>">
				<?php foreach ( $options as $vid => $label ) :
					if ( (int) $vid === $selected ) {
						$found = true;
					}
				?>
					<option value="<?php echo esc_attr( $vid ); ?>" <?php selected( (int) $vid === $selected ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</optgroup>
		<?php endforeach; ?>
		<?php if ( $selected && ! $found ) : ?>
			<option value="<?php echo esc_attr( $selected ); ?>" selected>ID <?php echo esc_html( $selected ); ?> (ไม่พบในรายการ)</option>
		<?php endif; ?>
	</select>
	<?php
}

/**
 * "Active" in the glossary sense, as far as it can be known without a cart:
 * Enabled, inside its dates, and (for a coupon Deal) the coupon is usable.
 * Returns 'ใช้งานอยู่' or the reason the deal is not working.
 */
function amouriq_bogo_deal_state( $deal ) {
	if ( isset( $deal['enabled'] ) && ! $deal['enabled'] ) {
		return 'ปิดอยู่';
	}
	$ctx = array( 'today' => current_time( 'Y-m-d' ), 'now' => current_time( 'Y-m-d H:i' ) );
	if ( ! amouriq_bogo_core_in_window( array_intersect_key( $deal, array( 'valid_from' => 1 ) ), $ctx ) ) {
		return 'ยังไม่ถึงเวลาเริ่ม';
	}
	if ( ! amouriq_bogo_core_in_window( array_intersect_key( $deal, array( 'valid_to' => 1 ) ), $ctx ) ) {
		return 'หมดช่วงเวลาแล้ว';
	}
	if ( isset( $deal['trigger'] ) && 'coupon' === $deal['trigger'] ) {
		$coupon_id = ! empty( $deal['coupon_code'] ) ? wc_get_coupon_id_by_code( $deal['coupon_code'] ) : 0;
		if ( ! $coupon_id || 'publish' !== get_post_status( $coupon_id ) ) {
			return 'คูปองไม่พร้อมใช้ (ไม่มี/ปิดอยู่)';
		}
		$coupon  = new WC_Coupon( $coupon_id );
		$expires = $coupon->get_date_expires();
		if ( $expires && $expires->getTimestamp() < time() ) {
			return 'คูปองหมดอายุแล้ว';
		}
		if ( $coupon->get_usage_limit() && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
			return 'คูปองใช้ครบจำนวนแล้ว';
		}
	}
	return 'ใช้งานอยู่';
}

function amouriq_bogo_page_url() {
	return admin_url( 'admin.php?page=amouriq-bogo' );
}

/**
 * Small POST button for one row action (toggle, delete).
 */
function amouriq_bogo_action_form( $action, $field, $value, $label, $class = 'button', $confirm = '', $deal_id = '' ) {
	?>
	<form method="post" action="<?php echo esc_url( amouriq_bogo_page_url() ); ?>" style="display:inline"<?php echo $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\');"' : ''; ?>>
		<?php wp_nonce_field( 'amouriq_bogo_save' ); ?>
		<input type="hidden" name="amouriq_bogo_action" value="<?php echo esc_attr( $action ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<?php if ( '' !== $deal_id ) : ?><input type="hidden" name="deal_id" value="<?php echo esc_attr( $deal_id ); ?>"><?php endif; ?>
		<button class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

function amouriq_bogo_badge( $state ) {
	if ( 'ใช้งานอยู่' === $state || 'เปิดอยู่' === $state ) {
		$class = 'on';
	} elseif ( 'ปิดอยู่' === $state ) {
		$class = 'off';
	} else {
		$class = 'warn';
	}
	return '<span class="amq-badge amq-' . $class . '">' . esc_html( $state ) . '</span>';
}

function amouriq_bogo_chips_html( $chips ) {
	$html = '';
	foreach ( $chips as $chip ) {
		$html .= '<span class="amq-chip">' . esc_html( $chip ) . '</span>';
	}
	return $html;
}

/** The tabs of the deals screen (the form for a deal has its own page, opened from the list). */
function amouriq_bogo_admin_tabs() {
	return array(
		'deals'    => 'ดีลทั้งหมด',
		'member'   => 'สิทธิพิเศษสมาชิก',
		'shipping' => 'ส่งฟรี',
		'fee'      => '⚙ ตั้งค่าทั่วไป',
	);
}

function amouriq_bogo_admin_tab() {
	// phpcs:ignore WordPress.Security.NonceVerification -- read-only
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'deals';
	return isset( amouriq_bogo_admin_tabs()[ $tab ] ) ? $tab : 'deals';
}

/** Quick starting points for the first deal: each one fills the new-deal form (see the script on the form). */
function amouriq_bogo_templates() {
	return array(
		array( 'key' => 'spend_percent', 'title' => 'ซื้อครบ ฿1,500 ลด 10%', 'text' => 'ส่วนลดเปอร์เซ็นต์เมื่อยอดซื้อถึงเป้า' ),
		array( 'key' => 'new_customer', 'title' => 'ลูกค้าใหม่ ลด 10% เมื่อซื้อครบ ฿500', 'text' => 'ให้เฉพาะคนที่ยังไม่เคยสั่งซื้อ ทำงานอัตโนมัติ' ),
		array( 'key' => 'buy_get', 'title' => 'ซื้อ 2 แถม 1', 'text' => 'สินค้าตัวเดียวกัน ชิ้นที่ 3 ฟรี' ),
		array( 'key' => 'free_shipping', 'title' => 'ส่งฟรีเมื่อซื้อครบ ฿799', 'text' => 'ค่าส่งเป็น 0 เมื่อยอดซื้อถึงเป้า' ),
		array( 'key' => 'gift', 'title' => 'ซื้อครบ ฿1,500 เลือกของแถมฟรี', 'text' => 'ลูกค้าเลือกของแถมเองในตะกร้า' ),
		array( 'key' => 'bundle', 'title' => 'ซื้อ 3 ชิ้น ในราคา ฿999', 'text' => 'ราคาชุดสำหรับสินค้าที่เลือก' ),
	);
}

/** The deals the owner made, as one table. Everything else (Member Privilege, free shipping) has its own tab. */
function amouriq_bogo_deals_table( $deals, $report, $types ) {
	$new_url = amouriq_bogo_page_url() . '&add=1#amq-form';
	if ( empty( $deals ) ) {
		?>
		<div class="amq-card amq-empty-state">
			<h2>ยังไม่มีดีล</h2>
			<p>เริ่มจากแม่แบบด้านล่าง (กดแล้วระบบกรอกให้ แก้ตัวเลขได้ก่อนบันทึก) หรือเพิ่มดีลเองตั้งแต่ต้น</p>
			<div class="amq-templates">
				<?php foreach ( amouriq_bogo_templates() as $tpl ) : ?>
					<a class="amq-template" href="<?php echo esc_url( $new_url . '&tpl=' . $tpl['key'] ); ?>"><strong><?php echo esc_html( $tpl['title'] ); ?></strong><span><?php echo esc_html( $tpl['text'] ); ?></span></a>
				<?php endforeach; ?>
			</div>
			<p><a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>">+ เพิ่มดีลเอง</a></p>
		</div>
		<?php
		return;
	}
	?>
	<div class="amq-scroll"><table class="widefat amq-table">
		<thead><tr><th>ดีล</th><th style="width:130px">สถานะ</th><th style="width:110px">ใช้แล้ว</th><th style="width:260px">จัดการ</th></tr></thead>
		<tbody>
		<?php foreach ( $deals as $i => $deal ) :
			$rep   = $report[ $deal['id'] ?? '' ] ?? array( 'orders' => 0, 'total' => 0 );
			$state = amouriq_bogo_deal_state( $deal );
			$is_on = ! isset( $deal['enabled'] ) || $deal['enabled'];
			$id    = isset( $deal['id'] ) ? $deal['id'] : '';
		?>
			<tr class="<?php echo $is_on ? '' : 'amq-dim'; ?>">
				<td>
					<strong><?php echo esc_html( $deal['label'] ?? '' ); ?></strong> <small class="amq-kind amq-kind-deal"><?php echo esc_html( $types[ $deal['type'] ] ?? $deal['type'] ); ?></small><br>
					<?php echo esc_html( amouriq_bogo_detail( $deal ) ); ?><br>
					<?php echo amouriq_bogo_chips_html( amouriq_bogo_chips( $deal ) ); // phpcs:ignore ?>
				</td>
				<td><?php echo amouriq_bogo_badge( $state ); // phpcs:ignore ?></td>
				<td><?php echo esc_html( $rep['orders'] ); ?> ออเดอร์<br><small>ลดรวม ฿<?php echo esc_html( number_format( $rep['total'], 2 ) ); ?></small></td>
				<td style="white-space:normal">
					<a class="button button-primary" href="<?php echo esc_url( amouriq_bogo_page_url() . '&edit=' . $i . '#amq-form' ); ?>">แก้ไข</a>
					<?php amouriq_bogo_action_form( 'toggle_deal', 'index', $i, $is_on ? 'ปิด' : 'เปิด', $is_on ? 'button' : 'button button-primary', '', $id ); ?>
					<?php amouriq_bogo_action_form( 'duplicate', 'index', $i, 'ทำสำเนา', 'button', '', $id ); ?>
					<span class="amq-order" title="ลำดับ: ดีลบนทำก่อนดีลล่าง (สำคัญเมื่อใช้ Exclusive)">
					<?php
					if ( $i > 0 ) {
						amouriq_bogo_action_form( 'move_up', 'index', $i, '↑', 'button', '', $id );
					}
					if ( $i < count( $deals ) - 1 ) {
						amouriq_bogo_action_form( 'move_down', 'index', $i, '↓', 'button', '', $id );
					}
					?>
					</span>
					<?php amouriq_bogo_action_form( 'delete', 'index', $i, 'ลบ', 'button button-link-delete', 'ลบดีลนี้?', $id ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table></div>
	<p class="description">ดีลที่อยู่ข้างบนทำงานก่อนดีลข้างล่าง (ใช้ลูกศร ↑ ↓ เรียงลำดับ) ส่วนลดรวมนับเฉพาะออเดอร์ที่ไม่ถูกยกเลิก ล้มเหลว หรือคืนเงิน</p>
	<?php
}

/** The Payment fee (cash on delivery): the owner's switch and amount. */
function amouriq_bogo_fee_panel() {
	$fee = function_exists( 'amouriq_payment_fee_settings' ) ? amouriq_payment_fee_settings() : array( 'enabled' => false, 'amount' => 30.0 );
	?>
	<div class="amq-card">
		<h2>ค่าธรรมเนียมเก็บเงินปลายทาง <?php echo amouriq_bogo_badge( $fee['enabled'] ? 'เปิดอยู่' : 'ปิดอยู่' ); // phpcs:ignore ?></h2>
		<p>เมื่อลูกค้าเลือกชำระเงินปลายทาง ระบบบวกค่าธรรมเนียมคงที่เข้ายอดรวม คิดแม้ส่งฟรี คิดหลังหักส่วนลดทั้งหมด ไม่มีภาษีเพิ่ม และไม่นับเป็นยอดซื้อสำหรับแต้มสะสมหรือเงื่อนไข "ซื้อครบ"</p>
		<form method="post" action="<?php echo esc_url( amouriq_bogo_page_url() . '&tab=fee' ); ?>">
			<?php wp_nonce_field( 'amouriq_bogo_save' ); ?>
			<input type="hidden" name="amouriq_bogo_action" value="save_payment_fee">
			<p><label><input type="checkbox" name="fee_enabled" value="1" <?php checked( $fee['enabled'] ); ?>> เก็บค่าธรรมเนียมเมื่อชำระเงินปลายทาง</label></p>
			<p><label>จำนวนเงิน (บาท) <input type="text" name="fee_amount" value="<?php echo esc_attr( amouriq_bogo_member_number( $fee['amount'] ) ); ?>" style="width:7em" required></label></p>
			<p><button type="submit" class="button button-primary">บันทึก</button></p>
		</form>
	</div>
	<?php
}

/** Free shipping in one place: the Flexible Shipping threshold (read only) and the coupons that give free shipping. */
function amouriq_bogo_shipping_panel() {
	$now       = current_time( 'timestamp' );
	$settings  = get_option( 'woocommerce_flexible_shipping_single_3_settings', array() );
	$threshold = is_array( $settings ) && isset( $settings['method_free_shipping'] ) ? $settings['method_free_shipping'] : null;
	$ship_url  = admin_url( 'admin.php?page=wc-settings&tab=shipping&instance_id=3' );
	?>
	<div class="amq-card">
		<h2>ส่งฟรีอัตโนมัติ</h2>
		<p>ยอดสั่งซื้อครบ <strong>฿<?php echo esc_html( null === $threshold ? '?' : $threshold ); ?></strong> ส่งฟรี <?php echo amouriq_bogo_badge( null === $threshold ? 'ตรวจไม่ได้' : 'ใช้งานอยู่' ); // phpcs:ignore ?> (ตั้งค่าที่ Flexible Shipping) <a class="button" href="<?php echo esc_url( $ship_url ); ?>">แก้ที่หน้าการจัดส่ง</a></p>
		<p class="description">อยากให้ส่งฟรีด้วยเงื่อนไขอื่น (ใส่คูปอง วันที่กำหนด ลูกค้าใหม่ ยอดซื้อสะสม) สร้างเป็นดีลชนิด "ส่งฟรีตามเงื่อนไข" ในแท็บ "ดีลทั้งหมด"</p>
	</div>
	<div class="amq-card">
		<h2>คูปองที่ให้ส่งฟรี</h2>
		<?php
		$coupons = get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1 ) );
		$rows    = 0;
		?>
		<div class="amq-scroll"><table class="widefat amq-table">
			<thead><tr><th>คูปอง</th><th style="width:140px">สถานะ</th><th style="width:110px">ใช้แล้ว</th><th style="width:230px">จัดการ</th></tr></thead>
			<tbody>
			<?php
			foreach ( $coupons as $post ) :
				$coupon = new WC_Coupon( $post->ID );
				if ( ! $coupon->get_free_shipping() ) {
					continue;
				}
				$rows++;
				$expires = $coupon->get_date_expires();
				$limit   = $coupon->get_usage_limit();
				$used    = $coupon->get_usage_count();
				if ( 'draft' === $post->post_status ) {
					$state = 'ปิดอยู่';
				} elseif ( $expires && $expires->getTimestamp() < $now ) {
					$state = 'หมดอายุแล้ว';
				} elseif ( $limit && $used >= $limit ) {
					$state = 'ใช้ครบจำนวนแล้ว';
				} else {
					$state = 'ใช้งานอยู่';
				}
				$is_on = 'draft' !== $post->post_status;
			?>
				<tr class="<?php echo $is_on ? '' : 'amq-dim'; ?>">
					<td><strong><?php echo esc_html( $coupon->get_code() ); ?></strong><br>หมดอายุ: <?php echo $expires ? esc_html( $expires->date_i18n( 'Y-m-d' ) ) : 'ไม่มีกำหนด'; ?></td>
					<td><?php echo amouriq_bogo_badge( $state ); // phpcs:ignore ?></td>
					<td><?php echo esc_html( $used ); ?>/<?php echo $limit ? esc_html( $limit ) : '∞'; ?> ครั้ง</td>
					<td style="white-space:normal">
						<?php amouriq_bogo_action_form( 'toggle_coupon', 'coupon_id', $post->ID, $is_on ? 'ปิด' : 'เปิด', $is_on ? 'button' : 'button button-primary' ); ?>
						<a class="button" href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">แก้ที่หน้าคูปอง</a>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="4" class="amq-empty">ยังไม่มีคูปองที่ติ๊ก "ส่งฟรี"</td></tr>
			<?php endif; ?>
			</tbody>
		</table></div>
		<p class="description">ปิดคูปอง = เปลี่ยนเป็นฉบับร่าง (ยังเปิดกลับได้) คูปองใหม่สร้างที่ การตลาด &gt; คูปอง แล้วติ๊ก "Allow free shipping"</p>
	</div>
	<?php
}

/**
 * Member Privilege: the discount a customer with an account gets, shown and edited like a Deal (name, percent, minimum,
 * Sale Price items, products it covers, who and when). It never stacks: the larger discount wins (ADR-0006).
 */
function amouriq_bogo_member_panel() {
	$on    = amouriq_member_privilege_enabled();
	$rule  = amouriq_bogo_member_rule();
	$cats  = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	$pct   = amouriq_bogo_member_number( $rule['percent'] );
	$min   = amouriq_bogo_member_number( $rule['min_spend'] );
	$name  = '' !== $rule['label'] ? $rule['label'] : 'สิทธิพิเศษสมาชิก';
	?>
	<div class="amq-card">
		<h2><?php echo esc_html( $name ); ?> <?php echo amouriq_bogo_badge( $on ? 'เปิดอยู่' : 'ปิดอยู่' ); // phpcs:ignore ?></h2>
		<p>ลูกค้าที่มีบัญชีได้ส่วนลด <strong><?php echo esc_html( $pct ); ?>%</strong> เมื่อยอดสินค้าครบ <strong>฿<?php echo esc_html( $min ); ?></strong> <?php echo $rule['include_sale'] ? 'ทุกรายการที่ร่วม (รวมสินค้าลดราคา)' : 'เฉพาะสินค้าราคาปกติ'; ?> ไม่ซ้อนกับดีลหรือคูปอง (ใช้ส่วนลดที่มากกว่า) ตั้งค่าด้านล่าง แล้วกดเปิด</p>
		<?php amouriq_bogo_action_form( 'toggle_member', 'noop', '1', $on ? 'ปิดสิทธิพิเศษสมาชิก' : 'เปิดสิทธิพิเศษสมาชิก', $on ? 'button' : 'button button-primary', $on ? '' : 'เปิดสิทธิพิเศษสมาชิก ' . $pct . '% ให้ลูกค้าทุกคนที่มีบัญชีทันที ใช่ไหม?' ); ?>
		<?php if ( ! $on ) : ?>
			<p class="description">ก่อนเปิด ตรวจ Discount Rules เดิมและ Points &amp; Rewards ว่าไม่ได้ลดซ้ำ และทดสอบเช็คเอาต์จริงด้วยบัญชีทดสอบ</p>
		<?php endif; ?>
	</div>

	<form method="post" action="<?php echo esc_url( amouriq_bogo_page_url() . '&tab=member' ); ?>" id="amq-member-form" class="amq-card">
		<?php wp_nonce_field( 'amouriq_bogo_save' ); ?>
		<input type="hidden" name="amouriq_bogo_action" value="save_member">
		<h2>ตั้งค่า</h2>
		<div class="amq-grid">
			<label class="k" for="member_label">ชื่อที่ลูกค้าเห็น</label>
			<div class="v"><input type="text" name="member_label" id="member_label" class="amq-wide" maxlength="60" placeholder="สิทธิพิเศษสมาชิก" value="<?php echo esc_attr( $rule['label'] ); ?>">
				<p class="description">ชื่อบรรทัดส่วนลดในตะกร้าและหน้าชำระเงิน เว้นว่าง = "สิทธิพิเศษสมาชิก"</p></div>

			<label class="k" for="member_percent">ส่วนลด</label>
			<div class="v"><input type="number" name="member_percent" id="member_percent" value="<?php echo esc_attr( $pct ); ?>" min="0.01" max="100" step="0.01" style="width:100px"> %</div>

			<label class="k" for="member_min_spend">ยอดซื้อขั้นต่ำ</label>
			<div class="v">฿ <input type="number" name="member_min_spend" id="member_min_spend" value="<?php echo esc_attr( $min ); ?>" min="0" step="0.01" style="width:120px">
				<p class="description">นับยอดสินค้าก่อนหักส่วนลด ไม่รวมค่าส่ง ตามราคาที่ลูกค้าเห็น (0 = ไม่มีขั้นต่ำ)</p></div>

			<span class="k" style="font-weight:600">สินค้าลดราคาอยู่แล้ว</span>
			<div class="v"><label><input type="checkbox" name="member_include_sale" value="1" <?php checked( $rule['include_sale'] ); ?>> ให้สิทธิพิเศษกับสินค้าที่ลดราคาอยู่แล้วด้วย</label>
				<p class="description">ไม่ติ๊ก = สินค้าลดราคาอยู่ไม่ได้ส่วนลดเพิ่ม (ค่าเริ่มต้น)</p></div>

			<label class="k" for="member_from">ใช้ได้ตั้งแต่</label>
			<div class="v"><input type="datetime-local" name="member_valid_from" id="member_from" value="<?php echo esc_attr( amouriq_bogo_datetime_value( $rule['valid_from'], false ) ); ?>"> <span class="description">เว้นว่าง = ทันที</span></div>
			<label class="k" for="member_to">ใช้ได้ถึง</label>
			<div class="v"><input type="datetime-local" name="member_valid_to" id="member_to" value="<?php echo esc_attr( amouriq_bogo_datetime_value( $rule['valid_to'], true ) ); ?>"> <span class="description">เว้นว่าง = ไม่มีวันหมด</span></div>
		</div>

		<h3>สินค้าที่ร่วมรายการ</h3>
		<div class="amq-grid"><span class="k">ขอบเขตสินค้า</span><div class="v">
			<?php amouriq_bogo_scope_fields( $rule['scope'], $cats ); ?>
		</div></div>

		<h3>ใครได้สิทธิ์ (เงื่อนไขเพิ่มเติม)</h3>
		<?php amouriq_bogo_condition_fields( amouriq_bogo_conditions_for_form( $rule['conditions'] ), 'สิทธิพิเศษสมาชิกใช้กับลูกค้าที่มีบัญชีเสมอ เงื่อนไขด้านล่างจำกัดให้แคบลงอีก เช่น เฉพาะผู้ที่เคยสั่งซื้อครบ 2 ครั้ง หรือบทบาท wholesale ไม่ใส่เลย = สมาชิกทุกคน' ); ?>

		<p style="margin-top:18px"><button class="button button-primary">บันทึก</button></p>
	</form>
	<?php amouriq_bogo_condition_script(); ?>
	<?php
}

function amouriq_bogo_admin_css() {
	?>
	<style>
		.amq-bogo .amq-top { display:flex; align-items:center; gap:12px; margin:8px 0 16px; }
		.amq-bogo h1 { margin:0; }
		.amq-scroll { overflow-x:auto; max-width:100%; }
		.amq-templates { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:10px; margin:12px 0; }
		.amq-template { display:block; border:1px solid #c3c4c7; border-radius:6px; padding:12px 14px; text-decoration:none; background:#fff; color:#1d2327; }
		.amq-template:hover { border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; }
		.amq-template strong { display:block; margin-bottom:4px; } .amq-template span { color:#50575e; font-size:12px; }
		.amq-empty-state { text-align:center; padding:24px; } .amq-empty-state .amq-templates { text-align:left; max-width:900px; margin-left:auto; margin-right:auto; }
		.amq-group-title { margin:16px 0 6px; font-size:14px; color:#1d2327; } .amq-group-help { margin:0 0 6px; color:#50575e; }
		.amq-two { display:grid; grid-template-columns:minmax(0,1fr) 300px; gap:20px; align-items:start; }
		.amq-side { position:sticky; top:40px; background:#fff; border:1px solid #c3c4c7; border-radius:6px; padding:14px 16px; }
		.amq-side h2 { margin-top:0; } .amq-side .amq-summary { position:static; margin:8px 0; }
		.amq-adv-block h3 { margin:14px 0 6px; font-size:13px; }
		button.amq-template { text-align:left; cursor:pointer; font:inherit; }
		@media (max-width:900px) { .amq-two { grid-template-columns:1fr; } .amq-side { position:static; } }
		.amq-summary { position:sticky; bottom:0; background:#f0f6fc; border:1px solid #2271b1; border-radius:6px; padding:10px 14px; margin:18px 0 8px; } .amq-summary b { color:#2271b1; }
		.amq-table { max-width:1250px; }
		.amq-table td { vertical-align:top; padding:12px 10px; line-height:1.55; }
		.amq-table tr.amq-dim td { background:#f6f7f7; color:#787c82; }
		.amq-table .button { margin:0 2px 2px 0; }
		.amq-empty { text-align:center; color:#646970; padding:24px !important; }
		.amq-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:600; }
		.amq-on { background:#d7f0dd; color:#14532d; }
		.amq-off { background:#e5e7eb; color:#4b5563; }
		.amq-warn { background:#fdecc8; color:#7a4a00; }
		.amq-kind { font-weight:600; }
		.amq-kind-deal { color:#2271b1; }
		.amq-kind-member { color:#1d6b3a; }
		.amq-kind-coupon { color:#8a4b00; }
		.amq-chip { display:inline-block; margin:4px 4px 0 0; padding:1px 8px; background:#eef2f6; border-radius:4px; font-size:12px; color:#3c434a; }
		.amq-card { max-width:1250px; background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:4px 24px 20px; margin-top:24px; }
		.amq-card h2 { margin:20px 0 4px; font-size:16px; }
		.amq-card .sub { color:#646970; margin:0 0 10px; }
		.amq-types { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:10px; margin:8px 0 4px; }
		.amq-type { display:block; border:1px solid #c3c4c7; border-radius:6px; padding:10px 12px; cursor:pointer; background:#fff; }
		.amq-type:has(input:checked) { border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; background:#f0f6fc; }
		.amq-type span { display:block; color:#646970; font-size:12px; margin-top:2px; }
		.amq-grid { display:grid; grid-template-columns:200px 1fr; gap:14px 16px; align-items:start; max-width:900px; margin-top:12px; }
		.amq-grid > label.k { font-weight:600; padding-top:6px; }
		.amq-grid .v .description { margin:4px 0 0; }
		.amq-grid .full { grid-column:1 / -1; }
		.amq-wide { width:100%; max-width:560px; }
		.amq-row[hidden] { display:none !important; }
		.amq-check-list { max-height:260px; overflow:auto; border:1px solid #c3c4c7; border-radius:4px; padding:8px 12px; background:#fff; max-width:680px; }
		.amq-check-group { margin-bottom:8px; }
		.amq-check-group label, .amq-check-list > label { display:block; padding:1px 0 1px 12px; }
		.amq-check em { color:#646970; font-style:normal; }
		.amq-check .amq-out em { color:#b32d2e; }
		.amq-check .amq-missing { color:#b32d2e; }
		.amq-check-filter { width:100%; max-width:680px; margin-bottom:6px; }
		.amq-card details { margin-top:16px; border-top:1px solid #dcdcde; padding-top:10px; }
		.amq-card details summary { cursor:pointer; font-weight:600; }
		@media (max-width:782px) { .amq-grid { grid-template-columns:1fr; } .amq-table td, .amq-table th { padding:8px 6px; } }
	</style>
	<?php
}

function amouriq_bogo_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$uid    = get_current_user_id();
	$notice = get_transient( 'amouriq_bogo_notice_' . $uid );
	$error  = get_transient( 'amouriq_bogo_error_' . $uid );
	delete_transient( 'amouriq_bogo_notice_' . $uid );
	delete_transient( 'amouriq_bogo_error_' . $uid );

	$deals   = amouriq_bogo_deals_stored();
	$types   = amouriq_bogo_type_labels();
	$report  = amouriq_bogo_report();
	$back    = amouriq_bogo_page_url();
	$edit_at = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : -1;
	$editing = isset( $deals[ $edit_at ] ) ? $deals[ $edit_at ] : null;
	$show    = $editing || isset( $_GET['add'] );

	$v = function ( $key, $default = '' ) use ( $editing ) {
		return ( $editing && isset( $editing[ $key ] ) ) ? $editing[ $key ] : $default;
	};
	$cur_type    = $v( 'type', 'same_product' );
	$cur_trigger = $v( 'trigger', 'auto' );

	$type_help = array(
		'same_product'           => 'เช่น ซื้อ 2 แถม 1 ของสินค้าตัวเดียวกัน',
		'product_pair'           => 'ซื้อสินค้า A ครบแล้วลูกค้าเลือก B (จากรายการที่ติ๊ก) ในตะกร้าเพื่อรับส่วนลด',
		'category_cheapest_free' => 'ครบ N ชิ้นในหมวดที่เลือก ชิ้นถูกสุดลด',
		'cheapest_free'          => 'ครบ N ชิ้นในตะกร้า (เลือกหมวดหรือทุกหมวด) ชิ้นถูกสุดลด',
		'quantity_tier'          => 'นับจำนวนรวมของสินค้าที่ติ๊ก (ผสมกลิ่นได้) ยิ่งมากยิ่งลดเป็นขั้น ขั้นสูงสุดที่ถึงใช้กับทุกชิ้น',
		'spend_threshold'        => 'ยอดสั่งซื้อครบเท่าไรลดกี่ % หรือกี่บาท เลือกสินค้าที่ร่วมรายการได้',
		'spend_gift'             => 'ยอดสั่งซื้อครบเท่าไร ลูกค้าเลือกของแถมฟรีจากรายการในตะกร้า',
		'fixed_price_item'       => 'สินค้าที่เลือกเหลือชิ้นละราคาที่ตั้ง (เช่น Castile Soap 250 ml ทุกกลิ่น ชิ้นละ ฿299) ชิ้นที่ถูกกว่านี้ไม่ถูกแตะ',
		'amount_off_item'        => 'ทุกชิ้นที่เลือกลดชิ้นละกี่บาท (เช่น ลดชิ้นละ ฿20)',
		'bundle_price'           => 'ซื้อครบ N ชิ้นในกลุ่มที่เลือก ได้ราคาชุดตามที่ตั้ง (เช่น 3 ชิ้น ฿999)',
		'free_shipping'          => 'ค่าส่งเป็น 0 เมื่อเข้าเงื่อนไข เช่น ซื้อครบ ฿X, ใส่คูปอง, เฉพาะวันที่กำหนด หรือเฉพาะลูกค้าใหม่ (ใช้แทนกฎส่งฟรีของ Discount Rules)',
	);

	$groups = $show ? amouriq_bogo_product_groups() : array();
	$stock  = $show ? amouriq_bogo_stock_labels() : array();
	$cats   = $show ? get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) : array();
	amouriq_bogo_admin_css();
	?>
	<div class="wrap amq-bogo">
		<?php $tab = amouriq_bogo_admin_tab(); ?>
		<div class="amq-top">
			<h1>ดีลและเงื่อนไขส่วนลด</h1>
			<?php if ( ! $show && 'deals' === $tab ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $back . '&add=1#amq-form' ); ?>">+ เพิ่มดีลใหม่</a>
			<?php endif; ?>
		</div>
		<?php if ( ! $show ) : ?>
			<nav class="nav-tab-wrapper" style="margin-bottom:12px">
				<?php foreach ( amouriq_bogo_admin_tabs() as $key => $label ) : ?>
					<a class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"<?php echo 'fee' === $key ? ' style="float:right"' : ''; ?> href="<?php echo esc_url( $back . '&tab=' . $key ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php else : ?>
			<p><a href="<?php echo esc_url( $back ); ?>">&larr; กลับไปรายการดีล</a></p>
		<?php endif; ?>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<?php
		if ( ! $show ) {
			if ( 'member' === $tab ) {
				amouriq_bogo_member_panel();
			} elseif ( 'shipping' === $tab ) {
				amouriq_bogo_shipping_panel();
			} elseif ( 'fee' === $tab ) {
				amouriq_bogo_fee_panel();
			} else {
				amouriq_bogo_deals_table( $deals, $report, $types );
			}
		}
		?>

		<?php if ( $show ) : ?>
		<div class="amq-card" id="amq-form">
			<form method="post" action="<?php echo esc_url( $back ); ?>">
				<?php wp_nonce_field( 'amouriq_bogo_save' ); ?>
				<input type="hidden" name="amouriq_bogo_action" value="<?php echo $editing ? 'update' : 'add'; ?>">
				<?php if ( $editing ) : ?>
					<input type="hidden" name="index" value="<?php echo esc_attr( $edit_at ); ?>">
					<input type="hidden" name="deal_id" value="<?php echo esc_attr( isset( $editing['id'] ) ? $editing['id'] : '' ); ?>">
				<?php endif; ?>

				<h2><?php echo $editing ? 'แก้ไขดีล #' . esc_html( $edit_at + 1 ) : 'เพิ่มดีลใหม่'; ?></h2>

				<?php if ( ! $editing ) : ?>
					<h3 class="amq-group-title">เริ่มจากแม่แบบ <span class="description">(กดแล้วช่องด้านล่างถูกเติมให้ ตรวจตัวเลขแล้วบันทึก)</span></h3>
					<div class="amq-templates">
						<?php foreach ( amouriq_bogo_templates() as $tpl ) : ?>
							<button type="button" class="amq-template amq-template-pick" data-tpl="<?php echo esc_attr( $tpl['key'] ); ?>"><strong><?php echo esc_html( $tpl['title'] ); ?></strong><span><?php echo esc_html( $tpl['text'] ); ?></span></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<details id="amq-type-picker" <?php echo $editing ? '' : 'open'; ?> style="margin:12px 0">
				<summary><strong>ประเภทดีล:</strong> <span id="amq-type-name"><?php echo esc_html( isset( $types[ $cur_type ] ) ? $types[ $cur_type ] : '' ); ?></span> <span class="description">(กดเพื่อเปลี่ยน)</span></summary>
				<?php
				$type_groups = array(
					'ลดตามยอดซื้อ ส่งฟรี และของแถม' => array( 'spend_threshold', 'free_shipping', 'spend_gift' ),
					'ซื้อสินค้าแล้วได้ส่วนลดหรือของแถม' => array( 'same_product', 'quantity_tier', 'product_pair', 'cheapest_free', 'category_cheapest_free' ),
					'ตั้งราคาพิเศษให้สินค้า' => array( 'bundle_price', 'fixed_price_item', 'amount_off_item' ),
				);
				?>
				<?php foreach ( $type_groups as $group_title => $group_keys ) : ?>
					<h3 class="amq-group-title"><?php echo esc_html( $group_title ); ?></h3>
					<div class="amq-types">
						<?php foreach ( $group_keys as $key ) : ?>
							<label class="amq-type">
								<input type="radio" name="type" value="<?php echo esc_attr( $key ); ?>" <?php checked( $cur_type, $key ); ?>>
								<strong><?php echo esc_html( $types[ $key ] ); ?></strong>
								<span><?php echo esc_html( $type_help[ $key ] ?? '' ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
				</details>

				<div class="amq-two"><div class="amq-main">
				<h2>1. รายละเอียดดีล</h2>
				<div class="amq-grid">
					<label class="k" for="bogo_label">ป้ายในตะกร้า</label>
					<div class="v"><input type="text" name="label" id="bogo_label" class="amq-wide" maxlength="100" placeholder="เช่น ซื้อ 2 แถม 1" value="<?php echo esc_attr( $v( 'label' ) ); ?>">
						<p class="description">ข้อความที่ลูกค้าเห็นในตะกร้าและหน้าชำระเงิน</p></div>

					<label class="k amq-row" data-types="same_product">สินค้า</label>
					<div class="v amq-row" data-types="same_product"><?php amouriq_bogo_product_select( 'product_id', $v( 'product_id' ), $groups ); ?></div>

					<label class="k amq-row" data-types="product_pair">สินค้า A (ซื้อ)</label>
					<div class="v amq-row" data-types="product_pair"><?php amouriq_bogo_product_select( 'buy_product_id', $v( 'buy_product_id' ), $groups ); ?></div>

					<span class="k amq-row" data-types="product_pair" style="font-weight:600;padding-top:6px">สินค้า B (ลูกค้าเลือกในตะกร้า)</span>
					<?php
					// A pair Deal saved before this form had one B; show it ticked.
					$pair_ids = $v( 'get_product_ids', array() );
					if ( ! $pair_ids && $v( 'get_product_id' ) ) {
						$pair_ids = array( (int) $v( 'get_product_id' ) );
					}
					?>
					<div class="v amq-row" data-types="product_pair"><?php amouriq_bogo_product_checklist( 'get_product_ids', $pair_ids, $groups, $stock, 'ติ๊กได้หลายรายการ เช่น ทุกกลิ่นของ Castile Soap 250 ml ลูกค้าจะเลือกเองในตะกร้า (ตัวที่หมดสต็อกจะเลือกไม่ได้)' ); ?></div>

					<label class="k amq-row" data-types="product_pair" for="bogo_gq2">จำนวน B ที่ได้ส่วนลด (ชิ้น)</label>
					<div class="v amq-row" data-types="product_pair"><input type="number" name="get_qty" id="bogo_gq2" min="1" value="<?php echo esc_attr( $v( 'get_qty', 1 ) ); ?>"></div>

					<span class="k amq-row" data-types="product_pair" style="font-weight:600;padding-top:6px">ส่วนลดของ B</span>
					<div class="v amq-row" data-types="product_pair">
						<?php
						$pair_mode  = $v( 'get_mode', 'percent' );
						$pair_value = $v( 'get_value', $v( 'get_discount_percent', '' ) );
						?>
						<input type="number" name="get_value" min="0" step="any" style="width:110px" value="<?php echo esc_attr( $pair_value ); ?>">
						<select name="get_mode"><option value="percent" <?php selected( $pair_mode, 'percent' ); ?>>%</option><option value="baht" <?php selected( $pair_mode, 'baht' ); ?>>บาทต่อชิ้น (ไม่เกินราคาสินค้า)</option></select>
					</div>

					<label class="k amq-row" data-types="category_cheapest_free cheapest_free" for="bogo_cat">หมวดสินค้า</label>
					<div class="v amq-row" data-types="category_cheapest_free cheapest_free">
						<select name="category" id="bogo_cat" class="amq-wide">
							<option value="">— ทุกหมวด (ใช้ได้เฉพาะประเภท "ครบ N ชิ้นในตะกร้า") —</option>
							<?php if ( ! is_wp_error( $cats ) ) : foreach ( $cats as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $v( 'category' ), $cat->slug ); ?>><?php echo esc_html( $cat->name ); ?></option>
							<?php endforeach; endif; ?>
						</select>
					</div>

					<label class="k amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free" for="bogo_bq">ซื้อกี่ชิ้น (สินค้า A)</label>
					<div class="v amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free"><input type="number" name="buy_qty" id="bogo_bq" min="1" value="<?php echo esc_attr( $v( 'buy_qty', 1 ) ); ?>"></div>

					<label class="k amq-row" data-types="same_product" for="bogo_gq">แถมกี่ชิ้น</label>
					<div class="v amq-row" data-types="same_product"><input type="number" name="get_qty" id="bogo_gq" min="1" value="<?php echo esc_attr( $v( 'get_qty', 1 ) ); ?>"></div>

					<label class="k amq-row" data-types="same_product category_cheapest_free cheapest_free" for="bogo_pct">ส่วนลดของชิ้นที่ได้ (%)</label>
					<div class="v amq-row" data-types="same_product category_cheapest_free cheapest_free"><input type="number" name="get_discount_percent" id="bogo_pct" min="0" max="100" value="<?php echo esc_attr( $v( 'get_discount_percent', 100 ) ); ?>">
						<p class="description">100 = ฟรี</p></div>

					<label class="k amq-row" data-types="spend_threshold spend_gift free_shipping" for="bogo_min">ยอดซื้อขั้นต่ำ (฿)</label>
					<div class="v amq-row" data-types="spend_threshold spend_gift free_shipping"><input type="number" name="min_spend" id="bogo_min" min="1" step="any" value="<?php echo esc_attr( $v( 'min_spend' ) ); ?>">
						<p class="description">นับทุกสินค้าในตะกร้า (รวมสินค้าลดราคา) ก่อนหักส่วนลด ไม่รวมค่าส่ง ตามราคาที่ลูกค้าเห็น</p></div>

					<span class="k amq-row" data-types="spend_gift" style="font-weight:600;padding-top:6px">ของแถมที่ให้เลือก</span>
					<div class="v amq-row" data-types="spend_gift"><?php amouriq_bogo_product_checklist( 'gift_ids', $v( 'gift_ids', array() ), $groups, $stock, 'ติ๊กได้หลายรายการ ลูกค้าเลือกเองในตะกร้า ตัวที่หมดสต็อกจะเลือกไม่ได้ ของแถมไม่นับรวมในยอดขั้นต่ำและจำนวนของดีลอื่น' ); ?></div>

					<label class="k amq-row" data-types="spend_gift" for="bogo_giftq">เลือกของแถมได้กี่ชิ้น</label>
					<div class="v amq-row" data-types="spend_gift"><input type="number" name="gift_qty" id="bogo_giftq" min="1" value="<?php echo esc_attr( $v( 'gift_qty', 1 ) ); ?>">
						<p class="description">เลือกได้ชิ้นละหนึ่งรายการ (เลือกรายการเดิมซ้ำไม่ได้)</p></div>

					<span class="k amq-row" data-types="spend_threshold" style="font-weight:600;padding-top:6px">ส่วนลด</span>
					<div class="v amq-row" data-types="spend_threshold">
						<input type="number" name="value" min="0" step="any" style="width:110px" value="<?php echo esc_attr( $v( 'value' ) ); ?>">
						<select name="mode"><option value="percent" <?php selected( $v( 'mode', 'percent' ), 'percent' ); ?>>% ของสินค้าที่ร่วมรายการ</option><option value="baht" <?php selected( $v( 'mode' ), 'baht' ); ?>>บาท (ไม่เกินมูลค่าสินค้าที่ร่วมรายการ)</option></select>
					</div>

					<span class="k amq-row" data-types="spend_threshold fixed_price_item amount_off_item bundle_price" style="font-weight:600;padding-top:6px">สินค้าที่ร่วมรายการ</span>
					<div class="v amq-row" data-types="spend_threshold fixed_price_item amount_off_item bundle_price"><?php amouriq_bogo_product_checklist( 'product_ids', $v( 'product_ids', array() ), $groups, $stock, 'ไม่ติ๊กเลย = ทุกสินค้าร่วมรายการ' ); ?></div>

					<span class="k amq-row" data-types="quantity_tier" style="font-weight:600;padding-top:6px">สินค้าที่ร่วมรายการ</span>
					<?php
					// A tier Deal saved before this form had one product; show it ticked.
					$tier_ids = $v( 'product_ids', array() );
					if ( ! $tier_ids && $v( 'product_id' ) ) {
						$tier_ids = array( (int) $v( 'product_id' ) );
					}
					?>
					<div class="v amq-row" data-types="quantity_tier"><?php amouriq_bogo_product_checklist( 'product_ids', $tier_ids, $groups, $stock, 'ติ๊กหลายรายการได้ เช่น ทุกกลิ่นของ Castile Soap ขนาด 250 ml จำนวนนับรวมกันข้ามกลิ่น' ); ?></div>

					<span class="k amq-row" data-types="spend_threshold quantity_tier fixed_price_item amount_off_item bundle_price" style="font-weight:600;padding-top:6px">สินค้าที่ยกเว้น</span>
					<div class="v amq-row" data-types="spend_threshold quantity_tier fixed_price_item amount_off_item bundle_price"><?php amouriq_bogo_product_checklist( 'excluded_ids', $v( 'excluded_ids', array() ), $groups, $stock, 'สินค้าที่ติ๊กจะไม่ได้ส่วนลด แต่ยังนับรวมในยอดขั้นต่ำ' ); ?></div>

					<span class="k amq-row" data-types="quantity_tier" style="font-weight:600;padding-top:6px">ขั้นส่วนลด</span>
					<div class="v amq-row" data-types="quantity_tier">
						<?php for ( $n = 1; $n <= 5; $n++ ) :
							$tier = isset( $editing['tiers'][ $n - 1 ] ) ? $editing['tiers'][ $n - 1 ] : array();
						?>
							<p style="margin:0 0 6px">ขั้นที่ <?php echo esc_html( $n ); ?>: ซื้อตั้งแต่
								<input type="number" name="tier_min_<?php echo esc_attr( $n ); ?>" min="1" style="width:80px" value="<?php echo esc_attr( $tier['min'] ?? '' ); ?>">
								ชิ้น ลด
								<input type="number" name="tier_pct_<?php echo esc_attr( $n ); ?>" min="0" max="100" style="width:80px" value="<?php echo esc_attr( $tier['percent'] ?? '' ); ?>"> %</p>
						<?php endfor; ?>
						<p class="description">ใช้อย่างน้อย 1 ขั้น เว้นว่างขั้นที่ไม่ใช้</p>
					</div>

					<label class="k amq-row" data-types="fixed_price_item bundle_price" for="bogo_price"><span data-only="fixed_price_item">ราคาต่อชิ้น (฿)</span><span data-only="bundle_price">ราคาชุด (฿)</span></label>
					<div class="v amq-row" data-types="fixed_price_item bundle_price"><input type="number" name="price" id="bogo_price" min="0" step="any" value="<?php echo esc_attr( $v( 'price' ) ); ?>">
						<p class="description">ราคาที่ลูกค้าเห็น (รวมภาษีถ้าร้านแสดงราคารวมภาษี) สินค้าที่ราคาต่ำกว่านี้ไม่ถูกแตะ ไม่มีการเพิ่มราคา</p></div>

					<label class="k amq-row" data-types="bundle_price" for="bogo_bundleq">ซื้อกี่ชิ้นต่อชุด</label>
					<div class="v amq-row" data-types="bundle_price"><input type="number" name="bundle_qty" id="bogo_bundleq" min="2" value="<?php echo esc_attr( $v( 'bundle_qty', 3 ) ); ?>">
						<p class="description">ระบบจัดชุดจากชิ้นที่แพงที่สุดก่อน (เป็นผลดีกับลูกค้า) ชิ้นที่เหลือไม่ครบชุดคิดราคาเต็ม ใช้ "จำกัดต่อออเดอร์" เพื่อจำกัดจำนวนชุด</p></div>

					<label class="k amq-row" data-types="amount_off_item" for="bogo_off">ลดต่อชิ้น (฿)</label>
					<div class="v amq-row" data-types="amount_off_item"><input type="number" name="off_value" id="bogo_off" min="0" step="any" value="<?php echo esc_attr( $v( 'value' ) ); ?>">
						<p class="description">ทุกชิ้นที่ร่วมรายการลดชิ้นละเท่านี้ (ไม่เกินราคาสินค้าชิ้นนั้น)</p></div>

					<span class="k amq-row" data-types="spend_threshold quantity_tier fixed_price_item amount_off_item bundle_price" style="font-weight:600;padding-top:6px">ขอบเขตสินค้า</span>
					<div class="v amq-row" data-types="spend_threshold quantity_tier fixed_price_item amount_off_item bundle_price">
						<details <?php echo ( $editing && ! empty( $editing['scope'] ) ) ? 'open' : ''; ?> style="margin:0;border:0;padding:0">
							<summary>เลือกตามหมวด แท็ก ขนาด หรือ SKU (ไม่บังคับ)</summary>
							<?php amouriq_bogo_scope_fields( isset( $editing['scope'] ) ? $editing['scope'] : array(), $cats ); ?>
						</details>
					</div>
				</div>

				<h2>2. ดีลนี้เริ่มทำงานเมื่อไหร่</h2>
				<div class="amq-grid">
					<span class="k" style="font-weight:600;padding-top:6px">วิธีเปิดใช้</span>
					<div class="v">
						<label><input type="radio" name="trigger" value="auto" <?php checked( $cur_trigger, 'auto' ); ?>> อัตโนมัติ ลูกค้าไม่ต้องทำอะไร ตะกร้าครบเงื่อนไขก็ลด</label><br>
						<label><input type="radio" name="trigger" value="coupon" <?php checked( $cur_trigger, 'coupon' ); ?>> ต้องใส่คูปอง ดีลทำงานเมื่อมีคูปองนี้ในตะกร้า</label>
					</div>
					<label class="k amq-row" data-trigger="coupon" for="bogo_coupon">รหัสคูปอง</label>
					<div class="v amq-row" data-trigger="coupon"><input type="text" name="coupon_code" id="bogo_coupon" class="regular-text" value="<?php echo esc_attr( $v( 'coupon_code' ) ); ?>">
						<p class="description">พิมพ์รหัสที่ต้องการ ถ้ายังไม่มีคูปองรหัสนี้ ระบบสร้างให้ตอนบันทึก (คูปองเปล่า ไม่ลดราคาเอง) ถ้ามีอยู่แล้วจะเชื่อมกับคูปองนั้น ไม่สร้างซ้ำ</p></div>
					<?php $cv = amouriq_bogo_coupon_form_values( $editing ? $editing : array() ); ?>
					<label class="k amq-row" data-trigger="coupon" for="bogo_coupon_expires">คูปองหมดอายุ</label>
					<div class="v amq-row" data-trigger="coupon"><input type="date" name="coupon_expires" id="bogo_coupon_expires" value="<?php echo esc_attr( $cv['expires'] ); ?>" <?php disabled( ! $cv['editable'] ); ?>>
						<span class="description">เว้นว่าง = ไม่มีวันหมดอายุ</span></div>
					<label class="k amq-row" data-trigger="coupon" for="bogo_coupon_limit">ใช้ได้ทั้งหมด (ครั้ง)</label>
					<div class="v amq-row" data-trigger="coupon"><input type="number" min="0" name="coupon_limit" id="bogo_coupon_limit" value="<?php echo esc_attr( $cv['limit'] ); ?>" <?php disabled( ! $cv['editable'] ); ?>>
						<span class="description">เว้นว่าง = ไม่จำกัด</span>
						<?php if ( ! $cv['editable'] ) : ?>
							<p class="description">คูปองนี้สร้างที่หน้าคูปองเอง จึงไม่แก้ที่นี่ <a href="<?php echo esc_url( isset( $cv['edit_url'] ) ? $cv['edit_url'] : '' ); ?>">แก้ที่หน้าคูปอง</a></p>
						<?php else : ?>
							<p class="description">ตั้งค่าอื่นของคูปอง (ส่งฟรี ขั้นต่ำ ฯลฯ) แก้ที่ การตลาด &gt; คูปอง</p>
						<?php endif; ?>
					</div>
				</div>

				<?php
				$adv_open = $editing && ( ! empty( $editing['conditions'] ) || ! empty( $editing['exclude_sale'] ) || ! empty( $editing['no_combine'] ) || ! empty( $editing['new_customer_only'] ) || ! empty( $editing['exclusive'] ) || ! empty( $editing['valid_from'] ) || ! empty( $editing['valid_to'] ) || ! empty( $editing['max_uses_per_customer'] ) || ! empty( $editing['max_per_order'] ) );
				?>
				<details id="amq-advanced" <?php echo $adv_open ? 'open' : ''; ?>>
					<summary><strong>ตัวเลือกขั้นสูง</strong> <span class="description">(ส่วนใหญ่ไม่ต้องแตะ: เงื่อนไขเพิ่มเติม, Exclusive, ช่วงเวลา, จำกัดสิทธิ์)</span></summary>
				<div class="amq-adv-block">$1$2</h3>
					<?php amouriq_bogo_condition_fields( amouriq_bogo_conditions_for_form( $v( 'conditions', array() ) ) ); ?>
				</div>

				<div class="amq-adv-block"><h3>ตัวเลือกเพิ่มเติม (วันที่ จำกัดสิทธิ์ ไม่ใช้ร่วมกับคูปอง)</h3>
					<div class="amq-grid">
						<span class="k" style="font-weight:600">เงื่อนไขพิเศษ</span>
						<div class="v">
							<label class="amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free quantity_tier spend_threshold fixed_price_item amount_off_item bundle_price"><input type="checkbox" name="exclude_sale" value="1" <?php checked( $editing ? ! empty( $editing['exclude_sale'] ) : true ); ?>> ไม่รวมสินค้าที่ลดราคาอยู่แล้ว (ไม่ได้ส่วนลด; ยังนับในยอดขั้นต่ำ)</label><br>
							<label><input type="checkbox" name="no_combine" value="1" <?php checked( ! empty( $editing['no_combine'] ) ); ?>> ไม่ใช้ร่วมกับคูปองอื่น (คูปองที่เปิดดีลนี้เองไม่นับ)</label><br>
							<label><input type="checkbox" name="new_customer_only" value="1" <?php checked( ! empty( $editing['new_customer_only'] ) ); ?>> ลูกค้าใหม่เท่านั้น (ยังไม่เคยมีออเดอร์ที่นับ ตรวจตามบัญชีหรืออีเมล)</label><br>
							<label class="amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free quantity_tier spend_threshold fixed_price_item amount_off_item bundle_price"><input type="checkbox" name="exclusive" value="1" <?php checked( ! empty( $editing['exclusive'] ) ); ?>> Exclusive: สินค้าที่ดีลนี้ลดแล้ว ดีลที่อยู่ถัดลงไปในรายการจะไม่ลดซ้ำ (ลำดับในรายการสำคัญ ไม่กระทบสิทธิพิเศษสมาชิกและคูปอง)</label>
						</div>
						<label class="k" for="bogo_from">ใช้ได้ตั้งแต่</label>
						<div class="v"><input type="datetime-local" name="valid_from" id="bogo_from" value="<?php echo esc_attr( amouriq_bogo_datetime_value( $v( 'valid_from' ), false ) ); ?>"> <span class="description">เว้นว่าง = ใช้ได้ทันที</span></div>
						<label class="k" for="bogo_to">ใช้ได้ถึง</label>
						<div class="v"><input type="datetime-local" name="valid_to" id="bogo_to" value="<?php echo esc_attr( amouriq_bogo_datetime_value( $v( 'valid_to' ), true ) ); ?>"> <span class="description">เว้นว่าง = ไม่มีวันหมด</span></div>
						<label class="k amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free quantity_tier spend_threshold fixed_price_item amount_off_item bundle_price free_shipping" for="bogo_maxc">จำกัดต่อลูกค้า (ครั้ง)</label>
						<div class="v amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free quantity_tier spend_threshold fixed_price_item amount_off_item bundle_price free_shipping"><input type="number" name="max_uses_per_customer" id="bogo_maxc" min="0" value="<?php echo esc_attr( $v( 'max_uses_per_customer', 0 ) ); ?>">
							<p class="description">0 = ไม่จำกัด ลูกค้าที่ล็อกอินนับตามบัญชี ที่ยังไม่ล็อกอินนับตามอีเมลตอนชำระเงิน</p></div>
						<label class="k amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free quantity_tier spend_threshold bundle_price" for="bogo_maxo">จำกัดต่อออเดอร์ (ครั้ง)</label>
						<div class="v amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free quantity_tier spend_threshold bundle_price"><input type="number" name="max_per_order" id="bogo_maxo" min="0" value="<?php echo esc_attr( $v( 'max_per_order', 0 ) ); ?>">
							<p class="description">0 = ไม่จำกัด</p></div>
					</div>
				</div>
				</details>
				</div>

				<aside class="amq-side">
					<h2>สรุปให้ตรวจ</h2>
					<div class="amq-summary" id="amq-live-summary"></div>
					<p class="description">ดีลใหม่เริ่มที่ "ปิดอยู่" เสมอ ตรวจแล้วค่อยเปิดที่รายการดีล</p>
					<p style="margin-top:14px">
						<?php submit_button( $editing ? 'บันทึกการแก้ไข' : 'เพิ่มดีล', 'primary', 'submit', false ); ?>
						<a class="button" href="<?php echo esc_url( $back ); ?>" style="margin-left:6px">ยกเลิก</a>
					</p>
				</aside></div>
			</form>
		</div>
		<script>
		(function () {
			var form = document.querySelector('#amq-form form');
			if (!form) { return; }
			function sync() {
				var type = form.querySelector('input[name=type]:checked');
				var trig = form.querySelector('input[name=trigger]:checked');
				type = type ? type.value : '';
				trig = trig ? trig.value : 'auto';
				// Fields of a type that is not chosen are hidden and also disabled, so they are not posted
				// (several types share field names such as product_ids and get_qty).
				function show(el, visible) {
					el.hidden = !visible;
					el.querySelectorAll('input, select, textarea').forEach(function (field) {
						field.disabled = !visible;
					});
				}
				form.querySelectorAll('[data-types]').forEach(function (el) {
					show(el, el.getAttribute('data-types').split(' ').indexOf(type) !== -1);
				});
				form.querySelectorAll('[data-only]').forEach(function (el) {
					el.hidden = el.getAttribute('data-only') !== type;
				});
				form.querySelectorAll('[data-trigger]').forEach(function (el) {
					show(el, el.getAttribute('data-trigger') === trig);
				});
			}
			form.querySelectorAll('.amq-check-filter').forEach(function (box) {
				box.addEventListener('input', function () {
					var q = box.value.toLowerCase();
					box.parentNode.querySelectorAll('.amq-check-list label').forEach(function (l) {
						l.style.display = l.textContent.toLowerCase().indexOf(q) === -1 ? 'none' : '';
					});
				});
			});
			// Condition rows: the operators and the hint follow the chosen type.
			var catalogEl = document.getElementById('amq-cond-catalog');
			var catalog = catalogEl ? JSON.parse(catalogEl.textContent) : {};
			function syncCondition(row) {
				var type = row.querySelector('.amq-cond-type').value;
				var op = row.querySelector('.amq-cond-op');
				var value = row.querySelector('.amq-cond-value');
				var hint = row.querySelector('.amq-cond-hint');
				var keep = op.value || op.getAttribute('data-current');
				op.innerHTML = '';
				if (!type || !catalog[type]) {
					op.hidden = true; value.hidden = true; hint.textContent = '';
					return;
				}
				op.hidden = false; value.hidden = false;
				Object.keys(catalog[type].ops).forEach(function (key) {
					var option = document.createElement('option');
					option.value = key;
					option.textContent = catalog[type].ops[key];
					if (key === keep) { option.selected = true; }
					op.appendChild(option);
				});
				hint.textContent = catalog[type].hint || '';
			}
			form.querySelectorAll('.amq-cond-row').forEach(function (row) {
				row.querySelector('.amq-cond-type').addEventListener('change', function () {
					row.querySelector('.amq-cond-op').setAttribute('data-current', '');
					syncCondition(row);
				});
				syncCondition(row);
			});
			// Templates (?tpl=...) fill the new-deal form; the live sentence says what the deal will do.
			function field(name) { return form.querySelector('[name="' + name + '"]:not(:disabled)'); }
			function fval(name) { var e = field(name); return e ? e.value : ''; }
			function setField(name, value) {
				var e = form.querySelector('[name="' + name + '"]');
				if (!e) { return; }
				if (e.type === 'checkbox') { e.checked = !!value; } else { e.value = value; }
			}
			var templates = {
				spend_percent: { type: 'spend_threshold', label: 'ซื้อครบ ฿1,500 ลด 10%', min_spend: 1500, mode: 'percent', value: 10 },
				new_customer: { type: 'spend_threshold', label: 'ลูกค้าใหม่ ลด 10%', min_spend: 500, mode: 'percent', value: 10, new_customer_only: true },
				buy_get: { type: 'same_product', label: 'ซื้อ 2 แถม 1', buy_qty: 2, get_qty: 1, get_discount_percent: 100 },
				free_shipping: { type: 'free_shipping', label: 'ส่งฟรีเมื่อซื้อครบ ฿799', min_spend: 799 },
				gift: { type: 'spend_gift', label: 'ซื้อครบ ฿1,500 เลือกของแถมฟรี', min_spend: 1500, gift_qty: 1 },
				bundle: { type: 'bundle_price', label: 'ซื้อ 3 ชิ้น ในราคา ฿999', bundle_qty: 3, price: 999 }
			};
			var tplKey = new URLSearchParams(window.location.search).get('tpl');
			var isNew = form.querySelector('input[name=amouriq_bogo_action][value=add]');
			function applyTemplate(key) {
				var tpl = templates[key];
				var oldNote = form.querySelector('.amq-tpl-note');
				if (oldNote) { oldNote.remove(); }
				var radio = form.querySelector('input[name=type][value=' + tpl.type + ']');
				if (radio) { radio.checked = true; }
				Object.keys(tpl).forEach(function (k) { if (k !== 'type') { setField(k, tpl[k]); } });
				var note = document.createElement('div');
				note.className = 'notice notice-info inline amq-tpl-note';
				note.innerHTML = '<p>กรอกจากแม่แบบแล้ว ตรวจตัวเลขและเลือกสินค้า (ถ้าต้องเลือก) แล้วกด "เพิ่มดีล"</p>';
				form.insertBefore(note, form.firstChild);
				var more = document.getElementById('amq-advanced');
				if (more && tpl.new_customer_only) { more.open = true; }
				sync();
				setTimeout(summarise, 0);
			}
			if (isNew && tplKey && templates[tplKey]) { applyTemplate(tplKey); }
			form.querySelectorAll('.amq-template-pick').forEach(function (btn) {
				btn.addEventListener('click', function () { applyTemplate(btn.getAttribute('data-tpl')); });
			});
			var summaryBox = document.getElementById('amq-live-summary');
			function money(v) { return v === '' ? '…' : '฿' + Number(v).toLocaleString('en-US'); }
			function summarise() {
				if (!summaryBox) { return; }
				var type = (form.querySelector('input[name=type]:checked') || {}).value || '';
				var s = '';
				var pct = function (n) { return fval(n) === '' ? '…' : fval(n) + '%'; };
				switch (type) {
					case 'same_product': s = 'ซื้อ ' + (fval('buy_qty') || '…') + ' แถม ' + (fval('get_qty') || '…') + ' (ลด ' + pct('get_discount_percent') + ')'; break;
					case 'product_pair': s = 'ซื้อสินค้า A ' + (fval('buy_qty') || '…') + ' ชิ้น เลือกสินค้า B ได้ ' + (fval('get_qty') || '…') + ' ชิ้น ลด ' + (fval('get_value') || '…') + (fval('get_mode') === 'baht' ? ' บาทต่อชิ้น' : '%'); break;
					case 'category_cheapest_free':
					case 'cheapest_free': s = 'ซื้อครบ ' + (fval('buy_qty') || '…') + ' ชิ้น ชิ้นถูกสุดลด ' + pct('get_discount_percent'); break;
					case 'quantity_tier':
						var tiers = [];
						for (var n = 1; n <= 5; n++) { if (fval('tier_min_' + n) && fval('tier_pct_' + n)) { tiers.push(fval('tier_min_' + n) + ' ชิ้นลด ' + fval('tier_pct_' + n) + '%'); } }
						s = tiers.length ? 'ซื้อตามจำนวน: ' + tiers.join(' / ') : 'ซื้อตามจำนวน ลดเป็นขั้น (ยังไม่ได้ใส่ขั้น)'; break;
					case 'spend_threshold': s = 'ซื้อครบ ' + money(fval('min_spend')) + ' ลด ' + (fval('value') || '…') + (fval('mode') === 'baht' ? ' บาท' : '%'); break;
					case 'spend_gift': s = 'ซื้อครบ ' + money(fval('min_spend')) + ' เลือกของแถมฟรี ' + (fval('gift_qty') || '…') + ' ชิ้น'; break;
					case 'fixed_price_item': s = 'สินค้าที่เลือกเหลือชิ้นละ ' + money(fval('price')); break;
					case 'amount_off_item': s = 'ทุกชิ้นที่เลือกลดชิ้นละ ' + money(fval('off_value')); break;
					case 'bundle_price': s = 'ซื้อ ' + (fval('bundle_qty') || '…') + ' ชิ้น ในราคา ' + money(fval('price')); break;
					case 'free_shipping': s = 'ส่งฟรี' + (fval('min_spend') ? ' เมื่อซื้อครบ ' + money(fval('min_spend')) : ' (ไม่มีขั้นต่ำ)'); break;
					default: s = 'เลือกประเภทดีลก่อน';
				}
				var extra = [];
				var trig = form.querySelector('input[name=trigger]:checked');
				if (trig && trig.value === 'coupon') { extra.push('ต้องใส่คูปอง ' + (fval('coupon_code') || '…')); } else { extra.push('ทำงานอัตโนมัติ'); }
				var nc = form.querySelector('input[name=new_customer_only]');
				if (nc && nc.checked) { extra.push('เฉพาะลูกค้าใหม่'); }
				var conds = 0;
				form.querySelectorAll('.amq-cond-type').forEach(function (e) { if (e.value) { conds++; } });
				if (conds) { extra.push('มีเงื่อนไขเพิ่ม ' + conds + ' ข้อ'); }
				if (fval('valid_from') || fval('valid_to')) { extra.push((fval('valid_from') || '…').replace('T', ' ') + ' ถึง ' + (fval('valid_to') || '…').replace('T', ' ')); }
				summaryBox.innerHTML = '<b>ดีลนี้:</b> ' + s + ' · ' + extra.join(' · ');
			}
			// After the field sync has run, so fields of other types (disabled) are not read.
			form.addEventListener('input', function () { setTimeout(summarise, 0); });
			form.addEventListener('change', function () { setTimeout(summarise, 0); });
$1			form.addEventListener('change', function () {
				var r = form.querySelector('input[name=type]:checked'), nameEl = document.getElementById('amq-type-name');
				if (r && nameEl && r.parentNode.querySelector('strong')) { nameEl.textContent = r.parentNode.querySelector('strong').textContent; }
			});
			sync();
			summarise();
		})();
		</script>
		<?php endif; ?>
	</div>
	<?php
}

/** One plain Thai summary of a saved Deal (what it does, who for, which products), for the confirmation notice. */
function amouriq_bogo_summary( $deal ) {
	$parts = array( amouriq_bogo_detail( $deal ) );
	if ( ! empty( $deal['scope'] ) ) {
		$parts[] = 'ขอบเขต: ' . amouriq_bogo_scope_text( $deal['scope'] );
	}
	if ( ! empty( $deal['conditions'] ) ) {
		$parts[] = amouriq_cond_describe( amouriq_bogo_conditions_for_form( $deal['conditions'] ) );
	}
	if ( ! empty( $deal['new_customer_only'] ) ) {
		$parts[] = 'ลูกค้าใหม่เท่านั้น';
	}
	return implode( ' | ', array_filter( $parts ) );
}

/**
 * The Product scope editor (include and exclude by category, tag, attribute, SKU, on-sale), shared by the
 * deal form and the Member Privilege panel. `$scope` is a stored scope, `$cats` the product_cat terms.
 */
function amouriq_bogo_scope_fields( $scope, $cats ) {
	$scope_inc  = isset( $scope['include'] ) ? $scope['include'] : array();
	$scope_exc  = isset( $scope['exclude'] ) ? $scope['exclude'] : array();
	$scope_attr = array();
	if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$scope_attr[ 'pa_' . $attribute->attribute_name ] = $attribute->attribute_label;
		}
	}
	$cur_attr_tax = '';
	$cur_attr_val = '';
	if ( ! empty( $scope_inc['attributes'] ) ) {
		$cur_attr_tax = (string) key( $scope_inc['attributes'] );
		$cur_attr_val = implode( ', ', (array) reset( $scope_inc['attributes'] ) );
	}
	?>
		<p class="description" style="margin-top:0">ใช้เลือกสินค้าที่ร่วมรายการด้วยหมวด แท็ก ขนาด หรือ SKU แทนการติ๊กทีละรายการ ถ้ากำหนดหลายข้อ สินค้าต้องตรงทุกข้อ (ภายในข้อเดียวกันตรงอย่างใดอย่างหนึ่งก็พอ) เว้นว่างทั้งหมด = ไม่จำกัดตามข้อนี้</p>
		<p><strong>ร่วมรายการเฉพาะ</strong></p>
		<p><label>หมวด<br><select name="scope_include_categories[]" multiple size="4" style="min-width:260px">
			<?php if ( ! is_wp_error( $cats ) ) : foreach ( $cats as $cat ) : ?>
				<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( in_array( $cat->slug, (array) ( $scope_inc['categories'] ?? array() ), true ) ); ?>><?php echo esc_html( $cat->name ); ?></option>
			<?php endforeach; endif; ?>
		</select></label></p>
		<p><label>แท็กสินค้า (slug คั่นด้วยจุลภาค) <input type="text" name="scope_include_tags" value="<?php echo esc_attr( implode( ', ', (array) ( $scope_inc['tags'] ?? array() ) ) ); ?>" class="regular-text"></label></p>
		<p><label>คุณสมบัติสินค้า (เช่น ขนาด)
			<select name="scope_include_attribute">
				<option value="">— ไม่ใช้ —</option>
				<?php foreach ( $scope_attr as $tax => $label_text ) : ?>
					<option value="<?php echo esc_attr( $tax ); ?>" <?php selected( $cur_attr_tax, $tax ); ?>><?php echo esc_html( $label_text . ' (' . $tax . ')' ); ?></option>
				<?php endforeach; ?>
			</select></label>
			ค่า (slug คั่นด้วยจุลภาค เช่น 250-ml) <input type="text" name="scope_include_attribute_values" value="<?php echo esc_attr( $cur_attr_val ); ?>"></p>
		<p><label>SKU (คั่นด้วยจุลภาค) <input type="text" name="scope_include_skus" value="<?php echo esc_attr( implode( ', ', (array) ( $scope_inc['skus'] ?? array() ) ) ); ?>" class="regular-text"></label></p>
		<p><label><input type="checkbox" name="scope_include_on_sale_only" value="1" <?php checked( ! empty( $scope_inc['on_sale_only'] ) ); ?>> เฉพาะสินค้าที่ลดราคาอยู่</label></p>
		<p><strong>ยกเว้น</strong></p>
		<p><label>หมวด<br><select name="scope_exclude_categories[]" multiple size="4" style="min-width:260px">
			<?php if ( ! is_wp_error( $cats ) ) : foreach ( $cats as $cat ) : ?>
				<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( in_array( $cat->slug, (array) ( $scope_exc['categories'] ?? array() ), true ) ); ?>><?php echo esc_html( $cat->name ); ?></option>
			<?php endforeach; endif; ?>
		</select></label></p>
		<p><label>แท็กสินค้า <input type="text" name="scope_exclude_tags" value="<?php echo esc_attr( implode( ', ', (array) ( $scope_exc['tags'] ?? array() ) ) ); ?>" class="regular-text"></label>
		&nbsp;<label>SKU <input type="text" name="scope_exclude_skus" value="<?php echo esc_attr( implode( ', ', (array) ( $scope_exc['skus'] ?? array() ) ) ); ?>"></label></p>
	<?php
}

/**
 * The Condition rows editor (up to 5 rows, all or any), shared by the deal form and the Member Privilege panel.
 * `$set` is a set as amouriq_bogo_conditions_for_form() returns it; `$note` replaces the default hint when given.
 */
function amouriq_bogo_condition_fields( $set, $note = '' ) {
	$catalog   = amouriq_cond_catalog();
	$cond_set  = $set;
	$cond_rows = array_values( $cond_set['rows'] );
	?>
	<?php if ( '' !== $note ) : ?>
	<p class="description"><?php echo esc_html( $note ); ?></p>
	<?php else : ?>
<p class="description">ไม่ใส่เลย = ใช้กับทุกคนตลอดเวลา ใส่ได้สูงสุด <?php echo esc_html( AMOURIQ_BOGO_CONDITION_ROWS ); ?> ข้อ ข้อมูลที่ยังไม่ทราบ (เช่น ยังไม่กรอกอีเมล) จะถือว่ายังไม่ตรงเงื่อนไข ดีลจะเริ่มทำงานเมื่อทราบข้อมูลแล้ว</p>
	<?php endif; ?>
<div class="amq-grid" id="amq-conditions">
	<label class="k" for="cond_match">ต้องตรง</label>
	<div class="v">
		<select name="cond_match" id="cond_match">
			<option value="all" <?php selected( $cond_set['match'], 'all' ); ?>>ครบทุกข้อ</option>
			<option value="any" <?php selected( $cond_set['match'], 'any' ); ?>>ข้อใดข้อหนึ่ง</option>
		</select>
	</div>
	<?php for ( $n = 1; $n <= AMOURIQ_BOGO_CONDITION_ROWS; $n++ ) :
		$row = isset( $cond_rows[ $n - 1 ] ) ? $cond_rows[ $n - 1 ] : array( 'type' => '', 'op' => '', 'value' => '' );
	?>
		<span class="k">เงื่อนไขข้อ <?php echo esc_html( $n ); ?></span>
		<div class="v amq-cond-row">
			<select name="cond_type_<?php echo esc_attr( $n ); ?>" class="amq-cond-type">
				<option value="">— ไม่ใช้ —</option>
				<?php foreach ( $catalog as $ckey => $cmeta ) : ?>
					<option value="<?php echo esc_attr( $ckey ); ?>" <?php selected( $row['type'], $ckey ); ?>><?php echo esc_html( $cmeta['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="cond_op_<?php echo esc_attr( $n ); ?>" class="amq-cond-op" data-current="<?php echo esc_attr( $row['op'] ); ?>"></select>
			<input type="text" name="cond_value_<?php echo esc_attr( $n ); ?>" class="amq-cond-value" style="min-width:220px" value="<?php echo esc_attr( $row['type'] ? amouriq_cond_format_value( $row ) : '' ); ?>">
			<span class="description amq-cond-hint"></span>
		</div>
	<?php endfor; ?>
</div>
<script type="application/json" id="amq-cond-catalog"><?php echo wp_json_encode( $catalog ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
	<?php
}

/**
 * The script for the Condition rows on a page that has no deal form (the Member Privilege panel):
 * the operators and the hint follow the chosen type.
 */
function amouriq_bogo_condition_script() {
	?>
	<script>
	(function () {
		var catalogEl = document.getElementById('amq-cond-catalog');
		var catalog = catalogEl ? JSON.parse(catalogEl.textContent) : {};
		function sync(row) {
			var type = row.querySelector('.amq-cond-type').value;
			var op = row.querySelector('.amq-cond-op');
			var value = row.querySelector('.amq-cond-value');
			var hint = row.querySelector('.amq-cond-hint');
			var keep = op.value || op.getAttribute('data-current');
			op.innerHTML = '';
			if (!type || !catalog[type]) { op.hidden = true; value.hidden = true; hint.textContent = ''; return; }
			op.hidden = false; value.hidden = false;
			Object.keys(catalog[type].ops).forEach(function (key) {
				var option = document.createElement('option');
				option.value = key; option.textContent = catalog[type].ops[key];
				if (key === keep) { option.selected = true; }
				op.appendChild(option);
			});
			hint.textContent = catalog[type].hint || '';
		}
		document.querySelectorAll('#amq-member-form .amq-cond-row').forEach(function (row) {
			row.querySelector('.amq-cond-type').addEventListener('change', function () {
				row.querySelector('.amq-cond-op').setAttribute('data-current', '');
				sync(row);
			});
			sync(row);
		});
	})();
	</script>
	<?php
}

/** One plain Thai summary of the current Member Privilege rule, for the confirmation notice. */
function amouriq_bogo_member_summary() {
	$rule  = amouriq_bogo_member_rule();
	$parts = array( ( '' !== $rule['label'] ? $rule['label'] : 'สิทธิพิเศษสมาชิก' ) . ' ลด ' . amouriq_bogo_member_number( $rule['percent'] ) . '% เมื่อยอดสินค้าครบ ฿' . amouriq_bogo_member_number( $rule['min_spend'] ) );
	$parts[] = $rule['include_sale'] ? 'รวมสินค้าลดราคา' : 'เฉพาะสินค้าราคาปกติ';
	if ( ! empty( $rule['scope'] ) ) {
		$parts[] = 'ขอบเขต: ' . amouriq_bogo_scope_text( $rule['scope'] );
	}
	if ( ! empty( $rule['conditions'] ) ) {
		$parts[] = amouriq_cond_describe( amouriq_bogo_conditions_for_form( $rule['conditions'] ) );
	}
	if ( $rule['valid_from'] || $rule['valid_to'] ) {
		$parts[] = ( $rule['valid_from'] ? $rule['valid_from'] : '…' ) . ' ถึง ' . ( $rule['valid_to'] ? $rule['valid_to'] : '…' );
	}
	return implode( ' | ', array_filter( $parts ) );
}
