<?php

namespace App\Domain\Reporting;

class SpreadsheetXmlExporter
{
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
        $rows = [];
        $rows[] = $this->row([
            $this->cell((string) $document['title'], 'String', 'Title'),
        ], count($document['columns']));
        $rows[] = $this->row([
            $this->cell((string) $document['subtitle'], 'String', 'Subtitle'),
        ], count($document['columns']));
        $rows[] = '<Row/>';

        foreach ($document['summary'] as $card) {
            $rows[] = $this->row([
                $this->cell((string) $card['label'], 'String', 'SummaryLabel'),
                $this->typedCell($card['value'], (string) $card['type'], 'SummaryValue'),
                $this->cell((string) $card['note'], 'String', 'Note'),
            ]);
        }

        $rows[] = '<Row/>';
        $headerCells = [];
        foreach ($document['columns'] as $column) {
            $headerCells[] = $this->cell((string) $column['label'], 'String', 'Header');
        }
        $rows[] = $this->row($headerCells);

        foreach ($document['rows'] as $row) {
            $cells = [];
            foreach ($document['columns'] as $column) {
                $key = (string) $column['key'];
                $cells[] = $this->typedCell(
                    $row['values'][$key] ?? null,
                    (string) $column['type'],
                    $this->styleFor((string) $column['type']),
                );
            }
            $rows[] = $this->row($cells);
        }

        $columnXml = collect($document['columns'])
            ->map(static fn (array $column): string => sprintf(
                '<Column ss:AutoFitWidth="0" ss:Width="%d"/>',
                max(55, min(180, (int) $column['export_width'] * 7)),
            ))
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<?mso-application progid="Excel.Sheet"?>'."\n"
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            .'xmlns:o="urn:schemas-microsoft-com:office:office" '
            .'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            .'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            .'<Styles>'
            .'<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Aptos" ss:Size="10"/></Style>'
            .'<Style ss:ID="Title"><Font ss:FontName="Aptos Display" ss:Size="18" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#4C1D95" ss:Pattern="Solid"/></Style>'
            .'<Style ss:ID="Subtitle"><Font ss:FontName="Aptos" ss:Size="10" ss:Color="#5B6472"/></Style>'
            .'<Style ss:ID="SummaryLabel"><Font ss:Bold="1" ss:Color="#4C1D95"/><Interior ss:Color="#F3E8FF" ss:Pattern="Solid"/></Style>'
            .'<Style ss:ID="SummaryValue"><Font ss:Bold="1"/><NumberFormat ss:Format="#,##0"/></Style>'
            .'<Style ss:ID="Note"><Font ss:Color="#5B6472"/></Style>'
            .'<Style ss:ID="Header"><Alignment ss:WrapText="1"/><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#6D28D9" ss:Pattern="Solid"/></Style>'
            .'<Style ss:ID="Money"><NumberFormat ss:Format="&quot;Rp&quot; #,##0"/></Style>'
            .'<Style ss:ID="Number"><NumberFormat ss:Format="#,##0"/></Style>'
            .'<Style ss:ID="Text"><Alignment ss:WrapText="1"/></Style>'
            .'</Styles>'
            .'<Worksheet ss:Name="Laporan"><Table>'.$columnXml.implode('', $rows).'</Table>'
            .'<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>12</SplitHorizontal><TopRowBottomPane>12</TopRowBottomPane><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions>'
            .'</Worksheet></Workbook>';
    }

    /** @param list<string> $cells */
    private function row(array $cells, ?int $mergeAcross = null): string
    {
        if ($mergeAcross !== null && $cells !== []) {
            $cells[0] = preg_replace(
                '/^<Cell /',
                sprintf('<Cell ss:MergeAcross="%d" ', max($mergeAcross - 1, 0)),
                $cells[0],
            ) ?? $cells[0];
        }

        return '<Row>'.implode('', $cells).'</Row>';
    }

    private function typedCell(mixed $value, string $type, string $style): string
    {
        if ($value === null || $value === '') {
            return '<Cell ss:StyleID="'.$style.'"/>';
        }

        if (in_array($type, ['money', 'number'], true)) {
            return $this->cell((string) $value, 'Number', $style);
        }

        return $this->cell((string) $value, 'String', $style);
    }

    private function cell(string $value, string $type, string $style): string
    {
        return sprintf(
            '<Cell ss:StyleID="%s"><Data ss:Type="%s">%s</Data></Cell>',
            $style,
            $type,
            htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        );
    }

    private function styleFor(string $type): string
    {
        return match ($type) {
            'money' => 'Money',
            'number' => 'Number',
            default => 'Text',
        };
    }
}
