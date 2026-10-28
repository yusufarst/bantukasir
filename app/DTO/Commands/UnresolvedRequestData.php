<?php

namespace App\DTO\Commands;

/**
 * One row of RD-04 (INTERACTION_CONTRACTS section 3.6; ADR-009 section 2):
 * the operation, its family, the intent, the key, whether the request
 * carried the current epoch or an earlier one, the received time in UTC and
 * the outcome, PENDING or HELD; with `scope=all` also the user's internal
 * identifier and display name. Never a payload, an amount or a product.
 */
final readonly class UnresolvedRequestData
{
    public function __construct(
        public string $operation,
        public string $family,
        public string $sourceIntentId,
        public string $idempotencyKey,
        public string $epoch,
        public string $receivedAt,
        public string $outcome,
        public ?int $userId = null,
        public ?string $userDisplayName = null,
    ) {}

    /**
     * @return array<string, string|array{id: int, displayName: string}>
     */
    public function toArray(bool $withUser): array
    {
        $row = [
            'operation' => $this->operation,
            'family' => $this->family,
            'sourceIntentId' => $this->sourceIntentId,
            'idempotencyKey' => $this->idempotencyKey,
            'epoch' => $this->epoch,
            'receivedAt' => $this->receivedAt,
            'outcome' => $this->outcome,
        ];

        if ($withUser && $this->userId !== null && $this->userDisplayName !== null) {
            $row['user'] = ['id' => $this->userId, 'displayName' => $this->userDisplayName];
        }

        return $row;
    }
}
