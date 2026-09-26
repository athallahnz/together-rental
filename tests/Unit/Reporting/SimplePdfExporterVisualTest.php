<?php

namespace Tests\Unit\Reporting;

use App\Domain\Reporting\SimplePdfExporter;
use PHPUnit\Framework\TestCase;

class SimplePdfExporterVisualTest extends TestCase
{
    public function test_standard_report_keeps_summary_values_and_uses_a4_landscape(): void
    {
        $pdf = (new SimplePdfExporter)->render($this->document(
            columns: [
                $this->column('number', 'Nomor', 'text', 17),
                $this->column('customer', 'Pelanggan', 'text', 20),
                $this->column('amount', 'Nilai', 'money', 15),
            ],
            rows: [
                $this->row('RNT-001', 'Nama Pelanggan UAT', 125000),
            ],
        ));

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringContainsString('/MediaBox [0 0 842 595]', $pdf);
        self::assertStringContainsString('RNT-001', $pdf);
        self::assertStringContainsString('Rp 125.000', $pdf);
        self::assertStringContainsString('HALAMAN 1 / 1', $pdf);
        self::assertStringContainsString('TOGETHER', $pdf);
    }

    public function test_wide_and_long_report_uses_a3_and_repeats_headers_on_next_pages(): void
    {
        $columns = [
            $this->column('number', 'Nomor', 'text', 17),
            $this->column('customer', 'Pelanggan', 'text', 20),
            $this->column('amount', 'Nilai', 'money', 15),
        ];
        for ($index = 1; $index <= 10; $index++) {
            $columns[] = $this->column('field_'.$index, 'Field '.$index, 'text', 13);
        }
        $rows = [];
        for ($index = 1; $index <= 110; $index++) {
            $rows[] = $this->row('RNT-'.sprintf('%03d', $index), 'Pelanggan UAT '.str_repeat('Long text ', 4), 125000);
        }
        $pdf = (new SimplePdfExporter)->render($this->document($columns, $rows));

        self::assertStringContainsString('/MediaBox [0 0 1191 842]', $pdf);
        self::assertGreaterThan(2, substr_count($pdf, '/Type /Page /Parent'));
        self::assertStringContainsString('RNT-110', $pdf);
        self::assertGreaterThan(1, substr_count($pdf, 'RINCIAN TRANSAKSI'));
    }

    public function test_empty_report_has_explicit_empty_state_and_safe_pdf_strings(): void
    {
        $document = $this->document(
            [$this->column('customer', 'Pelanggan', 'text', 20)],
            [],
        );
        $document['title'] = 'Together Kamera · Laporan (UAT)';
        $pdf = (new SimplePdfExporter)->render($document);

        self::assertStringContainsString('Tidak ada data untuk filter yang dipilih.', $pdf);
        self::assertStringContainsString('Laporan \\(UAT\\)', $pdf);
        self::assertSame(1, substr_count($pdf, '/Type /Page /Parent'));
    }

    public function test_project_png_logo_is_reused_on_all_pdf_pages(): void
    {
        $logo = dirname(__DIR__, 3).'/public/primary-logos.png';
        self::assertFileExists($logo);
        $columns = [$this->column('number', 'Nomor', 'text', 17)];
        $rows = [];
        for ($index = 1; $index <= 70; $index++) {
            $rows[] = $this->row('RNT-'.sprintf('%03d', $index), 'Pelanggan', 125000);
        }

        $pdf = (new SimplePdfExporter($logo))->render($this->document($columns, $rows));
        $pageCount = substr_count($pdf, '/Type /Page /Parent');
        self::assertGreaterThan(1, $pageCount);
        self::assertSame(1, substr_count($pdf, '/Subtype /Image'));
        self::assertStringContainsString('/Width 500 /Height 500', $pdf);
        self::assertSame($pageCount, substr_count($pdf, '/Logo Do'));
        self::assertSame($pageCount, substr_count($pdf, '/XObject << /Logo '));
        self::assertStringContainsString('TOGETHER KAMERA', $pdf);
    }

    public function test_normal_rows_remain_together_when_a_page_boundary_is_reached(): void
    {
        // Two-line cells at the end of a summary-heavy page previously left
        // the second line of an asset code orphaned on the next page.
        $columns = [
            $this->column('code', 'Kode Aset', 'text', 13),
            $this->column('product', 'Produk', 'text', 21),
            $this->column('category', 'Kategori', 'text', 15),
            $this->column('branch', 'Cabang', 'text', 9),
            $this->column('status', 'Status', 'status', 9),
            $this->column('condition', 'Kondisi', 'status', 9),
            $this->column('price', 'Harga Beli', 'money', 15),
            $this->column('count', 'Jml. Service', 'number', 10),
            $this->column('cost', 'Biaya Service', 'money', 13),
            $this->column('last', 'Service Terakhir', 'text', 17),
        ];
        $rows = [];
        for ($index = 1; $index <= 12; $index++) {
            $rows[] = [
                'id' => (string) $index,
                'href' => '/assets/'.$index,
                'values' => [
                    'code' => 'FIRSTTOKEN'.sprintf('%02d', $index).' SECONDTOKEN'.sprintf('%02d', $index),
                    'product' => 'PRODUCTTOKEN'.sprintf('%02d', $index),
                    'category' => 'UAT Camera Equipment',
                    'branch' => 'PNG',
                    'status' => 'available',
                    'condition' => 'good',
                    'price' => 3500000,
                    'count' => 0,
                    'cost' => 0,
                    'last' => '2026-09-23 15:24:18',
                ],
            ];
        }
        $document = $this->document($columns, $rows);
        $document['summary'] = array_merge($document['summary'], $document['summary'], $document['summary'], $document['summary']);
        $pdf = (new SimplePdfExporter('/nonexistent/logo.png'))->render($document);
        $streams = $this->pageStreams($pdf);
        self::assertGreaterThan(1, count($streams));

        foreach (range(1, 12) as $index) {
            $first = [];
            $second = [];
            $product = [];
            foreach ($streams as $page => $stream) {
                if (str_contains($stream, 'FIRSTTOKEN'.sprintf('%02d', $index))) {
                    $first[] = $page;
                }
                if (str_contains($stream, 'SECONDTOKEN'.sprintf('%02d', $index))) {
                    $second[] = $page;
                }
                if (str_contains($stream, 'PRODUCTTOKEN'.sprintf('%02d', $index))) {
                    $product[] = $page;
                }
            }
            self::assertCount(1, $first);
            self::assertSame($first, $second, 'Asset code fragments must stay on the same page for row '.$index);
            self::assertSame($first, $product, 'Asset code and its product must stay on the same page for row '.$index);
        }
    }

    public function test_oversized_single_row_can_span_pages_without_infinite_pagination(): void
    {
        $document = $this->document(
            [$this->column('customer', 'Catatan', 'text', 15)],
            [[
                'id' => '1',
                'href' => '/assets/1',
                'values' => ['customer' => str_repeat('Rincian perbaikan panjang ', 500).'END-MARKER'],
            ]],
        );
        $pdf = (new SimplePdfExporter('/nonexistent/logo.png'))->render($document);
        self::assertGreaterThan(1, count($this->pageStreams($pdf)));
        self::assertStringContainsString('END-MARKER', $pdf);
    }

    public function test_missing_logo_uses_a_valid_text_only_header(): void
    {
        $pdf = (new SimplePdfExporter('/nonexistent/logo.png'))->render($this->document([], []));
        self::assertStringContainsString('TOGETHER KAMERA', $pdf);
        self::assertSame(0, substr_count($pdf, '/Subtype /Image'));
        self::assertSame(1, substr_count($pdf, '/Type /Page /Parent'));
    }

    /** @return list<string> */
    private function pageStreams(string $pdf): array
    {
        preg_match_all('/\d+ 0 obj\n<< \/Length \d+ >>\nstream\n(.*?)\nendstream\nendobj/s', $pdf, $matches);

        return $matches[1];
    }

    /**
     * @param  list<array{key: string, label: string, type: string, export_width: int}>  $columns
     * @param  list<array{id: string, href: string, values: array<string, mixed>}>  $rows
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     summary: list<array{label: string, value: int|float, type: string, note: string}>,
     *     columns: list<array{key: string, label: string, type: string, export_width: int}>,
     *     rows: list<array{id: string, href: string, values: array<string, mixed>}>
     * }
     */
    private function document(array $columns, array $rows): array
    {
        return [
            'title' => 'Together Kamera · Booking, Rental & Return',
            'subtitle' => 'Periode 01-09-2026 s.d. 25-09-2026 · PNG · Dibuat 25-09-2026 09:25 WIB',
            'summary' => [
                ['label' => 'Rental tercatat', 'value' => 1, 'type' => 'number', 'note' => 'Rental valid pada periode.'],
                ['label' => 'Nilai rental', 'value' => 125000, 'type' => 'money', 'note' => 'Total nilai rental.'],
            ],
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /** @return array{key: string, label: string, type: string, export_width: int} */
    private function column(string $key, string $label, string $type, int $width): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'export_width' => $width];
    }

    /** @return array{id: string, href: string, values: array<string, mixed>} */
    private function row(string $number, string $customer, int $amount): array
    {
        return [
            'id' => $number,
            'href' => '/rentals/1',
            'values' => [
                'number' => $number,
                'customer' => $customer,
                'amount' => $amount,
            ],
        ];
    }
}
