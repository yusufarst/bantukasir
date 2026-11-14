<?php

namespace App\Queries\Inventory;

use App\Domain\Inventory\Onboarding\OpeningStock;
use LogicException;

/**
 * The eligibility of D-087 that RD-01 adds in the context `opening` (ROUTE_CONTRACTS RD-01;
 * INTERACTION_CONTRACTS section 3.1; UXI-292; TASK-035 scope item 5): for each product of a page, read
 * without a lock, ELIGIBLE, ZERO_COUNTED with its zero count, COUNTED, REPLACEABLE with the latest
 * reversed line it would replace, or HAS_MOVEMENT. The Inventory module reads the Catalog's rows and
 * never the other way round (STK-13), so the web layer gives a page of Product Search to this read, as
 * it gives it to ProductStock. RC-11, RC-14 and RC-48 decide again under the product's guard.
 */
final class OpeningEligibility
{
    public function __construct(private readonly OpeningStock $entries) {}

    /**
     * A page of RD-01 with each row's eligibility as `opening`.
     *
     * @param  array{rows: list<array<string, mixed>>, page: int, perPage: int, hasMore: bool}  $page
     * @return array{rows: list<array<string, mixed>>, page: int, perPage: int, hasMore: bool}
     */
    public function added(array $page): array
    {
        $ids = array_map(self::idOf(...), $page['rows']);
        $entries = $this->entries->entriesOf($ids);
        $rows = [];

        foreach ($page['rows'] as $row) {
            $entry = $entries[self::idOf($row)] ?? throw new LogicException('A product of a result has its onboarding facts.');
            $rows[] = [...$row, 'opening' => $entry->toArray()];
        }

        $page['rows'] = $rows;

        return $page;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function idOf(array $row): int
    {
        $id = $row['id'] ?? null;

        return is_int($id) ? $id : throw new LogicException('A result of Product Search names its product.');
    }
}
