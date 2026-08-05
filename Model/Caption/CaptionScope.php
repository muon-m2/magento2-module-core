<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Caption;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reports which store view an admin screen is currently scoped to.
 *
 * "Default scope" and "store view 0" are the same request but not the same idea: 0 is the admin
 * store, which exists as a row, so an unchecked getStore(0) would succeed and make an unscoped screen
 * look like a scoped one. Everything here funnels through getStoreId() returning 0 for "no scope",
 * and callers ask isDefaultScope() rather than comparing to 0 themselves.
 */
class CaptionScope
{
    /**
     * Request parameter Magento's store switcher writes.
     */
    public const STORE_PARAM = 'store';

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Get the store view the screen is scoped to.
     *
     * An unknown store id degrades to default scope rather than throwing: the parameter is in a URL a
     * merchant can edit or bookmark, and a 500 on a stale link is a worse answer than showing the
     * default values.
     *
     * @return int Zero when the screen is in default scope.
     */
    public function getStoreId(): int
    {
        $raw = $this->request->getParam(self::STORE_PARAM);

        // Digits only, not is_numeric(): that accepts "1.5" and "2e1", which cast to 1 and 2 and
        // would scope the screen to a store the merchant never asked for. Matches the same guard in
        // Muon_Core/js/grid/store-scoped-provider.
        if (!is_string($raw) && !is_int($raw)) {
            return 0;
        }

        if (preg_match('/^\d+$/', (string) $raw) !== 1) {
            return 0;
        }

        $storeId = (int) $raw;

        if ($storeId <= 0) {
            return 0;
        }

        try {
            $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException) {
            return 0;
        }

        return $storeId;
    }

    /**
     * Check whether the screen is editing default values rather than one store view's.
     *
     * @return bool
     */
    public function isDefaultScope(): bool
    {
        return $this->getStoreId() === 0;
    }
}
