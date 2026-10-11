<?php
/**
 * Plugin Name: AMOURIQ: portrait shipping label
 * Description: Replaces the document the "Print Invoices, Packing Slips, Labels"
 *              plugin (WebToffee) prints as its Shipping Label with a portrait,
 *              top-to-bottom layout: logo and sender, recipient, order number and an
 *              item table (number, product, option, quantity), with no prices.
 *              The plugin still owns the print button, the order list action and the
 *              print window; only the HTML it prints is replaced, through its
 *              `wt_pklist_alter_final_order_template_html` filter, so the plugin
 *              needs no edit and survives its updates. Sender and logo come from
 *              the plugin's own settings. The layout is in
 *              amouriq-shipping-label/label-html.php.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/amouriq-shipping-label/label-html.php';

add_filter( 'wt_pklist_alter_final_order_template_html', 'amouriq_shipping_label_replace', 20, 6 );

function amouriq_shipping_label_replace( $html, $template_type, $order ) {
	if ( 'shippinglabel' !== $template_type || ! $order instanceof WC_Order ) {
		return $html;
	}
	return amouriq_label_html( amouriq_shipping_label_data( $order ) );
}

/** The order as the plain data the label needs. No amounts are read. */
function amouriq_shipping_label_data( WC_Order $order ) {
	$settings = (array) get_option( 'Wf_Woocommerce_Packing_List', array() );
	$setting  = static function ( $key ) use ( $settings ) {
		return isset( $settings[ 'woocommerce_wf_packinglist_' . $key ] ) ? $settings[ 'woocommerce_wf_packinglist_' . $key ] : '';
	};

	// Deliver to the shipping address; an order with none (no shipping needed) uses the billing one.
	$formatted = $order->get_formatted_shipping_address();
	$phone     = method_exists( $order, 'get_shipping_phone' ) ? $order->get_shipping_phone() : '';
	if ( '' === trim( wp_strip_all_tags( $formatted ) ) ) {
		$formatted = $order->get_formatted_billing_address();
	}
	if ( '' === $phone ) {
		$phone = $order->get_billing_phone();
	}
	$recipient = preg_split( '#<br\s*/?>#i', (string) $formatted );
	$recipient = array_map( static function ( $line ) {
		return html_entity_decode( wp_strip_all_tags( $line ), ENT_QUOTES, 'UTF-8' );
	}, $recipient );

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
	return array(
		'logo_url'     => $setting( 'logo' ),
		'sender'       => array(
			'name'  => $setting( 'sender_name' ) ? $setting( 'sender_name' ) : $setting( 'companyname' ),
			'lines' => array_filter( array( $setting( 'sender_address_line1' ), $setting( 'sender_address_line2' ), trim( $state . ' ' . $setting( 'sender_postalcode' ) ) ) ),
			'phone' => $setting( 'sender_contact_number' ),
		),
		'recipient'    => array( 'lines' => $recipient, 'phone' => $phone ),
		'order_number' => $order->get_order_number(),
		'items'        => $items,
	);
}
