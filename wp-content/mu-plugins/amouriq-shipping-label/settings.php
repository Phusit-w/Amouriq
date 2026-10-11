<?php
/**
 * The owner's settings for the shipping label: WooCommerce > ใบปะหน้าพัสดุ.
 * One option holds them all (see amouriq_label_defaults() for the fields); the
 * page shows a live preview built from sample data. Empty sender/logo fields fall
 * back to the print plugin's own settings.
 */

defined( 'ABSPATH' ) || exit;

const AMOURIQ_LABEL_OPTION = 'amouriq_label_settings';

function amouriq_label_options() {
	return amouriq_label_clean_options( get_option( AMOURIQ_LABEL_OPTION, array() ) );
}

add_action( 'admin_menu', 'amouriq_label_settings_menu' );

function amouriq_label_settings_menu() {
	add_submenu_page( 'woocommerce', 'ใบปะหน้าพัสดุ', 'ใบปะหน้าพัสดุ', 'manage_woocommerce', 'amouriq-label', 'amouriq_label_settings_page' );
}

add_action( 'admin_init', 'amouriq_label_settings_save' );

function amouriq_label_settings_save() {
	if ( empty( $_POST['amouriq_label_save'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	check_admin_referer( 'amouriq_label_save' );

	if ( ! empty( $_POST['amouriq_label_reset'] ) ) {
		delete_option( AMOURIQ_LABEL_OPTION );
		$message = 'คืนค่าเริ่มต้นแล้ว';
	} else {
		$in    = wp_unslash( $_POST );
		$clean = amouriq_label_clean_options(
			array(
				'logo_url'       => isset( $in['logo_url'] ) ? $in['logo_url'] : '',
				'logo_width'     => isset( $in['logo_width'] ) ? $in['logo_width'] : '',
				'show_logo'      => ! empty( $in['show_logo'] ),
				'sender_name'    => isset( $in['sender_name'] ) ? $in['sender_name'] : '',
				'sender_address' => isset( $in['sender_address'] ) ? $in['sender_address'] : '',
				'sender_phone'   => isset( $in['sender_phone'] ) ? $in['sender_phone'] : '',
				'font_size'      => isset( $in['font_size'] ) ? $in['font_size'] : '',
				'show_option'    => ! empty( $in['show_option'] ),
				'show_to_phone'  => ! empty( $in['show_to_phone'] ),
				'note'           => isset( $in['note'] ) ? $in['note'] : '',
			)
		);
		update_option( AMOURIQ_LABEL_OPTION, $clean, false );
		$message = 'บันทึกใบปะหน้าแล้ว';
	}
	set_transient( 'amouriq_label_notice_' . get_current_user_id(), $message, 60 );
	wp_safe_redirect( admin_url( 'admin.php?page=amouriq-label' ) );
	exit;
}

function amouriq_label_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$notice_key = 'amouriq_label_notice_' . get_current_user_id();
	$notice     = get_transient( $notice_key );
	if ( $notice ) {
		delete_transient( $notice_key );
	}
	$o = amouriq_label_options();

	$sample = array(
		'options'      => $o,
		'logo_url'     => $o['logo_url'],
		'sender'       => array(
			'name'  => '' !== $o['sender_name'] ? $o['sender_name'] : '(ชื่อผู้ส่งจากปลั๊กอินพิมพ์เอกสาร)',
			'lines' => '' !== $o['sender_address'] ? explode( "\n", $o['sender_address'] ) : array( '(ที่อยู่ผู้ส่งจากปลั๊กอินพิมพ์เอกสาร)' ),
			'phone' => '' !== $o['sender_phone'] ? $o['sender_phone'] : '02-000-0000',
		),
		'recipient'    => array( 'lines' => array( 'สมชาย ใจดี', '99/9 หมู่ 1 ต.บางกะปิ เขตห้วยขวาง กรุงเทพฯ 10310' ), 'phone' => '081-234-5678' ),
		'order_number' => '1234',
		'items'        => array(
			array( 'name' => 'Organic Olive Castile Soap - Lavender', 'option' => 'ปริมาณ: 250 ml', 'qty' => 2 ),
			array( 'name' => 'Organic Olive Castile Soap - Rosemary', 'option' => 'ปริมาณ: 100 ml', 'qty' => 1 ),
		),
	);
	?>
	<div class="wrap">
		<h1>ใบปะหน้าพัสดุ</h1>
		<?php if ( $notice ) : ?><div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
		<p>ตั้งค่ารูปแบบใบปะหน้าที่พิมพ์จากหน้าออเดอร์ ช่องผู้ส่งหรือโลโก้ที่เว้นว่างจะใช้ค่าจากปลั๊กอินพิมพ์เอกสารเดิม ใบปะหน้าไม่แสดงราคา ส่วนลด หรือยอดชำระเสมอ</p>
		<div style="display:flex;gap:32px;flex-wrap:wrap;align-items:flex-start">
		<form method="post" style="flex:1 1 360px;max-width:520px">
			<?php wp_nonce_field( 'amouriq_label_save' ); ?>
			<input type="hidden" name="amouriq_label_save" value="1">
			<table class="form-table" role="presentation">
				<tr><th><label for="lb_logo">ลิงก์รูปโลโก้</label></th><td><input type="url" class="regular-text" id="lb_logo" name="logo_url" value="<?php echo esc_attr( $o['logo_url'] ); ?>" placeholder="https://…"><p class="description">เว้นว่าง = ใช้โลโก้ของปลั๊กอินพิมพ์เอกสาร</p></td></tr>
				<tr><th>แสดงโลโก้</th><td><label><input type="checkbox" name="show_logo" value="1" <?php checked( $o['show_logo'] ); ?>> แสดงโลโก้</label> &nbsp; กว้างสูงสุด <input type="number" name="logo_width" min="40" max="400" value="<?php echo esc_attr( $o['logo_width'] ); ?>" style="width:70px"> px</td></tr>
				<tr><th><label for="lb_sname">ชื่อผู้ส่ง / ร้าน</label></th><td><input type="text" class="regular-text" id="lb_sname" name="sender_name" value="<?php echo esc_attr( $o['sender_name'] ); ?>"></td></tr>
				<tr><th><label for="lb_saddr">ที่อยู่ผู้ส่ง</label></th><td><textarea class="large-text" rows="3" id="lb_saddr" name="sender_address"><?php echo esc_textarea( $o['sender_address'] ); ?></textarea><p class="description">หนึ่งบรรทัดต่อหนึ่งแถว เว้นว่าง = ใช้ที่อยู่จากปลั๊กอินพิมพ์เอกสาร</p></td></tr>
				<tr><th><label for="lb_sphone">เบอร์โทรผู้ส่ง</label></th><td><input type="text" class="regular-text" id="lb_sphone" name="sender_phone" value="<?php echo esc_attr( $o['sender_phone'] ); ?>"></td></tr>
				<tr><th><label for="lb_font">ขนาดตัวอักษร</label></th><td><input type="number" id="lb_font" name="font_size" min="10" max="24" value="<?php echo esc_attr( $o['font_size'] ); ?>" style="width:70px"> px (10 ถึง 24)</td></tr>
				<tr><th>ตารางสินค้า</th><td><label><input type="checkbox" name="show_option" value="1" <?php checked( $o['show_option'] ); ?>> แสดงคอลัมน์ตัวเลือก (ขนาด / กลิ่น)</label></td></tr>
				<tr><th>ผู้รับ</th><td><label><input type="checkbox" name="show_to_phone" value="1" <?php checked( $o['show_to_phone'] ); ?>> แสดงเบอร์โทรผู้รับ</label></td></tr>
				<tr><th><label for="lb_note">ข้อความท้ายใบ</label></th><td><textarea class="large-text" rows="3" id="lb_note" name="note"><?php echo esc_textarea( $o['note'] ); ?></textarea><p class="description">เช่น "สินค้าแตกหักง่าย" เว้นว่าง = ไม่แสดง</p></td></tr>
			</table>
			<p>
				<button class="button button-primary">บันทึก</button>
				<button class="button" name="amouriq_label_reset" value="1" onclick="return confirm('คืนค่าเริ่มต้นทั้งหมด ใช่ไหม?');">คืนค่าเริ่มต้น</button>
			</p>
		</form>
		<div style="flex:1 1 380px;max-width:560px">
			<h2 style="margin-top:0">ตัวอย่าง (บันทึกแล้วจึงเห็นการเปลี่ยนแปลง)</h2>
			<div style="background:#fff;border:1px solid #c3c4c7;padding:16px">
				<?php echo amouriq_label_html( $sample ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside amouriq_label_html(). ?>
			</div>
		</div>
		</div>
	</div>
	<?php
}
