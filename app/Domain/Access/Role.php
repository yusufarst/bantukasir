<?php

namespace App\Domain\Access;

/**
 * The three fixed roles (DOMAIN_MODEL BR-USR-01, D-032), each stored as a
 * flag of its own on `users` (SCHEMA section 5.1). SUPER_ADMIN is the Owner
 * and is never combined with another role (BR-USR-02).
 */
enum Role: string
{
    case SuperAdmin = 'SUPER_ADMIN';
    case OperationsAdmin = 'OPERATIONS_ADMIN';
    case Cashier = 'CASHIER';

    /** The column of `users` that holds the role. */
    public function column(): string
    {
        return match ($this) {
            self::SuperAdmin => 'role_super_admin',
            self::OperationsAdmin => 'role_operations_admin',
            self::Cashier => 'role_cashier',
        };
    }

    /** The COPY_DECK key of the role's name (section 3). */
    public function labelKey(): string
    {
        return match ($this) {
            self::SuperAdmin => 'access.role.super_admin',
            self::OperationsAdmin => 'access.role.operations_admin',
            self::Cashier => 'access.role.cashier',
        };
    }
}
