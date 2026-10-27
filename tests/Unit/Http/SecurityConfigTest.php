<?php

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The settings of the security baseline as the environment gives them
 * (AC-007-07; SECURITY_MODEL SEC-CH-10, SEC-HDR-02, SEC-HDR-05; TECHNICAL_RULES
 * TR-ERR-02; ENVIRONMENTS sections 10 and 11; TASK-007): debug output only in
 * local development, whatever APP_DEBUG says elsewhere; the trusted hosts the
 * listed names, or else the host of the application's address; the trusted
 * proxies only those listed; HSTS off until the operator turns it on. Each
 * case reads the configuration file under the environment variables it sets.
 */
final class SecurityConfigTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $saved = [];

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            if ($value === null) {
                unset($_SERVER[$name], $_ENV[$name]);
                putenv($name);
            } else {
                $_SERVER[$name] = $_ENV[$name] = $value;
                putenv($name.'='.(is_scalar($value) ? (string) $value : ''));
            }
        }

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function debugCases(): array
    {
        return [
            'local with debug' => ['local', 'true', true],
            'local without debug' => ['local', 'false', false],
            'testing with debug' => ['testing', 'true', false],
            'staging with debug' => ['staging', 'true', false],
            'production with debug' => ['production', 'true', false],
            'an empty environment with debug' => ['', 'true', false],
        ];
    }

    #[DataProvider('debugCases')]
    public function test_debug_output_is_on_only_in_local_development(string $environment, string $debug, bool $on): void
    {
        $config = $this->read('app', ['APP_ENV' => $environment, 'APP_DEBUG' => $debug]);

        $this->assertSame($on, $config['debug']);
    }

    public function test_the_trusted_hosts_are_the_listed_names_or_the_host_of_the_address(): void
    {
        $this->assertSame(['toko.test'], $this->read('security', ['APP_URL' => 'https://toko.test', 'APP_TRUSTED_HOSTS' => ''])['trusted_hosts']);
        $this->assertSame(['toko.test', 'staging.toko.test'], $this->read('security', ['APP_URL' => 'https://toko.test', 'APP_TRUSTED_HOSTS' => ' toko.test, staging.toko.test ,'])['trusted_hosts']);
        $this->assertSame([], $this->read('security', ['APP_URL' => '', 'APP_TRUSTED_HOSTS' => ''])['trusted_hosts']);
    }

    public function test_no_proxy_is_trusted_and_hsts_is_off_unless_the_environment_says_so(): void
    {
        $default = $this->read('security', ['APP_TRUSTED_PROXIES' => '', 'APP_HSTS' => '', 'APP_HSTS_SUBDOMAINS' => '']);

        $this->assertSame([], $default['trusted_proxies']);
        $this->assertSame(['enabled' => false, 'subdomains' => false], $default['hsts']);

        $set = $this->read('security', ['APP_TRUSTED_PROXIES' => '192.0.2.2,198.51.100.0/24', 'APP_HSTS' => 'true', 'APP_HSTS_SUBDOMAINS' => 'false']);
        $this->assertSame(['192.0.2.2', '198.51.100.0/24'], $set['trusted_proxies']);
        $this->assertSame(['enabled' => true, 'subdomains' => false], $set['hsts']);
    }

    /**
     * @param  array<string, string>  $variables
     * @return array<string, mixed>
     */
    private function read(string $file, array $variables): array
    {
        foreach ($variables as $name => $value) {
            if (! array_key_exists($name, $this->saved)) {
                $this->saved[$name] = $_SERVER[$name] ?? null;
            }

            $_SERVER[$name] = $_ENV[$name] = $value;
            putenv($name.'='.$value);
        }

        $config = require base_path('config/'.$file.'.php');
        $this->assertIsArray($config);

        /** @var array<string, mixed> $config */
        return $config;
    }
}
