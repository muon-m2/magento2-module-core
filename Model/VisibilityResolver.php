<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model;

use Muon\Core\Api\Data\FilterableInterface;
use Muon\Core\Api\VisibilityResolverInterface;
use Muon\Core\Api\VisitorContextInterface;
use Muon\Core\Model\Validator\ScheduleWindow;

/**
 * Decides whether one filterable thing may be shown to one visitor at one moment.
 *
 * Filters compose with AND: every rule must pass. Each is written to FAIL CLOSED — an unrecognised
 * visibility value, an unparseable schedule bound, or a group allow-list that was never loaded all
 * hide the subject rather than showing it. That direction is chosen deliberately: something that
 * should have appeared and did not is a merchant support ticket, while something that should have
 * been hidden and appeared is a disclosure.
 *
 * Store assignment is NOT evaluated here — it is a join in the collection, because filtering it in
 * PHP would mean loading every row in the estate on every store view's render.
 */
class VisibilityResolver implements VisibilityResolverInterface
{
    /**
     * @param \Muon\Core\Model\Validator\ScheduleWindow $scheduleWindow
     */
    public function __construct(
        private readonly ScheduleWindow $scheduleWindow
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isVisible(
        FilterableInterface $subject,
        VisitorContextInterface $visitor,
        \DateTimeInterface $now
    ): bool {
        return $this->passesLoginState($subject, $visitor)
            && $this->passesCustomerGroup($subject, $visitor)
            && $this->passesSchedule($subject, $now);
    }

    /**
     * Check the guest / logged-in filter.
     *
     * @param \Muon\Core\Api\Data\FilterableInterface $subject
     * @param \Muon\Core\Api\VisitorContextInterface $visitor
     * @return bool
     */
    private function passesLoginState(FilterableInterface $subject, VisitorContextInterface $visitor): bool
    {
        return match ($subject->getVisibility()) {
            FilterableInterface::VISIBILITY_ANY => true,
            FilterableInterface::VISIBILITY_GUEST => !$visitor->isLoggedIn(),
            FilterableInterface::VISIBILITY_LOGGED_IN => $visitor->isLoggedIn(),
            // An unrecognised value can only come from outside the validator — a data patch, a
            // restored dump, a direct SQL edit. Hide rather than guess.
            default => false,
        };
    }

    /**
     * Check the customer-group allow-list.
     *
     * An EMPTY allow-list means every group. Null means the assignment was never loaded, which on a
     * render path is a programming error rather than an unrestricted subject — treat it as restricted
     * so a missing assignment load fails visibly instead of silently publishing everything.
     *
     * @param \Muon\Core\Api\Data\FilterableInterface $subject
     * @param \Muon\Core\Api\VisitorContextInterface $visitor
     * @return bool
     */
    private function passesCustomerGroup(FilterableInterface $subject, VisitorContextInterface $visitor): bool
    {
        $allowed = $subject->getCustomerGroupIds();

        if ($allowed === null) {
            return false;
        }

        if ($allowed === []) {
            return true;
        }

        return in_array($visitor->getCustomerGroupId(), $allowed, true);
    }

    /**
     * Check the schedule window.
     *
     * Both bounds are inclusive of the instant they name and are compared in UTC.
     *
     * @param \Muon\Core\Api\Data\FilterableInterface $subject
     * @param \DateTimeInterface $now
     * @return bool
     */
    private function passesSchedule(FilterableInterface $subject, \DateTimeInterface $now): bool
    {
        return $this->scheduleWindow->contains($subject->getActiveFrom(), $subject->getActiveTo(), $now);
    }
}
