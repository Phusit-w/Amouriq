<?php

use PHPUnit\Framework\TestCase;

/**
 * Rule-conditions ticket 05: fixed price per item, bundle price, baht off per
 * item. All work over a Product scope, return a per-line split, and never
 * charge more than the shelf price. Amounts are worked out by hand.
 */
final class BogoPriceTypesTest extends TestCase {
	private function line($pid, $qty, $price, array $extra = []) {
		return array_merge([
			'product_id'     => $pid,
			'variation_id'   => 0,
			'quantity'       => $qty,
			'on_sale'        => false,
			'categories'     => ['soap'],
			'unit_price'     => $price,
			'price_incl_tax' => $price, // tax-free unless a test says otherwise
		], $extra);
	}

	private function ctx(array $extra = []) {
		return array_merge(['now' => '2026-10-12 12:00', 'today' => '2026-10-12', 'applied_coupons' => [], 'customer_uses' => 0, 'prices' => [], 'is_member' => false], $extra);
	}

	private function price(array $deal, array $lines, array $ctx = null) {
		return amouriq_bogo_cart_discounts([$deal], $lines, $ctx ?? $this->ctx())['deals'][0];
	}

	public function test_fixed_price_per_item_brings_each_dearer_item_down_to_the_price(): void {
		$deal = ['type' => 'fixed_price_item', 'price' => 299];
		$r    = $this->price($deal, [$this->line(1, 2, 400), $this->line(2, 1, 250)]);

		$this->assertEqualsWithDelta(202.0, $r['discount'], 0.001); // 2 x (400-299); the 250 item stays
		$this->assertEqualsWithDelta(202.0, $r['per_line'][0], 0.001);
		$this->assertArrayNotHasKey(1, $r['per_line']);
	}

	public function test_a_fixed_price_above_the_shelf_price_never_adds_a_charge(): void {
		$r = $this->price(['type' => 'fixed_price_item', 'price' => 900], [$this->line(1, 3, 400)]);
		$this->assertSame(0.0, $r['discount']);
	}

	public function test_amount_off_each_item_is_capped_at_the_item_price(): void {
		$r = $this->price(['type' => 'amount_off_item', 'value' => 50], [$this->line(1, 2, 400), $this->line(2, 3, 30)]);

		$this->assertEqualsWithDelta(100.0 + 90.0, $r['discount'], 0.001); // 2 x 50, 3 x 30 (cannot go below free)
		$this->assertEqualsWithDelta(100.0, $r['per_line'][0], 0.001);
		$this->assertEqualsWithDelta(90.0, $r['per_line'][1], 0.001);
	}

	public function test_prices_are_what_the_shopper_sees_and_the_discount_is_tax_exclusive(): void {
		// 400 ex tax = 428 shown. Fixed price 321 shown = 107 off shown = 100 ex tax per item.
		$r = $this->price(['type' => 'fixed_price_item', 'price' => 321], [$this->line(1, 2, 400, ['price_incl_tax' => 428.0])]);
		$this->assertEqualsWithDelta(200.0, $r['discount'], 0.001);
	}

	public function test_bundle_price_takes_the_dearest_items_into_each_complete_bundle(): void {
		// 5 pieces, bundles of 3 for 999: the dearest three are 500+500+400 = 1400 -> 401 off. 400 and 300 stay.
		$r = $this->price(['type' => 'bundle_price', 'bundle_qty' => 3, 'price' => 999], [$this->line(1, 2, 500), $this->line(2, 2, 400), $this->line(3, 1, 300)]);

		$this->assertEqualsWithDelta(401.0, $r['discount'], 0.001);
		$this->assertEqualsWithDelta(array_sum($r['per_line']), $r['discount'], 0.001);
		$this->assertEqualsWithDelta(401 * 1000 / 1400, $r['per_line'][0], 0.001);
		$this->assertEqualsWithDelta(401 * 400 / 1400, $r['per_line'][1], 0.001);
		$this->assertArrayNotHasKey(2, $r['per_line']);
	}

	public function test_a_cart_of_2n_plus_one_makes_two_bundles_and_leaves_one_item_full_price(): void {
		// Bundles of 2 for 700 over 500,500,400,400,300: (1000 -> 300 off) + (800 -> 100 off); the 300 stays.
		$r = $this->price(['type' => 'bundle_price', 'bundle_qty' => 2, 'price' => 700], [$this->line(1, 2, 500), $this->line(2, 2, 400), $this->line(3, 1, 300)]);

		$this->assertEqualsWithDelta(400.0, $r['discount'], 0.001);
		$this->assertEqualsWithDelta(300.0, $r['per_line'][0], 0.001);
		$this->assertEqualsWithDelta(100.0, $r['per_line'][1], 0.001);
	}

	public function test_too_few_items_or_a_bundle_price_above_the_items_gives_nothing(): void {
		$this->assertSame(0.0, $this->price(['type' => 'bundle_price', 'bundle_qty' => 3, 'price' => 999], [$this->line(1, 2, 500)])['discount']);
		$this->assertSame(0.0, $this->price(['type' => 'bundle_price', 'bundle_qty' => 2, 'price' => 5000], [$this->line(1, 2, 500)])['discount']);
	}

	public function test_max_per_order_limits_how_many_bundles(): void {
		$r = $this->price(['type' => 'bundle_price', 'bundle_qty' => 2, 'price' => 700, 'max_per_order' => 1], [$this->line(1, 2, 500), $this->line(2, 2, 400)]);
		$this->assertEqualsWithDelta(300.0, $r['discount'], 0.001); // only the first bundle
	}

	public function test_only_items_in_the_scope_are_priced(): void {
		$deal  = ['type' => 'fixed_price_item', 'price' => 300, 'scope' => ['include' => ['categories' => ['soap']]]];
		$lines = [$this->line(1, 1, 500), $this->line(2, 1, 500, ['categories' => ['candle']])];
		$r     = $this->price($deal, $lines);

		$this->assertEqualsWithDelta(200.0, $r['discount'], 0.001);
		$this->assertSame([0], array_keys($r['per_line']));
	}

	public function test_sale_items_are_skipped_when_the_deal_says_so(): void {
		$deal = ['type' => 'amount_off_item', 'value' => 50, 'exclude_sale' => true];
		$r    = $this->price($deal, [$this->line(1, 1, 400, ['on_sale' => true]), $this->line(2, 1, 400)]);
		$this->assertEqualsWithDelta(50.0, $r['discount'], 0.001);
	}

	public function test_member_privilege_takes_the_larger_per_line_next_to_these_deals(): void {
		// Fixed price 450 on a 500 item = 50 off, which is 10%, more than the member 5% (25): the Deal wins that line.
		$deal = ['type' => 'fixed_price_item', 'price' => 450];
		$out  = amouriq_bogo_cart_discounts([$deal], [$this->line(1, 1, 500), $this->line(2, 1, 400, ['categories' => ['candle']])], $this->ctx(['is_member' => true]));

		$this->assertEqualsWithDelta(50.0, $out['deals'][0]['discount'], 0.001);
		$this->assertSame([0], $out['member']['deal_won']);
		$this->assertEqualsWithDelta(20.0, $out['member']['discount'], 0.001); // the 400 item has no Deal discount, so it still gets 5%
	}
}
