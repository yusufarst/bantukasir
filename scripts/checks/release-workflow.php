<?php

// The check of the release workflows (AC-014-01; ADR-011 items 4 and 5; TASK-014 scope
// item 1), read from their text, because no YAML reader is a dependency of the project.
//
// .github/workflows/release.yml fails when:
//   - it can run for anything but a release or candidate tag: its "on" block is exactly
//     the push of the two tag patterns;
//   - it names a secret other than the token GitHub issues to the workflow, an
//     environment, or inherited secrets;
//   - a job can run in a fork: it has no check of the repository;
//   - it grants a write permission other than publishing packages, or grants that for
//     the whole workflow;
//   - an action is not pinned by its full commit;
//   - it does not pass the version and the commit that label the image.
//
// .github/workflows/release-dry-run.yml fails when it could publish: a write permission,
// a secret, a login to or a push to a registry, or the publishing script.
//
// Usage: php scripts/checks/release-workflow.php [--release=FILE] [--dry-run=FILE]

$root = dirname(__DIR__, 2);
$release = $root.'/.github/workflows/release.yml';
$dryRun = $root.'/.github/workflows/release-dry-run.yml';

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--release=')) {
        $release = substr($argument, strlen('--release='));
    } elseif (str_starts_with($argument, '--dry-run=')) {
        $dryRun = substr($argument, strlen('--dry-run='));
    } else {
        fwrite(STDERR, "Unknown argument: {$argument}\n");
        exit(2);
    }
}

/** The lines of the workflow, without comments and blank lines, each trimmed on the right. */
function workflowLines(string $file): array
{
    $lines = [];

    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = rtrim($line, " \r");

        if (trim($line) !== '' && ! str_starts_with(trim($line), '#')) {
            $lines[] = $line;
        }
    }

    return $lines;
}

/** The trimmed lines of a top-level key's block, with the key's own line. */
function topLevelBlock(array $lines, string $key): array
{
    $block = [];
    $inside = false;

    foreach ($lines as $line) {
        if (preg_match('/^[^\s#]/', $line) === 1) {
            $inside = str_starts_with($line, $key.':');
        }

        if ($inside) {
            $block[] = trim($line);
        }
    }

    return $block;
}

$problems = [];

$expectedTriggers = [
    'on:',
    'push:',
    'tags:',
    '- "v[0-9]+.[0-9]+.[0-9]+"',
    '- "v[0-9]+.[0-9]+.[0-9]+-rc.[0-9]+"',
];

$lines = is_file($release) ? workflowLines($release) : [];
$text = implode("\n", $lines);

if ($lines === []) {
    $problems[] = 'release.yml: it does not exist';
}

if (topLevelBlock($lines, 'on') !== $expectedTriggers) {
    $problems[] = 'release.yml: it can run for something other than a release or candidate tag';
}

preg_match_all('/\bsecrets\.([A-Za-z0-9_]+)/', $text, $secrets);

foreach (array_unique($secrets[1]) as $secret) {
    if ($secret !== 'GITHUB_TOKEN') {
        $problems[] = 'release.yml: it names the secret '.$secret;
    }
}

if (preg_match('/^\s*secrets:\s*inherit\b/m', $text) === 1) {
    $problems[] = 'release.yml: it inherits secrets';
}

if (preg_match('/^\s*environment:/m', $text) === 1) {
    $problems[] = 'release.yml: it names an environment';
}

if (preg_match('/^\s+if:.*github\.repository == \'[^\']+\'/m', $text) !== 1) {
    $problems[] = 'release.yml: a fork could publish: no job checks the repository';
}

if (topLevelBlock($lines, 'permissions') !== ['permissions: {}']) {
    $problems[] = 'release.yml: it grants permissions to the whole workflow';
}

preg_match_all('/^\s*([a-z-]+):\s*write\b/m', $text, $writes);

foreach ($writes[1] as $permission) {
    if ($permission !== 'packages') {
        $problems[] = 'release.yml: it grants the write permission '.$permission;
    }
}

preg_match_all('/^[\s-]*uses:\s*(\S+)/m', $text, $actions);

foreach ($actions[1] as $action) {
    if (preg_match('/@[0-9a-f]{40}$/', $action) !== 1) {
        $problems[] = 'release.yml: the action '.$action.' is not pinned by its full commit';
    }
}

foreach (['RELEASE_VERSION', 'RELEASE_COMMIT'] as $label) {
    if (preg_match('/^\s*'.$label.':\s*\$\{\{ github\.[a-z_]+ \}\}/m', $text) !== 1) {
        $problems[] = 'release.yml: it does not pass '.$label.', which labels the image';
    }
}

$dryLines = is_file($dryRun) ? workflowLines($dryRun) : [];
$dryText = implode("\n", $dryLines);

if ($dryLines === []) {
    $problems[] = 'release-dry-run.yml: it does not exist';
}

foreach ([
    'a write permission' => '/^\s*[a-z-]+:\s*write\b/m',
    'a secret' => '/\bsecrets\b/',
    'a login to a registry' => '/\bdocker\s+login\b/',
    'a push to a registry' => '/\bdocker\s+push\b/',
    'the publishing script' => '/scripts\/release\/publish\.sh/',
    'a tag trigger' => '/^\s*tags:/m',
] as $description => $pattern) {
    if (preg_match($pattern, $dryText) === 1) {
        $problems[] = 'release-dry-run.yml: it holds '.$description;
    }
}

if (! str_contains($dryText, 'scripts/checks/image-checks.sh')) {
    $problems[] = 'release-dry-run.yml: it does not run the image checks';
}

if ($problems !== []) {
    fwrite(STDOUT, "Release workflow check failed:\n  ".implode("\n  ", $problems)."\n");
    exit(1);
}

fwrite(STDOUT, "Release workflow check: only tags, the workflow's own token, never from a fork, and a dry run that cannot publish.\n");
exit(0);
