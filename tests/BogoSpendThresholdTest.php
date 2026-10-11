<?php

use PHPUnit\Framework\TestCase;

/**
 * Ticket 05 (.scratch/promotions-rework/issues/05-spend-threshold-discount-deal.md):
 * the spend-threshold discount Deal, observed through amouriq_bogo_cart_discounts().
 * Expected amounts are worked out by hand.
 */
final class BogoSpendThresholdTest extends TestCase {
	private const SOAP   = 100;
	private const CANDLE = 200;
	private const OIL    = 300;

	/** Prices are tax-exclusive; price_incl_tax is what the shopper sees (7% VAT). */
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
			'now'             => '2026-10-11 12:00',
			'today'           => '2026-10-11',
			'applied_coupons' => [],
			'customer_uses'   => 0,
			'prices'          => [],
			'is_member'       => false,
		], $extra);
	}

	private function deal(array $extra = []) {
		return array_merge(['type' => 'spend_threshold', 'min_spend' => 1000, 'mode' => 'percent', 'value' => 10], $extra);
	}

	private function cart(array $deal, array $lines, array $ctx = null) {
		return amouriq_bogo_cart_discounts([$deal], $lines, $ctx ?? $this->ctx());
	}

	private function discount(array $deal, array $lines, array $ctx = null) {
		return $this->cart($deal, $lines, $ctx)['deals'][0]['discount'];
	}

	public function test_percent_off_all_items_once_the_minimum_is_met(): void {
		// 2 x 500 ex tax = 1070 as shown, which meets 1000. 10% of 1000 ex tax.
		$this->assertEqualsWithDelta(100.0, $this->discount($this->deal(), [$this->line(self::SOAP, 2, 500)]), 0.001);
	}

	public function test_nothing_below_the_minimum(): void {
		// 1 x 500 = 535 shown.
		$this->assertSame(0.0, $this->discount($this->deal(), [$this->line(self::SOAP, 1, 500)]));
	}

	public function test_the_minimum_is_met_exactly_at_the_threshold(): void {
		$lines = [$this->line(self::SOAP, 1, 1000, ['price_incl_tax' => 1000.0])];
		$this->assertEqualsWithDelta(100.0, $this->discount($this->deal(), $lines), 0.001);

		$lines = [$this->line(self::SOAP, 1, 1000, ['price_incl_tax' => 999.99])];
		$this->assertSame(0.0, $this->discount($this->deal(), $lines));
	}

	public function test_the_minimum_counts_every_item_but_only_ticked_items_are_discounted(): void {
		// Soap 600 + candle 600 = 1284 shown; only the soap (ticked) is discounted: 10% of 600.
		$deal  = $this->deal(['product_ids' => [self::SOAP]]);
		$lines = [$this->line(self::SOAP, 1, 600), $this->line(self::CANDLE, 1, 600)];

		$this->assertEqualsWithDelta(60.0, $this->discount($deal, $lines), 0.001);
	}

	public function test_excluded_products_are_not_discounted_but_still_count_toward_the_minimum(): void {
		$deal  = $this->deal(['excluded_ids' => [self::CANDLE]]);
		$lines = [$this->line(self::SOAP, 1, 500), $this->line(self::CANDLE, 1, 500)];

		$this->assertEqualsWithDelta(50.0, $this->discount($deal, $lines), 0.001);
	}

	public function test_a_ticked_variation_matches_its_variation_id(): void {
		$deal  = $this->deal(['min_spend' => 100, 'product_ids' => [555]]);
		$lines = [$this->line(self::SOAP, 1, 500, ['variation_id' => 555]), $this->line(self::SOAP, 1, 500, ['variation_id' => 556])];

		$this->assertEqualsWithDelta(50.0, $this->discount($deal, $lines), 0.001);
	}

	public function test_sale_items_are_skipped_when_the_deal_skips_them_but_count_toward_the_minimum(): void {
		$deal  = $this->deal(['exclude_sale' => true]);
		$lines = [$this->line(self::SOAP, 1, 500), $this->line(self::CANDLE, 1, 500, ['on_sale' => true])];

		$this->assertEqualsWithDelta(50.0, $this->discount($deal, $lines), 0.001);
	}

	public function test_sale_items_are_discounted_when_the_deal_does_not_skip_them(): void {
		$lines = [$this->line(self::SOAP, 1, 500), $this->line(self::CANDLE, 1, 500, ['on_sale' => true])];

		$this->assertEqualsWithDelta(100.0, $this->discount($this->deal(), $lines), 0.001);
	}

	public function test_a_baht_discount_is_taken_as_shown_to_the_shopper(): void {
		// 100 baht off a 1070 shown cart: ex tax 100 / 1.07 = 93.457, so WooCommerce's own tax gross-up lands on 100.
		$deal = $this->deal(['mode' => 'baht', 'value' => 100]);

		$this->assertEqualsWithDelta(100 / 1.07, $this->discount($deal, [$this->line(self::SOAP, 2, 500)]), 0.001);
	}

	public function test_a_baht_discount_never_exceeds_the_eligible_items(): void {
		// 500 baht off, but only the 100 baht soap is eligible.
		$deal  = $this->deal(['mode' => 'baht', 'value' => 500, 'min_spend' => 100, 'product_ids' => [self::SOAP]]);
		$lines = [$this->line(self::SOAP, 1, 100), $this->line(self::CANDLE, 1, 900)];

		$this->assertEqualsWithDelta(100.0, $this->discount($deal, $lines), 0.001);
	}

	public function test_the_discount_is_split_over_the_eligible_lines(): void {
		$deal   = $this->deal(['excluded_ids' => [self::CANDLE]]);
		$lines  = [$this->line(self::SOAP, 1, 300), $this->line(self::OIL, 1, 700), $this->line(self::CANDLE, 1, 500)];
		$result = $this->cart($deal, $lines)['deals'][0];

		$this->assertEqualsWithDelta(30.0, $result['per_line'][0], 0.001);
		$this->assertEqualsWithDelta(70.0, $result['per_line'][1], 0.001);
		$this->assertArrayNotHasKey(2, $result['per_line']);
	}

	public function test_the_deal_does_not_add_to_member_privilege_the_larger_wins_per_line(): void {
		// Deal 2% of 1000 = 20 vs member 5% = 50 on the same line: member adds the 30 difference.
		$deal   = $this->deal(['value' => 2]);
		$result = $this->cart($deal, [$this->line(self::SOAP, 2, 500)], $this->ctx(['is_member' => true]));

		$this->assertEqualsWithDelta(20.0, $result['deals'][0]['discount'], 0.001);
		$this->assertEqualsWithDelta(30.0, $result['member']['discount'], 0.001);

		// A 10% deal beats member on that line: member adds nothing.
		$result = $this->cart($this->deal(), [$this->line(self::SOAP, 2, 500)], $this->ctx(['is_member' => true]));
		$this->assertSame(0.0, $result['member']['discount']);
	}

	// --- window and switch ----------------------------------------------

	public function test_it_is_off_before_its_start_time_and_after_its_end_time(): void {
		$lines = [$this->line(self::SOAP, 2, 500)];
		$deal  = $this->deal(['valid_from' => '2026-10-11 13:00', 'valid_to' => '2026-10-11 18:30']);

		$this->assertSame('outside_window', $this->cart($deal, $lines, $this->ctx(['now' => '2026-10-11 12:59']))['deals'][0]['reason']);
		$this->assertEqualsWithDelta(100.0, $this->discount($deal, $lines, $this->ctx(['now' => '2026-10-11 13:00'])), 0.001);
		$this->assertEqualsWithDelta(100.0, $this->discount($deal, $lines, $this->ctx(['now' => '2026-10-11 18:30'])), 0.001);
		$this->assertSame('outside_window', $this->cart($deal, $lines, $this->ctx(['now' => '2026-10-11 18:31']))['deals'][0]['reason']);
	}

	public function test_a_date_without_a_time_covers_the_whole_day(): void {
		$lines = [$this->line(self::SOAP, 2, 500)];
		$deal  = $this->deal(['valid_from' => '2026-10-11', 'valid_to' => '2026-10-11']);

		$this->assertGreaterThan(0, $this->discount($deal, $lines, $this->ctx(['now' => '2026-10-11 00:00'])));
		$this->assertGreaterThan(0, $this->discount($deal, $lines, $this->ctx(['now' => '2026-10-11 23:59'])));
		$this->assertSame(0.0, $this->discount($deal, $lines, $this->ctx(['now' => '2026-10-12 00:00'])));
	}

	public function test_a_switched_off_deal_gives_nothing(): void {
		$result = $this->cart($this->deal(['enabled' => false]), [$this->line(self::SOAP, 2, 500)])['deals'][0];

		$this->assertSame(0.0, $result['discount']);
		$this->assertSame('disabled', $result['reason']);
	}
}
