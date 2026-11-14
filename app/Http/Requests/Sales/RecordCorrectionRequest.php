<?php

namespace App\Http\Requests\Sales;

use App\Actions\Sales\RecordCorrection;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Sales\Corrections\Payout;

/**
 * The FormRequest of RC-06 `sale_correction.other` (INTERACTION_CONTRACTS RC-06; TECHNICAL_RULES sections
 * 5, 11.1 to 11.3; COPY_DECK section 16.1; BR-RFD-02, BR-RFD-05), on the base of a Sale's correction
 * (CorrectionRequest, which TASK-034 drew from this request for RC-05): the lines and the reason, and
 * `payout` — its `method`, CASH from the Owner's own funds or BANK_TRANSFER, and the `reference` of the
 * payout made, 1 to 100 characters — present exactly when money is refunded, absent for a return without
 * money. The payout takes no session or amount: the server reads or computes them (TR-VAL-03), and the
 * allowlist refuses them.
 *
 * Refused before any lock (RC-06): a Sale line named twice, VALIDATION_FAILED at each repeated line; no line
 * that refunds money or returns goods, at `payload.lines`; money refunded without a payout, at
 * `payload.payout`, or a payout without money; anything else that fails, at its fields.
 */
final class RecordCorrectionRequest extends CorrectionRequest
{
    public function operation(): string
    {
        return RecordCorrection::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payoutRules(): array
    {
        return [
            'payout' => ['sometimes', 'array'],
            'payout.method' => ['required_with:payload.payout', 'string', 'in:'.implode(',', Payout::METHODS)],
            'payout.reference' => ['required_with:payload.payout', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function payoutKeys(): array
    {
        return [
            'payload.payout.method.required_with' => ['ux.validation.required'],
            'payload.payout.method.in' => ['ux.validation.choose_one'],
            'payload.payout.reference.required_with' => ['ux.validation.required'],
            'payload.payout.reference.max' => ['ux.validation.text_max', ['maks' => 100]],
        ];
    }

    protected function payoutWrong(): bool
    {
        return $this->payoutProblem() !== null || $this->missingPayoutFields() !== [];
    }

    /**
     * Money without a payout is refused at `payload.payout`, and a payout without its method or reference
     * at those; a payout without money falls through, VALIDATION_FAILED without a field, since COPY_DECK
     * names no text for it and no page sends it.
     */
    protected function payoutRefusal(): ?CommandRefused
    {
        $required = [['key' => 'ux.validation.required', 'params' => []]];
        $problem = $this->payoutProblem();
        $missing = $this->missingPayoutFields();

        if ($problem === 'missing') {
            return new CommandRefused(ErrorCode::ValidationFailed, fields: ['payload.payout' => $required]);
        }

        if ($problem === null && $missing !== []) {
            return new CommandRefused(ErrorCode::ValidationFailed, fields: array_fill_keys($missing, $required));
        }

        return null;
    }

    /**
     * The payout against the money refunded: 'missing' when money is refunded without one, 'unexpected'
     * when one comes without money, or null (RC-06: absent for a return without money).
     */
    private function payoutProblem(): ?string
    {
        $payload = $this->input('payload');
        $present = is_array($payload) && array_key_exists('payout', $payload);
        $money = $this->refundsMoney();

        return match (true) {
            $money && ! $present => 'missing',
            ! $money && $present => 'unexpected',
            default => null,
        };
    }

    /**
     * The fields of a payout that it lacks, by path.
     *
     * @return list<string>
     */
    private function missingPayoutFields(): array
    {
        $payout = $this->input('payload.payout');

        if (! is_array($payout)) {
            return [];
        }

        $missing = [];

        foreach (['method', 'reference'] as $field) {
            $value = $payout[$field] ?? null;

            if ($value === null || $value === '') {
                $missing[] = 'payload.payout.'.$field;
            }
        }

        return $missing;
    }
}
