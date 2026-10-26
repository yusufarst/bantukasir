<?php

namespace App\Domain\Audit;

/**
 * The category of an audit event (SCHEMA section 5.4): a business change, or
 * one of the security-relevant changes of SECURITY_MODEL SEC-LOG-02, which
 * only the Owner reads (BR-AUD-04).
 */
enum AuditCategory: string
{
    case Business = 'BUSINESS';
    case Security = 'SECURITY';
}
