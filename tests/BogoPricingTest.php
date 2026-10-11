<?php

use PHPUnit\Framework\TestCase;

/**
 * Ticket 01 (.scratch/promotions-rework/issues/01-extract-pricing-core.md):
 * the discount a Deal gives for a cart, observed only through the pricing
 * core's single entry point, amouriq_bogo_price(). Expected amounts are
 * worked out by hand in each test, never recomputed with the engine's formula.
 */
final class BogoPricingTest extends TestCase {
	private const LAVENDER_250 = 2282;
	private const ROSEMARY_250 = 2288;

	private function line($id, $qty, $price, array $extra = []) {
		return array_merge([
			'product_id'   => $id,
			'variation_id' => 0,
			'quantity'     => $qty,
			'on_sale'      => false,
			'categories'   => [],
		], ['unit_price' => $price], $extra);
	}

	private function ctx(array $prices = [], array $extra = []) {
		return array_merge([
			'today'           => '2026-10-11',
			'applied_coupons' => [],
			'customer_uses'   => 0,
			'prices'          => $prices,
		], $extra);
	}

	private function discount(array $deal, array $lines, array $ctx) {
		return amouriq_bogo_price($deal, $lines, $ctx)['discount'];
	}

	// --- same product: buy X get Y of the same product -------------------

	public function test_buy_one_get_one_free_discounts_one_unit(): void {
		$deal  = ['type' => 'same_product', 'product_id' => self::LAVENDER_250, 'buy_qty' => 1, 'get_qty' => 1];
		$lines = [$this->line(self::LAVENDER_250, 2, 700)];

		$this->assertSame(700.0, $this->discount($deal, $lines, $this->ctx([self::LAVENDER_250 => 700])));
	}

	public function test_a_leftover_unit_gets_no_discount(): void {
		$deal  = ['type' => 'same_product', 'product_id' => self::LAVENDER_250, 'buy_qty' => 1, 'get_qty' => 1];
		$lines = [$this->line(self::LAVENDER_250, 3, 700)];

		$this->assertSame(700.0, $this->discount($deal, $lines, $this->ctx([self::LAVENDER_250 => 700])));
	}

	public function test_a_percentage_below_100_discounts_that_share_of_the_free_unit(): void {
		$deal  = ['type' => 'same_product', 'product_id' => self::LAVENDER_250, 'buy_qty' => 1, 'get_qty' => 1, 'get_discount_percent' => 50];
		$lines = [$this->line(self::LAVENDER_250, 2, 700)];

		$this->assertSame(350.0, $this->discount($deal, $lines, $this->ctx([self::LAVENDER_250 => 700])));
	}

	public function test_max_per_order_caps_how_many_times_it_applies(): void {
		$deal  = ['type' => 'same_product', 'product_id' => self::LAVENDER_250, 'buy_qty' => 1, 'get_qty' => 1, 'max_per_order' => 1];
		$lines = [$this->line(self::LAVENDER_250, 6, 700)];

		$this->assertSame(700.0, $this->discount($deal, $lines, $this->ctx([self::LAVENDER_250 => 700])));
	}

	public function test_sale_items_are_left_out_when_the_deal_skips_sale_items(): void {
		$deal  = ['type' => 'same_product', 'product_id' => self::LAVENDER_250, 'buy_qty' => 1, 'get_qty' => 1, 'exclude_sale' => true];
		$lines = [$this->line(self::LAVENDER_250, 2, 700, ['on_sale' => true])];

		$this->assertSame(0.0, $this->discount($deal, $lines, $this->ctx([self::LAVENDER_250 => 700])));
	}

	public function test_a_variation_id_in_the_cart_counts_toward_a_variation_deal(): void {
		$deal  = ['type' => 'same_product', 'product_id' => self::LAVENDER_250, 'buy_qty' => 1, 'get_qty' => 1];
		$lines = [$this->line(2278, 2, 700, ['variation_id' => self::LAVENDER_250])];

		$this->assertSame(700.0, $this->discount($deal, $lines, $this->ctx([self::LAVENDER_250 => 700])));
	}

	// --- product pair: buy A, get B at a discount ------------------------

	public function test_buying_a_discounts_one_b(): void {
		$deal  = ['type' => 'product_pair', 'buy_product_id' => self::LAVENDER_250, 'get_product_id' => self::ROSEMARY_250, 'get_discount_percent' => 20];
		$lines = [$this->line(self::LAVENDER_250, 1, 700), $this->line(self::ROSEMARY_250, 1, 700)];
		$ctx   = $this->ctx([self::LAVENDER_250 => 700, self::ROSEMARY_250 => 700]);

		$this->assertSame(140.0, $this->discount($deal, $lines, $ctx));
	}

	public function test_no_b_in_the_cart_means_no_discount(): void {
		$deal  = ['type' => 'product_pair', 'buy_product_id' => self::LAVENDER_250, 'get_product_id' => self::ROSEMARY_250, 'get_discount_percent' => 20];
		$lines = [$this->line(self::LAVENDER_250, 2, 700)];

		$this->assertSame(0.0, $this->discount($deal, $lines, $this->ctx([self::ROSEMARY_250 => 700])));
	}

	public function test_discounted_b_units_never_exceed_the_b_units_in_the_cart(): void {
		$deal  = ['type' => 'product_pair', 'buy_product_id' => self::LAVENDER_250, 'get_product_id' => self::ROSEMARY_250, 'get_qty' => 3, 'get_discount_percent' => 100];
		$lines = [$this->line(self::LAVENDER_250, 1, 700), $this->line(self::ROSEMARY_250, 2, 700)];

		$this->assertSame(1400.0, $this->discount($deal, $lines, $this->ctx([self::ROSEMARY_250 => 700])));
	}

	public function test_each_full_set_of_a_triggers_the_deal_again_up_to_the_cap(): void {
		$deal  = ['type' => 'product_pair', 'buy_product_id' => self::LAVENDER_250, 'get_product_id' => self::ROSEMARY_250, 'buy_qty' => 2, 'max_per_order' => 2];
		$lines = [$this->line(self::LAVENDER_250, 7, 700), $this->line(self::ROSEMARY_250, 5, 700)];

		// 7 A at 2 per trigger = 3 triggers, capped at 2; two free B at 700.
		$this->assertSame(1400.0, $this->discount($deal, $lines, $this->ctx([self::ROSEMARY_250 => 700])));
	}

	// --- cheapest item of each group of N --------------------------------

	public function test_the_cheapest_of_each_group_of_three_is_free(): void {
		$deal  = ['type' => 'category_cheapest_free', 'buy_qty' => 3];
		$lines = [$this->line(1, 1, 500), $this->line(2, 1, 300), $this->line(3, 1, 100), $this->line(4, 1, 400)];

		// Sorted 500, 400, 300, 100: one full group of three; 300 is its cheapest.
		$this->assertSame(300.0, $this->discount($deal, $lines, $this->ctx()));
	}

	public function test_two_full_groups_each_free_their_own_cheapest(): void {
		$deal  = ['type' => 'category_cheapest_free', 'buy_qty' => 2];
		$lines = [$this->line(1, 1, 500), $this->line(2, 1, 400), $this->line(3, 1, 300), $this->line(4, 1, 100)];

		// Groups (500, 400) and (300, 100): 400 and 100 are free.
		$this->assertSame(500.0, $this->discount($deal, $lines, $this->ctx()));
	}

	public function test_only_items_in_the_deals_category_count(): void {
		$deal  = ['type' => 'cheapest_free', 'buy_qty' => 2, 'category' => 'soap'];
		$lines = [
			$this->line(1, 1, 500, ['categories' => ['soap']]),
			$this->line(2, 1, 300, ['categories' => ['soap']]),
			$this->line(3, 1, 50, ['categories' => ['oil']]),
		];

		$this->assertSame(300.0, $this->discount($deal, $lines, $this->ctx()));
	}

	public function test_the_deals_category_may_be_given_as_a_term_id(): void {
		$deal  = ['type' => 'category_cheapest_free', 'buy_qty' => 2, 'category' => 17];
		$lines = [
			$this->line(1, 1, 500, ['categories' => ['soap', 17]]),
			$this->line(2, 1, 300, ['categories' => ['soap', 17]]),
		];

		$this->assertSame(300.0, $this->discount($deal, $lines, $this->ctx()));
	}

	public function test_a_leftover_partial_group_gets_nothing(): void {
		$deal  = ['type' => 'category_cheapest_free', 'buy_qty' => 3];
		$lines = [$this->line(1, 1, 500), $this->line(2, 1, 300)];

		$this->assertSame(0.0, $this->discount($deal, $lines, $this->ctx()));
	}

	public function test_sale_items_do_not_count_toward_a_group_when_the_deal_skips_them(): void {
		$deal  = ['type' => 'category_cheapest_free', 'buy_qty' => 2, 'exclude_sale' => true];
		$lines = [$this->line(1, 1, 500), $this->line(2, 1, 300, ['on_sale' => true])];

		$this->assertSame(0.0, $this->discount($deal, $lines, $this->ctx()));
	}

	// --- quantity tiers ---------------------------------------------------

	private function tierDeal() {
		return [
			'type'       => 'quantity_tier',
			'product_id' => self::LAVENDER_250,
			'tiers'      => [['min' => 2, 'percent' => 8], ['min' => 3, 'percent' => 10]],
		];
	}

	public function test_below_the_first_tier_there_is_no_discount(): void {
		$lines = [$this->line(self::LAVENDER_250, 1, 700)];

		$this->assertSame(0.0, $this->discount($this->tierDeal(), $lines, $this->ctx([self::LAVENDER_250 => 700])));
	}

	public function test_the_first_tier_discounts_every_unit(): void {
		$lines = [$this->line(self::LAVENDER_250, 2, 700)];

		// 2 x 700 = 1400; 8% of that is 112.
		$this->assertEqualsWithDelta(112.0, $this->discount($this->tierDeal(), $lines, $this->ctx([self::LAVENDER_250 => 700])), 0.001);
	}

	public function test_the_highest_tier_reached_replaces_the_lower_one(): void {
		$lines = [$this->line(self::LAVENDER_250, 5, 700)];

		// 5 x 700 = 3500 at 10% = 350, not 8% plus 10%.
		$this->assertEqualsWithDelta(350.0, $this->discount($this->tierDeal(), $lines, $this->ctx([self::LAVENDER_250 => 700])), 0.001);
	}

	// --- when a deal applies at all --------------------------------------

	private function freeUnitDeal(array $extra = []) {
		return array_merge(['type' => 'same_product', 'product_id' => self::LAVENDER_250], $extra);
	}

	private function pair() {
		return [$this->line(self::LAVENDER_250, 2, 700)];
	}

	private function priced() {
		return [self::LAVENDER_250 => 700];
	}

	public function test_a_switched_off_deal_gives_nothing(): void {
		$result = amouriq_bogo_price($this->freeUnitDeal(['enabled' => false]), $this->pair(), $this->ctx($this->priced()));

		$this->assertSame(0.0, $result['discount']);
		$this->assertSame('disabled', $result['reason']);
	}

	public function test_a_deal_saved_before_the_switch_existed_stays_on(): void {
		$this->assertSame(700.0, $this->discount($this->freeUnitDeal(), $this->pair(), $this->ctx($this->priced())));
	}

	public function test_the_deal_applies_on_its_first_and_last_day_only(): void {
		$deal = $this->freeUnitDeal(['valid_from' => '2026-10-11', 'valid_to' => '2026-10-11']);

		$this->assertSame(700.0, $this->discount($deal, $this->pair(), $this->ctx($this->priced(), ['today' => '2026-10-11'])));
		$this->assertSame('outside_window', amouriq_bogo_price($deal, $this->pair(), $this->ctx($this->priced(), ['today' => '2026-10-10']))['reason']);
		$this->assertSame('outside_window', amouriq_bogo_price($deal, $this->pair(), $this->ctx($this->priced(), ['today' => '2026-10-12']))['reason']);
	}

	public function test_a_coupon_deal_needs_its_own_coupon_in_the_cart_whatever_the_case(): void {
		$deal = $this->freeUnitDeal(['trigger' => 'coupon', 'coupon_code' => 'Test-BOGO']);

		$this->assertSame('coupon_missing', amouriq_bogo_price($deal, $this->pair(), $this->ctx($this->priced()))['reason']);
		$this->assertSame(700.0, $this->discount($deal, $this->pair(), $this->ctx($this->priced(), ['applied_coupons' => ['test-bogo']])));
	}

	public function test_a_coupon_deal_with_no_code_never_applies(): void {
		$deal = $this->freeUnitDeal(['trigger' => 'coupon']);

		$this->assertSame(0.0, $this->discount($deal, $this->pair(), $this->ctx($this->priced(), ['applied_coupons' => ['anything']])));
	}

	public function test_a_not_combinable_deal_yields_to_any_other_coupon(): void {
		$deal = $this->freeUnitDeal(['no_combine' => true]);

		$this->assertSame(700.0, $this->discount($deal, $this->pair(), $this->ctx($this->priced())));
		$result = amouriq_bogo_price($deal, $this->pair(), $this->ctx($this->priced(), ['applied_coupons' => ['welcome10']]));
		$this->assertSame(0.0, $result['discount']);
		$this->assertSame('blocked_by_other_coupon', $result['reason']);
	}

	public function test_a_not_combinable_coupon_deal_still_works_with_its_own_coupon(): void {
		$deal = $this->freeUnitDeal(['no_combine' => true, 'trigger' => 'coupon', 'coupon_code' => 'BOGO1']);

		$this->assertSame(700.0, $this->discount($deal, $this->pair(), $this->ctx($this->priced(), ['applied_coupons' => ['bogo1']])));
		$this->assertSame('blocked_by_other_coupon', amouriq_bogo_price($deal, $this->pair(), $this->ctx($this->priced(), ['applied_coupons' => ['bogo1', 'welcome10']]))['reason']);
	}

	public function test_the_per_customer_limit_stops_the_deal_once_used_up(): void {
		$deal = $this->freeUnitDeal(['max_uses_per_customer' => 1]);

		$this->assertSame(700.0, $this->discount($deal, $this->pair(), $this->ctx($this->priced(), ['customer_uses' => 0])));
		$this->assertSame('customer_limit', amouriq_bogo_price($deal, $this->pair(), $this->ctx($this->priced(), ['customer_uses' => 1]))['reason']);
	}

	public function test_the_per_customer_limit_waits_while_the_customer_is_unknown(): void {
		$deal = $this->freeUnitDeal(['max_uses_per_customer' => 1]);

		$this->assertSame('customer_limit', amouriq_bogo_price($deal, $this->pair(), $this->ctx($this->priced(), ['customer_uses' => null]))['reason']);
	}

	public function test_the_customer_lookup_and_cart_lines_are_only_built_for_a_deal_that_can_apply(): void {
		$lookups = 0;
		$builds  = 0;
		$uses    = function () use (&$lookups) { $lookups++; return 0; };
		$lines   = function () use (&$builds) { $builds++; return $this->pair(); };
		$ctx     = $this->ctx($this->priced(), ['customer_uses' => $uses, 'today' => '2026-12-01']);

		$expired = $this->freeUnitDeal(['max_uses_per_customer' => 1, 'valid_to' => '2026-11-30']);
		amouriq_bogo_price($expired, $lines, $ctx);
		$this->assertSame([0, 0], [$lookups, $builds]);

		$live = $this->freeUnitDeal(['max_uses_per_customer' => 1]);
		$this->assertSame(700.0, amouriq_bogo_price($live, $lines, $ctx)['discount']);
		$this->assertSame([1, 1], [$lookups, $builds]);
	}

	public function test_an_unlimited_deal_ignores_an_unknown_customer(): void {
		$this->assertSame(700.0, $this->discount($this->freeUnitDeal(), $this->pair(), $this->ctx($this->priced(), ['customer_uses' => null])));
	}

	public function test_an_unknown_deal_type_gives_nothing(): void {
		$this->assertSame(0.0, $this->discount(['type' => 'mystery'], $this->pair(), $this->ctx()));
	}
}
