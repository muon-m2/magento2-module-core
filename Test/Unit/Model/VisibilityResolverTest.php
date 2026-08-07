<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model;

use DateTimeImmutable;
use DateTimeZone;
use Muon\Core\Api\Data\FilterableInterface;
use Muon\Core\Api\VisitorContextInterface;
use Muon\Core\Model\Validator\ScheduleWindow;
use Muon\Core\Model\VisibilityResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Whether one filterable thing may be shown to one visitor at one moment.
 *
 * EVERY RULE FAILS CLOSED, AND THAT DIRECTION IS THE POINT OF THIS FILE. An unrecognised visibility
 * value, an unparseable schedule bound, or a group allow-list that was never loaded all HIDE the
 * subject. Something that should have appeared and did not is a merchant support ticket; something
 * that should have been hidden and appeared is a disclosure. The asymmetric tests below exist so a
 * later "sensible default" cannot quietly invert that.
 *
 * Store assignment is deliberately absent — it is a join in the collection, because evaluating it
 * here would mean loading every row in the estate on every render. `device` is absent too: it hides
 * by CSS, renders identical markup for everyone, and must never be treated as access control.
 */
class VisibilityResolverTest extends TestCase
{
    /**
     * @return \Muon\Core\Model\VisibilityResolver
     */
    private function resolver(): VisibilityResolver
    {
        return new VisibilityResolver(new ScheduleWindow());
    }

    /**
     * A subject with the given filters.
     *
     * @param string $visibility
     * @param int[]|null $groups
     * @param string|null $from
     * @param string|null $to
     * @return \Muon\Core\Api\Data\FilterableInterface
     */
    private function subject(
        string $visibility = FilterableInterface::VISIBILITY_ANY,
        ?array $groups = [],
        ?string $from = null,
        ?string $to = null
    ): FilterableInterface {
        $subject = $this->createStub(FilterableInterface::class);
        $subject->method('getVisibility')->willReturn($visibility);
        $subject->method('getCustomerGroupIds')->willReturn($groups);
        $subject->method('getDevice')->willReturn(FilterableInterface::DEVICE_ALL);
        $subject->method('getActiveFrom')->willReturn($from);
        $subject->method('getActiveTo')->willReturn($to);

        return $subject;
    }

    /**
     * @param bool $loggedIn
     * @param int $groupId
     * @return \Muon\Core\Api\VisitorContextInterface
     */
    private function visitor(bool $loggedIn = false, int $groupId = 0): VisitorContextInterface
    {
        $visitor = $this->createStub(VisitorContextInterface::class);
        $visitor->method('isLoggedIn')->willReturn($loggedIn);
        $visitor->method('getCustomerGroupId')->willReturn($groupId);
        $visitor->method('getStoreId')->willReturn(1);
        $visitor->method('getCacheKeySuffix')->willReturn('s1_g' . $groupId . '_a' . ($loggedIn ? 1 : 0));

        return $visitor;
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
     * The common case: no restrictions at all.
     *
     * @return void
     */
    public function testAnUnrestrictedSubjectIsVisible(): void
    {
        self::assertTrue(
            $this->resolver()->isVisible($this->subject(), $this->visitor(), $this->utc('2026-06-01 12:00:00'))
        );
    }

    /**
     * @param string $visibility
     * @param bool $loggedIn
     * @param bool $expected
     * @return void
     */
    #[DataProvider('loginStateProvider')]
    public function testLoginStateFilter(string $visibility, bool $loggedIn, bool $expected): void
    {
        self::assertSame(
            $expected,
            $this->resolver()->isVisible(
                $this->subject($visibility),
                $this->visitor($loggedIn),
                $this->utc('2026-06-01 12:00:00')
            )
        );
    }

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function loginStateProvider(): array
    {
        return [
            'any + guest' => [FilterableInterface::VISIBILITY_ANY, false, true],
            'any + logged in' => [FilterableInterface::VISIBILITY_ANY, true, true],
            'guest-only + guest' => [FilterableInterface::VISIBILITY_GUEST, false, true],
            'guest-only + logged in' => [FilterableInterface::VISIBILITY_GUEST, true, false],
            'members-only + guest' => [FilterableInterface::VISIBILITY_LOGGED_IN, false, false],
            'members-only + logged in' => [FilterableInterface::VISIBILITY_LOGGED_IN, true, true],
        ];
    }

    /**
     * FAIL CLOSED. An unrecognised value can only arrive from outside the validator — a data patch, a
     * restored dump, a direct SQL edit. Hide rather than guess.
     *
     * @return void
     */
    public function testAnUnrecognisedVisibilityValueHides(): void
    {
        self::assertFalse(
            $this->resolver()->isVisible(
                $this->subject('members_only_probably'),
                $this->visitor(true),
                $this->utc('2026-06-01 12:00:00')
            )
        );
    }

    /**
     * An EMPTY allow-list means every group. It is not the same as "no groups" — group 0 is a real
     * group (NOT LOGGED IN), so it cannot double as an "all" sentinel the way store ID 0 does.
     *
     * @return void
     */
    public function testAnEmptyGroupAllowListMeansEveryGroup(): void
    {
        self::assertTrue(
            $this->resolver()->isVisible(
                $this->subject(groups: []),
                $this->visitor(groupId: 42),
                $this->utc('2026-06-01 12:00:00')
            )
        );
    }

    /**
     * @return void
     */
    public function testAGroupAllowListAdmitsOnlyItsGroups(): void
    {
        $resolver = $this->resolver();
        $now = $this->utc('2026-06-01 12:00:00');

        self::assertTrue(
            $resolver->isVisible($this->subject(groups: [1, 2]), $this->visitor(groupId: 2), $now)
        );
        self::assertFalse(
            $resolver->isVisible($this->subject(groups: [1, 2]), $this->visitor(groupId: 3), $now)
        );
    }

    /**
     * Group 0 is a real group and must be matchable — a "not logged in" allow-list is a legitimate
     * thing to write, and treating 0 as falsy anywhere in the comparison would silently break it.
     *
     * @return void
     */
    public function testGroupZeroIsAMatchableGroupAndNotASentinel(): void
    {
        self::assertTrue(
            $this->resolver()->isVisible(
                $this->subject(groups: [0]),
                $this->visitor(groupId: 0),
                $this->utc('2026-06-01 12:00:00')
            )
        );
    }

    /**
     * FAIL CLOSED, and this one is the subtle case. NULL means the assignment was never loaded —
     * on a render path that is a missing addAssignmentData() call, i.e. a programming error. Reading
     * it as "unrestricted" would turn a forgotten join into publishing every restricted row to
     * everyone, silently. Treat it as restricted so the mistake is visible instead.
     *
     * @return void
     */
    public function testANeverLoadedGroupAllowListHidesRatherThanPublishing(): void
    {
        self::assertFalse(
            $this->resolver()->isVisible(
                $this->subject(groups: null),
                $this->visitor(groupId: 1),
                $this->utc('2026-06-01 12:00:00')
            )
        );
    }

    /**
     * @param string|null $from
     * @param string|null $to
     * @param string $now
     * @param bool $expected
     * @return void
     */
    #[DataProvider('scheduleProvider')]
    public function testScheduleFilter(?string $from, ?string $to, string $now, bool $expected): void
    {
        self::assertSame(
            $expected,
            $this->resolver()->isVisible(
                $this->subject(from: $from, to: $to),
                $this->visitor(),
                $this->utc($now)
            )
        );
    }

    /**
     * @return array<string, array{string|null, string|null, string, bool}>
     */
    public static function scheduleProvider(): array
    {
        return [
            'no window' => [null, null, '2026-06-01 12:00:00', true],
            'inside' => ['2026-01-01 00:00:00', '2026-12-31 23:59:59', '2026-06-01 12:00:00', true],
            'before it opens' => ['2026-07-01 00:00:00', null, '2026-06-01 12:00:00', false],
            'after it closes' => [null, '2026-05-01 00:00:00', '2026-06-01 12:00:00', false],
            'at the opening instant' => ['2026-06-01 12:00:00', null, '2026-06-01 12:00:00', true],
            'at the closing instant' => [null, '2026-06-01 12:00:00', '2026-06-01 12:00:00', true],
            // FAIL CLOSED: a bound that is set but unreadable closes the window. Opening it would
            // leak a campaign whose schedule nobody can read.
            'unparseable start' => ['whenever', null, '2026-06-01 12:00:00', false],
            'unparseable end' => [null, 'whenever', '2026-06-01 12:00:00', false],
        ];
    }

    /**
     * Filters compose with AND: passing two of three is not passing.
     *
     * @return void
     */
    public function testFiltersComposeWithAnd(): void
    {
        $subject = $this->subject(
            FilterableInterface::VISIBILITY_LOGGED_IN,
            [1],
            '2026-01-01 00:00:00',
            '2026-12-31 23:59:59'
        );

        // Right group, right window, wrong login state.
        self::assertFalse(
            $this->resolver()->isVisible(
                $subject,
                $this->visitor(loggedIn: false, groupId: 1),
                $this->utc('2026-06-01 12:00:00')
            )
        );

        // All three satisfied.
        self::assertTrue(
            $this->resolver()->isVisible(
                $subject,
                $this->visitor(loggedIn: true, groupId: 1),
                $this->utc('2026-06-01 12:00:00')
            )
        );
    }

    /**
     * `device` IS NOT ACCESS CONTROL and must not be evaluated here. It renders identical markup for
     * everyone and hides by CSS, which is what keeps it free of any cache-key cost. If this ever
     * starts filtering server-side it becomes both a cache-fragmentation bug and an implied security
     * promise the code does not keep.
     *
     * @return void
     */
    public function testDeviceIsNotEvaluated(): void
    {
        $subject = $this->createStub(FilterableInterface::class);
        $subject->method('getVisibility')->willReturn(FilterableInterface::VISIBILITY_ANY);
        $subject->method('getCustomerGroupIds')->willReturn([]);
        $subject->method('getDevice')->willReturn(FilterableInterface::DEVICE_DESKTOP);
        $subject->method('getActiveFrom')->willReturn(null);
        $subject->method('getActiveTo')->willReturn(null);

        self::assertTrue(
            $this->resolver()->isVisible($subject, $this->visitor(), $this->utc('2026-06-01 12:00:00'))
        );
    }
}
