<?php

namespace App\Http\Requests\Commands;

use App\Domain\Commands\Operations;
use App\Domain\Commands\RecoveryEpoch;
use Illuminate\Contracts\Validation\Validator;
use LogicException;

/**
 * The request of RC-50, the status check (INTERACTION_CONTRACTS section 2.1):
 * exactly `operation`, an operation of the server's list that users submit,
 * never a scheduled command's, `sourceIntentId`,
 * a UUID, and `recoveryEpoch`, the epoch the intent was created under; no
 * envelope of its own (TECHNICAL_RULES section 7.2). Who may check the
 * intent is the Action's check, answered NOT_FOUND (CAP-34).
 */
final class ResolveIntentRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operation' => ['required', 'string', 'regex:/^[a-z][a-z0-9_.]{0,63}$/'],
            'sourceIntentId' => ['required', 'string', 'uuid'],
            'recoveryEpoch' => ['required', 'string', 'regex:/^(?:[0-9a-f]{2}){'.RecoveryEpoch::MIN_BYTES.','.RecoveryEpoch::MAX_BYTES.'}$/'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['operation', 'sourceIntentId', 'recoveryEpoch'];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $operation = $this->input('operation');

                // A user's operation only: a scheduled command's intent is
                // derived and never checked by a user (review finding R-03).
                if (! is_string($operation) || ! Operations::submittedByUsers($operation)) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * @return array{operation: string, sourceIntentId: string, recoveryEpoch: string}
     */
    public function intent(): array
    {
        $input = $this->validatedInput();

        if (! is_string($input['operation'] ?? null) || ! is_string($input['sourceIntentId'] ?? null) || ! is_string($input['recoveryEpoch'] ?? null)) {
            throw new LogicException('The status check was validated before it is read.');
        }

        return ['operation' => $input['operation'], 'sourceIntentId' => strtolower($input['sourceIntentId']), 'recoveryEpoch' => $input['recoveryEpoch']];
    }
}
