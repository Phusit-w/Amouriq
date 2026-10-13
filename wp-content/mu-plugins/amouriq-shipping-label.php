<?php
/**
 * Plugin Name: AMOURIQ: portrait shipping label
 * Description: Prints the shipping label (ใบปะหน้าพัสดุ) itself: a print button on the order
 *              screen and a bulk action in the order list that prints the chosen orders, one
 *              per page, at the owner's paper size. The layout is portrait, top to bottom:
 *              logo and sender, recipient, order number and an item table (number, product,
 *              option, quantity), with no prices. The "Print Invoices, Packing Slips, Labels"
 *              plugin (WebToffee) stays active for invoices, but its shipping label button and
 *              bulk action are hidden. Sender and logo come from this plugin's settings page,
 *              falling back to the print plugin's own. The layout is in
 *              amouriq-shipping-label/label-html.php.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/amouriq-shipping-label/label-html.php';
require_once __DIR__ . '/amouriq-shipping-label/settings.php';

// --- hide the print plugin's label buttons (its invoices and packing slips are untouched) ---

add_filter(
	'wt_pklist_alter_print_actions',
	static function ( $buttons ) {
		foreach ( $buttons as $key => $button ) {
			$actions = array( isset( $button['action'] ) ? $button['action'] : '' );
			if ( ! empty( $button['items'] ) && is_array( $button['items'] ) ) {
				$actions = array_merge( $actions, array_keys( $button['items'] ) );
			}
			if ( array_intersect( $actions, array( 'print_shippinglabel', 'download_shippinglabel' ) ) ) {
				unset( $buttons[ $key ] );
			}
		}
		return $buttons;
	},
	99
);

add_filter(
	'wt_print_bulk_actions',
	static function ( $actions ) {
		unset( $actions['print_shippinglabel'], $actions['download_shippinglabel'] );
		return $actions;
	},
	99
);

// --- our own print: one order from its screen, or many from the order list ---

/** The address the print view opens: nonce-protected, for logged-in shop managers. */
function amouriq_shipping_label_print_url( array $order_ids ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'    => 'amouriq_label_print',
				'order_ids' => implode( ',', array_map( 'absint', $order_ids ) ),
			),
			admin_url( 'admin-post.php' )
		),
		'amouriq_label_print'
	);
}

// The button on a single order's screen.
add_action(
	'woocommerce_admin_order_data_after_shipping_address',
	static function ( $order ) {
		printf(
			'<p><a class="button" href="%s" target="_blank" rel="noopener">พิมพ์ใบปะหน้าพัสดุ</a></p>',
			esc_url( amouriq_shipping_label_print_url( array( $order->get_id() ) ) )
		);
	}
);

// The bulk action in the order list (the HPOS screen and the legacy posts screen).
foreach ( array( 'woocommerce_page_wc-orders', 'edit-shop_order' ) as $amouriq_label_screen ) {
	add_filter(
		'bulk_actions-' . $amouriq_label_screen,
		static function ( $actions ) {
			$actions['amouriq_print_labels'] = 'พิมพ์ใบปะหน้าพัสดุ';
			return $actions;
		}
	);
	add_filter(
		'handle_bulk_actions-' . $amouriq_label_screen,
		static function ( $redirect, $action, $ids ) {
			return 'amouriq_print_labels' === $action && $ids ? amouriq_shipping_label_print_url( $ids ) : $redirect;
		},
		10,
		3
	);
}

add_action( 'admin_post_amouriq_label_print', 'amouriq_shipping_label_print' );

function amouriq_shipping_label_print() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'ไม่มีสิทธิ์พิมพ์ใบปะหน้า', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'amouriq_label_print' );

	$ids    = isset( $_GET['order_ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['order_ids'] ) ) ) ) ) : array();
	$labels = array();
	foreach ( $ids as $id ) {
		$order = wc_get_order( $id );
		if ( $order instanceof WC_Order ) {
			$labels[] = amouriq_shipping_label_data( $order );
		}
	}
	if ( ! $labels ) {
		wp_die( 'ไม่พบออเดอร์ที่จะพิมพ์ใบปะหน้า' );
	}
	header( 'Content-Type: text/html; charset=utf-8' );
	// phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside amouriq_label_document().
	echo str_replace( '</body>', '<script>window.addEventListener("load",function(){window.print();});</script></body>', amouriq_label_document( $labels ) );
	exit;
}

/** The order as the plain data the label needs. No amounts are read. */
function amouriq_shipping_label_data( WC_Order $order ) {
	$settings = (array) get_option( 'Wf_Woocommerce_Packing_List', array() );
	$setting  = static function ( $key ) use ( $settings ) {
		return isset( $settings[ 'woocommerce_wf_packinglist_' . $key ] ) ? $settings[ 'woocommerce_wf_packinglist_' . $key ] : '';
	};

	// Deliver to the shipping address; an order with none (no shipping needed) uses the billing one.
	$phone     = method_exists( $order, 'get_shipping_phone' ) ? $order->get_shipping_phone() : '';
	$recipient = amouriq_label_recipient( $order->get_formatted_shipping_address(), $order->get_formatted_billing_address(), $phone, $order->get_billing_phone() );

	$items = array();
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		$name    = $item->get_name();
		$option  = '';
		if ( $product && $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			$name   = $parent ? $parent->get_name() : $name;
			$option = wc_get_formatted_variation( $product, true, false, false );
		}
		$items[] = array(
			'name'   => $name,
			'option' => $option,
			'qty'    => (int) $item->get_quantity(),
		);
	}

	$state = $setting( 'sender_city' );
	$opt   = amouriq_label_options();
	// What the owner typed on the label settings page wins; an empty field falls back to the print plugin's setting.
	$sender_lines = '' !== $opt['sender_address']
		? explode( "\n", $opt['sender_address'] )
		: array_filter( array( $setting( 'sender_address_line1' ), $setting( 'sender_address_line2' ), trim( $state . ' ' . $setting( 'sender_postalcode' ) ) ) );
	return array(
		'options'      => $opt,
		'logo_url'     => '' !== $opt['logo_url'] ? $opt['logo_url'] : $setting( 'logo' ),
		'sender'       => array(
			'name'  => '' !== $opt['sender_name'] ? $opt['sender_name'] : ( $setting( 'sender_name' ) ? $setting( 'sender_name' ) : $setting( 'companyname' ) ),
			'lines' => $sender_lines,
			'phone' => '' !== $opt['sender_phone'] ? $opt['sender_phone'] : $setting( 'sender_contact_number' ),
		),
		'recipient'    => $recipient,
		'order_number' => $order->get_order_number(),
		'items'        => $items,
	);
}
