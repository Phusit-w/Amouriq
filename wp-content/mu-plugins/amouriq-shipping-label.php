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
require_once __DIR__ . '/amouriq-shipping-label/pdf.php';

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

/** The address of the print view ('print') or the PDF download ('pdf'): nonce-protected, for shop managers. */
function amouriq_shipping_label_print_url( array $order_ids, $kind = 'print' ) {
	// Not wp_nonce_url(): it HTML-escapes the address, which a redirect (the bulk action) must not get.
	return add_query_arg(
		array(
			'action'    => 'pdf' === $kind ? 'amouriq_label_pdf' : 'amouriq_label_print',
			'order_ids' => implode( ',', array_map( 'absint', $order_ids ) ),
			'_wpnonce'  => wp_create_nonce( 'amouriq_label_print' ),
		),
		admin_url( 'admin-post.php' )
	);
}

// The "ใบปะหน้าพัสดุ" box on a single order's screen (the print plugin has a Print/Download box there too).
add_action(
	'add_meta_boxes',
	static function () {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		add_meta_box(
			'amouriq_label_box',
			'ใบปะหน้าพัสดุ',
			static function ( $post_or_order ) {
				$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
				if ( ! $order ) {
					return;
				}
				$ids = array( $order->get_id() );
				printf(
					'<p><a class="button button-primary" href="%1$s" target="_blank" rel="noopener">พิมพ์ใบปะหน้า</a> <a class="button" href="%2$s">ดาวน์โหลด PDF</a></p><p class="description">ขนาดกระดาษและรูปแบบตั้งที่ WooCommerce &gt; ใบปะหน้าพัสดุ</p>',
					esc_url( amouriq_shipping_label_print_url( $ids ) ),
					esc_url( amouriq_shipping_label_print_url( $ids, 'pdf' ) )
				);
			},
			$screens,
			'side',
			'default'
		);
	}
);

// A "ใบปะหน้า" column on the order list with a print and a download button on every row. It is our own
// column, so it shows without ticking anything in Screen Options (WooCommerce's Actions column can be hidden there).
function amouriq_shipping_label_add_column( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'order_status' === $key ) {
			$out['amouriq_label'] = 'ใบปะหน้า';
		}
	}
	return isset( $out['amouriq_label'] ) ? $out : $columns + array( 'amouriq_label' => 'ใบปะหน้า' );
}

function amouriq_shipping_label_column_buttons( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	$ids = array( $order->get_id() );
	printf(
		'<a class="button button-small" href="%1$s" target="_blank" rel="noopener" title="พิมพ์ใบปะหน้าพัสดุ">พิมพ์</a> <a class="button button-small" href="%2$s" title="ดาวน์โหลดใบปะหน้า (PDF)">PDF</a>',
		esc_url( amouriq_shipping_label_print_url( $ids ) ),
		esc_url( amouriq_shipping_label_print_url( $ids, 'pdf' ) )
	);
}

// The HPOS order list, then the older posts-based list.
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'amouriq_shipping_label_add_column' );
add_action(
	'manage_woocommerce_page_wc-orders_custom_column',
	static function ( $column, $order ) {
		if ( 'amouriq_label' === $column ) {
			amouriq_shipping_label_column_buttons( $order );
		}
	},
	10,
	2
);
add_filter( 'manage_edit-shop_order_columns', 'amouriq_shipping_label_add_column' );
add_action(
	'manage_shop_order_posts_custom_column',
	static function ( $column, $post_id ) {
		if ( 'amouriq_label' === $column ) {
			amouriq_shipping_label_column_buttons( wc_get_order( $post_id ) );
		}
	},
	10,
	2
);

// The bulk action in the order list (the HPOS screen and the legacy posts screen).
foreach ( array( 'woocommerce_page_wc-orders', 'edit-shop_order' ) as $amouriq_label_screen ) {
	add_filter(
		'bulk_actions-' . $amouriq_label_screen,
		static function ( $actions ) {
			$actions['amouriq_print_labels'] = 'พิมพ์ใบปะหน้าพัสดุ';
			$actions['amouriq_pdf_labels']   = 'ดาวน์โหลดใบปะหน้าพัสดุ (PDF)';
			return $actions;
		}
	);
	add_filter(
		'handle_bulk_actions-' . $amouriq_label_screen,
		static function ( $redirect, $action, $ids ) {
			if ( ! $ids ) {
				return $redirect;
			}
			if ( 'amouriq_print_labels' === $action ) {
				return amouriq_shipping_label_print_url( $ids );
			}
			return 'amouriq_pdf_labels' === $action ? amouriq_shipping_label_print_url( $ids, 'pdf' ) : $redirect;
		},
		10,
		3
	);
}

add_action( 'admin_post_amouriq_label_print', 'amouriq_shipping_label_print' );
add_action( 'admin_post_amouriq_label_pdf', 'amouriq_shipping_label_pdf' );

/** The label data of the orders named in the request, after the permission and nonce checks (dies when none). */
function amouriq_shipping_label_request_labels() {
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
	return $labels;
}

function amouriq_shipping_label_pdf() {
	$labels = amouriq_shipping_label_request_labels();
	$o      = $labels[0]['options'];
	$pdf    = amouriq_label_pdf_bytes( amouriq_label_document( $labels, true ), $o['page_width'], $o['page_height'] );
	if ( is_wp_error( $pdf ) ) {
		wp_die( esc_html( $pdf->get_error_message() ) );
	}
	$numbers = array_map(
		static function ( $label ) {
			return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $label['order_number'] );
		},
		$labels
	);
	$name = 'label-' . ( count( $numbers ) > 3 ? $numbers[0] . '-and-' . ( count( $numbers ) - 1 ) . '-more' : implode( '-', $numbers ) ) . '.pdf';
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . strlen( $pdf ) );
	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput -- binary PDF.
	exit;
}

function amouriq_shipping_label_print() {
	$labels = amouriq_shipping_label_request_labels();
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
		// Shown only when the owner switches them on (see amouriq_label_html()).
		'invoice_number' => (string) $order->get_meta( 'wf_invoice_number', true ),
		'order_date'   => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd/m/Y' ) : '',
		'customer_note' => (string) $order->get_customer_note(),
		'items'        => $items,
	);
}
