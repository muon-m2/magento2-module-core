<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Validator;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * The schedule-window rule, shared by validators and renderers alike.
 *
 * THE TWO HALVES DISAGREE ON PURPOSE. validate() is a SAVE-time check: it reports what the merchant
 * got wrong and says nothing about whether the window is currently open. contains() is a RENDER-time
 * check, and it treats an unparseable bound as CLOSED — because the failure direction that matters
 * there is leaking a campaign early, not hiding one late. A single "is this window valid" helper
 * used for both would have to pick one and would be wrong at the other call site.
 *
 * An ordinary injectable service rather than a static helper: a static call cannot be intercepted by
 * a plugin and cannot be swapped in a test, which is why both the Magento coding standard and PHPMD
 * reject it. It has no state and no dependencies, so a shared instance costs nothing.
 *
 * TIMES ARE UTC. An admin form converts from the store view's local time on the way in and back on
 * the way out; everything below this line is UTC.
 */
class ScheduleWindow
{
    /**
     * Check that a schedule window is a window.
     *
     * @param string|null $activeFrom
     * @param string|null $activeTo
     * @return string[] Empty when the window is acceptable.
     */
    public function validate(?string $activeFrom, ?string $activeTo): array
    {
        $errors = [];

        $from = $this->parse($activeFrom);
        $to = $this->parse($activeTo);

        if ($activeFrom !== null && $from === null) {
            $errors[] = (string) __('"Active From" is not a valid date and time.');
        }

        if ($activeTo !== null && $to === null) {
            $errors[] = (string) __('"Active To" is not a valid date and time.');
        }

        if ($from !== null && $to !== null && $from > $to) {
            $errors[] = (string) __('"Active From" must be earlier than "Active To".');
        }

        return $errors;
    }

    /**
     * Parse a stored timestamp.
     *
     * @param string|null $value
     * @return \DateTimeImmutable|null Null when absent or unparseable — the caller distinguishes the
     *                                 two by checking the input for null itself.
     */
    public function parse(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Check whether an instant falls inside a window.
     *
     * A bound that is set but unparseable closes the window rather than opening it: showing something
     * whose schedule cannot be read is the failure direction that leaks a campaign early.
     *
     * @param string|null $activeFrom
     * @param string|null $activeTo
     * @param \DateTimeInterface $now
     * @return bool
     */
    public function contains(?string $activeFrom, ?string $activeTo, \DateTimeInterface $now): bool
    {
        if ($activeFrom !== null) {
            $from = $this->parse($activeFrom);

            if ($from === null || $now < $from) {
                return false;
            }
        }

        if ($activeTo !== null) {
            $to = $this->parse($activeTo);

            if ($to === null || $now > $to) {
                return false;
            }
        }

        return true;
    }
}
