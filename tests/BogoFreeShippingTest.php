<?php

use PHPUnit\Framework\TestCase;

/**
 * Rule-conditions ticket 06: a free_shipping Deal discounts nothing but says
 * "shipping is free" while it is live: Enabled, in its dates, trigger coupon
 * present, Conditions met, minimum spend reached.
 */
final class BogoFreeShippingTest extends TestCase {
	private function lines($price = 500, $qty = 1) {
		return [['product_id' => 1, 'variation_id' => 0, 'quantity' => $qty, 'on_sale' => false, 'categories' => [], 'unit_price' => $price, 'price_incl_tax' => $price]];
	}

	private function ctx(array $extra = []) {
		return array_merge(['now' => '2026-10-17 12:00', 'today' => '2026-10-17', 'applied_coupons' => [], 'customer_uses' => 0, 'prices' => []], $extra);
	}

	private function free(array $deal, $lines = null, array $ctx = null): bool {
		return amouriq_bogo_core_free_shipping(array_merge(['type' => 'free_shipping', 'trigger' => 'auto'], $deal), $lines ?? $this->lines(), $ctx ?? $this->ctx());
	}

	public function test_a_deal_with_no_limits_is_free_shipping_for_every_cart(): void {
		$this->assertTrue($this->free([]));
	}

	public function test_minimum_spend_is_judged_on_shown_prices_before_discount(): void {
		$this->assertTrue($this->free(['min_spend' => 500]));
		$this->assertFalse($this->free(['min_spend' => 501]));
		$this->assertTrue($this->free(['min_spend' => 1000], $this->lines(250, 4)));
	}

	public function test_it_is_not_free_shipping_unless_the_deal_is_a_free_shipping_deal(): void {
		$this->assertFalse(amouriq_bogo_core_free_shipping(['type' => 'spend_threshold', 'trigger' => 'auto', 'min_spend' => 1, 'mode' => 'percent', 'value' => 10], $this->lines(), $this->ctx()));
	}

	public function test_a_disabled_deal_or_one_outside_its_dates_is_not_live(): void {
		$this->assertFalse($this->free(['enabled' => false]));
		$this->assertFalse($this->free(['valid_to' => '2026-10-16']));
		$this->assertFalse($this->free(['valid_from' => '2026-10-18']));
		$this->assertTrue($this->free(['valid_from' => '2026-10-17', 'valid_to' => '2026-10-17']));
	}

	public function test_a_coupon_triggered_deal_needs_its_coupon_in_the_cart(): void {
		$deal = ['trigger' => 'coupon', 'coupon_code' => 'SHIPFREE'];
		$this->assertFalse($this->free($deal));
		$this->assertTrue($this->free($deal, null, $this->ctx(['applied_coupons' => ['shipfree']])));
	}

	public function test_conditions_decide_and_unknown_does_not_count(): void {
		$weekend = ['conditions' => ['match' => 'all', 'rows' => [['type' => 'weekday', 'op' => 'in', 'value' => [6, 7]]]]];
		$this->assertTrue($this->free($weekend));                                                  // 17 Oct 2026 is a Saturday
		$this->assertFalse($this->free($weekend, null, $this->ctx(['now' => '2026-10-19 12:00']))); // Monday

		$history = ['conditions' => ['match' => 'all', 'rows' => [['type' => 'orders_count', 'op' => 'gte', 'value' => 1]]]];
		$this->assertFalse($this->free($history));                                                  // customer not known yet
		$this->assertTrue($this->free($history, null, $this->ctx(['orders_count' => 2])));
	}

	public function test_new_customers_only_and_the_customer_limit_apply_too(): void {
		$this->assertFalse($this->free(['new_customer_only' => true], null, $this->ctx(['is_new_customer' => false])));
		$this->assertTrue($this->free(['new_customer_only' => true], null, $this->ctx(['is_new_customer' => true])));
		$this->assertFalse($this->free(['max_uses_per_customer' => 1], null, $this->ctx(['customer_uses' => 1])));
	}

	public function test_it_never_discounts_anything(): void {
		$r = amouriq_bogo_cart_discounts([['type' => 'free_shipping', 'trigger' => 'auto']], $this->lines(), $this->ctx())['deals'][0];
		$this->assertSame(0.0, $r['discount']);
		$this->assertNull($r['reason']);
	}

	public function test_the_cart_says_how_much_more_to_spend(): void {
		$r = amouriq_bogo_cart_discounts([['type' => 'free_shipping', 'trigger' => 'auto', 'min_spend' => 799]], $this->lines(), $this->ctx())['deals'][0];
		$this->assertSame(['kind' => 'spend', 'remaining' => 299.0], $r['hint']);
	}
}
