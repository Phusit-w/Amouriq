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

function amouriq_bogo_summary( $deal ) {
	$type = isset( $deal['type'] ) ? $deal['type'] : '';
	$pct  = isset( $deal['get_discount_percent'] ) ? $deal['get_discount_percent'] : 100;
	$trig = ( isset( $deal['trigger'] ) && 'coupon' === $deal['trigger'] )
		? 'คูปอง ' . ( $deal['coupon_code'] ?? '' ) : 'อัตโนมัติ';

	if ( 'same_product' === $type ) {
		$detail = 'สินค้า/variation ' . $deal['product_id'] . ' ซื้อ ' . $deal['buy_qty'] . ' แถม ' . $deal['get_qty'] . ' (ลด ' . $pct . '%)';
	} elseif ( 'product_pair' === $type ) {
		$detail = 'ซื้อ ' . $deal['buy_product_id'] . ' × ' . ( $deal['buy_qty'] ?? 1 ) . ' ได้ ' . $deal['get_product_id'] . ' × ' . ( $deal['get_qty'] ?? 1 ) . ' (ลด ' . $pct . '%)';
	} elseif ( 'category_cheapest_free' === $type ) {
		$detail = 'หมวด ' . $deal['category'] . ' ครบ ' . $deal['buy_qty'] . ' ชิ้น ชิ้นถูกสุดลด ' . $pct . '%';
	} elseif ( 'cheapest_free' === $type ) {
		$scope  = ! empty( $deal['category'] ) ? 'หมวด ' . $deal['category'] : 'ทุกหมวด';
		$detail = $scope . ' ครบ ' . $deal['buy_qty'] . ' ชิ้น ชิ้นถูกสุดลด ' . $pct . '%';
	} elseif ( 'quantity_tier' === $type ) {
		$steps = array();
		foreach ( $deal['tiers'] ?? array() as $tier ) {
			$steps[] = $tier['min'] . ' ชิ้น ลด ' . $tier['percent'] . '%';
		}
		$detail = 'สินค้า/variation ' . $deal['product_id'] . ': ' . implode( ', ', $steps );
	} else {
		$detail = 'ไม่รู้จักประเภทนี้';
	}

	$extra = array( $trig );
	if ( ! empty( $deal['exclude_sale'] ) ) {
		$extra[] = 'ไม่รวมสินค้าลดราคา';
	}
	if ( ! empty( $deal['no_combine'] ) ) {
		$extra[] = 'ไม่ใช้ร่วมกับคูปองอื่น';
	}
	if ( ! empty( $deal['valid_from'] ) || ! empty( $deal['valid_to'] ) ) {
		$extra[] = 'ช่วง ' . ( $deal['valid_from'] ?? '…' ) . ' ถึง ' . ( $deal['valid_to'] ?? '…' );
	}
	if ( ! empty( $deal['max_uses_per_customer'] ) ) {
		$extra[] = 'ต่อลูกค้า ' . $deal['max_uses_per_customer'] . ' ครั้ง';
	}
	if ( ! empty( $deal['max_per_order'] ) ) {
		$extra[] = 'ต่อออเดอร์ ' . $deal['max_per_order'] . ' ครั้ง';
	}

	return $detail . ' | ' . implode( ' | ', $extra );
}

/**
 * Product and variation names for the ID fields, so the admin can type a name
 * and pick the matching ID.
 */
function amouriq_bogo_product_options() {
	$options  = array();
	$products = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'type' => array( 'simple', 'variable' ) ) );
	foreach ( $products as $product ) {
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child ) {
					$options[ $child->get_id() ] = $child->get_name();
				}
			}
		} else {
			$options[ $product->get_id() ] = $product->get_name();
		}
	}
	return $options;
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

function amouriq_bogo_toggle_form( $action, $field, $value, $is_on ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=amouriq-bogo' ) ); ?>" style="display:inline">
		<?php wp_nonce_field( 'amouriq_bogo_save' ); ?>
		<input type="hidden" name="amouriq_bogo_action" value="<?php echo esc_attr( $action ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<button class="button"><?php echo $is_on ? 'ปิด' : 'เปิด'; ?></button>
	</form>
	<?php
}

/**
 * Overview of every condition that can make an order cheaper or
 * ship free: BOGO deals, the automatic free-shipping threshold (owned by
 * Flexible Shipping, not edited here), and coupons that grant free shipping.
 */
function amouriq_bogo_status_panel( $deals ) {
	$now = current_time( 'timestamp' );
	$today = wp_date( 'Y-m-d', $now );

	// Automatic free shipping threshold lives in Flexible Shipping (zone Thailand).
	$settings  = get_option( 'woocommerce_flexible_shipping_single_3_settings', array() );
	$threshold = is_array( $settings ) && isset( $settings['method_free_shipping'] ) ? $settings['method_free_shipping'] : null;
	$edit_url  = admin_url( 'admin.php?page=wc-settings&tab=shipping&instance_id=3' );
	?>
	<h2>เงื่อนไขที่เปิดใช้งานอยู่</h2>
	<table class="widefat striped" style="max-width:1200px">
		<thead><tr><th>เงื่อนไข</th><th>รายละเอียด</th><th>สถานะ</th><th></th></tr></thead>
		<tbody>
		<tr>
			<td>ส่งฟรีอัตโนมัติ (Flexible Shipping)</td>
			<td>ขั้นต่อ ฿<?php echo esc_html( null === $threshold ? 'ไม่พบค่า' : $threshold ); ?> — <a href="<?php echo esc_url( $edit_url ); ?>">แก้ไขที่หน้าการจัดส่ง</a></td>
			<td><?php echo null === $threshold ? 'ตรวจไม่ได้' : 'ใช้งานอยู่'; ?></td>
			<td></td>
		</tr>
		<?php foreach ( $deals as $deal_index => $deal ) :
			$deal_on   = ! isset( $deal['enabled'] ) || $deal['enabled'];
			$trigger   = ( isset( $deal['trigger'] ) && 'coupon' === $deal['trigger'] ) ? 'คูปอง ' . ( $deal['coupon_code'] ?? '' ) : 'อัตโนมัติ';
			$status    = amouriq_bogo_deal_state( $deal );
		?>
			<tr>
				<td>ดีล BOGO: <?php echo esc_html( $deal['label'] ?? '' ); ?></td>
				<td><?php echo esc_html( amouriq_bogo_summary( $deal ) ); ?></td>
				<td><?php echo esc_html( $status . ' (' . $trigger . ')' ); ?></td>
				<td><?php amouriq_bogo_toggle_form( 'toggle_deal', 'index', $deal_index, $deal_on ); ?></td>
			</tr>
		<?php endforeach; ?>
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
		?>
			<tr>
				<td>คูปองส่งฟรี: <?php echo esc_html( $coupon->get_code() ); ?></td>
				<td>หมดอายุ: <?php echo $expires ? esc_html( $expires->date_i18n( 'Y-m-d' ) ) : 'ไม่มีกำหนด'; ?> | ใช้แล้ว <?php echo esc_html( $used ); ?>/<?php echo $limit ? esc_html( $limit ) : '∞'; ?></td>
				<td><?php echo esc_html( $state ); ?></td>
				<td><?php amouriq_bogo_toggle_form( 'toggle_coupon', 'coupon_id', $post->ID, 'draft' !== $post->post_status ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">ปุ่มเปิด/ปิดใช้ได้กับดีลและคูปองส่งฟรี (ปิดคูปอง = เปลี่ยนเป็นฉบับร่าง) ส่วนขั้นต่ำส่งฟรีแก้ที่หน้าการจัดส่ง (วันที่วันนี้: <?php echo esc_html( $today ); ?>)</p>
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
	$back    = admin_url( 'admin.php?page=amouriq-bogo' );
	$edit_at = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : -1;
	$editing = isset( $deals[ $edit_at ] ) ? $deals[ $edit_at ] : null;

	$v = function ( $key, $default = '' ) use ( $editing ) {
		return ( $editing && isset( $editing[ $key ] ) ) ? $editing[ $key ] : $default;
	};
	$is_type    = function ( $t ) use ( $v ) { return $v( 'type', 'same_product' ) === $t; };
	$is_trigger = function ( $t ) use ( $v ) { return $v( 'trigger', 'auto' ) === $t; };
	?>
	<div class="wrap">
		<h1>ดีล BOGO</h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<?php amouriq_bogo_status_panel( $deals ); ?>

		<h2>ดีลที่ใช้งานอยู่</h2>
		<?php if ( empty( $deals ) ) : ?>
			<p>ยังไม่มีดีล</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:1200px">
				<thead><tr><th>#</th><th>ประเภท</th><th>รายละเอียด</th><th>ป้ายในตะกร้า</th><th>สถานะ</th><th>ใช้แล้ว (ออเดอร์)</th><th>ส่วนลดรวม (ก่อนภาษี)</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $deals as $i => $deal ) :
					$id  = $deal['id'] ?? '';
					$rep = $report[ $id ] ?? array( 'orders' => 0, 'total' => 0 );
				?>
					<tr>
						<td><?php echo esc_html( $i + 1 ); ?></td>
						<td><?php echo esc_html( $types[ $deal['type'] ] ?? $deal['type'] ); ?></td>
						<td><?php echo esc_html( amouriq_bogo_summary( $deal ) ); ?></td>
						<td><?php echo esc_html( $deal['label'] ?? '' ); ?></td>
						<td><?php echo esc_html( amouriq_bogo_deal_state( $deal ) ); ?></td>
						<td><?php echo esc_html( $rep['orders'] ); ?></td>
						<td><?php echo esc_html( number_format( $rep['total'], 2 ) ); ?></td>
						<td style="white-space:nowrap">
							<a class="button" href="<?php echo esc_url( $back . '&edit=' . $i ); ?>">แก้ไข</a>
							<form method="post" action="<?php echo esc_url( $back ); ?>" style="display:inline" onsubmit="return confirm('ลบดีลนี้?');">
								<?php wp_nonce_field( 'amouriq_bogo_save' ); ?>
								<input type="hidden" name="amouriq_bogo_action" value="delete">
								<input type="hidden" name="index" value="<?php echo esc_attr( $i ); ?>">
								<button class="button button-link-delete">ลบ</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">ส่วนลดรวมนับเฉพาะออเดอร์ที่ยังไม่ถูกยกเลิก/ล้มเหลว/คืนเงิน</p>
		<?php endif; ?>

		<h2 style="margin-top:2em"><?php echo $editing ? 'แก้ไขดีล #' . esc_html( $edit_at + 1 ) : 'เพิ่มดีลใหม่'; ?></h2>
		<?php
		$products = amouriq_bogo_product_options();
		$cats     = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		?>
		<datalist id="bogo-products">
			<?php foreach ( $products as $pid => $pname ) : ?>
				<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pname ); ?></option>
			<?php endforeach; ?>
		</datalist>
		<datalist id="bogo-cats">
			<?php if ( ! is_wp_error( $cats ) ) : foreach ( $cats as $cat ) : ?>
				<option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
			<?php endforeach; endif; ?>
		</datalist>

		<form method="post" action="<?php echo esc_url( $back ); ?>" style="max-width:760px">
			<?php wp_nonce_field( 'amouriq_bogo_save' ); ?>
			<input type="hidden" name="amouriq_bogo_action" value="<?php echo $editing ? 'update' : 'add'; ?>">
			<?php if ( $editing ) : ?>
				<input type="hidden" name="index" value="<?php echo esc_attr( $edit_at ); ?>">
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="bogo_type">ประเภทดีล</label></th>
					<td><select name="type" id="bogo_type">
						<?php foreach ( $types as $key => $text ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $is_type( $key ) ); ?>><?php echo esc_html( $text ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
				<tr><th><label for="bogo_label">ป้ายในตะกร้า</label></th>
					<td><input type="text" name="label" id="bogo_label" class="regular-text" maxlength="100" placeholder="เช่น ซื้อ 2 แถม 1" value="<?php echo esc_attr( $v( 'label' ) ); ?>"></td></tr>
				<tr><th><label for="bogo_pid">สินค้า/variation</label></th>
					<td><input type="text" name="product_id" id="bogo_pid" list="bogo-products" inputmode="numeric" value="<?php echo esc_attr( $v( 'product_id' ) ); ?>">
						<p class="description">พิมพ์ชื่อแล้วเลือกจากรายการ หรือพิมพ์ ID (ใช้กับ "ซื้อ X แถม Y")</p></td></tr>
				<tr><th><label for="bogo_buy">สินค้า A (ซื้อ)</label></th>
					<td><input type="text" name="buy_product_id" id="bogo_buy" list="bogo-products" inputmode="numeric" value="<?php echo esc_attr( $v( 'buy_product_id' ) ); ?>">
						<p class="description">ใช้กับ "ซื้อ A แล้วได้ B"</p></td></tr>
				<tr><th><label for="bogo_get">สินค้า B (ได้รับส่วนลด)</label></th>
					<td><input type="text" name="get_product_id" id="bogo_get" list="bogo-products" inputmode="numeric" value="<?php echo esc_attr( $v( 'get_product_id' ) ); ?>"></td></tr>
				<tr><th><label for="bogo_cat">หมวดสินค้า</label></th>
					<td><input type="text" name="category" id="bogo_cat" list="bogo-cats" class="regular-text" value="<?php echo esc_attr( $v( 'category' ) ); ?>">
						<p class="description">ใช้กับ "ครบ N ชิ้นในหมวด"</p></td></tr>
				<tr><th><label for="bogo_bq">ซื้อกี่ชิ้น</label></th>
					<td><input type="number" name="buy_qty" id="bogo_bq" min="1" value="<?php echo esc_attr( $v( 'buy_qty', 1 ) ); ?>"></td></tr>
				<tr><th><label for="bogo_gq">แถมกี่ชิ้น</label></th>
					<td><input type="number" name="get_qty" id="bogo_gq" min="1" value="<?php echo esc_attr( $v( 'get_qty', 1 ) ); ?>">
						<p class="description">ใช้กับ "ซื้อ X แถม Y" และ "ซื้อ A แล้วได้ B" (หมวดไม่ใช้)</p></td></tr>
				<tr><th><label for="bogo_pct">ส่วนลดของชิ้นที่ได้ (%)</label></th>
					<td><input type="number" name="get_discount_percent" id="bogo_pct" min="0" max="100" value="<?php echo esc_attr( $v( 'get_discount_percent', 100 ) ); ?>">
						<p class="description">100 = ฟรี</p></td></tr>
				<tr><th>เปิดใช้งาน</th>
					<td>
						<label><input type="radio" name="trigger" value="auto" <?php checked( $is_trigger( 'auto' ) ); ?>> อัตโนมัติ</label><br>
						<label><input type="radio" name="trigger" value="coupon" <?php checked( $is_trigger( 'coupon' ) ); ?>> ต้องใส่คูปอง</label>
						<p><input type="text" name="coupon_code" class="regular-text" placeholder="รหัสคูปอง (เฉพาะกรณีต้องใส่คูปอง)" value="<?php echo esc_attr( $v( 'coupon_code' ) ); ?>"></p>
						<p class="description">รหัสต้องมีอยู่จริงในการตลาด &gt; คูปอง ระบบจะตรวจตอนบันทึก</p>
					</td></tr>
				<tr><th>ขั้นส่วนลด (ใช้กับ "ซื้อตามจำนวน")</th>
					<td>
						<?php for ( $n = 1; $n <= 3; $n++ ) :
							$tier = isset( $editing['tiers'][ $n - 1 ] ) ? $editing['tiers'][ $n - 1 ] : array();
						?>
							ขั้นที่ <?php echo esc_html( $n ); ?>: ซื้อตั้งแต่
							<input type="number" name="tier_min_<?php echo esc_attr( $n ); ?>" min="1" style="width:80px" value="<?php echo esc_attr( $tier['min'] ?? '' ); ?>">
							ชิ้น ลด
							<input type="number" name="tier_pct_<?php echo esc_attr( $n ); ?>" min="0" max="100" style="width:80px" value="<?php echo esc_attr( $tier['percent'] ?? '' ); ?>">
							%<br>
						<?php endfor; ?>
					</td></tr>
				<tr><th>ไม่รวมสินค้าลดราคา</th>
					<td><label><input type="checkbox" name="exclude_sale" value="1" <?php checked( ! empty( $editing['exclude_sale'] ) ); ?>> ข้ามสินค้าที่ลดราคาอยู่แล้ว (สินค้านั้นไม่นับและไม่ได้ส่วนลด)</label></td></tr>
				<tr><th>ไม่ใช้ร่วมกับคูปองอื่น</th>
					<td><label><input type="checkbox" name="no_combine" value="1" <?php checked( ! empty( $editing['no_combine'] ) ); ?>> ถ้ามีคูปองอื่นในตะกร้า ดีลนี้จะไม่ลด (คูปองที่ใช้เปิดดีลนี้เองไม่นับ)</label></td></tr>
				<tr><th><label for="bogo_from">ใช้ได้ตั้งแต่</label></th>
					<td><input type="date" name="valid_from" id="bogo_from" value="<?php echo esc_attr( $v( 'valid_from' ) ); ?>">
						<p class="description">เว้นว่างได้ = ใช้ได้ทันที</p></td></tr>
				<tr><th><label for="bogo_to">ใช้ได้ถึง</label></th>
					<td><input type="date" name="valid_to" id="bogo_to" value="<?php echo esc_attr( $v( 'valid_to' ) ); ?>">
						<p class="description">เว้นว่างได้ = ไม่มีวันหมด</p></td></tr>
				<tr><th><label for="bogo_maxc">จำกัดต่อลูกค้า (ครั้ง)</label></th>
					<td><input type="number" name="max_uses_per_customer" id="bogo_maxc" min="0" value="<?php echo esc_attr( $v( 'max_uses_per_customer', 0 ) ); ?>">
						<p class="description">จำนวนออเดอร์สูงสุดที่ลูกค้าคนหนึ่งใช้ดีลนี้ได้ 0 = ไม่จำกัด (ลูกค้าที่ล็อกอินนับตามบัญชี ลูกค้าไม่ล็อกอินนับตามอีเมล)</p></td></tr>
				<tr><th><label for="bogo_maxo">จำกัดต่อออเดอร์ (ครั้ง)</label></th>
					<td><input type="number" name="max_per_order" id="bogo_maxo" min="0" value="<?php echo esc_attr( $v( 'max_per_order', 0 ) ); ?>">
						<p class="description">ดีลนี้ลดได้สูงสุดกี่ครั้งต่อหนึ่งตะกร้า 0 = ไม่จำกัด</p></td></tr>
			</table>
			<?php submit_button( $editing ? 'บันทึกการแก้ไข' : 'เพิ่มดีล' ); ?>
			<?php if ( $editing ) : ?>
				<a href="<?php echo esc_url( $back ); ?>">ยกเลิกการแก้ไข</a>
			<?php endif; ?>
		</form>
	</div>
	<?php
}
