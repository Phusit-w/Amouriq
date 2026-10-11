<?php

use PHPUnit\Framework\TestCase;

/**
 * A Deal marked "new customers only" (automatic first-order discount, no coupon
 * code needed) applies only while the customer has no counted order.
 */
final class BogoNewCustomerDealTest extends TestCase {
	private function lines() {
		return [[
			'product_id'     => 100,
			'variation_id'   => 0,
			'quantity'       => 2,
			'on_sale'        => false,
			'categories'     => [],
			'unit_price'     => 500,
			'price_incl_tax' => 535.0,
		]];
	}

	private function discount(array $deal_extra, array $ctx_extra): array {
		$deal = array_merge(['type' => 'spend_threshold', 'min_spend' => 500, 'mode' => 'percent', 'value' => 10], $deal_extra);
		$ctx  = array_merge([
			'now'             => '2026-10-11 12:00',
			'today'           => '2026-10-11',
			'applied_coupons' => [],
			'customer_uses'   => 0,
			'prices'          => [],
			'is_member'       => false,
		], $ctx_extra);
		return amouriq_bogo_cart_discounts([$deal], $this->lines(), $ctx)['deals'][0];
	}

	public function test_applies_to_a_new_customer(): void {
		$r = $this->discount(['new_customer_only' => true], ['is_new_customer' => true]);
		$this->assertEqualsWithDelta(100.0, $r['discount'], 0.001);
	}

	public function test_stopped_for_a_returning_customer(): void {
		$r = $this->discount(['new_customer_only' => true], ['is_new_customer' => false]);
		$this->assertSame(0.0, $r['discount']);
		$this->assertSame('not_new_customer', $r['reason']);
	}

	public function test_the_lookup_is_a_callable_and_only_runs_for_a_marked_deal(): void {
		$calls = 0;
		$ask   = function () use (&$calls) {
			$calls++;
			return false;
		};
		$this->discount([], ['is_new_customer' => $ask]);
		$this->assertSame(0, $calls);
		$this->discount(['new_customer_only' => true], ['is_new_customer' => $ask]);
		$this->assertSame(1, $calls);
	}

	public function test_a_customer_not_yet_known_counts_as_new(): void {
		$r = $this->discount(['new_customer_only' => true], []);
		$this->assertEqualsWithDelta(100.0, $r['discount'], 0.001);
	}

	public function test_an_unmarked_deal_ignores_new_customer_status(): void {
		$r = $this->discount([], ['is_new_customer' => false]);
		$this->assertEqualsWithDelta(100.0, $r['discount'], 0.001);
	}
}
