<?php

/*
 * The performance harness of TASK-043 (TECHNICAL_RULES section 13; ACCEPTANCE_SUITES PERF01; TEST_STRATEGY
 * section 2, L7), in the application container of the local environment, on its database, whose marker
 * and configured environment name must both be local or test (ENVIRONMENTS ENV-04); it refuses any other
 * environment before it reads anything. It lives in the test harness, which the release build does not
 * contain, and it writes its files only to storage/app/perf, which Git ignores.
 *
 *   php tests/Performance/perf.php dataset --dataset=base|growth [--seed=43] [--scale=1000000] [--until=YYYY-MM-DD]
 *       Builds the dataset, at its scale in millionths (DatasetSize), through the Actions on an
 *       installation without products (Datasets), then
 *       prints its summary, with the reconciliation after it, and keeps it in storage/app/perf/dataset.json.
 *   php tests/Performance/perf.php plan --run=N --runs=M --seconds=S
 *       Writes the plan of run N for the load tool (Plan), after a reconciliation, which it prints.
 *   php tests/Performance/perf.php record --run=N
 *       Reads the summary that the load tool exported for run N, runs the reconciliation again, writes
 *       the record (Recorder) and prints it as a table. A dry run: PERF01 stays NOT_RUN.
 *
 * The last day of a dataset defaults to yesterday in Asia/Jakarta, so that its history ends before the
 * real clock that the server reads.
 */

use App\Actions\Operations\RunReconciliation;
use App\Domain\Operations\Reconciliation\ReconciliationRuns;
use Illuminate\Contracts\Console\Kernel;
use Tests\Performance\Dataset\Datasets;
use Tests\Performance\Dataset\DatasetSize;
use Tests\Performance\Plan;
use Tests\Performance\Recorder;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$argv = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];
$command = is_string($argv[1] ?? null) ? $argv[1] : '';
$options = [];

foreach (array_slice($argv, 2) as $argument) {
    if (is_string($argument) && preg_match('/^--([a-z]+)=(.*)$/D', $argument, $match) === 1) {
        $options[$match[1]] = $match[2];
    }
}

$option = static fn (string $name, string $default): string => $options[$name] ?? $default;
$number = static function (string $name, int $default) use ($option): int {
    $value = $option($name, (string) $default);

    return preg_match('/^[0-9]{1,9}$/D', $value) === 1 ? (int) $value : throw new InvalidArgumentException(sprintf('--%s is a whole number.', $name));
};
$folder = storage_path('app/perf');
$write = static function (string $file, mixed $data) use ($folder): void {
    if (! is_dir($folder) && ! mkdir($folder, 0700, true) && ! is_dir($folder)) {
        throw new RuntimeException('The folder of the harness cannot be made.');
    }

    file_put_contents($folder.'/'.$file, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
};
/** @return array<mixed> */
$read = static function (string $file) use ($folder): array {
    $data = json_decode((string) file_get_contents($folder.'/'.$file), true, 64, JSON_THROW_ON_ERROR);

    return is_array($data) ? $data : throw new RuntimeException(sprintf('%s holds no object.', $file));
};

try {
    if (! $app->environment(['local', 'testing'])) {
        throw new RuntimeException('The performance harness runs only in the local and testing environments.');
    }

    switch ($command) {
        case 'dataset':
            $until = $option('until', now('Asia/Jakarta')->subDay()->toDateString());
            $summary = Datasets::build($option('dataset', Datasets::BASE), $number('seed', Datasets::SEED), $number('scale', DatasetSize::FULL), $until);
            $write('dataset.json', $summary);
            echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
            exit($summary['reconciliation'] === ReconciliationRuns::CLEAN ? 0 : 1);

        case 'plan':
            $run = $number('run', 1);
            $plan = Plan::make($run, $number('runs', 1), $number('seconds', 120), $read('dataset.json'));
            $write('plan.json', $plan);
            echo sprintf("PLAN run=%d reconciliation_before=%s till_products=%d tokens=%d refunds=%d\n", $run, is_string($plan['reconciliationBefore']) ? $plan['reconciliationBefore'] : '-', is_array($plan['till']) ? count($plan['till']) : 0, is_array($plan['tokens']) ? count($plan['tokens']) : 0, is_array($plan['refunds']) ? count($plan['refunds']) : 0);
            exit($plan['reconciliationBefore'] === ReconciliationRuns::CLEAN ? 0 : 1);

        case 'record':
            $run = $number('run', 1);
            $started = microtime(true);
            $after = app(RunReconciliation::class)(null, ReconciliationRuns::SCHEDULE)->outcome;
            $record = Recorder::record($read('plan.json'), $read(sprintf('summary-%d.json', $run)), $after, (int) round((microtime(true) - $started) * 1000));
            $write(sprintf('record-%d.json', $run), $record);
            echo Recorder::markdown($record);
            $missing = Recorder::unmeasured($record);

            if ($missing !== []) {
                echo 'PERF_DRY_RUN_INCOMPLETE no sample of '.implode(', ', $missing)."\n";
            }

            exit($after === ReconciliationRuns::CLEAN && $missing === [] ? 0 : 1);

        default:
            throw new InvalidArgumentException('Usage: perf.php dataset|plan|record [options]');
    }
} catch (Throwable $failure) {
    fwrite(STDERR, sprintf("PERF_FAILED %s %s\n", $failure::class, $failure->getMessage()));
    exit(2);
}
