<?php

namespace App\DTO\Access;

use App\DTO\PageData;

/**
 * The page data of UXP-604 "Detail pengguna" (ROUTE_CONTRACTS RP-604) and,
 * with fewer fields, of UXP-605 "Ubah pengguna" (RP-605): the account's
 * identity, roles, status and version, whether a password is set, when its link
 * expires, and whether it is a SUPER_ADMIN account or the viewer's own, which
 * decide the controls (UXI-348). Never the password or the link's token
 * (SEC-CH-01). TASK-030 adds the OPEN cash session.
 */
final readonly class UserDetailData implements PageData
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
        public int $version,
        public bool $passwordSet,
        public ?string $linkExpiresAt,
        public bool $superAdmin,
        public bool $self,
    ) {}

    /**
     * @return array{id: int, displayName: string, email: string|null, roles: list<'SUPER_ADMIN'|'OPERATIONS_ADMIN'|'CASHIER'>, status: 'ACTIVE'|'DEACTIVATED'|'ANONYMIZED', version: int, passwordSet: bool, linkExpiresAt: string|null, superAdmin: bool, self: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->displayName,
            'email' => $this->email,
            'roles' => $this->roles,
            'status' => $this->status,
            'version' => $this->version,
            'passwordSet' => $this->passwordSet,
            'linkExpiresAt' => $this->linkExpiresAt,
            'superAdmin' => $this->superAdmin,
            'self' => $this->self,
        ];
    }

    /**
     * The fields of the edit form (RP-605).
     *
     * @return array{id: int, displayName: string, email: string|null, roles: list<'SUPER_ADMIN'|'OPERATIONS_ADMIN'|'CASHIER'>, version: int}
     */
    public function forEdit(): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->displayName,
            'email' => $this->email,
            'roles' => $this->roles,
            'version' => $this->version,
        ];
    }
}
