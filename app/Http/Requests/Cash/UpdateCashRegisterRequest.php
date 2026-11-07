<?php

namespace App\Http\Requests\Cash;

use App\Actions\Cash\UpdateCashRegister;

/**
 * The FormRequest of RC-34 `cash_register.update`: `registerId`, `code`, `label` and `active`, against
 * the expected `registerVersion` (INTERACTION_CONTRACTS RC-34). A register's scope is the whole
 * shop's, so a missing one is the Action's NOT_FOUND under its lock.
 */
final class UpdateCashRegisterRequest extends CashRegisterRequest
{
    public function operation(): string
    {
        return UpdateCashRegister::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return ['registerId' => ['required', 'integer', 'min:1'], ...self::registerRules()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return ['registerVersion' => ['required', 'integer', 'min:1']];
    }
}
