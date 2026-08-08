<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Api;

/**
 * Who is looking, expressed as the three dimensions everything caches by.
 *
 * READ FROM \Magento\Framework\App\Http\Context, NEVER FROM \Magento\Customer\Model\Session. Two
 * independent reasons, and both matter:
 *
 *  1. On a cacheable page \Magento\Customer\Model\Layout\DepersonalizePlugin empties the customer
 *     session after layout generation. A block reading it would see a logged-in customer on the
 *     first render and a guest on the next, and cache whichever it happened to get.
 *  2. Only values in the HTTP context reach Context::getVaryString(), which is what keys the
 *     full-page cache through the X-Magento-Vary cookie. A session read does not vary the page, so
 *     group-specific content built from it would be served to every group.
 *
 * Magento already writes CONTEXT_GROUP and CONTEXT_AUTH there on every request
 * (\Magento\Customer\Model\App\Action\ContextPlugin), so this costs nothing extra and adds no new
 * full-page-cache fragmentation — the buckets exist already.
 *
 * @api
 */
interface VisitorContextInterface
{
    /**
     * Get the current store view ID.
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Get the current customer group ID.
     *
     * @return int Group 0 (NOT LOGGED IN) for a guest — a real group, not a sentinel.
     */
    public function getCustomerGroupId(): int;

    /**
     * Check whether the visitor is logged in.
     *
     * @return bool
     */
    public function isLoggedIn(): bool;

    /**
     * Get a stable string identifying this visitor bucket, for use in a cache key.
     *
     * TWO VISITORS SHARING A SUFFIX MUST BE GUARANTEED TO SEE THE SAME CONTENT. That is the property
     * which keeps a restricted row out of another audience's cached markup, so every dimension that
     * can change what is visible has to appear here. A collision is a disclosure, not a cosmetic bug.
     *
     * @return string
     */
    public function getCacheKeySuffix(): string;
}
