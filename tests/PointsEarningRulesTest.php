<?php

use PHPUnit\Framework\TestCase;

/**
 * Points round two, ticket 01: Earning rules (a rate for a scope, an exclusion,
 * a date-range multiplier, a minimum order total and a per-order cap).
 * Amounts are worked out by hand. With no rules the result is the old flat rule.
 */
final class PointsEarningRulesTest extends TestCase {
	private function settings() {
		return amouriq_points_core_settings([]); // 1 point per 100 baht
	}

	private function line($paid, array $extra = []) {
		return array_merge(['paid' => $paid, 'product_id' => 1, 'variation_id' => 0, 'categories' => ['soap'], 'on_sale' => false], $extra);
	}

	private function candle($paid) {
		return $this->line($paid, ['product_id' => 2, 'categories' => ['candle']]);
	}

	private function earn(array $lines, $eligible, array $earning = [], array $ctx = []) {
		$ctx = array_merge(['now' => '2026-10-17 12:00'], $ctx); // a Saturday
		return amouriq_points_core_earn_order($lines, $eligible, amouriq_points_core_earning_clean($earning), $this->settings(), $ctx);
	}

	private function rate($baht, array $categories) {
		return ['kind' => 'rate', 'enabled' => true, 'baht' => $baht, 'scope' => ['include' => ['categories' => $categories]]];
	}

	public function test_with_no_rules_it_is_the_flat_rule(): void {
		$this->assertSame(8, $this->earn([$this->line(500), $this->candle(300)], 800));
		$this->assertSame(amouriq_points_core_earn(799.99, $this->settings()), $this->earn([$this->line(799.99)], 799.99));
		$this->assertSame(3, $this->earn([$this->line(100), $this->line(100), $this->line(100)], 300)); // no float drift under the step
	}

	public function test_a_category_rate_replaces_the_base_rate_on_those_lines(): void {
		$rules = ['rules' => [$this->rate(50, ['soap'])]]; // 1 point per 50 baht on soap
		$this->assertSame(13, $this->earn([$this->line(500), $this->candle(300)], 800, $rules)); // 10 + 3
	}

	public function test_an_excluded_scope_earns_nothing(): void {
		$rules = ['rules' => [['kind' => 'exclude', 'enabled' => true, 'scope' => ['include' => ['categories' => ['candle']]]]]];
		$this->assertSame(5, $this->earn([$this->line(500), $this->candle(300)], 800, $rules));
	}

	public function test_the_first_matching_rate_rule_wins_and_a_disabled_one_is_ignored(): void {
		$off   = $this->rate(10, ['soap']);
		$off['enabled'] = false;
		$rules = ['rules' => [$off, $this->rate(50, ['soap']), $this->rate(25, ['soap'])]];
		$this->assertSame(10, $this->earn([$this->line(500)], 500, $rules));
	}

	public function test_a_multiplier_applies_inside_its_dates_only(): void {
		$rules = ['rules' => [['kind' => 'multiplier', 'enabled' => true, 'factor' => 2, 'from' => '2026-10-17', 'to' => '2026-10-18']]];
		$this->assertSame(16, $this->earn([$this->line(800)], 800, $rules));
		$this->assertSame(8, $this->earn([$this->line(800)], 800, $rules, ['now' => '2026-10-19 09:00']));
		$this->assertSame(8, $this->earn([$this->line(800)], 800, $rules, ['now' => '2026-10-16 23:59']));
	}

	public function test_overlapping_multipliers_use_the_largest_not_the_product(): void {
		$rules = ['rules' => [
			['kind' => 'multiplier', 'enabled' => true, 'factor' => 2],
			['kind' => 'multiplier', 'enabled' => true, 'factor' => 3],
		]];
		$this->assertSame(24, $this->earn([$this->line(800)], 800, $rules));
	}

	public function test_a_multiplier_can_carry_conditions(): void {
		$rules = ['rules' => [['kind' => 'multiplier', 'enabled' => true, 'factor' => 2, 'conditions' => ['match' => 'all', 'rows' => [['type' => 'weekday', 'op' => 'in', 'value' => [6, 7]]]]]]];
		$this->assertSame(16, $this->earn([$this->line(800)], 800, $rules));                                  // Saturday
		$this->assertSame(8, $this->earn([$this->line(800)], 800, $rules, ['now' => '2026-10-19 12:00']));    // Monday
	}

	public function test_the_multiplier_comes_after_the_rates_and_rounding_happens_once(): void {
		// Soap 500 at 50/point = 10, candle 150 at 100/point = 1.5; sum 11.5 x 2 = 23 (not 2*10 + 2*1).
		$rules = ['rules' => [$this->rate(50, ['soap']), ['kind' => 'multiplier', 'enabled' => true, 'factor' => 2]]];
		$this->assertSame(23, $this->earn([$this->line(500), $this->candle(150)], 650, $rules));
	}

	public function test_a_minimum_order_total_and_a_per_order_cap(): void {
		$this->assertSame(0, $this->earn([$this->line(250)], 250, ['min_order' => 300]));
		$this->assertSame(3, $this->earn([$this->line(300)], 300, ['min_order' => 300]));
		$this->assertSame(20, $this->earn([$this->line(5000)], 5000, ['max_per_order' => 20]));
	}

	public function test_order_level_discounts_are_spread_over_the_lines(): void {
		// Lines add up to 1000 but the customer paid 800 (a member fee and points used). 800 earns 8.
		$this->assertSame(8, $this->earn([$this->line(600), $this->candle(400)], 800));
		// With a soap rate of 50/point: soap share 480 -> 9.6, candle share 320 -> 3.2; 12.8 -> 12.
		$rules = ['rules' => [$this->rate(50, ['soap'])]];
		$this->assertSame(12, $this->earn([$this->line(600), $this->candle(400)], 800, $rules));
	}

	public function test_nothing_paid_earns_nothing(): void {
		$this->assertSame(0, $this->earn([$this->line(0)], 0));
		$this->assertSame(0, $this->earn([], 0));
	}

	public function test_bad_stored_rules_are_dropped_or_clamped(): void {
		$clean = amouriq_points_core_earning_clean([
			'rules'         => [
				['kind' => 'rate', 'enabled' => true, 'baht' => 0, 'scope' => []],          // a rate must be above 0
				['kind' => 'multiplier', 'enabled' => true, 'factor' => 99],                  // clamped to 10
				['kind' => 'multiplier', 'enabled' => true, 'factor' => 0.5],                 // below 1 is not a bonus
				['kind' => 'sorcery', 'enabled' => true],                                     // unknown kind
				'not an array',
			],
			'min_order'     => -5,
			'max_per_order' => 'abc',
		]);

		$this->assertCount(1, $clean['rules']);
		$this->assertSame(10.0, $clean['rules'][0]['factor']);
		$this->assertSame(0.0, $clean['min_order']);
		$this->assertSame(0, $clean['max_per_order']);
	}

	public function test_a_refund_keeps_the_same_share_of_rule_based_points(): void {
		// 26 points on 800; refund 400 (half) keeps 13, so 13 are taken back.
		$this->assertSame(13, amouriq_points_core_refund_reversal_share(800, 400, 26, 26));
		// A second refund that brings the total to 600: keeps floor(26 * 200/800) = 6; 13 already taken, 13 left -> take 7 more.
		$this->assertSame(7, amouriq_points_core_refund_reversal_share(800, 600, 26, 13));
		$this->assertSame(0, amouriq_points_core_refund_reversal_share(800, 0, 26, 26));
		$this->assertSame(26, amouriq_points_core_refund_reversal_share(800, 800, 26, 26));
	}
}
