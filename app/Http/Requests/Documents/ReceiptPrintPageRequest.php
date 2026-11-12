<?php

namespace App\Http\Requests\Documents;

use App\Domain\Documents\Receipts\ReceiptPrints;
use App\Http\Requests\Commands\RefusingRequest;

/**
 * The parameter of RP-103P (ROUTE_CONTRACTS RP-103P; TASK-031 scope item 2): `size`, `58`, `80` or
 * `a4`, the size of the paper to render; any other parameter is refused.
 */
final class ReceiptPrintPageRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'size' => ['required', 'string', 'in:'.implode(',', ReceiptPrints::PAGE_SIZES)],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['size'];
    }

    /**
     * The size asked for, validated.
     */
    public function size(): string
    {
        $size = $this->validatedInput()['size'] ?? null;

        return is_string($size) ? $size : '58';
    }
}
