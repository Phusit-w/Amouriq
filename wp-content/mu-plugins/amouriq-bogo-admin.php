<?php
/**
 * Plugin Name: AMOURIQ: BOGO deals admin
 * Description: WooCommerce > ดีล BOGO screen to create, list and delete BOGO
 *              deals without editing code. Deals are stored in the
 *              `amouriq_bogo_deals` option; the engine in amouriq-bogo-deals.php
 *              reads that option, and falls back to amouriq-bogo/config.php
 *              only until the first save.
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
	);
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
		$deal['coupon_code'] = $code;
	}

	$int = function ( $key, $default = 0 ) use ( $in ) {
		return isset( $in[ $key ] ) && '' !== $in[ $key ] ? absint( $in[ $key ] ) : $default;
	};

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
	$notice = '';

	if ( 'delete' === $action ) {
		$index = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;
		if ( isset( $deals[ $index ] ) ) {
			unset( $deals[ $index ] );
			$notice = 'ลบดีลแล้ว';
		}
	} elseif ( 'add' === $action ) {
		$result = amouriq_bogo_build_deal( $_POST );
		if ( is_string( $result ) ) {
			set_transient( 'amouriq_bogo_error_' . get_current_user_id(), $result, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=amouriq-bogo' ) );
			exit;
		}
		$deals[] = $result;
		$notice  = 'เพิ่มดีลแล้ว';
	}

	update_option( 'amouriq_bogo_deals', array_values( $deals ), false );
	set_transient( 'amouriq_bogo_notice_' . get_current_user_id(), $notice, 60 );
	wp_safe_redirect( admin_url( 'admin.php?page=amouriq-bogo' ) );
	exit;
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
	} else {
		$detail = 'ไม่รู้จักประเภทนี้';
	}

	return $detail . ' | ' . $trig;
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

	$deals  = amouriq_bogo_deals();
	$types  = amouriq_bogo_type_labels();
	$action = admin_url( 'admin.php?page=amouriq-bogo' );
	?>
	<div class="wrap">
		<h1>ดีล BOGO</h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<h2>ดีลที่ใช้งานอยู่</h2>
		<?php if ( empty( $deals ) ) : ?>
			<p>ยังไม่มีดีล</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><th>#</th><th>ประเภท</th><th>รายละเอียด</th><th>ป้ายในตะกร้า</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $deals as $i => $deal ) : ?>
					<tr>
						<td><?php echo esc_html( $i + 1 ); ?></td>
						<td><?php echo esc_html( $types[ $deal['type'] ] ?? $deal['type'] ); ?></td>
						<td><?php echo esc_html( amouriq_bogo_summary( $deal ) ); ?></td>
						<td><?php echo esc_html( $deal['label'] ?? '' ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( $action ); ?>" onsubmit="return confirm('ลบดีลนี้?');">
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
		<?php endif; ?>

		<h2 style="margin-top:2em">เพิ่มดีลใหม่</h2>
		<form method="post" action="<?php echo esc_url( $action ); ?>" style="max-width:700px">
			<?php wp_nonce_field( 'amouriq_bogo_save' ); ?>
			<input type="hidden" name="amouriq_bogo_action" value="add">
			<table class="form-table" role="presentation">
				<tr><th><label for="bogo_type">ประเภทดีล</label></th>
					<td><select name="type" id="bogo_type">
						<?php foreach ( $types as $key => $text ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $text ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
				<tr><th><label for="bogo_label">ป้ายในตะกร้า</label></th>
					<td><input type="text" name="label" id="bogo_label" class="regular-text" maxlength="100" placeholder="เช่น ซื้อ 2 แถม 1"></td></tr>
				<tr><th><label for="bogo_pid">สินค้า/variation ID</label></th>
					<td><input type="number" name="product_id" id="bogo_pid" min="1">
						<p class="description">ใช้กับ "ซื้อ X แถม Y" ใส่ ID ของ variation ได้</p></td></tr>
				<tr><th><label for="bogo_buy">สินค้า A (buy) ID</label></th>
					<td><input type="number" name="buy_product_id" id="bogo_buy" min="1">
						<p class="description">ใช้กับ "ซื้อ A แล้วได้ B"</p></td></tr>
				<tr><th><label for="bogo_get">สินค้า B (get) ID</label></th>
					<td><input type="number" name="get_product_id" id="bogo_get" min="1"></td></tr>
				<tr><th><label for="bogo_cat">หมวด (slug)</label></th>
					<td><input type="text" name="category" id="bogo_cat" class="regular-text" placeholder="เช่น castile-soap">
						<p class="description">ใช้กับ "ครบ N ชิ้นในหมวด"</p></td></tr>
				<tr><th><label for="bogo_bq">ซื้อกี่ชิ้น (buy_qty)</label></th>
					<td><input type="number" name="buy_qty" id="bogo_bq" min="1" value="1"></td></tr>
				<tr><th><label for="bogo_gq">แถมกี่ชิ้น (get_qty)</label></th>
					<td><input type="number" name="get_qty" id="bogo_gq" min="1" value="1">
						<p class="description">ใช้กับ "ซื้อ X แถม Y" และ "ซื้อ A แล้วได้ B" (หมวดไม่ใช้)</p></td></tr>
				<tr><th><label for="bogo_pct">ส่วนลดของชิ้นที่ได้ (%)</label></th>
					<td><input type="number" name="get_discount_percent" id="bogo_pct" min="0" max="100" value="100">
						<p class="description">100 = ฟรี</p></td></tr>
				<tr><th>เปิดใช้งาน</th>
					<td>
						<label><input type="radio" name="trigger" value="auto" checked> อัตโนมัติ</label><br>
						<label><input type="radio" name="trigger" value="coupon"> ต้องใส่คูปอง</label>
						<p><input type="text" name="coupon_code" class="regular-text" placeholder="รหัสคูปอง (เฉพาะกรณีต้องใส่คูปอง)"></p>
						<p class="description">รหัสต้องตรงกับคูปองที่สร้างใน การตลาด > คูปอง</p>
					</td></tr>
			</table>
			<?php submit_button( 'เพิ่มดีล' ); ?>
		</form>
	</div>
	<?php
}
