<?php

namespace App\Actions\Commands;

use App\Domain\Commands\Envelope;
use App\Domain\Commands\Fingerprint;
use Carbon\CarbonImmutable;

/**
 * What a command's Action receives (INTERACTION_CONTRACTS section 1.1): the
 * validated envelope, with what the server derives — the actor from the
 * sign-in session (SEC-AZ-05), the fingerprint (ADR-007 section 1) and the
 * time the request was received by the application's web server
 * (TR-TIME-03).
 */
final readonly class CommandContext
{
    public string $fingerprint;

    public function __construct(
        public int $actorId,
        public Envelope $envelope,
        public CarbonImmutable $receivedAt,
    ) {
        $this->fingerprint = Fingerprint::of(
            $envelope->operation,
            $envelope->version,
            Fingerprint::user($actorId),
            $envelope->expected,
            $envelope->payload,
        );
    }

    public function receivedAtText(): string
    {
        return $this->receivedAt->format('Y-m-d H:i:s.uP');
    }
}
