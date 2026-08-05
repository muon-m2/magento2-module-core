<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model\Caption;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Muon\Core\Model\Caption\CaptionStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Reads use a stub and writes use a mock, deliberately: PHPUnit 12 emits a notice for a mock that
 * carries no expectations, and only the write tests have anything to expect.
 */
class CaptionStorageTest extends TestCase
{
    private const TABLE = 'muon_headermenu_item_caption';
    private const PHYSICAL_TABLE = 'pfx_' . self::TABLE;
    private const ENTITY_COLUMN = 'item_id';
    private const CAPTION_COLUMN = 'label';

    /**
     * Wire a connection into the class under test.
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return \Muon\Core\Model\Caption\CaptionStorage
     */
    private function storage(AdapterInterface $connection): CaptionStorage
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnCallback(
            static fn (string $table): string => 'pfx_' . $table
        );

        return new CaptionStorage($resource, self::TABLE, self::ENTITY_COLUMN, self::CAPTION_COLUMN);
    }

    /**
     * Stub the fluent bits every path needs, on whichever double the caller is building.
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return void
     */
    private function stubQueryBuilding(AdapterInterface $connection): void
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection->method('select')->willReturn($select);
        $connection->method('quoteIdentifier')->willReturnCallback(
            static fn (string $identifier): string => '`' . $identifier . '`'
        );
    }

    /**
     * A read-only connection returning fixed rows.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private function reading(array $rows): AdapterInterface
    {
        $connection = $this->createStub(AdapterInterface::class);
        $this->stubQueryBuilding($connection);
        $connection->method('fetchAll')->willReturn($rows);

        return $connection;
    }

    /**
     * A connection the caller will set expectations on.
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function writing(): AdapterInterface&MockObject
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->stubQueryBuilding($connection);

        return $connection;
    }

    public function testLoadForEntitiesGroupsByEntityThenStore(): void
    {
        $storage = $this->storage($this->reading([
            ['item_id' => '5', 'store_id' => '2', 'label' => 'Einkaufen'],
            ['item_id' => '5', 'store_id' => '3', 'label' => 'ショップ'],
            ['item_id' => '9', 'store_id' => '2', 'label' => 'Kasse'],
        ]));

        self::assertSame(
            [
                5 => [2 => 'Einkaufen', 3 => 'ショップ'],
                9 => [2 => 'Kasse'],
            ],
            $storage->loadForEntities([5, 9])
        );
    }

    /**
     * The property that keeps admin grids and REST list endpoints off an N+1.
     */
    public function testLoadForEntitiesIssuesOneQueryRegardlessOfIdCount(): void
    {
        $connection = $this->writing();
        $connection->expects(self::once())->method('fetchAll')->willReturn([]);

        $this->storage($connection)->loadForEntities(range(1, 200));
    }

    public function testEntitiesWithNoOverridesAreAbsentRatherThanEmpty(): void
    {
        $storage = $this->storage($this->reading([
            ['item_id' => '5', 'store_id' => '2', 'label' => 'Einkaufen'],
        ]));

        self::assertArrayNotHasKey(9, $storage->loadForEntities([5, 9]));
    }

    public function testLoadForEntitiesShortCircuitsOnAnEmptyIdList(): void
    {
        $connection = $this->writing();
        $connection->expects(self::never())->method('fetchAll');

        self::assertSame([], $this->storage($connection)->loadForEntities([]));
    }

    public function testNonPositiveIdsAreDiscardedBeforeQuerying(): void
    {
        $connection = $this->writing();
        $connection->expects(self::never())->method('fetchAll');

        self::assertSame([], $this->storage($connection)->loadForEntities([0, -3]));
    }

    public function testLoadForStoreKeysByEntity(): void
    {
        $storage = $this->storage($this->reading([
            ['item_id' => '5', 'label' => 'Einkaufen'],
            ['item_id' => '9', 'label' => 'Kasse'],
        ]));

        self::assertSame([5 => 'Einkaufen', 9 => 'Kasse'], $storage->loadForStore([5, 9], 2));
    }

    public function testLoadForStoreRefusesTheDefaultScope(): void
    {
        // Store 0's caption is the entity's own column; there is nothing here to read.
        $connection = $this->writing();
        $connection->expects(self::never())->method('fetchAll');

        self::assertSame([], $this->storage($connection)->loadForStore([5], 0));
    }

    /**
     * The nullable contract: null means "the caller did not mention captions".
     */
    public function testSaveWithNullTouchesNothing(): void
    {
        $connection = $this->writing();
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('insertOnDuplicate');

        $this->storage($connection)->save(5, null);
    }

    /**
     * ...and the empty array means "the caller supplied an empty set".
     */
    public function testSaveWithEmptyArrayClearsEveryOverride(): void
    {
        $connection = $this->writing();
        $connection->expects(self::once())
            ->method('delete')
            ->with(self::PHYSICAL_TABLE, ['`item_id` = ?' => 5]);
        $connection->expects(self::never())->method('insertOnDuplicate');

        $this->storage($connection)->save(5, []);
    }

    public function testSaveDeletesOnlyTheStoresTheNewSetDoesNotCover(): void
    {
        $connection = $this->writing();
        $connection->expects(self::once())
            ->method('delete')
            ->with(
                self::PHYSICAL_TABLE,
                ['`item_id` = ?' => 5, '`store_id` NOT IN (?)' => [2, 3]]
            );
        $connection->expects(self::once())
            ->method('insertOnDuplicate')
            ->with(
                self::PHYSICAL_TABLE,
                [
                    ['item_id' => 5, 'store_id' => 2, 'label' => 'Einkaufen'],
                    ['item_id' => 5, 'store_id' => 3, 'label' => 'ショップ'],
                ],
                [self::CAPTION_COLUMN]
            );

        $this->storage($connection)->save(5, [2 => 'Einkaufen', 3 => 'ショップ']);
    }

    public function testBlankCaptionIsDroppedRatherThanStored(): void
    {
        // Clearing the field in the admin form must remove the override, not persist an empty
        // string that would render an empty menu entry.
        $connection = $this->writing();
        $connection->expects(self::once())
            ->method('insertOnDuplicate')
            ->with(
                self::PHYSICAL_TABLE,
                [['item_id' => 5, 'store_id' => 2, 'label' => 'Einkaufen']],
                [self::CAPTION_COLUMN]
            );

        $this->storage($connection)->save(5, [2 => 'Einkaufen', 3 => '   ']);
    }

    public function testNonPositiveStoreIsDroppedRatherThanStored(): void
    {
        // CaptionValidator is what tells the merchant; storage just refuses to create a row no
        // resolver would ever read.
        $connection = $this->writing();
        $connection->expects(self::once())
            ->method('delete')
            ->with(self::PHYSICAL_TABLE, ['`item_id` = ?' => 5]);
        $connection->expects(self::never())->method('insertOnDuplicate');

        $this->storage($connection)->save(5, [0 => 'Shop']);
    }

    public function testDeleteForEntityRemovesEveryStore(): void
    {
        $connection = $this->writing();
        $connection->expects(self::once())
            ->method('delete')
            ->with(self::PHYSICAL_TABLE, ['`item_id` = ?' => 5]);

        $this->storage($connection)->deleteForEntity(5);
    }
}
