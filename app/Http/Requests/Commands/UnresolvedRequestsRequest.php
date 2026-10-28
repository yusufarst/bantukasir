<?php

namespace App\Http\Requests\Commands;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Grant;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use LogicException;

/**
 * The query of RD-04, the unresolved request receipts (ROUTE_CONTRACTS
 * RD-04, RT-08; INTERACTION_CONTRACTS sections 3.2 and 3.6): `scope`, `own`
 * by default or `all`, which only the Owner may ask for (FORBIDDEN
 * otherwise); `after` or `before`, the opaque cursor of a page's boundary
 * row; `perPage`, 1 to 100, 25 by default. Any other parameter is refused
 * (TR-VAL-07), and so is any body: the read takes its parameters from the
 * query string alone, so that the authorization, the validation and the
 * query read one and the same values (TASK-008 review finding R-01).
 */
final class UnresolvedRequestsRequest extends RefusingRequest
{
    public const int PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        $user = $this->user();

        return $this->query('scope') !== 'all'
            || ($user instanceof User && Capabilities::of($user, Capability::Cap34) === Grant::Full);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', 'string', 'in:own,all'],
            'after' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9_-]{1,200}$/'],
            'before' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9_-]{1,200}$/'],
            'perPage' => ['sometimes', 'string', 'regex:/^(?:[1-9][0-9]?|100)$/'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['scope', 'after', 'before', 'perPage'];
    }

    /**
     * The query string is the only source: a body, which a GET never needs,
     * is refused, and only one cursor is given at a time.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->json()->all() !== [] || $this->request->all() !== [] || $this->files->all() !== []
                    || ($this->query->has('after') && $this->query->has('before'))) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The query string's values, validated.
     *
     * @return array{everyUser: bool, after: string|null, before: string|null, perPage: int}
     */
    public function page(): array
    {
        $validated = $this->validatedInput();

        foreach ($validated as $name => $value) {
            if (($this->query->all()[$name] ?? null) !== $value) {
                throw new LogicException('RD-04 reads the query string it validated, and nothing else.');
            }
        }

        $after = $validated['after'] ?? null;
        $before = $validated['before'] ?? null;
        $perPage = $validated['perPage'] ?? null;

        return [
            'everyUser' => ($validated['scope'] ?? 'own') === 'all',
            'after' => is_string($after) ? $after : null,
            'before' => is_string($before) ? $before : null,
            'perPage' => is_string($perPage) ? (int) $perPage : self::PER_PAGE,
        ];
    }
}
