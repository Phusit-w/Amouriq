<?php

use PHPUnit\Framework\TestCase;

/**
 * Ticket 01 (.scratch/checkout-fee-label-deals/issues/01-cod-payment-fee.md):
 * the Payment fee, a fixed charge when the customer picks cash on delivery.
 */
final class PaymentFeeTest extends TestCase {
	private function settings(array $extra = []) {
		return array_merge(['enabled' => true, 'amount' => 30.0], $extra);
	}

	private function ctx(array $extra = []) {
		return array_merge(['has_items' => true, 'free_shipping' => false], $extra);
	}

	public function test_cash_on_delivery_gives_the_configured_amount(): void {
		$fee = amouriq_payment_fee_core('cod', $this->settings(), $this->ctx());

		$this->assertSame(30.0, $fee['amount']);
		$this->assertSame('ค่าธรรมเนียมเก็บเงินปลายทาง', $fee['name']);
	}

	public function test_other_payment_methods_give_no_fee(): void {
		foreach (['omise', 'omise_promptpay', 'bacs', '', null] as $method) {
			$this->assertNull(amouriq_payment_fee_core($method, $this->settings(), $this->ctx()));
		}
	}

	public function test_free_shipping_still_pays_the_fee(): void {
		$fee = amouriq_payment_fee_core('cod', $this->settings(), $this->ctx(['free_shipping' => true]));

		$this->assertSame(30.0, $fee['amount']);
	}

	public function test_switched_off_gives_no_fee(): void {
		$this->assertNull(amouriq_payment_fee_core('cod', $this->settings(['enabled' => false]), $this->ctx()));
	}

	public function test_the_amount_is_editable(): void {
		$fee = amouriq_payment_fee_core('cod', $this->settings(['amount' => 45.5]), $this->ctx());

		$this->assertSame(45.5, $fee['amount']);
	}

	public function test_a_zero_or_negative_amount_gives_no_fee(): void {
		$this->assertNull(amouriq_payment_fee_core('cod', $this->settings(['amount' => 0]), $this->ctx()));
		$this->assertNull(amouriq_payment_fee_core('cod', $this->settings(['amount' => -5]), $this->ctx()));
	}

	public function test_an_empty_cart_gives_no_fee(): void {
		$this->assertNull(amouriq_payment_fee_core('cod', $this->settings(), $this->ctx(['has_items' => false])));
	}

	public function test_the_customer_pays_exactly_the_amount_with_no_tax(): void {
		// The fee is added as non-taxable, so WooCommerce adds no tax on top of it.
		$fee = amouriq_payment_fee_core('cod', $this->settings(), $this->ctx());

		$this->assertFalse($fee['taxable']);
	}

	public function test_settings_are_read_with_defaults_on_at_thirty_baht(): void {
		$this->assertSame(['enabled' => true, 'amount' => 30.0], amouriq_payment_fee_core_settings(null, null));
		$this->assertSame(['enabled' => false, 'amount' => 30.0], amouriq_payment_fee_core_settings('no', '30'));
		$this->assertSame(['enabled' => true, 'amount' => 50.0], amouriq_payment_fee_core_settings('yes', '50'));
		$this->assertSame(['enabled' => true, 'amount' => 30.0], amouriq_payment_fee_core_settings('yes', 'abc'));
	}

	public function test_the_fee_is_not_purchase_value_for_points_or_spend_tests(): void {
		// Points earn on the order total less shipping; the fee is taken off too.
		$this->assertSame(1000.0, amouriq_payment_fee_core_eligible(1030.0, 30.0));
		$this->assertSame(1000.0, amouriq_payment_fee_core_eligible(1000.0, 0.0));
		$this->assertSame(0.0, amouriq_payment_fee_core_eligible(20.0, 30.0));
	}

	public function test_spend_measures_read_item_lines_only(): void {
		// Min spend / Deals measure cart lines, which never include a fee.
		$lines = [[
			'product_id' => 1, 'variation_id' => 0, 'quantity' => 1, 'on_sale' => false, 'categories' => [],
			'unit_price' => 1400.0, 'price_incl_tax' => 1400.0,
		]];
		$deal = ['id' => 'g1', 'type' => 'spend_gift', 'min_spend' => 1500, 'gift_ids' => [9], 'gift_qty' => 1];
		$ctx  = ['now' => '2026-10-11 12:00', 'today' => '2026-10-11', 'applied_coupons' => [], 'customer_uses' => 0, 'prices' => [], 'is_member' => false];

		// Even with a 30 baht fee on the order the cart is 100 short of 1500.
		$this->assertSame(0, amouriq_bogo_cart_discounts([$deal], $lines, $ctx)['deals'][0]['allowed']);
	}
}
