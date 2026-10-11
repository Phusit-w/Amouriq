<?php

use PHPUnit\Framework\TestCase;

/**
 * Rule-conditions ticket 08, part 3: an Exclusive Deal keeps later Deals in the
 * list off the lines it discounted. Member Privilege is not affected (ADR-0006).
 */
final class BogoExclusiveTest extends TestCase {
	private function line($pid, $qty, $price, array $cats) {
		return ['product_id' => $pid, 'variation_id' => 0, 'quantity' => $qty, 'on_sale' => false, 'categories' => $cats, 'unit_price' => $price, 'price_incl_tax' => $price];
	}

	private function ctx(array $extra = []) {
		return array_merge(['now' => '2026-10-12 12:00', 'today' => '2026-10-12', 'applied_coupons' => [], 'customer_uses' => 0, 'prices' => [], 'is_member' => false], $extra);
	}

	private function soapDeal(array $extra = []) {
		return array_merge(['type' => 'spend_threshold', 'min_spend' => 100, 'mode' => 'percent', 'value' => 20, 'scope' => ['include' => ['categories' => ['soap']]]], $extra);
	}

	private function everythingDeal(array $extra = []) {
		return array_merge(['type' => 'spend_threshold', 'min_spend' => 100, 'mode' => 'percent', 'value' => 10], $extra);
	}

	private function lines() {
		return [$this->line(1, 1, 500, ['soap']), $this->line(2, 1, 300, ['candle'])];
	}

	public function test_without_exclusive_deals_combine_as_before(): void {
		$r = amouriq_bogo_cart_discounts([$this->soapDeal(), $this->everythingDeal()], $this->lines(), $this->ctx())['deals'];

		$this->assertEqualsWithDelta(100.0, $r[0]['discount'], 0.001); // 20% of 500
		$this->assertEqualsWithDelta(80.0, $r[1]['discount'], 0.001);  // 10% of 800
	}

	public function test_an_exclusive_deal_keeps_later_deals_off_its_lines(): void {
		$r = amouriq_bogo_cart_discounts([$this->soapDeal(['exclusive' => true]), $this->everythingDeal()], $this->lines(), $this->ctx())['deals'];

		$this->assertEqualsWithDelta(100.0, $r[0]['discount'], 0.001);
		$this->assertEqualsWithDelta(30.0, $r[1]['discount'], 0.001); // 10% of the candle only
		$this->assertSame([1], array_keys($r[1]['per_line']));
	}

	public function test_exclusive_only_blocks_deals_after_it_in_the_list(): void {
		$r = amouriq_bogo_cart_discounts([$this->everythingDeal(), $this->soapDeal(['exclusive' => true])], $this->lines(), $this->ctx())['deals'];

		$this->assertEqualsWithDelta(80.0, $r[0]['discount'], 0.001); // ran first, on everything
		$this->assertEqualsWithDelta(100.0, $r[1]['discount'], 0.001);
	}

	public function test_an_exclusive_deal_that_gave_nothing_blocks_nothing(): void {
		$exclusive = $this->soapDeal(['exclusive' => true, 'min_spend' => 99999]);
		$r         = amouriq_bogo_cart_discounts([$exclusive, $this->everythingDeal()], $this->lines(), $this->ctx())['deals'];

		$this->assertSame(0.0, $r[0]['discount']);
		$this->assertEqualsWithDelta(80.0, $r[1]['discount'], 0.001);
	}

	public function test_member_privilege_still_compares_against_the_exclusive_deals_discount(): void {
		$out = amouriq_bogo_cart_discounts([$this->soapDeal(['exclusive' => true])], $this->lines(), $this->ctx(['is_member' => true]));

		// Soap: Deal 20% (100) beats member 5% (25). Candle: no Deal, member 5% of 300 = 15.
		$this->assertSame([0], $out['member']['deal_won']);
		$this->assertEqualsWithDelta(15.0, $out['member']['discount'], 0.001);
	}
}
