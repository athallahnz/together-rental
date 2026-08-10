<?php

namespace App\Domain\Reporting;

class SimplePdfExporter
{
    private const MAX_LINE_LENGTH = 132;

    /**
     * @param array{
     *     title: string,
     *     subtitle: string,
     *     summary: list<array{label: string, value: int|float, type: string, note: string}>,
     *     columns: list<array{key: string, label: string, type: string, export_width: int}>,
     *     rows: list<array{id: string, href: string, values: array<string, mixed>}>
     * } $document
     */
    public function render(array $document): string
    {
        $widths = $this->fitWidths($document['columns']);
        $tableHeader = $this->tableLine($document['columns'], $widths, null, true);
        $separator = str_repeat('-', min(self::MAX_LINE_LENGTH, strlen($tableHeader)));
        $tableRows = array_map(
            fn (array $row): string => $this->tableLine($document['columns'], $widths, $row['values']),
            $document['rows'],
        );
        $pages = [];
        $page = [
            (string) $document['title'],
            (string) $document['subtitle'],
            '',
        ];

        foreach ($document['summary'] as $card) {
            $page[] = sprintf(
                '%-24s %18s  %s',
                $this->ascii((string) $card['label']),
                $this->summaryValue($card['value'], (string) $card['type']),
                $this->ascii((string) $card['note']),
            );
        }

        $page[] = '';
        $page[] = $tableHeader;
        $page[] = $separator;

        foreach ($tableRows as $line) {
            if (count($page) >= 45) {
                $pages[] = $page;
                $page = [
                    (string) $document['title'],
                    (string) $document['subtitle'],
                    '',
                    $tableHeader,
                    $separator,
                ];
            }
            $page[] = $line;
        }

        if ($tableRows === []) {
            $page[] = 'Tidak ada data untuk filter yang dipilih.';
        }

        $pages[] = $page;

        return $this->buildPdf($pages);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @return list<int>
     */
    private function fitWidths(array $columns): array
    {
        $widths = array_map(static fn (array $column): int => max(6, (int) $column['export_width']), $columns);

        if ($widths === []) {
            return [];
        }

        $total = array_sum($widths) + max(count($widths) - 1, 0) * 3;

        while ($total > self::MAX_LINE_LENGTH) {
            $index = array_search(max($widths), $widths, true);
            if ($index === false || $widths[$index] <= 7) {
                break;
            }
            $widths[$index]--;
            $total--;
        }

        return $widths;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<int>  $widths
     * @param  array<string, mixed>|null  $values
     */
    private function tableLine(array $columns, array $widths, ?array $values, bool $header = false): string
    {
        $parts = [];
        foreach ($columns as $index => $column) {
            $value = $header
                ? (string) $column['label']
                : $this->displayValue($values[(string) $column['key']] ?? null, (string) $column['type']);
            $value = $this->truncate($this->ascii($value), $widths[$index]);
            $parts[] = str_pad($value, $widths[$index]);
        }

        return implode(' | ', $parts);
    }

    private function displayValue(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return match ($type) {
            'money' => 'Rp '.number_format((float) $value, 0, ',', '.'),
            'number' => number_format((float) $value, 0, ',', '.'),
            'direction' => match ((string) $value) {
                'in' => 'Masuk',
                'out' => 'Keluar',
                'increase' => 'Penambah',
                'decrease' => 'Pengurang',
                default => (string) $value,
            },
            default => (string) $value,
        };
    }

    private function summaryValue(mixed $value, string $type): string
    {
        return $type === 'money'
            ? 'Rp '.number_format((float) $value, 0, ',', '.')
            : number_format((float) $value, 0, ',', '.');
    }

    private function truncate(string $value, int $width): string
    {
        return strlen($value) <= $width
            ? $value
            : substr($value, 0, max($width - 1, 1)).'~';
    }

    private function ascii(string $value): string
    {
        $value = str_replace(['–', '—', '·', '“', '”', '’'], ['-', '-', '-', '"', '"', "'"], $value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return preg_replace('/[^\x20-\x7E]/', '', $ascii === false ? $value : $ascii) ?? '';
    }

    /** @param list<list<string>> $pages */
    private function buildPdf(array $pages): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
        ];
        $pageReferences = [];

        foreach ($pages as $index => $lines) {
            $pageObject = 4 + ($index * 2);
            $contentObject = $pageObject + 1;
            $pageReferences[] = $pageObject.' 0 R';
            $stream = $this->pageStream($lines, $index + 1, count($pages));
            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>',
                $contentObject,
            );
            $objects[$contentObject] = "<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }

        $objects[2] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', $pageReferences),
            count($pages),
        );
        ksort($objects);

        $pdf = "%PDF-1.4\n%Together Kamera Reporting Center\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($number = 1; $number < $size; $number++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$number] ?? 0)."\n";
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /** @param list<string> $lines */
    private function pageStream(array $lines, int $page, int $totalPages): string
    {
        $lines[] = '';
        $lines[] = "Halaman {$page} dari {$totalPages}";
        $commands = [
            'BT',
            '/F1 7 Tf',
            '10 TL',
            '28 566 Td',
        ];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $commands[] = 'T*';
            }
            $commands[] = '('.$this->escapePdf($this->ascii($line)).') Tj';
        }
        $commands[] = 'ET';

        return implode("\n", $commands);
    }

    private function escapePdf(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
