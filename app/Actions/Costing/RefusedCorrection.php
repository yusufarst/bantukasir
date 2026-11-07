<?php

namespace App\Actions\Costing;

use App\Domain\Costing\CostSource;
use App\Support\Numbers\Cost;

/**
 * What the mark of a refused cost correction records (SCHEMA section 5.8): the cost source, the
 * cause, how many corrections the refused attempt saw, and — for its audit event's Owner-only part
 * alone — the value attempted and its reason (BR-AUD-02).
 */
final readonly class RefusedCorrection
{
    public function __construct(
        public CostSource $source,
        public string $rejection,
        public int $correctionsSeen,
        public Cost $newAmount,
        public ?string $zeroNote,
        public string $reason,
    ) {}

    /**
     * The same refused correction, with the cause that the replay gave.
     */
    public function because(string $rejection): self
    {
        return new self($this->source, $rejection, $this->correctionsSeen, $this->newAmount, $this->zeroNote, $this->reason);
    }
}
