<?php

namespace Tests\Unit\Operations\Backup;

use App\Domain\Operations\Backup\BackupReason;
use App\Domain\Operations\Backup\BackupRefused;
use App\Domain\Operations\Backup\BackupSettings;
use App\Domain\Operations\Backup\SigV4;
use App\Domain\Operations\Backup\TierPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The rules of a run that need no server (BACKUP_RECOVERY sections 3, 4 and 6;
 * THR-10; D-112, D-113; L1): the tiers by the Jakarta clock, the signature, the
 * retention of each environment, and the static checks that keep the backup's code
 * off libcurl and libxml2 and its programs on argument lists.
 */
final class BackupRulesTest extends TestCase
{
    /**
     * @return array<string, array{BackupReason, string, list<string>}>
     */
    public static function tiers(): array
    {
        return [
            'a working day at 00.00' => [BackupReason::SCHEDULED, '2026-11-03 00:00:05', ['6h']],
            'a Sunday at 00.00' => [BackupReason::SCHEDULED, '2026-11-08 00:00:05', ['6h', 'weekly']],
            'a Sunday at 06.00' => [BackupReason::SCHEDULED, '2026-11-08 06:00:05', ['6h']],
            'the 1st at 00.00' => [BackupReason::SCHEDULED, '2026-12-01 00:00:05', ['6h', 'monthly']],
            'the 1st at 12.00' => [BackupReason::SCHEDULED, '2026-12-01 12:00:05', ['6h']],
            'a Sunday the 1st at 00.00' => [BackupReason::SCHEDULED, '2026-11-01 00:00:05', ['6h', 'weekly', 'monthly']],
            'on demand on a Sunday the 1st' => [BackupReason::ON_DEMAND, '2026-11-01 00:00:05', ['6h']],
            'before a release on a Sunday' => [BackupReason::PRE_RELEASE, '2026-11-08 00:00:05', ['6h']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('tiers')]
    public function test_the_tiers_follow_the_jakarta_clock(BackupReason $reason, string $jakarta, array $expected): void
    {
        // The application's clock is UTC: the same moment, seven hours earlier.
        $this->assertSame($expected, TierPlan::for($reason, CarbonImmutable::parse($jakarta, 'Asia/Jakarta')->utc()));
    }

    public function test_the_signature_is_the_hmac_chain_of_the_canonical_request(): void
    {
        $key = bin2hex(random_bytes(16));
        $payload = hash('sha256', 'body');
        $date = '20261031T000000Z';

        $headers = SigV4::sign('PUT', 'store.test:9000', '/bucket/files/a.age', ['b' => '2', 'a' => 'x y'], ['X-Amz-Meta-Bk-Recipient' => ' age1 ', 'content-md5' => 'm'], $payload, 'us-east-1', 'key-id', $key, $date);

        $canonical = implode("\n", [
            'PUT',
            '/bucket/files/a.age',
            'a=x%20y&b=2',
            "content-md5:m\nhost:store.test:9000\nx-amz-content-sha256:{$payload}\nx-amz-date:{$date}\nx-amz-meta-bk-recipient:age1\n",
            'content-md5;host;x-amz-content-sha256;x-amz-date;x-amz-meta-bk-recipient',
            $payload,
        ]);
        $toSign = implode("\n", ['AWS4-HMAC-SHA256', $date, '20261031/us-east-1/s3/aws4_request', hash('sha256', $canonical)]);
        $signing = 'AWS4'.$key;

        foreach (['20261031', 'us-east-1', 's3', 'aws4_request'] as $part) {
            $signing = hash_hmac('sha256', $part, $signing, true);
        }

        $this->assertSame(
            'AWS4-HMAC-SHA256 Credential=key-id/20261031/us-east-1/s3/aws4_request, SignedHeaders=content-md5;host;x-amz-content-sha256;x-amz-date;x-amz-meta-bk-recipient, Signature='.hash_hmac('sha256', $toSign, $signing),
            $headers['authorization'],
        );
        $this->assertArrayNotHasKey('host', $headers);
        $this->assertStringNotContainsString($key, implode(' ', $headers));
    }

    public function test_the_settings_read_the_region_and_the_retention_of_each_environment(): void
    {
        config([
            'app.environment_name' => 'production',
            'backup.store.endpoint' => 'https://s3.eu-central-003.store.test/',
            'backup.store.region' => null,
            'backup.store.bucket' => 'bucket',
            'backup.store.key_id' => 'key-id',
            'backup.store.key' => 'key',
            'backup.recipient' => 'age1'.str_repeat('q', 58),
        ]);

        $settings = BackupSettings::fromConfig();
        $this->assertSame('https://s3.eu-central-003.store.test', $settings->endpoint);
        $this->assertSame('eu-central-003', $settings->region);
        $this->assertSame([7, 35, 93], [$settings->retentionOf('6h'), $settings->retentionOf('weekly'), $settings->retentionOf('monthly')]);

        config(['backup.retention_profile' => 'reduced', 'backup.store.region' => 'named-region']);
        $settings = BackupSettings::fromConfig();
        $this->assertSame('named-region', $settings->region);
        $this->assertSame([3, 21, 62], [$settings->retentionOf('6h'), $settings->retentionOf('weekly'), $settings->retentionOf('monthly')]);

        // Staging has its own, whatever the profile says (OPEN_ITEMS T-22).
        config(['app.environment_name' => 'staging', 'backup.store.endpoint' => 'https://store.test:9000', 'backup.store.region' => '']);
        $settings = BackupSettings::fromConfig();
        $this->assertSame('us-east-1', $settings->region);
        $this->assertSame([3, 14, 31], [$settings->retentionOf('6h'), $settings->retentionOf('weekly'), $settings->retentionOf('monthly')]);
        $this->assertSame(1_000_000_000, $settings->usageThresholdBytes);

        config(['app.environment_name' => 'production']);
        $this->assertSame(6_000_000_000, BackupSettings::fromConfig()->usageThresholdBytes);

        // Staging's values are staging's.
        config(['backup.retention_profile' => 'staging']);

        try {
            BackupSettings::fromConfig();
            $this->fail('Staging\'s retention was accepted in production.');
        } catch (BackupRefused) {
            config(['backup.retention_profile' => 'full']);
        }

        config(['backup.recipient' => '--help']);
        $this->expectException(BackupRefused::class);
        BackupSettings::fromConfig();
    }

    public function test_the_server_holds_no_recovery_key_setting(): void
    {
        $flattened = (string) json_encode(config('backup'));

        $this->assertStringContainsString('recipient', $flattened);
        $this->assertDoesNotMatchRegularExpression('/identity|private|secret_key|AGE-SECRET/i', $flattened);
    }

    public function test_the_outbound_client_is_php_s_wrapper_and_no_code_reaches_libcurl_or_an_xml_parser(): void
    {
        $this->assertStringContainsString("'handler' => HandlerStack::create(new StreamHandler)", (string) file_get_contents(app_path('Domain/Operations/OutboundHttp.php')));

        // No outbound request of the application reaches libcurl (OPEN_ITEMS T-38 (a)).
        foreach (File::allFiles(app_path()) as $file) {
            $path = $file->getPathname();
            $source = (string) file_get_contents($path);
            $source = str_ends_with($path, 'OutboundHttp.php') ? str_replace('new Client([', '', $source) : $source;

            $this->assertDoesNotMatchRegularExpression('/\bcurl_[a-z]|CurlHandler|CurlMultiHandler|Utils::chooseHandler|new Client\(|\bHttp::|Facades\\\\Http\b/', $source, $path);
        }

        // Nothing the store answers reaches libxml2 (OPEN_ITEMS T-38 (c)).
        foreach ($this->sources() as $path => $source) {
            $this->assertDoesNotMatchRegularExpression('/simplexml_|SimpleXMLElement|DOMDocument|XMLReader|xml_parser_create/', $source, $path);
        }
    }

    public function test_every_program_starts_from_an_argument_list_in_one_class(): void
    {
        foreach ($this->sources() as $path => $source) {
            $this->assertDoesNotMatchRegularExpression('/fromShellCommandline|proc_open|Process::run\(/', $source, $path);

            if (! str_ends_with($path, 'BackupTools.php')) {
                $this->assertStringNotContainsString('Facades\Process', $source, $path);
            }
        }

        $tools = (string) file_get_contents(app_path('Domain/Operations/Backup/BackupTools.php'));

        $this->assertSame(1, preg_match_all('/->run\(/', str_replace('$this->run(', '', $tools)));
        $this->assertStringContainsString('$pending->run($command)', $tools);
        $this->assertStringNotContainsString('storageKey', $tools);
    }

    /**
     * @return array<string, string>
     */
    private function sources(): array
    {
        $sources = [];

        foreach ([...(array) glob(app_path('Domain/Operations/*.php')), ...(array) glob(app_path('Domain/Operations/Backup/*.php'))] as $path) {
            $path = (string) $path;
            $sources[$path] = (string) file_get_contents($path);
        }

        $this->assertArrayHasKey(app_path('Domain/Operations/Backup/ObjectStore.php'), $sources);

        return $sources;
    }
}
