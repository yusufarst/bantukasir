<?php

namespace App\Domain\Catalog;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The categories of the Catalog module (SCHEMA section 5.6; BR-CAT-09; D-042),
 * which only this module writes (STK-12). It opens no transaction of its own:
 * the Action that calls it owns the transaction and the locks (TR-TX-01). A
 * category is never deleted; deactivating one changes no product. Its locks are
 * those of TR §7.1 step 6 and SCHEMA section 7: a share lock on a category that
 * a command assigns to a product, an update lock on one whose name or lifecycle
 * it changes, which conflict, so that the one that holds the row first is in
 * force (RACE-29).
 */
final class Categories
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * Adds an active category at version 1 and returns its internal key.
     */
    public function insert(string $name): int
    {
        self::requireTransaction();
        $now = $this->now();

        return DB::table('categories')->insertGetId(['name' => $name, 'active' => true, 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
    }

    /**
     * The category under its share lock (FOR SHARE), or null: a command that
     * assigns it to a product.
     */
    public function lockShared(int $id): ?CategoryRecord
    {
        self::requireTransaction();

        return self::recordOf(DB::table('categories')->where('id', $id)->sharedLock()->first());
    }

    /**
     * The category under its update lock (FOR NO KEY UPDATE), or null: a
     * command that changes its name or lifecycle.
     */
    public function lockForChange(int $id): ?CategoryRecord
    {
        self::requireTransaction();

        return self::recordOf(DB::table('categories')->where('id', $id)->lock('for no key update')->first());
    }

    /**
     * Changes the category whose update lock the caller holds, one version on.
     *
     * @param  array{name?: string, active?: bool}  $columns
     */
    public function change(CategoryRecord $category, array $columns): void
    {
        self::requireTransaction();

        $changed = DB::table('categories')->where('id', $category->id)->update([...$columns, 'version' => $category->version + 1, 'updated_at' => $this->now()]);

        if ($changed !== 1) {
            throw new LogicException('A locked category exists.');
        }
    }

    private static function recordOf(?object $row): ?CategoryRecord
    {
        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $name = $values['name'] ?? null;
        $active = $values['active'] ?? null;
        $version = $values['version'] ?? null;

        if (! is_int($id) || ! is_string($name) || ! is_bool($active) || ! is_int($version)) {
            throw new LogicException('A category row holds its columns (SCHEMA section 5.6).');
        }

        return new CategoryRecord($id, $name, $active, $version);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A category is locked and changed inside the transaction of its Action (TR-TX-01).');
        }
    }
}
