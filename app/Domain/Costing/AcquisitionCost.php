<?php

namespace App\Domain\Costing;

use App\Support\Numbers\Cost;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;
use LogicException;

/**
 * The acquisition cost that a stock increase carries (COSTING section 2; BR-COST-02 to
 * BR-COST-04; INTERACTION_CONTRACTS RC-07): the total for the line, KNOWN or UNKNOWN, read
 * from a `cost` that its FormRequest validated. UNKNOWN is a status and has no amount; an
 * explicit zero carries its evidence or reason. Whether the actor may enter an explicit zero
 * is the Action's to check, under the actor's lock.
 */
final readonly class AcquisitionCost
{
    public const string KNOWN = 'KNOWN';

    public const string UNKNOWN = 'UNKNOWN';

    private function __construct(
        public Cost $cost,
        public ?string $zeroNote,
    ) {}

    /**
     * @param  array<array-key, mixed>  $cost
     *
     * @throws NonCanonicalNumber
     * @throws NumberOutOfRange
     */
    public static function fromPayload(array $cost): self
    {
        $status = $cost['status'] ?? null;

        if ($status === self::UNKNOWN) {
            return new self(Cost::unknown(), null);
        }

        $amount = $cost['amount'] ?? null;
        $note = $cost['zeroEvidence'] ?? null;

        if ($status !== self::KNOWN || ! is_string($amount)) {
            throw new LogicException('A cost of the payload was validated before the Action.');
        }

        $known = Cost::of($amount)->assertUnit();

        if (! $known->amount()->isZero()) {
            return new self($known, null);
        }

        return new self($known, is_string($note) ? $note : throw new LogicException('An explicit zero carries its evidence or reason (BR-COST-04).'));
    }

    /**
     * Whether this is an explicit zero, which is never an UNKNOWN (BR-COST-03, BR-COST-04).
     */
    public function isExplicitZero(): bool
    {
        return $this->cost->isKnown() && $this->cost->amount()->isZero();
    }

    /**
     * The status column of SCHEMA section 3.2.
     */
    public function status(): string
    {
        return $this->cost->isKnown() ? self::KNOWN : self::UNKNOWN;
    }

    /**
     * The amount column of SCHEMA section 3.2: null for UNKNOWN, never zero.
     */
    public function amount(): ?string
    {
        return $this->cost->isKnown() ? $this->cost->toCanonical() : null;
    }
}
