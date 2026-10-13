<?php

use PHPUnit\Framework\TestCase;

/** Bringing the old Points & Rewards plugin's balances over as one opening entry per member. */
final class PointsLegacyImportTest extends TestCase {
	public function test_only_positive_balances_are_carried_over_in_user_order(): void {
		$plan = amouriq_points_core_legacy_plan([33 => '99', 14 => '0', 25 => '20', 7 => '-5', 9 => 'abc', 28 => '117'], []);

		$this->assertSame([25, 28, 33], array_column($plan, 'user_id'));
		$this->assertSame([20, 117, 99], array_column($plan, 'points'));
	}

	public function test_a_member_already_imported_is_shown_as_done_not_ready(): void {
		$plan = amouriq_points_core_legacy_plan([25 => '20', 28 => '117'], [28]);

		$this->assertSame('ready', $plan[0]['status']);
		$this->assertSame('done', $plan[1]['status']);
	}

	public function test_a_fractional_old_balance_is_rounded_down_to_whole_points(): void {
		$plan = amouriq_points_core_legacy_plan([5 => '12.9', 6 => '0.4'], []);

		$this->assertCount(1, $plan);
		$this->assertSame(12, $plan[0]['points']);
	}

	public function test_nothing_to_import_gives_an_empty_plan(): void {
		$this->assertSame([], amouriq_points_core_legacy_plan([], []));
	}

	public function test_imported_points_count_as_adjusted_in_the_report_and_have_a_thai_name(): void {
		$now    = 1790000000;
		$report = amouriq_points_core_report([14 => [['time' => $now, 'points' => 50, 'expires' => $now + 100 * 86400, 'type' => 'import']]], $now);
		$month  = array_values($report['months'])[0];

		$this->assertSame(50, $month['adjusted']);
		$this->assertSame(0, $month['taken']);
		$this->assertSame(50, $report['outstanding']);
		$this->assertSame('ยกยอดจากระบบเดิม', amouriq_points_core_type_names()['import']);
	}
}
