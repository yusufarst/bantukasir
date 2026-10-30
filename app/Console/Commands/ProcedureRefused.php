<?php

namespace App\Console\Commands;

use RuntimeException;

/**
 * A refusal of an operator procedure after the identification of its
 * environment (OperatorProcedure): nothing was written, and the run is
 * recorded in the security log as refused. Its message is for the operator.
 */
final class ProcedureRefused extends RuntimeException {}
