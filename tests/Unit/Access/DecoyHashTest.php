<?php

namespace Tests\Unit\Access;

use App\Domain\Access\Accounts;
use App\Domain\Access\AccountStatus;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Tests\Harness\Access\HashingProbe;
use Tests\TestCase;

/**
 * The decoy hash (SEC-AUTH-08, TR-ERR-04; TASK-006 evidence section 13, the
 * Owner's change request on pull request #9). When no usable account matches
 * — an unknown email, an account that is not ACTIVE, an account without a
 * password — Accounts checks Accounts::DECOY_HASH in place of a stored hash:
 * one verification at the configured cost, the work of a wrong password, and
 * no hash made during the request. The decoy was made ahead of time by the
 * configured hasher, of a random text that was then discarded; when the
 * configured parameters change (TASK-048), the first test fails and gives a
 * replacement made the same way.
 */
final class DecoyHashTest extends TestCase
{
    public function test_the_decoy_is_an_argon2id_hash_with_the_configured_parameters(): void
    {
        $hasher = $this->app->make(Hasher::class);
        $made = $hasher->make(bin2hex(random_bytes(32)));

        $this->assertSame(
            password_get_info($made),
            password_get_info(Accounts::DECOY_HASH),
            'Accounts::DECOY_HASH must have the algorithm and parameters of the configured hasher. Replace it with this hash, made now by the configured hasher of a random text that was discarded: '.$made,
        );
        $this->assertSame('argon2id', password_get_info(Accounts::DECOY_HASH)['algoName']);
        $this->assertFalse($hasher->needsRehash(Accounts::DECOY_HASH));

        // password_get_info() reads only the prefix, and password_verify()
        // refuses a malformed salt or digest without doing the work: the decoy
        // has the whole shape of a hash the configured hasher makes, a salt of
        // 16 bytes and a digest of 32 (review finding R-25).
        $shape = '/^\$argon2id\$v=19\$m=\d+,t=\d+,p=\d+\$[A-Za-z0-9+\/]{22}\$[A-Za-z0-9+\/]{43}$/';
        $this->assertMatchesRegularExpression($shape, $made);
        $this->assertMatchesRegularExpression($shape, Accounts::DECOY_HASH);

        // The configured hasher, which verifies the algorithm first, checks it
        // as it checks a stored hash.
        $this->assertFalse($hasher->check(bin2hex(random_bytes(16)), Accounts::DECOY_HASH));
    }

    public function test_each_refused_case_checks_one_hash_at_a_stored_passwords_cost_and_makes_none(): void
    {
        $password = bin2hex(random_bytes(16));
        $stored = $this->app->make(Hasher::class)->make($password);
        $probe = HashingProbe::install($this->app);

        $cases = [
            'a wrong password' => [self::account(AccountStatus::Active, $stored), 'Salah#'.$password, $stored],
            'an unknown email' => [null, $password, Accounts::DECOY_HASH],
            'a deactivated account' => [self::account(AccountStatus::Deactivated, $stored), $password, Accounts::DECOY_HASH],
            'an anonymized account' => [self::account(AccountStatus::Anonymized, null), $password, Accounts::DECOY_HASH],
            'an account without a password' => [self::account(AccountStatus::Active, null), $password, Accounts::DECOY_HASH],
        ];

        foreach ($cases as $case => [$user, $typed, $checked]) {
            $probe->newRequest();

            $this->assertNull($this->app->make(Accounts::class)->verifyCredentials($user, $typed), $case);
            $this->assertSame(0, $probe->made, $case.': no hash is made during the request');
            $this->assertSame([$checked], $probe->checked, $case.': exactly one hash is checked');
            $this->assertSame(password_get_info($stored), password_get_info($checked), $case.': at the cost of a stored password');
        }

        // The account's own password signs in, with the same one check.
        $probe->newRequest();
        $active = self::account(AccountStatus::Active, $stored);
        $this->assertSame($active, $this->app->make(Accounts::class)->verifyCredentials($active, $password));
        $this->assertSame([0, [$stored]], [$probe->made, $probe->checked]);
    }

    public function test_a_password_check_of_an_account_without_a_password_checks_the_decoy_and_makes_no_hash(): void
    {
        $probe = HashingProbe::install($this->app);
        $probe->newRequest();

        $this->assertFalse($this->app->make(Accounts::class)->passwordMatches(self::account(AccountStatus::Active, null), bin2hex(random_bytes(16))));
        $this->assertSame([0, [Accounts::DECOY_HASH]], [$probe->made, $probe->checked]);
    }

    /** An account in memory: these checks read only its status and password. */
    private static function account(AccountStatus $status, ?string $password): User
    {
        $user = new User;
        $user->forceFill(['status' => $status->value, 'password' => $password]);

        return $user;
    }
}
