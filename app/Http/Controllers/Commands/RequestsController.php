<?php

namespace App\Http\Controllers\Commands;

use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-006, UXP-006 "Permintaan belum pasti" (ROUTE_CONTRACTS section 2;
 * CAP-34; INTERACTION_CONTRACTS section 2.2; TASK-009 scope item 6). The
 * page carries no data of its own: the browser builds its list from the
 * user's references in this browser (SECURITY_MODEL SEC-CH-05) and from
 * RD-04 — the user's own unresolved receipts and, for the Owner, every
 * other user's — so that what it shows is what the server answers to that
 * user at that moment, under RD-04's own authorization.
 */
final class RequestsController
{
    public function index(): Response
    {
        return Inertia::render('commands/Requests');
    }
}
