<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\CorrectBarcodeMisassignment;
use App\Domain\Catalog\Barcodes\CurrentMapping;

/**
 * The FormRequest of RC-30 `barcode.correct_misassignment` (INTERACTION_CONTRACTS RC-30;
 * BR-CAT-50, BR-CAT-51): `token`, `targetProductId` and a mandatory `reason` of 1 to 1,000
 * characters; expected `mapping`, as RD-05 showed it: `productId`, the product the token was
 * on, and `state`, whether it was active or retired as a misassignment. Whether the token can
 * be corrected, and whether the mapping is still as reviewed, is the Action's under its locks.
 */
final class CorrectBarcodeMisassignmentRequest extends CatalogCommandRequest
{
    private const int REASON_MAX = 1000;

    public function operation(): string
    {
        return CorrectBarcodeMisassignment::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'token' => ['required', ...self::tokenRules()],
            'targetProductId' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:'.self::REASON_MAX, 'regex:'.self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return [
            'mapping' => ['required', 'array'],
            'mapping.productId' => ['required', 'integer', 'min:1'],
            'mapping.state' => ['required', 'string', 'in:'.CurrentMapping::ACTIVE.','.CurrentMapping::RETIRED_AS_MISASSIGNMENT],
        ];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            ...parent::fieldKeys(),
            'payload.targetProductId.integer' => ['ux.validation.choose_one'],
            'payload.targetProductId.min' => ['ux.validation.choose_one'],
            'payload.reason.string' => ['ux.validation.required'],
            'payload.reason.max' => ['ux.validation.text_max', ['maks' => self::REASON_MAX]],
        ];
    }
}
