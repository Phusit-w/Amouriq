<?php

use PHPUnit\Framework\TestCase;

/**
 * Ticket 04 (.scratch/promotions-rework/issues/04-new-customer-and-email-coupons.md):
 * the New customer rule from CONTEXT.md.
 */
final class NewCustomerTest extends TestCase {
	private function order($status, $customer_id = 0, $email = '') {
		return ['status' => $status, 'customer_id' => $customer_id, 'billing_email' => $email];
	}

	public function test_a_customer_with_no_orders_is_new(): void {
		$this->assertTrue(amouriq_is_new_customer([], 7, 'a@example.test'));
	}

	/** @dataProvider counted */
	public function test_a_counted_order_by_account_makes_them_not_new(string $status): void {
		$this->assertFalse(amouriq_is_new_customer([$this->order($status, 7)], 7, ''));
	}

	public static function counted(): array {
		return [['pending'], ['on-hold'], ['processing'], ['completed']];
	}

	/** @dataProvider not_counted */
	public function test_cancelled_failed_and_refunded_orders_do_not_count(string $status): void {
		$this->assertTrue(amouriq_is_new_customer([$this->order($status, 7, 'a@example.test')], 7, 'a@example.test'));
	}

	public static function not_counted(): array {
		return [['cancelled'], ['failed'], ['refunded'], ['trash'], ['checkout-draft']];
	}

	public function test_a_counted_order_by_billing_email_counts_for_someone_registering_now(): void {
		// Registering in this checkout: no account yet, matched by the billing email.
		$this->assertFalse(amouriq_is_new_customer([$this->order('completed', 0, 'Old.Guest@Example.test')], 0, 'old.guest@example.test '));
	}

	public function test_someone_elses_order_does_not_count(): void {
		$this->assertTrue(amouriq_is_new_customer([$this->order('completed', 99, 'other@example.test')], 7, 'a@example.test'));
	}

	public function test_no_email_and_no_account_matches_nothing(): void {
		$this->assertTrue(amouriq_is_new_customer([$this->order('completed', 0, '')], 0, ''));
	}
}
