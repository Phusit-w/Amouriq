<?php

use PHPUnit\Framework\TestCase;

/**
 * Ticket 02 (.scratch/promotions-rework/issues/02-member-privilege.md):
 * Member Privilege (5% off Regular Price lines, ฿299 minimum) and the
 * larger-discount-wins rule against Deals (ADR-0006), observed through
 * amouriq_bogo_cart_discounts(). Expected amounts are worked out by hand.
 */
final class BogoMemberPrivilegeTest extends TestCase {
	private const SOAP   = 100;
	private const CANDLE = 200;
	private const OIL    = 300;

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
			'prices'          => [self::SOAP => 500, self::CANDLE => 400, self::OIL => 300],
			'is_member'       => true,
		], $extra);
	}

	private function member(array $lines, array $deals = [], array $ctx = null) {
		return amouriq_bogo_cart_discounts($deals, $lines, $ctx ?? $this->ctx())['member'];
	}

	public function test_gives_five_percent_on_regular_price_lines(): void {
		$m = $this->member([$this->line(self::SOAP, 1, 500), $this->line(self::CANDLE, 2, 400)]);

		$this->assertEqualsWithDelta(65.0, $m['discount'], 0.001); // 5% of 1300
		$this->assertNull($m['reason']);
	}

	public function test_minimum_spend_boundary_298_no_299_yes(): void {
		$this->assertSame('below_minimum', $this->member([$this->line(self::SOAP, 1, 298)])['reason']);
		$this->assertSame(0.0, $this->member([$this->line(self::SOAP, 1, 298)])['discount']);
		$this->assertEqualsWithDelta(14.95, $this->member([$this->line(self::SOAP, 1, 299)])['discount'], 0.001);
	}

	public function test_sale_price_lines_count_toward_the_minimum_but_get_nothing(): void {
		$lines = [$this->line(self::SOAP, 1, 200), $this->line(self::CANDLE, 1, 150, ['on_sale' => true])];

		// 350 spend meets 299; only the 200 Regular Price line is discounted.
		$this->assertEqualsWithDelta(10.0, $this->member($lines)['discount'], 0.001);
	}

	public function test_a_cart_of_only_sale_items_gets_nothing(): void {
		$m = $this->member([$this->line(self::SOAP, 1, 500, ['on_sale' => true])]);

		$this->assertSame(0.0, $m['discount']);
		$this->assertSame('no_regular_lines', $m['reason']);
	}

	public function test_logged_out_visitor_gets_nothing(): void {
		$m = $this->member([$this->line(self::SOAP, 1, 500)], [], $this->ctx(['is_member' => false]));

		$this->assertSame(0.0, $m['discount']);
		$this->assertSame('not_member', $m['reason']);
	}

	public function test_a_customer_registering_in_this_checkout_is_a_member_for_it(): void {
		// The WooCommerce layer sets is_member for a checkout that creates an account;
		// the core only sees that flag, so the discount applies on that order.
		$m = $this->member([$this->line(self::SOAP, 1, 500)], [], $this->ctx(['is_member' => true]));

		$this->assertEqualsWithDelta(25.0, $m['discount'], 0.001);
	}

	public function test_the_minimum_uses_the_displayed_price_when_given(): void {
		// 280 before tax is 299.60 as the shopper sees it, so the minimum is met.
		$m = $this->member([$this->line(self::SOAP, 1, 280, ['price_incl_tax' => 299.6])]);

		$this->assertEqualsWithDelta(14.0, $m['discount'], 0.001);
	}

	// --- against a Deal: the larger discount wins, per line -----------------

	private function bogo_soap() {
		return ['type' => 'same_product', 'product_id' => self::SOAP, 'buy_qty' => 1, 'get_qty' => 1];
	}

	public function test_a_larger_deal_on_a_line_wins_and_other_lines_still_get_five_percent(): void {
		$lines  = [$this->line(self::SOAP, 2, 500), $this->line(self::CANDLE, 1, 400)];
		$result = amouriq_bogo_cart_discounts([$this->bogo_soap()], $lines, $this->ctx());

		$this->assertSame(500.0, $result['deals'][0]['discount']); // one soap free
		// Soap line: member would be 50, the deal gives 500, so the deal wins.
		// Candle line: no deal, member 5% of 400 = 20.
		$this->assertEqualsWithDelta(20.0, $result['member']['discount'], 0.001);
		$this->assertSame([0], $result['member']['deal_won']);
	}

	public function test_a_smaller_deal_loses_to_member_privilege_on_that_line(): void {
		// Quantity tier of 1% on 2 soaps (1000): deal 10, member 50 -> member adds the 40 difference,
		// so the line ends at 50 off, not 60.
		$deal   = ['type' => 'quantity_tier', 'product_id' => self::SOAP, 'tiers' => [['min' => 2, 'percent' => 1]]];
		$result = amouriq_bogo_cart_discounts([$deal], [$this->line(self::SOAP, 2, 500)], $this->ctx());

		$this->assertEqualsWithDelta(10.0, $result['deals'][0]['discount'], 0.001);
		$this->assertEqualsWithDelta(40.0, $result['member']['discount'], 0.001);
	}

	public function test_a_deal_that_is_off_does_not_block_member_privilege(): void {
		$deal   = $this->bogo_soap() + ['enabled' => false];
		$result = amouriq_bogo_cart_discounts([$deal], [$this->line(self::SOAP, 2, 500)], $this->ctx());

		$this->assertEqualsWithDelta(50.0, $result['member']['discount'], 0.001);
	}

	// --- against a Coupon: the larger discount wins, whole cart (ticket 03) ---

	private function with_coupon(float $coupon_discount, array $lines, array $deals = []) {
		$ctx = $this->ctx(['applied_coupons' => ['save'], 'coupon_discount' => $coupon_discount]);
		return amouriq_bogo_cart_discounts($deals, $lines, $ctx)['member'];
	}

	public function test_a_larger_coupon_replaces_member_privilege(): void {
		// Member 5% of 1000 = 50; the coupon gives 80.
		$m = $this->with_coupon(80.0, [$this->line(self::SOAP, 2, 500)]);

		$this->assertSame(0.0, $m['discount']);
		$this->assertSame('coupon_larger', $m['reason']);
		$this->assertSame('coupon', $m['winner']);
	}

	public function test_a_smaller_coupon_loses_and_member_privilege_adds_only_the_difference(): void {
		// Member 50, coupon 30: the customer ends with 50 off in total, not 80.
		$m = $this->with_coupon(30.0, [$this->line(self::SOAP, 2, 500)]);

		$this->assertEqualsWithDelta(20.0, $m['discount'], 0.001);
		$this->assertSame('member', $m['winner']);
	}

	public function test_a_tie_goes_to_the_coupon(): void {
		$m = $this->with_coupon(50.0, [$this->line(self::SOAP, 2, 500)]);

		$this->assertSame(0.0, $m['discount']);
		$this->assertSame('coupon', $m['winner']);
	}

	public function test_a_coupon_worth_nothing_does_not_block_member_privilege(): void {
		// e.g. a free-shipping-only coupon.
		$m = $this->with_coupon(0.0, [$this->line(self::SOAP, 2, 500)]);

		$this->assertEqualsWithDelta(50.0, $m['discount'], 0.001);
		$this->assertNull($m['winner']);
	}

	public function test_without_a_coupon_member_privilege_is_unchanged(): void {
		$m = $this->member([$this->line(self::SOAP, 2, 500)]);

		$this->assertEqualsWithDelta(50.0, $m['discount'], 0.001);
		$this->assertNull($m['winner']);
	}

	public function test_a_deal_and_a_coupon_still_stack_and_member_privilege_is_compared_after_the_deal(): void {
		// Soap BOGO: deal 500 on the soap line; candle line member 20. Coupon 15 < 20.
		$lines  = [$this->line(self::SOAP, 2, 500), $this->line(self::CANDLE, 1, 400)];
		$result = amouriq_bogo_cart_discounts(
			[$this->bogo_soap()],
			$lines,
			$this->ctx(['applied_coupons' => ['save'], 'coupon_discount' => 15.0])
		);

		$this->assertSame(500.0, $result['deals'][0]['discount']);
		$this->assertEqualsWithDelta(5.0, $result['member']['discount'], 0.001); // 20 - 15
	}

	public function test_member_privilege_is_not_given_to_a_non_member_whatever_the_coupon(): void {
		$ctx = $this->ctx(['is_member' => false, 'coupon_discount' => 10.0]);
		$m   = amouriq_bogo_cart_discounts([], [$this->line(self::SOAP, 2, 500)], $ctx)['member'];

		$this->assertSame(0.0, $m['discount']);
		$this->assertSame('not_member', $m['reason']);
	}

	public function test_cheapest_free_is_charged_to_the_line_of_the_cheapest_item(): void {
		$deal   = ['type' => 'cheapest_free', 'buy_qty' => 2];
		$lines  = [$this->line(self::SOAP, 1, 500), $this->line(self::OIL, 1, 300)];
		$result = amouriq_bogo_cart_discounts([$deal], $lines, $this->ctx());

		$this->assertSame([1 => 300.0], $result['deals'][0]['per_line']);
		// Oil: deal 300 beats member 15. Soap: member 25.
		$this->assertEqualsWithDelta(25.0, $result['member']['discount'], 0.001);
		$this->assertSame([1], $result['member']['deal_won']);
	}

	public function test_deal_discount_per_line_adds_up_to_the_deal_total(): void {
		$deal   = ['type' => 'same_product', 'product_id' => self::SOAP, 'buy_qty' => 1, 'get_qty' => 1];
		$lines  = [$this->line(self::SOAP, 1, 500), $this->line(self::SOAP, 1, 500)];
		$result = amouriq_bogo_cart_discounts([$deal], $lines, $this->ctx())['deals'][0];

		$this->assertEqualsWithDelta($result['discount'], array_sum($result['per_line']), 0.001);
	}

	protected function tearDown(): void {
		$GLOBALS['amq_test_options'] = [];
	}

	public function test_owner_can_change_the_percent_and_the_minimum(): void {
		$GLOBALS['amq_test_options'] = ['amouriq_member_percent' => '8', 'amouriq_member_min_spend' => '500'];

		$m = $this->member([$this->line(self::SOAP, 2, 500)]);
		$this->assertEqualsWithDelta(80.0, $m['discount'], 0.001); // 8% of 1000

		$below = $this->member([$this->line(self::CANDLE, 1, 400)]);
		$this->assertSame('below_minimum', $below['reason']); // 400 < 500 although above the default 299
	}

	public function test_a_bad_stored_value_falls_back_to_the_default(): void {
		$GLOBALS['amq_test_options'] = ['amouriq_member_percent' => 'abc', 'amouriq_member_min_spend' => '-5'];

		$m = $this->member([$this->line(self::SOAP, 1, 500)]);
		$this->assertEqualsWithDelta(25.0, $m['discount'], 0.001); // 5% default
	}
}
