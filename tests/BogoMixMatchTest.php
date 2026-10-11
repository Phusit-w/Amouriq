<?php

use PHPUnit\Framework\TestCase;

/**
 * Ticket 06 (.scratch/promotions-rework/issues/06-mix-and-match-tiers.md):
 * quantity tiers counted across several ticked products and variations.
 */
final class BogoMixMatchTest extends TestCase {
	// Three scents of the same size, as variations of different products.
	private const LAVENDER = 2282;
	private const ROSEMARY = 2288;
	private const GERANIUM = 2296;
	private const OTHER    = 900;

	private function line($id, $qty, $price, array $extra = []) {
		return array_merge([
			'product_id'   => $id,
			'variation_id' => 0,
			'quantity'     => $qty,
			'on_sale'      => false,
			'categories'   => [],
			'unit_price'   => $price,
		], $extra);
	}

	private function ctx(array $extra = []) {
		return array_merge([
			'today'           => '2026-10-11',
			'applied_coupons' => [],
			'customer_uses'   => 0,
			'prices'          => [],
			'is_member'       => false,
		], $extra);
	}

	/** 2 pieces 8%, 3 pieces 10%, over the three scents. */
	private function deal(array $extra = []) {
		return array_merge([
			'type'        => 'quantity_tier',
			'product_ids' => [self::LAVENDER, self::ROSEMARY, self::GERANIUM],
			'tiers'       => [['min' => 2, 'percent' => 8], ['min' => 3, 'percent' => 10]],
		], $extra);
	}

	private function cart(array $deal, array $lines, array $ctx = null) {
		return amouriq_bogo_cart_discounts([$deal], $lines, $ctx ?? $this->ctx());
	}

	private function discount(array $deal, array $lines, array $ctx = null) {
		return $this->cart($deal, $lines, $ctx)['deals'][0]['discount'];
	}

	public function test_one_piece_is_below_the_first_tier(): void {
		$this->assertSame(0.0, $this->discount($this->deal(), [$this->line(self::LAVENDER, 1, 700)]));
	}

	public function test_two_pieces_of_different_scents_reach_the_first_tier(): void {
		$lines = [$this->line(self::LAVENDER, 1, 700), $this->line(self::ROSEMARY, 1, 700)];

		$this->assertEqualsWithDelta(112.0, $this->discount($this->deal(), $lines), 0.001); // 8% of 1400
	}

	public function test_three_pieces_get_ten_percent_on_all_three_not_eight_plus_ten(): void {
		$lines = [$this->line(self::LAVENDER, 1, 700), $this->line(self::ROSEMARY, 1, 700), $this->line(self::GERANIUM, 1, 700)];

		$this->assertEqualsWithDelta(210.0, $this->discount($this->deal(), $lines), 0.001); // 10% of 2100
	}

	public function test_five_pieces_still_get_ten_percent_on_all_five(): void {
		$lines = [$this->line(self::LAVENDER, 3, 700), $this->line(self::ROSEMARY, 2, 700)];

		$this->assertEqualsWithDelta(350.0, $this->discount($this->deal(), $lines), 0.001); // 10% of 3500
	}

	public function test_each_piece_is_discounted_at_its_own_price(): void {
		$lines = [$this->line(self::LAVENDER, 1, 400), $this->line(self::ROSEMARY, 1, 900)];

		$this->assertEqualsWithDelta(104.0, $this->discount($this->deal(), $lines), 0.001); // 8% of 1300
	}

	public function test_pieces_of_products_that_are_not_ticked_neither_count_nor_get_a_discount(): void {
		$lines = [$this->line(self::LAVENDER, 1, 700), $this->line(self::OTHER, 2, 700)];

		$this->assertSame(0.0, $this->discount($this->deal(), $lines));

		$lines = [$this->line(self::LAVENDER, 2, 700), $this->line(self::OTHER, 1, 700)];
		$this->assertEqualsWithDelta(112.0, $this->discount($this->deal(), $lines), 0.001); // only the 2 lavender
	}

	public function test_a_ticked_variation_matches_by_variation_id(): void {
		$deal  = $this->deal(['product_ids' => [7001, 7002]]);
		$lines = [$this->line(70, 1, 500, ['variation_id' => 7001]), $this->line(70, 1, 500, ['variation_id' => 7002]), $this->line(70, 1, 500, ['variation_id' => 7003])];

		$this->assertEqualsWithDelta(80.0, $this->discount($deal, $lines), 0.001); // 8% of 1000, the third size is not ticked
	}

	public function test_sale_items_neither_count_nor_get_a_discount_when_the_deal_skips_them(): void {
		$deal  = $this->deal(['exclude_sale' => true]);
		$lines = [$this->line(self::LAVENDER, 1, 700), $this->line(self::ROSEMARY, 1, 700, ['on_sale' => true])];

		$this->assertSame(0.0, $this->discount($deal, $lines)); // only 1 eligible piece

		$lines[] = $this->line(self::GERANIUM, 1, 700);
		$this->assertEqualsWithDelta(112.0, $this->discount($deal, $lines), 0.001); // 2 eligible pieces, 8% of 1400
	}

	public function test_sale_items_count_and_are_discounted_when_the_deal_does_not_skip_them(): void {
		$lines = [$this->line(self::LAVENDER, 1, 700), $this->line(self::ROSEMARY, 1, 700, ['on_sale' => true])];

		$this->assertEqualsWithDelta(112.0, $this->discount($this->deal(), $lines), 0.001);
	}

	public function test_an_old_single_product_deal_keeps_working(): void {
		$deal   = ['type' => 'quantity_tier', 'product_id' => self::LAVENDER, 'tiers' => [['min' => 2, 'percent' => 2], ['min' => 3, 'percent' => 4]]];
		$ctx    = $this->ctx(['prices' => [self::LAVENDER => 700]]);

		$this->assertEqualsWithDelta(84.0, $this->discount($deal, [$this->line(self::LAVENDER, 3, 700)], $ctx), 0.001); // 4% of 2100
	}

	public function test_the_discount_is_split_over_the_ticked_lines_only(): void {
		$lines  = [$this->line(self::LAVENDER, 1, 700), $this->line(self::OTHER, 1, 700), $this->line(self::ROSEMARY, 1, 300)];
		$result = $this->cart($this->deal(), $lines)['deals'][0];

		$this->assertEqualsWithDelta(56.0, $result['per_line'][0], 0.001);
		$this->assertEqualsWithDelta(24.0, $result['per_line'][2], 0.001);
		$this->assertArrayNotHasKey(1, $result['per_line']);
	}

	public function test_larger_wins_against_member_privilege_per_line(): void {
		// Deal 10% (3 pieces) beats the 5% on those lines; the unticked line still gets 5%.
		$lines  = [
			$this->line(self::LAVENDER, 1, 700), $this->line(self::ROSEMARY, 1, 700), $this->line(self::GERANIUM, 1, 700),
			$this->line(self::OTHER, 1, 1000),
		];
		$result = $this->cart($this->deal(), $lines, $this->ctx(['is_member' => true]));

		$this->assertEqualsWithDelta(50.0, $result['member']['discount'], 0.001); // 5% of 1000 only
	}

	public function test_a_low_tier_loses_to_member_privilege_on_the_same_lines(): void {
		// 2 pieces at 3% < member 5% on 1400: member adds the 2% difference.
		$deal   = $this->deal(['tiers' => [['min' => 2, 'percent' => 3]]]);
		$lines  = [$this->line(self::LAVENDER, 1, 700), $this->line(self::ROSEMARY, 1, 700)];
		$result = $this->cart($deal, $lines, $this->ctx(['is_member' => true]));

		$this->assertEqualsWithDelta(42.0, $result['deals'][0]['discount'], 0.001);
		$this->assertEqualsWithDelta(28.0, $result['member']['discount'], 0.001);
	}
}
