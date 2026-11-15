<?php

namespace App\Domain\Operations\Reconciliation;

use RuntimeException;

/**
 * A refusal of a repair procedure — the projection rebuild OP-07 or the cost recovery OP-06
 * (SECURITY_MODEL SEC-OP-06, SEC-OP-07; TASK-036) — with nothing written. Its message is for the
 * operator, in English like the runbooks, and names no amount or person.
 */
final class RepairRefused extends RuntimeException {}
