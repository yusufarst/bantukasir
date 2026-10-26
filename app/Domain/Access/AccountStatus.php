<?php

namespace App\Domain\Access;

/**
 * The status of an account (SCHEMA section 5.1; BR-USR-05, BR-USR-10,
 * BR-USR-11). Only an ACTIVE account with a usable password can sign in
 * (SECURITY_MODEL SEC-AUTH-09).
 */
enum AccountStatus: string
{
    case Active = 'ACTIVE';
    case Deactivated = 'DEACTIVATED';
    case Anonymized = 'ANONYMIZED';
}
