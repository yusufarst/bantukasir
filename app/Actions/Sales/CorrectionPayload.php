<?php

namespace App\Actions\Sales;

use App\Support\Numbers\Money;
use LogicException;

/**
 * The payload of RC-05 or RC-06 as the Action reads it from an envelope that its FormRequest validated
 * (INTERACTION_CONTRACTS RC-05 and RC-06): the original Sale, the lines in the order of the request, the
 * mandatory reason and the payout as the request gave it — RC-06's method and reference, absent for a
 * return without money, or RC-05's session — which each Action reads for itself.
 */
final readonly class CorrectionPayload
{
    /**
     * @param  list<CorrectionLine>  $lines
     * @param  array<array-key, mixed>|null  $payout
     */
    private function __construct(
        public int $saleId,
        public array $lines,
        public string $reason,
        public ?array $payout,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function of(array $payload): self
    {
        $sale = $payload['saleId'] ?? null;
        $lines = $payload['lines'] ?? null;
        $reason = $payload['reason'] ?? null;
        $payout = $payload['payout'] ?? null;

        if (! is_int($sale) || ! is_array($lines) || ! is_string($reason) || ($payout !== null && ! is_array($payout))) {
            throw new LogicException('The envelope was validated before the Action.');
        }

        $read = [];

        foreach (array_values($lines) as $index => $line) {
            $values = is_array($line) ? $line : [];
            $saleLine = $values['saleLineId'] ?? null;
            $refund = $values['refundAmount'] ?? null;
            $saleable = $values['returnedSaleable'] ?? null;
            $notSaleable = $values['returnedNotSaleable'] ?? null;

            if (! is_int($saleLine) || ! is_string($refund) || ! is_string($saleable) || ! is_string($notSaleable)) {
                throw new LogicException('A line of the envelope was validated before the Action.');
            }

            $read[] = new CorrectionLine($index, $saleLine, Money::of($refund), $saleable, $notSaleable);
        }

        return new self($sale, $read, $reason, $payout);
    }

    /**
     * The money the correction refunds, the sum of its lines' (G-06).
     */
    public function refundTotal(): Money
    {
        return Money::sum(...array_map(static fn (CorrectionLine $line): Money => $line->refund, $this->lines));
    }
}
