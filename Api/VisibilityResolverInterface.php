<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Api;

use Muon\Core\Api\Data\FilterableInterface;

/**
 * Decides whether one filterable thing may be shown to one visitor at one moment.
 *
 * Store assignment is NOT evaluated here — it is a join in the collection, because filtering it in
 * PHP would mean loading every row in the estate on every store view's render. This resolver handles
 * the three filters that cannot be pushed into that query cheaply: login state, customer group, and
 * the schedule window.
 *
 * The `device` filter is not evaluated at all. It renders identical markup for every visitor and
 * hides by CSS, which is exactly why it costs nothing at cache level — and exactly why it must never
 * be used for confidential content.
 *
 * @api
 */
interface VisibilityResolverInterface
{
    /**
     * Check whether the subject may be shown.
     *
     * @param \Muon\Core\Api\Data\FilterableInterface $subject
     * @param \Muon\Core\Api\VisitorContextInterface $visitor
     * @param \DateTimeInterface $now UTC. Passed in rather than read from a clock so the decision is
     *                                deterministic and testable, and so one render evaluates every
     *                                subject against the same instant.
     * @return bool
     */
    public function isVisible(
        FilterableInterface $subject,
        VisitorContextInterface $visitor,
        \DateTimeInterface $now
    ): bool;
}
