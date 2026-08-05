<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model\Caption;

use Muon\Core\Api\Data\ScopedCaptionInterfaceFactory;
use Muon\Core\Model\Caption\CaptionListConverter;
use Muon\Core\Model\Caption\ScopedCaption;
use PHPUnit\Framework\TestCase;

class CaptionListConverterTest extends TestCase
{
    /**
     * @var \Muon\Core\Model\Caption\CaptionListConverter
     */
    private CaptionListConverter $converter;

    protected function setUp(): void
    {
        $factory = $this->createStub(ScopedCaptionInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static fn (): ScopedCaption => new ScopedCaption());

        $this->converter = new CaptionListConverter($factory);
    }

    /**
     * The distinction the whole partial-update contract rests on: null must survive as null.
     */
    public function testNullIsPreservedRatherThanFlattenedToAnEmptyArray(): void
    {
        self::assertNull($this->converter->toMap(null));
    }

    public function testEmptyListBecomesAnEmptyMapNotNull(): void
    {
        self::assertSame([], $this->converter->toMap([]));
    }

    public function testDtoListBecomesAStoreKeyedMap(): void
    {
        $map = $this->converter->toMap([
            (new ScopedCaption())->setStoreId(2)->setCaption('Einkaufen'),
            (new ScopedCaption())->setStoreId(3)->setCaption('ショップ'),
        ]);

        self::assertSame([2 => 'Einkaufen', 3 => 'ショップ'], $map);
    }

    public function testForeignEntriesAreSkippedRatherThanFatal(): void
    {
        $map = $this->converter->toMap([
            (new ScopedCaption())->setStoreId(2)->setCaption('Einkaufen'),
            'not a caption',
        ]);

        self::assertSame([2 => 'Einkaufen'], $map);
    }

    public function testMapBecomesADtoList(): void
    {
        $captions = $this->converter->fromMap([2 => 'Einkaufen', 3 => 'ショップ']);

        self::assertCount(2, $captions);
        self::assertSame(2, $captions[0]->getStoreId());
        self::assertSame('Einkaufen', $captions[0]->getCaption());
        self::assertSame(3, $captions[1]->getStoreId());
        self::assertSame('ショップ', $captions[1]->getCaption());
    }

    public function testRoundTripIsLossless(): void
    {
        $map = [2 => 'Einkaufen', 3 => 'ショップ', 11 => 'Boutique'];

        self::assertSame($map, $this->converter->toMap($this->converter->fromMap($map)));
    }
}
