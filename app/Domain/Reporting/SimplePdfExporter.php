<?php

namespace App\Domain\Reporting;

/**
 * Dependency-free, vector PDF renderer for the integrated reporting export.
 * The controller and report service continue to own all filtering and totals.
 *
 * @phpstan-type PdfSummaryCard array{label: string, value: int|float, type: string, note: string}
 * @phpstan-type PdfColumn array{key: string, label: string, type: string, export_width: int}
 * @phpstan-type PdfRow array{id: string, href: string, values: array<string, mixed>}
 * @phpstan-type PdfDocument array{
 *     title: string, subtitle: string, summary: list<PdfSummaryCard>,
 *     columns: list<PdfColumn>, rows: list<PdfRow>
 * }
 */
class SimplePdfExporter
{
    private const A4_LANDSCAPE = [842, 595];

    private const A3_LANDSCAPE = [1191, 842];

    private const MARGIN = 32.0;

    private const FOOTER_RULE_Y = 42.0;

    private const CELL_PADDING = 5.0;

    private bool $logoAttempted = false;

    private ?string $logoImage = null;

    /**
     * By default the renderer reads the existing project logo at public/primary-logos.png.
     * Supplying a path is useful for isolated unit tests; no GD or PDF package is required.
     */
    public function __construct(private readonly ?string $logoPath = null) {}

    /** @param PdfDocument $document */
    public function render(array $document): string
    {
        // Wide datasets such as operational and stock-opname reports remain legible on A3.
        [$pageWidth, $pageHeight] = count($document['columns']) >= 11
            ? self::A3_LANDSCAPE
            : self::A4_LANDSCAPE;
        $widths = $this->columnWidths($document['columns'], $pageWidth - 2 * self::MARGIN);
        $pages = [];
        $stream = '';
        $cursor = 0.0;
        $this->startPage($pages, $stream, $cursor, $document, $widths, $pageWidth, $pageHeight, true);

        // Measure a continuation page once. A normal table row must move as one unit
        // whenever it can fit on a fresh page. Only an exceptionally tall row may split.
        $probePages = [];
        $probeStream = '';
        $probeCursor = 0.0;
        $this->startPage($probePages, $probeStream, $probeCursor, $document, $widths, $pageWidth, $pageHeight, false);
        $continuationCapacity = $probeCursor - (self::FOOTER_RULE_Y + 15);

        if ($document['rows'] === []) {
            $this->rect($stream, self::MARGIN, $cursor - 56, $pageWidth - 2 * self::MARGIN, 50, '#F1F5F9');
            $this->text($stream, 'Tidak ada data untuk filter yang dipilih.', self::MARGIN + 17, $cursor - 33, 10, '#64748B');
        }

        $cellFontSize = $pageWidth > 900 ? 8.0 : 7.2;
        $lineHeight = $pageWidth > 900 ? 12.0 : 10.8;
        foreach ($document['rows'] as $index => $row) {
            $cells = [];
            $maxLines = 1;
            foreach ($document['columns'] as $columnIndex => $column) {
                $value = $this->displayValue($row['values'][(string) $column['key']] ?? null, (string) $column['type']);
                $lines = $this->wrap($value, $widths[$columnIndex] - 2 * self::CELL_PADDING, $cellFontSize);
                $cells[] = $lines;
                $maxLines = max($maxLines, count($lines));
            }

            $rowHeight = $maxLines * $lineHeight + 12;
            $remainingCapacity = $cursor - (self::FOOTER_RULE_Y + 15);
            if ($rowHeight > $remainingCapacity && $remainingCapacity < $continuationCapacity - 0.1) {
                $this->startPage($pages, $stream, $cursor, $document, $widths, $pageWidth, $pageHeight, false);
            }

            $offset = 0;
            while ($offset < $maxLines) {
                $available = $cursor - (self::FOOTER_RULE_Y + 15);
                $lineCapacity = (int) floor(($available - 12) / $lineHeight);
                if ($lineCapacity < 1) {
                    $this->startPage($pages, $stream, $cursor, $document, $widths, $pageWidth, $pageHeight, false);
                    continue;
                }

                $lineCount = min($lineCapacity, $maxLines - $offset);
                $height = $lineCount * $lineHeight + 12;
                $bottom = $cursor - $height;
                $this->rect(
                    $stream,
                    self::MARGIN,
                    $bottom,
                    $pageWidth - 2 * self::MARGIN,
                    $height,
                    $index % 2 === 0 ? '#FFFFFF' : '#F4F7FB',
                );

                $x = self::MARGIN;
                foreach ($document['columns'] as $columnIndex => $column) {
                    $width = $widths[$columnIndex];
                    $type = (string) $column['type'];
                    $rawValue = (string) ($row['values'][(string) $column['key']] ?? '');
                    if ($type === 'status' && $offset === 0) {
                        $this->rect(
                            $stream,
                            $x + 1,
                            $bottom + 5,
                            2.5,
                            max(8.0, $height - 10),
                            $this->statusColor($rawValue),
                        );
                    }
                    if ($columnIndex > 0) {
                        $this->line($stream, $x, $bottom + 3, $x, $cursor - 3, '#E8EDF4', 0.4);
                    }
                    for ($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
                        $line = $cells[$columnIndex][$offset + $lineIndex] ?? null;
                        if ($line === null) {
                            continue;
                        }
                        $numeric = in_array($type, ['money', 'number'], true);
                        $textWidth = strlen($line) * $cellFontSize * 0.6;
                        $textX = $numeric
                            ? $x + $width - self::CELL_PADDING - $textWidth
                            : $x + self::CELL_PADDING;
                        $color = $type === 'status'
                            ? $this->statusColor($rawValue)
                            : ($numeric && (str_starts_with($line, '-') || str_starts_with($line, 'Rp -')) ? '#BE123C' : '#263448');
                        $this->text(
                            $stream,
                            $line,
                            max($x + self::CELL_PADDING, $textX),
                            $cursor - 12 - $lineIndex * $lineHeight,
                            $cellFontSize,
                            $color,
                            'F3',
                        );
                    }
                    $x += $width;
                }
                $this->line($stream, self::MARGIN, $bottom, $pageWidth - self::MARGIN, $bottom, '#E2E8F0', 0.5);
                $cursor = $bottom;
                $offset += $lineCount;

                if ($offset < $maxLines) {
                    $this->startPage($pages, $stream, $cursor, $document, $widths, $pageWidth, $pageHeight, false);
                }
            }
        }

        $pages[] = $stream;

        return $this->buildPdf($pages, $pageWidth, $pageHeight);
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return list<float>
     */
    private function columnWidths(array $columns, float $availableWidth): array
    {
        if ($columns === []) {
            return [];
        }

        $weights = array_map(
            static fn (array $column): int => max(8, (int) $column['export_width']),
            $columns,
        );
        $weightTotal = array_sum($weights);
        $widths = [];
        $assigned = 0.0;
        foreach ($weights as $index => $weight) {
            $width = $index === count($weights) - 1
                ? $availableWidth - $assigned
                : $availableWidth * $weight / $weightTotal;
            $widths[] = $width;
            $assigned += $width;
        }

        return $widths;
    }

    /**
     * @param list<string> $pages
     * @param PdfDocument $document
     * @param list<float> $widths
     */
    private function startPage(
        array &$pages,
        string &$stream,
        float &$cursor,
        array $document,
        array $widths,
        float $pageWidth,
        float $pageHeight,
        bool $first,
    ): void {
        if ($stream !== '') {
            $pages[] = $stream;
        }
        $stream = '';
        $this->rect($stream, 0, 0, $pageWidth, $pageHeight, '#FFFFFF');
        $headerHeight = $first ? 105.0 : 84.0;
        $this->rect($stream, 0, $pageHeight - $headerHeight, $pageWidth, $headerHeight, '#172554');
        $this->rect($stream, 0, $pageHeight - 6, $pageWidth, 6, '#2DD4BF');
        $brandX = self::MARGIN + 11;
        if ($this->logoImage() !== null) {
            $logoSize = $first ? 42.0 : 36.0;
            $logoY = $pageHeight - ($first ? 59.0 : 55.0);
            $this->strokeRect($stream, self::MARGIN, $logoY, $logoSize, $logoSize, '#5EEAD4', 0.8);
            $stream .= sprintf(
                "q %.2F 0 0 %.2F %.2F %.2F cm /Logo Do Q\n",
                $logoSize - 2,
                $logoSize - 2,
                self::MARGIN + 1,
                $logoY + 1,
            );
            $brandX = self::MARGIN + $logoSize + 14;
        } else {
            // Preserve a readable text-only header if the logo is unavailable.
            $this->rect($stream, self::MARGIN, $pageHeight - 39, 3, 18, '#2DD4BF');
        }
        $this->text($stream, 'TOGETHER KAMERA', $brandX, $pageHeight - 29, 13, '#FFFFFF', 'F2');
        $this->text($stream, 'RENTAL OPERATIONS', $brandX, $pageHeight - 43, 7.2, '#B7C7F2', 'F2');
        $this->text(
            $stream,
            'REPORTING CENTER  /  '.($first ? 'EXPORT PDF' : 'LANJUTAN'),
            $pageWidth - self::MARGIN,
            $pageHeight - 28,
            8,
            '#B7C7F2',
            'F2',
            true,
        );
        $reportTitle = trim(preg_replace('/^Together Kamera\s*[·-]\s*/u', '', (string) $document['title']) ?? (string) $document['title']);
        $titleX = $first ? self::MARGIN : $brandX;
        $titleLines = $this->wrap($reportTitle, $pageWidth - self::MARGIN - $titleX - 20, $first ? 19 : 14, 0.58);
        $this->text(
            $stream,
            $titleLines[0] ?? 'Laporan',
            $titleX,
            $pageHeight - ($first ? 80 : 65),
            $first ? 19 : 14,
            '#FFFFFF',
            'F2',
        );

        $subtitle = $this->ascii((string) $document['subtitle']);
        $metaLines = $this->wrap($subtitle, $pageWidth - 2 * self::MARGIN - 24, 8.4, 0.6);
        $metaHeight = max(44, 25 + count($metaLines) * 12);
        $metaTop = $pageHeight - $headerHeight - 12;
        $this->rect($stream, self::MARGIN, $metaTop - $metaHeight, $pageWidth - 2 * self::MARGIN, $metaHeight, '#F1F5FB');
        $this->rect($stream, self::MARGIN, $metaTop - $metaHeight, 3, $metaHeight, '#14B8A6');
        $this->text($stream, 'PERIODE  /  CAKUPAN  /  WAKTU CETAK', self::MARGIN + 13, $metaTop - 12, 6.8, '#64748B', 'F2');
        foreach ($metaLines as $index => $line) {
            $this->text($stream, $line, self::MARGIN + 13, $metaTop - 25 - $index * 12, 8.4, '#1E293B');
        }
        $cursor = $metaTop - $metaHeight - 18;

        if ($first && $document['summary'] !== []) {
            $this->text($stream, 'RINGKASAN PERIODE', self::MARGIN, $cursor, 9, '#334155', 'F2');
            $cursor -= 16;
            $this->summaryCards($stream, $document['summary'], $cursor, $pageWidth);
        }

        $this->text($stream, 'RINCIAN TRANSAKSI', self::MARGIN, $cursor, 9, '#334155', 'F2');
        $this->text(
            $stream,
            number_format(count($document['rows']), 0, ',', '.').' BARIS',
            $pageWidth - self::MARGIN,
            $cursor,
            8,
            '#64748B',
            'F2',
            true,
        );
        $cursor -= 15;
        $this->tableHeader($stream, $document['columns'], $widths, $cursor, $pageWidth);
        $cursor -= 36;
    }

    /**
     * @param list<array{label: string, value: int|float, type: string, note: string}> $cards
     */
    private function summaryCards(string &$stream, array $cards, float &$cursor, float $pageWidth): void
    {
        $gap = 10.0;
        $columns = 4;
        $cardWidth = ($pageWidth - 2 * self::MARGIN - ($columns - 1) * $gap) / $columns;
        $cardHeight = 70.0;
        $accents = ['#4F46E5', '#0891B2', '#059669', '#9333EA', '#EA580C', '#BE185D', '#0F766E', '#64748B'];

        foreach ($cards as $index => $card) {
            $column = $index % $columns;
            $row = intdiv($index, $columns);
            $x = self::MARGIN + $column * ($cardWidth + $gap);
            $top = $cursor - $row * ($cardHeight + 9);
            $bottom = $top - $cardHeight;
            $this->rect($stream, $x, $bottom, $cardWidth, $cardHeight, '#F8FAFC');
            $this->strokeRect($stream, $x, $bottom, $cardWidth, $cardHeight, '#E2E8F0', 0.7);
            $this->rect($stream, $x, $top - 3, $cardWidth, 3, $accents[$index % count($accents)]);
            $this->text($stream, $this->ellipsize((string) $card['label'], $cardWidth - 24, 8.2), $x + 11, $top - 18, 8.2, '#475569', 'F2');
            $value = $this->summaryValue($card['value'], (string) $card['type']);
            $size = strlen($value) > 21 ? 11.0 : (strlen($value) > 16 ? 13.0 : 15.0);
            $this->text($stream, $value, $x + 11, $top - 40, $size, '#0F172A', 'F2');
            $notes = $this->wrap((string) $card['note'], $cardWidth - 22, 6.8, 0.59);
            foreach (array_slice($notes, 0, 2) as $lineIndex => $note) {
                $this->text($stream, $note, $x + 11, $top - 53 - $lineIndex * 9, 6.8, '#64748B');
            }
        }
        $cursor -= (int) ceil(count($cards) / $columns) * ($cardHeight + 9) + 9;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param list<float> $widths
     */
    private function tableHeader(string &$stream, array $columns, array $widths, float $top, float $pageWidth): void
    {
        $this->rect($stream, self::MARGIN, $top - 36, $pageWidth - 2 * self::MARGIN, 36, '#273875');
        $x = self::MARGIN;
        foreach ($columns as $index => $column) {
            if ($index > 0) {
                $this->line($stream, $x, $top - 31, $x, $top - 5, '#55659B', 0.6);
            }
            $lines = $this->wrap((string) $column['label'], $widths[$index] - 2 * self::CELL_PADDING, 7.4);
            foreach (array_slice($lines, 0, 3) as $lineIndex => $line) {
                $this->text($stream, $line, $x + self::CELL_PADDING, $top - 13 - $lineIndex * 9, 7.4, '#FFFFFF', 'F4');
            }
            $x += $widths[$index];
        }
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

    private function summaryValue(int|float $value, string $type): string
    {
        return $type === 'money'
            ? 'Rp '.number_format((float) $value, 0, ',', '.')
            : number_format((float) $value, 0, ',', '.');
    }

    /** @return list<string> */
    private function wrap(string $text, float $availableWidth, float $fontSize, float $factor = 0.6): array
    {
        $value = trim(preg_replace('/\s+/', ' ', $this->ascii($text)) ?? '');
        if ($value === '') {
            return ['-'];
        }
        $capacity = max(3, (int) floor($availableWidth / ($fontSize * $factor)));
        $words = explode(' ', $value);
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            while (strlen($word) > $capacity) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                $lines[] = substr($word, 0, $capacity);
                $word = substr($word, $capacity);
            }
            if ($word === '') {
                continue;
            }
            if ($line !== '' && strlen($line) + 1 + strlen($word) > $capacity) {
                $lines[] = $line;
                $line = '';
            }
            $line = $line === '' ? $word : $line.' '.$word;
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines === [] ? ['-'] : $lines;
    }

    private function ellipsize(string $text, float $width, float $fontSize): string
    {
        $ascii = $this->ascii($text);
        $capacity = max(3, (int) floor($width / ($fontSize * 0.6)));

        return strlen($ascii) <= $capacity ? $ascii : substr($ascii, 0, $capacity - 3).'...';
    }

    private function ascii(string $value): string
    {
        $value = str_replace(['–', '—', '·', '“', '”', '’'], ['-', '-', ' | ', '"', '"', "'"], $value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return preg_replace('/[^\x20-\x7E]/', ' ', $ascii === false ? $value : $ascii) ?? '';
    }

    private function statusColor(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'completed', 'active', 'approved', 'received', 'returned', 'available', 'closed', 'paid' => '#047857',
            'cancelled', 'void', 'rejected', 'lost', 'overdue', 'failed' => '#BE123C',
            'pending', 'requested', 'in_progress', 'draft', 'reserved', 'maintenance', 'in_transit' => '#B45309',
            default => '#475569',
        };
    }

    /** @param list<string> $pages */
    private function buildPdf(array $pages, float $pageWidth, float $pageHeight): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
            6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $pageReferences = [];
        $totalPages = count($pages);
        // Share one compressed logo image object across every page of the PDF.
        $logoImage = $this->logoImage();
        $logoObject = $logoImage !== null ? 7 + $totalPages * 2 : null;
        if ($logoObject !== null) {
            $objects[$logoObject] = $logoImage;
        }
        foreach ($pages as $index => $pageStream) {
            $pageObject = 7 + $index * 2;
            $contentObject = $pageObject + 1;
            $pageReferences[] = $pageObject.' 0 R';
            $this->line($pageStream, self::MARGIN, self::FOOTER_RULE_Y, $pageWidth - self::MARGIN, self::FOOTER_RULE_Y, '#CBD5E1', 0.7);
            $this->text($pageStream, 'TOGETHER KAMERA   /   LAPORAN TERINTEGRASI', self::MARGIN, 25, 7, '#64748B', 'F2');
            $this->text(
                $pageStream,
                sprintf('HALAMAN %d / %d', $index + 1, $totalPages),
                $pageWidth - self::MARGIN,
                25,
                7,
                '#64748B',
                'F2',
                true,
            );
            $resources = '/Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R /F4 6 0 R >>';
            if ($logoObject !== null) {
                $resources .= ' /XObject << /Logo '.$logoObject.' 0 R >>';
            }
            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << %s >> /Contents %d 0 R >>',
                $pageWidth,
                $pageHeight,
                $resources,
                $contentObject,
            );
            $objects[$contentObject] = '<< /Length '.strlen($pageStream)." >>\nstream\n{$pageStream}\nendstream";
        }
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $pageReferences), $totalPages);
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

    /**
     * Converts the supplied 8-bit RGBA PNG to an embedded RGB image.
     * Alpha is composited over the PDF header navy; this works without GD.
     * Unsupported/missing PNGs degrade to the text-only header.
     */
    private function logoImage(): ?string
    {
        if ($this->logoAttempted) {
            return $this->logoImage;
        }
        $this->logoAttempted = true;
        $path = $this->logoPath ?? dirname(__DIR__, 3).'/public/primary-logos.png';
        if (!is_file($path)) {
            return null;
        }
        $png = @file_get_contents($path);
        if ($png === false || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        $length = strlen($png);
        $position = 8;
        $width = 0;
        $height = 0;
        $idat = '';
        while ($position + 12 <= $length) {
            $size = unpack('Nlength', substr($png, $position, 4));
            if ($size === false) {
                return null;
            }
            $chunkLength = $size['length'];
            $chunkType = substr($png, $position + 4, 4);
            $position += 8;
            if ($chunkLength > 8_000_000 || $position + $chunkLength + 4 > $length) {
                return null;
            }
            $chunk = substr($png, $position, $chunkLength);
            $position += $chunkLength + 4; // CRC (the public logo is a trusted local asset)
            if ($chunkType === 'IHDR' && $chunkLength === 13) {
                $info = unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace', $chunk);
                if ($info === false) {
                    return null;
                }
                $width = $info['width'];
                $height = $info['height'];
                if ($width < 1 || $height < 1 || $width * $height > 1_440_000
                    || $info['depth'] !== 8 || $info['color'] !== 6
                    || $info['compression'] !== 0 || $info['filter'] !== 0 || $info['interlace'] !== 0) {
                    return null;
                }
            } elseif ($chunkType === 'IDAT') {
                $idat .= $chunk;
            } elseif ($chunkType === 'IEND') {
                break;
            }
        }
        if ($width === 0 || $height === 0 || $idat === '') {
            return null;
        }

        $stride = $width * 4;
        $decoded = @gzuncompress($idat, 7_000_000);
        if ($decoded === false || strlen($decoded) !== ($stride + 1) * $height) {
            return null;
        }
        $rgb = '';
        $previous = array_fill(0, $stride, 0);
        $position = 0;
        for ($y = 0; $y < $height; $y++) {
            $filter = ord($decoded[$position++]);
            if ($filter > 4) {
                return null;
            }
            $packedRow = unpack('C*', substr($decoded, $position, $stride));
            if ($packedRow === false) {
                return null;
            }
            $row = array_values($packedRow);
            $position += $stride;
            $current = [];
            for ($i = 0; $i < $stride; $i++) {
                $left = $i >= 4 ? $current[$i - 4] : 0;
                $up = $previous[$i];
                $upperLeft = $i >= 4 ? $previous[$i - 4] : 0;
                $predictor = match ($filter) {
                    0 => 0,
                    1 => $left,
                    2 => $up,
                    3 => intdiv($left + $up, 2),
                    4 => $this->paeth($left, $up, $upperLeft),
                };
                $current[$i] = ($row[$i] + $predictor) & 255;
            }
            for ($x = 0; $x < $stride; $x += 4) {
                $alpha = $current[$x + 3];
                foreach ([23, 37, 84] as $channel => $background) {
                    $rgb .= chr(intdiv($alpha * $current[$x + $channel] + (255 - $alpha) * $background + 127, 255));
                }
            }
            $previous = $current;
        }
        $compressed = gzcompress($rgb, 6);
        if ($compressed === false) {
            return null;
        }
        $this->logoImage = sprintf(
            '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>',
            $width,
            $height,
            strlen($compressed),
        )."\nstream\n".$compressed."\nendstream";

        return $this->logoImage;
    }

    private function paeth(int $left, int $up, int $upperLeft): int
    {
        $prediction = $left + $up - $upperLeft;
        $leftDistance = abs($prediction - $left);
        $upDistance = abs($prediction - $up);
        $cornerDistance = abs($prediction - $upperLeft);
        if ($leftDistance <= $upDistance && $leftDistance <= $cornerDistance) {
            return $left;
        }

        return $upDistance <= $cornerDistance ? $up : $upperLeft;
    }

    private function rect(string &$stream, float $x, float $y, float $width, float $height, string $color): void
    {
        $stream .= $this->rgb($color)." rg\n";
        $stream .= sprintf('%.2F %.2F %.2F %.2F re f', $x, $y, $width, $height)."\n";
    }

    private function strokeRect(string &$stream, float $x, float $y, float $width, float $height, string $color, float $stroke): void
    {
        $stream .= $this->rgb($color)." RG\n";
        $stream .= sprintf('%.2F w %.2F %.2F %.2F %.2F re S', $stroke, $x, $y, $width, $height)."\n";
    }

    private function line(string &$stream, float $x1, float $y1, float $x2, float $y2, string $color, float $stroke): void
    {
        $stream .= $this->rgb($color)." RG\n";
        $stream .= sprintf('%.2F w %.2F %.2F m %.2F %.2F l S', $stroke, $x1, $y1, $x2, $y2)."\n";
    }

    private function text(
        string &$stream,
        string $value,
        float $x,
        float $y,
        float $size,
        string $color,
        string $font = 'F1',
        bool $right = false,
    ): void {
        $value = $this->ascii($value);
        if ($right) {
            $x -= strlen($value) * $size * ($font === 'F3' || $font === 'F4' ? 0.6 : 0.57);
        }
        $stream .= sprintf(
            'BT /%s %.2F Tf %s rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $font,
            $size,
            $this->rgb($color),
            $x,
            $y,
            $this->escapePdf($value),
        )."\n";
    }

    private function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return sprintf(
            '%.3F %.3F %.3F',
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        );
    }

    private function escapePdf(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
