<?php

namespace App\Domain\Documents;

use App\Models\TransactionDocument;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

class TransactionDocumentPdfRenderer
{
    private const LINE_WIDTH = 88;

    private const PAGE_LINES = 66;

    public function render(TransactionDocument $document): string
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $document->getRawOriginal('snapshot') === null
            ? []
            : (array) $document->snapshot;
        $lines = $this->header($document, $snapshot);

        $lines = match ($document->document_type) {
            'invoice' => [...$lines, ...$this->invoice($snapshot)],
            'receipt' => [...$lines, ...$this->receipt($snapshot)],
            'agreement' => [...$lines, ...$this->agreement($snapshot)],
            default => [...$lines, 'Jenis dokumen tidak dikenali.'],
        };

        $lines[] = '';
        $lines[] = str_repeat('-', self::LINE_WIDTH);
        $lines[] = 'Snapshot SHA-256: '.$document->content_hash;
        $lines[] = 'Dokumen ini dibentuk dari snapshot transaksi pada saat penerbitan.';

        return $this->buildPdf($this->paginate($lines));
    }

    /** @param array<string, mixed> $snapshot
     * @return list<string>
     */
    private function header(TransactionDocument $document, array $snapshot): array
    {
        $branch = is_array($snapshot['branch'] ?? null) ? $snapshot['branch'] : [];
        $source = is_array($snapshot['source'] ?? null) ? $snapshot['source'] : [];
        $title = match ($document->document_type) {
            'invoice' => 'INVOICE',
            'receipt' => 'NOTA PEMBAYARAN',
            'agreement' => 'PERJANJIAN RENTAL',
            default => 'DOKUMEN TRANSAKSI',
        };

        return [
            'TOGETHER KAMERA',
            $this->value($branch['name'] ?? null),
            $this->branchContact($branch),
            str_repeat('=', self::LINE_WIDTH),
            $title,
            'No. Dokumen : '.$document->document_number.'  |  Versi: '.$document->version,
            'Sumber       : '.$this->value($source['reference'] ?? $document->source_reference),
            'Status       : '.$this->value($source['status'] ?? null),
            'Diterbitkan  : '.$this->dateTime($document->issued_at).' WIB',
            str_repeat('=', self::LINE_WIDTH),
            '',
        ];
    }

    /** @param array<string, mixed> $snapshot
     * @return list<string>
     */
    private function invoice(array $snapshot): array
    {
        $lines = $this->customerSection($snapshot);
        $source = is_array($snapshot['source'] ?? null) ? $snapshot['source'] : [];
        $lines[] = 'PERIODE / TRANSAKSI';
        foreach ([
            'Mulai' => $source['starts_at'] ?? $source['checked_out_at'] ?? null,
            'Jatuh tempo' => $source['ends_at'] ?? $source['due_at'] ?? null,
            'Selesai' => $source['returned_at'] ?? null,
        ] as $label => $value) {
            if ($value !== null) {
                $lines[] = sprintf('%-16s: %s', $label, $this->dateTime($value));
            }
        }
        $lines[] = '';
        $lines[] = 'RINCIAN';
        $lines[] = str_repeat('-', self::LINE_WIDTH);

        $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $description = $this->value($item['description'] ?? null);
            $qty = (int) ($item['quantity'] ?? 0);
            $total = (float) ($item['total_amount'] ?? 0);
            $lines[] = sprintf('%2d. %-55s %3d x %s', $index + 1, $this->truncate($description, 55), $qty, $this->money($total));
        }
        if ($items === []) {
            $lines[] = 'Tidak ada rincian item.';
        }

        $financial = is_array($snapshot['financial'] ?? null) ? $snapshot['financial'] : [];
        $lines[] = str_repeat('-', self::LINE_WIDTH);
        foreach ([
            'Subtotal' => $financial['subtotal'] ?? null,
            'Diskon' => $financial['discount_amount'] ?? null,
            'Pajak' => $financial['tax_amount'] ?? null,
            'Denda keterlambatan' => $financial['late_fee_amount'] ?? null,
            'Biaya kerusakan' => $financial['damage_fee_amount'] ?? null,
            'TOTAL' => $financial['total_amount'] ?? null,
            'Terbayar' => $financial['rental_paid'] ?? $financial['paid_amount'] ?? null,
            'Sisa tagihan' => $financial['balance_due'] ?? null,
            'Deposit jaminan' => $financial['deposit_required'] ?? $financial['deposit_amount'] ?? null,
            'Deposit dibayar' => $financial['deposit_paid'] ?? null,
        ] as $label => $value) {
            if ($value !== null) {
                $lines[] = sprintf('%-34s %20s', $label, $this->money((float) $value));
            }
        }

        $payments = is_array($snapshot['payments'] ?? null) ? $snapshot['payments'] : [];
        if ($payments !== []) {
            $lines[] = '';
            $lines[] = 'HISTORI PEMBAYARAN';
            foreach ($payments as $payment) {
                if (! is_array($payment)) {
                    continue;
                }
                $lines[] = sprintf(
                    '- %s | %s | %s | %s',
                    $this->value($payment['payment_number'] ?? null),
                    $this->value($payment['status'] ?? null),
                    $this->value($payment['method'] ?? null),
                    $this->money((float) ($payment['amount'] ?? 0)),
                );
            }
        }

        return $lines;
    }

    /** @param array<string, mixed> $snapshot
     * @return list<string>
     */
    private function receipt(array $snapshot): array
    {
        $lines = $this->customerSection($snapshot);
        $payment = is_array($snapshot['payment'] ?? null) ? $snapshot['payment'] : [];
        $related = is_array($snapshot['related'] ?? null) ? $snapshot['related'] : [];
        $method = is_array($payment['method'] ?? null) ? $payment['method'] : [];
        $receiver = is_array($payment['receiver'] ?? null) ? $payment['receiver'] : [];

        $lines[] = 'DETAIL PEMBAYARAN';
        $lines[] = str_repeat('-', self::LINE_WIDTH);
        $lines[] = 'Nomor payment : '.$this->value($payment['payment_number'] ?? null);
        $lines[] = 'Tanggal        : '.$this->dateTime($payment['paid_at'] ?? null);
        $lines[] = 'Metode         : '.$this->value($method['name'] ?? null);
        $lines[] = 'Referensi      : '.$this->value($payment['external_reference'] ?? null);
        $lines[] = 'Petugas        : '.$this->value($receiver['name'] ?? null);
        $lines[] = 'Jenis          : '.$this->value($payment['type'] ?? null);
        $lines[] = '';
        $lines[] = 'Diterima sebesar: '.$this->money((float) ($payment['amount'] ?? 0));
        $lines[] = '';
        $lines[] = 'Booking        : '.$this->value($related['booking_reference'] ?? null);
        $lines[] = 'Rental         : '.$this->value($related['rental_reference'] ?? null);
        $lines[] = 'Perpanjangan   : '.$this->value($related['extension_reference'] ?? null);
        $lines[] = '';
        $lines[] = 'Status payment : '.$this->value($payment['status'] ?? null);

        return $lines;
    }

    /** @param array<string, mixed> $snapshot
     * @return list<string>
     */
    private function agreement(array $snapshot): array
    {
        $lines = $this->customerSection($snapshot);
        $source = is_array($snapshot['source'] ?? null) ? $snapshot['source'] : [];
        $financial = is_array($snapshot['financial'] ?? null) ? $snapshot['financial'] : [];

        $lines[] = 'DETAIL RENTAL';
        $lines[] = 'Nomor rental   : '.$this->value($source['reference'] ?? null);
        $lines[] = 'Checkout       : '.$this->dateTime($source['checked_out_at'] ?? null);
        $lines[] = 'Jatuh tempo    : '.$this->dateTime($source['due_at'] ?? null);
        $lines[] = 'Nilai rental   : '.$this->money((float) ($financial['total_amount'] ?? 0));
        $lines[] = 'Deposit        : '.$this->money((float) ($financial['deposit_amount'] ?? 0));
        $lines[] = '';
        $lines[] = 'UNIT / ITEM YANG DISERAHKAN';
        $lines[] = str_repeat('-', self::LINE_WIDTH);

        $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $lines[] = sprintf('%2d. %s (qty %d)', $index + 1, $this->value($item['description'] ?? null), (int) ($item['quantity'] ?? 0));
            $assets = is_array($item['assets'] ?? null) ? $item['assets'] : [];
            foreach ($assets as $asset) {
                if (! is_array($asset)) {
                    continue;
                }
                $lines[] = sprintf(
                    '    - %s | SN %s | kondisi %s',
                    $this->value($asset['asset_code'] ?? null),
                    $this->value($asset['serial_number'] ?? null),
                    $this->value($asset['checkout_condition'] ?? null),
                );
            }
        }

        $collaterals = is_array($snapshot['collaterals'] ?? null) ? $snapshot['collaterals'] : [];
        if ($collaterals !== []) {
            $lines[] = '';
            $lines[] = 'JAMINAN FISIK';
            foreach ($collaterals as $collateral) {
                if (! is_array($collateral)) {
                    continue;
                }
                $lines[] = sprintf(
                    '- %s / %s / %s',
                    $this->value($collateral['type'] ?? null),
                    $this->value($collateral['number'] ?? null),
                    $this->value($collateral['status'] ?? null),
                );
            }
        }

        $lines[] = '';
        $lines[] = 'KETENTUAN';
        $terms = is_array($snapshot['agreement_terms'] ?? null) ? $snapshot['agreement_terms'] : [];
        foreach ($terms as $index => $term) {
            foreach ($this->wrap(($index + 1).'. '.(string) $term, self::LINE_WIDTH) as $wrapped) {
                $lines[] = $wrapped;
            }
        }
        $lines[] = '';
        $lines[] = 'Penyewa,                                  Petugas Together Kamera,';
        $lines[] = '';
        $lines[] = '';
        $lines[] = '(________________________)                 (________________________)';

        return $lines;
    }

    /** @param array<string, mixed> $snapshot
     * @return list<string>
     */
    private function customerSection(array $snapshot): array
    {
        $customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];
        $address = is_array($customer['address'] ?? null) ? $customer['address'] : [];
        $lines = [
            'PELANGGAN',
            'Nama           : '.$this->value($customer['name'] ?? null),
            'No. pelanggan  : '.$this->value($customer['customer_number'] ?? null),
            'Telepon        : '.$this->value($customer['phone'] ?? null),
            'Email          : '.$this->value($customer['email'] ?? null),
        ];
        $addressText = implode(', ', array_filter([
            $address['address'] ?? null,
            $address['village'] ?? null,
            $address['district'] ?? null,
            $address['city'] ?? null,
            $address['province'] ?? null,
            $address['postal_code'] ?? null,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
        if ($addressText !== '') {
            foreach ($this->wrap('Alamat         : '.$addressText, self::LINE_WIDTH) as $line) {
                $lines[] = $line;
            }
        }
        $lines[] = '';

        return $lines;
    }

    /** @param array<string, mixed> $branch */
    private function branchContact(array $branch): string
    {
        $parts = array_filter([
            $branch['address'] ?? null,
            $branch['city'] ?? null,
            $branch['province'] ?? null,
            $branch['phone'] ?? null,
            $branch['email'] ?? null,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        return $parts === [] ? '-' : implode(' | ', $parts);
    }

    private function money(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }

    private function dateTime(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->format('d-m-Y H:i');
        }

        if (! is_string($value) || $value === '') {
            return '-';
        }

        try {
            return CarbonImmutable::parse($value)->format('d-m-Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }

    private function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }

    private function truncate(string $value, int $width): string
    {
        $value = $this->ascii($value);

        return strlen($value) <= $width
            ? $value
            : substr($value, 0, max(1, $width - 1)).'~';
    }

    /** @return list<string> */
    private function wrap(string $value, int $width): array
    {
        $wrapped = wordwrap($this->ascii($value), $width, "\n", true);

        return explode("\n", $wrapped);
    }

    private function ascii(string $value): string
    {
        $value = str_replace(['–', '—', '·', '“', '”', '’'], ['-', '-', '-', '"', '"', "'"], $value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return preg_replace('/[^\x20-\x7E]/', '', $ascii === false ? $value : $ascii) ?? '';
    }

    /** @param list<string> $lines
     * @return list<list<string>>
     */
    private function paginate(array $lines): array
    {
        $pages = [];
        $page = [];
        foreach ($lines as $line) {
            $wrapped = $this->wrap($line, self::LINE_WIDTH);
            foreach ($wrapped as $part) {
                if (count($page) >= self::PAGE_LINES) {
                    $pages[] = $page;
                    $page = [];
                }
                $page[] = $part;
            }
        }
        if ($page !== [] || $pages === []) {
            $pages[] = $page;
        }

        return $pages;
    }

    /** @param list<list<string>> $pages */
    private function buildPdf(array $pages): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>',
        ];
        $pageReferences = [];

        foreach ($pages as $index => $lines) {
            $pageObject = 5 + ($index * 2);
            $contentObject = $pageObject + 1;
            $pageReferences[] = $pageObject.' 0 R';
            $stream = $this->pageStream($lines, $index + 1, count($pages));
            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $contentObject,
            );
            $objects[$contentObject] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }

        $objects[2] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', $pageReferences),
            count($pages),
        );
        ksort($objects);

        $pdf = "%PDF-1.4\n%Together Kamera Transaction Documents\n";
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
            '/F1 8 Tf',
            '11 TL',
            '38 805 Td',
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
