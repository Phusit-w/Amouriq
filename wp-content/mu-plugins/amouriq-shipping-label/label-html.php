<?php
/**
 * The shipping label as HTML, from plain data, so it can be tested without
 * WordPress. A portrait document, top to bottom: logo and sender, recipient, order
 * number, then an item table (number, product, option, quantity). Long text wraps.
 * It never receives a price, discount or total, so none can appear.
 *
 * $label: array(
 *   'options'      => array (optional) the owner's layout settings, see amouriq_label_defaults(),
 *   'logo_url'     => string,
 *   'sender'       => array( 'name' => string, 'lines' => string[], 'phone' => string ),
 *   'recipient'    => array( 'lines' => string[] (name first, then address), 'phone' => string ),
 *   'order_number' => string,
 *   'items'        => array( array( 'name' => string, 'option' => string, 'qty' => int ) ),
 * )
 */

defined( 'ABSPATH' ) || exit;

function amouriq_label_escape( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/** Non-empty lines, escaped, joined with line breaks. */
function amouriq_label_lines( $lines ) {
	$out = array();
	foreach ( (array) $lines as $line ) {
		$line = trim( (string) $line );
		if ( '' !== $line ) {
			$out[] = amouriq_label_escape( $line );
		}
	}
	return implode( '<br>', $out );
}

/**
 * The layout settings the owner can change, with their defaults. Sender and logo
 * left empty fall back to the print plugin's own settings.
 */
function amouriq_label_defaults() {
	return array(
		'logo_url'        => '',
		'logo_width'      => 220,
		'show_logo'       => true,
		'sender_name'     => '',
		'sender_address'  => '',
		'sender_phone'    => '',
		'font_size'       => 14,
		'show_option'     => true,
		'show_to_phone'   => true,
		'note'            => '',
		'page_width'      => 100, // mm, the paper the label prints on
		'page_height'     => 150,
	);
}

/** Any stored or posted settings as safe values: unknown keys dropped, bad numbers back to the default. */
function amouriq_label_clean_options( $raw ) {
	$raw      = is_array( $raw ) ? $raw : array();
	$defaults = amouriq_label_defaults();
	$out      = $defaults;

	foreach ( array( 'sender_name', 'sender_phone' ) as $key ) {
		if ( isset( $raw[ $key ] ) ) {
			$out[ $key ] = trim( strip_tags( (string) $raw[ $key ] ) );
		}
	}
	foreach ( array( 'sender_address', 'note' ) as $key ) {
		if ( isset( $raw[ $key ] ) ) {
			$lines       = preg_split( '/\r\n|\r|\n/', strip_tags( (string) $raw[ $key ] ) );
			$out[ $key ] = trim( implode( "\n", array_map( 'trim', $lines ) ) );
		}
	}
	if ( isset( $raw['logo_url'] ) ) {
		$url             = trim( (string) $raw['logo_url'] );
		$out['logo_url'] = ( '' !== $url && preg_match( '#^https?://#i', $url ) && false !== filter_var( $url, FILTER_VALIDATE_URL ) ) ? $url : '';
	}
	foreach ( array( 'logo_width' => array( 40, 400 ), 'font_size' => array( 10, 24 ), 'page_width' => array( 50, 400 ), 'page_height' => array( 50, 600 ) ) as $key => $range ) {
		if ( isset( $raw[ $key ] ) && is_numeric( $raw[ $key ] ) && $raw[ $key ] >= $range[0] && $raw[ $key ] <= $range[1] ) {
			$out[ $key ] = (int) round( $raw[ $key ] );
		}
	}
	foreach ( array( 'show_logo', 'show_option', 'show_to_phone' ) as $key ) {
		if ( isset( $raw[ $key ] ) ) {
			$out[ $key ] = ! in_array( $raw[ $key ], array( false, 0, '0', 'no', '' ), true );
		}
	}
	return $out;
}

/**
 * Who the parcel goes to, as the label's recipient (name first, then address lines): the
 * shipping address, or the billing one when the order has no shipping address (no shipping
 * needed). The addresses are WooCommerce's formatted HTML (lines split by <br>); the phone
 * follows the same choice.
 */
function amouriq_label_recipient( $shipping_html, $billing_html, $shipping_phone, $billing_phone ) {
	$use_shipping = '' !== trim( strip_tags( (string) $shipping_html ) );
	$html         = $use_shipping ? $shipping_html : $billing_html;
	$phone        = '' !== trim( (string) $shipping_phone ) ? $shipping_phone : $billing_phone;

	$lines = preg_split( '#<br\s*/?>#i', (string) $html );
	$lines = array_map(
		static function ( $line ) {
			return trim( html_entity_decode( strip_tags( $line ), ENT_QUOTES, 'UTF-8' ) );
		},
		$lines
	);
	return array( 'lines' => array_values( array_filter( $lines, 'strlen' ) ), 'phone' => (string) $phone );
}

/**
 * A print page of one or more labels: each label on its own page of the owner's paper size
 * (the first label's settings decide it; all labels of one print share them). `$for_pdf` makes the
 * document for dompdf: it ignores @page margins, so the page is padded in the markup, and the type is
 * capped so a label fits small paper.
 */
function amouriq_label_document( array $labels, $for_pdf = false ) {
	$first = amouriq_label_clean_options( isset( $labels[0]['options'] ) ? $labels[0]['options'] : array() );
	$pages = '';
	$pdf_css = '';
	if ( $for_pdf ) {
		$size    = min( 12, (int) $first['font_size'] );
		$pdf_css = 'div.amq-page{ padding:4mm; }
'
			. 'div.amq-page .amq-label{ font-size:' . $size . 'px; line-height:1.2; }
'
			. 'div.amq-page .amq-label .to{ font-size:' . ( $size + 3 ) . 'px; }
'
			. 'div.amq-page .amq-label .order{ font-size:' . ( $size + 4 ) . 'px; }
'
			. 'div.amq-page .amq-label .block{ padding:5px 0; }
'
			. 'div.amq-page .amq-label .logo{ max-height:40px; }
'
			. 'div.amq-page .amq-label table.items{ width:97%; }
'
			. 'div.amq-page .amq-label table.items th, div.amq-page .amq-label table.items td{ padding:3px 5px; }
';
	}
	foreach ( $labels as $label ) {
		$pages .= '<div class="amq-page">' . amouriq_label_html( $label ) . '</div>';
	}
	return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>ใบปะหน้าพัสดุ</title><style>
@page{ size: ' . (int) $first['page_width'] . 'mm ' . (int) $first['page_height'] . 'mm; margin: ' . ( $for_pdf ? '0' : '4mm' ) . '; }
html, body{ margin:0; padding:0; background:#fff; }
.amq-page{ break-after:page; page-break-after:always; }
.amq-page:last-child{ break-after:auto; page-break-after:auto; }
' . $pdf_css . '</style></head><body>' . $pages . '</body></html>';
}

function amouriq_label_html( array $label ) {
	$opt       = amouriq_label_clean_options( isset( $label['options'] ) ? $label['options'] : array() );
	$sender    = isset( $label['sender'] ) ? $label['sender'] : array();
	$recipient = isset( $label['recipient'] ) ? $label['recipient'] : array();

	$rows = '';
	foreach ( array_values( isset( $label['items'] ) ? $label['items'] : array() ) as $i => $item ) {
		$rows .= '<tr><td class="num">' . ( $i + 1 ) . '</td>'
			. '<td>' . amouriq_label_escape( $item['name'] ) . '</td>'
			. ( $opt['show_option'] ? '<td>' . amouriq_label_escape( isset( $item['option'] ) ? $item['option'] : '' ) . '</td>' : '' )
			. '<td class="qty">' . (int) $item['qty'] . '</td></tr>';
	}

	$logo = ( $opt['show_logo'] && ! empty( $label['logo_url'] ) ) ? '<img class="logo" src="' . amouriq_label_escape( $label['logo_url'] ) . '" alt="">' : '';

	$sender_lines = amouriq_label_lines( array_merge( array( isset( $sender['name'] ) ? $sender['name'] : '' ), isset( $sender['lines'] ) ? $sender['lines'] : array() ) );
	$sender_phone = ! empty( $sender['phone'] ) ? '<br>โทร ' . amouriq_label_escape( $sender['phone'] ) : '';
	$to_phone     = ( $opt['show_to_phone'] && ! empty( $recipient['phone'] ) ) ? '<br>โทร ' . amouriq_label_escape( $recipient['phone'] ) : '';
	$option_th    = $opt['show_option'] ? '<th>ตัวเลือก</th>' : '';
	$note         = '' !== $opt['note'] ? '<tr><td class="block note">' . str_replace( "\n", '<br>', amouriq_label_escape( $opt['note'] ) ) . '</td></tr>' : '';
	$font         = (int) $opt['font_size'];
	$logo_width   = (int) $opt['logo_width'];

	return '<style type="text/css">
.amq-label{ width:100%; table-layout:fixed; border-collapse:collapse; font-family:Sarabun,Tahoma,"Helvetica Neue",Arial,sans-serif; font-size:' . $font . 'px; line-height:1.5; color:#111; }
.amq-label td, .amq-label th{ word-wrap:break-word; overflow-wrap:anywhere; vertical-align:top; }
.amq-label .logo{ max-width:' . $logo_width . 'px; max-height:70px; display:block; margin-bottom:6px; }
.amq-label .tag{ font-size:12px; color:#555; letter-spacing:.04em; }
.amq-label .block{ padding:10px 0; border-bottom:1px solid #999; }
.amq-label .to{ font-size:18px; font-weight:bold; }
.amq-label .order{ font-size:20px; font-weight:bold; }
.amq-label table.items{ width:100%; table-layout:fixed; border-collapse:collapse; margin-top:6px; }
.amq-label table.items th, .amq-label table.items td{ border:1px solid #777; padding:5px 7px; text-align:left; }
.amq-label table.items th{ background:#eee; }
.amq-label table.items tr{ page-break-inside:avoid; }
.amq-label .num{ width:46px; text-align:center !important; }
.amq-label .qty{ width:70px; text-align:center !important; }
</style>
<table class="amq-label"><tbody>
<tr><td class="block">' . $logo . '<span class="tag">ผู้ส่ง (From)</span><br>' . $sender_lines . $sender_phone . '</td></tr>
<tr><td class="block"><span class="tag">ผู้รับ (To)</span><br><span class="to">' . amouriq_label_lines( isset( $recipient['lines'] ) ? $recipient['lines'] : array() ) . '</span>' . $to_phone . '</td></tr>
<tr><td class="block"><span class="tag">เลขที่ออเดอร์ (Order No.)</span><br><span class="order">' . amouriq_label_escape( isset( $label['order_number'] ) ? $label['order_number'] : '' ) . '</span></td></tr>
<tr><td><table class="items"><thead><tr><th class="num">ลำดับ</th><th>สินค้า</th>' . $option_th . '<th class="qty">จำนวน</th></tr></thead><tbody>' . $rows . '</tbody></table></td></tr>' . $note . '
</tbody></table>';
}
