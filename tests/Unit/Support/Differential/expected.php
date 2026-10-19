<?php

// Writes the server's expected outcomes into a table of differential cases of
// TASK-003, once, after generate-cases.mjs has written its inputs: the
// outcomes of ServerOutcomes and the ux.format texts that the client's run
// installs as its dictionary. It is run by hand, never by a test, so that no
// test writes a file and neither run depends on the other.
//
// Usage, from the project's root (in the app container):
//   php tests/Unit/Support/Differential/expected.php [FILE]
// FILE defaults to tests/Unit/Support/cases/differential.json.

use App\Support\Localization\Dictionary;
use Illuminate\Contracts\Console\Kernel;
use Tests\Unit\Support\Differential\ServerOutcomes;

$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';

$application = require $root.'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

$file = $argv[1] ?? $root.'/tests/Unit/Support/cases/differential.json';
$table = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

if (! is_array($table) || ! is_array($table['cases'] ?? null)) {
    fwrite(STDERR, "{$file} holds no cases.\n");
    exit(1);
}

/** @var array<string, list<mixed>> $cases */
$cases = $table['cases'];
$table['dictionary'] = array_filter(Dictionary::forInterface(), fn (string $key): bool => str_starts_with($key, 'ux.format.'), ARRAY_FILTER_USE_KEY);
$table['expected'] = ServerOutcomes::all($cases);

file_put_contents($file, json_encode($table, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

$counts = array_map(fn (array $outcomes): int => count($outcomes), $table['expected']);
fwrite(STDOUT, sprintf("%s: expected outcomes written, %d in all (%s).\n", $file, array_sum($counts), implode(', ', array_map(fn (string $name, int $count): string => "{$name} {$count}", array_keys($counts), $counts))));
