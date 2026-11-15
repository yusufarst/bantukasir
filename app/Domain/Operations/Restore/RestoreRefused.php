<?php

namespace App\Domain\Operations\Restore;

use RuntimeException;

/**
 * A refusal of a restore procedure — OP-04, OP-05 or OP-08 (SECURITY_MODEL SEC-OP-04, SEC-OP-05,
 * SEC-OP-08; TASK-037) — with nothing written. Its message is for the operator, in English like the
 * runbooks, and never holds a secret.
 */
final class RestoreRefused extends RuntimeException {}
