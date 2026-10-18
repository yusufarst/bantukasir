<?php

// Migration review (TEST_STRATEGY section 6, PRODUCTION_DATA_SAFETY section 1,
// REL-06): a migration that drops a table, a column, a schema or a database,
// truncates, or deletes or updates rows fails, unless it names the Owner's
// approval of that exact operation in a line
//
//     // REL-06 approval: D-NNN
//
// where D-NNN is the decision of DECISIONS that records the approval. Every
// table is treated as retained data. Migrations are forward only (EXC-41), so
// a down() that drops is refused too.
//
// Usage: php scripts/checks/migration-review.php [--migrations=DIR] [--decisions=FILE]

$root = dirname(__DIR__, 2);
$migrations = $root.'/database/migrations';
$decisions = $root.'/docs/00-governance/DECISIONS.md';

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--migrations=')) {
        $migrations = substr($argument, strlen('--migrations='));
    } elseif (str_starts_with($argument, '--decisions=')) {
        $decisions = substr($argument, strlen('--decisions='));
    } else {
        fwrite(STDERR, "Unknown argument: {$argument}\n");
        exit(2);
    }
}

// SQL statements and query-builder calls that remove or rewrite data.
$destructive = [
    'DROP TABLE, SCHEMA, DATABASE or COLUMN' => '/\bDROP\s+(?:TABLE|SCHEMA|DATABASE|COLUMN)\b/i',
    'ALTER TABLE … DROP of a column' => '/\bALTER\s+TABLE\s+\S+\s+DROP\s+(?!CONSTRAINT\b|NOT\b|DEFAULT\b|IDENTITY\b|EXPRESSION\b)/i',
    'TRUNCATE' => '/\bTRUNCATE\b/i',
    'DELETE FROM' => '/\bDELETE\s+FROM\b/i',
    'UPDATE … SET' => '/\bUPDATE\s+\S+\s+SET\b/i',
    'a schema-builder drop' => '/(?:Schema::|->)(?:drop|dropIfExists|dropAllTables|dropAllViews|dropColumn|dropColumns|dropMorphs|dropNullableMorphs|dropTimestamps|dropTimestampsTz|dropSoftDeletes|dropSoftDeletesTz|dropRememberToken)\s*\(/i',
    'a query-builder delete or update' => '/->(?:delete|forceDelete|truncate|update|updateOrInsert|upsert|increment|decrement|incrementEach|decrementEach)\s*\(/i',
    'DB::delete() or DB::update()' => '/\bDB::(?:delete|update)\s*\(/i',
];

$approved = [];

if (is_file($decisions)) {
    preg_match_all('/^### (D-\d{3,})\b/m', (string) file_get_contents($decisions), $matches);
    $approved = array_flip($matches[1]);
}

$files = is_dir($migrations) ? glob($migrations.'/*.php') : [];
$problems = [];

foreach ($files ?: [] as $file) {
    $code = (string) file_get_contents($file);
    $found = [];

    foreach ($destructive as $description => $pattern) {
        if (preg_match($pattern, $code) === 1) {
            $found[] = $description;
        }
    }

    if ($found === []) {
        continue;
    }

    preg_match_all('/^\s*\/\/\s*REL-06 approval:\s*(D-\d{3,})\s*$/m', $code, $references);
    $known = array_filter($references[1], fn (string $decision): bool => array_key_exists($decision, $approved));

    if ($references[1] === []) {
        $problems[] = sprintf('%s: %s, with no REL-06 approval reference', basename($file), implode('; ', $found));
    } elseif (count($known) !== count($references[1])) {
        $problems[] = sprintf('%s: %s, with a REL-06 approval reference to a decision that %s does not record', basename($file), implode('; ', $found), $decisions);
    }
}

if ($problems !== []) {
    fwrite(STDOUT, "Migration review failed (REL-06):\n  ".implode("\n  ", $problems)."\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Migration review: %d migration(s), none destructive without its approval.\n", count($files ?: [])));
exit(0);
