<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Validator;

use DateTimeImmutable;
use DateTimeZone;

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
     * The one format a stored bound may be in — Magento's datetime column format, UTC.
     */
    private const FORMAT = 'Y-m-d H:i:s';

    /**
     * Parse a stored timestamp.
     *
     * STRICT FORMAT, NOT `new DateTimeImmutable($value)`. That constructor accepts RELATIVE
     * expressions — "tomorrow", "+1 day", "yesterday", "now" all parse happily — and contains() runs
     * at RENDER time, so a stored relative value resolves to a different instant on every single
     * request. A window saved as "+1 day" would never open, and one saved as "yesterday" would be
     * permanently open; neither would look wrong in the database, and neither would ever be
     * reproducible.
     *
     * The distinction is invisible to a test that only tries prose: "sometime next week" is rejected
     * by both, which is why the loose version passed for as long as it did.
     *
     * A caller sending ISO-8601 now gets a clear validation error at save time instead of a value
     * that appears to work and then drifts.
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

        $parsed = DateTimeImmutable::createFromFormat(self::FORMAT, $value, new DateTimeZone('UTC'));

        if ($parsed === false) {
            return null;
        }

        // createFromFormat() is forgiving about a value that merely STARTS with the format, and it
        // silently rolls impossible dates over ("2026-02-31" becomes 3 March). Round-tripping the
        // result is what makes both of those a rejection rather than a quiet reinterpretation.
        return $parsed->format(self::FORMAT) === trim($value) ? $parsed : null;
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
