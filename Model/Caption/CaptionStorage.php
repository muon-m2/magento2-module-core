<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Caption;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Muon\Core\Api\CaptionStorageInterface;

/**
 * Caption persistence for one entity type, configured by DI rather than by argument.
 *
 * THE THREE IDENTIFIER STRINGS BELOW ARE WIRING, NOT INPUT. They arrive only from a di.xml virtual
 * type, never from a method parameter and never from a request, and every use passes through
 * quoteIdentifier(). Do not widen the constructor or add a setter to make them "flexible" — that is
 * exactly the change that would turn configuration into an injection surface. Values are bound
 * separately in every statement.
 */
class CaptionStorage implements CaptionStorageInterface
{
    /**
     * The store column is fixed by convention across every caption table, so it is not configurable.
     */
    private const STORE_COLUMN = 'store_id';

    /**
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param string $table Caption table name, unprefixed.
     * @param string $entityColumn Column holding the owning entity's id.
     * @param string $captionColumn Column holding the caption text.
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly string $table,
        private readonly string $entityColumn,
        private readonly string $captionColumn
    ) {
    }

    /**
     * @inheritDoc
     */
    public function loadForEntities(array $entityIds): array
    {
        $ids = $this->normalizeIds($entityIds);

        if ($ids === []) {
            return [];
        }

        $connection = $this->connection();
        $select = $connection->select()
            ->from(
                $this->tableName(),
                [$this->entityColumn, self::STORE_COLUMN, $this->captionColumn]
            )
            ->where($connection->quoteIdentifier($this->entityColumn) . ' IN (?)', $ids);

        $grouped = [];

        foreach ($connection->fetchAll($select) as $row) {
            $grouped[(int) $row[$this->entityColumn]][(int) $row[self::STORE_COLUMN]] =
                (string) $row[$this->captionColumn];
        }

        return $grouped;
    }

    /**
     * @inheritDoc
     */
    public function loadForStore(array $entityIds, int $storeId): array
    {
        $ids = $this->normalizeIds($entityIds);

        if ($ids === [] || $storeId <= 0) {
            return [];
        }

        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->tableName(), [$this->entityColumn, $this->captionColumn])
            ->where($connection->quoteIdentifier($this->entityColumn) . ' IN (?)', $ids)
            ->where($connection->quoteIdentifier(self::STORE_COLUMN) . ' = ?', $storeId);

        $captions = [];

        foreach ($connection->fetchAll($select) as $row) {
            $captions[(int) $row[$this->entityColumn]] = (string) $row[$this->captionColumn];
        }

        return $captions;
    }

    /**
     * @inheritDoc
     */
    public function save(int $entityId, ?array $captions): void
    {
        // Null is "not mentioned", not "empty" — see the interface. Returning here is what lets a
        // partial REST update leave a store's translations alone.
        if ($captions === null) {
            return;
        }

        $normalized = $this->normalizeCaptions($captions);
        $connection = $this->connection();

        $condition = [$connection->quoteIdentifier($this->entityColumn) . ' = ?' => $entityId];

        // Delete only what the new set does not cover, so unchanged rows are not rewritten.
        if ($normalized !== []) {
            $condition[$connection->quoteIdentifier(self::STORE_COLUMN) . ' NOT IN (?)'] =
                array_keys($normalized);
        }

        $connection->delete($this->tableName(), $condition);

        if ($normalized === []) {
            return;
        }

        $rows = [];

        foreach ($normalized as $storeId => $caption) {
            $rows[] = [
                $this->entityColumn => $entityId,
                self::STORE_COLUMN => $storeId,
                $this->captionColumn => $caption,
            ];
        }

        $connection->insertOnDuplicate($this->tableName(), $rows, [$this->captionColumn]);
    }

    /**
     * @inheritDoc
     */
    public function deleteForEntity(int $entityId): void
    {
        $connection = $this->connection();

        $connection->delete(
            $this->tableName(),
            [$connection->quoteIdentifier($this->entityColumn) . ' = ?' => $entityId]
        );
    }

    /**
     * Reduce a caller's caption map to storable rows.
     *
     * A non-positive store id is dropped rather than reported: store 0 is the default and lives on
     * the entity, and CaptionValidator is what tells a merchant they asked for something impossible.
     * Silently storing it would create a row that no resolver ever reads.
     *
     * @param array<int|string,mixed> $captions
     * @return array<int,string>
     */
    private function normalizeCaptions(array $captions): array
    {
        $normalized = [];

        foreach ($captions as $storeId => $caption) {
            $storeId = (int) $storeId;
            $caption = (string) $caption;

            if ($storeId <= 0 || trim($caption) === '') {
                continue;
            }

            $normalized[$storeId] = $caption;
        }

        return $normalized;
    }

    /**
     * Reduce a caller's id list to positive unique ints.
     *
     * @param array<int|string,mixed> $entityIds
     * @return int[]
     */
    private function normalizeIds(array $entityIds): array
    {
        $ids = [];

        foreach ($entityIds as $entityId) {
            $entityId = (int) $entityId;

            if ($entityId > 0) {
                $ids[] = $entityId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Get the prefixed physical table name.
     *
     * @return string
     */
    private function tableName(): string
    {
        return $this->resourceConnection->getTableName($this->table);
    }

    /**
     * Get the write connection.
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
