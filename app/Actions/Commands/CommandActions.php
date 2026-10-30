<?php

namespace App\Actions\Commands;

use App\Actions\Access\AnonymizeAccount;
use App\Actions\Access\CreateStaffAccount;
use App\Actions\Access\DeactivateAccount;
use App\Actions\Access\IssuePasswordLink;
use App\Actions\Access\ReactivateAccount;
use App\Actions\Access\RevokePasswordLink;
use App\Actions\Access\UpdateOwnerDetails;
use App\Actions\Access\UpdateStaffAccount;
use App\Actions\Configuration\ChangeBusinessProfile;
use App\Domain\Commands\Operations;
use LogicException;

/**
 * The Action of each operation that has one (ROUTE_CONTRACTS section 6), so
 * that a status check rebuilds a committed command's result through the
 * Action that wrote it (RC-50; ADR-007 section 3). Each command's Task adds
 * its line to ACTIONS with its route, FormRequest and Action (TASK-008 scope
 * item 8); the test harness adds its probe only in the local and testing
 * environments (Operations::PROBE_ENVIRONMENTS).
 */
final class CommandActions
{
    /**
     * Operation => Action, one line per command that its Task has built.
     *
     * @var array<string, class-string<CommandAction>>
     */
    private const array ACTIONS = [
        // RC-32 (TASK-010).
        ChangeBusinessProfile::OPERATION => ChangeBusinessProfile::class,
        // RC-35 to RC-42 (TASK-011).
        CreateStaffAccount::OPERATION => CreateStaffAccount::class,
        UpdateStaffAccount::OPERATION => UpdateStaffAccount::class,
        UpdateOwnerDetails::OPERATION => UpdateOwnerDetails::class,
        DeactivateAccount::OPERATION => DeactivateAccount::class,
        ReactivateAccount::OPERATION => ReactivateAccount::class,
        AnonymizeAccount::OPERATION => AnonymizeAccount::class,
        IssuePasswordLink::OPERATION => IssuePasswordLink::class,
        RevokePasswordLink::OPERATION => RevokePasswordLink::class,
    ];

    /**
     * @return array<string, class-string<CommandAction>>
     */
    public static function built(): array
    {
        return self::ACTIONS;
    }

    /** @var array<string, class-string<CommandAction>> */
    private static array $testing = [];

    /**
     * The Action of the operation, or null when no Task has built it yet.
     */
    public static function for(string $operation): ?CommandAction
    {
        $class = self::built()[$operation] ?? self::$testing[$operation] ?? null;

        return $class === null ? null : app($class);
    }

    /**
     * @param  class-string<CommandAction>  $action
     */
    public static function allowForTesting(string $operation, string $action): void
    {
        if (! app()->environment(Operations::PROBE_ENVIRONMENTS) || ! Operations::exists($operation) || array_key_exists($operation, Operations::FAMILIES)) {
            throw new LogicException('Only a probe operation of the local and testing environments is added here.');
        }

        self::$testing[$operation] = $action;
    }
}
