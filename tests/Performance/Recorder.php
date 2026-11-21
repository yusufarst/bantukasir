<?php

namespace Tests\Performance;

use Illuminate\Support\Facades\DB;

/**
 * The record of a run of the PERF01 scenarios (TECHNICAL_RULES section 13.2, "Each run records the
 * versions, configuration, indexes, host, dataset, run count, measured p95 and a reconciliation before
 * and after"; TASK-043 scope item 2, AC-043-02): for each budget of section 13.1 the statistic it names
 * as measured, with its samples and failures, beside the proposed budget, which a dry run never judges.
 *
 * A run outside the intended server is a dry run, and its record says so: PERF01 stays NOT_RUN until
 * TASK-048 runs it there (AC-043-03; TEST_STRATEGY TS-01, TS-09). The host is described by its kind,
 * its processors and memory, never by a name or an address (EXC-61, EXC-63).
 */
final class Recorder
{
    public const string LABEL = 'DRY RUN: not PERF01 evidence. PERF01 stays NOT_RUN until TASK-048 runs it on the intended server.';

    /**
     * @param  array<mixed>  $plan  the plan of the run (perf.php plan)
     * @param  array<mixed>  $summary  the summary that the load tool exported
     * @return array<string, mixed>
     */
    public static function record(array $plan, array $summary, string $reconciliationAfter, ?int $reconciliationAfterMs = null): array
    {
        $metrics = is_array($summary['metrics'] ?? null) ? $summary['metrics'] : [];
        $results = [];

        foreach (Budgets::METRICS as $metric => $budget) {
            $values = self::values($metrics, $metric);
            $failures = self::values($metrics, 'fail_'.substr($metric, 3));
            $observed = $values[$budget['statistic']] ?? null;
            $samples = $values['count'] ?? 0;
            $failed = $failures['count'] ?? 0;

            $results[$metric] = [
                'operation' => $budget['operation'],
                'statistic' => $budget['statistic'],
                'budgetMs' => $budget['milliseconds'],
                'observedMs' => is_int($observed) || is_float($observed) ? (int) round($observed) : null,
                'samples' => is_int($samples) || is_float($samples) ? (int) $samples : 0,
                'failures' => is_int($failed) || is_float($failed) ? (int) $failed : 0,
            ];
        }

        return [
            'label' => self::LABEL,
            'perf01' => 'NOT_RUN',
            'run' => $plan['run'] ?? null,
            'runs' => $plan['runs'] ?? null,
            'startedAt' => $plan['startedAt'] ?? null,
            'finishedAt' => now('Asia/Jakarta')->toIso8601String(),
            'revision' => self::env('PERF_REVISION'),
            'versions' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'postgresql' => DB::scalar('select pg_catalog.current_setting(\'server_version\')'),
                'loadTool' => self::env('PERF_LOAD_TOOL'),
            ],
            'configuration' => [
                'environment' => app()->environment(),
                'fpmMaxChildren' => self::fpmMaxChildren(),
                'opcache' => ini_get('opcache.enable'),
                'databaseLimits' => config('database.limits'),
                'replay' => config('costing.replay'),
                'scenarioSeconds' => $plan['seconds'] ?? null,
            ],
            'indexes' => self::indexes(),
            'host' => self::host(),
            'dataset' => $plan['dataset'] ?? null,
            'load' => Budgets::LOAD,
            'results' => $results,
            'reconciliation' => ['before' => $plan['reconciliationBefore'] ?? null, 'beforeMs' => $plan['reconciliationBeforeMs'] ?? null, 'after' => $reconciliationAfter, 'afterMs' => $reconciliationAfterMs],
        ];
    }

    /**
     * The record as the Markdown table that the run prints, for the CI log and the evidence.
     *
     * @param  array<string, mixed>  $record
     */
    public static function markdown(array $record): string
    {
        $reconciliation = is_array($record['reconciliation'] ?? null) ? $record['reconciliation'] : [];
        $lines = [
            '**'.self::LABEL.'**',
            '',
            sprintf('Run %s of %s, revision %s, reconciliation of every product before %s (%s ms) and after %s (%s ms).', self::text($record['run'] ?? null), self::text($record['runs'] ?? null), self::text($record['revision'] ?? null), self::text($reconciliation['before'] ?? null), self::text($reconciliation['beforeMs'] ?? null), self::text($reconciliation['after'] ?? null), self::text($reconciliation['afterMs'] ?? null)),
            '',
            '| Operation | Proposed budget | Statistic | Observed (ms) | Samples | Failures |',
            '| --- | --- | --- | --- | --- | --- |',
        ];

        foreach (is_array($record['results'] ?? null) ? $record['results'] : [] as $result) {
            if (! is_array($result)) {
                continue;
            }

            $lines[] = sprintf('| %s | %s ms | %s | %s | %s | %s |', self::text($result['operation'] ?? null), self::text($result['budgetMs'] ?? null), self::text($result['statistic'] ?? null), self::text($result['observedMs'] ?? null), self::text($result['samples'] ?? null), self::text($result['failures'] ?? null));
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * The metrics of the record without one successful sample, which a dry run of every scenario must
     * not leave (TASK-043 section 7, "The dry run of every scenario").
     *
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    public static function unmeasured(array $record): array
    {
        $missing = [];

        foreach (is_array($record['results'] ?? null) ? $record['results'] : [] as $metric => $result) {
            $samples = is_array($result) && is_int($result['samples'] ?? null) ? $result['samples'] : 0;
            $failures = is_array($result) && is_int($result['failures'] ?? null) ? $result['failures'] : 0;

            if ($samples === 0 || $failures >= $samples) {
                $missing[] = (string) $metric;
            }
        }

        return $missing;
    }

    /**
     * Every index of the schema public, counted, with one SHA-256 of their definitions in order.
     *
     * @return array{count: int, sha256: string}
     */
    private static function indexes(): array
    {
        $definitions = DB::table('pg_catalog.pg_indexes')->where('schemaname', 'public')->orderBy('tablename')->orderBy('indexname')->pluck('indexdef')->all();

        return ['count' => count($definitions), 'sha256' => hash('sha256', implode("\n", array_map(static fn (mixed $definition): string => is_string($definition) ? $definition : '', $definitions)))];
    }

    /**
     * @return array{kind: string|null, system: string, processors: int, memoryMiB: int|null}
     */
    private static function host(): array
    {
        $cpuinfo = is_readable('/proc/cpuinfo') ? (string) file_get_contents('/proc/cpuinfo') : '';
        $meminfo = is_readable('/proc/meminfo') ? (string) file_get_contents('/proc/meminfo') : '';

        return [
            'kind' => self::env('PERF_HOST'),
            'system' => php_uname('s').' '.php_uname('r').' '.php_uname('m'),
            'processors' => preg_match_all('/^processor\s*:/m', $cpuinfo),
            'memoryMiB' => preg_match('/^MemTotal:\s+([0-9]+) kB$/m', $meminfo, $match) === 1 ? intdiv((int) $match[1], 1024) : null,
        ];
    }

    /**
     * The statistics of one metric of the summary, or none.
     *
     * @param  array<mixed>  $metrics
     * @return array<mixed>
     */
    private static function values(array $metrics, string $name): array
    {
        $metric = $metrics[$name] ?? null;
        $values = is_array($metric) ? ($metric['values'] ?? null) : null;

        return is_array($values) ? $values : [];
    }

    private static function fpmMaxChildren(): ?int
    {
        $file = base_path('docker/app/php-fpm.conf');
        $conf = is_readable($file) ? (string) file_get_contents($file) : '';

        return preg_match('/^pm\.max_children\s*=\s*([0-9]+)$/m', $conf, $match) === 1 ? (int) $match[1] : null;
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '-';
    }
}
