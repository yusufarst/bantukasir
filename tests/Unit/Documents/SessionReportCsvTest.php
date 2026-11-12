<?php

namespace Tests\Unit\Documents;

use App\Domain\Documents\SessionReports\SessionReportCsv;
use LogicException;
use Tests\TestCase;

/**
 * The CSV dialect of a session report, with no database (AC-031-03; DOCUMENT_CONTRACTS DC-03; BR-CASH-22;
 * SECURITY_MODEL SEC-CH-07; suite SESSION08; TASK-031 section 7, L1): the byte-order mark, semicolons and
 * CRLF; labels of COPY_DECK section 17.2, money labels with " (Rp)"; plain integers, a negative variance
 * negative with its word; every text quoted, inner quotes doubled, formula and control prefixes neutralized;
 * the reason and notes only when present; the empty line and the refunds' table header; the file name; and
 * only the template version it knows.
 */
final class SessionReportCsvTest extends TestCase
{
    public function test_every_text_is_quoted_and_a_formula_or_control_prefix_is_neutralized(): void
    {
        foreach ([
            'Rina' => '"Rina"',
            'Kasir "pagi"' => '"Kasir ""pagi"""',
            '=SUM(A1:A2)' => '"\'=SUM(A1:A2)"',
            '+62 811' => '"\'+62 811"',
            '-1500' => '"\'-1500"',
            '@cmd' => '"\'@cmd"',
            "\tTab" => "\"'\tTab\"",
            "\rCR" => "\"'\rCR\"",
            'a=b' => '"a=b"',
            '' => '""',
        ] as $value => $field) {
            $this->assertSame($field, SessionReportCsv::text((string) $value), json_encode($value, JSON_THROW_ON_ERROR));
        }
    }

    public function test_the_file_holds_the_fields_of_dc_02_in_order_with_a_variance_numeric_and_its_word(): void
    {
        $file = SessionReportCsv::render(self::report(['variance' => '2500', 'physicalCash' => '122500', 'closeReason' => 'Kasir sakit', 'notes' => "Baris 1\nBaris 2"]), self::local());

        $this->assertStringStartsWith("\u{FEFF}\"Laporan sesi kas\";\"20261112-1\"\r\n", $file);
        // Then an empty line and the header of the drawer-funded refunds' table, also without a row (review R-01).
        $this->assertStringEndsWith("\"Zona waktu: WIB (Asia/Jakarta)\"\r\n\r\n\"Penjualan\";\"Referensi\";\"Jumlah (Rp)\";\"Waktu\";\"Oleh\"\r\n", $file);
        $lines = explode("\r\n", substr($file, strlen(SessionReportCsv::BOM), -2));
        $this->assertSame([
            '"Laporan sesi kas";"20261112-1"',
            '"Laci kas";"LACI-1 · Laci 1"',
            '"Penanggung jawab";"Rina"',
            '"Dibuka";"2026-11-12 08:00"',
            '"Dihitung sampai";"2026-11-12 17:30"',
            '"Ditutup";"2026-11-12 17:30"',
            '"Modal awal (Rp)";20000',
            '"Jumlah penjualan";2',
            '"Nilai penjualan (Rp)";150000',
            '"Pembayaran tunai (Rp)";100000',
            '"Pembayaran transfer bank (Rp)";50000',
            '"Pengembalian dari laci kas (Rp)";0',
            '"Uang seharusnya di laci (Rp)";120000',
            '"Uang fisik di laci (Rp)";122500',
            '"Selisih (Rp)";2500;"lebih"',
            '"Ditutup oleh";"Hendra"',
            '"Alasan penutupan";"Kasir sakit"',
            "\"Catatan\";\"Baris 1\nBaris 2\"",
            '"Zona waktu: WIB (Asia/Jakarta)"',
            '',
            '"Penjualan";"Referensi";"Jumlah (Rp)";"Waktu";"Oleh"',
        ], $lines);

        // A negative variance stays a negative number; an even one is "sesuai"; no reason, no notes.
        $short = SessionReportCsv::render(self::report(['variance' => '-1500', 'physicalCash' => '118500']), self::local());
        $this->assertStringContainsString("\r\n\"Selisih (Rp)\";-1500;\"kurang\"\r\n", $short);
        $this->assertStringNotContainsString('Alasan penutupan', $short);
        $this->assertStringNotContainsString('Catatan', $short);
        $this->assertStringContainsString("\r\n\"Selisih (Rp)\";0;\"sesuai\"\r\n", SessionReportCsv::render(self::report([]), self::local()));
    }

    public function test_the_file_name_keeps_letters_digits_and_hyphens_only(): void
    {
        $this->assertSame('laporan-sesi-20261112-1.csv', SessionReportCsv::fileName('20261112-1'));
        $this->assertSame('laporan-sesi-a-b-c-.csv', SessionReportCsv::fileName('a/b c"'));
    }

    public function test_a_template_or_a_time_it_does_not_know_is_refused(): void
    {
        $this->assertThrows(fn () => SessionReportCsv::render(self::report(['template' => 'session_report.2']), self::local()), LogicException::class);
        $this->assertThrows(fn () => SessionReportCsv::render(self::report([]), [...self::local(), 'closedAt' => '2026-11-12T10:30:00Z']), LogicException::class);
    }

    /**
     * A report of DC-02 as SessionReportDocuments reads one, with these fields changed.
     *
     * @param  array<string, mixed>  $changes
     * @return array{sessionId: int, number: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, cutoffAt: string, closedAt: string, openingCash: string, saleCount: int, saleValue: string, cashPayments: string, transferPayments: string, drawerRefunds: string, expectedCash: string, physicalCash: string, variance: string, closedBy: string, closeReason: string|null, notes: string|null, template: string}
     */
    private static function report(array $changes): array
    {
        /** @var array{sessionId: int, number: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, cutoffAt: string, closedAt: string, openingCash: string, saleCount: int, saleValue: string, cashPayments: string, transferPayments: string, drawerRefunds: string, expectedCash: string, physicalCash: string, variance: string, closedBy: string, closeReason: string|null, notes: string|null, template: string} $report */
        $report = [
            'sessionId' => 1,
            'number' => '20261112-1',
            'registerCode' => 'LACI-1',
            'registerLabel' => 'Laci 1',
            'responsible' => 'Rina',
            'openedAt' => '2026-11-12T01:00:00.000000Z',
            'cutoffAt' => '2026-11-12T10:30:00.000000Z',
            'closedAt' => '2026-11-12T10:30:00.000000Z',
            'openingCash' => '20000',
            'saleCount' => 2,
            'saleValue' => '150000',
            'cashPayments' => '100000',
            'transferPayments' => '50000',
            'drawerRefunds' => '0',
            'expectedCash' => '120000',
            'physicalCash' => '120000',
            'variance' => '0',
            'closedBy' => 'Hendra',
            'closeReason' => null,
            'notes' => null,
            'template' => 'session_report.1',
            ...$changes,
        ];

        return $report;
    }

    /**
     * @return array{openedAt: string, cutoffAt: string, closedAt: string}
     */
    private static function local(): array
    {
        return ['openedAt' => '2026-11-12 08:00', 'cutoffAt' => '2026-11-12 17:30', 'closedAt' => '2026-11-12 17:30'];
    }
}
