<?php

use PHPUnit\Framework\TestCase;

/** Points round two, ticket 07: who is told that points are about to expire, and only once per batch. */
final class PointsExpiryEmailTest extends TestCase {
	private const DAY = 86400;

	private function earn($time, $points, $expires) {
		return ['time' => $time, 'points' => $points, 'type' => 'earn', 'expires' => $expires];
	}

	public function test_the_settings_default_to_off_and_14_days_and_bad_values_fall_back(): void {
		$d = amouriq_points_core_expiry_mail_settings([]);
		$this->assertFalse($d['enabled']);
		$this->assertSame(14, $d['days']);

		$s = amouriq_points_core_expiry_mail_settings(['enabled' => '1', 'days' => '30']);
		$this->assertTrue($s['enabled']);
		$this->assertSame(30, $s['days']);

		$this->assertSame(14, amouriq_points_core_expiry_mail_settings(['days' => 'abc'])['days']);
		$this->assertSame(14, amouriq_points_core_expiry_mail_settings(['days' => 0])['days']);
		$this->assertSame(14, amouriq_points_core_expiry_mail_settings(['days' => 999])['days']);
	}

	public function test_points_expiring_inside_the_window_are_summed_with_the_soonest_date(): void {
		$now    = 1000 * self::DAY;
		$ledger = [
			$this->earn($now - 300 * self::DAY, 40, $now + 5 * self::DAY),
			$this->earn($now - 200 * self::DAY, 25, $now + 10 * self::DAY),
			$this->earn($now - 100 * self::DAY, 60, $now + 90 * self::DAY), // outside the window
		];
		$e = amouriq_points_core_expiring($ledger, $now, 14);

		$this->assertSame(65, $e['points']);
		$this->assertSame($now + 5 * self::DAY, $e['soonest']);
	}

	public function test_nothing_expiring_gives_null_and_used_points_are_not_counted(): void {
		$now = 1000 * self::DAY;
		$this->assertNull(amouriq_points_core_expiring([], $now, 14));
		$this->assertNull(amouriq_points_core_expiring([$this->earn($now - 300 * self::DAY, 40, $now + 400 * self::DAY)], $now, 14));

		// 40 earned, 40 used: nothing left to expire.
		$ledger = [$this->earn($now - 300 * self::DAY, 40, $now + 5 * self::DAY), ['time' => $now - 10 * self::DAY, 'points' => -40, 'type' => 'use', 'expires' => 0]];
		$this->assertNull(amouriq_points_core_expiring($ledger, $now, 14));
	}

	public function test_already_expired_points_are_never_mentioned(): void {
		$now = 1000 * self::DAY;
		$ledger = [$this->earn($now - 400 * self::DAY, 80, $now - 2 * self::DAY)];
		$this->assertNull(amouriq_points_core_expiring($ledger, $now, 14));
	}

	public function test_a_batch_is_notified_once(): void {
		$now = 1000 * self::DAY;
		$e   = ['points' => 65, 'soonest' => $now + 5 * self::DAY];

		$this->assertTrue(amouriq_points_core_should_notify($e, 0));
		$this->assertFalse(amouriq_points_core_should_notify($e, $now + 5 * self::DAY));   // told about this batch already
		$this->assertTrue(amouriq_points_core_should_notify($e, $now - 20 * self::DAY));    // told about an older batch
		$this->assertFalse(amouriq_points_core_should_notify(null, 0));
	}

	public function test_the_message_has_the_balance_the_date_and_no_prices(): void {
		$text = amouriq_points_core_expiry_message(65, strtotime('2026-11-01 12:00:00 UTC'), 'https://example.test/my-account/points/');
		$this->assertStringContainsString('65 แต้ม', $text['subject'] . $text['body']);
		$this->assertStringContainsString('1 พ.ย. 2026', $text['body']);
		$this->assertStringContainsString('https://example.test/my-account/points/', $text['body']);
		$this->assertStringNotContainsString('฿', $text['body']);
	}
}
