<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Caption;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Muon\Core\Api\Data\ScopedCaptionInterface;

/**
 * Checks a set of caption overrides before it reaches the database.
 *
 * ERRORS ARE ACCUMULATED AND RETURNED, NOT THROWN. The two consuming modules disagree on style —
 * Muon_HeaderMenu's validators collect messages so a merchant fixes one form pass, Muon_FooterMenu's
 * throw on the first problem. Returning the list satisfies both: a caller that wants to throw throws
 * on element zero, while a caller that wants every message already has them.
 */
class CaptionValidator
{
    /**
     * Matches the varchar(255) every caption column declares. Enforced here so an over-long value
     * produces a readable message rather than a silent MySQL truncation.
     */
    public const MAX_LENGTH = 255;

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Collect the problems with a set of caption overrides.
     *
     * A blank caption is NOT an error — it means "remove this override", which is what clearing the
     * field in the admin form should do.
     *
     * @param \Muon\Core\Api\Data\ScopedCaptionInterface[]|null $captions Null means "not supplied".
     * @return string[] Empty when the set is acceptable.
     */
    public function validate(?array $captions): array
    {
        if ($captions === null || $captions === []) {
            return [];
        }

        $errors = [];
        $seen = [];

        foreach ($captions as $caption) {
            if (!$caption instanceof ScopedCaptionInterface) {
                $errors[] = (string) __('A store caption entry is malformed.');

                continue;
            }

            $storeId = $caption->getStoreId();

            foreach ($this->problemsWith($caption, $storeId, isset($seen[$storeId])) as $problem) {
                $errors[] = $problem;
            }

            $seen[$storeId] = true;
        }

        return $errors;
    }

    /**
     * Collect the problems with one caption entry.
     *
     * @param \Muon\Core\Api\Data\ScopedCaptionInterface $caption
     * @param int $storeId
     * @param bool $isDuplicate
     * @return string[]
     */
    private function problemsWith(ScopedCaptionInterface $caption, int $storeId, bool $isDuplicate): array
    {
        $problems = [];

        if ($storeId <= 0) {
            // Store 0 is All Store Views, whose caption IS the entity's own. Accepting it would
            // create a second, competing default that nothing reads.
            $problems[] = (string) __('A store caption must name a specific store view.');
        } elseif (!$this->storeExists($storeId)) {
            $problems[] = (string) __('Store view with ID %1 does not exist.', $storeId);
        }

        if ($isDuplicate) {
            $problems[] = (string) __('Store view with ID %1 has more than one caption.', $storeId);
        }

        if (mb_strlen(trim($caption->getCaption())) > self::MAX_LENGTH) {
            $problems[] = (string) __(
                'The caption for store view %1 is longer than %2 characters.',
                $storeId,
                self::MAX_LENGTH
            );
        }

        return $problems;
    }

    /**
     * Check that a store view exists.
     *
     * @param int $storeId
     * @return bool
     */
    private function storeExists(int $storeId): bool
    {
        try {
            $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException) {
            return false;
        }

        return true;
    }
}
