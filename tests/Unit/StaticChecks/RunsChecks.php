<?php

namespace Tests\Unit\StaticChecks;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

/**
 * Runs a check the way the command contract does — from the project's root,
 * with an argument list, never a shell string (THR-10) — and gives a test its
 * own temporary directory outside the tracked tree (EXC-63).
 */
trait RunsChecks
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private static function runCheck(array $command, array $environment = []): Process
    {
        $process = new Process($command, self::projectRoot(), $environment === [] ? null : $environment, null, 600);
        $process->run();

        return $process;
    }

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function makeTemporaryDirectory(string $purpose): string
    {
        $directory = sprintf('%s/bantukasir-%s-%s', sys_get_temp_dir(), $purpose, bin2hex(random_bytes(8)));
        mkdir($directory, 0700);

        return $directory;
    }

    /**
     * Removes the one directory the test created, and only that one.
     */
    private static function removeTemporaryDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            assert($entry instanceof SplFileInfo);
            $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($directory);
    }
}
