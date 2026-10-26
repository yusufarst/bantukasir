<?php

namespace App\Http\Auth;

use App\Domain\Access\LinkInvalid;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer to every failure of a one-time link (RA-06; SECURITY_MODEL
 * SEC-ACC-06; UI_STATES UXS-41): the one uniform LINK_INVALID, which reveals
 * nothing about the account or the link (TR-ERR-04), on the validation-error
 * channel (TECHNICAL_RULES section 4) — 422 with the error body of
 * ROUTE_CONTRACTS RT-07 for JSON, the form error of the page otherwise. It is
 * written after the consumption's transaction has rolled back, with the
 * LINK_FAILED entry of the security log (SEC-LOG-03).
 */
final class LinkInvalidAnswer
{
    public static function for(Request $request, LinkInvalid $failure): Response
    {
        app(SecurityLog::class)->record(SecurityEvent::LinkFailed, SecurityActor::anonymous(), SecurityOutcome::Refused, $request, $failure->namedUserId);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse([
                'outcome' => 'REJECTED',
                'code' => 'LINK_INVALID',
                'messageKey' => 'ux.state.link_invalid',
                'params' => (object) [],
                'fields' => (object) [],
                'lines' => (object) [],
            ], 422);
        }

        return redirect()->route('password.link')->withErrors(['form' => __('ux.state.link_invalid.body')]);
    }
}
