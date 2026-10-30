<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\RequiredSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The settings each process role needs at start-up (AC-012-03; SECURITY_MODEL
 * SEC-SECRET-03; ENVIRONMENTS section 11): both complete environments pass, each
 * required setting missing, empty or a default refuses, and a credential of
 * another role refuses. The image checks repeat this against the image itself.
 */
final class RequiredSettingsTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private static function web(): array
    {
        return [
            'APP_PROCESS_ROLE' => 'web',
            'APP_ENV' => 'production',
            'APP_ENVIRONMENT_NAME' => 'production',
            'APP_URL' => 'https://shop.test',
            'APP_TRUSTED_PROXIES' => '10.0.0.1, 192.0.2.0/24,2001:db8::/32',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'DB_HOST' => 'database',
            'DB_DATABASE' => 'shop',
            'DB_USERNAME' => 'shop_runtime',
            'DB_PASSWORD' => bin2hex(random_bytes(12)),
            'HEARTBEAT_WEB_URL' => 'https://heartbeat.test/'.bin2hex(random_bytes(6)),
            'HEARTBEAT_RECONCILIATION_URL' => 'https://heartbeat.test/'.bin2hex(random_bytes(6)),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function backup(): array
    {
        return [
            'APP_PROCESS_ROLE' => 'backup',
            'APP_ENV' => 'production',
            'APP_ENVIRONMENT_NAME' => 'production',
            'DB_HOST' => 'database',
            'DB_DATABASE' => 'shop',
            'BACKUP_DB_USERNAME' => 'shop_backup',
            'BACKUP_DB_PASSWORD' => bin2hex(random_bytes(12)),
            'BACKUP_STORE_ENDPOINT' => 'https://store.test',
            'BACKUP_STORE_BUCKET' => 'shop-backups',
            'BACKUP_STORE_KEY_ID' => bin2hex(random_bytes(6)),
            'BACKUP_STORE_KEY' => bin2hex(random_bytes(12)),
            'BACKUP_RECIPIENT' => 'age1'.str_repeat('q', 58),
            'HEARTBEAT_BACKUP_URL' => 'https://heartbeat.test/'.bin2hex(random_bytes(6)),
        ];
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function roles(): array
    {
        return ['web' => [self::web()], 'backup' => [self::backup()]];
    }

    /**
     * @param  array<string, string>  $environment
     */
    #[DataProvider('roles')]
    public function test_a_complete_environment_passes(array $environment): void
    {
        $this->assertSame([], RequiredSettings::problems($environment));
    }

    public function test_a_missing_or_an_unknown_role_refuses_whatever_else_is_set(): void
    {
        $environment = self::web();
        unset($environment['APP_PROCESS_ROLE']);

        $this->assertSame(['APP_PROCESS_ROLE' => RequiredSettings::MISSING], RequiredSettings::problems($environment));
        $this->assertSame(['APP_PROCESS_ROLE' => RequiredSettings::INVALID], RequiredSettings::problems(['APP_PROCESS_ROLE' => 'worker'] + $environment));
    }

    /**
     * @param  array<string, string>  $environment
     */
    #[DataProvider('roles')]
    public function test_each_required_setting_refuses_when_missing_empty_or_a_placeholder(array $environment): void
    {
        foreach (RequiredSettings::required($environment['APP_PROCESS_ROLE']) as $name) {
            $without = $environment;
            unset($without[$name]);
            $this->assertSame([$name => RequiredSettings::MISSING], RequiredSettings::problems($without), "{$name} missing");

            $this->assertSame([$name => RequiredSettings::MISSING], RequiredSettings::problems([$name => '  '] + $environment), "{$name} empty");

            $this->assertSame([$name => RequiredSettings::DEFAULT], RequiredSettings::problems([$name => 'ChangeMe'] + $environment), "{$name} a placeholder");
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function defaultsOfOneSetting(): array
    {
        return [
            'the local environment' => ['APP_ENV', 'local'],
            'the tests' => ['APP_ENV', 'testing'],
            'the local host' => ['DB_HOST', '127.0.0.1'],
            'the service of compose.yaml' => ['DB_HOST', 'db'],
            'the fallback of the database' => ['DB_DATABASE', 'bantukasir'],
            'the test database' => ['DB_DATABASE', 'bantukasir_test'],
            'the local runtime role' => ['DB_USERNAME', 'bk_runtime'],
            'the local address' => ['APP_URL', 'https://localhost'],
        ];
    }

    #[DataProvider('defaultsOfOneSetting')]
    public function test_a_default_of_one_setting_refuses(string $name, string $value): void
    {
        $this->assertSame([$name => RequiredSettings::DEFAULT], RequiredSettings::problems([$name => $value] + self::web()));
        $this->assertContains($value, RequiredSettings::defaults($name));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'a plain address' => ['APP_URL', 'http://shop.test'],
            'an environment name of the tests' => ['APP_ENVIRONMENT_NAME', 'test2'],
            'the local marker' => ['APP_ENVIRONMENT_NAME', 'local'],
            'every proxy' => ['APP_TRUSTED_PROXIES', '*'],
            'every IPv4 address' => ['APP_TRUSTED_PROXIES', '10.0.0.1,0.0.0.0/0'],
            'every IPv6 address' => ['APP_TRUSTED_PROXIES', '::/0'],
            'a short key' => ['APP_KEY', 'base64:c2hvcnQ='],
            'a plain ping address' => ['HEARTBEAT_WEB_URL', 'http://heartbeat.test/ping'],
            'debug on' => ['APP_DEBUG', 'true'],
            'another database driver' => ['DB_CONNECTION', 'sqlite'],
            'sessions in files' => ['SESSION_DRIVER', 'file'],
            'another cache store' => ['CACHE_STORE', 'file'],
            'a cookie without Secure' => ['SESSION_SECURE_COOKIE', 'false'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_a_value_that_breaks_a_rule_refuses(string $name, string $value): void
    {
        $this->assertSame([$name => RequiredSettings::INVALID], RequiredSettings::problems([$name => $value] + self::web()));
    }

    public function test_the_backup_recipient_must_be_a_recipient_of_age(): void
    {
        $this->assertSame(['BACKUP_RECIPIENT' => RequiredSettings::INVALID], RequiredSettings::problems(['BACKUP_RECIPIENT' => 'age1short'] + self::backup()));
    }

    /**
     * @param  array<string, string>  $environment
     */
    #[DataProvider('roles')]
    public function test_a_credential_of_another_role_refuses(array $environment): void
    {
        $forbidden = RequiredSettings::forbidden($environment['APP_PROCESS_ROLE']);

        $this->assertContains('DB_MIGRATION_PASSWORD', $forbidden);

        foreach ($forbidden as $name) {
            $this->assertSame([$name => RequiredSettings::FORBIDDEN], RequiredSettings::problems([$name => 'held-by-another-role'] + $environment), $name);
        }
    }

    public function test_the_roles_never_share_a_credential(): void
    {
        $this->assertSame([], array_values(array_intersect(RequiredSettings::required(RequiredSettings::WEB), RequiredSettings::forbidden(RequiredSettings::WEB))));
        $this->assertSame([], array_values(array_intersect(RequiredSettings::required(RequiredSettings::BACKUP), RequiredSettings::forbidden(RequiredSettings::BACKUP))));
        $this->assertContains('DB_PASSWORD', RequiredSettings::forbidden(RequiredSettings::BACKUP));
        $this->assertContains('BACKUP_DB_PASSWORD', RequiredSettings::forbidden(RequiredSettings::WEB));
    }

    public function test_a_problem_never_holds_a_value(): void
    {
        $secret = bin2hex(random_bytes(12));

        $problems = RequiredSettings::problems(['DB_PASSWORD' => 'password', 'BACKUP_STORE_KEY' => $secret, 'APP_DEBUG' => $secret] + self::web());

        $this->assertStringNotContainsString($secret, (string) json_encode($problems));
        $this->assertSame(['DB_PASSWORD' => RequiredSettings::DEFAULT, 'APP_DEBUG' => RequiredSettings::INVALID, 'BACKUP_STORE_KEY' => RequiredSettings::FORBIDDEN], $problems);
    }
}
