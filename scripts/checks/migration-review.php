<?php

// Migration review (TEST_STRATEGY section 6, PRODUCTION_DATA_SAFETY section 1,
// REL-06, REL-20; TASK-014 scope item 6). A migration fails when:
//
// - it drops a table, a column, a schema or a database, truncates, deletes or
//   updates rows, or is marked with the class destructive, unless it names the
//   Owner's approval of that exact operation in a line
//
//       // REL-06 approval: D-NNN
//
//   where D-NNN is the decision of DECISIONS that records the approval;
// - its comment lacks the metadata of REL-20: its class, expected duration, lock
//   impact and verification queries.
//
// Every table is treated as retained data. Migrations are forward only (EXC-41),
// so a down() that drops is refused too. What a source says is read by
// App\Domain\Operations\Release\MigrationReview, which release:preflight reads too.
//
// Usage: php scripts/checks/migration-review.php [--migrations=DIR] [--decisions=FILE]

use App\Domain\Operations\Release\MigrationReview;

$root = dirname(__DIR__, 2);
$migrations = $root.'/database/migrations';
$decisions = $root.'/docs/00-governance/DECISIONS.md';

require_once $root.'/app/Domain/Operations/Release/MigrationReview.php';

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

$approved = [];

if (is_file($decisions)) {
    preg_match_all('/^### (D-\d{3,})\b/m', (string) file_get_contents($decisions), $matches);
    $approved = array_flip($matches[1]);
}

$files = is_dir($migrations) ? glob($migrations.'/*.php') : [];
$problems = [];

foreach ($files ?: [] as $file) {
    $code = (string) file_get_contents($file);
    $missing = MigrationReview::missingMetadata($code);

    if ($missing !== []) {
        $problems[] = sprintf('%s: no REL-20 metadata: %s', basename($file), implode(', ', $missing));
    }

    if (! MigrationReview::isDestructive($code)) {
        continue;
    }

    $found = MigrationReview::destructiveStatements($code);

    if ($found === []) {
        $found = ['marked destructive'];
    }

    $references = MigrationReview::approvals($code);
    $known = array_filter($references, fn (string $decision): bool => array_key_exists($decision, $approved));

    if ($references === []) {
        $problems[] = sprintf('%s: %s, with no REL-06 approval reference', basename($file), implode('; ', $found));
    } elseif (count($known) !== count($references)) {
        $problems[] = sprintf('%s: %s, with a REL-06 approval reference to a decision that %s does not record', basename($file), implode('; ', $found), $decisions);
    }
}

if ($problems !== []) {
    fwrite(STDOUT, "Migration review failed (REL-06, REL-20):\n  ".implode("\n  ", $problems)."\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Migration review: %d migration(s), none destructive without its approval, each with its REL-20 metadata.\n", count($files ?: [])));
exit(0);
