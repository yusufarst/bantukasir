<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\SessionReports\SessionReportDocuments;
use App\Domain\Documents\SessionReports\SessionReportPrints;
use App\Models\User;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-206P, the print view of a session report (ROUTE_CONTRACTS RP-206P; DOCUMENT_CONTRACTS DC-02;
 * BR-CASH-19 to BR-CASH-22; ARC-44, ARC-45; TASK-031 scope item 2): the report of a CLOSED session
 * rendered on A4 from its snapshot only, by the template version it names, without a copy marker. The
 * session is read under the viewer's scope now — its responsible user with CAP-21, or the Owner — and
 * the print request must be a committed RC-45 of this session's report; anything else is one NOT_FOUND
 * (TR-ERR-03). A read (TR-HTTP-02).
 */
final class SessionReportPrintController
{
    /**
     * @throws NotFound
     */
    public function show(Request $request, string $session, string $printRequest, SessionReportDocuments $documents, SessionReportPrints $prints): Response
    {
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            abort(401);
        }

        $read = $documents->forViewer($session, $viewer);
        $requestKey = ObjectScope::key($printRequest);

        if ($requestKey === null || ! $prints->exists($read['report']['sessionId'], $requestKey)) {
            throw new NotFound;
        }

        return Inertia::render('documents/SessionReportPrint', ['report' => $read['report'], 'issuer' => $read['issuer']]);
    }
}
