<?php

namespace App\Domain\Documents;

use LogicException;

/**
 * The template versions of the documents, kept in the code base (ARCHITECTURE ARC-44, ARC-45;
 * BR-DOC-05; DOCUMENT_CONTRACTS DC-01 to DC-03; TASK-031 scope item 1). A snapshot names the version
 * that renders it — `receipt.1` for the receipt in 58 mm, 80 mm and A4 (SCHEMA section 5.9), and
 * `session_report.1` for the session report and its CSV (section 5.10) — and a version, once a
 * snapshot names it, is never changed in place: a change of a template is a new version added here
 * and beside its renderers (resources/js/lib/documents/templates.ts, SessionReportCsv), so that every
 * earlier document is reproduced exactly. A version that no renderer knows is a defect, never a
 * document rendered by another version.
 */
final class Templates
{
    /** The receipt of DC-01 as TASK-028 froze its first snapshots (App\Domain\Sales\Sales::RECEIPT_TEMPLATE). */
    public const string RECEIPT_1 = 'receipt.1';

    /** The session report of DC-02 and its CSV of DC-03 (App\Domain\Cash\SessionReports::TEMPLATE). */
    public const string SESSION_REPORT_1 = 'session_report.1';

    /** @var list<string> */
    public const array RECEIPTS = [self::RECEIPT_1];

    /** @var list<string> */
    public const array SESSION_REPORTS = [self::SESSION_REPORT_1];

    /**
     * The receipt's version, which a renderer of this code base knows.
     */
    public static function receipt(string $version): string
    {
        return in_array($version, self::RECEIPTS, true) ? $version : throw new LogicException(sprintf('%s is no receipt template of the code base (ARC-45).', $version));
    }

    /**
     * The session report's version, which a renderer of this code base knows.
     */
    public static function sessionReport(string $version): string
    {
        return in_array($version, self::SESSION_REPORTS, true) ? $version : throw new LogicException(sprintf('%s is no session report template of the code base (ARC-45).', $version));
    }
}
