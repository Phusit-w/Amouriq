<?php

use PHPUnit\Framework\TestCase;

/**
 * Story 24 (.scratch/promotions-rework/spec.md): the cart says why a Deal did not
 * apply yet. The core returns a `hint` for a live Deal that is not met: how much
 * more to spend, or how many more pieces to add for the next tier.
 */
final class BogoHintTest extends TestCase {
	private function line($id, $qty, $price, array $extra = []) {
		return array_merge([
			'product_id'     => $id,
			'variation_id'   => 0,
			'quantity'       => $qty,
			'on_sale'        => false,
			'categories'     => [],
			'unit_price'     => $price,
			'price_incl_tax' => $price,
		], $extra);
	}

	private function ctx(array $extra = []) {
		return array_merge(['now' => '2026-10-11 12:00', 'today' => '2026-10-11', 'applied_coupons' => [], 'customer_uses' => 0, 'prices' => [], 'is_member' => false], $extra);
	}

	private function hint(array $deal, array $lines, array $ctx = null) {
		return amouriq_bogo_cart_discounts([$deal], $lines, $ctx ?? $this->ctx())['deals'][0]['hint'];
	}

	public function test_a_spend_threshold_deal_says_how_much_more_to_spend(): void {
		$deal = ['id' => 'a', 'type' => 'spend_threshold', 'min_spend' => 1000, 'mode' => 'percent', 'value' => 10];

		$this->assertSame(['kind' => 'spend', 'remaining' => 400.0], $this->hint($deal, [$this->line(1, 1, 600)]));
	}

	public function test_no_hint_once_the_threshold_is_met(): void {
		$deal = ['id' => 'a', 'type' => 'spend_threshold', 'min_spend' => 1000, 'mode' => 'percent', 'value' => 10];

		$this->assertNull($this->hint($deal, [$this->line(1, 1, 1000)]));
	}

	public function test_a_gift_deal_says_how_much_more_to_spend_and_ignores_gifts_already_in_the_cart(): void {
		$deal = ['id' => 'g', 'type' => 'spend_gift', 'min_spend' => 1500, 'gift_ids' => [9], 'gift_qty' => 1];
		$gift = $this->line(9, 1, 0, ['is_gift' => true, 'gift_deal' => 'g']);

		$this->assertSame(['kind' => 'spend', 'remaining' => 500.0], $this->hint($deal, [$this->line(1, 1, 1000), $gift]));
	}

	public function test_a_mix_and_match_deal_says_how_many_more_pieces_reach_the_next_tier(): void {
		$deal = ['id' => 't', 'type' => 'quantity_tier', 'product_ids' => [1, 2, 3], 'tiers' => [['min' => 2, 'percent' => 8], ['min' => 3, 'percent' => 10]]];

		$this->assertSame(['kind' => 'pieces', 'remaining' => 1, 'percent' => 8.0], $this->hint($deal, [$this->line(1, 1, 700)]));
		$this->assertSame(['kind' => 'pieces', 'remaining' => 1, 'percent' => 10.0], $this->hint($deal, [$this->line(1, 1, 700), $this->line(2, 1, 700)]));
	}

	public function test_no_pieces_hint_at_the_top_tier_or_with_none_of_the_products_in_the_cart(): void {
		$deal = ['id' => 't', 'type' => 'quantity_tier', 'product_ids' => [1, 2, 3], 'tiers' => [['min' => 2, 'percent' => 8], ['min' => 3, 'percent' => 10]]];

		$this->assertNull($this->hint($deal, [$this->line(1, 3, 700)]));
		$this->assertNull($this->hint($deal, [$this->line(99, 1, 700)]));
	}

	public function test_no_hint_for_a_deal_that_is_off_or_outside_its_dates(): void {
		$deal = ['id' => 'a', 'type' => 'spend_threshold', 'min_spend' => 1000, 'mode' => 'percent', 'value' => 10, 'enabled' => false];
		$this->assertNull($this->hint($deal, [$this->line(1, 1, 600)]));

		$deal = ['id' => 'a', 'type' => 'spend_threshold', 'min_spend' => 1000, 'mode' => 'percent', 'value' => 10, 'valid_to' => '2026-10-11 09:00'];
		$this->assertNull($this->hint($deal, [$this->line(1, 1, 600)]));
	}

	public function test_deals_of_other_types_have_no_hint(): void {
		$deal = ['id' => 'p', 'type' => 'same_product', 'product_id' => 1, 'buy_qty' => 1, 'get_qty' => 1];

		$this->assertNull($this->hint($deal, [$this->line(1, 1, 700)]));
	}

	public function test_a_deal_that_beats_member_privilege_on_every_line_is_reported_as_deal_larger(): void {
		$deal   = ['id' => 'a', 'type' => 'spend_threshold', 'min_spend' => 1000, 'mode' => 'percent', 'value' => 10];
		$result = amouriq_bogo_cart_discounts([$deal], [$this->line(1, 2, 700)], $this->ctx(['is_member' => true]));

		$this->assertSame('deal_larger', $result['member']['reason']);
	}
}
