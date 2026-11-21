<?php

namespace App\Http\Requests\Reporting;

use App\Http\Requests\Commands\RefusingRequest;
use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use Illuminate\Contracts\Validation\Validator;
use InvalidArgumentException;

/**
 * The parameter of RP-501 (ROUTE_CONTRACTS RP-501; INTERACTION_CONTRACTS section 3.4; SALES_CASH BR-RPT-01;
 * TASK-041 scope item 2): `date`, an Asia/Jakarta date written YYYY-MM-DD, today at the request by default. A date
 * that does not exist, or that is after today in Asia/Jakarta at the request, is refused VALIDATION_FAILED, as the
 * page refuses it at its date field before it fetches anything. Any other parameter is refused.
 */
final class DailySummaryRequest extends RefusingRequest
{
    private const string DATE = 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['date' => ['sometimes', 'string', self::DATE]];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['date'];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $date = $this->query('date');

                if ($validator->errors()->isNotEmpty() || ! is_string($date)) {
                    return;
                }

                try {
                    if (BusinessDate::fromString($date)->isAfter(BusinessDate::ofInstant(app(Clock::class)->now()))) {
                        $validator->errors()->add('', '');
                    }
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The date of the query string, validated: today in Asia/Jakarta when none is given.
     */
    public function date(): BusinessDate
    {
        $date = $this->validatedInput()['date'] ?? null;

        return is_string($date) ? BusinessDate::fromString($date) : BusinessDate::ofInstant(app(Clock::class)->now());
    }
}
