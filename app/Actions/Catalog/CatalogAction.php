<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Catalog\Categories;
use App\Domain\Catalog\CategoryRecord;
use App\Domain\Catalog\ProductRecord;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use LogicException;

/**
 * The base of RC-17 to RC-24 (INTERACTION_CONTRACTS section 5; TECHNICAL_RULES
 * section 7.2; TASK-015 scope item 2): the capability of every result — CAP-04
 * opens their pages — and what the commands share: the category a command
 * assigns, under its share lock and active (BR-CAT-09; RACE-29), and the expected
 * versions, read under the update lock (TR-VAL-08).
 *
 * The answer's location is the page of ROUTE_CONTRACTS that shows the record:
 * RP-302 for a product, RP-307 for a category.
 */
abstract class CatalogAction extends CommandAction
{
    /** The result's types and the page of a category (SCHEMA section 5.3; ROUTE_CONTRACTS RP-307). */
    protected const string PRODUCT = 'product';

    protected const string CATEGORY = 'category';

    protected const string CATEGORIES_PAGE = '/categories';

    public function __construct(
        CommandServices $services,
        protected readonly Categories $categories,
        protected readonly Products $products,
        protected readonly Barcodes $barcodes,
    ) {
        parent::__construct($services);
    }

    public function capabilities(): array
    {
        return [Capability::Cap04];
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap04)) {
            return null;
        }

        return [
            'type' => $result->type,
            'id' => $result->ref,
            'location' => $result->type === self::PRODUCT ? '/products/'.(string) $result->ref : self::CATEGORIES_PAGE,
        ];
    }

    /**
     * A category that exists, under the share lock of a command that assigns it
     * (TR §7.1 step 6). The command calls its point once step 6 holds all its
     * locks.
     *
     * @throws CommandRefused NOT_FOUND
     */
    protected function assignedCategory(int $id): CategoryRecord
    {
        return $this->categories->lockShared($id) ?? throw CommandRefused::of(ErrorCode::NotFound);
    }

    /**
     * Refuses an inactive category for an assignment: at the field, with the
     * code's own key (INTERACTION_CONTRACTS section 1.6).
     *
     * @throws CommandRefused CATEGORY_INACTIVE
     */
    protected static function requireActive(CategoryRecord $category): void
    {
        if (! $category->active) {
            throw new CommandRefused(ErrorCode::CategoryInactive, fields: ['payload.categoryId' => [['key' => ErrorCode::CategoryInactive->messageKey(), 'params' => []]]]);
        }
    }

    /**
     * The category under its update lock, at the version the user reviewed.
     *
     * @param  array<array-key, mixed>  $payload
     * @param  array<array-key, mixed>  $expected
     *
     * @throws CommandRefused NOT_FOUND or STALE_VERSION
     */
    protected function categoryToChange(array $payload, array $expected): CategoryRecord
    {
        $category = $this->categories->lockForChange(self::whole($payload['categoryId'] ?? null));
        $this->services->points->afterLock('6');

        if ($category === null) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        if ($category->version !== ($expected['categoryVersion'] ?? null)) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        return $category;
    }

    /**
     * The product under its update lock, at the version the user reviewed.
     *
     * @param  array<array-key, mixed>  $payload
     * @param  array<array-key, mixed>  $expected
     *
     * @throws CommandRefused NOT_FOUND or STALE_VERSION
     */
    protected function productToChange(array $payload, array $expected): ProductRecord
    {
        $product = $this->products->lock(self::whole($payload['productId'] ?? null));
        $this->services->points->afterLock('6');

        if ($product === null) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        if ($product->version !== ($expected['productVersion'] ?? null)) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        return $product;
    }

    /**
     * The token's one ordinary mapping to the product and its active row, after the
     * token in the canonical order of SCH-32 (TASK-016): two write steps. Returns
     * the mapping's internal key.
     *
     * @param  Barcodes::MANUFACTURER|Barcodes::INTERNAL  $kind
     */
    protected function attachToken(int $tokenId, string $kind, int $productId, User $actor, int $commandId): int
    {
        $mapping = $this->barcodes->insertMapping($tokenId, $kind, $productId, $actor->id, $actor->display_name, $commandId);
        $this->written();

        $this->barcodes->activate($tokenId, $mapping, $productId);
        $this->written();

        return $mapping;
    }

    /**
     * The refusal of a command that the record's state does not allow, or that
     * would change nothing (TASK-011).
     */
    protected static function nothingToChange(): CommandRefused
    {
        return CommandRefused::of(ErrorCode::ValidationFailed);
    }

    protected static function actorOf(User $actor): Actor
    {
        return Actor::user($actor->id, $actor->display_name);
    }

    protected static function text(mixed $value): string
    {
        return is_string($value) ? $value : throw new LogicException('A text of the payload was validated before the Action.');
    }

    protected static function whole(mixed $value): int
    {
        return is_int($value) ? $value : throw new LogicException('A whole number of the payload was validated before the Action.');
    }
}
