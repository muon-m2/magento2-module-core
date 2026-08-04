<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model\Style;

use Muon\Core\Model\Style\CssValueSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The sanitizer is the only thing standing between a merchant-entered appearance value and a
 * `<style>` block on every page of the store, so the rejection cases matter more than the
 * acceptance ones.
 *
 * @see \Muon\Core\Model\Style\CssValueSanitizer
 */
class CssValueSanitizerTest extends TestCase
{
    private CssValueSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new CssValueSanitizer();
    }

    /**
     * @param string $value
     * @param string|null $expected
     */
    #[DataProvider('colorProvider')]
    public function testColorAcceptsOnlyKnownGoodShapes(string $value, ?string $expected): void
    {
        self::assertSame($expected, $this->sanitizer->color($value));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function colorProvider(): array
    {
        return [
            'short hex' => ['#abc', '#abc'],
            'long hex' => ['#1E2939', '#1e2939'],
            'hex with alpha' => ['#1e293980', '#1e293980'],
            'rgb function' => ['rgb(30, 41, 57)', 'rgb(30, 41, 57)'],
            'rgba function' => ['rgba(30, 41, 57, 0.5)', 'rgba(30, 41, 57, 0.5)'],
            'hsl function' => ['hsl(210, 30%, 20%)', 'hsl(210, 30%, 20%)'],
            'keyword' => ['transparent', 'transparent'],
            'empty' => ['', null],
            'unknown keyword' => ['rebeccapurple', null],
            // The reason this class exists: each of these would otherwise close the declaration.
            'declaration breakout' => ['red;} body { display:none } </style><img src=x onerror=alert(1)>', null],
            'brace injection' => ['red}', null],
            'semicolon injection' => ['red;', null],
            'expression' => ['expression(alert(1))', null],
            'url function' => ['url(javascript:alert(1))', null],
            'comment breakout' => ['red/*', null],
        ];
    }

    /**
     * @param string $value
     * @param string|null $expected
     */
    #[DataProvider('pixelProvider')]
    public function testPixelsClampsAndRejects(string $value, ?string $expected): void
    {
        self::assertSame($expected, $this->sanitizer->pixels($value, 10, 32));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function pixelProvider(): array
    {
        return [
            'in range' => ['16', '16px'],
            'below minimum clamps' => ['2', '10px'],
            'above maximum clamps' => ['900', '32px'],
            'empty' => ['', null],
            'non-numeric' => ['16px', null],
            'negative' => ['-4', null],
            'injection' => ['16;}body{', null],
        ];
    }

    /**
     * @param string $value
     * @param string|null $expected
     */
    #[DataProvider('fontWeightProvider')]
    public function testFontWeightAcceptsOnlyTheNineKeywords(string $value, ?string $expected): void
    {
        self::assertSame($expected, $this->sanitizer->fontWeight($value));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function fontWeightProvider(): array
    {
        return [
            'numeric weight' => ['700', '700'],
            'lightest' => ['100', '100'],
            'bold keyword rejected' => ['bold', null],
            'out of set' => ['750', null],
            'injection' => ['700;}', null],
        ];
    }

    /**
     * @param string $value
     * @param string[] $allowed
     * @param string $default
     * @param string $expected
     */
    #[DataProvider('keywordProvider')]
    public function testKeywordFallsBackToTheDefault(
        string $value,
        array $allowed,
        string $default,
        string $expected
    ): void {
        self::assertSame($expected, $this->sanitizer->keyword($value, $allowed, $default));
    }

    /**
     * @return array<string, array{0: string, 1: string[], 2: string, 3: string}>
     */
    public static function keywordProvider(): array
    {
        return [
            'known keyword' => ['grid', ['grid', 'columns'], 'grid', 'grid'],
            'other known keyword' => ['columns', ['grid', 'columns'], 'grid', 'columns'],
            'unknown falls back' => ['exploded', ['grid', 'columns'], 'grid', 'grid'],
            'empty falls back' => ['', ['grid', 'columns'], 'grid', 'grid'],
        ];
    }
}
