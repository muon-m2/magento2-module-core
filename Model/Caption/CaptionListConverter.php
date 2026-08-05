<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Caption;

use Muon\Core\Api\Data\ScopedCaptionInterface;
use Muon\Core\Api\Data\ScopedCaptionInterfaceFactory;

/**
 * Converts between the two shapes a caption set takes.
 *
 * The API and the entity speak ScopedCaptionInterface[] because that is what serialises into a REST
 * payload; storage speaks a plain store-id-to-caption map because a DTO in a persistence layer is
 * noise. Three resource models need the conversion in both directions, so it lives here rather than
 * three times over.
 */
class CaptionListConverter
{
    /**
     * @param \Muon\Core\Api\Data\ScopedCaptionInterfaceFactory $captionFactory
     */
    public function __construct(
        private readonly ScopedCaptionInterfaceFactory $captionFactory
    ) {
    }

    /**
     * Reduce a DTO list to the map storage writes.
     *
     * Null is preserved rather than flattened to an empty array: the difference is what keeps a
     * partial REST update from wiping existing captions.
     *
     * @param \Muon\Core\Api\Data\ScopedCaptionInterface[]|null $captions
     * @return array<int,string>|null
     */
    public function toMap(?array $captions): ?array
    {
        if ($captions === null) {
            return null;
        }

        $map = [];

        foreach ($captions as $caption) {
            if ($caption instanceof ScopedCaptionInterface) {
                $map[$caption->getStoreId()] = $caption->getCaption();
            }
        }

        return $map;
    }

    /**
     * Build a DTO list from a stored map.
     *
     * @param array<int,string> $map
     * @return \Muon\Core\Api\Data\ScopedCaptionInterface[]
     */
    public function fromMap(array $map): array
    {
        $captions = [];

        foreach ($map as $storeId => $caption) {
            /** @var \Muon\Core\Api\Data\ScopedCaptionInterface $dto */
            $dto = $this->captionFactory->create();
            $captions[] = $dto->setStoreId((int) $storeId)->setCaption((string) $caption);
        }

        return $captions;
    }
}
