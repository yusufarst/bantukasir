<?php

namespace App\Http\Requests\Documents;

use App\Actions\Documents\RecordLabelPrint;
use App\Http\Requests\Catalog\CatalogCommandRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-47 `document.print_labels` (INTERACTION_CONTRACTS, "RC-44 to
 * RC-47"; TECHNICAL_RULES section 11; BR-CAT-36): `productId`, `mappingId` and `count`,
 * JSON whole numbers, 1 to 100 labels, and no expected version: the mapping is judged
 * active by the Action. COPY_DECK has no text for the limit of 100, so above it the field
 * takes the text of a quantity too large, a reading for the next change control; the
 * page's field keeps to the limit (evidence, finding F-3).
 */
final class RecordLabelPrintRequest extends CatalogCommandRequest
{
    public const int MAX_LABELS = 100;

    public function operation(): string
    {
        return RecordLabelPrint::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            ...self::productTargetRules(),
            'mappingId' => ['required', 'integer', 'min:1'],
            'count' => ['required', 'integer', 'between:1,'.self::MAX_LABELS],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $count = $this->input('payload.count');

            if ($count !== null && ! is_int($count)) {
                $validator->errors()->add('', '');
            }
        }];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $count = $this->input('payload.count');

        return [
            ...parent::fieldKeys(),
            'payload.count.integer' => ['ux.validation.quantity_positive'],
            'payload.count.between' => [is_int($count) && $count > self::MAX_LABELS ? 'ux.validation.quantity_max' : 'ux.validation.quantity_positive'],
        ];
    }
}
