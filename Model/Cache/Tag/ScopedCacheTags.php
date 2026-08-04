<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Cache\Tag;

use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Derives a cache tag and its scope-qualified variants from one base tag.
 *
 * WHY THIS EXISTS. A module that declares a single scope-less tag on every cacheable render, and
 * purges by that same tag whatever scope was saved, evicts the full-page cache for the whole estate
 * on any config save. PageCache folds block identities into `X-Magento-Tags`, so every cached page in
 * every store view carries the tag — and a `showInStore="1"` field changed on ONE store view then
 * purges all of them. That is the wholesale flush the scoping was meant to avoid, arriving through
 * the tag instead.
 *
 * Two Muon modules hit that independently and fixed it the same way, which is why the algorithm now
 * lives here rather than in either of them.
 *
 * The fix is a second, scope-qualified tag. A rendered page carries BOTH `{BASE}` and
 * `{BASE}_{storeId}`, and the save path emits only the tags matching the scope that changed:
 *
 *  - `stores`   -> `{BASE}_{storeId}` — one store view's pages.
 *  - `websites` -> the same, once per store view in the website.
 *  - `default`  -> the bare `{BASE}`, which every page still carries.
 *
 * Keeping the render, save and delete paths on one class is the point: they must agree about the tag
 * shape or a purge silently misses.
 *
 * @api Consumed through a virtual type per module, each supplying its own base tag.
 */
class ScopedCacheTags
{
    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param string $baseTag The module's estate-wide tag, e.g. `MUON_HEADERMENU`.
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly string $baseTag
    ) {
    }

    /**
     * Get the tag for one store view's pages.
     *
     * @param int $storeId
     * @return string
     */
    public function forStore(int $storeId): string
    {
        return $this->baseTag . '_' . $storeId;
    }

    /**
     * Get the estate-wide tag that every rendered page carries.
     *
     * Exposed as a method rather than leaving callers to read the base tag directly, so the tag
     * shape stays owned by one class.
     *
     * @return string
     */
    public function estateWide(): string
    {
        return $this->baseTag;
    }

    /**
     * Get the tags to purge for a config save at one scope.
     *
     * @param string $scope
     * @param int $scopeId
     * @return string[]
     */
    public function forSavedScope(string $scope, int $scopeId): array
    {
        if ($scope === ScopeInterface::SCOPE_STORES) {
            return [$this->forStore($scopeId)];
        }

        if ($scope === ScopeInterface::SCOPE_WEBSITES) {
            return $this->forWebsite($scopeId);
        }

        return [$this->estateWide()];
    }

    /**
     * Expand a website to the tags of every store view in it.
     *
     * @param int $websiteId
     * @return string[]
     */
    private function forWebsite(int $websiteId): array
    {
        $tags = [];

        foreach ($this->storeManager->getStores() as $store) {
            if ((int) $store->getWebsiteId() === $websiteId) {
                $tags[] = $this->forStore((int) $store->getId());
            }
        }

        // A website with no store views purges nothing of its own; falling back to the bare tag keeps
        // the save meaningful rather than a silent no-op.
        return $tags === [] ? [$this->estateWide()] : $tags;
    }
}
