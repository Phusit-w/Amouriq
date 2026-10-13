<?php

use PHPUnit\Framework\TestCase;

/** Picking several Deals in the list and turning them on, off or deleting them together. */
final class BogoBulkTest extends TestCase {
	private function deals() {
		return [
			['id' => 'a', 'label' => 'A', 'enabled' => true],
			['id' => 'b', 'label' => 'B', 'enabled' => false],
			['id' => 'c', 'label' => 'C'], // no `enabled` key means on
		];
	}

	public function test_disable_turns_the_picked_deals_off_and_counts_only_real_changes(): void {
		$r = amouriq_bogo_core_bulk($this->deals(), ['a', 'b'], 'disable', '2026-10-14 09:00:00');

		$this->assertFalse($r['deals'][0]['enabled']);
		$this->assertFalse($r['deals'][1]['enabled']);
		$this->assertSame(1, $r['changed']); // b was already off
		$this->assertSame('2026-10-14 09:00:00', $r['deals'][0]['updated_at']);
		$this->assertArrayNotHasKey('updated_at', $r['deals'][1]);
	}

	public function test_enable_turns_the_picked_deals_on(): void {
		$r = amouriq_bogo_core_bulk($this->deals(), ['b', 'c'], 'enable', '2026-10-14 09:00:00');

		$this->assertTrue($r['deals'][1]['enabled']);
		$this->assertSame(1, $r['changed']); // c was already on
	}

	public function test_a_deal_with_no_enabled_key_counts_as_on_and_can_be_disabled(): void {
		$r = amouriq_bogo_core_bulk($this->deals(), ['c'], 'disable', '2026-10-14 09:00:00');

		$this->assertFalse($r['deals'][2]['enabled']);
		$this->assertSame(1, $r['changed']);
	}

	public function test_delete_removes_only_the_picked_deals_and_keeps_the_order(): void {
		$r = amouriq_bogo_core_bulk($this->deals(), ['a', 'c'], 'delete', '2026-10-14 09:00:00');

		$this->assertSame(['b'], array_column($r['deals'], 'id'));
		$this->assertSame(2, $r['changed']);
	}

	public function test_unknown_ids_and_unknown_actions_change_nothing(): void {
		$none = amouriq_bogo_core_bulk($this->deals(), ['zzz'], 'delete', 'now');
		$this->assertSame($this->deals(), $none['deals']);
		$this->assertSame(0, $none['changed']);

		$odd = amouriq_bogo_core_bulk($this->deals(), ['a'], 'explode', 'now');
		$this->assertSame($this->deals(), $odd['deals']);
		$this->assertSame(0, $odd['changed']);
	}

	public function test_nothing_picked_changes_nothing(): void {
		$this->assertSame(0, amouriq_bogo_core_bulk($this->deals(), [], 'delete', 'now')['changed']);
	}
}
