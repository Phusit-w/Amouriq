<?php
/**
 * Plugin Name: AMOURIQ: BOGO cart choice
 * Description: For a buy-A-choose-B Deal (product_pair with get_product_ids), lists
 *              the items the customer may pick in the cart once the Deal is met.
 *              A pick is an ordinary cart line, so stock, removal and the discount
 *              work as for any item; the discount itself comes from
 *              amouriq-bogo-deals.php. Not choosing never blocks checkout: the
 *              cart and the checkout only show a notice.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The buy-A-choose-B Deals that are live for this cart and still have choices
 * left, each as array( 'deal' => deal, 'left' => int ).
 */
function amouriq_bogo_open_choices() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}
	$cart = WC()->cart;

	$open = array();
	foreach ( amouriq_bogo_deals() as $deal ) {
		if ( ! amouriq_bogo_core_is_pair_choice( $deal ) ) {
			continue;
		}
		$result = amouriq_bogo_price(
			$deal,
			static function () use ( $cart ) {
				return amouriq_bogo_cart_lines( $cart );
			},
			amouriq_bogo_price_context( array( $deal ), $cart )
		);
		if ( $result['choices_left'] > 0 ) {
			$open[] = array( 'deal' => $deal, 'left' => $result['choices_left'] );
		}
	}
	return $open;
}

/**
 * The items a customer can pick for a Deal: the ticked products and variations
 * (a ticked variable product stands for all its variations), minus Sale Price
 * items when the Deal skips them. Returns WC_Product variations/simple products.
 */
function amouriq_bogo_choice_items( $deal ) {
	$items = array();
	foreach ( $deal['get_product_ids'] as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			continue;
		}
		$products = $product->is_type( 'variable' ) ? array_map( 'wc_get_product', $product->get_children() ) : array( $product );
		foreach ( $products as $item ) {
			if ( ! $item || ( ! empty( $deal['exclude_sale'] ) && $item->is_on_sale() ) ) {
				continue;
			}
			$items[ $item->get_id() ] = $item;
		}
	}
	return $items;
}

function amouriq_bogo_choice_pick_url( $deal, $item_id ) {
	return add_query_arg(
		array(
			'amq_pick'  => $deal['id'],
			'amq_item'  => $item_id,
			'_wpnonce'  => wp_create_nonce( 'amq_pick' ),
		),
		wc_get_cart_url()
	);
}

/** What the discount is, in words, for the box and the notice. */
function amouriq_bogo_choice_offer( $deal ) {
	return 'percent' === ( isset( $deal['get_mode'] ) ? $deal['get_mode'] : 'percent' )
		? 'ลด ' . $deal['get_value'] . '%'
		: 'ลด ฿' . $deal['get_value'];
}

add_action( 'woocommerce_before_cart_table', 'amouriq_bogo_render_choices' );

function amouriq_bogo_render_choices() {
	foreach ( amouriq_bogo_open_choices() as $open ) {
		$deal  = $open['deal'];
		$label = ! empty( $deal['label'] ) ? $deal['label'] : 'ดีลพิเศษ';
		?>
		<div class="amq-choice" style="border:2px solid #b8860b;background:#fff8e6;border-radius:6px;padding:14px 16px;margin:0 0 20px">
			<strong><?php echo esc_html( $label ); ?></strong>:
			คุณได้สิทธิ์เลือกสินค้าอีก <strong><?php echo esc_html( $open['left'] ); ?></strong> ชิ้น <?php echo esc_html( amouriq_bogo_choice_offer( $deal ) ); ?>
			(เลือกหรือไม่เลือกก็ได้ ไม่ติดขัดการชำระเงิน)
			<ul style="list-style:none;margin:10px 0 0;padding:0">
			<?php foreach ( amouriq_bogo_choice_items( $deal ) as $item_id => $item ) :
				$in_stock = $item->is_in_stock() && $item->is_purchasable();
			?>
				<li style="display:flex;justify-content:space-between;gap:12px;align-items:center;padding:6px 0;border-top:1px solid #ecd9a8<?php echo $in_stock ? '' : ';opacity:.6'; ?>">
					<span><?php echo esc_html( $item->get_name() ); ?> — <?php echo wp_kses_post( wc_price( wc_get_price_to_display( $item ) ) ); ?></span>
					<?php if ( $in_stock ) : ?>
						<a class="button amq-pick" href="<?php echo esc_url( amouriq_bogo_choice_pick_url( $deal, $item_id ) ); ?>">เลือกชิ้นนี้</a>
					<?php else : ?>
						<span class="button disabled" aria-disabled="true" style="cursor:not-allowed">สินค้าหมด</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}

add_action( 'woocommerce_before_checkout_form', 'amouriq_bogo_checkout_choice_notice', 5 );

function amouriq_bogo_checkout_choice_notice() {
	foreach ( amouriq_bogo_open_choices() as $open ) {
		$deal = $open['deal'];
		wc_print_notice(
			sprintf(
				'%s: คุณยังไม่ได้เลือกสินค้า %d ชิ้นที่ได้ส่วนลด (%s) <a href="%s">เลือกในตะกร้า</a> หรือชำระเงินต่อได้เลย',
				esc_html( ! empty( $deal['label'] ) ? $deal['label'] : 'ดีลพิเศษ' ),
				(int) $open['left'],
				esc_html( amouriq_bogo_choice_offer( $deal ) ),
				esc_url( wc_get_cart_url() )
			),
			'notice'
		);
	}
}

add_action( 'template_redirect', 'amouriq_bogo_handle_pick' );

/**
 * Add the picked item as a normal cart line, after checking again that the Deal is
 * live, the item is one of its choices, it is in stock and a choice is left.
 */
function amouriq_bogo_handle_pick() {
	if ( empty( $_GET['amq_pick'] ) || empty( $_GET['amq_item'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$back = wc_get_cart_url();
	if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'amq_pick' ) ) {
		wc_add_notice( 'ลิงก์หมดอายุ กรุณาเลือกใหม่อีกครั้ง', 'error' );
		wp_safe_redirect( $back );
		exit;
	}

	$deal_id = sanitize_text_field( wp_unslash( $_GET['amq_pick'] ) );
	$item_id = absint( $_GET['amq_item'] );

	$chosen = null;
	foreach ( amouriq_bogo_open_choices() as $open ) {
		if ( isset( $open['deal']['id'] ) && $open['deal']['id'] === $deal_id ) {
			$chosen = $open['deal'];
		}
	}
	$items = $chosen ? amouriq_bogo_choice_items( $chosen ) : array();
	$item  = isset( $items[ $item_id ] ) ? $items[ $item_id ] : null;

	if ( ! $item ) {
		wc_add_notice( 'ตอนนี้เลือกสินค้านี้ไม่ได้แล้ว (เงื่อนไขดีลไม่ครบ หรือใช้สิทธิ์เลือกครบแล้ว)', 'error' );
	} elseif ( ! $item->is_in_stock() || ! $item->is_purchasable() ) {
		wc_add_notice( $item->get_name() . ' หมดสต็อก เลือกไม่ได้', 'error' );
	} else {
		$parent     = $item->is_type( 'variation' ) ? $item->get_parent_id() : $item->get_id();
		$variation  = $item->is_type( 'variation' ) ? $item->get_id() : 0;
		$attributes = $item->is_type( 'variation' ) ? $item->get_variation_attributes() : array();
		if ( WC()->cart->add_to_cart( $parent, 1, $variation, $attributes ) ) {
			wc_add_notice( 'เลือก ' . $item->get_name() . ' แล้ว ได้ส่วนลดตามดีล', 'success' );
		}
	}
	wp_safe_redirect( $back );
	exit;
}
