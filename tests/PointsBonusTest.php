<?php

use PHPUnit\Framework\TestCase;

/** Points round two, ticket 02: the one-time bonuses (sign-up, first order, verified review). */
final class PointsBonusTest extends TestCase {
	private const DAY = 86400;

	private function bonus(array $review = []) {
		return amouriq_points_core_bonus_clean(['review' => array_merge(['enabled' => true, 'points' => 10, 'per_year' => 5], $review)]);
	}

	private function entry($product, $time) {
		return ['type' => 'bonus_review', 'points' => 10, 'ref' => $product, 'time' => $time, 'expires' => 0];
	}

	public function test_every_bonus_is_off_by_default(): void {
		$b = amouriq_points_core_bonus_clean([]);
		$this->assertFalse($b['signup']['enabled']);
		$this->assertFalse($b['first_order']['enabled']);
		$this->assertFalse($b['review']['enabled']);
		$this->assertSame(0, amouriq_points_core_review_bonus($b, [], 1, 1000));
	}

	public function test_stored_settings_are_cleaned(): void {
		$b = amouriq_points_core_bonus_clean(['signup' => ['enabled' => '1', 'points' => '25'], 'first_order' => ['enabled' => 'no', 'points' => 'abc'], 'review' => ['per_year' => '-3', 'junk' => 1]]);

		$this->assertTrue($b['signup']['enabled']);
		$this->assertSame(25, $b['signup']['points']);
		$this->assertFalse($b['first_order']['enabled']);
		$this->assertSame(30, $b['first_order']['points']);
		$this->assertSame(5, $b['review']['per_year']);
		$this->assertArrayNotHasKey('junk', $b['review']);
	}

	public function test_a_review_earns_the_bonus_once_per_product(): void {
		$now = 400 * self::DAY;
		$this->assertSame(10, amouriq_points_core_review_bonus($this->bonus(), [], 7, $now));
		$this->assertSame(0, amouriq_points_core_review_bonus($this->bonus(), [$this->entry(7, $now - 200 * self::DAY)], 7, $now)); // same product, even long ago
		$this->assertSame(10, amouriq_points_core_review_bonus($this->bonus(), [$this->entry(7, $now - 10)], 8, $now));         // another product
	}

	public function test_the_yearly_cap_counts_the_last_365_days_only(): void {
		$now    = 800 * self::DAY;
		$recent = [];
		for ($i = 1; $i <= 5; $i++) {
			$recent[] = $this->entry(100 + $i, $now - $i * 10 * self::DAY);
		}
		$this->assertSame(0, amouriq_points_core_review_bonus($this->bonus(), $recent, 999, $now)); // 5 in the year: capped

		$older = $recent;
		$older[0] = $this->entry(101, $now - 400 * self::DAY); // one of them is more than a year old
		$this->assertSame(10, amouriq_points_core_review_bonus($this->bonus(), $older, 999, $now));
	}

	public function test_no_yearly_cap_when_it_is_zero_and_nothing_when_points_are_zero(): void {
		$now = 800 * self::DAY;
		$many = [];
		for ($i = 1; $i <= 20; $i++) {
			$many[] = $this->entry(100 + $i, $now - $i * self::DAY);
		}
		$this->assertSame(10, amouriq_points_core_review_bonus($this->bonus(['per_year' => 0]), $many, 999, $now));
		$this->assertSame(0, amouriq_points_core_review_bonus($this->bonus(['points' => 0]), [], 1, $now));
	}

	public function test_other_ledger_entries_do_not_count_toward_the_review_cap(): void {
		$now = 800 * self::DAY;
		$ledger = [['type' => 'earn', 'points' => 50, 'time' => $now - 5, 'expires' => 0, 'ref' => 7]];
		$this->assertSame(10, amouriq_points_core_review_bonus($this->bonus(['per_year' => 1]), $ledger, 7, $now));
	}

	public function test_the_new_entry_types_have_thai_names(): void {
		$names = amouriq_points_core_type_names();
		foreach (['bonus_signup', 'bonus_first', 'bonus_review', 'earn', 'adjust'] as $type) {
			$this->assertArrayHasKey($type, $names);
		}
	}
}
