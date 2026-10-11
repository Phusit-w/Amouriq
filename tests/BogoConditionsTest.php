<?php

use PHPUnit\Framework\TestCase;

/**
 * Rule-conditions ticket 01: the Condition evaluator in the pricing core, on plain data.
 * A set is { match: all|any, rows: [ {type, op, value} ] }; the answer is
 * 'match', 'no_match' or 'unknown' (an input not known yet), with what is missing.
 */
final class BogoConditionsTest extends TestCase {
	private function ctx(array $extra = []) {
		return array_merge([
			'now'                   => '2026-10-12 19:30', // a Monday evening
			'logged_in'             => true,
			'user_roles'            => ['customer'],
			'email'                 => 'Som@Example.com',
			'orders_count'          => 3,
			'total_spent'           => 2500.0,
			'days_since_last_order' => 20,
			'cart_items'            => 2,
			'cart_quantity'         => 5,
			'cart_subtotal'         => 1200.0,
			'cart_product_ids'      => [100, 200],
			'cart_category_ids'     => [7],
		], $extra);
	}

	private function one($type, $op, $value, array $ctx = null) {
		return amouriq_cond_evaluate(['match' => 'all', 'rows' => [['type' => $type, 'op' => $op, 'value' => $value]]], $ctx ?? $this->ctx());
	}

	public function test_an_empty_set_always_matches(): void {
		$this->assertSame('match', amouriq_cond_evaluate([], $this->ctx())['result']);
		$this->assertSame('match', amouriq_cond_evaluate(['match' => 'all', 'rows' => []], [])['result']);
	}

	public function test_logged_in(): void {
		$this->assertSame('match', $this->one('logged_in', 'is', true)['result']);
		$this->assertSame('no_match', $this->one('logged_in', 'is', true, $this->ctx(['logged_in' => false]))['result']);
		$this->assertSame('match', $this->one('logged_in', 'is', false, $this->ctx(['logged_in' => false]))['result']);
	}

	public function test_user_role(): void {
		$this->assertSame('match', $this->one('user_role', 'in', ['wholesale', 'customer'])['result']);
		$this->assertSame('no_match', $this->one('user_role', 'in', ['wholesale'])['result']);
		$this->assertSame('no_match', $this->one('user_role', 'in', ['customer'], $this->ctx(['logged_in' => false, 'user_roles' => []]))['result']);
	}

	public function test_email_in_list_ignores_case_and_is_unknown_without_an_email(): void {
		$this->assertSame('match', $this->one('email_in_list', 'in', ['som@example.com'])['result']);
		$this->assertSame('no_match', $this->one('email_in_list', 'in', ['other@example.com'])['result']);
		$r = $this->one('email_in_list', 'in', ['som@example.com'], $this->ctx(['email' => '']));
		$this->assertSame('unknown', $r['result']);
		$this->assertSame(['email'], $r['missing']);
	}

	public function test_numeric_history_conditions(): void {
		$this->assertSame('match', $this->one('orders_count', 'gte', 3)['result']);
		$this->assertSame('no_match', $this->one('orders_count', 'gte', 4)['result']);
		$this->assertSame('match', $this->one('orders_count', 'eq', 0, $this->ctx(['orders_count' => 0]))['result']);
		$this->assertSame('match', $this->one('total_spent', 'between', [2000, 3000])['result']);
		$this->assertSame('no_match', $this->one('total_spent', 'lte', 1000)['result']);
		$this->assertSame('match', $this->one('days_since_last_order', 'gte', 14)['result']);
		$this->assertSame('no_match', $this->one('days_since_last_order', 'gte', 30)['result']);
	}

	public function test_history_unknown_until_the_customer_is_identified(): void {
		$ctx = $this->ctx(['orders_count' => null, 'total_spent' => null, 'days_since_last_order' => null]);
		foreach (['orders_count', 'total_spent', 'days_since_last_order'] as $type) {
			$r = $this->one($type, 'gte', 1, $ctx);
			$this->assertSame('unknown', $r['result'], $type);
			$this->assertSame(['customer'], $r['missing'], $type);
		}
	}

	public function test_days_since_last_order_is_no_match_for_someone_who_never_ordered(): void {
		$this->assertSame('no_match', $this->one('days_since_last_order', 'gte', 1, $this->ctx(['days_since_last_order' => false]))['result']);
	}

	public function test_weekday_uses_iso_numbers_monday_is_1(): void {
		$this->assertSame('match', $this->one('weekday', 'in', [1, 2])['result']);
		$this->assertSame('no_match', $this->one('weekday', 'in', [6, 7])['result']);
	}

	public function test_time_of_day_including_an_overnight_window(): void {
		$this->assertSame('match', $this->one('time_of_day', 'between', ['18:00', '21:00'])['result']);
		$this->assertSame('no_match', $this->one('time_of_day', 'between', ['09:00', '12:00'])['result']);
		$this->assertSame('match', $this->one('time_of_day', 'between', ['22:00', '06:00'], $this->ctx(['now' => '2026-10-12 23:10']))['result']);
		$this->assertSame('match', $this->one('time_of_day', 'between', ['22:00', '06:00'], $this->ctx(['now' => '2026-10-12 05:59']))['result']);
		$this->assertSame('no_match', $this->one('time_of_day', 'between', ['22:00', '06:00'], $this->ctx(['now' => '2026-10-12 12:00']))['result']);
	}

	public function test_cart_numbers(): void {
		$this->assertSame('match', $this->one('cart_items', 'gte', 2)['result']);
		$this->assertSame('no_match', $this->one('cart_items', 'gte', 3)['result']);
		$this->assertSame('match', $this->one('cart_quantity', 'between', [5, 10])['result']);
		$this->assertSame('match', $this->one('cart_subtotal', 'gte', 1200)['result']);
		$this->assertSame('no_match', $this->one('cart_subtotal', 'lte', 1199.99)['result']);
	}

	public function test_cart_has_product_and_category(): void {
		$this->assertSame('match', $this->one('cart_has_product', 'has_any', [100, 999])['result']);
		$this->assertSame('no_match', $this->one('cart_has_product', 'has_all', [100, 999])['result']);
		$this->assertSame('match', $this->one('cart_has_product', 'has_all', [100, 200])['result']);
		$this->assertSame('match', $this->one('cart_has_category', 'has_any', [7])['result']);
		$this->assertSame('no_match', $this->one('cart_has_category', 'has_any', [8])['result']);
	}

	public function test_all_needs_every_row_and_any_needs_one(): void {
		$rows = [
			['type' => 'orders_count', 'op' => 'gte', 'value' => 3],   // match
			['type' => 'weekday', 'op' => 'in', 'value' => [6, 7]],     // no match
		];
		$this->assertSame('no_match', amouriq_cond_evaluate(['match' => 'all', 'rows' => $rows], $this->ctx())['result']);
		$this->assertSame('match', amouriq_cond_evaluate(['match' => 'any', 'rows' => $rows], $this->ctx())['result']);
		$this->assertSame('no_match', amouriq_cond_evaluate(['match' => 'any', 'rows' => array_slice($rows, 1)], $this->ctx())['result']);
	}

	public function test_a_definite_no_beats_an_unknown_in_all_and_a_match_beats_it_in_any(): void {
		$ctx  = $this->ctx(['orders_count' => null]);
		$rows = [
			['type' => 'orders_count', 'op' => 'gte', 'value' => 1],  // unknown
			['type' => 'weekday', 'op' => 'in', 'value' => [6, 7]],   // no match
		];
		$this->assertSame('no_match', amouriq_cond_evaluate(['match' => 'all', 'rows' => $rows], $ctx)['result']);
		$this->assertSame('unknown', amouriq_cond_evaluate(['match' => 'any', 'rows' => $rows], $ctx)['result']);

		$rows[1]['value'] = [1];
		$this->assertSame('unknown', amouriq_cond_evaluate(['match' => 'all', 'rows' => $rows], $ctx)['result']);
		$this->assertSame('match', amouriq_cond_evaluate(['match' => 'any', 'rows' => $rows], $ctx)['result']);
	}

	public function test_a_missing_context_value_is_unknown_and_an_unknown_type_never_matches(): void {
		$this->assertSame('unknown', $this->one('cart_subtotal', 'gte', 1, [])['result']);
		$this->assertSame('no_match', $this->one('moon_phase', 'is', 'full')['result']);
	}

	public function test_context_values_may_be_callables_run_only_when_a_row_needs_them(): void {
		$calls = 0;
		$ctx   = $this->ctx(['orders_count' => function () use (&$calls) {
			$calls++;
			return 4;
		}]);

		$this->one('weekday', 'in', [1], $ctx);
		$this->assertSame(0, $calls);
		$this->assertSame('match', $this->one('orders_count', 'gte', 4, $ctx)['result']);
		$this->assertSame(1, $calls);
	}

	public function test_a_deal_with_conditions_is_stopped_when_they_are_not_met_and_one_without_is_unchanged(): void {
		$deal  = ['type' => 'spend_threshold', 'min_spend' => 500, 'mode' => 'percent', 'value' => 10];
		$lines = [['product_id' => 100, 'variation_id' => 0, 'quantity' => 2, 'on_sale' => false, 'categories' => [], 'unit_price' => 500, 'price_incl_tax' => 535.0]];
		$ctx   = array_merge($this->ctx(), ['today' => '2026-10-12', 'applied_coupons' => [], 'customer_uses' => 0, 'prices' => [], 'is_member' => false]);

		$plain = amouriq_bogo_cart_discounts([$deal], $lines, $ctx)['deals'][0];
		$this->assertEqualsWithDelta(100.0, $plain['discount'], 0.001);

		$met = amouriq_bogo_cart_discounts([$deal + ['conditions' => ['match' => 'all', 'rows' => [['type' => 'orders_count', 'op' => 'gte', 'value' => 3]]]]], $lines, $ctx)['deals'][0];
		$this->assertEqualsWithDelta(100.0, $met['discount'], 0.001);

		$unmet = amouriq_bogo_cart_discounts([$deal + ['conditions' => ['match' => 'all', 'rows' => [['type' => 'orders_count', 'op' => 'gte', 'value' => 9]]]]], $lines, $ctx)['deals'][0];
		$this->assertSame(0.0, $unmet['discount']);
		$this->assertSame('conditions_not_met', $unmet['reason']);
	}
}
