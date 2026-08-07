<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model\Validator;

use DateTimeImmutable;
use DateTimeZone;
use Muon\Core\Model\Validator\ScheduleWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The schedule-window rule, shared by every consuming module's validators and renderers.
 *
 * THE TWO HALVES DISAGREE ON PURPOSE, and that is the thing worth pinning. validate() is a SAVE-time
 * check: it reports what the merchant got wrong and says nothing about whether the window is open.
 * contains() is a RENDER-time check, and it treats an unparseable bound as CLOSED — because the
 * failure direction that matters there is leaking a campaign early, not hiding one late. A single
 * "is this window valid" helper used for both would have to pick one, and would be wrong at the
 * other call site.
 *
 * Everything below the admin form is UTC; the form converts to and from the store view's local time.
 */
class ScheduleWindowTest extends TestCase
{
    /**
     * @return \Muon\Core\Model\Validator\ScheduleWindow
     */
    private function window(): ScheduleWindow
    {
        return new ScheduleWindow();
    }

    /**
     * @param string $value
     * @return \DateTimeImmutable
     */
    private function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    /**
     * No schedule at all is the common case and must be silent.
     *
     * @return void
     */
    public function testAnAbsentWindowIsValid(): void
    {
        self::assertSame([], $this->window()->validate(null, null));
    }

    /**
     * @param string|null $from
     * @param string|null $to
     * @return void
     */
    #[DataProvider('acceptableWindowProvider')]
    public function testAnAcceptableWindowReportsNothing(?string $from, ?string $to): void
    {
        self::assertSame([], $this->window()->validate($from, $to));
    }

    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function acceptableWindowProvider(): array
    {
        return [
            'ordered bounds' => ['2026-01-01 00:00:00', '2026-02-01 00:00:00'],
            'open-ended start' => ['2026-01-01 00:00:00', null],
            'open-ended end' => [null, '2026-02-01 00:00:00'],
            // Not an error: a window that opens and closes at the same instant is empty rather than
            // inverted, and the merchant may be building it one field at a time.
            'identical bounds' => ['2026-01-01 00:00:00', '2026-01-01 00:00:00'],
        ];
    }

    /**
     * An inverted window would never open, so it is worth saying so at save time rather than
     * leaving the merchant to wonder why nothing appeared.
     *
     * @return void
     */
    public function testAnInvertedWindowIsRejected(): void
    {
        self::assertSame(
            ['"Active From" must be earlier than "Active To".'],
            $this->window()->validate('2026-02-01 00:00:00', '2026-01-01 00:00:00')
        );
    }

    /**
     * Each bound is reported on its own, naming the field the merchant can see.
     *
     * @return void
     */
    public function testAnUnparseableStartIsRejected(): void
    {
        self::assertSame(
            ['"Active From" is not a valid date and time.'],
            $this->window()->validate('not a date', null)
        );
    }

    /**
     * @return void
     */
    public function testAnUnparseableEndIsRejected(): void
    {
        self::assertSame(
            ['"Active To" is not a valid date and time.'],
            $this->window()->validate(null, 'whenever')
        );
    }

    /**
     * Both wrong reports both — a merchant fixing one field per save is a worse experience than
     * being told everything at once.
     *
     * @return void
     */
    public function testBothBoundsAreReportedTogether(): void
    {
        self::assertCount(2, $this->window()->validate('nope', 'also nope'));
    }

    /**
     * An unparseable bound cannot also be compared, so the ordering rule must not fire on top of the
     * parse error and give the merchant two messages about one mistake.
     *
     * @return void
     */
    public function testAnUnparseableBoundDoesNotAlsoTripTheOrderingRule(): void
    {
        $errors = $this->window()->validate('rubbish', '2026-01-01 00:00:00');

        self::assertSame(['"Active From" is not a valid date and time.'], $errors);
    }

    /**
     * An EMPTY STRING is rejected as unparseable rather than read as "no bound". Both controllers
     * normalise "" to null before it reaches here, so this only bites a hand-built REST payload —
     * and rejecting it is the honest answer: an empty string is not a date, and silently treating it
     * as "no schedule" would discard a bound the caller believed they had set.
     *
     * @return void
     */
    public function testAnEmptyStringIsNotReadAsNoBound(): void
    {
        self::assertSame(
            ['"Active From" is not a valid date and time.'],
            $this->window()->validate('', null)
        );
    }

    /**
     * @param string|null $value
     * @return void
     */
    #[DataProvider('unparseableProvider')]
    public function testParseReturnsNullForAnythingItCannotRead(?string $value): void
    {
        self::assertNull($this->window()->parse($value));
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function unparseableProvider(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'whitespace' => ['   '],
            'prose' => ['sometime next week'],
        ];
    }

    /**
     * A parsed value is UTC, whatever the server's own timezone is.
     *
     * @return void
     */
    public function testParsingIsAnchoredToUtc(): void
    {
        $parsed = $this->window()->parse('2026-01-01 12:00:00');

        self::assertNotNull($parsed);
        self::assertSame('UTC', $parsed->getTimezone()->getName());
        self::assertSame('2026-01-01 12:00:00', $parsed->format('Y-m-d H:i:s'));
    }

    /**
     * @param string|null $from
     * @param string|null $to
     * @param string $now
     * @param bool $expected
     * @return void
     */
    #[DataProvider('containsProvider')]
    public function testContainsAnswersWhetherTheWindowIsOpen(
        ?string $from,
        ?string $to,
        string $now,
        bool $expected
    ): void {
        self::assertSame($expected, $this->window()->contains($from, $to, $this->utc($now)));
    }

    /**
     * @return array<string, array{string|null, string|null, string, bool}>
     */
    public static function containsProvider(): array
    {
        return [
            'no window is always open' => [null, null, '2026-01-15 00:00:00', true],
            'inside' => ['2026-01-01 00:00:00', '2026-02-01 00:00:00', '2026-01-15 00:00:00', true],
            'before it opens' => ['2026-01-01 00:00:00', null, '2025-12-31 23:59:59', false],
            'after it closes' => [null, '2026-02-01 00:00:00', '2026-02-01 00:00:01', false],
            'exactly at the opening instant' => ['2026-01-01 00:00:00', null, '2026-01-01 00:00:00', true],
            'exactly at the closing instant' => [null, '2026-02-01 00:00:00', '2026-02-01 00:00:00', true],
            // THE SAFETY DIRECTION: a bound that is set but unreadable closes the window. Opening it
            // would leak a campaign whose schedule nobody can read.
            'unparseable start closes it' => ['rubbish', null, '2026-01-15 00:00:00', false],
            'unparseable end closes it' => [null, 'rubbish', '2026-01-15 00:00:00', false],
        ];
    }
}
