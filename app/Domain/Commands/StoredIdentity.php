<?php

namespace App\Domain\Commands;

/**
 * A row of `command_identities` as read after a conflict or by a status
 * check (SCHEMA section 5.3): a COMMAND's identity, or the FENCE of a
 * resolution. Fingerprint and epoch are hexadecimal.
 */
final readonly class StoredIdentity
{
    public function __construct(
        public int $id,
        public string $kind,
        public string $operation,
        public string $sourceIntentId,
        public ?string $idempotencyKey,
        public ?int $actorUserId,
        public ?string $fingerprint,
        public string $recoveryEpoch,
    ) {}

    public function isFence(): bool
    {
        return $this->kind === CommandIdentities::FENCE;
    }
}
