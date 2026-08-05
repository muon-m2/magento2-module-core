<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model\Caption;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Muon\Core\Model\Caption\CaptionValidator;
use Muon\Core\Model\Caption\ScopedCaption;
use PHPUnit\Framework\TestCase;

class CaptionValidatorTest extends TestCase
{
    /**
     * Store ids this fake estate recognises.
     */
    private const KNOWN_STORES = [1, 2, 3];

    /**
     * @var \Muon\Core\Model\Caption\CaptionValidator
     */
    private CaptionValidator $validator;

    protected function setUp(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(
            function (int|string|null $storeId): StoreInterface {
                if (!in_array((int) $storeId, self::KNOWN_STORES, true)) {
                    throw new NoSuchEntityException(__('No such store.'));
                }

                return $this->createStub(StoreInterface::class);
            }
        );

        $this->validator = new CaptionValidator($storeManager);
    }

    /**
     * @param int $storeId
     * @param string $caption
     * @return \Muon\Core\Model\Caption\ScopedCaption
     */
    private function caption(int $storeId, string $caption): ScopedCaption
    {
        return (new ScopedCaption())->setStoreId($storeId)->setCaption($caption);
    }

    public function testNotSuppliedIsAccepted(): void
    {
        self::assertSame([], $this->validator->validate(null));
    }

    public function testEmptySetIsAccepted(): void
    {
        // An empty set is "clear every override", which is a legitimate instruction.
        self::assertSame([], $this->validator->validate([]));
    }

    public function testValidSetIsAccepted(): void
    {
        self::assertSame([], $this->validator->validate([
            $this->caption(1, 'Shop'),
            $this->caption(2, 'Einkaufen'),
        ]));
    }

    public function testStoreZeroIsRejected(): void
    {
        $errors = $this->validator->validate([$this->caption(0, 'Shop')]);

        self::assertCount(1, $errors);
        self::assertStringContainsString('specific store view', $errors[0]);
    }

    public function testUnknownStoreIsRejectedNamingTheId(): void
    {
        $errors = $this->validator->validate([$this->caption(9999, 'Shop')]);

        self::assertCount(1, $errors);
        self::assertStringContainsString('9999', $errors[0]);
    }

    public function testTwoCaptionsForOneStoreAreRejected(): void
    {
        $errors = $this->validator->validate([
            $this->caption(2, 'Einkaufen'),
            $this->caption(2, 'Kaufen'),
        ]);

        self::assertCount(1, $errors);
        self::assertStringContainsString('more than one caption', $errors[0]);
    }

    public function testCaptionAtTheColumnLimitIsAccepted(): void
    {
        self::assertSame(
            [],
            $this->validator->validate([$this->caption(1, str_repeat('a', CaptionValidator::MAX_LENGTH))])
        );
    }

    public function testCaptionOneCharacterOverTheLimitIsRejected(): void
    {
        // The column is varchar(255); without this check MySQL truncates silently.
        $errors = $this->validator->validate([
            $this->caption(1, str_repeat('a', CaptionValidator::MAX_LENGTH + 1)),
        ]);

        self::assertCount(1, $errors);
        self::assertStringContainsString('longer than', $errors[0]);
    }

    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        // A multibyte caption at the character limit fits the column; strlen() would reject it.
        self::assertSame(
            [],
            $this->validator->validate([$this->caption(1, str_repeat('ショ', 100))])
        );
    }

    public function testBlankCaptionIsAcceptedBecauseItMeansRemoveTheOverride(): void
    {
        self::assertSame([], $this->validator->validate([$this->caption(2, '   ')]));
    }

    public function testEveryProblemIsReportedNotJustTheFirst(): void
    {
        // The header module's Save controller shows every message at once so a merchant fixes one
        // form pass rather than one field per round trip.
        $errors = $this->validator->validate([
            $this->caption(0, 'Shop'),
            $this->caption(9999, 'Shop'),
            $this->caption(1, str_repeat('a', CaptionValidator::MAX_LENGTH + 1)),
        ]);

        self::assertCount(3, $errors);
    }

    public function testMalformedEntryIsReportedRatherThanFatal(): void
    {
        /** @phpstan-ignore-next-line intentionally passing a wrong type */
        $errors = $this->validator->validate(['not a caption']);

        self::assertCount(1, $errors);
        self::assertStringContainsString('malformed', $errors[0]);
    }
}
