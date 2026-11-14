<?php

namespace App\Domain\Documents\SessionReports;

use App\Domain\Documents\Templates;
use App\Support\Numbers\Money;
use LogicException;

/**
 * The CSV of a session report, DOCUMENT_CONTRACTS DC-03, template `session_report.1` (BR-CASH-22;
 * SECURITY_MODEL SEC-CH-07; ARC-46; TASK-031 scope item 3), rendered on the server from the report's
 * snapshot only, so that a repeated or retried export gives the same file:
 *
 * - UTF-8 with a byte-order mark; the semicolon as separator, the list separator of Indonesian
 *   regional settings; CRLF line ends;
 * - the line `Laporan sesi kas;<number>`, then one line `<label>;<value>` per field of DC-02 rows 3
 *   to 11 — the reason and the notes only when the report holds them — then the time-zone line, an
 *   empty line, the header line of the drawer-funded refunds' table and one line per refund (TASK-034);
 * - the labels of COPY_DECK section 17.2, a money label with " (Rp)";
 * - money as whole rupiah, plain integers, unquoted and without separators, a negative variance a
 *   negative number with its word in the next field; the count of Sales a plain integer;
 * - times `YYYY-MM-DD HH:MM` in Asia/Jakarta, with the time-zone line;
 * - every text field quoted with `"`, an inner `"` doubled, and one that begins with `=`, `+`, `-`,
 *   `@`, a tab or a carriage return prefixed with an apostrophe, so that no spreadsheet reads it as a
 *   formula.
 *
 * @phpstan-import-type Report from SessionReportDocuments
 * @phpstan-import-type LocalTimes from SessionReportDocuments
 */
final class SessionReportCsv
{
    public const string BOM = "\u{FEFF}";

    public const string SEPARATOR = ';';

    public const string LINE_END = "\r\n";

    /** The first characters that a spreadsheet may read as a formula or a control (BR-CASH-22). */
    private const array DANGEROUS_FIRST = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * The file name of DC-03: `laporan-sesi-<number>.csv`, every character of the number other than a
     * letter, a digit or a hyphen replaced by a hyphen.
     */
    public static function fileName(string $number): string
    {
        return 'laporan-sesi-'.(string) preg_replace('/[^A-Za-z0-9-]/', '-', $number).'.csv';
    }

    /**
     * The file's bytes.
     *
     * @param  Report  $report
     * @param  LocalTimes  $local  the times in Asia/Jakarta, `YYYY-MM-DD HH:MM`
     */
    public static function render(array $report, array $local): string
    {
        if (Templates::sessionReport($report['template']) !== Templates::SESSION_REPORT_1) {
            throw new LogicException('Only session_report.1 is rendered here (ARC-45).');
        }

        if (count($local['refundTimes']) !== count($report['drawerRefundRows'])) {
            throw new LogicException('Each drawer-funded refund of the CSV has its time in Asia/Jakarta (DC-03).');
        }

        foreach ([$local['openedAt'], $local['cutoffAt'], $local['closedAt'], ...$local['refundTimes']] as $time) {
            if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$/D', $time) !== 1) {
                throw new LogicException('A time of the CSV is YYYY-MM-DD HH:MM in Asia/Jakarta (DC-03).');
            }
        }

        $variance = Money::of($report['variance']);
        $lines = [
            [self::text(self::label(__('documents.session_report.title'))), self::text($report['number'])],
            [self::text(self::label(__('documents.session_report.register'))), self::text(self::register($report['registerCode'], $report['registerLabel']))],
            [self::text(self::label(__('documents.session_report.responsible'))), self::text($report['responsible'])],
            [self::text(self::label(__('documents.session_report.opened_at'))), self::text($local['openedAt'])],
            [self::text(self::label(__('documents.session_report.cutoff'))), self::text($local['cutoffAt'])],
            [self::text(self::label(__('documents.session_report.closed_at'))), self::text($local['closedAt'])],
            [self::text(self::money(__('documents.session_report.opening_cash'))), self::integer($report['openingCash'])],
            [self::text(self::label(__('documents.session_report.sale_count'))), (string) $report['saleCount']],
            [self::text(self::money(__('documents.session_report.sale_value'))), self::integer($report['saleValue'])],
            [self::text(self::money(__('documents.session_report.cash_payments'))), self::integer($report['cashPayments'])],
            [self::text(self::money(__('documents.session_report.transfer_payments'))), self::integer($report['transferPayments'])],
            [self::text(self::money(__('documents.session_report.drawer_refunds'))), self::integer($report['drawerRefunds'])],
            [self::text(self::money(__('documents.session_report.expected_cash'))), self::integer($report['expectedCash'])],
            [self::text(self::money(__('documents.session_report.physical_cash'))), self::integer($report['physicalCash'])],
            [self::text(self::money(__('documents.session_report.variance'))), self::integer($report['variance']), self::text(self::label(match (true) {
                $variance->isNegative() => __('ux.format.variance_short'),
                $variance->isPositive() => __('ux.format.variance_over'),
                default => __('ux.format.variance_even'),
            }))],
            [self::text(self::label(__('documents.session_report.closed_by'))), self::text($report['closedBy'])],
        ];

        if ($report['closeReason'] !== null) {
            $lines[] = [self::text(self::label(__('documents.session_report.close_reason'))), self::text($report['closeReason'])];
        }

        if ($report['notes'] !== null) {
            $lines[] = [self::text(self::label(__('documents.session_report.notes'))), self::text($report['notes'])];
        }

        $lines[] = [self::text(self::label(__('documents.session_report.time_zone')))];

        // Then an empty line and the drawer-funded refunds as a table with its header line, also without
        // a row (review R-01). Section 17.2 has no keys for these headers; the keys of exactly these words
        // are used, a reading for the next change control of COPY_DECK (evidence F-1). One line per
        // refund, in the order the report froze them: the Sale's number, the payout's reference, the
        // amount, the time in Asia/Jakarta and the Owner's name (TASK-034).
        $lines[] = [];
        $lines[] = [
            self::text(self::label(__('sales.list.title'))),
            self::text(self::label(__('sales.detail.reference'))),
            self::text(self::money(__('sales.detail.quantity'))),
            self::text(self::label(__('sales.list.col_time'))),
            self::text(self::label(__('inventory.history.col_actor'))),
        ];

        foreach ($report['drawerRefundRows'] as $index => $refund) {
            $time = $local['refundTimes'][$index] ?? throw new LogicException('Each drawer-funded refund of the CSV has its time (DC-03).');
            $lines[] = [self::text($refund['saleNumber']), self::text($refund['reference']), self::integer($refund['amount']), self::text($time), self::text($refund['owner'])];
        }

        return self::BOM.implode('', array_map(static fn (array $fields): string => implode(self::SEPARATOR, $fields).self::LINE_END, $lines));
    }

    /**
     * A text field: quoted, an inner quote doubled, a dangerous first character neutralized by a
     * leading apostrophe (BR-CASH-22, SEC-CH-07).
     */
    public static function text(string $value): string
    {
        $safe = $value !== '' && in_array($value[0], self::DANGEROUS_FIRST, true) ? "'".$value : $value;

        return '"'.str_replace('"', '""', $safe).'"';
    }

    /**
     * A money field: the canonical whole rupiah, a plain integer, negative with a minus.
     */
    private static function integer(string $amount): string
    {
        $canonical = Money::of($amount)->toCanonical();

        return preg_match('/^-?[0-9]+$/D', $canonical) === 1 ? $canonical : throw new LogicException('Money is whole rupiah (ADR-006).');
    }

    /** A label of the money columns, which keep rupiah: ":kolom (Rp)". */
    private static function money(mixed $label): string
    {
        return self::label(__('documents.session_report.money_header', ['kolom' => self::label($label)]));
    }

    private static function label(mixed $text): string
    {
        return is_string($text) ? $text : throw new LogicException('A label of the CSV is a text of lang/id.');
    }

    /** The register as the session pages name it: its code, and its label when it differs. */
    private static function register(string $code, string $label): string
    {
        return $code === $label ? $code : $code.' · '.$label;
    }
}
