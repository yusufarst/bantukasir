<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-22 `category.rename` (INTERACTION_CONTRACTS RC-21 to RC-24; BR-CAT-09;
 * CAP-04): under the category's update lock, at the version the user reviewed, a
 * new name, audited. The same name is refused. An inactive category may be renamed;
 * no rule forbids it.
 *
 * Write steps (TEST_STRATEGY TS-21): the category, the audit event, the result.
 */
final class RenameCategory extends CatalogAction
{
    public const string OPERATION = 'category.rename';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $category = $this->categoryToChange($payload, $context->envelope->expected);
        $name = self::text($payload['name'] ?? null);

        if ($name === $category->name) {
            throw self::nothingToChange();
        }

        $this->categories->change($category, ['name' => $name]);
        $this->written();

        $this->services->audit->record(AuditCategory::Business, 'CATEGORY_RENAME', self::actorOf($actor), ['from' => $category->name, 'to' => $name], subject: ['type' => self::CATEGORY, 'id' => $category->id], commandId: $commandId);
        $this->written();

        return new StoredResult(self::CATEGORY, $category->id);
    }
}
