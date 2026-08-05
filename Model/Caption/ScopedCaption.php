<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Caption;

use Magento\Framework\Api\AbstractSimpleObject;
use Muon\Core\Api\Data\ScopedCaptionInterface;

/**
 * A store view and the caption it renders.
 */
class ScopedCaption extends AbstractSimpleObject implements ScopedCaptionInterface
{
    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return (int) $this->_get(self::STORE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setStoreId(int $storeId): static
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getCaption(): string
    {
        return (string) $this->_get(self::CAPTION);
    }

    /**
     * @inheritDoc
     */
    public function setCaption(string $caption): static
    {
        return $this->setData(self::CAPTION, $caption);
    }
}
