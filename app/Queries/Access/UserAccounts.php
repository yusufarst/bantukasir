<?php

namespace App\Queries\Access;

use App\Domain\Access\AccountStatus;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Access\Role;
use App\DTO\Access\UserDetailData;
use App\DTO\Access\UserRowData;
use App\Models\User;
use App\Policies\ObjectScope;

/**
 * The reads of the Owner's user pages (ROUTE_CONTRACTS RP-602, RP-604 to RP-606):
 * every account, by its filters, as DTOs. The list takes no paging parameter
 * (RP-602), so it is bounded instead; a shop's accounts stay far below the bound.
 */
final class UserAccounts
{
    /** The most rows of RP-602 (TR-PAGE; an account is added by a person, one at a time). */
    public const int LIST_BOUND = 500;

    public function __construct(private readonly OneTimeLinks $links) {}

    /**
     * @return list<UserRowData>
     */
    public function list(?string $status, ?string $role): array
    {
        $query = User::query()->orderBy('display_name')->orderBy('id')->limit(self::LIST_BOUND);

        if ($status !== null) {
            $query->where('status', strtoupper($status));
        }

        if ($role !== null) {
            $query->where(match ($role) {
                'owner' => Role::SuperAdmin->column(),
                'cashier' => Role::Cashier->column(),
                default => Role::OperationsAdmin->column(),
            }, true);
        }

        $rows = [];

        foreach ($query->get() as $user) {
            $rows[] = new UserRowData($user->id, $user->display_name, self::shownEmail($user), self::roleCodes($user), self::statusCode($user));
        }

        return $rows;
    }

    /**
     * One account, or NOT_FOUND for a guessed identifier (SEC-AZ-06).
     */
    public function detail(mixed $identifier, User $viewer): UserDetailData
    {
        return $this->detailOf(ObjectScope::find(User::query(), $identifier), $viewer);
    }

    /**
     * A staff account that is not anonymized, the only kind that can be edited
     * (RP-605), or NOT_FOUND.
     */
    public function editable(mixed $identifier, User $viewer): UserDetailData
    {
        return $this->detailOf(ObjectScope::find(User::query()->where('role_super_admin', false)->where('status', '<>', AccountStatus::Anonymized->value), $identifier), $viewer);
    }

    private function detailOf(User $user, User $viewer): UserDetailData
    {
        return new UserDetailData(
            id: $user->id,
            displayName: $user->display_name,
            email: self::shownEmail($user),
            roles: self::roleCodes($user),
            status: self::statusCode($user),
            version: $user->version,
            passwordSet: $user->password !== null,
            linkExpiresAt: $this->links->activeUntil($user)?->utc()->format('Y-m-d\TH:i:s.u\Z'),
            superAdmin: $user->role_super_admin,
            self: $user->id === $viewer->id,
        );
    }

    private static function shownEmail(User $user): ?string
    {
        return $user->status() === AccountStatus::Anonymized ? null : $user->email;
    }

    /**
     * @return list<'SUPER_ADMIN'|'OPERATIONS_ADMIN'|'CASHIER'>
     */
    private static function roleCodes(User $user): array
    {
        return array_map(static fn (Role $role): string => $role->value, $user->roles());
    }

    /**
     * @return 'ACTIVE'|'DEACTIVATED'|'ANONYMIZED'
     */
    private static function statusCode(User $user): string
    {
        return $user->status()->value;
    }
}
