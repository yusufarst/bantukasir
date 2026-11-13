<?php

namespace App\Http\Requests\Cash;

use App\Actions\Cash\AddReviewNote;
use App\Http\Requests\Commands\CommandRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-43 `review_note.add` (INTERACTION_CONTRACTS RC-43; TECHNICAL_RULES TR-VAL-05 and
 * section 11.3): `sessionId`, a JSON whole number, and `text`, 1 to 2,000 characters, whose only control
 * character allowed is the line break of a multi-line note. No expected value: the session's state is
 * checked under its share lock. Never an author or a time: the server writes them (TR-VAL-03).
 */
final class AddReviewNoteRequest extends CommandRequest
{
    /** Text without a control character but the line break (TR-VAL-05). */
    private const string NOTE_TEXT = '/\A(?:[^\p{Cc}]|\n)*\z/u';

    public function operation(): string
    {
        return AddReviewNote::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'sessionId' => ['required', 'integer', 'min:1'],
            'text' => ['required', 'string', 'max:2000', 'regex:'.self::NOTE_TEXT],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $session = $this->input('payload.sessionId');

            if ($session !== null && ! is_int($session)) {
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
            'payload.text.required' => ['ux.validation.required'],
            'payload.text.max' => ['ux.validation.text_max', ['maks' => 2000]],
        ];
    }
}
