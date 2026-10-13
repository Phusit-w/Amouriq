<?php

use PHPUnit\Framework\TestCase;

/**
 * Tickets 06 and 07 (.scratch/checkout-fee-label-deals/issues): what saving or copying a
 * coupon-trigger Deal does with its coupon code (ADR-0009). The lookup stands in for
 * WooCommerce: it says whether a code is already a coupon and what state it is in.
 */
final class BogoCouponDecisionTest extends TestCase {
	/** @param array<string, array> $coupons code => ['id' => int, 'status' => string] */
	private function lookup(array $coupons) {
		return static function ($code) use ($coupons) {
			return $coupons[$code] ?? null;
		};
	}

	public function test_an_unknown_code_is_created(): void {
		$r = amouriq_bogo_core_coupon_decision('summer', $this->lookup([]), 'save');

		$this->assertSame('create', $r['action']);
		$this->assertSame('summer', $r['code']);
	}

	public function test_an_existing_code_is_linked_and_nothing_is_created(): void {
		$r = amouriq_bogo_core_coupon_decision('summer', $this->lookup(['summer' => ['id' => 9, 'status' => 'publish']]), 'save');

		$this->assertSame('link', $r['action']);
		$this->assertSame(9, $r['coupon_id']);
	}

	public function test_a_disabled_coupon_is_still_linked(): void {
		$r = amouriq_bogo_core_coupon_decision('summer', $this->lookup(['summer' => ['id' => 9, 'status' => 'draft']]), 'save');

		$this->assertSame('link', $r['action']);
	}

	public function test_an_empty_code_is_refused_in_thai(): void {
		$r = amouriq_bogo_core_coupon_decision('', $this->lookup([]), 'save');

		$this->assertSame('refuse', $r['action']);
		$this->assertStringContainsString('รหัสคูปอง', $r['message']);
	}

	public function test_a_code_with_spaces_or_symbols_is_refused(): void {
		foreach (['two words', 'a/b', 'x"y', str_repeat('a', 61)] as $code) {
			$this->assertSame('refuse', amouriq_bogo_core_coupon_decision($code, $this->lookup([]), 'save')['action'], $code);
		}
	}

	public function test_a_code_sitting_in_the_trash_is_refused(): void {
		$r = amouriq_bogo_core_coupon_decision('summer', $this->lookup(['summer' => ['id' => 9, 'status' => 'trash']]), 'save');

		$this->assertSame('refuse', $r['action']);
		$this->assertStringContainsString('ถังขยะ', $r['message']);
	}

	public function test_thai_and_dash_codes_are_fine(): void {
		$this->assertSame('create', amouriq_bogo_core_coupon_decision('ลด-10_ok.1', $this->lookup([]), 'save')['action']);
	}

	public function test_copy_adds_a_suffix(): void {
		$r = amouriq_bogo_core_coupon_decision('summer', $this->lookup(['summer' => ['id' => 9, 'status' => 'publish']]), 'copy');

		$this->assertSame('create', $r['action']);
		$this->assertSame('summer-2', $r['code']);
	}

	public function test_copy_skips_codes_in_use(): void {
		$taken = ['summer' => ['id' => 9, 'status' => 'publish'], 'summer-2' => ['id' => 10, 'status' => 'publish'], 'summer-3' => ['id' => 11, 'status' => 'draft']];
		$r     = amouriq_bogo_core_coupon_decision('summer', $this->lookup($taken), 'copy');

		$this->assertSame('summer-4', $r['code']);
	}

	public function test_a_trashed_suffix_counts_as_in_use(): void {
		$taken = ['summer-2' => ['id' => 10, 'status' => 'trash']];

		$this->assertSame('summer-3', amouriq_bogo_core_coupon_decision('summer', $this->lookup($taken), 'copy')['code']);
	}

	public function test_copying_a_deal_with_no_code_creates_nothing(): void {
		$this->assertSame('none', amouriq_bogo_core_coupon_decision('', $this->lookup([]), 'copy')['action']);
	}

	public function test_a_coupon_made_by_the_points_programme_is_refused(): void {
		$r = amouriq_bogo_core_coupon_decision('summer', $this->lookup(['summer' => ['id' => 9, 'status' => 'publish', 'owner' => 'points']]), 'save', 'b1');

		$this->assertSame('refuse', $r['action']);
		$this->assertStringContainsString('แต้มสะสม', $r['message']);
	}

	public function test_a_coupon_another_deal_created_is_refused(): void {
		$r = amouriq_bogo_core_coupon_decision('summer', $this->lookup(['summer' => ['id' => 9, 'status' => 'publish', 'owner' => 'bOTHER']]), 'save', 'b1');

		$this->assertSame('refuse', $r['action']);
		$this->assertStringContainsString('ดีลอื่น', $r['message']);
	}

	public function test_a_deal_keeps_linking_to_the_coupon_it_created_itself(): void {
		$r = amouriq_bogo_core_coupon_decision('summer', $this->lookup(['summer' => ['id' => 9, 'status' => 'publish', 'owner' => 'b1']]), 'save', 'b1');

		$this->assertSame('link', $r['action']);
	}

	public function test_a_new_deal_cannot_take_a_coupon_a_deal_created(): void {
		$r = amouriq_bogo_core_coupon_decision('summer', $this->lookup(['summer' => ['id' => 9, 'status' => 'publish', 'owner' => 'b1']]), 'save');

		$this->assertSame('refuse', $r['action']);
	}

	public function test_a_coupon_the_owner_made_by_hand_is_still_linked(): void {
		$r = amouriq_bogo_core_coupon_decision('summer', $this->lookup(['summer' => ['id' => 9, 'status' => 'publish', 'owner' => '']]), 'save', 'b1');

		$this->assertSame('link', $r['action']);
	}
}
