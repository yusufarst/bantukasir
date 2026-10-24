<?php

/*
 * The process of a race worker (TEST_STRATEGY TS-20, TS-21; TASK-004): one
 * competing command in a process of its own, with its own connection, started
 * by Tests\Harness\Race\Worker. Before its command it refuses to run outside
 * the testing environment, and on any connection but the runtime role on the
 * test database that its test checked, whose identity the worker's environment
 * gives (Worker::DATABASE, TestDatabase::verify); it binds PipePoints, so that
 * the test controls the command's interleaving through standard input and
 * output (Protocol), runs the scenario and reports its result or its failure.
 *
 * Usage: php tests/Harness/Race/process.php <scenario class> <arguments as base64 JSON> <barriers, comma-separated, or -> <fail after write step k, or ->
 */

use App\Database\CommandPoints;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Harness\Race\PipePoints;
use Tests\Harness\Race\Protocol;
use Tests\Harness\Race\Scenario;
use Tests\Harness\Race\Worker;
use Tests\Harness\TestDatabase;

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$argv = $_SERVER['argv'] ?? [];
[$class, $encoded, $barriers, $failAfter] = is_array($argv) && count($argv) === 5 ? array_slice($argv, 1) : [null, null, null, null];

try {
    if (! $app->environment('testing')) {
        throw new RuntimeException('The race worker runs only in the testing environment.');
    }

    $identity = getenv(Worker::DATABASE);

    if (! is_string($identity) || $identity === '') {
        throw new RuntimeException('The race worker needs the identity of the test database ('.Worker::DATABASE.').');
    }

    TestDatabase::verify(DB::connection(), 'runtime', $identity);

    if (! is_string($class) || ! str_starts_with($class, 'Tests\\') || ! is_subclass_of($class, Scenario::class)) {
        throw new RuntimeException('The race worker runs a Scenario of the test suite only.');
    }

    $arguments = json_decode(base64_decode(is_string($encoded) ? $encoded : '', true) ?: 'null', true, 16, JSON_THROW_ON_ERROR);

    if (! is_array($arguments) || array_filter(array_keys($arguments), is_int(...)) !== []) {
        throw new RuntimeException('The race worker needs its arguments as a JSON object.');
    }

    $app->instance(CommandPoints::class, new PipePoints(
        is_string($barriers) && $barriers !== '-' ? explode(',', $barriers) : [],
        is_string($failAfter) && $failAfter !== '-' ? (int) $failAfter : null,
        STDIN,
        STDOUT,
    ));

    $pid = DB::scalar('select pg_backend_pid()');
    Protocol::send(STDOUT, 'READY', is_int($pid) ? (string) $pid : '0');

    // Booted and connected: the command starts only when the test says so,
    // so that no transaction waits while another worker starts.
    if (fgets(STDIN) !== "RUN\n") {
        throw new RuntimeException('The race worker was not told to run.');
    }

    $scenario = $app->make($class);
    assert($scenario instanceof Scenario);

    /** @var array<string, mixed> $arguments */
    Protocol::send(STDOUT, 'DONE', json_encode($scenario->run($arguments), JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $failure) {
    Protocol::send(STDOUT, 'FAILED', json_encode([
        'class' => $failure::class,
        'sqlstate' => $failure instanceof QueryException ? (string) $failure->getCode() : null,
        'message' => $failure->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
