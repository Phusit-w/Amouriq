<?php

use PHPUnit\Framework\TestCase;

/**
 * Ticket 07 (.scratch/promotions-rework/issues/07-buy-a-discount-b-with-cart-choice.md):
 * "buy N of A, M of the ticked B items get a percent or baht discount", where the
 * customer picks B in the cart. The pick is an ordinary cart line.
 */
final class BogoPairChoiceTest extends TestCase {
	private const A  = 10;   // the product to buy
	private const B1 = 21;   // choosable items (e.g. Castile Soap 250 ml scents)
	private const B2 = 22;
	private const B3 = 23;
	private const X  = 99;   // anything else

	private function line($id, $qty, $price, array $extra = []) {
		return array_merge([
			'product_id'     => $id,
			'variation_id'   => 0,
			'quantity'       => $qty,
			'on_sale'        => false,
			'categories'     => [],
			'unit_price'     => $price,
			'price_incl_tax' => round($price * 1.07, 2),
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

	private function deal(array $extra = []) {
		return array_merge([
			'type'           => 'product_pair',
			'buy_product_id' => self::A,
			'buy_qty'        => 1,
			'get_qty'        => 1,
			'get_product_ids' => [self::B1, self::B2, self::B3],
			'get_mode'       => 'percent',
			'get_value'      => 20,
		], $extra);
	}

	private function cart(array $deal, array $lines, array $ctx = null) {
		return amouriq_bogo_cart_discounts([$deal], $lines, $ctx ?? $this->ctx());
	}

	private function deal_result(array $deal, array $lines, array $ctx = null) {
		return $this->cart($deal, $lines, $ctx)['deals'][0];
	}

	public function test_without_the_a_product_there_is_no_discount_and_nothing_to_choose(): void {
		$r = $this->deal_result($this->deal(), [$this->line(self::B1, 1, 700)]);

		$this->assertSame(0.0, $r['discount']);
		$this->assertSame(0, $r['choices_left']);
	}

	public function test_with_a_and_no_b_the_customer_has_one_choice_left_and_no_discount_yet(): void {
		$r = $this->deal_result($this->deal(), [$this->line(self::A, 1, 500)]);

		$this->assertSame(0.0, $r['discount']);
		$this->assertSame(1, $r['choices_left']);
	}

	public function test_the_chosen_b_gets_the_percentage_and_no_choices_are_left(): void {
		$r = $this->deal_result($this->deal(), [$this->line(self::A, 1, 500), $this->line(self::B2, 1, 700)]);

		$this->assertEqualsWithDelta(140.0, $r['discount'], 0.001); // 20% of 700
		$this->assertSame(0, $r['choices_left']);
	}

	public function test_only_as_many_b_as_the_deal_allows_are_discounted(): void {
		// One A allows one B; the second B is a normal purchase.
		$lines = [$this->line(self::A, 1, 500), $this->line(self::B1, 1, 700), $this->line(self::B2, 1, 900)];
		$r     = $this->deal_result($this->deal(), $lines);

		$this->assertEqualsWithDelta(140.0, $r['discount'], 0.001);
		$this->assertSame([1 => 140.0], $r['per_line']);
		$this->assertSame(0, $r['choices_left']);
	}

	public function test_two_a_allow_two_b_of_any_ticked_variations(): void {
		$lines = [$this->line(self::A, 2, 500), $this->line(self::B1, 1, 700), $this->line(self::B3, 1, 900)];
		$r     = $this->deal_result($this->deal(), $lines);

		$this->assertEqualsWithDelta(320.0, $r['discount'], 0.001); // 20% of 700 + 20% of 900
		$this->assertSame(0, $r['choices_left']);
	}

	public function test_buy_and_get_quantities_scale_the_allowance(): void {
		// Buy 2 of A, 2 of B get the discount. Three A gives one trigger, so 2 choices.
		$deal = $this->deal(['buy_qty' => 2, 'get_qty' => 2]);

		$this->assertSame(2, $this->deal_result($deal, [$this->line(self::A, 3, 500)])['choices_left']);
		$this->assertSame(0, $this->deal_result($deal, [$this->line(self::A, 1, 500)])['choices_left']);
	}

	public function test_a_baht_discount_is_taken_as_shown_and_never_exceeds_the_item(): void {
		$deal = $this->deal(['get_mode' => 'baht', 'get_value' => 100]);
		$r    = $this->deal_result($deal, [$this->line(self::A, 1, 500), $this->line(self::B1, 1, 700)]);

		$this->assertEqualsWithDelta(100 / 1.07, $r['discount'], 0.001); // as shown to the shopper

		$deal = $this->deal(['get_mode' => 'baht', 'get_value' => 5000]);
		$r    = $this->deal_result($deal, [$this->line(self::A, 1, 500), $this->line(self::B1, 1, 700)]);
		$this->assertEqualsWithDelta(700.0, $r['discount'], 0.001);
	}

	public function test_sale_items_are_skipped_when_the_deal_skips_them(): void {
		$deal  = $this->deal(['exclude_sale' => true]);
		$lines = [$this->line(self::A, 1, 500), $this->line(self::B1, 1, 700, ['on_sale' => true])];
		$r     = $this->deal_result($deal, $lines);

		$this->assertSame(0.0, $r['discount']);
		$this->assertSame(1, $r['choices_left']); // a sale B does not use up the choice

		// A sale item does not count as the A either.
		$r = $this->deal_result($deal, [$this->line(self::A, 1, 500, ['on_sale' => true]), $this->line(self::B1, 1, 700)]);
		$this->assertSame(0.0, $r['discount']);
		$this->assertSame(0, $r['choices_left']);
	}

	public function test_a_ticked_variation_matches_by_variation_id(): void {
		$deal  = $this->deal(['get_product_ids' => [5001, 5002]]);
		$lines = [$this->line(self::A, 1, 500), $this->line(50, 1, 700, ['variation_id' => 5002]), $this->line(50, 1, 700, ['variation_id' => 5003])];
		$r     = $this->deal_result($deal, $lines);

		$this->assertSame([1 => 140.0], $r['per_line']);
	}

	public function test_max_per_order_caps_the_allowance(): void {
		$deal = $this->deal(['max_per_order' => 1]);
		$r    = $this->deal_result($deal, [$this->line(self::A, 3, 500)]);

		$this->assertSame(1, $r['choices_left']);
	}

	public function test_a_switched_off_deal_offers_no_choice(): void {
		$r = $this->deal_result($this->deal(['enabled' => false]), [$this->line(self::A, 1, 500)]);

		$this->assertSame(0, $r['choices_left']);
		$this->assertSame('disabled', $r['reason']);
	}

	public function test_the_a_item_is_not_also_counted_as_a_b_choice(): void {
		// A is also one of the ticked B items: one A cannot be both the purchase and the discounted piece.
		$deal = $this->deal(['get_product_ids' => [self::A, self::B1]]);
		$r    = $this->deal_result($deal, [$this->line(self::A, 1, 500)]);

		$this->assertSame(0.0, $r['discount']);
	}

	public function test_larger_wins_against_member_privilege_per_line(): void {
		// Chosen B at 20% beats the 5%; A and the other item keep 5%.
		$lines  = [$this->line(self::A, 1, 500), $this->line(self::B2, 1, 700), $this->line(self::X, 1, 200)];
		$result = $this->cart($this->deal(), $lines, $this->ctx(['is_member' => true]));

		$this->assertEqualsWithDelta(140.0, $result['deals'][0]['discount'], 0.001);
		$this->assertEqualsWithDelta(35.0, $result['member']['discount'], 0.001); // 5% of (500 + 200)
		$this->assertSame([1], $result['member']['deal_won']);
	}

	public function test_a_small_discount_loses_to_member_privilege_on_that_line(): void {
		// 3% on B (21) < 5% (35): member adds the 14 difference on that line.
		$deal   = $this->deal(['get_value' => 3]);
		$result = $this->cart($deal, [$this->line(self::A, 1, 500), $this->line(self::B2, 1, 700)], $this->ctx(['is_member' => true]));

		$this->assertEqualsWithDelta(21.0, $result['deals'][0]['discount'], 0.001);
		$this->assertEqualsWithDelta(25.0 + 14.0, $result['member']['discount'], 0.001);
	}

	public function test_an_old_single_product_pair_deal_still_works_as_before(): void {
		$deal = ['type' => 'product_pair', 'buy_product_id' => self::A, 'get_product_id' => self::B1, 'get_discount_percent' => 20];
		$ctx  = $this->ctx(['prices' => [self::B1 => 700]]);
		$r    = $this->deal_result($deal, [$this->line(self::A, 1, 500), $this->line(self::B1, 1, 700)], $ctx);

		$this->assertEqualsWithDelta(140.0, $r['discount'], 0.001);
		$this->assertSame(0, $r['choices_left']);
	}
}
