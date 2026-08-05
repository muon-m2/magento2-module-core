<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Api;

/**
 * Chooses the caption a store view renders.
 *
 * @api Consumed by every module with per-store captions; an override changes fallback behaviour
 *      estate-wide.
 */
interface CaptionResolverInterface
{
    /**
     * Resolve the caption for one store view.
     *
     * THE DEFAULT IS RETURNED VERBATIM — it is never passed through the translator. A menu caption is
     * merchant-authored content, and routing it through __() would let an unrelated i18n entry
     * silently rewrite a caption whose text happens to collide with a translation key ("Sale",
     * "Home", "Back"). Store views that need different wording get an explicit override row; there is
     * no implicit second source.
     *
     * @param array<int,string> $storeCaptions Store id to caption.
     * @param int $storeId
     * @param string $default The entity's own caption.
     * @return string The override when one exists and is not blank, otherwise $default unchanged.
     */
    public function resolve(array $storeCaptions, int $storeId, string $default): string;
}
