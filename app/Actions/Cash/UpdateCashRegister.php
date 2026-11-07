<?php

namespace App\Actions\Cash;

use App\Actions\Commands\CommandContext;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-34 `cash_register.update` (INTERACTION_CONTRACTS RC-34; TECHNICAL_RULES section 7.2 and RACE-26;
 * BR-CASH-02; G-19; CAP-31): under the register's update lock (TR §7.1 step 3), at the version the
 * Owner reviewed, a new code, label or active flag — only while no session is OPEN on the register,
 * read under that lock, so that a change and a session open never interleave. A session OPEN on it
 * is refused VALIDATION_FAILED with the reason of UXS-13; a change that changes nothing is refused
 * too (TASK-011). Historical records keep the identity they recorded.
 *
 * Write steps (TEST_STRATEGY TS-21): the register, the audit event, the result.
 */
final class UpdateCashRegister extends CashRegisterAction
{
    public const string OPERATION = 'cash_register.update';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $register = $this->registers->lock(self::whole($payload['registerId'] ?? null));
        $this->services->points->afterLock('3');

        if ($register === null) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        if ($register->version !== ($context->envelope->expected['registerVersion'] ?? null)) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        // G-19, under the register's lock: a session opened meanwhile has committed before the lock
        // was granted, and this read sees it (TR-VAL-08).
        if ($this->sessions->openOn($register->id)) {
            throw new CommandRefused(ErrorCode::ValidationFailed, fields: ['payload.registerId' => [['key' => 'ux.validation.register_in_use', 'params' => []]]]);
        }

        $code = self::text($payload['code'] ?? null);
        $label = self::text($payload['label'] ?? null);
        $active = self::flag($payload['active'] ?? null);

        if ($code === $register->code && $label === $register->label && $active === $register->active) {
            throw CommandRefused::of(ErrorCode::ValidationFailed);
        }

        self::withUniqueCode(function () use ($register, $code, $label, $active): void {
            $this->registers->change($register, $code, $label, $active);
        });
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'REGISTER_CHANGE',
            self::actorOf($actor),
            [
                'from' => ['code' => $register->code, 'label' => $register->label, 'active' => $register->active],
                'to' => ['code' => $code, 'label' => $label, 'active' => $active],
            ],
            subject: ['type' => self::RESULT_TYPE, 'id' => $register->id],
            commandId: $commandId,
        );
        $this->written();

        return new StoredResult(self::RESULT_TYPE, $register->id);
    }
}
