<?php
/**
 * Plugin Name: AMOURIQ: BOGO cart choice and gifts
 * Description: Two Deals where the customer picks items in the cart:
 *              - buy-A-choose-B (product_pair with get_product_ids): the pick is an
 *                ordinary cart line that gets the Deal's discount;
 *              - spend-threshold gift (spend_gift): the pick is a free (0 baht) line
 *                with its quantity locked, tagged with the Deal id (cart item data
 *                `amq_gift`). It is a real line, so stock is decremented when the
 *                order is placed and it appears on the order.
 *              This file lists the choices, adds the pick, makes gift lines free and
 *              drops a gift (with a notice) when its Deal no longer entitles it or it is
 *              out of stock. The discount maths lives in amouriq-bogo/pricing-core.php.
 *              Not choosing never blocks checkout: only a notice shows.
 */

defined( 'ABSPATH' ) || exit;

/** Deals where the customer picks in the cart. */
function amouriq_bogo_is_choice_deal( $deal ) {
	return amouriq_bogo_core_is_pair_choice( $deal ) || ( isset( $deal['type'] ) && 'spend_gift' === $deal['type'] );
}

function amouriq_bogo_is_gift_deal( $deal ) {
	return isset( $deal['type'] ) && 'spend_gift' === $deal['type'];
}

/** The cart's price result for one Deal (discount, choices_left, allowed). */
function amouriq_bogo_deal_result( $deal, $cart ) {
	return amouriq_bogo_price(
		$deal,
		static function () use ( $cart ) {
			return amouriq_bogo_cart_lines( $cart );
		},
		amouriq_bogo_price_context( array( $deal ), $cart )
	);
}

/**
 * The choice Deals that are live for this cart and still have choices left, each
 * as array( 'deal' => deal, 'left' => int ).
 */
function amouriq_bogo_open_choices() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}

	$open = array();
	foreach ( amouriq_bogo_deals() as $deal ) {
		if ( ! amouriq_bogo_is_choice_deal( $deal ) ) {
			continue;
		}
		$result = amouriq_bogo_deal_result( $deal, WC()->cart );
		if ( $result['choices_left'] > 0 ) {
			$open[] = array( 'deal' => $deal, 'left' => $result['choices_left'] );
		}
	}
	return $open;
}

/**
 * The items a customer can pick for a Deal: the ticked products and variations
 * (a ticked variable product stands for all its variations), minus Sale Price
 * items when a discount Deal skips them. Returns WC_Product objects by ID.
 */
function amouriq_bogo_choice_items( $deal ) {
	$ids   = amouriq_bogo_is_gift_deal( $deal ) ? ( isset( $deal['gift_ids'] ) ? $deal['gift_ids'] : array() ) : $deal['get_product_ids'];
	$items = array();
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			continue;
		}
		$products = $product->is_type( 'variable' ) ? array_map( 'wc_get_product', $product->get_children() ) : array( $product );
		foreach ( $products as $item ) {
			if ( ! $item || ( ! amouriq_bogo_is_gift_deal( $deal ) && ! empty( $deal['exclude_sale'] ) && $item->is_on_sale() ) ) {
				continue;
			}
			$items[ $item->get_id() ] = $item;
		}
	}
	return $items;
}

/** IDs of the items already in the cart as a gift of this Deal. */
function amouriq_bogo_gifts_in_cart( $deal ) {
	$ids = array();
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( isset( $cart_item['amq_gift'] ) && $cart_item['amq_gift'] === $deal['id'] ) {
			$ids[] = (int) ( $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'] );
		}
	}
	return $ids;
}

function amouriq_bogo_choice_pick_url( $deal, $item_id ) {
	return add_query_arg(
		array(
			'amq_pick' => $deal['id'],
			'amq_item' => $item_id,
			'_wpnonce' => wp_create_nonce( 'amq_pick' ),
		),
		wc_get_cart_url()
	);
}

/** What the customer gets, in words, for the box and the notice. */
function amouriq_bogo_choice_offer( $deal ) {
	if ( amouriq_bogo_is_gift_deal( $deal ) ) {
		return 'ของแถมฟรี';
	}
	return 'percent' === ( isset( $deal['get_mode'] ) ? $deal['get_mode'] : 'percent' )
		? 'ลด ' . $deal['get_value'] . '%'
		: 'ลด ฿' . $deal['get_value'];
}

add_action( 'woocommerce_before_cart_table', 'amouriq_bogo_render_choices' );

function amouriq_bogo_render_choices() {
	foreach ( amouriq_bogo_open_choices() as $open ) {
		$deal   = $open['deal'];
		$label  = ! empty( $deal['label'] ) ? $deal['label'] : 'ดีลพิเศษ';
		$is_gift = amouriq_bogo_is_gift_deal( $deal );
		$taken  = $is_gift ? amouriq_bogo_gifts_in_cart( $deal ) : array();
		?>
		<div class="amq-choice" style="border:2px solid #b8860b;background:#fff8e6;border-radius:6px;padding:14px 16px;margin:0 0 20px">
			<strong><?php echo esc_html( $label ); ?></strong>:
			คุณได้สิทธิ์เลือก<?php echo $is_gift ? 'ของแถม' : 'สินค้า'; ?>อีก <strong><?php echo esc_html( $open['left'] ); ?></strong> ชิ้น <?php echo $is_gift ? '(ฟรี)' : esc_html( amouriq_bogo_choice_offer( $deal ) ); ?>
			(เลือกหรือไม่เลือกก็ได้ ไม่ติดขัดการชำระเงิน)
			<ul style="list-style:none;margin:10px 0 0;padding:0">
			<?php foreach ( amouriq_bogo_choice_items( $deal ) as $item_id => $item ) :
				$in_stock = $item->is_in_stock() && $item->is_purchasable();
				$have     = in_array( $item_id, $taken, true );
			?>
				<li style="display:flex;justify-content:space-between;gap:12px;align-items:center;padding:6px 0;border-top:1px solid #ecd9a8<?php echo ( $in_stock && ! $have ) ? '' : ';opacity:.6'; ?>">
					<span><?php echo esc_html( $item->get_name() ); ?><?php echo $is_gift ? '' : ' — ' . wp_kses_post( wc_price( wc_get_price_to_display( $item ) ) ); ?></span>
					<?php if ( $have ) : ?>
						<span class="button disabled" aria-disabled="true">เลือกแล้ว</span>
					<?php elseif ( $in_stock ) : ?>
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
				'%s: คุณยังไม่ได้เลือก%s %d ชิ้น (%s) <a href="%s">เลือกในตะกร้า</a> หรือชำระเงินต่อได้เลย',
				esc_html( ! empty( $deal['label'] ) ? $deal['label'] : 'ดีลพิเศษ' ),
				amouriq_bogo_is_gift_deal( $deal ) ? 'ของแถม' : 'สินค้าที่ได้ส่วนลด',
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
 * Add the picked item as a normal cart line (a free line for a gift), after
 * checking again that the Deal is live, the item is one of its choices, it is in
 * stock, a choice is left and (for a gift) it is not already taken.
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

	if ( ! $item || ( amouriq_bogo_is_gift_deal( $chosen ) && in_array( $item_id, amouriq_bogo_gifts_in_cart( $chosen ), true ) ) ) {
		wc_add_notice( 'ตอนนี้เลือกสินค้านี้ไม่ได้แล้ว (เงื่อนไขดีลไม่ครบ ใช้สิทธิ์เลือกครบแล้ว หรือเลือกไปแล้ว)', 'error' );
	} elseif ( ! $item->is_in_stock() || ! $item->is_purchasable() ) {
		wc_add_notice( $item->get_name() . ' หมดสต็อก เลือกไม่ได้', 'error' );
	} else {
		$parent     = $item->is_type( 'variation' ) ? $item->get_parent_id() : $item->get_id();
		$variation  = $item->is_type( 'variation' ) ? $item->get_id() : 0;
		$attributes = $item->is_type( 'variation' ) ? $item->get_variation_attributes() : array();
		$data       = amouriq_bogo_is_gift_deal( $chosen ) ? array( 'amq_gift' => $chosen['id'] ) : array();
		if ( WC()->cart->add_to_cart( $parent, 1, $variation, $attributes, $data ) ) {
			wc_add_notice( amouriq_bogo_is_gift_deal( $chosen ) ? 'เพิ่มของแถม ' . $item->get_name() . ' แล้ว' : 'เลือก ' . $item->get_name() . ' แล้ว ได้ส่วนลดตามดีล', 'success' );
		}
	}
	wp_safe_redirect( $back );
	exit;
}

// --- gift lines in the cart ---------------------------------------------

add_action( 'woocommerce_before_calculate_totals', 'amouriq_bogo_settle_gifts', 20 );

/**
 * Before totals: drop gifts the Deal no longer entitles (below the minimum, Deal
 * off or outside its dates, more gifts than allowed) or that are out of stock,
 * with a notice; lock the rest to quantity 1 and price 0.
 */
function amouriq_bogo_settle_gifts( $cart ) {
	static $busy = false;
	if ( $busy || ( is_admin() && ! defined( 'DOING_AJAX' ) ) ) {
		return;
	}
	$has_gift = false;
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( ! empty( $cart_item['amq_gift'] ) ) {
			$has_gift = true;
			break;
		}
	}
	if ( ! $has_gift ) {
		return;
	}
	$busy = true;

	// Entitlement per gift Deal, from the paid items.
	$allowed = array();
	$deals   = array();
	foreach ( amouriq_bogo_deals() as $deal ) {
		if ( amouriq_bogo_is_gift_deal( $deal ) ) {
			$allowed[ $deal['id'] ] = amouriq_bogo_deal_result( $deal, $cart )['allowed'];
			$deals[ $deal['id'] ]   = $deal;
		}
	}

	$lines = amouriq_bogo_cart_lines( $cart );
	$drop  = array();
	foreach ( amouriq_bogo_core_gifts_to_remove( $lines, $allowed ) as $index ) {
		$drop[ $lines[ $index ]['cart_key'] ] = 'entitlement';
	}
	foreach ( $lines as $line ) {
		if ( empty( $line['is_gift'] ) || isset( $drop[ $line['cart_key'] ] ) ) {
			continue;
		}
		$item = wc_get_product( $line['variation_id'] ? $line['variation_id'] : $line['product_id'] );
		if ( ! $item || ! $item->is_in_stock() ) {
			$drop[ $line['cart_key'] ] = 'stock';
		}
	}

	foreach ( $drop as $key => $why ) {
		$cart_item = $cart->get_cart_item( $key );
		$name      = $cart_item ? $cart_item['data']->get_name() : 'ของแถม';
		$deal      = $cart_item && isset( $deals[ $cart_item['amq_gift'] ] ) ? $deals[ $cart_item['amq_gift'] ] : null;
		$cart->remove_cart_item( $key );
		if ( 'stock' === $why ) {
			wc_add_notice( 'ของแถม ' . $name . ' หมดสต็อก จึงนำออกจากตะกร้า', 'notice' );
		} elseif ( $deal ) {
			wc_add_notice( 'ของแถม ' . $name . ' ถูกนำออก เพราะยอดซื้อไม่ถึง ฿' . $deal['min_spend'] . ' หรือเลือกเกินสิทธิ์', 'notice' );
		} else {
			wc_add_notice( 'ของแถม ' . $name . ' ถูกนำออก เพราะดีลนี้สิ้นสุดแล้ว', 'notice' );
		}
	}

	foreach ( $cart->get_cart() as $key => $cart_item ) {
		if ( ! empty( $cart_item['amq_gift'] ) ) {
			$cart->set_quantity( $key, 1, false );
			$cart_item['data']->set_price( 0 );
		}
	}
	$busy = false;
}

// A gift's quantity is shown, not editable, and the line says it is a gift.
add_filter(
	'woocommerce_cart_item_quantity',
	static function ( $quantity, $cart_item_key, $cart_item ) {
		return ! empty( $cart_item['amq_gift'] ) ? '1' : $quantity;
	},
	10,
	3
);

add_filter(
	'woocommerce_get_item_data',
	static function ( $data, $cart_item ) {
		if ( ! empty( $cart_item['amq_gift'] ) ) {
			$data[] = array( 'key' => 'ของแถม', 'value' => 'ฟรี' );
		}
		return $data;
	},
	10,
	2
);
