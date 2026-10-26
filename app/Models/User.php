<?php

namespace App\Models;

use App\Domain\Access\AccountStatus;
use App\Domain\Access\Role;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Foundation\Auth\User as Authenticatable;
use LogicException;

/**
 * The persistence model of an account (SCHEMA section 5.1, `users`), which
 * the session guard and the password broker need (ADR-004). It carries no
 * business rule and no side effect: the Access module's services and the
 * Actions write it, never a request's input (TR-VAL-02), and it is never
 * serialized to the browser — pages receive DTOs (SEC-CH-01).
 *
 * There is no remember token (SEC-AUTH-07) and the application never sends
 * email (D-049): the broker's notification is refused.
 *
 * @property int $id
 * @property string $display_name
 * @property string $email
 * @property string|null $password
 * @property bool $role_super_admin
 * @property bool $role_operations_admin
 * @property bool $role_cashier
 * @property string $status
 * @property CarbonImmutable|null $deactivated_at
 * @property CarbonImmutable|null $anonymized_at
 * @property int $version
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class User extends Authenticatable implements CanResetPassword
{
    protected $table = 'users';

    /** Nothing is mass assigned (TR-VAL-02). */
    protected $guarded = ['*'];

    protected $hidden = ['password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role_super_admin' => 'boolean',
            'role_operations_admin' => 'boolean',
            'role_cashier' => 'boolean',
            'version' => 'integer',
            'deactivated_at' => 'immutable_datetime',
            'anonymized_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function status(): AccountStatus
    {
        return AccountStatus::from($this->status);
    }

    public function isActive(): bool
    {
        return $this->status() === AccountStatus::Active;
    }

    /**
     * The account's roles in the order of COPY_DECK section 3: Kasir, Admin
     * Operasional; or Pemilik alone.
     *
     * @return list<Role>
     */
    public function roles(): array
    {
        $roles = [];

        foreach ([Role::SuperAdmin, Role::Cashier, Role::OperationsAdmin] as $role) {
            if ($this->getAttribute($role->column()) === true) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /** No remember token exists (SEC-AUTH-07). */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        throw new LogicException('The application never sends email: a one-time link is handed over in person (D-049).');
    }
}
