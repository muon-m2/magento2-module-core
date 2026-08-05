<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Api;

/**
 * Reads and writes per-store-view caption overrides for one entity type.
 *
 * NO TABLE NAME APPEARS IN ANY SIGNATURE HERE. An implementation is bound per entity through a DI
 * virtual type carrying its table and column names, the same way ScopedCacheTags is bound per module
 * with its base tag. Passing them as method arguments would put SQL identifiers on a public contract
 * where a caller could source them from a request.
 *
 * THERE IS DELIBERATELY NO DEFAULT PREFERENCE for this interface. An unconfigured instance would
 * carry empty identifiers and fail at query time rather than at wiring time; leaving it unbound makes
 * a missing virtual type an immediate DI error instead.
 *
 * @api Consumed through a virtual type per entity.
 */
interface CaptionStorageInterface
{
    /**
     * Load every store's caption for each of the given entities.
     *
     * ONE QUERY REGARDLESS OF HOW MANY IDS ARE PASSED. This is the method that keeps admin grids and
     * REST list endpoints off an N+1; a per-entity variant is deliberately not offered.
     *
     * @param int[] $entityIds
     * @return array<int,array<int,string>> Entity id to store id to caption. Entities with no
     *                                        overrides are absent, not present-and-empty.
     */
    public function loadForEntities(array $entityIds): array;

    /**
     * Load one store view's caption for each of the given entities.
     *
     * @param int[] $entityIds
     * @param int $storeId
     * @return array<int,string> Entity id to caption; entities with no override for this store are
     *                            absent.
     */
    public function loadForStore(array $entityIds, int $storeId): array;

    /**
     * Replace an entity's caption overrides.
     *
     * NULL AND THE EMPTY ARRAY MEAN DIFFERENT THINGS, and inverting them is silently destructive.
     * Null means "the caller did not mention captions" — a partial REST update — and leaves every
     * existing row untouched. The empty array means "the caller supplied an empty set" — an admin
     * form where every override was cleared — and removes them all. This is the same rule the menu
     * modules already apply to store_ids, and it is why the parameter is nullable rather than
     * defaulted.
     *
     * A blank or whitespace-only caption is dropped rather than stored, so clearing a field in the
     * admin form removes the override instead of persisting an empty string that would render an
     * empty menu entry.
     *
     * @param int $entityId
     * @param array<int,string>|null $captions Store id to caption.
     * @return void
     */
    public function save(int $entityId, ?array $captions): void;

    /**
     * Remove every caption override for an entity.
     *
     * The schema cascades on entity delete, so this exists for callers that clear captions without
     * deleting the entity.
     *
     * @param int $entityId
     * @return void
     */
    public function deleteForEntity(int $entityId): void;
}
