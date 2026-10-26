<?php

namespace App\Http\Requests\Access;

use App\Domain\Access\LinkInvalid;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Access\PasswordProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * The request of RA-06, the consumption of a one-time link (ROUTE_CONTRACTS
 * section 4; INTERACTION_CONTRACTS IC-13): exactly `user` (the account's
 * internal identity, from the link's fragment), `token`, `password` and
 * `password_confirmation`, in the body only, never the URL (SEC-ACC-05;
 * TR-VAL-01). The token is never trimmed or altered (TR-VAL-05). A malformed
 * `user` or `token`, or any other field, is the uniform LINK_INVALID (UXS-41,
 * SEC-ACC-06); a new password that does not meet AICWDF-COMPAT-8, or whose
 * repetition differs, is a field error (UXS-05), and the link stays as it was.
 */
final class ConsumeLinkRequest extends FormRequest
{
    private const array FIELDS = ['user', 'token', 'password', 'password_confirmation', '_token'];

    /** Field errors return to the link page, whose address carries no fragment any more. */
    protected $redirectRoute = 'password.link';

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
            'user' => ['required', 'string', 'regex:/^[1-9][0-9]{0,18}$/'],
            'token' => ['required', 'string', 'max:'.OneTimeLinks::TOKEN_MAX],
            'password' => ['required', 'string', new PasswordProfile],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user.*' => __('ux.state.link_invalid.body'),
            'token.*' => __('ux.state.link_invalid.body'),
            'password.required' => __('ux.validation.required'),
            'password.string' => __('ux.validation.password'),
            'password_confirmation.required' => __('ux.validation.required'),
            'password_confirmation.string' => __('ux.validation.password_repeat'),
            'password_confirmation.same' => __('ux.validation.password_repeat'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), self::FIELDS) !== []) {
                $validator->errors()->add('user', __('ux.state.link_invalid.body'));
            }
        });
    }

    public function userId(): int
    {
        return (int) $this->string('user')->toString();
    }

    public function token(): string
    {
        $token = $this->input('token');

        return is_string($token) ? $token : '';
    }

    public function newPassword(): string
    {
        $password = $this->input('password');

        return is_string($password) ? $password : '';
    }

    /**
     * @throws LinkInvalid when the link's own fields are malformed
     * @throws ValidationException otherwise
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        // The security log names no account here: a malformed link is not
        // looked up (App\Http\Auth\LinkInvalidAnswer).
        if ($errors->has('user') || $errors->has('token')) {
            throw new LinkInvalid(null);
        }

        // The field errors return to the link page without any input: the
        // link's account and token are never flashed, nor are the passwords
        // (SEC-CH-03, SEC-ACC-05; review finding R-01).
        throw new ValidationException($validator, redirect()->route('password.link')->withErrors($errors));
    }
}
