<?php

use PHPUnit\Framework\TestCase;

/** Rule-conditions ticket 08, part 1: the rows the form posts become stored Conditions, and back. */
final class BogoConditionsAdminTest extends TestCase {
	private function ok($type, $op, $text) {
		$r = amouriq_cond_parse_row($type, $op, $text);
		$this->assertArrayHasKey('row', $r, $r['error'] ?? '');
		return $r['row'];
	}

	private function bad($type, $op, $text): string {
		$r = amouriq_cond_parse_row($type, $op, $text);
		$this->assertArrayHasKey('error', $r);
		return $r['error'];
	}

	public function test_numbers_and_ranges(): void {
		$this->assertSame(['type' => 'orders_count', 'op' => 'gte', 'value' => 3], $this->ok('orders_count', 'gte', '3'));
		$this->assertSame(['type' => 'total_spent', 'op' => 'between', 'value' => [1000, 5000.5]], $this->ok('total_spent', 'between', '1000-5000.5'));
		$this->assertSame(['type' => 'cart_quantity', 'op' => 'between', 'value' => [2, 5]], $this->ok('cart_quantity', 'between', '2, 5'));
		$this->bad('orders_count', 'gte', 'abc');
		$this->bad('orders_count', 'gte', '-1');
		$this->bad('total_spent', 'between', '5000-1000'); // low first
		$this->bad('total_spent', 'gte', '');
	}

	public function test_an_operator_the_type_does_not_have_falls_back_to_its_default(): void {
		$this->assertSame('gte', $this->ok('orders_count', 'has_any', '2')['op']);
		$this->assertSame('in', $this->ok('weekday', 'gte', '1')['op']);
	}

	public function test_weekdays_by_thai_name_or_number(): void {
		$this->assertSame([6, 7], $this->ok('weekday', 'in', 'เสาร์, อาทิตย์')['value']);
		$this->assertSame([1, 5], $this->ok('weekday', 'in', '1,5,5')['value']);
		$this->assertSame([4], $this->ok('weekday', 'in', 'พฤหัสบดี')['value']);
		$this->bad('weekday', 'in', 'ศุกร์, วันพระ');
		$this->bad('weekday', 'in', '8');
	}

	public function test_time_window(): void {
		$this->assertSame(['18:00', '21:30'], $this->ok('time_of_day', 'between', '18:00-21:30')['value']);
		$this->assertSame(['22:00', '06:00'], $this->ok('time_of_day', 'between', '22:00 - 06:00')['value']);
		$this->assertSame(['09:05', '12:00'], $this->ok('time_of_day', 'between', '9:05-12:00')['value']);
		$this->bad('time_of_day', 'between', '25:00-26:00');
		$this->bad('time_of_day', 'between', 'evening');
	}

	public function test_lists_roles_emails_products_and_categories(): void {
		$this->assertSame(['customer', 'wholesale'], $this->ok('user_role', 'in', 'Customer, wholesale')['value']);
		$this->assertSame(['a@x.com', 'b@y.com'], $this->ok('email_in_list', 'in', "A@x.com\nb@y.com")['value']);
		$this->bad('email_in_list', 'in', 'not-an-email');
		$this->assertSame([2278, 2285], $this->ok('cart_has_product', 'has_all', '2278, 2285, 2278')['value']);
		$this->bad('cart_has_product', 'has_any', '22a');
		$this->assertSame(['soap', '12'], $this->ok('cart_has_category', 'has_any', 'soap, 12')['value']);
	}

	public function test_logged_in_yes_no(): void {
		$this->assertTrue($this->ok('logged_in', 'is', 'ใช่')['value']);
		$this->assertFalse($this->ok('logged_in', 'is', 'ไม่')['value']);
		$this->bad('logged_in', 'is', 'อาจจะ');
	}

	public function test_an_unknown_type_is_an_error(): void {
		$this->bad('moon_phase', 'is', 'full');
	}

	public function test_formatting_a_stored_row_gives_back_what_the_form_accepts(): void {
		foreach ([
			['orders_count', 'between', '2-5'],
			['weekday', 'in', 'เสาร์, อาทิตย์'],
			['time_of_day', 'between', '18:00-21:00'],
			['cart_has_product', 'has_any', '2278, 2285'],
			['logged_in', 'is', 'ใช่'],
		] as [$type, $op, $text]) {
			$row = $this->ok($type, $op, $text);
			$this->assertSame($text, amouriq_cond_format_value($row), $type);
			$this->assertSame($row, $this->ok($type, $op, amouriq_cond_format_value($row)), $type);
		}
	}

	public function test_a_set_reads_as_one_thai_sentence(): void {
		$set = ['match' => 'all', 'rows' => [
			$this->ok('orders_count', 'gte', '2'),
			$this->ok('weekday', 'in', 'เสาร์, อาทิตย์'),
		]];
		$this->assertSame('ครบทุกข้อ: จำนวนออเดอร์ที่เคยมี อย่างน้อย 2 · วันในสัปดาห์ เป็นวัน เสาร์, อาทิตย์', amouriq_cond_describe($set));
		$set['match'] = 'any';
		$this->assertStringStartsWith('ข้อใดข้อหนึ่ง: ', amouriq_cond_describe($set));
		$this->assertSame('', amouriq_cond_describe([]));
	}
}
