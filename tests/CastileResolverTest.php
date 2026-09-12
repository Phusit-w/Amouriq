<?php

use PHPUnit\Framework\TestCase;

/**
 * Covers ticket 01 (.scratch/castile-sales-page/issues/01-scent-size-resolver-phpunit.md):
 * every real (scent, size) combination the CASTILE buying block can select,
 * plus the predictable-rejection and out-of-stock-reporting behavior it
 * depends on.
 */
final class CastileResolverTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['amq_test_products'] = [
			2278 => new FakeWcProduct(2278, 'Organic Olive Castile Soap - Lavender', 9001, [
				$this->variationFixture(2281, '100ml', 459, 'SL-CT-NA-LV0100-2', true),
				$this->variationFixture(2282, '250-ml', 779, 'SL-CT-NA-LV0250-2', true),
				$this->variationFixture(2283, '500-ml', 1199, 'SL-CT-NA-LV0500-2', true),
			]),
			2285 => new FakeWcProduct(2285, 'Organic Olive Castile Soap - Rosemary', 9002, [
				$this->variationFixture(2287, '100ml', 459, 'SL-CT-NA-RM0100-2', true),
				$this->variationFixture(2288, '250-ml', 779, 'SL-CT-NA-RM0250-2', true),
				$this->variationFixture(2289, '500-ml', 1199, 'SL-CT-NA-RM0500-2', true),
			]),
			// Rose Geranium 500ml is deliberately out of stock, to cover the
			// out-of-stock-reporting test below.
			2293 => new FakeWcProduct(2293, 'Organic Olive Castile Soap - Rose Geranium', 9003, [
				$this->variationFixture(2295, '100ml', 459, 'SL-CT-NA-RG0100-2', true),
				$this->variationFixture(2296, '250-ml', 779, 'SL-CT-NA-RG0250-2', true),
				$this->variationFixture(2297, '500-ml', 1199, 'SL-CT-NA-RG0500-2', false),
			]),
		];
	}

	private function variationFixture($id, $attribute_value, $price, $sku, $in_stock) {
		return [
			'variation_id' => $id,
			'attributes' => ['attribute_pa_quantity' => $attribute_value],
			'display_price' => $price,
			'price_html' => '<span class="woocommerce-Price-amount">฿' . number_format($price) . '</span>',
			'sku' => $sku,
			'is_in_stock' => $in_stock,
			'image_id' => 0,
		];
	}

	/** @dataProvider inStockCombinations */
	public function test_resolves_each_real_in_stock_combination($scent, $size, $product_id, $variation_id, $price, $sku): void {
		$result = amq_castile_resolve_selection($scent, $size);

		$this->assertNotNull($result);
		$this->assertSame($product_id, $result['product_id']);
		$this->assertSame($variation_id, $result['variation_id']);
		$this->assertSame($price, $result['price']);
		$this->assertSame($sku, $result['sku']);
		$this->assertTrue($result['in_stock']);
	}

	public static function inStockCombinations(): array {
		return [
			'lavender 100ml' => ['lavender', '100', 2278, 2281, 459.0, 'SL-CT-NA-LV0100-2'],
			'lavender 250ml' => ['lavender', '250', 2278, 2282, 779.0, 'SL-CT-NA-LV0250-2'],
			'lavender 500ml' => ['lavender', '500', 2278, 2283, 1199.0, 'SL-CT-NA-LV0500-2'],
			'rosemary 100ml' => ['rosemary', '100', 2285, 2287, 459.0, 'SL-CT-NA-RM0100-2'],
			'rosemary 250ml' => ['rosemary', '250', 2285, 2288, 779.0, 'SL-CT-NA-RM0250-2'],
			'rosemary 500ml' => ['rosemary', '500', 2285, 2289, 1199.0, 'SL-CT-NA-RM0500-2'],
			'rose-geranium 100ml' => ['rose-geranium', '100', 2293, 2295, 459.0, 'SL-CT-NA-RG0100-2'],
			'rose-geranium 250ml' => ['rose-geranium', '250', 2293, 2296, 779.0, 'SL-CT-NA-RG0250-2'],
			// rose-geranium 500ml is the out-of-stock fixture — covered by
			// test_reports_out_of_stock_variation_instead_of_hiding_it() below,
			// which brings total coverage to all 9 combinations.
		];
	}

	public function test_reports_out_of_stock_variation_instead_of_hiding_it(): void {
		$result = amq_castile_resolve_selection('rose-geranium', '500');

		$this->assertNotNull($result);
		$this->assertSame(2293, $result['product_id']);
		$this->assertSame(2297, $result['variation_id']);
		$this->assertFalse($result['in_stock']);
	}

	public function test_rejects_unknown_scent(): void {
		$this->assertNull(amq_castile_resolve_selection('mango', '250'));
	}

	public function test_rejects_unknown_size(): void {
		$this->assertNull(amq_castile_resolve_selection('lavender', '750'));
	}

	public function test_returns_null_when_product_is_missing_entirely(): void {
		$GLOBALS['amq_test_products'] = [];

		$this->assertNull(amq_castile_resolve_selection('lavender', '250'));
	}
}
