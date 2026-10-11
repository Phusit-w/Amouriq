<?php

use PHPUnit\Framework\TestCase;

/**
 * Member Privilege made customizable like a Deal: its own name, whether sale items count,
 * a Product scope, Conditions and dates. Defaults keep the original rule (5%, ฿299, regular prices only).
 */
final class BogoMemberRuleTest extends TestCase {
	protected function tearDown(): void {
		$GLOBALS['amq_test_options'] = [];
	}

	private function set(array $extra) {
		$GLOBALS['amq_test_options'] = ['amouriq_member_extra' => $extra];
	}

	private function line($pid, $qty, $price, array $extra = []) {
		return array_merge(['product_id' => $pid, 'variation_id' => 0, 'quantity' => $qty, 'on_sale' => false, 'categories' => ['soap'], 'unit_price' => $price, 'price_incl_tax' => $price], $extra);
	}

	private function member(array $lines, array $ctx = []) {
		$ctx = array_merge(['now' => '2026-10-12 12:00', 'today' => '2026-10-12', 'applied_coupons' => [], 'customer_uses' => 0, 'prices' => [], 'is_member' => true], $ctx);
		return amouriq_bogo_cart_discounts([], $lines, $ctx)['member'];
	}

	public function test_the_defaults_are_the_original_rule(): void {
		$rule = amouriq_bogo_member_rule();
		$this->assertSame(5.0, $rule['percent']);
		$this->assertSame(299.0, $rule['min_spend']);
		$this->assertFalse($rule['include_sale']);
		$this->assertSame('', $rule['label']);
		$this->assertSame([], $rule['scope']);
		$this->assertSame([], $rule['conditions']);
	}

	public function test_sale_items_get_it_only_when_the_owner_allows(): void {
		$lines = [$this->line(1, 1, 600), $this->line(2, 1, 400, ['on_sale' => true])];

		$this->assertEqualsWithDelta(30.0, $this->member($lines)['discount'], 0.001); // 5% of the 600 regular item
		$this->set(['include_sale' => true]);
		$this->assertEqualsWithDelta(50.0, $this->member($lines)['discount'], 0.001); // 5% of 1000
	}

	public function test_a_product_scope_limits_the_lines(): void {
		$this->set(['scope' => ['include' => ['categories' => ['soap']]]]);
		$lines = [$this->line(1, 1, 600), $this->line(2, 1, 400, ['categories' => ['candle']])];
		$m     = $this->member($lines);

		$this->assertEqualsWithDelta(30.0, $m['discount'], 0.001);
		$this->assertSame([0], array_keys($m['per_line']));
	}

	public function test_conditions_decide_who_gets_it(): void {
		$this->set(['conditions' => ['match' => 'all', 'rows' => [['type' => 'orders_count', 'op' => 'gte', 'value' => 2]]]]);
		$lines = [$this->line(1, 1, 600)];

		$no = $this->member($lines, ['orders_count' => 1]);
		$this->assertSame(0.0, $no['discount']);
		$this->assertSame('conditions_not_met', $no['reason']);

		$this->assertEqualsWithDelta(30.0, $this->member($lines, ['orders_count' => 3])['discount'], 0.001);
		$this->assertSame('conditions_not_met', $this->member($lines)['reason']); // customer history not known yet
	}

	public function test_dates_open_and_close_it(): void {
		$lines = [$this->line(1, 1, 600)];

		$this->set(['valid_to' => '2026-10-11']);
		$this->assertSame('outside_window', $this->member($lines)['reason']);
		$this->set(['valid_from' => '2026-10-13']);
		$this->assertSame('outside_window', $this->member($lines)['reason']);
		$this->set(['valid_from' => '2026-10-12', 'valid_to' => '2026-10-12']);
		$this->assertEqualsWithDelta(30.0, $this->member($lines)['discount'], 0.001);
	}

	public function test_it_still_needs_an_account_and_still_loses_to_a_larger_deal(): void {
		$this->set(['include_sale' => true]);
		$this->assertSame('not_member', $this->member([$this->line(1, 1, 600)], ['is_member' => false])['reason']);

		$deal = ['type' => 'amount_off_item', 'value' => 100, 'trigger' => 'auto'];
		$ctx  = ['now' => '2026-10-12 12:00', 'today' => '2026-10-12', 'applied_coupons' => [], 'customer_uses' => 0, 'prices' => [], 'is_member' => true];
		$out  = amouriq_bogo_cart_discounts([$deal], [$this->line(1, 1, 600)], $ctx);
		$this->assertSame([0], $out['member']['deal_won']); // the deal's 100 beats 5% of 600
	}

	public function test_stored_extras_are_cleaned(): void {
		$c = amouriq_bogo_member_extra_clean(['include_sale' => '1', 'label' => '  สิทธิ์พนักงาน <b>x</b> ', 'scope' => 'oops', 'conditions' => ['rows' => 'oops'], 'valid_from' => '2026-13-45', 'valid_to' => '2026-12-31 23:59', 'junk' => 1]);

		$this->assertTrue($c['include_sale']);
		$this->assertSame('สิทธิ์พนักงาน x', $c['label']);
		$this->assertSame([], $c['scope']);
		$this->assertSame([], $c['conditions']);
		$this->assertSame('', $c['valid_from']);
		$this->assertSame('2026-12-31 23:59', $c['valid_to']);
		$this->assertArrayNotHasKey('junk', $c);
	}
}
