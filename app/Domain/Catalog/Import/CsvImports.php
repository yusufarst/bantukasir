<?php

namespace App\Domain\Catalog\Import;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The record of a committed import (SCHEMA section 5.6, `csv_product_imports`), which only
 * this module writes (STK-12): insert-only, in the transaction of the Action that creates
 * its products (TR-TX-01).
 */
final class CsvImports
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * Records that every row of the file became a product (BR-CAT-38), and returns its
     * internal key.
     */
    public function insert(string $sha256, int $rowCount, int $actorUserId, string $actorDisplayName, int $commandId): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('An import is recorded inside the transaction of its Action (TR-TX-01).');
        }

        $row = (array) DB::selectOne(
            "insert into csv_product_imports (command_id, file_sha256, data_row_count, products_created, committed_at, actor_user_id, actor_display_name)
             values (?, decode(?, 'hex'), ?, ?, ?, ?, ?) returning id",
            [$commandId, $sha256, $rowCount, $rowCount, $this->clock->now()->format('Y-m-d H:i:s.uP'), $actorUserId, $actorDisplayName],
        );
        $id = $row['id'] ?? null;

        return is_int($id) ? $id : throw new LogicException('An inserted import has its key.');
    }
}
