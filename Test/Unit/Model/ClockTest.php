<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model;

use DateTime;
use DateTimeZone;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Muon\Core\Model\Clock;
use PHPUnit\Framework\TestCase;

/**
 * "What time is it, in UTC."
 *
 * THE CONVERSION IS THE WHOLE POINT. Magento's TimezoneInterface hands back the current instant in
 * the *store's* configured timezone. Every schedule column in every consuming module is stored UTC
 * and compared UTC, so a call site that used the timezone service directly would shift each window
 * by the store's offset — silently, and differently per website on an estate spanning timezones.
 * This class exists so that conversion happens in exactly one place, and these tests exist so it
 * cannot quietly stop happening.
 */
class ClockTest extends TestCase
{
    /**
     * Build the clock around a timezone service pinned to a known instant and zone.
     *
     * @param string $localTime
     * @param string $zone
     * @return \Muon\Core\Model\Clock
     */
    private function clock(string $localTime, string $zone): Clock
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('date')->willReturn(new DateTime($localTime, new DateTimeZone($zone)));

        return new Clock($timezone);
    }

    /**
     * @return void
     */
    public function testTheReturnedInstantIsAlwaysUtc(): void
    {
        $now = $this->clock('2026-06-01 14:00:00', 'America/New_York')->nowUtc();

        self::assertSame('UTC', $now->getTimezone()->getName());
    }

    /**
     * A store ahead of UTC must read BACK, not forward. Getting the sign wrong is the failure this
     * class exists to prevent and it would still pass a "returns UTC" assertion on its own.
     *
     * @return void
     */
    public function testAnInstantAheadOfUtcIsConvertedBackwards(): void
    {
        // Tokyo is UTC+9 year-round, so this is unambiguous regardless of the date.
        $now = $this->clock('2026-06-01 09:00:00', 'Asia/Tokyo')->nowUtc();

        self::assertSame('2026-06-01 00:00:00', $now->format('Y-m-d H:i:s'));
    }

    /**
     * And a store behind UTC must read FORWARD, across the date boundary.
     *
     * @return void
     */
    public function testAnInstantBehindUtcIsConvertedForwardsAcrossMidnight(): void
    {
        // New York in June is EDT, UTC-4.
        $now = $this->clock('2026-06-01 22:00:00', 'America/New_York')->nowUtc();

        self::assertSame('2026-06-02 02:00:00', $now->format('Y-m-d H:i:s'));
    }

    /**
     * The instant is preserved exactly — this is a re-expression, not a truncation.
     *
     * @return void
     */
    public function testTheUnderlyingInstantIsUnchanged(): void
    {
        $local = new DateTime('2026-06-01 14:30:45', new DateTimeZone('Europe/Warsaw'));
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('date')->willReturn($local);

        self::assertSame(
            $local->getTimestamp(),
            (new Clock($timezone))->nowUtc()->getTimestamp()
        );
    }

    /**
     * Immutable, so a consumer cannot modify the shared clock's answer for everyone else.
     *
     * @return void
     */
    public function testTheResultIsImmutable(): void
    {
        $now = $this->clock('2026-06-01 12:00:00', 'UTC')->nowUtc();
        $later = $now->modify('+1 day');

        self::assertNotSame($now, $later);
        self::assertSame('2026-06-01 12:00:00', $now->format('Y-m-d H:i:s'));
    }
}
