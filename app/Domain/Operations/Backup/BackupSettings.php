<?php

namespace App\Domain\Operations\Backup;

use SensitiveParameter;

/**
 * The settings of one run, read and checked from config/backup.php before anything
 * starts. Their form is checked here too, because they become arguments of a
 * program or part of a request (THR-10): a value that fails refuses the run. A
 * refusal says only the word "settings", never a value.
 */
final readonly class BackupSettings
{
    private const string ENDPOINT = '#\Ahttps?://[A-Za-z0-9.-]+(:\d+)?/?\z#';

    private const string BUCKET = '/\A[A-Za-z0-9._-]{3,63}\z/';

    private const string RECIPIENT = '/\Aage1[02-9ac-hj-np-z]{58}\z/';

    private const string DEFAULT_REGION = 'us-east-1';

    /**
     * @param  array{'6h': int, weekly: int, monthly: int}  $retentionDays
     * @param  array{version: string|null, commit: string|null, imageDigest: string|null}  $release
     */
    public function __construct(
        public string $endpoint,
        public string $region,
        public string $bucket,
        public string $keyId,
        #[SensitiveParameter]
        public string $key,
        public string $recipient,
        public string $temporaryPath,
        public int $timeLimitSeconds,
        public int $partBytes,
        public string $pgDump,
        public string $pgRestore,
        public string $age,
        public array $retentionDays,
        public int $usageThresholdBytes,
        public array $release,
    ) {}

    /**
     * @throws BackupRefused
     */
    public static function fromConfig(): self
    {
        $endpoint = rtrim(self::text('backup.store.endpoint'), '/');
        $region = config('backup.store.region');
        $bucket = self::text('backup.store.bucket');
        $recipient = self::text('backup.recipient');

        if (preg_match(self::ENDPOINT, $endpoint) !== 1 || preg_match(self::BUCKET, $bucket) !== 1 || preg_match(self::RECIPIENT, $recipient) !== 1) {
            throw new BackupRefused('settings');
        }

        return new self(
            $endpoint,
            is_string($region) && $region !== '' ? $region : self::regionOf($endpoint),
            $bucket,
            self::text('backup.store.key_id'),
            self::text('backup.store.key'),
            $recipient,
            self::text('backup.temporary_path'),
            self::number('backup.time_limit_seconds'),
            self::number('backup.part_bytes'),
            self::text('backup.tools.pg_dump'),
            self::text('backup.tools.pg_restore'),
            self::text('backup.tools.age'),
            self::retention(),
            self::number('backup.usage_threshold_bytes'),
            [
                'version' => self::optional('backup.release.version'),
                'commit' => self::optional('backup.release.commit'),
                'imageDigest' => self::optional('backup.release.image_digest'),
            ],
        );
    }

    /**
     * The days of a tier's Object Lock.
     */
    public function retentionOf(string $tier): int
    {
        return match ($tier) {
            TierPlan::SIX_HOURS => $this->retentionDays['6h'],
            TierPlan::WEEKLY => $this->retentionDays['weekly'],
            default => $this->retentionDays['monthly'],
        };
    }

    /**
     * Staging's tiers are its own (BACKUP_RECOVERY section 6, OPEN_ITEMS T-22);
     * elsewhere the profile that the operator set.
     *
     * @return array{'6h': int, weekly: int, monthly: int}
     *
     * @throws BackupRefused
     */
    private static function retention(): array
    {
        $profile = config('app.environment_name') === 'staging' ? 'staging' : self::text('backup.retention_profile');

        if (! in_array($profile, ['full', 'reduced', 'staging'], true)) {
            throw new BackupRefused('settings');
        }

        return [
            '6h' => self::number('backup.retention.'.$profile.'.6h'),
            'weekly' => self::number('backup.retention.'.$profile.'.weekly'),
            'monthly' => self::number('backup.retention.'.$profile.'.monthly'),
        ];
    }

    /**
     * The store's region: the second label of an endpoint s3.<region>.<host>, as
     * the candidate store of ADR-010 names it, and the usual default otherwise.
     */
    private static function regionOf(string $endpoint): string
    {
        return preg_match('#\Ahttps?://s3\.([a-z0-9-]+)\.#', $endpoint, $match) === 1 ? $match[1] : self::DEFAULT_REGION;
    }

    /**
     * @throws BackupRefused
     */
    private static function text(string $name): string
    {
        $value = config($name);

        if (! is_string($value) || $value === '') {
            throw new BackupRefused('settings');
        }

        return $value;
    }

    private static function optional(string $name): ?string
    {
        $value = config($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @throws BackupRefused
     */
    private static function number(string $name): int
    {
        $value = config($name);

        if (! is_int($value) || $value < 0) {
            throw new BackupRefused('settings');
        }

        return $value;
    }
}
