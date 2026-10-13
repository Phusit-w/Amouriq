<?php
/**
 * The owner's settings for the shipping label: WooCommerce > ใบปะหน้าพัสดุ.
 * One option holds them all (see amouriq_label_defaults() for the fields). The page groups them into
 * cards, with switches for what the label shows, and a live preview that redraws as the form changes
 * (the preview is the real label, drawn by the server from the form's current values). Empty sender/logo
 * fields fall back to the print plugin's own settings.
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
		update_option( AMOURIQ_LABEL_OPTION, amouriq_label_options_from_input( wp_unslash( $_POST ) ), false );
		$message = 'บันทึกใบปะหน้าแล้ว';
	}
	set_transient( 'amouriq_label_notice_' . get_current_user_id(), $message, 60 );
	wp_safe_redirect( admin_url( 'admin.php?page=amouriq-label' ) );
	exit;
}

// The live preview: the form's current values in, the drawn label out. Nothing is saved.
add_action( 'wp_ajax_amouriq_label_preview', 'amouriq_label_preview_ajax' );

function amouriq_label_preview_ajax() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'amouriq_label_preview', 'preview_nonce' );
	$o = amouriq_label_options_from_input( wp_unslash( $_POST ) );
	wp_send_json_success(
		array(
			'html' => amouriq_label_html( amouriq_label_sample( $o ) ),
			'w'    => $o['page_width'],
			'h'    => $o['page_height'],
		)
	);
}

/** One on/off row: a switch, what it is, and a hint of what it does. */
function amouriq_label_switch( $name, $checked, $title, $hint = '' ) {
	?>
	<label class="amq-sw">
		<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
		<span class="amq-sw-ui" aria-hidden="true"></span>
		<span class="amq-sw-text"><strong><?php echo esc_html( $title ); ?></strong><?php if ( '' !== $hint ) : ?><small><?php echo esc_html( $hint ); ?></small><?php endif; ?></span>
	</label>
	<?php
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
	?>
	<style>
		.amq-lbl h1 { margin-bottom:4px; }
		.amq-lbl-grid { display:grid; grid-template-columns:minmax(0,1fr) 440px; gap:20px; align-items:start; margin-top:14px; }
		@media (max-width:1100px) { .amq-lbl-grid { grid-template-columns:1fr; } }
		.amq-lbl-card { background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:16px 18px; margin:0 0 14px; }
		.amq-lbl-card h2 { margin:0 0 4px; font-size:15px; } .amq-lbl-card > .description { margin:0 0 10px; }
		.amq-sw { display:flex; gap:12px; align-items:flex-start; padding:9px 0; border-top:1px solid #f0f0f1; cursor:pointer; }
		.amq-sw:first-of-type { border-top:0; }
		.amq-sw input { position:absolute; opacity:0; }
		.amq-sw-ui { flex:none; position:relative; width:38px; height:22px; border-radius:11px; background:#a7aaad; transition:background .15s; margin-top:1px; }
		.amq-sw-ui::after { content:""; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:#fff; transition:left .15s; }
		.amq-sw input:checked + .amq-sw-ui { background:#2e8b4b; }
		.amq-sw input:checked + .amq-sw-ui::after { left:19px; }
		.amq-sw input:focus-visible + .amq-sw-ui { outline:2px solid #2271b1; outline-offset:2px; }
		.amq-sw-text strong { display:block; font-weight:600; } .amq-sw-text small { color:#646970; }
		.amq-lbl-field { display:grid; grid-template-columns:150px minmax(0,1fr); gap:6px 14px; align-items:start; margin:10px 0; }
		.amq-lbl-field > label { font-weight:600; padding-top:5px; }
		.amq-lbl-field input[type=text], .amq-lbl-field input[type=url], .amq-lbl-field textarea { width:100%; max-width:480px; }
		@media (max-width:782px) { .amq-lbl-field { grid-template-columns:1fr; } }
		.amq-lbl-presets { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
		.amq-lbl-side { position:sticky; top:42px; }
		.amq-lbl-paperwrap { background:#e9eaec; border-radius:6px; padding:14px; overflow:auto; max-height:70vh; text-align:center; }
		.amq-lbl-paper { background:#fff; box-shadow:0 1px 6px rgba(0,0,0,.25); box-sizing:border-box; padding:4mm; margin:0 auto; text-align:left; overflow:hidden; transition:opacity .15s; }
		.amq-lbl-paper.is-loading { opacity:.55; }
		.amq-lbl-actions { display:flex; gap:8px; align-items:center; margin-top:12px; flex-wrap:wrap; }
		.amq-lbl-size { color:#646970; font-size:12px; margin:8px 0 0; text-align:center; }
	</style>
	<div class="wrap amq-lbl">
		<h1>ใบปะหน้าพัสดุ</h1>
		<?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
		<p class="description">ตั้งว่าใบปะหน้าแสดงอะไร ขนาดกระดาษเท่าไร ตัวอย่างทางขวาเปลี่ยนทันทีที่แก้ แต่ต้องกด "บันทึก" ถึงจะใช้กับการพิมพ์จริง ใบปะหน้าไม่แสดงราคา ส่วนลด หรือยอดชำระเสมอ</p>

		<form method="post" id="amq-lbl-form">
			<?php wp_nonce_field( 'amouriq_label_save' ); ?>
			<input type="hidden" name="amouriq_label_save" value="1">
			<div class="amq-lbl-grid">
				<div class="amq-lbl-main">

					<div class="amq-lbl-card">
						<h2>1. ส่วนที่แสดงบนใบปะหน้า</h2>
						<p class="description">เปิดเฉพาะที่ต้องการ ผู้รับแสดงเสมอ ส่วนที่เปิดแต่ออเดอร์นั้นไม่มีข้อมูลจะไม่พิมพ์เป็นช่องว่าง</p>
						<?php
						amouriq_label_switch( 'show_sender', $o['show_sender'], 'ผู้ส่ง', 'ชื่อ ที่อยู่ เบอร์โทรของร้าน' );
						amouriq_label_switch( 'show_logo', $o['show_logo'], 'โลโก้ร้าน', 'อยู่เหนือชื่อผู้ส่ง (ต้องเปิด "ผู้ส่ง" ด้วย)' );
						amouriq_label_switch( 'show_to_phone', $o['show_to_phone'], 'เบอร์โทรผู้รับ', 'ให้คนส่งโทรหาลูกค้าได้' );
						amouriq_label_switch( 'show_order_number', $o['show_order_number'], 'เลขที่ออเดอร์' );
						amouriq_label_switch( 'show_invoice_number', $o['show_invoice_number'], 'เลขที่ใบแจ้งหนี้', 'อ่านจากเลขที่ปลั๊กอิน Print Invoices เก็บไว้' );
						amouriq_label_switch( 'show_order_date', $o['show_order_date'], 'วันที่สั่งซื้อ' );
						amouriq_label_switch( 'show_items', $o['show_items'], 'ตารางสินค้า', 'ลำดับ ชื่อสินค้า จำนวน (ไม่มีราคา)' );
						amouriq_label_switch( 'show_option', $o['show_option'], 'คอลัมน์ตัวเลือกในตาราง', 'ขนาด / กลิ่น ของสินค้า (ใช้เมื่อเปิดตารางสินค้า)' );
						amouriq_label_switch( 'show_customer_note', $o['show_customer_note'], 'หมายเหตุจากลูกค้า', 'ข้อความที่ลูกค้าเขียนตอนสั่งซื้อ' );
						?>
					</div>

					<div class="amq-lbl-card">
						<h2>2. ข้อมูลผู้ส่งและโลโก้</h2>
						<p class="description">เว้นว่าง = ใช้ค่าจากปลั๊กอินพิมพ์เอกสารเดิม (กรอกที่นี่เพื่อไม่ต้องพึ่งปลั๊กอินนั้น)</p>
						<div class="amq-lbl-field"><label for="lb_sname">ชื่อผู้ส่ง / ร้าน</label><div><input type="text" id="lb_sname" name="sender_name" value="<?php echo esc_attr( $o['sender_name'] ); ?>"></div></div>
						<div class="amq-lbl-field"><label for="lb_saddr">ที่อยู่ผู้ส่ง</label><div><textarea rows="3" id="lb_saddr" name="sender_address"><?php echo esc_textarea( $o['sender_address'] ); ?></textarea><p class="description">หนึ่งบรรทัดต่อหนึ่งแถว</p></div></div>
						<div class="amq-lbl-field"><label for="lb_sphone">เบอร์โทรผู้ส่ง</label><div><input type="text" id="lb_sphone" name="sender_phone" value="<?php echo esc_attr( $o['sender_phone'] ); ?>"></div></div>
						<div class="amq-lbl-field"><label for="lb_logo">ลิงก์รูปโลโก้</label><div><input type="url" id="lb_logo" name="logo_url" value="<?php echo esc_attr( $o['logo_url'] ); ?>" placeholder="https://…"><p class="description">เว้นว่าง = ใช้โลโก้ของปลั๊กอินพิมพ์เอกสาร</p></div></div>
						<div class="amq-lbl-field"><label for="lb_logow">ความกว้างโลโก้สูงสุด</label><div><input type="number" id="lb_logow" name="logo_width" min="40" max="400" value="<?php echo esc_attr( $o['logo_width'] ); ?>" style="width:80px"> px</div></div>
					</div>

					<div class="amq-lbl-card">
						<h2>3. กระดาษและตัวอักษร</h2>
						<div class="amq-lbl-field"><label>ขนาดกระดาษ</label><div>
							กว้าง <input type="number" name="page_width" id="lb_pw" min="50" max="400" value="<?php echo esc_attr( $o['page_width'] ); ?>" style="width:80px"> ×
							สูง <input type="number" name="page_height" id="lb_ph" min="50" max="600" value="<?php echo esc_attr( $o['page_height'] ); ?>" style="width:80px"> มม.
							<div class="amq-lbl-presets">
								<span class="description" style="align-self:center">เลือกเร็ว:</span>
								<button type="button" class="button button-small amq-lbl-preset" data-w="100" data-h="150">สติกเกอร์ 100×150</button>
								<button type="button" class="button button-small amq-lbl-preset" data-w="100" data-h="100">100×100</button>
								<button type="button" class="button button-small amq-lbl-preset" data-w="105" data-h="148">A6</button>
								<button type="button" class="button button-small amq-lbl-preset" data-w="148" data-h="210">A5</button>
							</div>
						</div></div>
						<div class="amq-lbl-field"><label for="lb_font">ขนาดตัวอักษร</label><div><input type="number" id="lb_font" name="font_size" min="10" max="24" value="<?php echo esc_attr( $o['font_size'] ); ?>" style="width:80px"> px <span class="description">(10 ถึง 24; ในไฟล์ PDF ลดให้ไม่เกิน 12 เพื่อให้พอดีกระดาษเล็ก)</span></div></div>
					</div>

					<div class="amq-lbl-card">
						<h2>4. ข้อความท้ายใบ</h2>
						<div class="amq-lbl-field"><label for="lb_note">ข้อความ</label><div><textarea rows="2" id="lb_note" name="note"><?php echo esc_textarea( $o['note'] ); ?></textarea><p class="description">เช่น "สินค้าแตกหักง่าย" เว้นว่าง = ไม่แสดง</p></div></div>
					</div>

				</div>
				<aside class="amq-lbl-side">
					<div class="amq-lbl-card">
						<h2>ตัวอย่าง <span class="description" id="amq-lbl-status">(อัปเดตสดตามที่แก้)</span></h2>
						<div class="amq-lbl-paperwrap">
							<div class="amq-lbl-paper" id="amq-lbl-paper" style="width:<?php echo esc_attr( $o['page_width'] ); ?>mm;min-height:<?php echo esc_attr( $o['page_height'] ); ?>mm">
								<?php echo amouriq_label_html( amouriq_label_sample( $o ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside amouriq_label_html(). ?>
							</div>
						</div>
						<p class="amq-lbl-size" id="amq-lbl-size">กระดาษ <?php echo esc_html( $o['page_width'] . ' × ' . $o['page_height'] ); ?> มม. (ขนาดจริงบนหน้าจอโดยประมาณ)</p>
						<div class="amq-lbl-actions">
							<button type="submit" class="button button-primary button-large">บันทึก</button>
							<button type="submit" class="button" name="amouriq_label_reset" value="1" onclick="return confirm('คืนค่าเริ่มต้นทั้งหมด ใช่ไหม?');">คืนค่าเริ่มต้น</button>
						</div>
					</div>
				</aside>
			</div>
		</form>
	</div>
	<script>
	(function () {
		var form = document.getElementById('amq-lbl-form');
		var paper = document.getElementById('amq-lbl-paper');
		var status = document.getElementById('amq-lbl-status');
		var sizeText = document.getElementById('amq-lbl-size');
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'amouriq_label_preview' ) ); ?>;
		var timer = null, token = 0;

		function redraw() {
			var mine = ++token;
			var data = new FormData(form);
			data.set('action', 'amouriq_label_preview');
			data.set('preview_nonce', nonce);
			data.delete('amouriq_label_save');
			paper.classList.add('is-loading');
			status.textContent = '(กำลังอัปเดต…)';
			fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (mine !== token || !res.success) { return; }
					paper.innerHTML = res.data.html;
					paper.style.width = res.data.w + 'mm';
					paper.style.minHeight = res.data.h + 'mm';
					sizeText.textContent = 'กระดาษ ' + res.data.w + ' × ' + res.data.h + ' มม. (ขนาดจริงบนหน้าจอโดยประมาณ)';
					status.textContent = '(อัปเดตสดตามที่แก้)';
				})
				.catch(function () { status.textContent = '(อัปเดตตัวอย่างไม่สำเร็จ ลองกดบันทึกแล้วรีเฟรช)'; })
				.then(function () { if (mine === token) { paper.classList.remove('is-loading'); } });
		}
		function later() { clearTimeout(timer); timer = setTimeout(redraw, 300); }

		form.addEventListener('input', later);
		form.addEventListener('change', later);
		form.querySelectorAll('.amq-lbl-preset').forEach(function (btn) {
			btn.addEventListener('click', function () {
				document.getElementById('lb_pw').value = btn.getAttribute('data-w');
				document.getElementById('lb_ph').value = btn.getAttribute('data-h');
				later();
			});
		});
	})();
	</script>
	<?php
}
