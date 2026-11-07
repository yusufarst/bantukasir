<?php

namespace App\Actions\Cash;

use App\Actions\Commands\CommandContext;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-33 `cash_register.create` (INTERACTION_CONTRACTS RC-33; BR-CASH-02, BR-CASH-03; CAP-31): a new
 * register, at version 1, with no session, so nothing is locked for it (TECHNICAL_RULES section
 * 7.2). A code that another register holds in any letter case is refused at its field.
 *
 * Write steps (TEST_STRATEGY TS-21): the register, the audit event, the result.
 */
final class CreateCashRegister extends CashRegisterAction
{
    public const string OPERATION = 'cash_register.create';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $code = self::text($payload['code'] ?? null);
        $label = self::text($payload['label'] ?? null);
        $active = self::flag($payload['active'] ?? null);

        $id = self::withUniqueCode(fn (): int => $this->registers->insert($code, $label, $active));
        $this->written();

        $this->services->audit->record(AuditCategory::Business, 'REGISTER_CREATE', self::actorOf($actor), ['code' => $code, 'label' => $label, 'active' => $active], subject: ['type' => self::RESULT_TYPE, 'id' => $id], commandId: $commandId);
        $this->written();

        return new StoredResult(self::RESULT_TYPE, $id);
    }
}
