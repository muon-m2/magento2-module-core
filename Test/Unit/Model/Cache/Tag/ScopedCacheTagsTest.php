<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model\Cache\Tag;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Muon\Core\Model\Cache\Tag\ScopedCacheTags;
use PHPUnit\Framework\TestCase;

class ScopedCacheTagsTest extends TestCase
{
    private const BASE = 'MUON_EXAMPLE';

    /**
     * @param array<int, int> $storeIdToWebsiteId
     */
    private function tags(array $storeIdToWebsiteId = []): ScopedCacheTags
    {
        $stores = [];
        foreach ($storeIdToWebsiteId as $storeId => $websiteId) {
            $store = $this->createStub(StoreInterface::class);
            $store->method('getId')->willReturn($storeId);
            $store->method('getWebsiteId')->willReturn($websiteId);
            $stores[] = $store;
        }

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);

        return new ScopedCacheTags($storeManager, self::BASE);
    }

    public function testStoreTagIsQualifiedByStoreId(): void
    {
        self::assertSame('MUON_EXAMPLE_7', $this->tags()->forStore(7));
    }

    public function testEstateWideTagIsTheBareBaseTag(): void
    {
        self::assertSame('MUON_EXAMPLE', $this->tags()->estateWide());
    }

    /**
     * A store-scoped save must not reach any other store view.
     */
    public function testStoreScopeEmitsOnlyThatStoresTag(): void
    {
        self::assertSame(
            ['MUON_EXAMPLE_3'],
            $this->tags([1 => 1, 3 => 2])->forSavedScope(ScopeInterface::SCOPE_STORES, 3)
        );
    }

    public function testWebsiteScopeExpandsToEveryStoreViewInThatWebsite(): void
    {
        $result = $this->tags([1 => 1, 2 => 2, 3 => 2, 4 => 3])
            ->forSavedScope(ScopeInterface::SCOPE_WEBSITES, 2);

        self::assertSame(['MUON_EXAMPLE_2', 'MUON_EXAMPLE_3'], $result);
    }

    /**
     * The default scope keeps the bare tag, which every rendered page still carries.
     */
    public function testDefaultScopeEmitsTheEstateWideTag(): void
    {
        self::assertSame(['MUON_EXAMPLE'], $this->tags([1 => 1])->forSavedScope('default', 0));
    }

    /**
     * A website with no store views must not purge nothing at all.
     */
    public function testWebsiteWithNoStoreViewsFallsBackToTheEstateWideTag(): void
    {
        self::assertSame(
            ['MUON_EXAMPLE'],
            $this->tags([1 => 1])->forSavedScope(ScopeInterface::SCOPE_WEBSITES, 99)
        );
    }

    /**
     * Two modules must not collide: the base tag is the whole namespace.
     */
    public function testDifferentBaseTagsDoNotCollide(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([]);

        $a = new ScopedCacheTags($storeManager, 'MUON_HEADERMENU');
        $b = new ScopedCacheTags($storeManager, 'MUON_TOPMENU');

        self::assertNotSame($a->forStore(1), $b->forStore(1));
        self::assertSame('MUON_HEADERMENU_1', $a->forStore(1));
        self::assertSame('MUON_TOPMENU_1', $b->forStore(1));
    }
}
