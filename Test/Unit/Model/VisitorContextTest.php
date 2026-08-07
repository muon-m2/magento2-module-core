<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model;

use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\GroupManagement;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Muon\Core\Model\VisitorContext;
use PHPUnit\Framework\TestCase;

/**
 * Who is looking, expressed as the three dimensions everything caches by.
 *
 * THE SOURCE IS THE SUBJECT UNDER TEST, not just the values. Every value must come from
 * `App\Http\Context` and never from the customer session — on a cacheable page
 * `DepersonalizePlugin` empties that session after layout generation, and only HTTP-context values
 * reach `Context::getVaryString()`, which keys the full-page cache. A group-aware fragment built
 * from a session read would be cached once and served to every group. The tests below pass a stub
 * `HttpContext` and no session at all, so a future "simplification" to a session read cannot make
 * them pass.
 */
class VisitorContextTest extends TestCase
{
    /**
     * Build the subject over a fixed HTTP context and store.
     *
     * @param array<string, mixed> $contextValues
     * @param int|null $storeId Null makes the store manager throw, as it does in admin scope.
     * @return \Muon\Core\Model\VisitorContext
     */
    private function visitor(array $contextValues = [], ?int $storeId = 1): VisitorContext
    {
        $httpContext = $this->createStub(HttpContext::class);
        $httpContext->method('getValue')->willReturnCallback(
            static fn (string $key): mixed => $contextValues[$key] ?? null
        );

        $storeManager = $this->createStub(StoreManagerInterface::class);

        if ($storeId === null) {
            $storeManager->method('getStore')->willThrowException(new NoSuchEntityException());
        } else {
            $store = $this->createStub(StoreInterface::class);
            $store->method('getId')->willReturn($storeId);
            $storeManager->method('getStore')->willReturn($store);
        }

        return new VisitorContext($httpContext, $storeManager);
    }

    /**
     * @return void
     */
    public function testStoreIdComesFromTheStoreManager(): void
    {
        self::assertSame(7, $this->visitor([], 7)->getStoreId());
    }

    /**
     * No resolvable store means no content rather than a fatal in a page fragment. Admin-scope
     * callers — a cron, a config save — legitimately land here.
     *
     * @return void
     */
    public function testAnUnresolvableStoreFallsBackToZeroRatherThanThrowing(): void
    {
        self::assertSame(0, $this->visitor([], null)->getStoreId());
    }

    /**
     * @return void
     */
    public function testCustomerGroupComesFromTheHttpContext(): void
    {
        $visitor = $this->visitor([CustomerContext::CONTEXT_GROUP => 3]);

        self::assertSame(3, $visitor->getCustomerGroupId());
    }

    /**
     * A REQUEST WITHOUT THE CONTEXT VALUE IS A GUEST, not an unknown. Group 0 IS the NOT LOGGED IN
     * group — a real group — so there is no spare sentinel for "unknown" and nothing to represent
     * it with. Defaulting anywhere else would silently move guests into another group's bucket.
     *
     * @return void
     */
    public function testAMissingGroupValueMeansNotLoggedIn(): void
    {
        self::assertSame(
            (int) GroupManagement::NOT_LOGGED_IN_ID,
            $this->visitor()->getCustomerGroupId()
        );
    }

    /**
     * The context stores its values as strings; the contract returns int.
     *
     * @return void
     */
    public function testAStringGroupValueIsReturnedAsAnInt(): void
    {
        $visitor = $this->visitor([CustomerContext::CONTEXT_GROUP => '4']);

        self::assertSame(4, $visitor->getCustomerGroupId());
    }

    /**
     * @return void
     */
    public function testLoggedInIsReadFromTheAuthContextValue(): void
    {
        self::assertTrue($this->visitor([CustomerContext::CONTEXT_AUTH => 1])->isLoggedIn());
        self::assertFalse($this->visitor([CustomerContext::CONTEXT_AUTH => 0])->isLoggedIn());
        self::assertFalse($this->visitor()->isLoggedIn());
    }

    /**
     * THE CACHE KEY SUFFIX IS THE ISOLATION GUARANTEE. Two visitors sharing a suffix are guaranteed
     * to be allowed to see the same content, so every dimension that can change what is visible must
     * appear in it. A collision here is a disclosure across customer groups, not a cosmetic bug.
     *
     * @return void
     */
    public function testTheCacheKeySuffixCarriesAllThreeDimensions(): void
    {
        $visitor = $this->visitor([
            CustomerContext::CONTEXT_GROUP => 2,
            CustomerContext::CONTEXT_AUTH => 1,
        ], 5);

        self::assertSame('s5_g2_a1', $visitor->getCacheKeySuffix());
    }

    /**
     * @return void
     */
    public function testVisitorsDifferingInAnyDimensionGetDifferentSuffixes(): void
    {
        $base = $this->visitor([
            CustomerContext::CONTEXT_GROUP => 2,
            CustomerContext::CONTEXT_AUTH => 1,
        ], 5)->getCacheKeySuffix();

        $otherStore = $this->visitor([
            CustomerContext::CONTEXT_GROUP => 2,
            CustomerContext::CONTEXT_AUTH => 1,
        ], 6)->getCacheKeySuffix();

        $otherGroup = $this->visitor([
            CustomerContext::CONTEXT_GROUP => 3,
            CustomerContext::CONTEXT_AUTH => 1,
        ], 5)->getCacheKeySuffix();

        $otherAuth = $this->visitor([
            CustomerContext::CONTEXT_GROUP => 2,
            CustomerContext::CONTEXT_AUTH => 0,
        ], 5)->getCacheKeySuffix();

        self::assertCount(4, array_unique([$base, $otherStore, $otherGroup, $otherAuth]));
    }

    /**
     * The instance is shared by the object manager, so the store memo must not survive into the next
     * request — a long-running worker would otherwise answer with the previous request's store.
     *
     * @return void
     */
    public function testResetStateClearsTheMemoisedStore(): void
    {
        // A store manager that answers differently each time it is actually consulted. That is the
        // only way to tell a memo hit from a fresh read — a stub with one fixed answer would pass
        // this test whether the memo existed, worked, or was never cleared.
        $ids = [9, 11];
        $store = $this->createStub(StoreInterface::class);
        // A full closure with `use (&$ids)`, NOT an arrow function: `fn` captures by value, so
        // array_shift() would pop from a fresh copy on every call and the stub would answer 9
        // forever — making the memo assertion below vacuous instead of failing.
        $store->method('getId')->willReturnCallback(
            static function () use (&$ids): int {
                return array_shift($ids) ?? 0;
            }
        );
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $visitor = new VisitorContext($this->createStub(HttpContext::class), $storeManager);

        self::assertSame(9, $visitor->getStoreId());
        self::assertSame(9, $visitor->getStoreId(), 'The second read must come from the memo.');

        $visitor->_resetState();

        self::assertSame(11, $visitor->getStoreId(), 'After a reset the store is read again.');
    }
}
