<?php

use PHPUnit\Framework\TestCase;

/**
 * Rule-conditions ticket 04: Product scope. Include dimensions are ANDed
 * (a category AND a size), values inside a dimension are ORed, any exclude hit
 * leaves the line out. Deals without a scope behave exactly as before.
 */
final class BogoScopeTest extends TestCase {
	private function line($pid, $vid, $qty, $price, array $extra = []) {
		return array_merge([
			'product_id'     => $pid,
			'variation_id'   => $vid,
			'quantity'       => $qty,
			'on_sale'        => false,
			'categories'     => ['soap', 7],
			'tags'           => [],
			'attributes'     => [],
			'sku'            => '',
			'unit_price'     => $price,
			'price_incl_tax' => round($price * 1.07, 2),
		], $extra);
	}

	private function ctx() {
		return ['now' => '2026-10-12 12:00', 'today' => '2026-10-12', 'applied_coupons' => [], 'customer_uses' => 0, 'prices' => [], 'is_member' => false];
	}

	private function in($line, array $scope): bool {
		return amouriq_bogo_core_in_scope($line, $scope);
	}

	public function test_no_scope_covers_every_line(): void {
		$this->assertTrue($this->in($this->line(1, 0, 1, 100), []));
	}

	public function test_include_by_category_slug_or_id(): void {
		$this->assertTrue($this->in($this->line(1, 0, 1, 100), ['include' => ['categories' => ['soap']]]));
		$this->assertTrue($this->in($this->line(1, 0, 1, 100), ['include' => ['categories' => [7]]]));
		$this->assertFalse($this->in($this->line(1, 0, 1, 100), ['include' => ['categories' => ['candle']]]));
	}

	public function test_include_by_tag_attribute_sku_and_product(): void {
		$line = $this->line(1, 11, 1, 100, ['tags' => ['gift'], 'attributes' => ['pa_quantity' => ['250-ml']], 'sku' => 'CS-LAV-250']);

		$this->assertTrue($this->in($line, ['include' => ['tags' => ['gift']]]));
		$this->assertFalse($this->in($line, ['include' => ['tags' => ['new']]]));
		$this->assertTrue($this->in($line, ['include' => ['attributes' => ['pa_quantity' => ['100-ml', '250-ml']]]]));
		$this->assertFalse($this->in($line, ['include' => ['attributes' => ['pa_quantity' => ['500-ml']]]]));
		$this->assertTrue($this->in($line, ['include' => ['skus' => ['cs-lav-250']]])); // case-insensitive
		$this->assertTrue($this->in($line, ['include' => ['products' => [11]]]));   // the variation
		$this->assertTrue($this->in($line, ['include' => ['products' => [1]]]));    // or its parent
		$this->assertFalse($this->in($line, ['include' => ['products' => [2]]]));
	}

	public function test_include_dimensions_must_all_match(): void {
		$line  = $this->line(1, 11, 1, 100, ['attributes' => ['pa_quantity' => ['250-ml']]]);
		$scope = ['include' => ['categories' => ['soap'], 'attributes' => ['pa_quantity' => ['500-ml']]]];

		$this->assertFalse($this->in($line, $scope)); // right category, wrong size
		$scope['include']['attributes']['pa_quantity'] = ['250-ml'];
		$this->assertTrue($this->in($line, $scope));
	}

	public function test_on_sale_only_and_skip_sale(): void {
		$sale    = $this->line(1, 0, 1, 100, ['on_sale' => true]);
		$regular = $this->line(2, 0, 1, 100);

		$this->assertTrue($this->in($sale, ['include' => ['on_sale_only' => true]]));
		$this->assertFalse($this->in($regular, ['include' => ['on_sale_only' => true]]));
		$this->assertFalse($this->in($sale, ['exclude' => ['on_sale_only' => true]]));
		$this->assertTrue($this->in($regular, ['exclude' => ['on_sale_only' => true]]));
	}

	public function test_any_exclude_hit_leaves_the_line_out_even_when_included(): void {
		$line  = $this->line(1, 0, 1, 100, ['sku' => 'GIFT-1']);
		$scope = ['include' => ['categories' => ['soap']], 'exclude' => ['skus' => ['gift-1']]];

		$this->assertFalse($this->in($line, $scope));
		$this->assertTrue($this->in($this->line(1, 0, 1, 100, ['sku' => 'OTHER']), $scope));
	}

	public function test_a_scoped_spend_threshold_discounts_only_lines_in_scope_but_counts_the_whole_cart_for_the_minimum(): void {
		$deal  = ['type' => 'spend_threshold', 'min_spend' => 1000, 'mode' => 'percent', 'value' => 10, 'scope' => ['include' => ['categories' => ['soap']]]];
		$lines = [
			$this->line(1, 0, 1, 500),                                   // soap
			$this->line(2, 0, 1, 700, ['categories' => ['candle', 8]]), // candle: counts toward 1000, not discounted
		];
		$r = amouriq_bogo_cart_discounts([$deal], $lines, $this->ctx())['deals'][0];

		$this->assertEqualsWithDelta(50.0, $r['discount'], 0.001); // 10% of the soap only
		$this->assertSame([0], array_keys($r['per_line']));
	}

	public function test_a_scoped_quantity_tier_counts_only_lines_in_scope(): void {
		$deal  = ['type' => 'quantity_tier', 'tiers' => [['min' => 2, 'percent' => 8], ['min' => 3, 'percent' => 10]], 'scope' => ['include' => ['attributes' => ['pa_quantity' => ['250-ml']]]]];
		$lines = [
			$this->line(1, 11, 1, 400, ['attributes' => ['pa_quantity' => ['250-ml']]]),
			$this->line(1, 12, 1, 400, ['attributes' => ['pa_quantity' => ['250-ml']]]),
			$this->line(1, 13, 5, 150, ['attributes' => ['pa_quantity' => ['100-ml']]]), // many pieces, wrong size
		];
		$r = amouriq_bogo_cart_discounts([$deal], $lines, $this->ctx())['deals'][0];

		$this->assertEqualsWithDelta(64.0, $r['discount'], 0.001); // 2 pieces at 8% of 800
	}

	public function test_a_deal_without_a_scope_is_unchanged(): void {
		$deal  = ['type' => 'spend_threshold', 'min_spend' => 500, 'mode' => 'percent', 'value' => 10];
		$lines = [$this->line(1, 0, 2, 500)];
		$this->assertEqualsWithDelta(100.0, amouriq_bogo_cart_discounts([$deal], $lines, $this->ctx())['deals'][0]['discount'], 0.001);
	}
}
