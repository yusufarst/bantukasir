<?php

namespace App\Domain\Operations;

/**
 * The settings each process role needs at start-up (SECURITY_MODEL
 * SEC-SECRET-03; ENVIRONMENTS section 11; ADR-010 item 6; TASK-012 scope item
 * 3): a required setting that is missing or holds a default, a setting that
 * must not hold another value, and a credential of another role each refuse
 * the start. A problem is named by the setting and its kind, never by its
 * value.
 *
 * A default is a placeholder, a fallback of config/ or a value of the local
 * environment, which only the release's start judges (docker/app/entrypoint.sh).
 */
final class RequiredSettings
{
    public const string WEB = 'web';

    public const string BACKUP = 'backup';

    public const string MISSING = 'missing';

    public const string DEFAULT = 'default';

    public const string INVALID = 'invalid';

    public const string FORBIDDEN = 'forbidden';

    private const string HTTPS_ADDRESS = '#\Ahttps://[^\s/:@?\#]+(:\d+)?(/\S*)?\z#i';

    /** Addresses or CIDR ranges, none of them every address (SEC-HDR-05, THR-16). */
    private const string PROXY_HOPS = '#\A[0-9A-Fa-f:.]+(/[1-9]\d*)?(\s*,\s*[0-9A-Fa-f:.]+(/[1-9]\d*)?)*\z#';

    /** The name of each required setting per role; ENVIRONMENTS section 11. */
    private const array REQUIRED = [
        self::WEB => [
            'APP_ENV',
            'APP_ENVIRONMENT_NAME',
            'APP_URL',
            'APP_TRUSTED_PROXIES',
            'APP_KEY',
            'DB_HOST',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'HEARTBEAT_WEB_URL',
            'HEARTBEAT_RECONCILIATION_URL',
        ],
        self::BACKUP => [
            'APP_ENV',
            'APP_ENVIRONMENT_NAME',
            'DB_HOST',
            'DB_DATABASE',
            'BACKUP_DB_USERNAME',
            'BACKUP_DB_PASSWORD',
            'BACKUP_STORE_ENDPOINT',
            'BACKUP_STORE_BUCKET',
            'BACKUP_STORE_KEY_ID',
            'BACKUP_STORE_KEY',
            'BACKUP_RECIPIENT',
            'HEARTBEAT_BACKUP_URL',
        ],
    ];

    /** Credentials that a role never holds (ADR-010 item 6; ADR-011 item 7). */
    private const array FORBIDDEN_SETTINGS = [
        self::WEB => [
            'DB_MIGRATION_USERNAME',
            'DB_MIGRATION_PASSWORD',
            'BACKUP_DB_USERNAME',
            'BACKUP_DB_PASSWORD',
            'BACKUP_STORE_KEY_ID',
            'BACKUP_STORE_KEY',
            'BACKUP_RECIPIENT',
            'HEARTBEAT_BACKUP_URL',
        ],
        self::BACKUP => [
            'DB_MIGRATION_USERNAME',
            'DB_MIGRATION_PASSWORD',
            'APP_KEY',
            'APP_PREVIOUS_KEYS',
            'DB_USERNAME',
            'DB_PASSWORD',
            'HEARTBEAT_WEB_URL',
            'HEARTBEAT_RECONCILIATION_URL',
        ],
    ];

    /** Optional settings that, when set, must hold one of these values. */
    private const array ALLOWED = [
        self::WEB => [
            'APP_DEBUG' => ['false', '0', 'off', 'no'],
            'DB_CONNECTION' => ['pgsql'],
            'SESSION_DRIVER' => ['database'],
            'CACHE_STORE' => ['database'],
            'SESSION_SECURE_COOKIE' => ['true', '1', 'on', 'yes'],
        ],
        self::BACKUP => [
            'APP_DEBUG' => ['false', '0', 'off', 'no'],
        ],
    ];

    /** The defaults of every setting, in lower case. */
    private const array PLACEHOLDERS = [
        'null',
        'none',
        'undefined',
        'changeme',
        'change-me',
        'change_me',
        'secret',
        'password',
        'example',
        'placeholder',
        'default',
        'todo',
        'xxx',
        'test',
        'laravel',
        'homestead',
        'root',
        'postgres',
        'admin',
    ];

    /** The defaults of one setting, in lower case: config/ and compose.yaml. */
    private const array DEFAULTS = [
        'APP_ENV' => ['local', 'testing', 'development', 'dev'],
        'APP_URL' => ['https://localhost', 'https://127.0.0.1'],
        'DB_HOST' => ['127.0.0.1', 'localhost', '::1', 'db'],
        'DB_DATABASE' => ['bantukasir', 'bantukasir_test'],
        'DB_USERNAME' => ['bk_runtime'],
        'BACKUP_DB_USERNAME' => ['bk_backup'],
    ];

    /** What a value must look like, where its form says something. */
    private const array FORMS = [
        'APP_ENVIRONMENT_NAME' => '/\A(drill|staging|production)\z/',
        'APP_URL' => self::HTTPS_ADDRESS,
        'APP_TRUSTED_PROXIES' => self::PROXY_HOPS,
        'APP_KEY' => '#\Abase64:[A-Za-z0-9+/]{43}=\z#',
        'HEARTBEAT_WEB_URL' => self::HTTPS_ADDRESS,
        'HEARTBEAT_RECONCILIATION_URL' => self::HTTPS_ADDRESS,
        'HEARTBEAT_BACKUP_URL' => self::HTTPS_ADDRESS,
        'BACKUP_STORE_ENDPOINT' => self::HTTPS_ADDRESS,
        'BACKUP_RECIPIENT' => '/\Aage1[02-9ac-hj-np-z]{58}\z/',
    ];

    /**
     * The settings a role needs.
     *
     * @return list<string>
     */
    public static function required(string $role): array
    {
        return self::REQUIRED[$role] ?? [];
    }

    /**
     * The credentials a role never holds.
     *
     * @return list<string>
     */
    public static function forbidden(string $role): array
    {
        return self::FORBIDDEN_SETTINGS[$role] ?? [];
    }

    /**
     * The values that refuse a setting as a default, for the image checks.
     *
     * @return list<string>
     */
    public static function defaults(string $name): array
    {
        return self::DEFAULTS[$name] ?? [];
    }

    /**
     * What refuses the start with this environment: a kind per setting, empty
     * when the start may go on.
     *
     * @param  array<string, string>  $environment
     * @return array<string, string>
     */
    public static function problems(array $environment): array
    {
        $role = self::value($environment, 'APP_PROCESS_ROLE');

        if ($role === '') {
            return ['APP_PROCESS_ROLE' => self::MISSING];
        }

        if (! array_key_exists($role, self::REQUIRED)) {
            return ['APP_PROCESS_ROLE' => self::INVALID];
        }

        $problems = [];

        foreach (self::REQUIRED[$role] as $name) {
            $problem = self::judge($name, self::value($environment, $name));

            if ($problem !== null) {
                $problems[$name] = $problem;
            }
        }

        foreach (self::ALLOWED[$role] as $name => $allowed) {
            $value = strtolower(self::value($environment, $name));

            if ($value !== '' && ! in_array($value, $allowed, true)) {
                $problems[$name] = self::INVALID;
            }
        }

        foreach (self::FORBIDDEN_SETTINGS[$role] as $name) {
            if (self::value($environment, $name) !== '') {
                $problems[$name] = self::FORBIDDEN;
            }
        }

        return $problems;
    }

    private static function judge(string $name, string $value): ?string
    {
        if ($value === '') {
            return self::MISSING;
        }

        $lower = strtolower($value);

        if (in_array($lower, self::PLACEHOLDERS, true) || in_array($lower, self::DEFAULTS[$name] ?? [], true)) {
            return self::DEFAULT;
        }

        $form = self::FORMS[$name] ?? null;

        return $form !== null && preg_match($form, $value) !== 1 ? self::INVALID : null;
    }

    /**
     * @param  array<string, string>  $environment
     */
    private static function value(array $environment, string $name): string
    {
        return trim($environment[$name] ?? '');
    }
}
