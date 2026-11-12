<?php

namespace App\Http\Requests\Documents;

use App\Actions\Documents\ExportSessionReport;

/**
 * The FormRequest of RC-46 `document.export_session_report` (SessionReportRequest).
 */
final class ExportSessionReportRequest extends SessionReportRequest
{
    public function operation(): string
    {
        return ExportSessionReport::OPERATION;
    }
}
