<?php

namespace App\Http\Requests\Reporting;

use App\Http\Requests\Commands\RefusingRequest;
use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use App\Support\Time\Period;
use App\Support\Time\PeriodKind;
use Illuminate\Contracts\Validation\Validator;
use InvalidArgumentException;

/**
 * The parameters of RP-502 (ROUTE_CONTRACTS RP-502; INTERACTION_CONTRACTS section 3.4; TECHNICAL_RULES
 * section 11.4; BR-COST-37; TASK-040 scope item 1): `period`, one of `today`, `7d`, `this_month`,
 * `last_month` and `custom`, Bulan Ini by default; `from` and `to`, Asia/Jakarta dates, both required for
 * `custom` and refused with any other period. A custom range that starts after its end, ends after today
 * in Asia/Jakarta at the request, or is longer than 366 days is refused VALIDATION_FAILED, as the page
 * refuses it at its date fields before it fetches anything. Any other parameter is refused.
 */
final class FinanceReportRequest extends RefusingRequest
{
    private const string DATE = 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:today,7d,this_month,last_month,custom'],
            'from' => ['sometimes', 'string', self::DATE],
            'to' => ['sometimes', 'string', self::DATE],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['period', 'from', 'to'];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $custom = $this->query('period') === PeriodKind::Custom->value;
                $dates = [$this->query('from'), $this->query('to')];

                if (! $custom) {
                    if ($this->query->has('from') || $this->query->has('to')) {
                        $validator->errors()->add('', '');
                    }

                    return;
                }

                if (! is_string($dates[0]) || ! is_string($dates[1])) {
                    $validator->errors()->add('', '');

                    return;
                }

                // A date that does not exist, and each refusal of InvalidPeriod.
                try {
                    Period::custom(BusinessDate::fromString($dates[0]), BusinessDate::fromString($dates[1]), app(Clock::class)->now());
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The period of the query string, validated: Bulan Ini when none is given.
     *
     * @return array{kind: PeriodKind, from: BusinessDate|null, to: BusinessDate|null}
     */
    public function period(): array
    {
        $validated = $this->validatedInput();
        $period = $validated['period'] ?? null;
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        $kind = is_string($period) ? PeriodKind::from($period) : PeriodKind::ThisMonth;

        return [
            'kind' => $kind,
            'from' => $kind === PeriodKind::Custom && is_string($from) ? BusinessDate::fromString($from) : null,
            'to' => $kind === PeriodKind::Custom && is_string($to) ? BusinessDate::fromString($to) : null,
        ];
    }
}
