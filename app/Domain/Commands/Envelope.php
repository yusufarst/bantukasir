<?php

namespace App\Domain\Commands;

/**
 * The command envelope of ADR-007 section 1 as the server validated and
 * normalized it (INTERACTION_CONTRACTS section 1.1): the operation and its
 * version, the source intent, the idempotency key, the recovery epoch the
 * page received (hexadecimal), the expected versions and the business
 * payload. The actor, the fingerprint and the received time are the
 * server's, never the client's (CommandContext).
 */
final readonly class Envelope
{
    /**
     * @param  array<array-key, mixed>  $expected
     * @param  array<array-key, mixed>  $payload
     */
    public function __construct(
        public string $operation,
        public int $version,
        public string $sourceIntentId,
        public string $idempotencyKey,
        public string $recoveryEpoch,
        public array $expected,
        public array $payload,
    ) {}
}
