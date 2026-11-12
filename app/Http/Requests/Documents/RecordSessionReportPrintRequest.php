<?php

namespace App\Http\Requests\Documents;

use App\Actions\Documents\RecordSessionReportPrint;

/**
 * The FormRequest of RC-45 `document.print_session_report` (SessionReportRequest).
 */
final class RecordSessionReportPrintRequest extends SessionReportRequest
{
    public function operation(): string
    {
        return RecordSessionReportPrint::OPERATION;
    }
}
