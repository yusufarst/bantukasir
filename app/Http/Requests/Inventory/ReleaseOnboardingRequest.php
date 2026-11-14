<?php

namespace App\Http\Requests\Inventory;

use App\Actions\Inventory\ReleaseOnboarding;
use App\Http\Requests\Commands\CommandRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-13 `onboarding.release` (INTERACTION_CONTRACTS RC-13; TECHNICAL_RULES section
 * 5): no payload, and `expected.onboardingVersion`, the onboarding version reviewed, a JSON whole number
 * (TR-VAL-06). Whether onboarding may be released is the Action's to check, under the update lock of the
 * onboarding state.
 */
final class ReleaseOnboardingRequest extends CommandRequest
{
    public function operation(): string
    {
        return ReleaseOnboarding::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return ['onboardingVersion' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            if (! is_int($this->input('expected.onboardingVersion'))) {
                $validator->errors()->add('', '');
            }
        }];
    }
}
