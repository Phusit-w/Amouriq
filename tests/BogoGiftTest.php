<?php

use PHPUnit\Framework\TestCase;

/**
 * Ticket 08 (.scratch/promotions-rework/issues/08-spend-threshold-gift-deal.md):
 * the spend-threshold gift Deal. A gift is an ordinary cart line at 0 baht flagged
 * `is_gift` with its Deal id in `gift_deal`; the core says how many gifts the
 * customer is entitled to, how many choices are left, and which gift lines must go.
 */
final class BogoGiftTest extends TestCase {
	private const SOAP = 100;
	private const OIL  = 200;
	private const G1   = 31;
	private const G2   = 32;

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

	private function gift($id, $deal_id = 'g1') {
		return $this->line($id, 1, 0, ['is_gift' => true, 'gift_deal' => $deal_id, 'price_incl_tax' => 0.0]);
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
		return array_merge(['id' => 'g1', 'type' => 'spend_gift', 'min_spend' => 1500, 'gift_ids' => [self::G1, self::G2], 'gift_qty' => 1], $extra);
	}

	private function cart(array $deal, array $lines, array $ctx = null) {
		return amouriq_bogo_cart_discounts([$deal], $lines, $ctx ?? $this->ctx());
	}

	private function deal_result(array $deal, array $lines, array $ctx = null) {
		return $this->cart($deal, $lines, $ctx)['deals'][0];
	}

	public function test_below_the_minimum_there_is_no_entitlement(): void {
		// 1000 ex tax = 1070 shown.
		$r = $this->deal_result($this->deal(), [$this->line(self::SOAP, 1, 1000)]);

		$this->assertSame(0, $r['allowed']);
		$this->assertSame(0, $r['choices_left']);
	}

	public function test_at_the_minimum_the_customer_may_choose_the_gift_quantity(): void {
		$lines = [$this->line(self::SOAP, 1, 1500, ['price_incl_tax' => 1500.0])];
		$r     = $this->deal_result($this->deal(['gift_qty' => 2]), $lines);

		$this->assertSame(2, $r['allowed']);
		$this->assertSame(2, $r['choices_left']);

		$lines = [$this->line(self::SOAP, 1, 1500, ['price_incl_tax' => 1499.99])];
		$this->assertSame(0, $this->deal_result($this->deal(), $lines)['allowed']);
	}

	public function test_a_gift_in_the_cart_uses_up_a_choice(): void {
		$lines = [$this->line(self::SOAP, 2, 1000), $this->gift(self::G1)];
		$r     = $this->deal_result($this->deal(['gift_qty' => 2]), $lines);

		$this->assertSame(2, $r['allowed']);
		$this->assertSame(1, $r['choices_left']);
	}

	public function test_gifts_do_not_count_toward_the_minimum(): void {
		// A gift line carrying a nonzero price must still not count.
		$gift  = $this->gift(self::G1);
		$gift['price_incl_tax'] = 800.0;
		$gift['unit_price']     = 800.0;
		$lines = [$this->line(self::SOAP, 1, 1000), $gift];

		$this->assertSame(0, $this->deal_result($this->deal(), $lines)['allowed']);
	}

	public function test_sale_items_count_toward_the_minimum(): void {
		$lines = [$this->line(self::SOAP, 2, 1000, ['on_sale' => true])];

		$this->assertSame(1, $this->deal_result($this->deal(), $lines)['allowed']);
	}

	public function test_a_gift_of_another_deal_does_not_use_this_deals_choices(): void {
		$lines = [$this->line(self::SOAP, 2, 1000), $this->gift(self::G1, 'other')];

		$this->assertSame(1, $this->deal_result($this->deal(), $lines)['choices_left']);
	}

	public function test_gifts_do_not_count_toward_other_deals_quantities(): void {
		// Mix and match over G1: the paid piece alone is below 2 pieces, the free gift must not make it two.
		$tier  = ['id' => 't1', 'type' => 'quantity_tier', 'product_ids' => [self::G1], 'tiers' => [['min' => 2, 'percent' => 10]]];
		$lines = [$this->line(self::G1, 1, 500), $this->gift(self::G1)];

		$this->assertSame(0.0, $this->cart($tier, $lines)['deals'][0]['discount']);
	}

	public function test_gifts_do_not_trigger_a_cheapest_free_deal(): void {
		$deal  = ['id' => 'c1', 'type' => 'cheapest_free', 'buy_qty' => 2];
		$lines = [$this->line(self::SOAP, 1, 500), $this->gift(self::G1)];

		$this->assertSame(0.0, $this->cart($deal, $lines)['deals'][0]['discount']);
	}

	public function test_member_privilege_still_applies_to_paid_items_alongside_a_gift(): void {
		$lines  = [$this->line(self::SOAP, 2, 1000), $this->gift(self::G1)];
		$result = $this->cart($this->deal(), $lines, $this->ctx(['is_member' => true]));

		$this->assertEqualsWithDelta(100.0, $result['member']['discount'], 0.001); // 5% of 2000
		$this->assertSame(1, $result['deals'][0]['allowed']);
	}

	public function test_a_gift_does_not_decide_the_member_minimum(): void {
		$gift   = $this->gift(self::G1);
		$gift['price_incl_tax'] = 500.0;
		$gift['unit_price']     = 500.0;
		$result = $this->cart($this->deal(), [$this->line(self::SOAP, 1, 100), $gift], $this->ctx(['is_member' => true]));

		$this->assertSame('below_minimum', $result['member']['reason']); // 107 shown, the gift's 500 is ignored
	}

	public function test_a_switched_off_or_out_of_window_deal_entitles_nothing(): void {
		$lines = [$this->line(self::SOAP, 2, 1000)];

		$off = $this->deal_result($this->deal(['enabled' => false]), $lines);
		$this->assertSame(0, $off['allowed']);
		$this->assertSame('disabled', $off['reason']);

		$late = $this->deal_result($this->deal(['valid_to' => '2026-10-11 09:00']), $lines);
		$this->assertSame(0, $late['allowed']);
		$this->assertSame('outside_window', $late['reason']);
	}

	// --- which gift lines must be removed -------------------------------

	public function test_gift_lines_beyond_the_allowance_are_removed(): void {
		$lines = [$this->line(self::SOAP, 1, 1000), $this->gift(self::G1), $this->gift(self::G2)];

		$this->assertSame([2], amouriq_bogo_core_gifts_to_remove($lines, ['g1' => 1]));
	}

	public function test_all_gifts_go_when_the_deal_no_longer_entitles(): void {
		$lines = [$this->line(self::SOAP, 1, 100), $this->gift(self::G1), $this->gift(self::G2, 'gone')];

		$this->assertSame([1, 2], amouriq_bogo_core_gifts_to_remove($lines, ['g1' => 0]));
	}

	public function test_gifts_within_the_allowance_stay(): void {
		$lines = [$this->line(self::SOAP, 2, 1000), $this->gift(self::G1)];

		$this->assertSame([], amouriq_bogo_core_gifts_to_remove($lines, ['g1' => 1]));
	}

	public function test_a_gift_quantity_above_one_is_not_split_but_trimmed_by_the_cart_layer(): void {
		// Quantity is locked to 1 by the cart layer; the core counts whatever quantity it is told.
		$gift  = $this->gift(self::G1);
		$gift['quantity'] = 2;
		$lines = [$this->line(self::SOAP, 2, 1000), $gift];

		$this->assertSame(0, $this->deal_result($this->deal(), $lines)['choices_left']);
	}

	// --- the Gift choice box (tickets 04, 05) ----------------------------

	private function candidate($id, $parent, $name, $price, array $extra = []) {
		return array_merge(['id' => $id, 'parent_id' => $parent, 'name' => $name, 'option' => '', 'regular_price' => $price, 'in_stock' => true], $extra);
	}

	public function test_a_simple_gift_shows_its_regular_price(): void {
		$groups = amouriq_bogo_core_gift_choices([$this->candidate(31, 31, 'สบู่', 120.0)], []);

		$this->assertCount(1, $groups);
		$this->assertSame('สบู่', $groups[0]['name']);
		$this->assertSame(120.0, $groups[0]['options'][0]['regular_price']);
		$this->assertTrue($groups[0]['options'][0]['available']);
		$this->assertTrue($groups[0]['available']);
	}

	public function test_options_of_one_product_share_a_group_each_with_its_own_price(): void {
		$groups = amouriq_bogo_core_gift_choices([
			$this->candidate(41, 40, 'ครีม', 200.0, ['option' => '50 มล.']),
			$this->candidate(42, 40, 'ครีม', 350.0, ['option' => '100 มล.']),
		], []);

		$this->assertCount(1, $groups);
		$this->assertSame(40, $groups[0]['product_id']);
		$this->assertSame(['50 มล.', '100 มล.'], array_column($groups[0]['options'], 'option'));
		$this->assertSame([200.0, 350.0], array_column($groups[0]['options'], 'regular_price'));
	}

	public function test_a_sold_out_option_is_unavailable_but_the_group_is_not(): void {
		$groups = amouriq_bogo_core_gift_choices([
			$this->candidate(41, 40, 'ครีม', 200.0, ['option' => 'S', 'in_stock' => false]),
			$this->candidate(42, 40, 'ครีม', 350.0, ['option' => 'L']),
		], []);

		$this->assertFalse($groups[0]['options'][0]['available']);
		$this->assertTrue($groups[0]['options'][1]['available']);
		$this->assertTrue($groups[0]['available']);
	}

	public function test_a_group_with_every_option_sold_out_is_unavailable(): void {
		$groups = amouriq_bogo_core_gift_choices([$this->candidate(31, 31, 'สบู่', 120.0, ['in_stock' => false])], []);

		$this->assertFalse($groups[0]['available']);
	}

	public function test_a_gift_already_picked_is_marked_picked_and_not_available(): void {
		$groups = amouriq_bogo_core_gift_choices([$this->candidate(31, 31, 'สบู่', 120.0), $this->candidate(32, 32, 'น้ำมัน', 90.0)], [31]);

		$this->assertTrue($groups[0]['options'][0]['picked']);
		$this->assertFalse($groups[0]['options'][0]['available']);
		$this->assertFalse($groups[1]['options'][0]['picked']);
		$this->assertTrue($groups[1]['options'][0]['available']);
	}

	public function test_no_candidates_gives_no_groups(): void {
		$this->assertSame([], amouriq_bogo_core_gift_choices([], []));
	}

	public function test_each_option_carries_its_picture(): void {
		$groups = amouriq_bogo_core_gift_choices([
			$this->candidate(41, 40, 'ครีม', 200.0, ['option' => 'S', 'image' => 'https://example.test/s.jpg']),
			$this->candidate(42, 40, 'ครีม', 350.0, ['option' => 'L']),
		], []);

		$this->assertSame('https://example.test/s.jpg', $groups[0]['options'][0]['image']);
		$this->assertSame('', $groups[0]['options'][1]['image']);
	}
}
