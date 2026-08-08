<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model;

use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * "What time is it, in UTC."
 *
 * A one-method service rather than an inline `new DateTimeImmutable()` at each call site, for two
 * reasons. It is the seam that makes a schedule filter deterministic in a test without stubbing
 * Magento's whole TimezoneInterface. And it keeps the UTC conversion in one place: schedule columns
 * are stored UTC and schedule-boundary crons compare against UTC, so a call site that forgot the
 * conversion would shift every window by the store's offset — a silent, per-website wrong answer on
 * an estate spanning timezones.
 */
class Clock
{
    /**
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     */
    public function __construct(
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Get the current instant, in UTC.
     *
     * @return \DateTimeImmutable
     */
    public function nowUtc(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($this->timezone->date())
            ->setTimezone(new DateTimeZone('UTC'));
    }
}
