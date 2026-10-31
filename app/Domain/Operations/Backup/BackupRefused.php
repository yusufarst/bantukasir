<?php

namespace App\Domain\Operations\Backup;

use RuntimeException;

/**
 * The run cannot start: its settings are not those of a backup. No code of SCHEMA
 * section 5.12 names this and no row is written, since the database may not be
 * this environment's (ENV-04); settings:check refuses the same settings at the
 * container's start. The message is a fixed word, never a value.
 */
final class BackupRefused extends RuntimeException {}
