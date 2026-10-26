<?php

namespace App\Http\Requests\Access;

use App\Domain\Access\PasswordProfile;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Http\Auth\AuthenticateUser;
use Illuminate\Contracts\Validation\Validator;
use Laravel\Fortify\Http\Requests\LoginRequest;

/**
 * The request of RA-01, in place of Fortify's LoginRequest (the container
 * gives it to Fortify's controller): exactly `email` and `password`
 * (TR-VAL-01) — "remember me" does not exist (SEC-AUTH-07) — an email of at
 * most 254 characters and a password of at most 128 (TECHNICAL_RULES section
 * 11.3). Any refusal, whichever field it concerns, is the one uniform answer
 * of a failed sign-in (UXS-42; the sign-in page has no field errors, UI_STATES
 * section 6) and a SIGN_IN_FAILED entry of the security log.
 */
final class SignInRequest extends LoginRequest
{
    private const array FIELDS = ['email', 'password', '_token'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string', 'max:'.PasswordProfile::MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['*' => __('ux.state.signin_failed.sign_in.body')];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), self::FIELDS) !== []) {
                $validator->errors()->add('form', __('ux.state.signin_failed.sign_in.body'));
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        app(SecurityLog::class)->record(SecurityEvent::SignInFailed, SecurityActor::anonymous(), SecurityOutcome::Refused, $this);

        throw AuthenticateUser::failure();
    }
}
