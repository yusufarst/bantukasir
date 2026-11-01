<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-21 `category.create` (INTERACTION_CONTRACTS RC-21 to RC-24; TECHNICAL_RULES
 * section 7.2; BR-CAT-09; CAP-04): an active category. Names are not unique
 * (SCHEMA section 5.6), and nothing is locked for a creation.
 *
 * Write steps (TEST_STRATEGY TS-21): the category, the audit event, the result.
 */
final class CreateCategory extends CatalogAction
{
    public const string OPERATION = 'category.create';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $name = self::text($context->envelope->payload['name'] ?? null);
        $id = $this->categories->insert($name);
        $this->written();

        $this->services->audit->record(AuditCategory::Business, 'CATEGORY_CREATE', self::actorOf($actor), ['name' => $name], subject: ['type' => self::CATEGORY, 'id' => $id], commandId: $commandId);
        $this->written();

        return new StoredResult(self::CATEGORY, $id);
    }
}
