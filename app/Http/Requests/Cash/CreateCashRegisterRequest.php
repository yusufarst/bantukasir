<?php

namespace App\Http\Requests\Cash;

use App\Actions\Cash\CreateCashRegister;

/**
 * The FormRequest of RC-33 `cash_register.create`: `code`, `label` and `active`; no expected version.
 */
final class CreateCashRegisterRequest extends CashRegisterRequest
{
    public function operation(): string
    {
        return CreateCashRegister::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return self::registerRules();
    }
}
