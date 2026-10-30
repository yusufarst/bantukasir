<?php

namespace App\Http\Requests\Configuration;

use App\Actions\Configuration\ChangeBusinessProfile;
use App\Domain\Configuration\Accents;
use App\Domain\Configuration\ProfileFields;
use App\Http\Requests\Commands\CommandRequest;

/**
 * The FormRequest of RC-32 `business_profile.change` (INTERACTION_CONTRACTS
 * RC-32; TECHNICAL_RULES section 11.3; TASK-010 scope item 4): the envelope
 * of every command with the payload `shopName` (1 to 100), `address` (up to
 * 500), `contact` (up to 200), `email` (up to 254), `documentHeader` and
 * `documentFooter` (up to 500 each), `documentIdentity` (up to 100) — every
 * one but the shop name optional, null when absent — `accentToken`, one of
 * DESIGN_SYSTEM section 3.3 or null, `logoAction` (`keep`, `replace` or
 * `remove`) and `logo`, a file exactly when the action is `replace`; and the
 * expected `revisionId`, the revision in force as the Owner reviewed it.
 *
 * Without a new logo the body is JSON. With one it is multipart (ROUTE_CONTRACTS
 * RT-12, at most 5 MiB): the envelope travels as form fields beside the file,
 * so its version and the expected revision arrive as texts and are read as
 * the integers they stand for — only a canonical whole number — before
 * validation (INTERACTION_CONTRACTS section 1.1). The file's name and
 * declared type are never read (SEC-UP-01); its content is judged by the
 * Action before the transaction, and its SHA-256 stands for it in the
 * fingerprint (Fingerprint).
 */
final class ChangeBusinessProfileRequest extends CommandRequest
{
    /** The logo's limit of RT-12, in kibibytes, as the file rule reads it. */
    private const int LOGO_KIB = 5 * 1024;

    public function operation(): string
    {
        return ChangeBusinessProfile::OPERATION;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isJson()) {
            return;
        }

        $expected = $this->input('expected');
        $replaced = ['version' => self::whole($this->input('version'))];

        if (is_array($expected) && array_key_exists('revisionId', $expected)) {
            $replaced['expected'] = [...$expected, 'revisionId' => self::whole($expected['revisionId'])];
        }

        $this->merge($replaced);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        $rules = [];

        foreach (ProfileFields::LENGTHS as $field => $max) {
            $rules[$field] = $field === 'shopName'
                ? ['required', 'string', 'max:'.$max]
                : ['present', 'nullable', 'string', 'max:'.$max];
        }

        $rules['email'][] = 'email:rfc';

        return [
            ...$rules,
            'accentToken' => ['present', 'nullable', 'string', 'in:'.implode(',', Accents::TOKENS)],
            'logoAction' => ['required', 'string', 'in:'.implode(',', ChangeBusinessProfile::LOGO_ACTIONS)],
            'logo' => ['required_if:payload.logoAction,replace', 'prohibited_if:payload.logoAction,keep,remove', 'file', 'max:'.self::LOGO_KIB],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return ['revisionId' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $keys = [];

        foreach (ProfileFields::LENGTHS as $field => $max) {
            $keys['payload.'.$field.'.max'] = ['ux.validation.text_max', ['maks' => $max]];
            $keys['payload.'.$field.'.string'] = ['ux.validation.required'];
        }

        return [
            ...$keys,
            'payload.email.email' => ['ux.validation.email'],
            'payload.accentToken.in' => ['ux.validation.required'],
            'payload.logo.required_if' => ['ux.validation.upload.unreadable'],
            'payload.logo.file' => ['ux.validation.upload.unreadable'],
            'payload.logo.max' => ['ux.validation.upload.too_large', ['ukuran' => '5 MB']],
        ];
    }

    /**
     * A form field that stands for a whole number, read as one; anything
     * else stays as it came, for the rules to refuse.
     */
    private static function whole(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^(?:0|[1-9][0-9]{0,17})$/D', $value) === 1 ? (int) $value : $value;
    }
}
