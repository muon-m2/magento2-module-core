<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model\Caption;

use Muon\Core\Model\Caption\CaptionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CaptionResolverTest extends TestCase
{
    /**
     * @var \Muon\Core\Model\Caption\CaptionResolver
     */
    private CaptionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CaptionResolver();
    }

    public function testOverrideWinsForItsOwnStore(): void
    {
        self::assertSame('Einkaufen', $this->resolver->resolve([2 => 'Einkaufen'], 2, 'Shop'));
    }

    public function testAnotherStoresOverrideIsNotBorrowed(): void
    {
        self::assertSame('Shop', $this->resolver->resolve([2 => 'Einkaufen'], 7, 'Shop'));
    }

    /**
     * @param string $override
     * @return void
     */
    #[DataProvider('blankOverrideProvider')]
    public function testBlankOverrideFallsThroughToTheDefault(string $override): void
    {
        // A stored blank would render an empty menu entry. Falling through is also what the
        // storefront's COALESCE join does, because blanks are never persisted in the first place.
        self::assertSame('Shop', $this->resolver->resolve([2 => $override], 2, 'Shop'));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function blankOverrideProvider(): array
    {
        return [
            'empty string' => [''],
            'spaces' => ['   '],
            'tab and newline' => ["\t\n"],
        ];
    }

    /**
     * The user chose verbatim fallback over routing the default through __(). A default whose text
     * collides with a translation key must not be rewritten.
     */
    public function testDefaultIsReturnedByteForByteWithoutTranslation(): void
    {
        $default = 'Sale';

        self::assertSame($default, $this->resolver->resolve([], 5, $default));
    }

    public function testOverrideKeepsDeliberateSurroundingSpace(): void
    {
        // Trim decides emptiness only; it must not alter a value the merchant chose to pad.
        self::assertSame('  Sale  ', $this->resolver->resolve([2 => '  Sale  '], 2, 'Shop'));
    }

    public function testMultibyteOverrideSurvivesIntact(): void
    {
        self::assertSame('ショップ', $this->resolver->resolve([3 => 'ショップ'], 3, 'Shop'));
    }
}
