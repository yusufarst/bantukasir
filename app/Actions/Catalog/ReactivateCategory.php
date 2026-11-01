<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-24 `category.reactivate` (INTERACTION_CONTRACTS RC-21 to RC-24; BR-CAT-09;
 * D-042; CAP-04): under the category's update lock, at the version the user
 * reviewed, the active flag on, audited. An active category is refused.
 *
 * Write steps (TEST_STRATEGY TS-21): the category, the audit event, the result.
 */
final class ReactivateCategory extends CatalogAction
{
    public const string OPERATION = 'category.reactivate';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $category = $this->categoryToChange($context->envelope->payload, $context->envelope->expected);

        if ($category->active) {
            throw self::nothingToChange();
        }

        $this->categories->change($category, ['active' => true]);
        $this->written();

        $this->services->audit->record(AuditCategory::Business, 'CATEGORY_REACTIVATE', self::actorOf($actor), [], subject: ['type' => self::CATEGORY, 'id' => $category->id], commandId: $commandId);
        $this->written();

        return new StoredResult(self::CATEGORY, $category->id);
    }
}
