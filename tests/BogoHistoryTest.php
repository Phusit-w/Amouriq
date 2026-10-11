<?php

use PHPUnit\Framework\TestCase;

/** Rule-conditions ticket 02: the order-history summary behind the history Conditions. */
final class BogoHistoryTest extends TestCase {
	private const DAY = 86400;

	public function test_no_orders_means_zero_and_never(): void {
		$h = amouriq_cond_history([], 1000000);
		$this->assertSame(0, $h['orders_count']);
		$this->assertSame(0.0, $h['total_spent']);
		$this->assertFalse($h['days_since_last_order']);
	}

	public function test_counts_sums_and_measures_days_from_the_latest_order(): void {
		$now = 100 * self::DAY;
		$h   = amouriq_cond_history([
			['total' => 500.0, 'time' => $now - 40 * self::DAY],
			['total' => 1200.5, 'time' => $now - 20 * self::DAY + 3600],
		], $now);

		$this->assertSame(2, $h['orders_count']);
		$this->assertEqualsWithDelta(1700.5, $h['total_spent'], 0.001);
		$this->assertSame(19, $h['days_since_last_order']); // 19 days and 23 hours: whole days only
	}

	public function test_a_future_dated_order_counts_as_today(): void {
		$h = amouriq_cond_history([['total' => 10.0, 'time' => 5000]], 1000);
		$this->assertSame(0, $h['days_since_last_order']);
	}

	public function test_a_fully_refunded_order_still_counts_as_an_order_but_adds_no_spend(): void {
		$h = amouriq_cond_history([['total' => 0.0, 'time' => 100]], 100 + self::DAY);
		$this->assertSame(1, $h['orders_count']);
		$this->assertSame(0.0, $h['total_spent']);
		$this->assertSame(1, $h['days_since_last_order']);
	}

	public function test_it_feeds_the_conditions_evaluator(): void {
		$now = 100 * self::DAY;
		$ctx = array_merge(['now' => '2026-10-12 12:00'], amouriq_cond_history([['total' => 2500.0, 'time' => $now - 30 * self::DAY]], $now));
		$set = ['match' => 'all', 'rows' => [
			['type' => 'orders_count', 'op' => 'gte', 'value' => 1],
			['type' => 'total_spent', 'op' => 'gte', 'value' => 2000],
			['type' => 'days_since_last_order', 'op' => 'gte', 'value' => 14],
		]];
		$this->assertSame('match', amouriq_cond_evaluate($set, $ctx)['result']);
	}
}
