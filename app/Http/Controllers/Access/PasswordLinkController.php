<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\ConsumeLink;
use App\Domain\Access\LinkInvalid;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Http\Requests\Access\ConsumeLinkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-002, UXP-002 "Atur kata sandi", and RA-06, the consumption of a
 * one-time link (ROUTE_CONTRACTS sections 2 and 4; SECURITY_MODEL SEC-ACC-05,
 * SEC-ACC-06). The account and the token travel only in the link's fragment,
 * which the page reads and removes before anything else runs, and in the body
 * of the submission; neither is ever a prop, a flash, a redirect or a URL.
 * Both responses send no referrer. The page's one prop says whether the
 * password was just set; its wording is the same for a first password and a
 * reset, so that it reveals nothing about the link (COPY_DECK section 6.3).
 */
final class PasswordLinkController
{
    private const string SET = 'access.link_consumed';

    public function show(Request $request): Response
    {
        Inertia::clearHistory();

        return Inertia::render('access/SetPassword', [
            'passwordSet' => $request->session()->get(self::SET) === true,
        ]);
    }

    /**
     * @throws LinkInvalid answered by App\Http\Auth\LinkInvalidAnswer
     */
    public function store(ConsumeLinkRequest $request, ConsumeLink $consume, SecurityLog $securityLog): RedirectResponse
    {
        $result = $consume($request->userId(), $request->token(), $request->newPassword());

        if ($result['sessionsRevoked'] > 0) {
            $securityLog->record(SecurityEvent::SessionRevoked, SecurityActor::user($result['userId']), SecurityOutcome::Succeeded, $request, $result['userId']);
        }

        return redirect()->route('password.link')->with(self::SET, true);
    }
}
