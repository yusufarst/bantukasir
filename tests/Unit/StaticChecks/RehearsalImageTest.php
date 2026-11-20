<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\TestCase;

/**
 * The accounts of the restore rehearsal's image (TASK-037 scope item 7; docker/rehearsal/Dockerfile).
 * Its base, the pinned PostgreSQL image of Debian 13, already holds the system group operator of
 * Debian's base-passwd, and useradd given no group makes one of the account's own name: the build of
 * the account operator stopped on that group in step 2 of the job Restore rehearsal of CI run
 * 43746933217 (TASK-037 fix cycle 1). Each account of the image is given its group by name, which this
 * test checks in the fast run, before the job builds the image on the pull request's head.
 */
final class RehearsalImageTest extends TestCase
{
    use RunsChecks;

    public function test_each_account_of_the_rehearsal_image_is_given_its_group(): void
    {
        $accounts = [];

        foreach (file(self::projectRoot().'/docker/rehearsal/Dockerfile', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (preg_match('/\buseradd\s+([^;]*)/', $line, $call) !== 1) {
                continue;
            }

            $arguments = preg_split('/\s+/', trim($call[1])) ?: [];
            $accounts[(string) end($arguments)] = $arguments;
        }

        $this->assertArrayHasKey('operator', $accounts, 'The image makes the account operator, as which compose.yaml runs the service operator.');

        foreach ($accounts as $name => $options) {
            $group = array_search('--gid', $options, true);
            $this->assertIsInt($group, sprintf('useradd is given no group for %s, so it makes a group of that name, which the base image may already hold.', $name));
            $this->assertMatchesRegularExpression('/^[a-z_][a-z0-9_-]*$/', $options[$group + 1] ?? '', sprintf('useradd is given the group of %s by its name.', $name));
        }
    }
}
