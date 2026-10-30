<?php

namespace App\Domain\Access;

use RuntimeException;

/**
 * The bootstrap procedure refused (SECURITY_MODEL SEC-OP-01; RACE-30): an
 * account of some kind exists, or the installation's state was inserted by
 * another run, which then committed first. Nothing was written.
 */
final class BootstrapRefused extends RuntimeException
{
    public const string ACCOUNTS_EXIST = 'ACCOUNTS_EXIST';

    public const string ALREADY_INSTALLED = 'ALREADY_INSTALLED';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::ACCOUNTS_EXIST => 'An account exists in this installation; the bootstrap runs only before any account.',
            default => 'This installation has been bootstrapped already.',
        });
    }
}
