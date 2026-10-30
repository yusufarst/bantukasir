<?php

namespace Tests\Harness\Fixtures;

use App\Actions\Access\BootstrapInput;
use App\Actions\Access\BootstrapInstallation;
use App\Actions\Access\ConsumeLink;
use App\Actions\Access\CreateStaffAccount;
use App\Actions\Commands\CommandContext;
use App\Database\DatabaseRefused;
use App\Database\EnvironmentMarker;
use App\Domain\Access\Accounts;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Access\Role;
use App\Domain\Access\StaffRoles;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Domain\Commands\RecoveryEpoch;
use App\Domain\Configuration\BusinessProfiles;
use App\Domain\Configuration\ProfileFields;
use App\Domain\Inventory\OnboardingState;
use App\Models\User;
use App\Support\Time\Clock;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * The fixture builder of the synthetic shop "Toko Contoh" (KEY_SCREENS
 * section 5; EXECUTION_CONTEXT section 10; TASK-004). It runs only where the
 * database's marker and the configured environment name are both test or
 * local (ENVIRONMENTS ENV-04), never on any other: it lives in the test
 * harness, which the release build does not contain.
 *
 * Its people and shop are those of the previews, all invented. Fixtures are
 * created through the Actions and services, never by inserting rows
 * (TEST_STRATEGY section 3; EXC-45), so each Task that delivers an Action
 * extends this builder with the records it creates. Since TASK-011 the people's
 * accounts come from OP-01 (Hendra, the Owner) and RC-35 (the staff, created by
 * the Owner), and a password is set the one way a password is set, through a
 * one-time link issued by the broker and consumed by ConsumeLink. A password
 * is generated at run time and never committed (EXECUTION_CONTEXT EXC-63).
 */
final class TokoContoh
{
    public const string SHOP = 'Toko Contoh';

    /**
     * The people of KEY_SCREENS section 5 with their roles (D-032).
     */
    public const array PEOPLE = [
        'Hendra' => ['SUPER_ADMIN'],
        'Agus' => ['OPERATIONS_ADMIN'],
        'Rina' => ['CASHIER'],
        'Dewi' => ['CASHIER', 'OPERATIONS_ADMIN'],
    ];

    /**
     * The day around which the previews' numbers and dates lie.
     */
    public const string AROUND = '2026-10-17';

    /**
     * Builds the shop on the database of this connection, after the marker
     * check, and returns the marker it was built on.
     *
     * @throws DatabaseRefused on any database whose marker is not test or local
     */
    public static function build(ConnectionInterface $connection): string
    {
        return EnvironmentMarker::require($connection, ['test', 'local']);
    }

    /**
     * The email of a person, under the reserved domain `.test`.
     */
    public static function email(string $person): string
    {
        self::roles($person);

        return strtolower($person).'@contoh.test';
    }

    /**
     * The person's account, created if it does not exist yet, ACTIVE, with a
     * new password that meets AICWDF-COMPAT-8, set through a one-time link;
     * every sign-in session of the account is revoked by the consumption.
     * Runs on the application's default connection, after the marker check.
     *
     * @return array{id: int, email: string, password: string}
     *
     * @throws DatabaseRefused
     */
    public static function person(string $person): array
    {
        self::build(DB::connection());

        $email = self::email($person);

        // The installation first: its Owner, Hendra, comes from OP-01's Action.
        self::shop();

        $account = app(Accounts::class)->findByEmail($email);

        if ($account !== null && ! $account->isActive()) {
            throw new LogicException(sprintf('The fixture account of %s is not ACTIVE.', $person));
        }

        $id = $account === null ? self::createStaff($person, $email, StaffRoles::namesOf(self::roles($person))) : $account->id;
        $password = self::newPassword();
        app(ConsumeLink::class)($id, self::link($person)['token'], $password);

        return ['id' => $id, 'email' => $email, 'password' => $password];
    }

    /**
     * The installation of Toko Contoh (TASK-010; EXECUTION_CONTEXT EXC-45):
     * the recovery epoch, the onboarding state NOT_STARTED and the first
     * BusinessProfile revision "Toko Contoh" with its pointer, whose actor is
     * the Owner Hendra. On a database without any account, epoch or
     * onboarding state it is OP-01's own Action that installs it, with Hendra
     * as the Owner, as the operator would; where the fixtures of earlier
     * Tasks already created accounts or the epoch, the same services write
     * only what is missing. Returns the internal key of the revision in force.
     *
     * @throws DatabaseRefused
     */
    public static function shop(): int
    {
        self::build(DB::connection());

        $profiles = app(BusinessProfiles::class);
        $current = $profiles->current();

        if ($current !== null) {
            return $current->id;
        }

        if (! DB::table('users')->exists() && app(RecoveryEpoch::class)->current() === null && app(OnboardingState::class)->current() === null) {
            return app(BootstrapInstallation::class)(new BootstrapInput('Hendra', self::email('Hendra'), self::SHOP, 'Instalasi Toko Contoh untuk pengujian.'))->revisionId;
        }

        return DB::transaction(static function () use ($profiles): int {
            $accounts = app(Accounts::class);
            $owner = $accounts->findByEmail(self::email('Hendra')) ?? $accounts->create('Hendra', self::email('Hendra'), self::roles('Hendra'));
            $epochs = app(RecoveryEpoch::class);
            $onboarding = app(OnboardingState::class);

            if ($epochs->current() === null) {
                $epochs->issue();
            }

            if ($onboarding->current() === null) {
                $onboarding->insertInitial();
            }

            return $profiles->insertFirst(new ProfileFields(self::SHOP), $owner->id, $owner->display_name);
        });
    }

    /**
     * The installation's recovery epoch as pages receive it (TASK-008),
     * issued through the Commands module's service if absent; the bootstrap
     * of TASK-010 issues it, and shop() installs it with the rest.
     *
     * @throws DatabaseRefused
     */
    public static function recoveryEpoch(): string
    {
        self::build(DB::connection());
        self::shop();
        $epochs = app(RecoveryEpoch::class);

        return $epochs->current() ?? DB::transaction(static fn (): string => $epochs->issue());
    }

    /**
     * A new one-time link for the person's existing account, replacing any
     * earlier one: the account's internal identity and the token.
     *
     * @return array{user: int, token: string}
     *
     * @throws DatabaseRefused
     */
    public static function link(string $person): array
    {
        self::build(DB::connection());

        $email = self::email($person);

        return DB::transaction(static function () use ($email, $person): array {
            $found = app(Accounts::class)->findByEmail($email);
            $account = $found === null ? null : app(Accounts::class)->lockForUpdate($found->id);

            if (! $account instanceof User) {
                throw new LogicException(sprintf('The fixture account of %s does not exist.', $person));
            }

            return ['user' => $account->id, 'token' => app(OneTimeLinks::class)->issue($account)];
        });
    }

    /**
     * Another staff account of the shop, beside the people of KEY_SCREENS section
     * 5, for a test that changes it (TASK-011): created by RC-35's Action, with
     * these roles and, unless asked not to, a password set through a link.
     *
     * @param  list<string>  $roles  the names of RC-35's payload
     * @return array{id: int, email: string, password: string|null}
     *
     * @throws DatabaseRefused
     */
    public static function staff(string $name, array $roles = ['cashier'], bool $withPassword = true): array
    {
        self::build(DB::connection());
        self::shop();

        $email = strtolower($name).'@contoh.test';
        $id = self::createStaff($name, $email, $roles);

        if (! $withPassword) {
            return ['id' => $id, 'email' => $email, 'password' => null];
        }

        $password = self::newPassword();
        app(ConsumeLink::class)($id, self::tokenFor($id), $password);

        return ['id' => $id, 'email' => $email, 'password' => $password];
    }

    /**
     * A new one-time link for an existing account, replacing any earlier one,
     * issued by the broker under the account's lock: its token.
     *
     * @throws DatabaseRefused
     */
    public static function tokenFor(int $id): string
    {
        self::build(DB::connection());

        return DB::transaction(static function () use ($id): string {
            $account = app(Accounts::class)->lockForUpdate($id);

            return $account instanceof User ? app(OneTimeLinks::class)->issue($account) : throw new LogicException('The fixture account exists.');
        });
    }

    /**
     * A random password of 24 characters that meets AICWDF-COMPAT-8: an
     * uppercase and a lowercase letter, a number and a symbol, then random
     * hexadecimal digits.
     */
    public static function newPassword(): string
    {
        return 'Kc7#'.bin2hex(random_bytes(10));
    }

    /**
     * The staff account of a person, created by the Owner through RC-35's
     * Action (EXECUTION_CONTEXT EXC-45), as the page would, without its link.
     *
     * @param  list<string>  $roles  the names of RC-35's payload
     */
    private static function createStaff(string $person, string $email, array $roles): int
    {
        $owner = User::query()->where('role_super_admin', true)->where('status', 'ACTIVE')->firstOrFail();
        $envelope = new Envelope(
            CreateStaffAccount::OPERATION,
            Operations::VERSION,
            (string) Str::uuid(),
            (string) Str::uuid(),
            self::recoveryEpoch(),
            [],
            ['displayName' => $person, 'email' => $email, 'roles' => $roles],
        );
        $outcome = app(CreateStaffAccount::class)->execute(new CommandContext($owner->id, $envelope, app(Clock::class)->now()));
        $id = $outcome->result['id'] ?? null;

        return is_int($id) ? $id : throw new LogicException(sprintf('The fixture account of %s was not created.', $person));
    }

    /**
     * @return non-empty-list<Role>
     */
    private static function roles(string $person): array
    {
        if (! array_key_exists($person, self::PEOPLE)) {
            throw new InvalidArgumentException(sprintf('%s is not a person of Toko Contoh (KEY_SCREENS section 5).', $person));
        }

        return array_map(static fn (string $role): Role => Role::from($role), self::PEOPLE[$person]);
    }
}
