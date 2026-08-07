<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Api\Data;

/**
 * The visibility filters an audience-targetable thing carries.
 *
 * Extracted so \Muon\Core\Api\VisibilityResolverInterface takes ONE argument type rather than a
 * union or a family of near-identical methods. Anything that can be shown or hidden by the same
 * rules implements this — a menu item, a marketing block, a promotional banner — and gets the
 * shared resolver for free.
 *
 * THREE OF THE FOUR FILTERS ARE ACCESS CONTROL; ONE IS NOT. Store, customer group and login state
 * remove the subject server-side, so a visitor who fails them receives no markup for it at all.
 * `device` does not: it renders identical markup for everyone and hides by CSS, which is what keeps
 * it free of any cache-key cost. NEVER USE `device` TO HIDE SOMETHING CONFIDENTIAL.
 *
 * @api
 */
interface FilterableInterface
{
    /**
     * Visible to everyone, whatever their login state.
     */
    public const VISIBILITY_ANY = 'any';

    /**
     * Visible only to a visitor who is not logged in.
     */
    public const VISIBILITY_GUEST = 'guest';

    /**
     * Visible only to a logged-in customer.
     */
    public const VISIBILITY_LOGGED_IN = 'logged_in';

    /**
     * Every valid visibility value.
     */
    public const VISIBILITIES = [
        self::VISIBILITY_ANY,
        self::VISIBILITY_GUEST,
        self::VISIBILITY_LOGGED_IN,
    ];

    /**
     * Rendered at every viewport.
     */
    public const DEVICE_ALL = 'all';

    /**
     * Rendered, but hidden by CSS below the mobile breakpoint.
     */
    public const DEVICE_DESKTOP = 'desktop';

    /**
     * Rendered, but hidden by CSS at and above the mobile breakpoint.
     */
    public const DEVICE_MOBILE = 'mobile';

    /**
     * Every valid device value.
     */
    public const DEVICES = [
        self::DEVICE_ALL,
        self::DEVICE_DESKTOP,
        self::DEVICE_MOBILE,
    ];

    /**
     * Get the login-state filter.
     *
     * @return string One of the self::VISIBILITY_* constants.
     */
    public function getVisibility(): string;

    /**
     * Get the customer groups this may be shown to.
     *
     * An EMPTY array means every group — it is not the same as "no groups". Group 0 is a real group
     * (NOT LOGGED IN), so it cannot double as an "all" sentinel the way store ID 0 does.
     *
     * Null means "not loaded / not supplied by the caller" and is distinct from an empty array. On a
     * render path null is a programming error — a missing assignment load — and the resolver treats
     * it as restricted so the mistake surfaces rather than publishing everything.
     *
     * @return int[]|null
     */
    public function getCustomerGroupIds(): ?array;

    /**
     * Get the device filter.
     *
     * @return string One of the self::DEVICE_* constants.
     */
    public function getDevice(): string;

    /**
     * Get the start of the schedule window.
     *
     * @return string|null UTC, 'Y-m-d H:i:s'. Null means "no start bound".
     */
    public function getActiveFrom(): ?string;

    /**
     * Get the end of the schedule window.
     *
     * @return string|null UTC, 'Y-m-d H:i:s'. Null means "no end bound".
     */
    public function getActiveTo(): ?string;
}
