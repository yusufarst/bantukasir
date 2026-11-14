<?php

namespace App\Http\Controllers\Cash;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Cash\DrawerRefunds;
use App\Domain\Cash\ReviewNotes;
use App\Domain\Cash\Sessions;
use App\Domain\Cash\SessionSales;
use App\Domain\Sales\Corrections\SaleCorrections;
use App\Domain\Sales\Sales;
use App\Http\Requests\Cash\SessionsPageRequest;
use App\Models\User;
use App\Policies\NotFound;
use App\Queries\Cash\CashRegisterRows;
use App\Queries\Cash\SessionRecords;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-205, UXP-205 "Sesi kas", and RP-206, UXP-206 "Detail sesi kas" (ROUTE_CONTRACTS RP-205, RP-206;
 * COPY_DECK sections 7.10 and 7.11; NAVIGATION_CONTRACTS UXP-205, UXP-206; BR-CASH-19 to BR-CASH-21;
 * TASK-030 scope item 3), for a holder of CAP-21 on the sessions they are responsible for and for the
 * Owner on every session (CAP-22); OPERATIONS_ADMIN has no access (the gate).
 *
 * RP-205: one page of the sessions in scope with the filters and the page's size as they were read,
 * which the page keeps in its address when it pages (NC-10); for the Owner also
 * the registers and the people of the filters "Laci kas" and "Penanggung jawab" (UXI-136). RP-206: one
 * session in scope, NOT_FOUND otherwise (TR-ERR-03); an OPEN session with its running figures, which
 * the Sales module's query gives (STK-10), and whether the viewer may close it here — its own
 * (UXI-139), and, for the Owner, whether he may close it with an Owner-assisted close — another
 * user's (UXI-140); a CLOSED session with its immutable report (BR-CASH-19) and, for the Owner, its
 * review notes as a deferred prop (UXI-144). The print and export controls are TASK-031's, the
 * Owner's review notes and assisted close TASK-032's, and the drawer-funded refunds — their total in an
 * OPEN session's figures and, beside a CLOSED session's variance, each refund as the report froze it
 * (UXI-143) — TASK-034's.
 */
final class SessionsController
{
    /** The Owner's deferred prop of RP-206, the review notes (UXI-144; TASK-032). */
    public const string NOTES = 'notes';

    public function index(SessionsPageRequest $request, SessionRecords $sessions, CashRegisterRows $registers): Response
    {
        $viewer = self::viewer($request);
        $filters = $request->filters();
        $owner = Capabilities::allows($viewer, Capability::Cap22);

        return Inertia::render('cash/Sessions', [
            'filters' => [
                'status' => $filters['status'],
                'from' => $filters['from'],
                'to' => $filters['to'],
                'register' => $filters['register'],
                'responsible' => $filters['responsible'],
                'perPage' => $filters['perPage'],
            ],
            'sessions' => $sessions->list($viewer, $filters),
            // The Owner's filters (UXI-136); none for anyone else, who sees only their own sessions.
            'registers' => $owner ? array_map(static fn (array $register): array => ['id' => $register['id'], 'code' => $register['code'], 'label' => $register['label']], $registers->list()) : [],
            'responsibles' => $owner ? $sessions->responsibles() : [],
        ]);
    }

    /**
     * @throws NotFound
     */
    public function show(Request $request, string $session, SessionRecords $sessions, Sales $sales, SaleCorrections $corrections, ReviewNotes $notes): Response
    {
        $viewer = self::viewer($request);
        $read = $sessions->detail($session, $viewer);
        $found = $read['session'];
        $report = $read['report'];
        $props = [
            'session' => [
                'id' => $found['id'],
                'number' => $found['number'],
                'status' => $found['status'],
                'registerCode' => $found['registerCode'],
                'registerLabel' => $found['registerLabel'],
                'responsible' => $found['responsible'],
                'openedAt' => $found['openedAt'],
                'closedAt' => $found['closedAt'],
                // UXI-139: the responsible user's own OPEN session.
                'canClose' => $found['status'] === Sessions::OPEN && $found['own'] && Capabilities::allows($viewer, Capability::Cap21),
                // UXI-140: another user's OPEN session, for the Owner (CAP-23; TASK-032).
                'canAssistedClose' => $found['status'] === Sessions::OPEN && ! $found['own'] && Capabilities::allows($viewer, Capability::Cap23),
            ],
            'figures' => $report === null ? SessionSales::of($sales->ofSession($found['id']))->figures($read['openingCash'], DrawerRefunds::of($corrections->drawerRefundsOf($found['id']))) : $report['figures'],
            // The count of the report, every field null while the session is OPEN.
            'count' => $report === null ? ['physicalCash' => null, 'variance' => null, 'closedBy' => null, 'closeReason' => null, 'notes' => null] : $report['count'],
            // UXI-143: the report's drawer-funded refunds beside the variance, each as it was frozen; none
            // while the session is OPEN, whose figures hold their total (TASK-034; OPEN_ITEMS T-45).
            'drawerRefunds' => $report === null ? [] : $report['refunds'],
        ];

        // UXI-144: the Owner's review notes of a CLOSED session, a deferred prop that the page reads
        // again after "Simpan catatan" (TASK-032); nobody else reads them (CAP-22).
        if ($report !== null && Capabilities::allows($viewer, Capability::Cap22)) {
            $props[self::NOTES] = Inertia::defer(static fn (): array => $notes->of($found['id']));
        }

        return Inertia::render('cash/Session', $props);
    }

    private static function viewer(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
