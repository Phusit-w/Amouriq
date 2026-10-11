<?php
/**
 * Plugin Name: AMOURIQ: coupon conditions
 * Description: Adds the same Conditions the Deals use (who the customer is, when it is,
 *              what is in the cart) to ordinary WooCommerce coupons. A panel on the coupon
 *              edit screen ("เงื่อนไขเพิ่มเติม" under Usage restriction) holds up to five
 *              rows; when a customer tries the coupon and a row is not met, WooCommerce
 *              refuses it with a Thai message that says which condition. A coupon with no
 *              rows behaves exactly as before. The "ลูกค้าใหม่เท่านั้น" box is a separate
 *              feature (amouriq-coupon-first-order.php) and stays as it is.
 *
 *              Needs the Deal engine files (amouriq-bogo/*, amouriq-bogo-deals.php and
 *              amouriq-bogo-admin.php) uploaded with it: the evaluator, the cart context
 *              and the row parser live there.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/amouriq-bogo/pricing-core.php';

add_action( 'woocommerce_coupon_options_usage_restriction', 'amouriq_coupon_conditions_panel', 20 );

function amouriq_coupon_conditions_panel( $coupon_id ) {
	if ( ! function_exists( 'amouriq_bogo_conditions_for_form' ) ) {
		return;
	}
	$catalog = amouriq_cond_catalog();
	$set     = amouriq_bogo_conditions_for_form( get_post_meta( $coupon_id, 'amouriq_conditions', true ) );
	$rows    = array_values( $set['rows'] );
	?>
	<div class="options_group amq-coupon-conditions">
		<style>
		.amq-coupon-conditions h4 { margin:12px 12px 4px; }
		.amq-coupon-conditions p.amq-note { padding:0 12px; }
		.amq-coupon-conditions p.form-field select, .amq-coupon-conditions p.form-field input.amq-cond-value { float:none; width:auto; display:inline-block; margin:0 4px 0 0; vertical-align:middle; }
		.amq-coupon-conditions p.form-field input.amq-cond-value { min-width:200px; }
		.amq-coupon-conditions p.form-field .amq-cond-hint { display:block; float:none; margin:4px 0 0; padding:0; }
		.amq-coupon-conditions [hidden] { display:none !important; }
		</style>
		<h4>เงื่อนไขเพิ่มเติม: ใครและเมื่อไหร่</h4>
		<p class="description amq-note">ไม่ใส่เลย = ไม่จำกัดเพิ่ม ข้อมูลที่ยังไม่ทราบ (เช่น ยังไม่กรอกอีเมล) ถือว่ายังไม่ตรงเงื่อนไข ลูกค้าจะเห็นข้อความบอกว่าต้องทำอะไร</p>
		<p class="form-field">
			<label for="cond_match">ต้องตรง</label>
			<select name="cond_match" id="cond_match">
				<option value="all" <?php selected( $set['match'], 'all' ); ?>>ครบทุกข้อ</option>
				<option value="any" <?php selected( $set['match'], 'any' ); ?>>ข้อใดข้อหนึ่ง</option>
			</select>
		</p>
		<?php for ( $n = 1; $n <= AMOURIQ_BOGO_CONDITION_ROWS; $n++ ) :
			$row = isset( $rows[ $n - 1 ] ) ? $rows[ $n - 1 ] : array( 'type' => '', 'op' => '', 'value' => '' );
		?>
			<p class="form-field amq-cond-row">
				<label>เงื่อนไขข้อ <?php echo esc_html( $n ); ?></label>
				<select name="cond_type_<?php echo esc_attr( $n ); ?>" class="amq-cond-type">
					<option value="">— ไม่ใช้ —</option>
					<?php foreach ( $catalog as $key => $meta ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $row['type'], $key ); ?>><?php echo esc_html( $meta['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="cond_op_<?php echo esc_attr( $n ); ?>" class="amq-cond-op" data-current="<?php echo esc_attr( $row['op'] ); ?>"></select>
				<input type="text" name="cond_value_<?php echo esc_attr( $n ); ?>" class="amq-cond-value" value="<?php echo esc_attr( $row['type'] ? amouriq_cond_format_value( $row ) : '' ); ?>">
				<span class="description amq-cond-hint"></span>
			</p>
		<?php endfor; ?>
		<script type="application/json" id="amq-coupon-cond-catalog"><?php echo wp_json_encode( $catalog ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
		<script>
		(function () {
			var catalogEl = document.getElementById('amq-coupon-cond-catalog');
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
			document.querySelectorAll('.amq-coupon-conditions .amq-cond-row').forEach(function (row) {
				row.querySelector('.amq-cond-type').addEventListener('change', function () {
					row.querySelector('.amq-cond-op').setAttribute('data-current', '');
					sync(row);
				});
				sync(row);
			});
		})();
		</script>
	</div>
	<?php
}

add_action( 'woocommerce_coupon_options_save', 'amouriq_coupon_conditions_save', 20 );

/** Save the rows. A mistake in a row is reported on the coupon page and the earlier conditions are kept. */
function amouriq_coupon_conditions_save( $post_id ) {
	if ( ! function_exists( 'amouriq_bogo_build_conditions' ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verifies the coupon save nonce before this action.
	$set = amouriq_bogo_build_conditions( $_POST );
	if ( is_string( $set ) ) {
		if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
			WC_Admin_Meta_Boxes::add_error( 'เงื่อนไขเพิ่มเติมของคูปองไม่ได้ถูกบันทึก — ' . $set );
		}
		return;
	}
	if ( $set ) {
		update_post_meta( $post_id, 'amouriq_conditions', $set );
	} else {
		delete_post_meta( $post_id, 'amouriq_conditions' );
	}
}

add_filter( 'woocommerce_coupon_is_valid', 'amouriq_coupon_conditions_check', 20, 2 );

function amouriq_coupon_conditions_check( $valid, $coupon ) {
	if ( ! $valid || ! function_exists( 'WC' ) || ! WC()->cart || ! function_exists( 'amouriq_bogo_condition_context' ) ) {
		return $valid;
	}
	$set = $coupon->get_meta( 'amouriq_conditions' );
	if ( empty( $set['rows'] ) ) {
		return $valid;
	}

	$ctx = array( 'now' => current_time( 'Y-m-d H:i' ) ) + amouriq_bogo_condition_context( WC()->cart );
	$why = amouriq_cond_explain( $set, $ctx );
	if ( 'match' !== $why['result'] ) {
		// WooCommerce shows the message of the exception to the customer.
		throw new Exception( $why['message'] );
	}
	return $valid;
}
