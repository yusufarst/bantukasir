<?php

namespace App\Domain\Audit;

/**
 * The event types of the security log (SECURITY_MODEL SEC-LOG-03; SCHEMA
 * section 5.4), the nine values of `security_log_entries.event_type`.
 */
enum SecurityEvent: string
{
    case SignInSucceeded = 'SIGN_IN_SUCCEEDED';
    case SignInFailed = 'SIGN_IN_FAILED';
    case Throttled = 'THROTTLED';
    case LinkFailed = 'LINK_FAILED';
    case PasswordConfirmationFailed = 'PASSWORD_CONFIRMATION_FAILED';
    case CsrfFailed = 'CSRF_FAILED';
    case AuthorizationRefused = 'AUTHORIZATION_REFUSED';
    case SessionRevoked = 'SESSION_REVOKED';
    case OperatorProcedureRun = 'OPERATOR_PROCEDURE_RUN';
}
