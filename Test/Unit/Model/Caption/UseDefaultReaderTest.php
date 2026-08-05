<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model\Caption;

use Muon\Core\Model\Caption\UseDefaultReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UseDefaultReaderTest extends TestCase
{
    /**
     * @var \Muon\Core\Model\Caption\UseDefaultReader
     */
    private UseDefaultReader $reader;

    protected function setUp(): void
    {
        $this->reader = new UseDefaultReader();
    }

    /**
     * @param mixed $flag
     * @param bool $expected
     * @return void
     */
    #[DataProvider('flagProvider')]
    public function testFlagIsReadFromEitherSubmissionMechanism(mixed $flag, bool $expected): void
    {
        self::assertSame(
            $expected,
            $this->reader->isDefaultRequested(['use_default' => ['label' => $flag]], 'label')
        );
    }

    /**
     * @return array<string,array{mixed,bool}>
     */
    public static function flagProvider(): array
    {
        return [
            // abstract.js::toggleUseDefault writes numbers.
            'js number one' => [1, true],
            'js number zero' => [0, false],
            // The same value arrives as a string once it has been through a POST body.
            'string one' => ['1', true],
            // THE TRAP: (bool) '0' is true, so a loose cast here inverts the whole feature.
            'string zero' => ['0', false],
            // A plain browser submit of the rendered checkbox sends "on".
            'checkbox on' => ['on', true],
            'boolean true' => [true, true],
            'boolean false' => [false, false],
            'empty string' => ['', false],
            'null' => [null, false],
        ];
    }

    public function testAbsentFieldIsNotADefaultRequest(): void
    {
        self::assertFalse($this->reader->isDefaultRequested(['use_default' => ['title' => '1']], 'label'));
    }

    public function testAbsentMapIsNotADefaultRequest(): void
    {
        self::assertFalse($this->reader->isDefaultRequested(['label' => 'Shop'], 'label'));
    }

    public function testNonArrayMapIsIgnoredRatherThanFatal(): void
    {
        self::assertFalse($this->reader->isDefaultRequested(['use_default' => 'yes'], 'label'));
    }
}
