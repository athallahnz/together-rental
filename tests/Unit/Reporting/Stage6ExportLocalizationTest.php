<?php

namespace Tests\Unit\Reporting;

use App\Domain\Reporting\SimplePdfExporter;
use App\Domain\Reporting\SpreadsheetXmlExporter;
use App\Domain\Reporting\Stage6Presentation;
use Tests\TestCase;

class Stage6ExportLocalizationTest extends TestCase
{
    public function test_excel_language_changes_labels_but_keeps_numeric_cells(): void
    {
        $document = $this->document();
        $exporter = new SpreadsheetXmlExporter;
        $id = $exporter->render($document, 'id');
        $en = $exporter->render($document, 'en');

        self::assertStringContainsString('ss:Name="Laporan"', $id);
        self::assertStringContainsString('ss:Name="Report"', $en);
        self::assertStringContainsString('ss:Type="Number">500000', $id);
        self::assertStringContainsString('ss:Type="Number">500000', $en);
        self::assertStringContainsString('ss:Type="String">Confirmed', $en);
        self::assertStringContainsString('ss:Type="String">Dikonfirmasi', $id);
        self::assertStringNotContainsString('ss:Type="String">confirmed', $id);
    }

    public function test_pdf_language_changes_page_labels_and_keeps_amount(): void
    {
        $document = $this->document();
        $exporter = new SimplePdfExporter('/nonexistent-stage6-logo.png');
        $id = $exporter->render($document, 'id');
        $en = $exporter->render($document, 'en');

        self::assertStringContainsString('HALAMAN 1 / 1', $id);
        self::assertStringContainsString('PAGE 1 / 1', $en);
        self::assertStringContainsString('PERIOD SUMMARY', $en);
        self::assertStringContainsString('Rp 500.000', $id);
        self::assertStringContainsString('Rp 500.000', $en);
        self::assertStringContainsString('PUSAT LAPORAN  /  EKSPOR PDF', $id);
        self::assertStringContainsString('REPORTING CENTER  /  PDF EXPORT', $en);
        self::assertStringContainsString('1 ROW', $en);
        self::assertStringContainsString('INTEGRATED REPORT', $en);
        self::assertStringContainsString('Dikonfirmasi', $id);
        self::assertStringNotContainsString('LANJUTAN', $en);
    }

    public function test_canonical_statuses_have_localized_labels_without_changing_keys(): void
    {
        foreach (['completed' => ['Selesai', 'Completed'], 'cancelled' => ['Dibatalkan', 'Cancelled'], 'converted' => ['Dikonversi', 'Converted'], 'lost' => ['Hilang', 'Lost'], 'good' => ['Baik', 'Good'], 'maintenance' => ['Pemeliharaan', 'Maintenance']] as $key => [$id, $en]) {
            self::assertSame($id, Stage6Presentation::status($key, 'id'));
            self::assertSame($en, Stage6Presentation::status($key, 'en'));
        }
    }

    public function test_indonesian_report_terms_are_localized_at_presentation_boundary(): void
    {
        $previous = app()->getLocale();
        app()->setLocale('id');
        try {
            $presentation = new Stage6Presentation;
            self::assertSame('Pemesanan', $presentation->text('Booking'));
            self::assertSame('Penyewaan', $presentation->text('Rental'));
            self::assertSame('Pengembalian', $presentation->text('Return'));
            self::assertSame('Pembayaran masuk', $presentation->text('Payment masuk'));
            self::assertSame('5 pengembalian dana dibayarkan.', $presentation->text('5 refund dibayarkan.'));
        } finally {
            app()->setLocale($previous);
        }
    }

    public function test_pdf_continuation_and_row_count_use_english_on_every_page(): void
    {
        $document = $this->document();
        $document['rows'] = array_fill(0, 80, $document['rows'][0]);
        $pdf = (new SimplePdfExporter('/nonexistent-stage6-logo.png'))->render($document, 'en');

        self::assertStringContainsString('CONTINUED', $pdf);
        self::assertStringContainsString('80 ROWS', $pdf);
        self::assertStringNotContainsString('LANJUTAN', $pdf);
        self::assertStringNotContainsString('80 BARIS', $pdf);
    }

    public function test_indonesian_operational_pdf_keeps_type_and_status_as_whole_words(): void
    {
        $columns = [
            ['key' => 'kind', 'label' => 'Jenis', 'type' => 'text', 'export_width' => 10],
            ['key' => 'number', 'label' => 'Nomor', 'type' => 'text', 'export_width' => 17],
            ['key' => 'reference', 'label' => 'Referensi', 'type' => 'text', 'export_width' => 16],
            ['key' => 'customer', 'label' => 'Pelanggan', 'type' => 'text', 'export_width' => 18],
            ['key' => 'branch', 'label' => 'Cabang', 'type' => 'text', 'export_width' => 10],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status', 'export_width' => 11],
            ['key' => 'occurred_at', 'label' => 'Waktu Transaksi', 'type' => 'datetime', 'export_width' => 17],
            ['key' => 'starts_at', 'label' => 'Mulai/Tempo', 'type' => 'datetime', 'export_width' => 16],
            ['key' => 'ends_at', 'label' => 'Selesai/Kembali', 'type' => 'datetime', 'export_width' => 16],
            ['key' => 'total_amount', 'label' => 'Nilai', 'type' => 'money', 'export_width' => 14],
            ['key' => 'charges', 'label' => 'Denda/Biaya', 'type' => 'money', 'export_width' => 13],
            ['key' => 'paid_amount', 'label' => 'Terbayar', 'type' => 'money', 'export_width' => 13],
            ['key' => 'balance_due', 'label' => 'Saldo', 'type' => 'money', 'export_width' => 13],
        ];
        $document = [
            'title' => 'Visual check',
            'subtitle' => 'Periode 01-09-2026 s.d. 27-09-2026',
            'summary' => [],
            'columns' => $columns,
            'rows' => [['id' => 'return-1', 'href' => '/returns/1', 'values' => [
                'kind' => 'Pengembalian', 'status' => 'confirmed', 'total_amount' => 500000,
            ]]],
        ];

        $pdf = (new SimplePdfExporter('/nonexistent-stage6-logo.png'))->render($document, 'id');

        self::assertStringContainsString('(Pengembalian) Tj', $pdf);
        self::assertStringContainsString('(Dikonfirmasi) Tj', $pdf);
        self::assertStringContainsString('(Rp 500.000) Tj', $pdf);
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        return [
            'title' => 'Together Kamera · Report',
            'subtitle' => 'Period 2026-08-01 to 2026-08-09',
            'summary' => [['label' => 'Rental value', 'value' => 500000, 'type' => 'money', 'note' => 'Test']],
            'columns' => [
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'export_width' => 15],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status', 'export_width' => 12],
            ],
            'rows' => [['id' => 'test', 'href' => '/rentals/1', 'values' => ['amount' => 500000, 'status' => 'confirmed']]],
        ];
    }
}
