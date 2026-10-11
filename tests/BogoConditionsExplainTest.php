<?php

use PHPUnit\Framework\TestCase;

/** Rule-conditions ticket 07: the Thai reason a Coupon is refused (or that it cannot be checked yet). */
final class BogoConditionsExplainTest extends TestCase {
	private function ctx(array $extra = []) {
		return array_merge(['now' => '2026-10-19 12:00', 'logged_in' => true, 'user_roles' => ['customer'], 'email' => 'a@b.co', 'orders_count' => 1, 'cart_quantity' => 2, 'cart_subtotal' => 400.0], $extra);
	}

	private function set($match, array $rows) {
		return ['match' => $match, 'rows' => $rows];
	}

	public function test_a_matching_set_has_no_message(): void {
		$r = amouriq_cond_explain($this->set('all', [['type' => 'orders_count', 'op' => 'gte', 'value' => 1]]), $this->ctx());
		$this->assertSame(['result' => 'match', 'message' => ''], $r);
	}

	public function test_a_refusal_names_only_the_conditions_that_were_not_met(): void {
		$set = $this->set('all', [
			['type' => 'orders_count', 'op' => 'gte', 'value' => 1],   // met
			['type' => 'cart_quantity', 'op' => 'gte', 'value' => 6],  // not met
			['type' => 'weekday', 'op' => 'in', 'value' => [6, 7]],    // not met (a Monday)
		]);
		$r = amouriq_cond_explain($set, $this->ctx());

		$this->assertSame('no_match', $r['result']);
		$this->assertStringContainsString('จำนวนชิ้นรวมในตะกร้า อย่างน้อย 6', $r['message']);
		$this->assertStringContainsString('วันในสัปดาห์ เป็นวัน เสาร์, อาทิตย์', $r['message']);
		$this->assertStringNotContainsString('จำนวนออเดอร์', $r['message']);
	}

	public function test_an_any_set_lists_every_option_when_none_was_met(): void {
		$set = $this->set('any', [['type' => 'cart_quantity', 'op' => 'gte', 'value' => 6], ['type' => 'weekday', 'op' => 'in', 'value' => [6]]]);
		$r   = amouriq_cond_explain($set, $this->ctx());

		$this->assertSame('no_match', $r['result']);
		$this->assertStringContainsString('ข้อใดข้อหนึ่ง', $r['message']);
		$this->assertStringContainsString('จำนวนชิ้นรวมในตะกร้า', $r['message']);
		$this->assertStringContainsString('วันในสัปดาห์', $r['message']);
	}

	public function test_an_unknown_input_tells_the_shopper_what_to_do(): void {
		$r = amouriq_cond_explain($this->set('all', [['type' => 'orders_count', 'op' => 'gte', 'value' => 1]]), $this->ctx(['orders_count' => null]));
		$this->assertSame('unknown', $r['result']);
		$this->assertStringContainsString('เข้าสู่ระบบหรือกรอกอีเมล', $r['message']);

		$r = amouriq_cond_explain($this->set('all', [['type' => 'cart_subtotal', 'op' => 'gte', 'value' => 1]]), ['now' => '2026-10-19 12:00']);
		$this->assertSame('unknown', $r['result']);
		$this->assertStringContainsString('เพิ่มสินค้าลงตะกร้า', $r['message']);
	}

	public function test_no_conditions_always_match(): void {
		$this->assertSame('match', amouriq_cond_explain([], $this->ctx())['result']);
	}
}
