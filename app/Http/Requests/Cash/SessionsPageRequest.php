<?php

namespace App\Http\Requests\Cash;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Cash\Sessions;
use App\Http\Requests\Commands\RefusingRequest;
use App\Models\User;
use App\Policies\ObjectScope;
use App\Queries\Cash\SessionRecords;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Validator as FrameworkValidator;

/**
 * The parameters of RP-205 (ROUTE_CONTRACTS RP-205; INTERACTION_CONTRACTS section 3.2; TECHNICAL_RULES
 * section 11.4; TASK-030 scope item 3): `status`, OPEN or CLOSED; `from` and `to`, days of
 * Asia/Jakarta, `from` never after `to`, refused at `to`; `register` and `responsible`, internal keys,
 * the Owner's filters only (UXI-136) — another viewer's request naming one is refused; `after` or
 * `before`, never both, the opaque cursor of a page's boundary row; `perPage`, 1 to 100, 25 by
 * default. Any other parameter is refused.
 */
final class SessionsPageRequest extends RefusingRequest
{
    private const string DAY = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:'.Sessions::OPEN.','.Sessions::CLOSED],
            'from' => ['sometimes', 'string', 'regex:'.self::DAY, 'date'],
            'to' => ['sometimes', 'string', 'regex:'.self::DAY, 'date'],
            'register' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
            'responsible' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
            'after' => ['sometimes', 'string', SessionRecords::CURSOR],
            'before' => ['sometimes', 'string', SessionRecords::CURSOR],
            'perPage' => ['sometimes', 'string', 'regex:/^(?:[1-9][0-9]?|100)$/D'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['status', 'from', 'to', 'register', 'responsible', 'after', 'before', 'perPage'];
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

                foreach (['register', 'responsible'] as $filter) {
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
     * The query string's values, validated: a filter absent is null.
     *
     * @return array{status: string|null, from: string|null, to: string|null, register: int|null, responsible: int|null, after: string|null, before: string|null, perPage: int}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $text = static function (string $name) use ($validated): ?string {
            $value = $validated[$name] ?? null;

            return is_string($value) ? $value : null;
        };
        $perPage = $text('perPage');

        return [
            'status' => $text('status'),
            'from' => $text('from'),
            'to' => $text('to'),
            'register' => ObjectScope::key($text('register')),
            'responsible' => ObjectScope::key($text('responsible')),
            'after' => $text('after'),
            'before' => $text('before'),
            'perPage' => $perPage === null ? SessionRecords::PER_PAGE : (int) $perPage,
        ];
    }
}
