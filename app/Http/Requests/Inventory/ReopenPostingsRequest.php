<?php

namespace App\Http\Requests\Inventory;

use App\Actions\Inventory\ReopenPostings;
use App\Http\Requests\Commands\CommandRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-16 `posting_block.reopen` (INTERACTION_CONTRACTS RC-16; TECHNICAL_RULES
 * sections 5 and 11.3): `blockId`, the posting block to reopen, a JSON whole number (TR-VAL-06), and
 * `reason`, optional, up to 1,000 characters without a control character; no expected version. Whether
 * the block exists and is still open is the Action's to answer, NOT_FOUND and STALE_VERSION under the
 * product's guard.
 */
final class ReopenPostingsRequest extends CommandRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function operation(): string
    {
        return ReopenPostings::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'blockId' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return [];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $block = $this->input('payload.blockId');

            if ($block !== null && ! is_int($block)) {
                $validator->errors()->add('', '');
            }
        }];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'payload.blockId.integer' => ['ux.validation.choose_one'],
            'payload.blockId.min' => ['ux.validation.choose_one'],
            'payload.reason.max' => ['ux.validation.text_max', ['maks' => 1000]],
        ];
    }
}
