<?php

namespace App\Http\Requests\Sales;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Http\Requests\Commands\RefusingRequest;
use App\Models\User;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Keyset;
use App\Queries\Sales\SaleRecords;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Validator as FrameworkValidator;

/**
 * The parameters of RP-201 (ROUTE_CONTRACTS RP-201; INTERACTION_CONTRACTS section 3.2; TECHNICAL_RULES
 * section 11.4; TASK-031 scope item 4): `from` and `to`, business dates of Asia/Jakarta, `from` never
 * after `to`, refused at `to`; `number`, a Sale's number of up to 32 characters, matched exactly;
 * `cashier` and `register`, internal keys, the Owner's filters only (UXI-114) — another viewer's request
 * naming one is refused; `after` or `before`, never both, the opaque cursor of a page's boundary row;
 * `perPage`, 1 to 100, 25 by default. Any other parameter is refused.
 */
final class SalesPageRequest extends RefusingRequest
{
    private const string DAY = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'string', 'regex:'.self::DAY, 'date'],
            'to' => ['sometimes', 'string', 'regex:'.self::DAY, 'date'],
            'number' => ['sometimes', 'string', 'max:32'],
            'cashier' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
            'register' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
            'after' => ['sometimes', 'string', Keyset::CURSOR],
            'before' => ['sometimes', 'string', Keyset::CURSOR],
            'perPage' => ['sometimes', 'string', 'regex:/^(?:[1-9][0-9]?|100)$/D'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['from', 'to', 'number', 'cashier', 'register', 'after', 'before', 'perPage'];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'to.prohibited' => ['ux.validation.date_order'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $viewer = $this->user();
                $owner = $viewer instanceof User && Capabilities::allows($viewer, Capability::Cap22);

                if ($this->query->has('after') && $this->query->has('before')) {
                    $validator->errors()->add('', '');
                }

                foreach (['cashier', 'register'] as $filter) {
                    if ($this->query->has($filter) && (! $owner || ObjectScope::key($this->query($filter)) === null)) {
                        $validator->errors()->add('', '');
                    }
                }

                $from = $this->query('from');
                $to = $this->query('to');

                if (is_string($from) && is_string($to) && preg_match(self::DAY, $from) === 1 && preg_match(self::DAY, $to) === 1 && strcmp($from, $to) > 0) {
                    if ($validator instanceof FrameworkValidator) {
                        $validator->addFailure('to', 'prohibited');
                    } else {
                        $validator->errors()->add('', '');
                    }
                }
            },
        ];
    }

    /**
     * The query string's values, validated: a filter absent, or a number left empty, is null.
     *
     * @return array{from: string|null, to: string|null, number: string|null, cashier: int|null, register: int|null, after: string|null, before: string|null, perPage: int}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $text = static function (string $name) use ($validated): ?string {
            $value = $validated[$name] ?? null;

            return is_string($value) && $value !== '' ? $value : null;
        };
        $perPage = $text('perPage');

        return [
            'from' => $text('from'),
            'to' => $text('to'),
            'number' => $text('number'),
            'cashier' => ObjectScope::key($text('cashier')),
            'register' => ObjectScope::key($text('register')),
            'after' => $text('after'),
            'before' => $text('before'),
            'perPage' => $perPage === null ? SaleRecords::PER_PAGE : (int) $perPage,
        ];
    }
}
