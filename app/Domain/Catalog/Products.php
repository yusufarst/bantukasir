<?php

namespace App\Domain\Catalog;

use App\Database\SqlState;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use App\Support\Time\Clock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * The products and their selling price revisions, in the Catalog module
 * (SCHEMA section 5.6; BR-CAT-01 to BR-CAT-13; D-036, D-037, D-042), which only
 * this module writes (STK-12). It opens no transaction of its own: the Action
 * that calls it owns the transaction and the locks (TR-TX-01). A product is
 * never deleted, and a revision is only ever added.
 *
 * Whether two SKUs are the same is the unique index's alone, through lower()
 * (SCH-14): nothing here compares them, and a violation of that index is the
 * refusal DUPLICATE_SKU, which ends the transaction (TR-TX-08). A creation that
 * races another waits on the index and is refused once the other commits.
 *
 * A product and its first revision need each other's keys, and the runtime role
 * takes nothing from a sequence (SCH-44). insert() therefore writes the product
 * with NO_REVISION in its pointer, a value no revision ever has, and the Action
 * then adds the revision and moves the pointer in the same transaction. The
 * deferred key refuses a commit that leaves the pointer so (SCHEMA section 5.6).
 */
final class Products
{
    /** The pointer of a product whose first revision is not written yet; a key is never below 1. */
    private const int NO_REVISION = 0;

    /** The index that holds a SKU's uniqueness (SCHEMA section 5.6). */
    private const array SKU_INDEX = ['products_sku_ci_uniq'];

    public function __construct(private readonly Clock $clock) {}

    /**
     * The product as committed, without a lock, or null: what a command reads to
     * choose its locks, which it must compare with the row under the lock.
     */
    public function read(int $id): ?ProductRecord
    {
        return self::recordOf(self::query()->where('p.id', $id)->first());
    }

    /**
     * The product under its update lock (step 6): FOR UPDATE when the command
     * changes its unit or precision, whose key the stock movements reference,
     * and FOR NO KEY UPDATE otherwise (SCHEMA section 7). The row is locked alone
     * and the price in force read after it: a lock that joined the revision and
     * waited for a price change would join the old revision to the new pointer,
     * find no row and answer NOT_FOUND for a product that exists.
     */
    public function lock(int $id, bool $forUnitChange = false): ?ProductRecord
    {
        self::requireTransaction();

        return self::recordOf(self::query()->where('p.id', $id)->lock($forUnitChange ? 'for update' : 'for no key update')->first());
    }

    /**
     * The products of a stock posting under their update locks, ascending (TR §7.1 step 6;
     * TR-LOCK-01): FOR NO KEY UPDATE, which the FOR UPDATE of an edit of the unit or the
     * precision conflicts with (RACE-05). A row whose lock waited for another command is
     * read as that command left it. A product that does not exist is absent from the
     * answer, which stands in the order of the keys.
     *
     * @param  list<int>  $ids
     * @return array<int, GuardedProduct>
     */
    public function lockForPosting(array $ids): array
    {
        self::requireTransaction();

        $rows = DB::table('products')->whereIn('id', $ids)->orderBy('id')->lock('for no key update')->get(['id', 'base_unit', 'quantity_precision', 'active', 'version']);
        $products = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $id = $values['id'] ?? null;
            $unit = $values['base_unit'] ?? null;
            $precision = $values['quantity_precision'] ?? null;
            $active = $values['active'] ?? null;
            $version = $values['version'] ?? null;

            if (! is_int($id) || ! is_string($unit) || ! is_int($precision) || ! is_bool($active) || ! is_int($version)) {
                throw new LogicException('A product row holds its columns (SCHEMA section 5.6).');
            }

            $products[$id] = new GuardedProduct($id, $unit, $precision, $active, $version);
        }

        return $products;
    }

    /**
     * Adds an active product with no movement and no stock (BR-CAT-03), its
     * pointer to be moved by the Action.
     *
     * @throws CommandRefused DUPLICATE_SKU
     */
    public function insert(string $sku, ProductFields $fields, int $commandId): int
    {
        self::requireTransaction();
        $now = $this->now();

        try {
            return DB::table('products')->insertGetId([
                'sku' => $sku,
                'name' => $fields->name,
                'category_id' => $fields->categoryId,
                'brand' => $fields->brand,
                'base_unit' => $fields->baseUnit,
                'quantity_precision' => $fields->quantityPrecision,
                'current_price_revision_id' => self::NO_REVISION,
                'minimum_stock' => $fields->minimumStock->toCanonical(),
                'active' => true,
                'version' => 1,
                'created_command_id' => $commandId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $failure) {
            throw SqlState::uniqueOn($failure, self::SKU_INDEX)
                ? new CommandRefused(ErrorCode::DuplicateSku, fields: ['payload.sku' => [['key' => ErrorCode::DuplicateSku->messageKey(), 'params' => []]]])
                : $failure;
        }
    }

    /**
     * Adds a selling price revision of the product, with its actor's name frozen
     * (SCHEMA section 3.5), and returns its internal key.
     */
    public function insertRevision(int $productId, Money $amount, int $actorUserId, string $actorDisplayName, int $commandId): int
    {
        self::requireTransaction();

        return DB::table('selling_price_revisions')->insertGetId([
            'product_id' => $productId,
            'amount' => $amount->toCanonical(),
            'created_at' => $this->now(),
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Points the product that the caller just inserted at its first revision.
     * It leaves the version as it is: this is one creation.
     */
    public function pointAtFirstRevision(int $productId, int $revisionId): void
    {
        self::requireTransaction();

        $moved = DB::table('products')->where('id', $productId)->where('current_price_revision_id', self::NO_REVISION)->update(['current_price_revision_id' => $revisionId]);

        if ($moved !== 1) {
            throw new LogicException('The first revision is pointed at by the transaction that inserted its product.');
        }
    }

    /**
     * Applies RC-18's fields to the product whose update lock the caller holds,
     * one version on.
     */
    public function edit(ProductRecord $product, ProductFields $fields, int $revisionId): void
    {
        $this->change($product, [
            'name' => $fields->name,
            'category_id' => $fields->categoryId,
            'brand' => $fields->brand,
            'base_unit' => $fields->baseUnit,
            'quantity_precision' => $fields->quantityPrecision,
            'current_price_revision_id' => $revisionId,
            'minimum_stock' => $fields->minimumStock->toCanonical(),
        ]);
    }

    /**
     * Switches the active flag of the product whose update lock the caller
     * holds, one version on (BR-CAT-08).
     */
    public function setActive(ProductRecord $product, bool $active): void
    {
        $this->change($product, ['active' => $active]);
    }

    /**
     * Points the product whose update lock the caller holds at a photo, or at none,
     * one version on (BR-CAT-44). The file it pointed at stays (D-042).
     */
    public function setPhoto(ProductRecord $product, ?int $fileId): void
    {
        $this->change($product, ['photo_file_id' => $fileId]);
    }

    /**
     * @param  array<string, mixed>  $columns
     */
    private function change(ProductRecord $product, array $columns): void
    {
        self::requireTransaction();

        $changed = DB::table('products')->where('id', $product->id)->update([...$columns, 'version' => $product->version + 1, 'updated_at' => $this->now()]);

        if ($changed !== 1) {
            throw new LogicException('A locked product exists.');
        }
    }

    private static function query(): Builder
    {
        return DB::table('products', 'p')
            ->select(['p.id', 'p.sku', 'p.name', 'p.category_id', 'p.brand', 'p.base_unit', 'p.quantity_precision', 'p.current_price_revision_id', 'p.minimum_stock', 'p.active', 'p.version', 'p.photo_file_id']);
    }

    private static function recordOf(?object $row): ?ProductRecord
    {
        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $sku = $values['sku'] ?? null;
        $name = $values['name'] ?? null;
        $category = $values['category_id'] ?? null;
        $brand = $values['brand'] ?? null;
        $unit = $values['base_unit'] ?? null;
        $precision = $values['quantity_precision'] ?? null;
        $revision = $values['current_price_revision_id'] ?? null;
        $minimum = $values['minimum_stock'] ?? null;
        $active = $values['active'] ?? null;
        $version = $values['version'] ?? null;
        $photo = $values['photo_file_id'] ?? null;

        if (! is_int($id) || ! is_string($sku) || ! is_string($name) || ! is_int($category) || ! is_string($unit)
            || ! is_int($precision) || ! is_int($revision) || ! is_string($minimum) || ! is_bool($active) || ! is_int($version)) {
            throw new LogicException('A product row holds its columns (SCHEMA section 5.6).');
        }

        // A revision is never changed, so it needs no lock (SCH-31).
        $amount = DB::table('selling_price_revisions')->where('id', $revision)->value('amount');

        if (! is_int($amount) && ! is_string($amount)) {
            throw new LogicException('A product points at a revision of its own (SCHEMA section 5.6).');
        }

        return new ProductRecord(
            $id,
            $sku,
            $name,
            $category,
            is_string($brand) ? $brand : null,
            $unit,
            $precision,
            $revision,
            Money::fromDatabase($amount),
            Quantity::fromDatabase($minimum, $precision),
            $active,
            $version,
            is_int($photo) ? $photo : null,
        );
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A product is locked and changed inside the transaction of its Action (TR-TX-01).');
        }
    }
}
