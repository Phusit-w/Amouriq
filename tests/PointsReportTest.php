<?php

use PHPUnit\Framework\TestCase;

/** Points round two, ticket 04: the report numbers and the CSV line. */
final class PointsReportTest extends TestCase {
	private function t($date) {
		return strtotime($date . ' 12:00:00 UTC');
	}

	private function entry($date, $points, $type, $expires = null) {
		$time = $this->t($date);
		return ['time' => $time, 'points' => $points, 'type' => $type, 'expires' => $points > 0 ? ($expires ? $this->t($expires) : $time + 365 * 86400) : 0];
	}

	public function test_months_add_up_each_kind_of_entry(): void {
		$ledgers = [
			1 => [
				$this->entry('2026-01-10', 100, 'earn'),
				$this->entry('2026-01-20', 20, 'bonus_signup'),
				$this->entry('2026-02-05', -30, 'use'),
				$this->entry('2026-02-07', 50, 'adjust'),
				$this->entry('2026-02-09', -10, 'adjust'),
				$this->entry('2026-03-01', -15, 'refund'),
				$this->entry('2026-03-02', 30, 'give_back'),
			],
		];
		$r = amouriq_points_core_report($ledgers, $this->t('2026-04-01'));

		$this->assertSame(['earned' => 120, 'used' => 0, 'adjusted' => 0, 'taken' => 0, 'expired' => 0], $r['months']['2026-01']);
		$this->assertSame(['earned' => 0, 'used' => 30, 'adjusted' => 40, 'taken' => 0, 'expired' => 0], $r['months']['2026-02']);
		$this->assertSame(['earned' => 0, 'used' => 0, 'adjusted' => 0, 'taken' => 15, 'expired' => 0], $r['months']['2026-03']); // -15 refund + 30 given back
	}

	public function test_unused_points_count_as_expired_in_the_month_they_expired(): void {
		// 100 earned 2025-01-01 expiring 2026-01-01; 40 used on 2025-06-01 leaves 60 to expire.
		$ledgers = [1 => [$this->entry('2025-01-01', 100, 'earn', '2026-01-01'), $this->entry('2025-06-01', -40, 'use')]];
		$r       = amouriq_points_core_report($ledgers, $this->t('2026-05-01'));

		$this->assertSame(60, $r['months']['2026-01']['expired']);
		$this->assertSame(0, $r['outstanding']);
	}

	public function test_outstanding_is_what_can_still_be_spent_and_agrees_with_the_balance(): void {
		$now     = $this->t('2026-06-01');
		$ledgers = [
			1 => [$this->entry('2026-01-01', 100, 'earn', '2027-01-01'), $this->entry('2026-02-01', -30, 'use')],
			2 => [$this->entry('2026-03-01', 50, 'bonus_signup', '2027-03-01')],
			3 => [$this->entry('2025-01-01', 80, 'earn', '2026-01-01')], // expired
		];
		$r = amouriq_points_core_report($ledgers, $now);

		$sum = 0;
		foreach ($ledgers as $ledger) {
			$sum += amouriq_points_core_balance($ledger, $now)['points'];
		}
		$this->assertSame(120, $r['outstanding']);
		$this->assertSame($sum, $r['outstanding']);
	}

	public function test_points_expiring_in_the_next_30_days(): void {
		$now     = $this->t('2026-06-01');
		$ledgers = [
			1 => [$this->entry('2025-06-10', 40, 'earn', '2026-06-20'), $this->entry('2025-08-01', 60, 'earn', '2026-09-01')],
			2 => [$this->entry('2025-06-30', 25, 'earn', '2026-07-01')],
		];
		$r = amouriq_points_core_report($ledgers, $now);

		$this->assertSame(65, $r['expiring_soon']);
		$this->assertSame(125, $r['outstanding']);
	}

	public function test_top_earners_are_ranked_and_limited_to_ten(): void {
		$ledgers = [];
		for ($u = 1; $u <= 12; $u++) {
			$ledgers[$u] = [$this->entry('2026-01-01', $u * 10, 'earn')];
		}
		$ledgers[1][] = $this->entry('2026-02-01', -5, 'use'); // spending does not lower "earned"
		$top = amouriq_points_core_report($ledgers, $this->t('2026-06-01'))['top_earners'];

		$this->assertCount(10, $top);
		$this->assertSame([12, 11, 10], array_slice(array_keys($top), 0, 3));
		$this->assertSame(120, $top[12]);
	}

	public function test_the_site_time_offset_moves_an_entry_to_the_right_month(): void {
		$ledgers = [1 => [['time' => strtotime('2026-01-31 20:00:00 UTC'), 'points' => 10, 'type' => 'earn', 'expires' => 0]]];

		$this->assertArrayHasKey('2026-01', amouriq_points_core_report($ledgers, strtotime('2026-06-01 UTC'))['months']);
		$this->assertArrayHasKey('2026-02', amouriq_points_core_report($ledgers, strtotime('2026-06-01 UTC'), 7 * 3600)['months']); // Thailand is UTC+7: already 1 February
	}

	public function test_an_empty_programme_reports_zeros(): void {
		$r = amouriq_points_core_report([], 1000);
		$this->assertSame([], $r['months']);
		$this->assertSame(0, $r['outstanding']);
		$this->assertSame([], $r['top_earners']);
	}

	public function test_a_csv_line_quotes_commas_quotes_and_keeps_thai(): void {
		$line = amouriq_points_core_csv_line(['2026-01-10', 7, 'สมชาย, "ใจดี"', 100]);
		$this->assertSame("2026-01-10,7,\"สมชาย, \"\"ใจดี\"\"\",100\n", $line);
	}
}
