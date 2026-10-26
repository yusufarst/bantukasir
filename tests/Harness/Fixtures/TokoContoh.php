<?php

namespace Tests\Harness\Fixtures;

use App\Actions\Access\ConsumeLink;
use App\Database\DatabaseRefused;
use App\Database\EnvironmentMarker;
use App\Domain\Access\Accounts;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Access\Role;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
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
 * extends this builder with the records it creates. TASK-006 adds the
 * people's accounts: until the bootstrap (TASK-010) and user management
 * (TASK-011) exist, an account is created through the Access module's account
 * service, and its password is set the one way a password is set, through a
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

        $roles = self::roles($person);
        $email = self::email($person);
        $accounts = app(Accounts::class);

        $id = DB::transaction(static function () use ($accounts, $person, $email, $roles): int {
            $account = $accounts->findByEmail($email) ?? $accounts->create($person, $email, $roles);

            if (! $account->isActive()) {
                throw new LogicException(sprintf('The fixture account of %s is not ACTIVE.', $person));
            }

            return $account->id;
        });

        $password = self::newPassword();
        app(ConsumeLink::class)($id, self::link($person)['token'], $password);

        return ['id' => $id, 'email' => $email, 'password' => $password];
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
     * A random password of 24 characters that meets AICWDF-COMPAT-8: an
     * uppercase and a lowercase letter, a number and a symbol, then random
     * hexadecimal digits.
     */
    public static function newPassword(): string
    {
        return 'Kc7#'.bin2hex(random_bytes(10));
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
