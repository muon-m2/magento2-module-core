<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Caption;

use Muon\Core\Api\CaptionResolverInterface;

/**
 * Picks the store's override when it has one, otherwise the entity's own caption.
 *
 * On the storefront the same choice is made in SQL by the collections' COALESCE join, so this class
 * runs on the admin and API paths rather than per rendered menu entry. Both must agree: a blank
 * override falls through in COALESCE only because blanks are never stored (see CaptionStorage).
 */
class CaptionResolver implements CaptionResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(array $storeCaptions, int $storeId, string $default): string
    {
        $override = $storeCaptions[$storeId] ?? null;

        // Trim only decides emptiness — the override itself is returned exactly as the merchant
        // entered it, including any deliberate leading or trailing space.
        if (is_string($override) && trim($override) !== '') {
            return $override;
        }

        return $default;
    }
}
