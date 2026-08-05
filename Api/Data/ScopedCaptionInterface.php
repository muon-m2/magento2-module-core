<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Api\Data;

/**
 * One store view's override of an entity's default caption.
 *
 * @api Serialised into the Web API payload of every module declaring a store_captions attribute.
 */
interface ScopedCaptionInterface
{
    /**
     * Store id field name.
     */
    public const STORE_ID = 'store_id';

    /**
     * Caption field name.
     */
    public const CAPTION = 'caption';

    /**
     * Get the store view this caption applies to.
     *
     * Never 0: store 0 means "the default", which lives on the entity's own column rather than in
     * an override row.
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Set the store view this caption applies to.
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): static;

    /**
     * Get the caption rendered in this store view.
     *
     * @return string
     */
    public function getCaption(): string;

    /**
     * Set the caption rendered in this store view.
     *
     * @param string $caption
     * @return $this
     */
    public function setCaption(string $caption): static;
}
