<?php

namespace App\Database;

use RuntimeException;

/**
 * A database operation refused before its first statement because the target
 * is not the one required: its marker differs from the environment's name
 * (ENVIRONMENTS ENV-04), the connection is not the role that the operation
 * needs (SCHEMA section 10), or the roles of the database cannot be told apart.
 */
final class DatabaseRefused extends RuntimeException {}
