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
		'product_pair'           => 'ซื้อ A แล้วได้ B ลดราคา',
		'category_cheapest_free' => 'ครบ N ชิ้นในหมวด ชิ้นถูกสุดลด',
		'cheapest_free'          => 'ครบ N ชิ้นในตะกร้า (ทุกหมวด) ชิ้นถูกสุดลด',
		'quantity_tier'          => 'ซื้อตามจำนวน ลดเป็นขั้น (สินค้าชิ้นเดียว)',
	);
}

function amouriq_bogo_valid_date( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return false;
	}
	return $value;
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

	if ( 'coupon' === $trigger ) {
		$code = isset( $in['coupon_code'] ) ? wc_format_coupon_code( wp_unslash( $in['coupon_code'] ) ) : '';
		if ( '' === $code ) {
			return 'เลือกให้ทำงานด้วยคูปองแต่ยังไม่ได้ใส่รหัสคูปอง';
		}
		if ( ! wc_get_coupon_id_by_code( $code ) ) {
			return 'ไม่พบคูปองรหัส "' . $code . '" ในระบบ ให้สร้างคูปองก่อน หรือแก้รหัสให้ตรง';
		}
		$deal['coupon_code'] = $code;
	}

	$from = amouriq_bogo_valid_date( $in['valid_from'] ?? '' );
	$to   = amouriq_bogo_valid_date( $in['valid_to'] ?? '' );
	if ( false === $from || false === $to ) {
		return 'รูปแบบวันที่ไม่ถูกต้อง (ใช้รูปแบบ ปี-เดือน-วัน)';
	}
	if ( $from && $to && $from > $to ) {
		return 'วันเริ่มต้องไม่เกินวันสิ้นสุด';
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
		$get = $int( 'get_product_id' );
		if ( ! $buy || ! wc_get_product( $buy ) ) {
			return 'ไม่พบสินค้า A (buy) ID นี้: ' . $buy;
		}
		if ( ! $get || ! wc_get_product( $get ) ) {
			return 'ไม่พบสินค้า B (get) ID นี้: ' . $get;
		}
		$deal['buy_product_id'] = $buy;
		$deal['get_product_id'] = $get;
		$deal['buy_qty']        = max( 1, $int( 'buy_qty', 1 ) );
		$deal['get_qty']        = max( 1, $int( 'get_qty', 1 ) );
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
		$pid = $int( 'product_id' );
		if ( ! $pid || ! wc_get_product( $pid ) ) {
			return 'ไม่พบสินค้า/variation ID นี้: ' . $pid;
		}
		$tiers = array();
		for ( $n = 1; $n <= 3; $n++ ) {
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
		$deal['product_id'] = $pid;
		$deal['tiers']      = $tiers;
	}

	if ( ! empty( $in['exclude_sale'] ) ) {
		$deal['exclude_sale'] = true;
	}
	if ( ! empty( $in['no_combine'] ) ) {
		$deal['no_combine'] = true;
	}

	if ( '' === $label ) {
		$deal['label'] = 'Deal discount';
	}

	return $deal;
}

function amouriq_bogo_admin_handle() {
	if ( ! isset( $_POST['amouriq_bogo_action'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	check_admin_referer( 'amouriq_bogo_save' );

	$deals  = amouriq_bogo_deals();
	$action = sanitize_key( $_POST['amouriq_bogo_action'] );
	$uid    = get_current_user_id();
	$index  = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;
	$back   = admin_url( 'admin.php?page=amouriq-bogo' );

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
	} elseif ( 'toggle_coupon' === $action ) {
		$post = get_post( isset( $_POST['coupon_id'] ) ? (int) $_POST['coupon_id'] : 0 );
		if ( $post && 'shop_coupon' === $post->post_type && in_array( $post->post_status, array( 'publish', 'draft' ), true ) ) {
			$turn_on = 'draft' === $post->post_status;
			wp_update_post( array( 'ID' => $post->ID, 'post_status' => $turn_on ? 'publish' : 'draft' ) );
			set_transient( 'amouriq_bogo_notice_' . $uid, $turn_on ? 'เปิดคูปองแล้ว' : 'ปิดคูปองแล้ว (เปลี่ยนเป็นฉบับร่าง)', 60 );
		}
		wp_safe_redirect( $back );
		exit;
	} elseif ( 'add' === $action ) {
		$result = amouriq_bogo_build_deal( $_POST );
		if ( is_string( $result ) ) {
			set_transient( 'amouriq_bogo_error_' . $uid, $result, 60 );
			wp_safe_redirect( $back );
			exit;
		}
		$result['id'] = 'b' . wp_generate_password( 10, false, false );
		$deals[]      = $result;
		set_transient( 'amouriq_bogo_notice_' . $uid, 'เพิ่มดีลแล้ว', 60 );
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
		$result['id'] = $deals[ $index ]['id'];
		if ( isset( $deals[ $index ]['enabled'] ) ) {
			$result['enabled'] = $deals[ $index ]['enabled'];
		}
		$deals[ $index ] = $result;
		set_transient( 'amouriq_bogo_notice_' . $uid, 'บันทึกการแก้ไขแล้ว', 60 );
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
		return 'ซื้อ ' . amouriq_bogo_pname( $deal['buy_product_id'] ) . ' × ' . ( $deal['buy_qty'] ?? 1 ) . ' ได้ ' . amouriq_bogo_pname( $deal['get_product_id'] ) . ' × ' . ( $deal['get_qty'] ?? 1 ) . ' (' . $off . ')';
	}
	if ( 'category_cheapest_free' === $type ) {
		return 'หมวด ' . amouriq_bogo_cat_name( $deal['category'] ) . ' ครบ ' . $deal['buy_qty'] . ' ชิ้น ชิ้นถูกสุด' . $off;
	}
	if ( 'cheapest_free' === $type ) {
		$scope = ! empty( $deal['category'] ) ? 'หมวด ' . amouriq_bogo_cat_name( $deal['category'] ) : 'ทุกหมวด';
		return $scope . ' ครบ ' . $deal['buy_qty'] . ' ชิ้น ชิ้นถูกสุด' . $off;
	}
	if ( 'quantity_tier' === $type ) {
		$steps = array();
		foreach ( $deal['tiers'] ?? array() as $tier ) {
			$steps[] = $tier['min'] . ' ชิ้นลด ' . $tier['percent'] . '%';
		}
		return amouriq_bogo_pname( $deal['product_id'] ) . ': ' . implode(' / ', $steps );
	}
	return 'ไม่รู้จักประเภทนี้';
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
	$today = current_time( 'Y-m-d' );
	if ( ! empty( $deal['valid_from'] ) && $today < $deal['valid_from'] ) {
		return 'ยังไม่ถึงวันเริ่ม';
	}
	if ( ! empty( $deal['valid_to'] ) && $today > $deal['valid_to'] ) {
		return 'หมดช่วงวันที่แล้ว';
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
function amouriq_bogo_action_form( $action, $field, $value, $label, $class = 'button', $confirm = '' ) {
	?>
	<form method="post" action="<?php echo esc_url( amouriq_bogo_page_url() ); ?>" style="display:inline"<?php echo $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\');"' : ''; ?>>
		<?php wp_nonce_field( 'amouriq_bogo_save' ); ?>
		<input type="hidden" name="amouriq_bogo_action" value="<?php echo esc_attr( $action ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<button class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

function amouriq_bogo_badge( $state ) {
	if ( 'ใช้งานอยู่' === $state ) {
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

/**
 * One table with everything that makes an order cheaper or ship free: the
 * free-shipping threshold (read only, owned by Flexible Shipping), BOGO deals
 * and coupons that grant free shipping.
 */
function amouriq_bogo_overview( $deals, $report, $types ) {
	$now       = current_time( 'timestamp' );
	$settings  = get_option( 'woocommerce_flexible_shipping_single_3_settings', array() );
	$threshold = is_array( $settings ) && isset( $settings['method_free_shipping'] ) ? $settings['method_free_shipping'] : null;
	$ship_url  = admin_url( 'admin.php?page=wc-settings&tab=shipping&instance_id=3' );
	?>
	<table class="widefat amq-table">
		<thead><tr><th style="width:150px">ประเภท</th><th>รายละเอียด</th><th style="width:130px">สถานะ</th><th style="width:110px">ใช้แล้ว</th><th style="width:230px">จัดการ</th></tr></thead>
		<tbody>
		<tr>
			<td><span class="amq-kind">ส่งฟรีอัตโนมัติ</span></td>
			<td>ยอดสั่งซื้อครบ <strong>฿<?php echo esc_html( null === $threshold ? '?' : $threshold ); ?></strong> ส่งฟรี (ตั้งค่าที่ Flexible Shipping)</td>
			<td><?php echo amouriq_bogo_badge( null === $threshold ? 'ตรวจไม่ได้' : 'ใช้งานอยู่' ); // phpcs:ignore ?></td>
			<td>—</td>
			<td><a class="button" href="<?php echo esc_url( $ship_url ); ?>">แก้ที่หน้าการจัดส่ง</a></td>
		</tr>
		<?php foreach ( $deals as $i => $deal ) :
			$rep   = $report[ $deal['id'] ?? '' ] ?? array( 'orders' => 0, 'total' => 0 );
			$state = amouriq_bogo_deal_state( $deal );
			$is_on = ! isset( $deal['enabled'] ) || $deal['enabled'];
		?>
			<tr class="<?php echo $is_on ? '' : 'amq-dim'; ?>">
				<td><span class="amq-kind amq-kind-deal">ดีล</span><br><small><?php echo esc_html( $types[ $deal['type'] ] ?? $deal['type'] ); ?></small></td>
				<td>
					<strong><?php echo esc_html( $deal['label'] ?? '' ); ?></strong><br>
					<?php echo esc_html( amouriq_bogo_detail( $deal ) ); ?><br>
					<?php echo amouriq_bogo_chips_html( amouriq_bogo_chips( $deal ) ); // phpcs:ignore ?>
				</td>
				<td><?php echo amouriq_bogo_badge( $state ); // phpcs:ignore ?></td>
				<td><?php echo esc_html( $rep['orders'] ); ?> ออเดอร์<br><small>ลดรวม ฿<?php echo esc_html( number_format( $rep['total'], 2 ) ); ?></small></td>
				<td style="white-space:nowrap">
					<?php amouriq_bogo_action_form( 'toggle_deal', 'index', $i, $is_on ? 'ปิด' : 'เปิด', $is_on ? 'button' : 'button button-primary' ); ?>
					<a class="button" href="<?php echo esc_url( amouriq_bogo_page_url() . '&edit=' . $i . '#amq-form' ); ?>">แก้ไข</a>
					<?php amouriq_bogo_action_form( 'delete', 'index', $i, 'ลบ', 'button button-link-delete', 'ลบดีลนี้?' ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( empty( $deals ) ) : ?>
			<tr><td colspan="5" class="amq-empty">ยังไม่มีดีล กด "+ เพิ่มดีลใหม่" ด้านบนเพื่อสร้างดีลแรก</td></tr>
		<?php endif; ?>
		<?php
		$coupons = get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1 ) );
		foreach ( $coupons as $post ) :
			$coupon = new WC_Coupon( $post->ID );
			if ( ! $coupon->get_free_shipping() ) {
				continue;
			}
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
				<td><span class="amq-kind amq-kind-coupon">คูปองส่งฟรี</span></td>
				<td><strong><?php echo esc_html( $coupon->get_code() ); ?></strong><br>หมดอายุ: <?php echo $expires ? esc_html( $expires->date_i18n( 'Y-m-d' ) ) : 'ไม่มีกำหนด'; ?></td>
				<td><?php echo amouriq_bogo_badge( $state ); // phpcs:ignore ?></td>
				<td><?php echo esc_html( $used ); ?>/<?php echo $limit ? esc_html( $limit ) : '∞'; ?> ครั้ง</td>
				<td style="white-space:nowrap">
					<?php amouriq_bogo_action_form( 'toggle_coupon', 'coupon_id', $post->ID, $is_on ? 'ปิด' : 'เปิด', $is_on ? 'button' : 'button button-primary' ); ?>
					<a class="button" href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">แก้ที่หน้าคูปอง</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">ปิดคูปอง = เปลี่ยนเป็นฉบับร่าง (ยังเปิดกลับได้) ส่วนลดรวมนับเฉพาะออเดอร์ที่ไม่ถูกยกเลิก/ล้มเหลว/คืนเงิน ขั้นต่ำส่งฟรีแก้ที่หน้าการจัดส่ง</p>
	<?php
}

function amouriq_bogo_admin_css() {
	?>
	<style>
		.amq-bogo .amq-top { display:flex; align-items:center; gap:12px; margin:8px 0 16px; }
		.amq-bogo h1 { margin:0; }
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

	$deals   = amouriq_bogo_deals();
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
		'product_pair'           => 'ซื้อสินค้า A แล้วสินค้า B ลดราคา',
		'category_cheapest_free' => 'ครบ N ชิ้นในหมวดที่เลือก ชิ้นถูกสุดลด',
		'cheapest_free'          => 'ครบ N ชิ้นในตะกร้า (เลือกหมวดหรือทุกหมวด) ชิ้นถูกสุดลด',
		'quantity_tier'          => 'ซื้อสินค้าตัวเดียวกันยิ่งมากยิ่งลดเป็นขั้น เช่น 2/3/4 ชิ้นลด 2/4/6%',
	);

	$groups = $show ? amouriq_bogo_product_groups() : array();
	$cats   = $show ? get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) : array();
	amouriq_bogo_admin_css();
	?>
	<div class="wrap amq-bogo">
		<div class="amq-top">
			<h1>ดีลและเงื่อนไขส่วนลด</h1>
			<?php if ( ! $show ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $back . '&add=1#amq-form' ); ?>">+ เพิ่มดีลใหม่</a>
			<?php endif; ?>
		</div>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<?php amouriq_bogo_overview( $deals, $report, $types ); ?>

		<?php if ( $show ) : ?>
		<div class="amq-card" id="amq-form">
			<form method="post" action="<?php echo esc_url( $back ); ?>">
				<?php wp_nonce_field( 'amouriq_bogo_save' ); ?>
				<input type="hidden" name="amouriq_bogo_action" value="<?php echo $editing ? 'update' : 'add'; ?>">
				<?php if ( $editing ) : ?>
					<input type="hidden" name="index" value="<?php echo esc_attr( $edit_at ); ?>">
				<?php endif; ?>

				<h2><?php echo $editing ? 'แก้ไขดีล #' . esc_html( $edit_at + 1 ) : 'เพิ่มดีลใหม่'; ?></h2>

				<h2>1. เลือกประเภทดีล</h2>
				<div class="amq-types">
					<?php foreach ( $types as $key => $text ) : ?>
						<label class="amq-type">
							<input type="radio" name="type" value="<?php echo esc_attr( $key ); ?>" <?php checked( $cur_type, $key ); ?>>
							<strong><?php echo esc_html( $text ); ?></strong>
							<span><?php echo esc_html( $type_help[ $key ] ?? '' ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>

				<h2>2. รายละเอียดดีล</h2>
				<div class="amq-grid">
					<label class="k" for="bogo_label">ป้ายในตะกร้า</label>
					<div class="v"><input type="text" name="label" id="bogo_label" class="amq-wide" maxlength="100" placeholder="เช่น ซื้อ 2 แถม 1" value="<?php echo esc_attr( $v( 'label' ) ); ?>">
						<p class="description">ข้อความที่ลูกค้าเห็นในตะกร้าและหน้าชำระเงิน</p></div>

					<label class="k amq-row" data-types="same_product quantity_tier">สินค้า</label>
					<div class="v amq-row" data-types="same_product quantity_tier"><?php amouriq_bogo_product_select( 'product_id', $v( 'product_id' ), $groups ); ?></div>

					<label class="k amq-row" data-types="product_pair">สินค้า A (ซื้อ)</label>
					<div class="v amq-row" data-types="product_pair"><?php amouriq_bogo_product_select( 'buy_product_id', $v( 'buy_product_id' ), $groups ); ?></div>

					<label class="k amq-row" data-types="product_pair">สินค้า B (ได้ส่วนลด)</label>
					<div class="v amq-row" data-types="product_pair"><?php amouriq_bogo_product_select( 'get_product_id', $v( 'get_product_id' ), $groups ); ?></div>

					<label class="k amq-row" data-types="category_cheapest_free cheapest_free" for="bogo_cat">หมวดสินค้า</label>
					<div class="v amq-row" data-types="category_cheapest_free cheapest_free">
						<select name="category" id="bogo_cat" class="amq-wide">
							<option value="">— ทุกหมวด (ใช้ได้เฉพาะประเภท "ครบ N ชิ้นในตะกร้า") —</option>
							<?php if ( ! is_wp_error( $cats ) ) : foreach ( $cats as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $v( 'category' ), $cat->slug ); ?>><?php echo esc_html( $cat->name ); ?></option>
							<?php endforeach; endif; ?>
						</select>
					</div>

					<label class="k amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free" for="bogo_bq">ซื้อกี่ชิ้น</label>
					<div class="v amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free"><input type="number" name="buy_qty" id="bogo_bq" min="1" value="<?php echo esc_attr( $v( 'buy_qty', 1 ) ); ?>"></div>

					<label class="k amq-row" data-types="same_product product_pair" for="bogo_gq">แถมกี่ชิ้น</label>
					<div class="v amq-row" data-types="same_product product_pair"><input type="number" name="get_qty" id="bogo_gq" min="1" value="<?php echo esc_attr( $v( 'get_qty', 1 ) ); ?>"></div>

					<label class="k amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free" for="bogo_pct">ส่วนลดของชิ้นที่ได้ (%)</label>
					<div class="v amq-row" data-types="same_product product_pair category_cheapest_free cheapest_free"><input type="number" name="get_discount_percent" id="bogo_pct" min="0" max="100" value="<?php echo esc_attr( $v( 'get_discount_percent', 100 ) ); ?>">
						<p class="description">100 = ฟรี</p></div>

					<span class="k amq-row" data-types="quantity_tier" style="font-weight:600;padding-top:6px">ขั้นส่วนลด</span>
					<div class="v amq-row" data-types="quantity_tier">
						<?php for ( $n = 1; $n <= 3; $n++ ) :
							$tier = isset( $editing['tiers'][ $n - 1 ] ) ? $editing['tiers'][ $n - 1 ] : array();
						?>
							<p style="margin:0 0 6px">ขั้นที่ <?php echo esc_html( $n ); ?>: ซื้อตั้งแต่
								<input type="number" name="tier_min_<?php echo esc_attr( $n ); ?>" min="1" style="width:80px" value="<?php echo esc_attr( $tier['min'] ?? '' ); ?>">
								ชิ้น ลด
								<input type="number" name="tier_pct_<?php echo esc_attr( $n ); ?>" min="0" max="100" style="width:80px" value="<?php echo esc_attr( $tier['percent'] ?? '' ); ?>"> %</p>
						<?php endfor; ?>
						<p class="description">ใช้อย่างน้อย 1 ขั้น เว้นว่างขั้นที่ไม่ใช้</p>
					</div>
				</div>

				<h2>3. ดีลนี้เริ่มทำงานเมื่อไหร่</h2>
				<div class="amq-grid">
					<span class="k" style="font-weight:600;padding-top:6px">วิธีเปิดใช้</span>
					<div class="v">
						<label><input type="radio" name="trigger" value="auto" <?php checked( $cur_trigger, 'auto' ); ?>> อัตโนมัติ ลูกค้าไม่ต้องทำอะไร ตะกร้าครบเงื่อนไขก็ลด</label><br>
						<label><input type="radio" name="trigger" value="coupon" <?php checked( $cur_trigger, 'coupon' ); ?>> ต้องใส่คูปอง ดีลทำงานเมื่อมีคูปองนี้ในตะกร้า</label>
					</div>
					<label class="k amq-row" data-trigger="coupon" for="bogo_coupon">รหัสคูปอง</label>
					<div class="v amq-row" data-trigger="coupon"><input type="text" name="coupon_code" id="bogo_coupon" class="regular-text" value="<?php echo esc_attr( $v( 'coupon_code' ) ); ?>">
						<p class="description">ต้องสร้างคูปองไว้แล้วที่ การตลาด &gt; คูปอง ระบบตรวจตอนบันทึก (สร้างเป็นส่วนลดคงที่ 0 ได้ถ้าใช้คูปองเป็นแค่ตัวเปิดดีล)</p></div>
				</div>

				<details <?php echo ( $editing && ( ! empty( $editing['exclude_sale'] ) || ! empty( $editing['no_combine'] ) || ! empty( $editing['valid_from'] ) || ! empty( $editing['valid_to'] ) || ! empty( $editing['max_uses_per_customer'] ) || ! empty( $editing['max_per_order'] ) ) ) ? 'open' : ''; ?>>
					<summary>4. ตัวเลือกเพิ่มเติม (วันที่ จำกัดสิทธิ์ ไม่ใช้ร่วมกับคูปอง)</summary>
					<div class="amq-grid">
						<span class="k" style="font-weight:600">เงื่อนไขพิเศษ</span>
						<div class="v">
							<label><input type="checkbox" name="exclude_sale" value="1" <?php checked( ! empty( $editing['exclude_sale'] ) ); ?>> ไม่รวมสินค้าที่ลดราคาอยู่แล้ว (ไม่นับและไม่ได้ส่วนลด)</label><br>
							<label><input type="checkbox" name="no_combine" value="1" <?php checked( ! empty( $editing['no_combine'] ) ); ?>> ไม่ใช้ร่วมกับคูปองอื่น (คูปองที่เปิดดีลนี้เองไม่นับ)</label>
						</div>
						<label class="k" for="bogo_from">ใช้ได้ตั้งแต่</label>
						<div class="v"><input type="date" name="valid_from" id="bogo_from" value="<?php echo esc_attr( $v( 'valid_from' ) ); ?>"> <span class="description">เว้นว่าง = ใช้ได้ทันที</span></div>
						<label class="k" for="bogo_to">ใช้ได้ถึง</label>
						<div class="v"><input type="date" name="valid_to" id="bogo_to" value="<?php echo esc_attr( $v( 'valid_to' ) ); ?>"> <span class="description">เว้นว่าง = ไม่มีวันหมด</span></div>
						<label class="k" for="bogo_maxc">จำกัดต่อลูกค้า (ครั้ง)</label>
						<div class="v"><input type="number" name="max_uses_per_customer" id="bogo_maxc" min="0" value="<?php echo esc_attr( $v( 'max_uses_per_customer', 0 ) ); ?>">
							<p class="description">0 = ไม่จำกัด ลูกค้าที่ล็อกอินนับตามบัญชี ที่ยังไม่ล็อกอินนับตามอีเมลตอนชำระเงิน</p></div>
						<label class="k" for="bogo_maxo">จำกัดต่อออเดอร์ (ครั้ง)</label>
						<div class="v"><input type="number" name="max_per_order" id="bogo_maxo" min="0" value="<?php echo esc_attr( $v( 'max_per_order', 0 ) ); ?>">
							<p class="description">0 = ไม่จำกัด</p></div>
					</div>
				</details>

				<p style="margin-top:20px">
					<?php submit_button( $editing ? 'บันทึกการแก้ไข' : 'เพิ่มดีล', 'primary', 'submit', false ); ?>
					<a class="button" href="<?php echo esc_url( $back ); ?>" style="margin-left:6px">ยกเลิก</a>
				</p>
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
				form.querySelectorAll('[data-types]').forEach(function (el) {
					el.hidden = el.getAttribute('data-types').split(' ').indexOf(type) === -1;
				});
				form.querySelectorAll('[data-trigger]').forEach(function (el) {
					el.hidden = el.getAttribute('data-trigger') !== trig;
				});
			}
			form.addEventListener('change', sync);
			sync();
		})();
		</script>
		<?php endif; ?>
	</div>
	<?php
}
