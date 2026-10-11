<?php
/**
 * The shipping label as HTML, from plain data, so it can be tested without
 * WordPress. A portrait document, top to bottom: logo and sender, recipient, order
 * number, then an item table (number, product, option, quantity). Long text wraps.
 * It never receives a price, discount or total, so none can appear.
 *
 * $label: array(
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

function amouriq_label_html( array $label ) {
	$sender    = isset( $label['sender'] ) ? $label['sender'] : array();
	$recipient = isset( $label['recipient'] ) ? $label['recipient'] : array();

	$rows = '';
	foreach ( array_values( isset( $label['items'] ) ? $label['items'] : array() ) as $i => $item ) {
		$rows .= '<tr><td class="num">' . ( $i + 1 ) . '</td>'
			. '<td>' . amouriq_label_escape( $item['name'] ) . '</td>'
			. '<td>' . amouriq_label_escape( isset( $item['option'] ) ? $item['option'] : '' ) . '</td>'
			. '<td class="qty">' . (int) $item['qty'] . '</td></tr>';
	}

	$logo = ! empty( $label['logo_url'] ) ? '<img class="logo" src="' . amouriq_label_escape( $label['logo_url'] ) . '" alt="">' : '';

	$sender_lines = amouriq_label_lines( array_merge( array( isset( $sender['name'] ) ? $sender['name'] : '' ), isset( $sender['lines'] ) ? $sender['lines'] : array() ) );
	$sender_phone = ! empty( $sender['phone'] ) ? '<br>โทร ' . amouriq_label_escape( $sender['phone'] ) : '';
	$to_phone     = ! empty( $recipient['phone'] ) ? '<br>โทร ' . amouriq_label_escape( $recipient['phone'] ) : '';

	return '<style type="text/css">
.amq-label{ width:100%; table-layout:fixed; border-collapse:collapse; font-family:Sarabun,Tahoma,"Helvetica Neue",Arial,sans-serif; font-size:14px; line-height:1.5; color:#111; }
.amq-label td, .amq-label th{ word-wrap:break-word; overflow-wrap:anywhere; vertical-align:top; }
.amq-label .logo{ max-width:220px; max-height:70px; display:block; margin-bottom:6px; }
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
<tr><td><table class="items"><thead><tr><th class="num">ลำดับ</th><th>สินค้า</th><th>ตัวเลือก</th><th class="qty">จำนวน</th></tr></thead><tbody>' . $rows . '</tbody></table></td></tr>
</tbody></table>';
}
