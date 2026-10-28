<?php

namespace Tests\Harness\Commands;

use App\Domain\Commands\ErrorCode;
use App\Http\Requests\Commands\CommandRequest;

/**
 * The FormRequest of the probe operation: a payload of `note` (1 to 100
 * characters) and, for the tests, `refuseWith`, a code of TECHNICAL_RULES
 * section 4 that the Action then refuses with, `count`, an integer, and
 * `hideResult`, which makes the result one the viewer may not see; no
 * expected versions.
 */
final class ProbeRequest extends CommandRequest
{
    public function operation(): string
    {
        return Probe::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'note' => ['required', 'string', 'max:100'],
            'refuseWith' => ['sometimes', 'string', 'in:'.implode(',', array_map(static fn (ErrorCode $code): string => $code->value, ErrorCode::cases()))],
            'count' => ['sometimes', 'integer'],
            'hideResult' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'payload.note.max' => ['ux.validation.text_max', ['maks' => 100]],
            'payload.note.string' => ['ux.validation.required'],
        ];
    }
}
