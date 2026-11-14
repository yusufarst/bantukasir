<?php

namespace App\Domain\Inventory\Onboarding;

/**
 * A product's onboarding entry, its eligibility of D-087 (INTERACTION_CONTRACTS section 3.1, the
 * `opening` context; BR-INV-26, BR-INV-27, BR-INV-47, BR-INV-48; G-22, G-24):
 *
 * - ZERO_COUNTED: an effective zero count, one not withdrawn, with its key;
 * - COUNTED: an effective OPENING, one not reversed, with its line and its movement;
 * - HAS_MOVEMENT: another movement than an OPENING or a REVERSAL exists, so no opening entry is
 *   possible (BR-INV-26);
 * - REPLACEABLE: every OPENING of the product is reversed and it has no effective entry, with the
 *   latest line, which no line replaced yet, and that line's OPENING (BR-INV-48);
 * - ELIGIBLE: no movement and no effective entry.
 *
 * A product has at most one effective entry (G-22), so the order of these is the order in which the
 * facts decide it.
 */
final readonly class OnboardingEntry
{
    public const string ELIGIBLE = 'ELIGIBLE';

    public const string ZERO_COUNTED = 'ZERO_COUNTED';

    public const string COUNTED = 'COUNTED';

    public const string REPLACEABLE = 'REPLACEABLE';

    public const string HAS_MOVEMENT = 'HAS_MOVEMENT';

    private function __construct(
        public int $productId,
        public string $eligibility,
        public ?int $zeroCountId,
        public ?int $lineId,
        public ?int $movementId,
    ) {}

    /**
     * The entry that these facts of the product decide: its effective zero count, its effective
     * OPENING's line and movement, its latest opening line and that line's movement, and whether it
     * has a movement of another type.
     */
    public static function decide(int $productId, ?int $zeroCount, ?int $effectiveLine, ?int $effectiveMovement, ?int $latestLine, ?int $latestMovement, bool $otherMovement): self
    {
        if ($zeroCount !== null) {
            return new self($productId, self::ZERO_COUNTED, $zeroCount, null, null);
        }

        if ($effectiveLine !== null && $effectiveMovement !== null) {
            return new self($productId, self::COUNTED, null, $effectiveLine, $effectiveMovement);
        }

        if ($otherMovement) {
            return new self($productId, self::HAS_MOVEMENT, null, null, null);
        }

        if ($latestLine !== null && $latestMovement !== null) {
            return new self($productId, self::REPLACEABLE, null, $latestLine, $latestMovement);
        }

        return new self($productId, self::ELIGIBLE, null, null, null);
    }

    /**
     * Whether the product has an effective onboarding entry: a zero count not withdrawn, or an
     * OPENING not reversed (G-22).
     */
    public function isEffective(): bool
    {
        return $this->eligibility === self::ZERO_COUNTED || $this->eligibility === self::COUNTED;
    }

    /**
     * What RD-01 in the context `opening` adds to the product's row (INTERACTION_CONTRACTS section
     * 3.1): its eligibility, the zero count of ZERO_COUNTED and the line that REPLACEABLE would replace.
     *
     * @return array{eligibility: string, zeroCountId: int|null, replacesLineId: int|null}
     */
    public function toArray(): array
    {
        return [
            'eligibility' => $this->eligibility,
            'zeroCountId' => $this->zeroCountId,
            'replacesLineId' => $this->eligibility === self::REPLACEABLE ? $this->lineId : null,
        ];
    }
}
