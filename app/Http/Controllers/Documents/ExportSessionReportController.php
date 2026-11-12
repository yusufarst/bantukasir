<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Documents\ExportSessionReport;
use App\Domain\Documents\SessionReports\SessionReportCsv;
use App\Domain\Documents\SessionReports\SessionReportDocuments;
use App\Http\Commands\CommandPipeline;
use App\Http\Requests\Documents\ExportSessionReportRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * RC-46 `document.export_session_report` (ROUTE_CONTRACTS section 6; INTERACTION_CONTRACTS, "RC-44 to
 * RC-47"; DOCUMENT_CONTRACTS DC-03; BR-CASH-22; TASK-031 scope item 3). The command runs through the
 * pipeline of every command (CommandPipeline), which records its request receipt, its audit event and
 * its result; a refusal is that answer as it is. Once the export is committed — now (201) or as the
 * same identity's repeat (200) — the answer is the CSV of DC-03 itself, rendered from the report's
 * immutable snapshot for the actor under their scope now (SEC-CH-07), as an attachment named
 * `laporan-sesi-<number>.csv`: the same file however often it is asked for. A committed export whose
 * result the actor may no longer see is answered as the pipeline gives it, without a file.
 */
final class ExportSessionReportController
{
    public function __invoke(Request $request, CommandPipeline $pipeline, SessionReportDocuments $documents): Response
    {
        $answer = $pipeline->run($request, ExportSessionReportRequest::class, ExportSessionReport::class);
        $data = $answer->getData(true);
        $result = is_array($data) ? ($data['result'] ?? null) : null;
        $session = is_array($result) && ($result['type'] ?? null) === ExportSessionReport::RESULT ? ($result['id'] ?? null) : null;
        $viewer = $request->user();

        if (! in_array($answer->getStatusCode(), [200, 201], true) || ! is_int($session) || ! $viewer instanceof User) {
            return $answer;
        }

        $read = $documents->forViewer($session, $viewer);
        $file = SessionReportCsv::render($read['report'], $read['local']);

        return response($file, $answer->getStatusCode(), [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, SessionReportCsv::fileName($read['report']['number'])),
        ]);
    }
}
