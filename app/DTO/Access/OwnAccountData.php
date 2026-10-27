<?php

namespace App\DTO\Access;

use App\DTO\PageData;

/**
 * The page data of UXP-003 "Akun saya" (ROUTE_CONTRACTS RP-003): the user's
 * own display name, email and roles, and whether the user is the Owner, who
 * alone changes the Owner's own name and email (SEC-ACC-10; the control is
 * TASK-011's). Nothing else of the account — its identity, status, password,
 * times or version — reaches the page (SEC-CH-01, STK-21).
 */
final readonly class OwnAccountData implements PageData
{
    /**
     * @param  list<'SUPER_ADMIN'|'OPERATIONS_ADMIN'|'CASHIER'>  $roles  in the order of COPY_DECK section 3
     */
    public function __construct(
        public string $displayName,
        public string $email,
        public array $roles,
        public bool $owner,
    ) {}

    /**
     * @return array{displayName: string, email: string, roles: list<'SUPER_ADMIN'|'OPERATIONS_ADMIN'|'CASHIER'>, owner: bool}
     */
    public function toArray(): array
    {
        return [
            'displayName' => $this->displayName,
            'email' => $this->email,
            'roles' => $this->roles,
            'owner' => $this->owner,
        ];
    }
}
