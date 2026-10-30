<?php

namespace App\DTO\Access;

use App\DTO\PageData;

/**
 * A row of UXP-602 "Pengguna" (ROUTE_CONTRACTS RP-602): the Owner's list of
 * accounts. The placeholder address of an anonymized account is never shown
 * (COPY_DECK section 3): its email is null. TASK-027 adds the OPEN-session mark.
 */
final readonly class UserRowData implements PageData
{
    /**
     * @param  list<'SUPER_ADMIN'|'OPERATIONS_ADMIN'|'CASHIER'>  $roles  in the order of COPY_DECK section 3
     * @param  'ACTIVE'|'DEACTIVATED'|'ANONYMIZED'  $status
     */
    public function __construct(
        public int $id,
        public string $displayName,
        public ?string $email,
        public array $roles,
        public string $status,
    ) {}

    /**
     * @return array{id: int, displayName: string, email: string|null, roles: list<'SUPER_ADMIN'|'OPERATIONS_ADMIN'|'CASHIER'>, status: 'ACTIVE'|'DEACTIVATED'|'ANONYMIZED'}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->displayName,
            'email' => $this->email,
            'roles' => $this->roles,
            'status' => $this->status,
        ];
    }
}
