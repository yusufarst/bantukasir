<?php

namespace App\Http\Requests\Sales;

use App\Actions\Sales\CorrectionAction;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Formatting\Format;
use App\Support\Numbers\Quantity;
use App\Support\Validation\MoneyRule;
use App\Support\Validation\QuantityRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest base of a Sale's correction, RC-05 and RC-06 (INTERACTION_CONTRACTS RC-05 and RC-06;
 * TECHNICAL_RULES sections 5, 11.1 to 11.3; COPY_DECK section 16.1; BR-RFD-02; TASK-033, TASK-034): `saleId`,
 * the original Sale; `lines`, 1 to 200, each with its `saleLineId`, its `refundAmount` in whole rupiah and its
 * `returnedSaleable` and `returnedNotSaleable`, each zero or more; `reason`, 1 to 1,000 characters without a
 * control character (TR-VAL-05); and the payout of each operation, whose rules and refusals its request gives.
 * No expected versions: the bounds are checked under the locks. A line takes no price, total, cost or product:
 * the server reads or computes them (TR-VAL-03), and the allowlist refuses them. Identifiers are JSON whole
 * numbers (TR-VAL-06); a quantity is judged here at the largest precision, and against its product's in the
 * Action.
 *
 * Refused before any lock: a Sale line named twice, VALIDATION_FAILED at each repeated line; with every field
 * valid, the payout's own refusal of each operation, then no line that refunds money or returns goods, at
 * `payload.lines`; anything else that fails, at its fields.
 */
abstract class CorrectionRequest extends CommandRequest
{
    protected const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    /** The quantities of a line, each zero or more. */
    private const array RETURNED = ['returnedSaleable', 'returnedNotSaleable'];

    /**
     * The rules of the payout, by path below `payload`.
     *
     * @return array<string, mixed>
     */
    abstract protected function payoutRules(): array;

    /**
     * The message keys of the payout's rules, by path and rule.
     *
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    abstract protected function payoutKeys(): array;

    /**
     * Whether the payout breaks a rule of its operation that its rules cannot say: a field that the
     * validator alone would let pass.
     */
    abstract protected function payoutWrong(): bool;

    /**
     * With every field valid, the refusal that the payout's own rule gives, or null.
     */
    abstract protected function payoutRefusal(): ?CommandRefused;

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'saleId' => ['required', 'integer', 'min:1'],
            'lines' => ['required', 'array', 'list', 'min:1', 'max:'.CorrectionAction::MAX_LINES],
            'lines.*' => ['required', 'array'],
            'lines.*.saleLineId' => ['required', 'integer', 'min:1'],
            'lines.*.refundAmount' => ['required', new MoneyRule],
            'lines.*.returnedSaleable' => ['required', new QuantityRule(Quantity::MAX_PRECISION, zeroAllowed: true)],
            'lines.*.returnedNotSaleable' => ['required', new QuantityRule(Quantity::MAX_PRECISION, zeroAllowed: true)],
            'reason' => ['required', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
            ...$this->payoutRules(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $sale = $this->input('payload.saleId');

            if ($sale !== null && ! is_int($sale)) {
                $validator->errors()->add('', '');
            }

            foreach ($this->lineValues() as $line) {
                $saleLine = $line['saleLineId'] ?? null;

                if ($saleLine !== null && ! is_int($saleLine)) {
                    $validator->errors()->add('', '');
                }
            }

            if ($this->repeatedLines() !== [] || $this->payoutWrong() || ! $this->anyLineMoves()) {
                $validator->errors()->add('', '');
            }
        }];
    }

    /**
     * A Sale line named twice is refused at each line that repeats it; with every field valid, the
     * payout's own refusal, then lines that move nothing at `payload.lines`; every other failure is the
     * base's.
     */
    protected function failedValidation(Validator $validator): never
    {
        $repeated = $this->repeatedLines();

        if ($repeated !== []) {
            throw new CommandRefused(ErrorCode::ValidationFailed, lines: $repeated);
        }

        if ($validator->failed() === []) {
            $payout = $this->payoutRefusal();

            if ($payout !== null) {
                throw $payout;
            }

            if (! $this->anyLineMoves()) {
                throw new CommandRefused(ErrorCode::ValidationFailed, fields: ['payload.lines' => [['key' => 'ux.validation.lines_min', 'params' => []]]]);
            }
        }

        parent::failedValidation($validator);
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $keys = [
            'payload.saleId.integer' => ['ux.validation.choose_one'],
            'payload.saleId.min' => ['ux.validation.choose_one'],
            'payload.lines.required' => ['ux.validation.lines_min'],
            'payload.lines.min' => ['ux.validation.lines_min'],
            'payload.lines.max' => ['ux.validation.lines_max', ['maks' => CorrectionAction::MAX_LINES]],
            'payload.lines.*.saleLineId.integer' => ['ux.validation.choose_one'],
            'payload.lines.*.saleLineId.min' => ['ux.validation.choose_one'],
            'payload.reason.required' => ['ux.validation.required'],
            'payload.reason.max' => ['ux.validation.text_max', ['maks' => 1000]],
            ...$this->payoutKeys(),
        ];
        $rule = new QuantityRule(Quantity::MAX_PRECISION, zeroAllowed: true);

        foreach ($this->lineValues() as $index => $line) {
            $path = 'payload.lines.'.(string) $index;
            $keys[$path.'.refundAmount.money_rule'] = ['ux.validation.'.(MoneyRule::problem($line['refundAmount'] ?? null, $path.'.refundAmount') ?? 'money')];

            foreach (self::RETURNED as $field) {
                $problem = $rule->problem($line[$field] ?? null, $path.'.'.$field);

                if ($problem !== null) {
                    $keys[$path.'.'.$field.'.quantity_rule'] = ['ux.validation.'.$problem, $problem === 'quantity_decimals' ? ['desimal' => Format::count(Quantity::MAX_PRECISION)] : []];
                }
            }
        }

        return $keys;
    }

    /**
     * Whether a valid line refunds money above zero.
     */
    protected function refundsMoney(): bool
    {
        foreach ($this->lineValues() as $index => $line) {
            $amount = $line['refundAmount'] ?? null;

            if (is_string($amount) && $amount !== '0' && MoneyRule::problem($amount, 'payload.lines.'.(string) $index.'.refundAmount') === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a valid line refunds money or returns goods (RC-05 and RC-06, "at least one line with an amount
     * or a quantity").
     */
    protected function anyLineMoves(): bool
    {
        if ($this->refundsMoney()) {
            return true;
        }

        $positive = new QuantityRule(Quantity::MAX_PRECISION);

        foreach ($this->lineValues() as $index => $line) {
            foreach (self::RETURNED as $field) {
                if ($positive->problem($line[$field] ?? null, 'payload.lines.'.(string) $index.'.'.$field) === null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The lines of the request, each as an array, by index.
     *
     * @return array<int, array<array-key, mixed>>
     */
    private function lineValues(): array
    {
        $lines = $this->input('payload.lines');
        $values = [];

        foreach (is_array($lines) ? array_values($lines) : [] as $index => $line) {
            $values[$index] = is_array($line) ? $line : [];
        }

        return $values;
    }

    /**
     * The lines that name a Sale line an earlier line names, each by its index with that Sale line
     * (INTERACTION_CONTRACTS section 1.6).
     *
     * @return array<int, array{saleLineId: int}>
     */
    private function repeatedLines(): array
    {
        $seen = [];
        $repeated = [];

        foreach ($this->lineValues() as $index => $line) {
            $saleLine = $line['saleLineId'] ?? null;

            if (! is_int($saleLine)) {
                continue;
            }

            if (isset($seen[$saleLine])) {
                $repeated[$index] = ['saleLineId' => $saleLine];
            }

            $seen[$saleLine] = true;
        }

        return $repeated;
    }
}
