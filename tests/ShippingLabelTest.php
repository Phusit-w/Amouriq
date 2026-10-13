<?php

use PHPUnit\Framework\TestCase;

/**
 * Ticket 09 (.scratch/promotions-rework/issues/09-shipping-label-portrait.md):
 * the portrait label, built from plain data.
 */
final class ShippingLabelTest extends TestCase {
	private function label(array $extra = []) {
		return array_merge([
			'logo_url'     => 'https://example.test/logo.webp',
			'sender'       => ['name' => 'Nature Kernel Co., Ltd.', 'lines' => ['61/63 Bang Waek Road', 'Bang Khae 10160'], 'phone' => '020233284'],
			'recipient'    => ['lines' => ['สมชาย ใจดี', '99/9 หมู่ 1 ต.บางกะปิ'], 'phone' => '0812345678'],
			'order_number' => '5390',
			'items'        => [
				['name' => 'Organic Olive Castile Soap - Rosemary', 'option' => 'ปริมาณ: 250 ml', 'qty' => 2],
				['name' => 'Organic Olive Castile Soap - Lavender', 'option' => 'ปริมาณ: 100 ml', 'qty' => 1],
			],
		], $extra);
	}

	public function test_it_has_logo_sender_recipient_order_number_and_items_in_that_order(): void {
		$html = amouriq_label_html($this->label());

		foreach (['Nature Kernel Co., Ltd.', '61/63 Bang Waek Road', '020233284', 'สมชาย ใจดี', '0812345678', '5390', 'logo.webp'] as $text) {
			$this->assertStringContainsString($text, $html);
		}
		$this->assertLessThan(strpos($html, 'Nature Kernel'), strpos($html, 'logo.webp'));
		$this->assertLessThan(strpos($html, 'สมชาย ใจดี'), strpos($html, 'Nature Kernel'));
		$this->assertLessThan(strpos($html, '5390'), strpos($html, 'สมชาย ใจดี'));
		$this->assertLessThan(strpos($html, 'Castile Soap - Rosemary'), strpos($html, '5390'));
	}

	public function test_every_item_is_numbered_with_name_option_and_quantity(): void {
		$html = amouriq_label_html($this->label());

		$this->assertMatchesRegularExpression('#<td class="num">1</td><td>Organic Olive Castile Soap - Rosemary</td><td class="opt">ปริมาณ: 250 ml</td><td class="qty">2</td>#u', $html);
		$this->assertMatchesRegularExpression('#<td class="num">2</td><td>Organic Olive Castile Soap - Lavender</td><td class="opt">ปริมาณ: 100 ml</td><td class="qty">1</td>#u', $html);
	}

	public function test_a_free_gift_is_listed_like_any_other_item(): void {
		$items   = $this->label()['items'];
		$items[] = ['name' => 'Gift Soap', 'option' => 'ปริมาณ: 30 ml', 'qty' => 1];
		$html    = amouriq_label_html($this->label(['items' => $items]));

		$this->assertMatchesRegularExpression('#<td class="num">3</td><td>Gift Soap</td>#u', $html);
	}

	public function test_no_price_discount_or_total_wording_or_currency_appears(): void {
		$text = mb_strtolower(strip_tags(amouriq_label_html($this->label())));

		foreach (['฿', 'บาท', 'ราคา', 'ส่วนลด', 'ยอดรวม', 'รวมทั้งสิ้น', 'total', 'discount', 'price', 'thb', '$'] as $word) {
			$this->assertStringNotContainsString($word, $text, "label must not contain: $word");
		}
	}

	public function test_a_long_address_and_many_items_are_all_present_and_wrap(): void {
		$long  = trim(str_repeat('บ้านเลขที่ 1234/567 หมู่บ้านตัวอย่างซอยยาวมาก ', 12));
		$items = [];
		for ($i = 1; $i <= 60; $i++) {
			$items[] = ['name' => "Product $i", 'option' => 'ปริมาณ: 100 ml', 'qty' => 1];
		}
		$html = amouriq_label_html($this->label(['recipient' => ['lines' => ['ผู้รับ', $long], 'phone' => ''], 'items' => $items]));

		$this->assertStringContainsString($long, $html);
		$this->assertStringContainsString('<td>Product 60</td>', $html);
		$this->assertStringContainsString('overflow-wrap:anywhere', $html);
		$this->assertStringContainsString('table-layout:fixed', $html);
		$this->assertStringContainsString('page-break-inside:avoid', $html); // a row is never cut in half; the table continues on the next page
	}

	public function test_text_is_escaped(): void {
		$html = amouriq_label_html($this->label(['recipient' => ['lines' => ['<script>alert(1)</script> & co'], 'phone' => '']]));

		$this->assertStringNotContainsString('<script>', $html);
		$this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; co', $html);
	}

	public function test_it_works_with_no_logo_and_no_items(): void {
		$html = amouriq_label_html($this->label(['logo_url' => '', 'items' => []]));

		$this->assertStringNotContainsString('<img', $html);
		$this->assertStringContainsString('<tbody></tbody>', $html);
	}

	public function test_the_sender_is_two_lines_name_with_phone_then_the_address_run_together(): void {
		$html = amouriq_label_html($this->label());

		$this->assertStringContainsString('Nature Kernel Co., Ltd.  โทร 020233284<br>61/63 Bang Waek Road Bang Khae 10160</div>', $html);
	}

	public function test_the_owner_can_type_the_sender_lines_and_nothing_else_is_added(): void {
		$html = amouriq_label_html($this->label(['options' => ['sender_lines' => "ร้าน A & B\nโทร 02-000-0000\n<b>กทม.</b>"]]));

		// Tags are stripped when the option is cleaned, and what remains is escaped when printed.
		$this->assertStringContainsString('ร้าน A &amp; B<br>โทร 02-000-0000<br>กทม.</div>', $html);
		$this->assertStringNotContainsString('Nature Kernel', $html);
		$this->assertStringNotContainsString('020233284', $html);
	}

	public function test_typed_sender_lines_are_cleaned_like_the_other_multiline_options(): void {
		$o = amouriq_label_clean_options(['sender_lines' => "  แถวหนึ่ง  \r\nแถวสอง\n"]);

		$this->assertSame("แถวหนึ่ง\nแถวสอง", $o['sender_lines']);
		$this->assertSame('', amouriq_label_clean_options([])['sender_lines']);
	}

	public function test_items_table_is_not_inside_a_cell_so_it_can_continue_on_the_next_page(): void {
		$html = amouriq_label_html($this->label());

		// A table nested in a table cell cannot be split by the PDF library: it jumped whole to the next page.
		$this->assertStringNotContainsString('<td><table', $html);
		$this->assertStringContainsString('<table class="items"><thead><tr class="running"><th colspan="4">ออเดอร์ #5390', $html);
	}

	public function test_print_page_shrinks_a_tall_label_to_fit_but_the_pdf_runs_no_script(): void {
		$print = amouriq_label_document([$this->label()]);
		$pdf   = amouriq_label_document([$this->label()], true);

		$this->assertStringContainsString('.amq-page', $print);
		$this->assertStringContainsString('style.zoom', $print);
		$this->assertStringNotContainsString('<script', $pdf);
		// The script sits after the last label in <body>, so "last label" must not mean :last-child (a blank extra sheet).
		$this->assertStringContainsString('.amq-page:last-of-type', $print);
		$this->assertStringNotContainsString(':last-child', $print);
	}

	public function test_options_can_hide_the_option_column_logo_and_recipient_phone(): void {
		$html = amouriq_label_html($this->label(['options' => ['show_option' => false, 'show_logo' => false, 'show_to_phone' => false]]));

		$this->assertStringNotContainsString('ตัวเลือก', $html);
		$this->assertStringNotContainsString('ปริมาณ: 250 ml', $html);
		$this->assertStringNotContainsString('<img', $html);
		$this->assertStringNotContainsString('0812345678', $html);
		$this->assertStringContainsString('<td class="qty">2</td>', $html);
	}

	public function test_options_set_font_size_logo_width_and_a_footer_note(): void {
		$html = amouriq_label_html($this->label(['options' => ['font_size' => 18, 'logo_width' => 120, 'note' => "สินค้าแตกหักง่าย\nโปรดระวัง <b>x</b>"]]));

		$this->assertStringContainsString('font-size:18px', $html);
		$this->assertStringContainsString('max-width:120px', $html);
		$this->assertStringContainsString('สินค้าแตกหักง่าย<br>โปรดระวัง x', $html);
	}

	public function test_bad_option_values_fall_back_to_the_defaults(): void {
		$o = amouriq_label_clean_options(['font_size' => 999, 'logo_width' => 'abc', 'logo_url' => 'javascript:alert(1)', 'unknown' => 'x']);

		$this->assertSame(14, $o['font_size']);
		$this->assertSame(220, $o['logo_width']);
		$this->assertSame('', $o['logo_url']);
		$this->assertArrayNotHasKey('unknown', $o);
	}

	public function test_without_options_the_label_is_unchanged(): void {
		$html = amouriq_label_html($this->label());

		$this->assertStringContainsString('font-size:14px', $html);
		$this->assertStringContainsString('ตัวเลือก', $html);
		$this->assertStringContainsString('โทร 0812345678', $html);
	}

	// --- own print: paper size, one page per order, address fallback (tickets 02, 03) ---

	public function test_the_paper_size_defaults_to_100_by_150_mm(): void {
		$doc = amouriq_label_document([$this->label()]);

		$this->assertStringContainsString('size: 100mm 150mm', $doc);
	}

	public function test_the_paper_size_is_a_setting(): void {
		$doc = amouriq_label_document([$this->label(['options' => ['page_width' => 105, 'page_height' => 148]])]);

		$this->assertStringContainsString('size: 105mm 148mm', $doc);
	}

	public function test_a_bad_paper_size_falls_back_to_the_default(): void {
		$o = amouriq_label_clean_options(['page_width' => '5', 'page_height' => 'abc']);

		$this->assertSame(100, $o['page_width']);
		$this->assertSame(150, $o['page_height']);
		$this->assertSame(210, amouriq_label_clean_options(['page_width' => 210])['page_width']);
	}

	public function test_each_order_is_its_own_page(): void {
		$doc = amouriq_label_document([
			$this->label(['order_number' => '5390']),
			$this->label(['order_number' => '5391']),
			$this->label(['order_number' => '5392']),
		]);

		$this->assertSame(3, substr_count($doc, 'class="amq-page"'));
		$this->assertLessThan(strpos($doc, '5391'), strpos($doc, '5390'));
		$this->assertLessThan(strpos($doc, '5392'), strpos($doc, '5391'));
		// A page break after every page but the last, so no blank page ends the print.
		$this->assertStringContainsString('.amq-page:last-of-type{ break-after:auto; page-break-after:auto; }', $doc);
		$this->assertStringContainsString('.amq-page{ break-after:page; page-break-after:always; }', $doc);
	}

	public function test_a_single_order_is_a_single_page(): void {
		$this->assertSame(1, substr_count(amouriq_label_document([$this->label()]), 'class="amq-page"'));
	}

	public function test_the_document_never_shows_a_price(): void {
		$doc = amouriq_label_document([$this->label()]);

		$this->assertStringNotContainsString('฿', $doc);
	}

	public function test_an_order_with_no_shipping_address_falls_back_to_billing(): void {
		$to = amouriq_label_recipient('', 'สมชาย ใจดี<br/>99/9 หมู่ 1', '', '0812345678');

		$this->assertSame(['สมชาย ใจดี', '99/9 หมู่ 1'], $to['lines']);
		$this->assertSame('0812345678', $to['phone']);
	}

	public function test_a_shipping_address_wins_over_billing(): void {
		$to = amouriq_label_recipient('วิชัย รักดี<br/>1 ถนนสุขุมวิท', 'สมชาย ใจดี<br/>99/9', '0899999999', '0812345678');

		$this->assertSame(['วิชัย รักดี', '1 ถนนสุขุมวิท'], $to['lines']);
		$this->assertSame('0899999999', $to['phone']);
	}

	public function test_a_shipping_address_of_only_tags_counts_as_empty(): void {
		$to = amouriq_label_recipient('<br/> ', 'สมชาย ใจดี', '', '0812345678');

		$this->assertSame(['สมชาย ใจดี'], $to['lines']);
	}

	public function test_html_in_the_address_is_stripped_and_entities_decoded(): void {
		$to = amouriq_label_recipient('A &amp; B<br/><b>9</b>', '', '', '');

		$this->assertSame(['A & B', '9'], $to['lines']);
	}

	public function test_a_line_break_with_a_space_still_splits_the_address(): void {
		$to = amouriq_label_recipient('สมชาย ใจดี<br />99/9 หมู่ 1<BR>กรุงเทพฯ', '', '', '');

		// The name keeps its own line; the address parts run on as one paragraph (a sticker has little room).
		$this->assertSame(['สมชาย ใจดี', '99/9 หมู่ 1 กรุงเทพฯ'], $to['lines']);
	}

	public function test_a_long_formatted_address_becomes_a_name_and_one_address_paragraph(): void {
		$to = amouriq_label_recipient('สมชาย ใจดี<br/>99/9 หมู่ 1<br/>ต.บางกะปิ<br/>เขตห้วยขวาง<br/>กรุงเทพมหานคร<br/>10310', '', '', '0812345678');

		$this->assertSame(['สมชาย ใจดี', '99/9 หมู่ 1 ต.บางกะปิ เขตห้วยขวาง กรุงเทพมหานคร 10310'], $to['lines']);
	}

	public function test_the_pdf_document_is_compact_and_pads_the_page_itself(): void {
		$pdf = amouriq_label_document([$this->label()], true);

		// dompdf ignores @page margins, so the page is padded in the markup; type is capped to fit small paper.
		$this->assertStringContainsString('margin: 0', $pdf);
		$this->assertStringContainsString('div.amq-page{ padding:4mm; }', $pdf);
		$this->assertStringContainsString('div.amq-page .amq-label{ font-size:12px; line-height:1.2; }', $pdf);
		$this->assertStringContainsString('size: 100mm 150mm', $pdf);
	}

	public function test_the_pdf_never_makes_the_type_bigger_than_the_owner_chose(): void {
		$pdf = amouriq_label_document([$this->label(['options' => ['font_size' => 10]])], true);

		$this->assertStringContainsString('font-size:10px; line-height:1.2', $pdf);
	}

	public function test_the_print_document_is_not_changed_by_the_pdf_mode(): void {
		$print = amouriq_label_document([$this->label()]);

		$this->assertStringContainsString('margin: 4mm', $print);
		$this->assertStringNotContainsString('div.amq-page{ padding', $print);
	}

	// --- what the label shows is the owner's choice ---

	private function full(array $options = []) {
		return $this->label([
			'options'        => $options,
			'invoice_number' => 'INV-5398',
			'order_date'     => '11/10/2026',
			'customer_note'  => 'ฝากไว้ที่ป้อมยาม',
		]);
	}

	public function test_invoice_number_date_and_customer_note_are_off_until_switched_on(): void {
		$html = amouriq_label_html($this->full());

		$this->assertStringNotContainsString('INV-5398', $html);
		$this->assertStringNotContainsString('11/10/2026', $html);
		$this->assertStringNotContainsString('ฝากไว้ที่ป้อมยาม', $html);
	}

	public function test_each_extra_can_be_switched_on(): void {
		$html = amouriq_label_html($this->full(['show_invoice_number' => true, 'show_order_date' => true, 'show_customer_note' => true]));

		$this->assertStringContainsString('INV-5398', $html);
		$this->assertStringContainsString('11/10/2026', $html);
		$this->assertStringContainsString('ฝากไว้ที่ป้อมยาม', $html);
	}

	public function test_an_extra_that_is_on_but_empty_for_this_order_leaves_no_blank_label(): void {
		$html = amouriq_label_html($this->label(['options' => ['show_invoice_number' => true, 'show_order_date' => true, 'show_customer_note' => true]]));

		$this->assertStringNotContainsString('เลขที่ใบแจ้งหนี้', $html);
		$this->assertStringNotContainsString('หมายเหตุจากลูกค้า', $html);
	}

	public function test_the_sender_block_can_be_switched_off(): void {
		$html = amouriq_label_html($this->full(['show_sender' => false]));

		$this->assertStringNotContainsString('Nature Kernel Co., Ltd.', $html);
		$this->assertStringNotContainsString('020233284', $html);
		$this->assertStringNotContainsString('logo.webp', $html);
		$this->assertStringContainsString('สมชาย ใจดี', $html);
	}

	public function test_the_order_number_can_be_switched_off(): void {
		$html = amouriq_label_html($this->full(['show_order_number' => false]));

		$this->assertStringNotContainsString('เลขที่ออเดอร์', $html);
		$this->assertStringNotContainsString('>5390<', $html);
	}

	public function test_the_item_table_can_be_switched_off(): void {
		$html = amouriq_label_html($this->full(['show_items' => false]));

		$this->assertStringNotContainsString('Castile Soap - Rosemary', $html);
		$this->assertStringNotContainsString('ลำดับ', $html);
		$this->assertStringContainsString('สมชาย ใจดี', $html);
	}

	public function test_the_recipient_always_shows(): void {
		$html = amouriq_label_html($this->full(['show_sender' => false, 'show_order_number' => false, 'show_items' => false]));

		$this->assertStringContainsString('สมชาย ใจดี', $html);
		$this->assertStringContainsString('99/9 หมู่ 1', $html);
	}

	public function test_the_new_switches_default_like_the_old_label(): void {
		$o = amouriq_label_clean_options([]);

		foreach (['show_sender', 'show_order_number', 'show_items'] as $on) {
			$this->assertTrue($o[$on], $on);
		}
		foreach (['show_invoice_number', 'show_order_date', 'show_customer_note'] as $off) {
			$this->assertFalse($o[$off], $off);
		}
		$this->assertTrue(amouriq_label_clean_options(['show_invoice_number' => '1'])['show_invoice_number']);
		$this->assertFalse(amouriq_label_clean_options(['show_sender' => '0'])['show_sender']);
	}

	public function test_without_the_new_options_the_label_is_the_same_as_before(): void {
		$html = amouriq_label_html($this->label());

		foreach (['Nature Kernel Co., Ltd.', 'logo.webp', 'สมชาย ใจดี', '5390', 'Castile Soap - Rosemary', 'ลำดับ'] as $text) {
			$this->assertStringContainsString($text, $html);
		}
	}

	// --- the settings form and the live preview share one reading of the form ---

	public function test_an_unticked_box_is_off_and_a_ticked_one_is_on(): void {
		$o = amouriq_label_options_from_input(['show_sender' => '1', 'font_size' => '13']);

		$this->assertTrue($o['show_sender']);
		$this->assertFalse($o['show_order_number']); // not in the form = unticked
		$this->assertFalse($o['show_logo']);
		$this->assertFalse($o['show_invoice_number']);
		$this->assertSame(13, $o['font_size']);
	}

	public function test_form_values_are_cleaned_like_stored_ones(): void {
		$o = amouriq_label_options_from_input(['font_size' => '99', 'page_width' => 'x', 'logo_url' => 'javascript:alert(1)', 'sender_name' => '<b>ร้าน</b>']);

		$this->assertSame(14, $o['font_size']);
		$this->assertSame(100, $o['page_width']);
		$this->assertSame('', $o['logo_url']);
		$this->assertSame('ร้าน', $o['sender_name']);
	}

	public function test_the_sample_label_follows_the_options(): void {
		$o      = amouriq_label_options_from_input(['show_sender' => '1', 'show_invoice_number' => '1', 'sender_name' => 'ร้านทดลอง']);
		$sample = amouriq_label_sample($o);
		$html   = amouriq_label_html($sample);

		$this->assertStringContainsString('ร้านทดลอง', $html);
		$this->assertStringContainsString('เลขที่ใบแจ้งหนี้', $html);
		$this->assertStringNotContainsString('เลขที่ออเดอร์', $html); // that box is unticked
	}

	public function test_an_empty_sender_in_the_sample_says_it_comes_from_the_print_plugin(): void {
		$html = amouriq_label_html(amouriq_label_sample(amouriq_label_options_from_input(['show_sender' => '1'])));

		$this->assertStringContainsString('ปลั๊กอินพิมพ์เอกสาร', $html);
	}
}
