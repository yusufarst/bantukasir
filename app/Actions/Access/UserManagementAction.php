<?php

namespace App\Actions\Access;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Database\SqlState;
use App\Domain\Access\Accounts;
use App\Domain\Access\AccountStatus;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Audit\Actor;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use LogicException;
use Throwable;

/**
 * The base of RC-35 to RC-42, the user-management family (INTERACTION_CONTRACTS
 * "RC-35 to RC-42"; TECHNICAL_RULES section 7.2, "User management"; TASK-011
 * scope item 1): after the identity, the epoch and the actor guard, step 2.2 is
 * the update lock of the target account, then the expected version
 * `userVersion`, then the checks under the lock, the facts and the audit event.
 *
 * The actor's own account is never a target of these routes: it is refused
 * before any lock, so that the share lock of step 2.1 is never followed by an
 * update lock on the same row (TR-LOCK-05), and neither is any SUPER_ADMIN
 * account, except that RC-40 may anonymize a former Owner (CAP-33; D-048). Such
 * a request is FORBIDDEN and recorded in the security log. Revoking comes
 * before a change of the email, because the framework's token repository keys
 * its row by the email (SEC-ACC-04).
 */
abstract class UserManagementAction extends CommandAction
{
    /** The pages of the result (ROUTE_CONTRACTS RP-602, RP-604, RP-606). */
    public const string USERS = '/settings/users';

    /** The result's type (SCHEMA section 5.3). */
    protected const string RESULT_TYPE = 'user';

    /** The unique key of the email, whose conflict means another account took it meanwhile. */
    private const array EMAIL_KEYS = ['users_email_ci_uniq'];

    public function __construct(
        CommandServices $services,
        protected readonly Accounts $accounts,
        protected readonly OneTimeLinks $links,
    ) {
        parent::__construct($services);
    }

    /**
     * The account's detail page, for a viewer who may open it (RP-604).
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap27) && ! Capabilities::allows($viewer, Capability::Cap28)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => self::USERS.'/'.(string) $result->ref];
    }

    /**
     * The target account under its update lock (step 2.2), at the version the
     * user reviewed. Not found for a guessed identifier.
     *
     * @throws CommandRefused
     * @throws ActorNotAllowed
     */
    protected function target(CommandContext $context, User $actor): User
    {
        $id = self::whole($context->envelope->payload['userId'] ?? null);

        if ($id === $actor->id) {
            throw new ActorNotAllowed;
        }

        $target = $this->accounts->lockForUpdate($id);
        $this->services->points->afterLock('2.2');

        if ($target === null) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        if ($target->version !== ($context->envelope->expected['userVersion'] ?? null)) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        return $target;
    }

    /**
     * The target as above, which must be a staff account (CAP-33).
     *
     * @throws CommandRefused
     * @throws ActorNotAllowed
     */
    protected function staffTarget(CommandContext $context, User $actor): User
    {
        $target = $this->target($context, $actor);

        if ($target->role_super_admin) {
            throw new ActorNotAllowed;
        }

        return $target;
    }

    /**
     * The refusal of a command that the account's state does not allow.
     */
    protected static function notAllowedInState(): CommandRefused
    {
        return CommandRefused::of(ErrorCode::ValidationFailed);
    }

    /**
     * Refuses an email held by another account, with what the Owner can do
     * about it (SEC-ACC-03; COPY_DECK section 11.3).
     *
     * @throws CommandRefused
     */
    protected function refuseHeldEmail(string $email, ?int $exceptAccount): void
    {
        $holder = $this->accounts->findByEmail($email);

        if ($holder !== null && $holder->id !== $exceptAccount) {
            throw self::emailHeld($holder->status() === AccountStatus::Deactivated ? 'access.user_form.email_taken_deactivated' : 'access.user_form.email_taken');
        }
    }

    /**
     * Applies the change; another account that took the email meanwhile is
     * refused like one that held it.
     *
     * @param  array<string, mixed>  $columns
     *
     * @throws CommandRefused
     */
    protected function changeAccount(User $target, array $columns): void
    {
        try {
            $this->accounts->change($target, $columns);
        } catch (Throwable $failure) {
            throw SqlState::uniqueOn($failure, self::EMAIL_KEYS) ? self::emailHeld('access.user_form.email_taken') : $failure;
        }
    }

    /**
     * Ends the account's sign-in sessions, write step included, and notes it
     * for the security log (SEC-SESS-04).
     */
    protected function endSessions(User $target): void
    {
        if ($this->accounts->revokeSessions($target->id) > 0) {
            $this->sessionsEnded($target->id);
        }

        $this->written();
    }

    /**
     * Revokes the account's link, write step included (SEC-ACC-04).
     */
    protected function revokeLink(User $target): void
    {
        $this->links->revoke($target);
        $this->written();
    }

    protected static function actorOf(User $actor): Actor
    {
        return Actor::user($actor->id, $actor->display_name);
    }

    protected static function result(User $account): StoredResult
    {
        return new StoredResult(self::RESULT_TYPE, $account->id);
    }

    /**
     * When a link issued now expires, as the pages read an instant.
     */
    protected function linkExpiry(): string
    {
        return $this->services->clock->now()->addMinutes(OneTimeLinks::minutes())->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * A text of the payload, validated by the FormRequest.
     */
    protected static function text(mixed $value): string
    {
        return is_string($value) ? $value : throw new LogicException('A text of the payload was validated before the Action.');
    }

    /**
     * A whole number of the payload, validated by the FormRequest.
     */
    protected static function whole(mixed $value): int
    {
        return is_int($value) ? $value : throw new LogicException('A whole number of the payload was validated before the Action.');
    }

    /**
     * A list of the payload, validated by the FormRequest.
     *
     * @return array<array-key, mixed>
     */
    protected static function items(mixed $value): array
    {
        return is_array($value) ? $value : throw new LogicException('A list of the payload was validated before the Action.');
    }

    private static function emailHeld(string $key): CommandRefused
    {
        return new CommandRefused(ErrorCode::ValidationFailed, fields: ['payload.email' => [['key' => $key, 'params' => []]]]);
    }
}
