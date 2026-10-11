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

		$this->assertMatchesRegularExpression('#<td class="num">1</td><td>Organic Olive Castile Soap - Rosemary</td><td>ปริมาณ: 250 ml</td><td class="qty">2</td>#u', $html);
		$this->assertMatchesRegularExpression('#<td class="num">2</td><td>Organic Olive Castile Soap - Lavender</td><td>ปริมาณ: 100 ml</td><td class="qty">1</td>#u', $html);
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
}
