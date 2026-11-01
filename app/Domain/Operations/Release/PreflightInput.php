<?php

namespace App\Domain\Operations\Release;

/**
 * What the operator types for the release preflight (REL-23): the environment's
 * name, the release record's list of migrations, the approvals that record names,
 * and the two confirmations. Nothing of it comes from the environment or the
 * database, so that a typed value can differ from what is there.
 */
final readonly class PreflightInput
{
    /**
     * @param  list<string>  $migrations  the release record's list, by migration name
     * @param  list<string>  $approvals  the decisions that the release record names under REL-06
     */
    public function __construct(
        public string $environment,
        public array $migrations,
        public array $approvals,
        public bool $backupSchedulePaused,
        public bool $firstInstallation,
    ) {}
}
