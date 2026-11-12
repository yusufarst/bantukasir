<?php

namespace App\Http\Requests\Documents;

use App\Actions\Documents\RecordReceiptPrint;
use App\Http\Requests\Commands\CommandRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-44 `document.print_receipt` (INTERACTION_CONTRACTS, "RC-44 to RC-47";
 * TECHNICAL_RULES section 11): `saleId`, a JSON whole number, and `size`, the text `58`, `80` or
 * `A4`; no expected version, since a print changes nothing. Whether the Sale is in the actor's scope
 * is the Action's, where another cashier's Sale answers as one that does not exist (TR-ERR-03).
 */
final class RecordReceiptPrintRequest extends CommandRequest
{
    public function operation(): string
    {
        return RecordReceiptPrint::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'saleId' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'string', 'in:58,80,A4'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $sale = $this->input('payload.saleId');

            if ($sale !== null && ! is_int($sale)) {
                $validator->errors()->add('', '');
            }
        }];
    }
}
