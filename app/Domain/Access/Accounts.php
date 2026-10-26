<?php

namespace App\Domain\Access;

use App\Models\User;
use App\Support\Time\Clock;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The account service of the Access module (STACK_AND_MODULES section 2;
 * SCHEMA section 5.1): it reads and writes `users` and the sign-in sessions
 * of `sessions`, and nothing else does. It opens no transaction of its own:
 * the Action that calls it owns the transaction and the locks (ARCHITECTURE
 * section 3, TR-TX-01). Until the bootstrap (TASK-010) and user management
 * (TASK-011) exist, the test fixtures create accounts through create()
 * (EXECUTION_CONTEXT EXC-45; TASK-006 scope item 10).
 */
final class Accounts
{
    /**
     * The decoy hash (SEC-AUTH-08, TR-ERR-04): an Argon2id hash with the
     * parameters of config/hashing.php, made ahead of time by the configured
     * hasher of a random text that was discarded, so that no password is
     * known to match it. A refused sign-in checks it in place of a stored
     * hash, and so does a password check of an account without a password:
     * one verification of the same cost as a wrong password's, and no hash
     * made during the request. A hash made at run time and kept in a static
     * property would be made on every refused sign-in, because PHP-FPM starts
     * each request with its static properties at their defaults (TASK-006
     * evidence section 13). The result of checking it is never used, so even
     * a text that matched it would sign nobody in. When the configured
     * parameters change (TASK-048), tests/Unit/Access/DecoyHashTest.php fails
     * and gives a replacement made the same way.
     */
    public const string DECOY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$dC5XdzFMQy5qd1hTcHBmWA$0C/ow8TxFL5zP7L3ldhru1TJBLw0x/Bbp9Tu3D0TCWs';

    public function __construct(
        private readonly Hasher $hasher,
        private readonly Clock $clock,
    ) {}

    /**
     * Creates an ACTIVE account without a usable password (SEC-ACC-02): its
     * first password is set through a one-time link. The display name and
     * the email are trimmed and validated by the caller (TR-VAL-05); the
     * database refuses a second account with the same email, without regard
     * to letter case (users_email_ci_uniq, SCH-14), and a SUPER_ADMIN account
     * with another role (users_super_admin_alone). Whether a SUPER_ADMIN
     * account may be created at all is the caller's rule (CAP-33, G-08).
     *
     * @param  non-empty-list<Role>  $roles
     */
    public function create(string $displayName, string $email, array $roles): User
    {
        self::requireTransaction();

        $now = $this->clock->now();
        $user = new User;

        $user->forceFill([
            'display_name' => $displayName,
            'email' => $email,
            'password' => null,
            'role_super_admin' => in_array(Role::SuperAdmin, $roles, true),
            'role_operations_admin' => in_array(Role::OperationsAdmin, $roles, true),
            'role_cashier' => in_array(Role::Cashier, $roles, true),
            'status' => AccountStatus::Active->value,
            'deactivated_at' => null,
            'anonymized_at' => null,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $user->timestamps = false;
        $user->save();

        return $user;
    }

    /**
     * The account whose email equals this one without regard to letter case,
     * compared by the database's lower() as its unique index is (SCH-14).
     */
    public function findByEmail(string $email): ?User
    {
        return User::query()->whereRaw('lower(email) = lower(?)', [$email])->first();
    }

    /**
     * The account of a sign-in: ACTIVE, with a usable password that matches
     * (SEC-AUTH-09). Every other case checks the decoy hash and answers null,
     * so that the answer and its time are the same for an unknown email, a
     * deactivated or anonymized account, an account without a password and a
     * wrong password (SEC-AUTH-08).
     */
    public function verifyCredentials(?User $user, #[\SensitiveParameter] string $password): ?User
    {
        $hash = $user?->password;

        if ($user === null || ! $user->isActive() || $hash === null) {
            $this->hasher->check($password, self::DECOY_HASH);

            return null;
        }

        return $this->hasher->check($password, $hash) ? $user : null;
    }

    /**
     * The account, locked for an update of its own row (TECHNICAL_RULES
     * section 7.1 step 2.2, TR-LOCK-05; SCHEMA section 7: FOR NO KEY UPDATE),
     * or null when none has this internal identity. Called inside the
     * caller's transaction.
     */
    public function lockForUpdate(int $id): ?User
    {
        self::requireTransaction();

        return User::query()->whereKey($id)->lock('for no key update')->first();
    }

    /**
     * Sets the account's password, hashed with Argon2id (ADR-004 item 7), on
     * the row the caller has locked. The password has passed AICWDF-COMPAT-8
     * (PasswordProfile). The version does not change: it counts changes of
     * the name, email, roles and status (SCHEMA section 5.1).
     */
    public function setPassword(User $locked, #[\SensitiveParameter] string $password): void
    {
        self::requireTransaction();

        $locked->forceFill([
            'password' => $this->hasher->make($password),
            'updated_at' => $this->clock->now(),
        ]);
        $locked->timestamps = false;
        $locked->save();
    }

    /**
     * Whether this plain password is the account's current password.
     */
    public function passwordMatches(User $user, #[\SensitiveParameter] string $password): bool
    {
        $hash = $user->password;

        if ($hash === null) {
            $this->hasher->check($password, self::DECOY_HASH);

            return false;
        }

        return $this->hasher->check($password, $hash);
    }

    /**
     * After a sign-in: a stored hash whose parameters are lower than the
     * configured ones is replaced by a new hash of the same password
     * (SEC-AUTH-06). The update applies only while the stored hash is still
     * the one that was checked, so that a password changed meanwhile is never
     * replaced by the old one.
     */
    public function rehashIfNeeded(User $user, #[\SensitiveParameter] string $password): void
    {
        $hash = $user->password;

        if ($hash === null || ! $this->hasher->needsRehash($hash)) {
            return;
        }

        User::query()
            ->whereKey($user->id)
            ->where('password', $hash)
            ->toBase()
            ->update(['password' => $this->hasher->make($password), 'updated_at' => $this->clock->now()]);
    }

    /**
     * Revokes the account's sign-in sessions — removes them from the session
     * store, so that no old permission survives (SECURITY_MODEL SEC-SESS-04)
     * — except the one named, and returns how many were removed. Called inside
     * the caller's transaction, after the account lock.
     */
    public function revokeSessions(int $userId, ?string $exceptSessionId = null): int
    {
        self::requireTransaction();

        $sessions = DB::table('sessions')->where('user_id', $userId);

        if ($exceptSessionId !== null) {
            $sessions->where('id', '<>', $exceptSessionId);
        }

        return $sessions->delete();
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The account is changed inside the transaction of the Action that changes it (TR-TX-01).');
        }
    }
}
