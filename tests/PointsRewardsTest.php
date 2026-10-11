<?php

use PHPUnit\Framework\TestCase;

/** Points round two, ticket 05: the Rewards catalogue and what a redemption turns into. */
final class PointsRewardsTest extends TestCase {
	private function reward(array $extra = []) {
		return array_merge(['id' => 'r1', 'label' => 'คูปอง ฿100', 'cost' => 200, 'kind' => 'coupon', 'type' => 'fixed', 'value' => 100, 'min_spend' => 500, 'days_valid' => 30, 'enabled' => true], $extra);
	}

	public function test_a_good_reward_is_kept_and_normalised(): void {
		$clean = amouriq_points_core_rewards_clean([$this->reward(['cost' => '200', 'value' => '100.5', 'min_spend' => '0', 'days_valid' => '45'])]);

		$this->assertCount(1, $clean);
		$this->assertSame(200, $clean[0]['cost']);
		$this->assertEquals(100.5, $clean[0]['value']);
		$this->assertSame(45, $clean[0]['days_valid']);
		$this->assertSame('r1', $clean[0]['id']);
	}

	public function test_bad_rewards_are_dropped(): void {
		$bad = [
			$this->reward(['label' => '  ']),                 // no name
			$this->reward(['cost' => 0]),                     // free is not a reward
			$this->reward(['value' => 0]),                    // nothing to give
			$this->reward(['type' => 'percent', 'value' => 150]), // over 100 %
			$this->reward(['kind' => 'sorcery']),
			'not an array',
		];
		$this->assertSame([], amouriq_points_core_rewards_clean($bad));
	}

	public function test_days_valid_is_kept_in_range_and_ids_are_generated_when_missing(): void {
		$clean = amouriq_points_core_rewards_clean([$this->reward(['id' => '', 'days_valid' => 9999])]);
		$this->assertSame(30, $clean[0]['days_valid']);
		$this->assertNotSame('', $clean[0]['id']);
	}

	public function test_a_reward_can_be_redeemed_only_with_enough_points_and_when_on(): void {
		$r = $this->reward();
		$this->assertNull(amouriq_points_core_check_redeem($r, 200));
		$this->assertNull(amouriq_points_core_check_redeem($r, 5000));
		$this->assertStringContainsString('ไม่พอ', amouriq_points_core_check_redeem($r, 199));
		$this->assertNotNull(amouriq_points_core_check_redeem($this->reward(['enabled' => false]), 5000));
	}

	public function test_the_coupon_a_fixed_reward_becomes(): void {
		$now  = strtotime('2026-10-12 10:00:00 UTC');
		$spec = amouriq_points_core_coupon_spec($this->reward(), $now, 'Som@Example.com', 'PTS-ABCD1234');

		$this->assertSame('PTS-ABCD1234', $spec['code']);
		$this->assertSame('fixed_cart', $spec['discount_type']);
		$this->assertEquals(100, $spec['amount']);
		$this->assertEquals(500, $spec['minimum_amount']);
		$this->assertSame(1, $spec['usage_limit']);
		$this->assertSame(1, $spec['usage_limit_per_user']);
		$this->assertSame(['som@example.com'], $spec['email_restrictions']);
		$this->assertSame(strtotime('2026-11-11 23:59:59 UTC'), $spec['date_expires']); // 30 days, to the end of that day
		$this->assertFalse($spec['free_shipping']);
	}

	public function test_a_percent_reward_becomes_a_percent_coupon(): void {
		$spec = amouriq_points_core_coupon_spec($this->reward(['type' => 'percent', 'value' => 10, 'min_spend' => 0]), strtotime('2026-10-12 10:00:00 UTC'), 'a@b.co', 'PTS-X');
		$this->assertSame('percent', $spec['discount_type']);
		$this->assertEquals(10, $spec['amount']);
		$this->assertEquals(0, $spec['minimum_amount']);
	}

	public function test_a_redeem_entry_counts_as_points_used_in_the_report(): void {
		$t = strtotime('2026-03-01 12:00:00 UTC');
		$r = amouriq_points_core_report([1 => [
			['time' => $t - 86400, 'points' => 300, 'type' => 'earn', 'expires' => $t + 365 * 86400],
			['time' => $t, 'points' => -200, 'type' => 'redeem', 'expires' => 0],
		]], $t + 86400);

		$this->assertSame(200, $r['months']['2026-03']['used']);
		$this->assertSame(100, $r['outstanding']);
		$this->assertArrayHasKey('redeem', amouriq_points_core_type_names());
	}

	public function test_the_confirm_text_says_the_points_are_not_returned(): void {
		$text = amouriq_points_core_redeem_confirm($this->reward());
		$this->assertStringContainsString('200', $text);
		$this->assertStringContainsString('30 วัน', $text);
		$this->assertStringContainsString('ไม่คืนแต้ม', $text);
	}
}
