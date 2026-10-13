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

/**
 * The page a pick link comes from and returns to: the checkout when the box is shown there,
 * the cart otherwise.
 */
function amouriq_bogo_choice_page_url() {
	return function_exists( 'is_checkout' ) && is_checkout() ? wc_get_checkout_url() : wc_get_cart_url();
}

function amouriq_bogo_choice_pick_url( $deal, $item_id ) {
	return add_query_arg(
		array(
			'amq_pick' => $deal['id'],
			'amq_item' => $item_id,
			'_wpnonce' => wp_create_nonce( 'amq_pick_' . $deal['id'] . '_' . $item_id ),
		),
		amouriq_bogo_choice_page_url()
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

/** The Deal's choice items as the plain candidates the Gift choice builder takes (see amouriq_bogo_core_gift_choices()). */
function amouriq_bogo_choice_candidates( $deal ) {
	$candidates = array();
	foreach ( amouriq_bogo_choice_items( $deal ) as $item_id => $item ) {
		$is_variation = $item->is_type( 'variation' );
		$parent       = $is_variation ? wc_get_product( $item->get_parent_id() ) : $item;
		$image_id     = $item->get_image_id() ? $item->get_image_id() : ( $parent ? $parent->get_image_id() : 0 );
		$candidates[] = array(
			'id'            => $item_id,
			'parent_id'     => $parent ? $parent->get_id() : $item_id,
			'name'          => $parent ? $parent->get_name() : $item->get_name(),
			'option'        => $is_variation ? wc_get_formatted_variation( $item, true, false ) : '',
			'regular_price' => (float) wc_get_price_to_display( $item, array( 'price' => (float) $item->get_regular_price() ) ),
			'in_stock'      => $item->is_in_stock() && $item->is_purchasable(),
			'image'         => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '',
		);
	}
	return $candidates;
}

add_action( 'woocommerce_before_cart_table', 'amouriq_bogo_render_choices' );
// Mini-cart shoppers go straight to the checkout, so the same box is there too (in place of the old notice).
add_action( 'woocommerce_before_checkout_form', 'amouriq_bogo_render_choices', 5 );

function amouriq_bogo_render_choices() {
	foreach ( amouriq_bogo_open_choices() as $open ) {
		$deal    = $open['deal'];
		$label   = ! empty( $deal['label'] ) ? $deal['label'] : 'ดีลพิเศษ';
		$is_gift = amouriq_bogo_is_gift_deal( $deal );
		$groups  = amouriq_bogo_core_gift_choices( amouriq_bogo_choice_candidates( $deal ), $is_gift ? amouriq_bogo_gifts_in_cart( $deal ) : array() );
		?>
		<div class="amq-choice" style="border:2px solid #b8860b;background:#fff8e6;border-radius:6px;padding:14px 16px;margin:0 0 20px">
			<strong><?php echo esc_html( $label ); ?></strong>:
			คุณได้สิทธิ์เลือก<?php echo $is_gift ? 'ของแถม' : 'สินค้า'; ?>อีก <strong><?php echo esc_html( $open['left'] ); ?></strong> ชิ้น <?php echo $is_gift ? '(ฟรี)' : esc_html( amouriq_bogo_choice_offer( $deal ) ); ?>
			(เลือกหรือไม่เลือกก็ได้ ไม่ติดขัดการชำระเงิน)
			<ul style="list-style:none;margin:10px 0 0;padding:0">
			<?php foreach ( $groups as $group ) :
				// The option shown first: the first one still pickable, else the first.
				$shown = $group['options'][0];
				foreach ( $group['options'] as $option ) {
					if ( $option['available'] ) {
						$shown = $option;
						break;
					}
				}
				$multi = count( $group['options'] ) > 1;
			?>
				<li class="amq-choice-row" data-gift="<?php echo $is_gift ? '1' : '0'; ?>" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;padding:6px 0;border-top:1px solid #ecd9a8<?php echo $group['available'] ? '' : ';opacity:.6'; ?>">
					<span class="amq-choice-info" style="display:flex;align-items:center;gap:10px">
						<img class="amq-choice-img" src="<?php echo esc_url( $shown['image'] ); ?>" alt="" width="56" height="56" style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #ecd9a8;background:#fff<?php echo '' === $shown['image'] ? ';visibility:hidden' : ''; ?>">
						<span>
						<?php echo esc_html( $group['name'] ); ?>
						<?php if ( $multi ) : ?>
							<select class="amq-choice-option" aria-label="ตัวเลือก">
								<?php foreach ( $group['options'] as $option ) : ?>
									<option value="<?php echo esc_attr( $option['id'] ); ?>"
										data-href="<?php echo esc_attr( $option['available'] ? amouriq_bogo_choice_pick_url( $deal, $option['id'] ) : '' ); ?>"
										data-state="<?php echo esc_attr( $option['picked'] ? 'picked' : ( $option['available'] ? 'ok' : 'out' ) ); ?>"
										data-price="<?php echo esc_attr( wc_price( $option['regular_price'] ) ); ?>"
										data-image="<?php echo esc_attr( $option['image'] ); ?>"
										<?php selected( $option['id'], $shown['id'] ); ?>>
										<?php echo esc_html( $option['option'] . ( $option['available'] ? '' : ( $option['picked'] ? ' (เลือกแล้ว)' : ' (สินค้าหมด)' ) ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( '' !== $shown['option'] ) : ?>
							<small>(<?php echo esc_html( $shown['option'] ); ?>)</small>
						<?php endif; ?>
						&mdash;
						<span class="amq-choice-price">
						<?php if ( $is_gift ) : ?>
							<del><?php echo wp_kses_post( wc_price( $shown['regular_price'] ) ); ?></del> <strong>ฟรี</strong>
						<?php else : ?>
							<?php echo wp_kses_post( wc_price( $shown['regular_price'] ) ); ?>
						<?php endif; ?>
						</span>
						</span>
					</span>
					<span class="amq-choice-action">
					<?php if ( $shown['picked'] ) : ?>
						<span class="button disabled" aria-disabled="true">เลือกแล้ว</span>
					<?php elseif ( $shown['available'] ) : ?>
						<a class="button amq-pick" href="<?php echo esc_url( amouriq_bogo_choice_pick_url( $deal, $shown['id'] ) ); ?>">เลือกชิ้นนี้</a>
					<?php else : ?>
						<span class="button disabled" aria-disabled="true" style="cursor:not-allowed">สินค้าหมด</span>
					<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
			</ul>
		</div>
		<?php
		amouriq_bogo_choice_script();
	}
}

/** Once per page: changing the option in a row shows that option's price and the right button. */
function amouriq_bogo_choice_script() {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	?>
	<script>
	document.addEventListener('change', function (e) {
		if (!e.target.classList || !e.target.classList.contains('amq-choice-option')) { return; }
		var opt = e.target.options[e.target.selectedIndex], row = e.target.closest('.amq-choice-row');
		var gift = row.dataset.gift === '1';
		var img = row.querySelector('.amq-choice-img');
		if (img) { img.src = opt.dataset.image || ''; img.style.visibility = opt.dataset.image ? 'visible' : 'hidden'; }
		row.querySelector('.amq-choice-price').innerHTML = gift ? '<del>' + opt.dataset.price + '</del> <strong>ฟรี</strong>' : opt.dataset.price;
		var box = row.querySelector('.amq-choice-action'), state = opt.dataset.state;
		box.innerHTML = '';
		var el = document.createElement(state === 'ok' ? 'a' : 'span');
		el.className = 'button' + (state === 'ok' ? ' amq-pick' : ' disabled');
		if (state === 'ok') { el.href = opt.dataset.href; el.textContent = 'เลือกชิ้นนี้'; }
		else { el.setAttribute('aria-disabled', 'true'); el.textContent = state === 'picked' ? 'เลือกแล้ว' : 'สินค้าหมด'; }
		box.appendChild(el);
	});
	</script>
	<?php
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
	$back    = amouriq_bogo_choice_page_url();
	$deal_id = sanitize_text_field( wp_unslash( $_GET['amq_pick'] ) );
	$item_id = absint( $_GET['amq_item'] );

	// The link is good for this Deal and this item only.
	if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'amq_pick_' . $deal_id . '_' . $item_id ) ) {
		wc_add_notice( 'ลิงก์หมดอายุ กรุณาเลือกใหม่อีกครั้ง', 'error' );
		wp_safe_redirect( $back );
		exit;
	}

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
		if ( ! $item || ! amouriq_bogo_gift_in_stock( $item, $cart ) ) {
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

/**
 * Whether one unit is left for the gift after the paid lines of the same stock. Paid
 * lines are counted so a customer buying the last unit does not keep a gift that
 * WooCommerce would then refuse at checkout.
 */
function amouriq_bogo_gift_in_stock( $item, $cart ) {
	if ( ! $item->is_in_stock() ) {
		return false;
	}
	if ( ! $item->managing_stock() || $item->backorders_allowed() ) {
		return true;
	}
	$stock_id = $item->get_stock_managed_by_id();
	$paid     = 0;
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( ! empty( $cart_item['amq_gift'] ) ) {
			continue;
		}
		$other = $cart_item['data'];
		if ( $other && $other->get_stock_managed_by_id() === $stock_id ) {
			$paid += (int) $cart_item['quantity'];
		}
	}
	return (int) $item->get_stock_quantity() - $paid >= 1;
}

// The order line says it was a gift (the cart line is tagged, the order item gets a note).
add_action(
	'woocommerce_checkout_create_order_line_item',
	static function ( $order_item, $cart_item_key, $values ) {
		if ( ! empty( $values['amq_gift'] ) ) {
			$order_item->add_meta_data( 'ของแถม', 'ฟรี', true );
		}
	},
	10,
	3
);

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
