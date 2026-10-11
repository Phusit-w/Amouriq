<?php

use PHPUnit\Framework\TestCase;

/** The points programme rules (.scratch/points-programme/spec.md), as plain data. */
final class PointsTest extends TestCase {
	private function settings(array $over = []) {
		return amouriq_points_core_settings($over);
	}

	private function t(string $date): int {
		return (new DateTimeImmutable($date . ' 12:00:00 UTC'))->getTimestamp();
	}

	public function test_100_baht_earns_one_point_and_the_rest_is_dropped(): void {
		$s = $this->settings();
		$this->assertSame(0, amouriq_points_core_earn(99.99, $s));
		$this->assertSame(1, amouriq_points_core_earn(100, $s));
		$this->assertSame(12, amouriq_points_core_earn(1299.5, $s));
		$this->assertSame(0, amouriq_points_core_earn(-50, $s));
	}

	public function test_the_rate_follows_the_setting_and_nonsense_falls_back(): void {
		$this->assertSame(10, amouriq_points_core_earn(1000, $this->settings(['earn_baht' => 100])));
		$this->assertSame(20, amouriq_points_core_earn(1000, $this->settings(['earn_baht' => 50])));
		$this->assertEquals(100.0, $this->settings(['earn_baht' => 0])['earn_baht']);
		$this->assertEquals(20.0, $this->settings(['max_percent' => 'abc'])['max_percent']);
		$this->assertEquals(100.0, $this->settings(['max_percent' => 500])['max_percent']);
	}

	public function test_points_expire_twelve_months_after_the_day_they_were_earned(): void {
		$s = $this->settings();
		$this->assertSame($this->t('2027-10-11'), amouriq_points_core_expiry($this->t('2026-10-11'), $s));
		$this->assertSame(0, amouriq_points_core_expiry($this->t('2026-10-11'), $this->settings(['expiry_months' => 0])));
	}

	public function test_balance_adds_earned_lots_and_takes_off_what_was_used(): void {
		$ledger = [
			['time' => $this->t('2026-01-01'), 'points' => 100, 'expires' => $this->t('2027-01-01')],
			['time' => $this->t('2026-02-01'), 'points' => 50, 'expires' => $this->t('2027-02-01')],
			['time' => $this->t('2026-03-01'), 'points' => -30, 'expires' => 0],
		];
		$b = amouriq_points_core_balance($ledger, $this->t('2026-06-01'));
		$this->assertSame(120, $b['points']);
		$this->assertSame($this->t('2027-01-01'), $b['soonest_expiry']);
	}

	public function test_use_takes_from_the_points_that_expire_first(): void {
		$ledger = [
			['time' => $this->t('2026-01-01'), 'points' => 100, 'expires' => $this->t('2027-01-01')],
			['time' => $this->t('2026-02-01'), 'points' => 100, 'expires' => $this->t('2027-02-01')],
			['time' => $this->t('2026-03-01'), 'points' => -100, 'expires' => 0],
		];
		// The first lot was used up; after it would have expired the second still stands.
		$b = amouriq_points_core_balance($ledger, $this->t('2027-01-15'));
		$this->assertSame(100, $b['points']);
		$this->assertSame($this->t('2027-02-01'), $b['soonest_expiry']);
	}

	public function test_expired_points_are_not_spendable(): void {
		$ledger = [['time' => $this->t('2026-01-01'), 'points' => 100, 'expires' => $this->t('2027-01-01')]];
		$this->assertSame(100, amouriq_points_core_balance($ledger, $this->t('2026-12-31'))['points']);
		$this->assertSame(0, amouriq_points_core_balance($ledger, $this->t('2027-01-01'))['points']);
	}

	public function test_a_use_never_takes_points_that_had_already_expired(): void {
		$ledger = [
			['time' => $this->t('2026-01-01'), 'points' => 100, 'expires' => $this->t('2026-06-01')],
			['time' => $this->t('2026-03-01'), 'points' => 100, 'expires' => $this->t('2027-03-01')],
			['time' => $this->t('2026-09-01'), 'points' => -60, 'expires' => 0],
		];
		// On 2026-09-01 the first lot is gone, so the 60 come out of the second.
		$this->assertSame(40, amouriq_points_core_balance($ledger, $this->t('2026-10-01'))['points']);
	}

	public function test_a_reversal_larger_than_the_balance_stops_at_zero(): void {
		$ledger = [
			['time' => $this->t('2026-01-01'), 'points' => 10, 'expires' => $this->t('2027-01-01')],
			['time' => $this->t('2026-02-01'), 'points' => -40, 'expires' => 0],
		];
		$this->assertSame(0, amouriq_points_core_balance($ledger, $this->t('2026-03-01'))['points']);
	}

	public function test_an_empty_ledger_is_zero(): void {
		$this->assertSame(['points' => 0, 'soonest_expiry' => 0], amouriq_points_core_balance([], time()));
	}

	public function test_most_points_usable_is_twenty_percent_of_the_item_total(): void {
		$s = $this->settings();
		$this->assertSame(['points' => 200, 'reason' => ''], amouriq_points_core_redeem_limit(5000, 1000, $s));
		$this->assertSame(['points' => 150, 'reason' => ''], amouriq_points_core_redeem_limit(150, 1000, $s));
	}

	public function test_below_fifty_points_nothing_can_be_used(): void {
		$s = $this->settings();
		$this->assertSame('below_minimum', amouriq_points_core_redeem_limit(49, 5000, $s)['reason']);
		$this->assertSame('cart_too_small', amouriq_points_core_redeem_limit(5000, 200, $s)['reason']);
		$this->assertSame('no_points', amouriq_points_core_redeem_limit(0, 5000, $s)['reason']);
		$this->assertSame(50, amouriq_points_core_redeem_limit(5000, 250, $s)['points']);
	}

	public function test_a_point_is_worth_the_redeem_setting(): void {
		$s = $this->settings(['redeem_baht' => 0.5]);
		$limit = amouriq_points_core_redeem_limit(5000, 1000, $s);
		$this->assertSame(400, $limit['points']);
		$this->assertEquals(200.0, amouriq_points_core_redeem_apply(400, $limit, $s)['baht']);
	}

	public function test_a_request_is_clamped_to_the_limit_and_refused_below_the_minimum(): void {
		$s = $this->settings();
		$limit = amouriq_points_core_redeem_limit(5000, 1000, $s);
		$this->assertSame(['points' => 200, 'baht' => 200.0], amouriq_points_core_redeem_apply(9999, $limit, $s));
		$this->assertSame(['points' => 120, 'baht' => 120.0], amouriq_points_core_redeem_apply(120, $limit, $s));
		$this->assertSame(['points' => 0, 'baht' => 0.0], amouriq_points_core_redeem_apply(49, $limit, $s));
		$this->assertSame(['points' => 0, 'baht' => 0.0], amouriq_points_core_redeem_apply(-5, $limit, $s));
	}

	public function test_a_refund_takes_back_the_points_the_refunded_money_earned(): void {
		$s = $this->settings();
		// Paid 1000 -> 10 points. Refund 300 -> keeps 7.
		$this->assertSame(3, amouriq_points_core_refund_reversal(1000, 300, 10, $s));
		// Refunding everything takes back all.
		$this->assertSame(10, amouriq_points_core_refund_reversal(1000, 1000, 10, $s));
		$this->assertSame(10, amouriq_points_core_refund_reversal(1000, 1500, 10, $s));
		// A refund that does not cross a 100-baht step takes nothing.
		$this->assertSame(0, amouriq_points_core_refund_reversal(1000, 0, 10, $s));
		// Already reversed once: only the difference is taken the second time.
		$this->assertSame(0, amouriq_points_core_refund_reversal(1000, 300, 7, $s));
	}

	public function test_an_adjustment_needs_points_a_reason_and_enough_balance_to_deduct(): void {
		$this->assertNull(amouriq_points_core_check_adjustment(50, 'ชดเชย', 0));
		$this->assertNull(amouriq_points_core_check_adjustment(-30, 'แก้ไขผิดพลาด', 30));
		$this->assertNotNull(amouriq_points_core_check_adjustment(0, 'x', 10));
		$this->assertNotNull(amouriq_points_core_check_adjustment(10, '   ', 10));
		$this->assertStringContainsString('20', amouriq_points_core_check_adjustment(-21, 'x', 20));
	}
}
