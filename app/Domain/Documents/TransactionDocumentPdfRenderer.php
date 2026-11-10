<?php

namespace App\Domain\Documents;

use App\Models\TransactionDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

class TransactionDocumentPdfRenderer
{
    private const W = 595.28;

    private const H = 841.89;

    private const MX = 36.0;

    private const TOP = 806.0;

    private const BOTTOM = 42.0;

    /** @var list<string> */
    private array $pages = [];

    private string $stream = '';

    private float $y = self::TOP;

    private int $page = 0;

    private string $documentNumber = '';

    private string $contentHash = '';

    /** Presentation-only translation; document snapshots remain unchanged. */
    private function l(string $label): string
    {
        $labels = trans('uat035b_stage4.pdf');

        return is_array($labels) ? (string) ($labels[$label] ?? $label) : $label;
    }

    private function displayValue(string $group, mixed $value): string
    {
        $raw = $this->value($value);
        $labels = trans('uat035b_stage4.pdf_values.'.$group);

        return is_array($labels) ? (string) ($labels[$raw] ?? $raw) : $raw;
    }

    private function agreementClause(string $section, mixed $value): string
    {
        $raw = $this->value($value);
        if (app()->getLocale() !== 'en') {
            return $raw;
        }

        $clauses = trans('uat035b_stage4.agreement.'.$section);

        return is_array($clauses) ? (string) ($clauses[$raw] ?? $raw) : $raw;
    }

    public function render(TransactionDocument $document): string
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $document->getRawOriginal('snapshot') === null
            ? []
            : (array) $document->snapshot;

        $this->pages = [];
        $this->stream = '';
        $this->page = 0;
        $this->documentNumber = (string) $document->document_number;
        $this->contentHash = (string) $document->content_hash;

        $issuer = $this->issuer($document);
        if ($document->document_type === 'agreement') {
            return $this->compactAgreement($document, $snapshot, $issuer);
        }
        $this->newPage();
        $this->header($document, $snapshot, $issuer);

        match ($document->document_type) {
            'invoice' => $this->invoice($snapshot, $issuer),
            'receipt' => $this->receipt($snapshot, $issuer),
            'agreement' => $this->agreement($snapshot, $issuer),
            default => $this->paragraph($this->l('Jenis dokumen tidak dikenali.')),
        };

        $this->finishPage();

        return $this->buildPdf($this->pages);
    }

    /** @param array<string,mixed> $snapshot
     *  @param array{name:string} $issuer */
    private function header(TransactionDocument $document, array $snapshot, array $issuer): void
    {
        $branch = is_array($snapshot['branch'] ?? null) ? $snapshot['branch'] : [];
        $source = is_array($snapshot['source'] ?? null) ? $snapshot['source'] : [];
        $branchName = $this->value($branch['name'] ?? 'Together Kamera');
        $title = match ($document->document_type) {
            'invoice' => $this->l('INVOICE'),
            'receipt' => $this->l('NOTA PEMBAYARAN'),
            'agreement' => $this->l('SURAT PERJANJIAN SEWA'),
            default => $this->l('DOKUMEN TRANSAKSI'),
        };
        $cw = self::W - (2 * self::MX);

        $this->text($this->upper($branchName), self::MX, $this->y, 13, true, 'center', $cw);
        $this->y -= 15;

        $contact = $this->branchContact($branch);
        foreach ($this->wrap($contact, $cw, 7.3) as $line) {
            $this->text($line, self::MX, $this->y, 7.3, false, 'center', $cw);
            $this->y -= 9;
        }

        $this->y -= 2;
        $this->line(self::MX, $this->y, self::W - self::MX, $this->y, .8);
        $this->y -= 22;
        $this->text($title, self::MX, $this->y, 14, true, 'center', $cw);
        $this->y -= 19;

        $this->label($this->l('No. Dokumen'), (string) $document->document_number, self::MX, 72);
        $this->label($this->l('Versi'), (string) $document->version, 320, 60);
        $this->y -= 13;
        $this->label($this->l('Sumber'), $this->value($source['reference'] ?? $document->source_reference), self::MX, 72);
        $this->label($this->l('Status'), $this->displayValue('status', $source['status'] ?? null), 320, 60);
        $this->y -= 13;
        $this->label($this->l('Diterbitkan'), $this->dateTime($document->issued_at).' WIB', self::MX, 72);
        $this->label($this->l('Petugas'), $issuer['name'], 320, 60);
        $this->y -= 16;

        $this->line(self::MX, $this->y, self::W - self::MX, $this->y, .6);
        $this->y -= 14;
    }

    /** @param array<string,mixed> $snapshot
     *  @param array{name:string} $issuer */
    private function invoice(array $snapshot, array $issuer): void
    {
        $source = is_array($snapshot['source'] ?? null) ? $snapshot['source'] : [];
        $customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];

        $this->infoPair(
            $this->l('DATA PELANGGAN'),
            [
                [$this->l('Nama'), $this->value($customer['name'] ?? null)],
                [$this->l('No. Pelanggan'), $this->value($customer['customer_number'] ?? null)],
                [$this->l('Telepon'), $this->value($customer['phone'] ?? null)],
                [$this->l('Alamat'), $this->customerAddress($customer)],
            ],
            $this->l('DATA TRANSAKSI'),
            [
                [$this->l('Mulai'), $this->dateTime($source['starts_at'] ?? $source['checked_out_at'] ?? null)],
                [$this->l('Kembali'), $this->dateTime($source['ends_at'] ?? $source['due_at'] ?? null)],
                [$this->l('Selesai'), $this->dateTime($source['returned_at'] ?? null)],
                [$this->l('Status'), $this->displayValue('status', $source['status'] ?? null)],
            ],
        );

        $this->section($this->l('RINCIAN BARANG / JASA'));
        $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
        $this->tableHeader([$this->l('No'), $this->l('Item'), $this->l('Qty'), $this->l('Harga'), $this->l('Total')], [28, 300, 40, 78, 77]);

        if ($items === []) {
            $this->paragraph($this->l('Tidak ada rincian item.'), 8);
        }

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $this->ensure(24);
            $this->text((string) ($i + 1), self::MX + 6, $this->y, 8);
            $this->text($this->truncate($this->value($item['description'] ?? null), 48), self::MX + 34, $this->y, 8);
            $this->text((string) ((int) ($item['quantity'] ?? 0)), 364, $this->y, 8, false, 'right', 35);
            $this->text($this->money((float) ($item['unit_rate'] ?? 0)), 404, $this->y, 8, false, 'right', 72);
            $this->text($this->money((float) ($item['total_amount'] ?? 0)), 481, $this->y, 8, true, 'right', 74);
            $this->y -= 18;
            $this->line(self::MX, $this->y + 6, self::W - self::MX, $this->y + 6, .2);
        }

        $this->y -= 4;
        $this->section($this->l('RINGKASAN TAGIHAN'));
        $financial = is_array($snapshot['financial'] ?? null) ? $snapshot['financial'] : [];
        foreach ([
            $this->l('Subtotal') => $financial['subtotal'] ?? null,
            $this->l('Diskon') => $financial['discount_amount'] ?? null,
            $this->l('Pajak') => $financial['tax_amount'] ?? null,
            $this->l('Denda keterlambatan') => $financial['late_fee_amount'] ?? null,
            $this->l('Biaya kerusakan') => $financial['damage_fee_amount'] ?? null,
            $this->l('TOTAL') => $financial['total_amount'] ?? null,
            $this->l('Terbayar') => $financial['rental_paid'] ?? $financial['paid_amount'] ?? null,
            $this->l('Sisa tagihan') => $financial['balance_due'] ?? null,
            $this->l('Deposit jaminan') => $financial['deposit_required'] ?? $financial['deposit_amount'] ?? null,
            $this->l('Deposit dibayar') => $financial['deposit_paid'] ?? null,
        ] as $label => $value) {
            if ($value === null) {
                continue;
            }
            $bold = in_array($label, [$this->l('TOTAL'), $this->l('Sisa tagihan')], true);
            $this->text($label, 330, $this->y, 8.5, $bold);
            $this->text($this->money((float) $value), 445, $this->y, 8.5, $bold, 'right', 110);
            $this->y -= 12;
        }

        $payments = is_array($snapshot['payments'] ?? null) ? $snapshot['payments'] : [];
        if ($payments !== []) {
            $this->y -= 5;
            $this->section($this->l('HISTORI PEMBAYARAN'));
            foreach ($payments as $payment) {
                if (! is_array($payment)) {
                    continue;
                }
                $this->paragraph(sprintf(
                    '- %s | %s | %s | %s',
                    $this->value($payment['payment_number'] ?? null),
                    $this->value($payment['method'] ?? null),
                    $this->money((float) ($payment['amount'] ?? 0)),
                    $this->displayValue('status', $payment['status'] ?? null),
                ), 7.8, 10);
                $refunded = (float) ($payment['refunded_amount'] ?? 0);
                if ($refunded > 0) {
                    $this->paragraph(sprintf(
                        $this->l('Refund dibayar: -%s | Bersih: %s'),
                        $this->money($refunded),
                        $this->money((float) ($payment['net_amount'] ?? 0)),
                    ), 7.8, 10);
                }
            }
        }

        $this->y -= 8;
        $this->sign2($this->l('Diterbitkan oleh'), $issuer['name'], $this->l('Pelanggan'), $this->value($customer['name'] ?? null));
    }

    /** @param array<string,mixed> $snapshot
     *  @param array{name:string} $issuer */
    private function receipt(array $snapshot, array $issuer): void
    {
        $customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];
        $payment = is_array($snapshot['payment'] ?? null) ? $snapshot['payment'] : [];
        $related = is_array($snapshot['related'] ?? null) ? $snapshot['related'] : [];
        $method = is_array($payment['method'] ?? null) ? $payment['method'] : [];
        $receiver = is_array($payment['receiver'] ?? null) ? $payment['receiver'] : [];

        $this->infoPair(
            $this->l('DITERIMA DARI'),
            [
                [$this->l('Nama'), $this->value($customer['name'] ?? null)],
                [$this->l('No. Pelanggan'), $this->value($customer['customer_number'] ?? null)],
                [$this->l('Telepon'), $this->value($customer['phone'] ?? null)],
                [$this->l('Alamat'), $this->customerAddress($customer)],
            ],
            $this->l('DETAIL PEMBAYARAN'),
            [
                [$this->l('No. Payment'), $this->value($payment['payment_number'] ?? null)],
                [$this->l('Tanggal'), $this->dateTime($payment['paid_at'] ?? null)],
                [$this->l('Metode'), $this->value($method['name'] ?? null)],
                [$this->l('Petugas'), $this->value($receiver['name'] ?? $issuer['name'])],
            ],
        );

        $this->ensure(82);
        $top = $this->y;
        $this->rect(self::MX, $top - 66, self::W - 2 * self::MX, 66, false, .7);
        $this->text($this->l('TELAH DITERIMA PEMBAYARAN SEBESAR'), self::MX, $top - 20, 8, true, 'center', self::W - 2 * self::MX);
        $this->text($this->money((float) ($payment['amount'] ?? 0)), self::MX, $top - 46, 18, true, 'center', self::W - 2 * self::MX);
        $this->y -= 78;

        $this->section($this->l('REFERENSI TRANSAKSI'));
        foreach ([
            'Booking' => $related['booking_reference'] ?? null,
            'Rental' => $related['rental_reference'] ?? null,
            'Perpanjangan' => $related['extension_reference'] ?? null,
            'Referensi eksternal' => $payment['external_reference'] ?? null,
            'Jenis pembayaran' => $this->displayValue('payment_type', $payment['type'] ?? null),
            'Status' => $this->displayValue('status', $payment['status'] ?? null),
        ] as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $this->label($this->l($label), $this->value($value), self::MX + 6, 110, 8.3);
            $this->y -= 12;
        }

        $this->y -= 8;
        $this->sign2($this->l('Diterbitkan oleh'), $issuer['name'], $this->l('Pembayar'), $this->value($customer['name'] ?? null));
    }

    /**
     * A5 portrait with measured typography, structured invoice blocks and intact snapshot clauses.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array{name: string}  $issuer
     */
    private function compactAgreement(TransactionDocument $document, array $snapshot, array $issuer): string
    {
        $branch = is_array($snapshot['branch'] ?? null) ? $snapshot['branch'] : [];
        $source = is_array($snapshot['source'] ?? null) ? $snapshot['source'] : [];
        $customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];
        $financial = is_array($snapshot['financial'] ?? null) ? $snapshot['financial'] : [];
        $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
        $collaterals = is_array($snapshot['collaterals'] ?? null) ? $snapshot['collaterals'] : [];
        $rights = is_array($snapshot['agreement_rights_obligations'] ?? null) ? array_values($snapshot['agreement_rights_obligations']) : [];
        $terms = is_array($snapshot['agreement_terms'] ?? null) ? array_values($snapshot['agreement_terms']) : [];
        $width = 419.53;
        $height = 595.28;
        $margin = 20.0;
        $cw = $width - 2 * $margin;
        $gap = 14.0;
        $col = ($cw - $gap) / 2;
        $rightX = $margin + $col + $gap;
        $fonts = $this->agreementFonts();
        $dense = count($items) > 2;

        // Prefer the largest readable clause size; signatures always have a reserved area.
        foreach (($dense ? [7.0, 6.8, 6.6, 6.4, 6.2, 6.0] : [7.0, 6.8, 6.6, 6.4]) as $clauseSize) {
            $this->stream = '';
            $y = $height - $margin - 3;
            $brandY = $this->a5Block($this->upper($this->value($branch['name'] ?? 'Together Kamera')), $margin, $y, 229, 10.5, 12, true);
            $this->a5Text($this->documentNumber, $width - $margin, $y, 7.2, true, 'right');
            $this->a5Text($this->l('Versi').' '.$document->version.' / '.$this->dateTime($document->issued_at).' WIB', $width - $margin, $y - 10, 6.1, false, 'right', .4);
            $y = min($brandY, $y - 13);
            $y = $this->a5Block($this->branchContact($branch), $margin, $y, $cw, 6.5, 8, false, .4);
            $y -= 3;
            $this->a5Text(__('direct_rental.agreement_title'), $margin, $y, 9, true);
            $y -= 10;
            $this->line($margin, $y, $width - $margin, $y, .7);
            $y -= 13;

            $this->a5Text($this->l('DATA PENYEWA'), $margin, $y, 6.5, true, 'left', .4);
            $this->a5Text($this->l('DATA SEWA'), $rightX, $y, 6.5, true, 'left', .4);
            $top = $y - 10;
            $leftY = $this->a5Block($this->value($customer['name'] ?? null), $margin, $top, $col, 8.3, 10, true);
            $leftY = $this->a5Block($this->l('Telepon').': '.$this->value($customer['phone'] ?? null), $margin, $leftY - 1, $col, 7.1, 9);
            $leftY = $this->a5Block($this->customerAddress($customer), $margin, $leftY, $col, 6.8, 8.5, false, .35);
            $rightY = $this->a5Block($this->value($source['reference'] ?? null), $rightX, $top, $col, 8, 10, true);
            $rightY = $this->a5Block($this->l('Mulai').': '.$this->dateTime($source['checked_out_at'] ?? null), $rightX, $rightY - 1, $col, 7.1, 9);
            $rightY = $this->a5Block($this->l('Kembali').': '.$this->dateTime($source['due_at'] ?? null), $rightX, $rightY, $col, 7.1, 9);
            $rightY = $this->a5Block($this->l('Status').': '.$this->displayValue('status', $source['status'] ?? null), $rightX, $rightY, $col, 6.8, 8.5, false, .35);
            $y = min($leftY, $rightY) - 5;

            // A proper table: item description / count / amount, with unit details indented below.
            $this->fillRect($margin, $y - 5, $cw, 16, .94);
            $this->a5Text($this->l('BARANG SEWA'), $margin + 5, $y, 6.8, true);
            $this->a5Text($this->l('Qty'), $margin + $cw - 86, $y, 6.8, true, 'center');
            $this->a5Text($this->l('Total'), $width - $margin - 5, $y, 6.8, true, 'right');
            $y -= 14;
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $bottom = $this->a5Block(($index + 1).'. '.$this->value($item['description'] ?? null), $margin + 5, $y, $cw - 123, 7.6, 9.5, true);
                $this->a5Text((string) (int) ($item['quantity'] ?? 0), $margin + $cw - 86, $y, 7.6, false, 'center');
                $this->a5Text($this->money((float) ($item['total_amount'] ?? 0)), $width - $margin - 5, $y, 7.6, true, 'right');
                $y = $bottom;
                foreach (is_array($item['assets'] ?? null) ? $item['assets'] : [] as $asset) {
                    if (is_array($asset)) {
                        $y = $this->a5Block(sprintf($this->l('Unit %s | SN %s | kondisi %s'), $this->value($asset['asset_code'] ?? null), $this->value($asset['serial_number'] ?? null), $this->displayValue('condition', $asset['checkout_condition'] ?? null)), $margin + 13, $y, $cw - 20, 6.5, 8, false, .35);
                    }
                }
                $this->line($margin, $y + 2, $width - $margin, $y + 2, .2);
                $y -= 5;
            }
            $y = $this->a5Block($this->l('Subtotal').': '.$this->money((float) ($financial['subtotal'] ?? 0)).'     '.$this->l('Diskon').': '.$this->money((float) ($financial['discount_amount'] ?? 0)).'     '.$this->l('Pajak').': '.$this->money((float) ($financial['tax_amount'] ?? 0)), $margin + 5, $y, $cw - 10, 6.7, 8.5, false, .35);
            $y -= 2;
            $this->fillRect($margin, $y - 24, $cw, 30, .95);
            foreach ([[$this->l('Total sewa'), $financial['total_amount'] ?? 0], [$this->l('Terbayar'), $financial['rental_paid'] ?? $financial['paid_amount'] ?? 0], [$this->l('Sisa tagihan'), $financial['balance_due'] ?? 0]] as $index => [$label, $value]) {
                $x = $margin + 7 + $index * $cw / 3;
                $this->a5Text($label, $x, $y - 2, 6.8, false, 'left', .4);
                $this->a5Text($this->money((float) $value), $x, $y - 17, 10, true);
            }
            $y -= $dense ? 29 : 32;
            $y = $this->a5Block($this->l('Deposit jaminan').': '.$this->money((float) ($financial['deposit_amount'] ?? $financial['deposit_required'] ?? 0)).'     '.$this->l('Denda').': '.$this->money((float) ($financial['late_fee_amount'] ?? 0)).'     '.$this->l('Kerusakan').': '.$this->money((float) ($financial['damage_fee_amount'] ?? 0)), $margin + 5, $y, $cw - 10, 6.7, 8.5, false, .35);
            foreach ($collaterals as $collateral) {
                if (is_array($collateral)) {
                    $y = $this->a5Block($this->l('JAMINAN').': '.$this->value($collateral['type'] ?? null).' '.$this->value($collateral['number'] ?? null).' / '.$this->value($collateral['holder_name'] ?? null).' / '.$this->displayValue('status', $collateral['status'] ?? null), $margin + 5, $y, $cw - 10, 6.7, 8.5);
                }
            }
            $y -= $dense ? 4 : 8;
            $this->line($margin, $y + 7, $width - $margin, $y + 7, .3);
            $this->a5Text($this->l($rights === [] ? 'KETENTUAN SEWA' : 'HAK DAN KEWAJIBAN PENYEWA'), $margin, $y - 4, 6.8, true);
            $termsLeftWidth = 168.0;
            $termsRightX = $margin + $termsLeftWidth + $gap;
            $this->a5Text($this->l('KETENTUAN SEWA'), $termsRightX, $y - 4, 6.8, true);
            $y -= 14;
            $leftClauses = $rights;
            $rightClauses = $terms;
            $rightStart = 1;
            if ($rights === []) {
                $split = (int) ceil(count($terms) / 2);
                $leftClauses = array_slice($terms, 0, $split);
                $rightClauses = array_slice($terms, $split);
                $rightStart = $split + 1;
            }
            $bottoms = [];
            foreach ([$leftClauses, $rightClauses] as $column => $clauses) {
                $columnY = $y;
                $x = $column === 0 ? $margin : $termsRightX;
                $clauseWidth = $column === 0 ? $termsLeftWidth : $cw - $termsLeftWidth - $gap;
                foreach ($clauses as $index => $clause) {
                    $number = $index + ($column === 0 ? 1 : $rightStart);
                    $section = $column === 0 && $rights !== [] ? 'rights' : 'terms';
                    $this->a5Text($number.'.', $x, $columnY, $clauseSize, true, 'left', .35);
                    $columnY = $this->a5Block($this->agreementClause($section, $clause), $x + 12, $columnY, $clauseWidth - 12, $clauseSize, $clauseSize + .8);
                    $columnY -= $dense ? 1.5 : 2;
                }
                $bottoms[] = $columnY;
            }
            $guarantor = isset($collaterals[0]) && is_array($collaterals[0]) ? $this->value($collaterals[0]['holder_name'] ?? null) : '-';
            $signWidth = $cw / 3;
            $names = [$issuer['name'], $guarantor, $this->value($customer['name'] ?? null)];
            $nameLines = array_map(fn (string $name): array => $this->a5Wrap($name, $signWidth - 14, 6.8, true), $names);
            $signTop = 58 + max(array_map(count(...), $nameLines)) * 8;
            if (min($bottoms) < $signTop + 9) {
                continue;
            }
            foreach ([$this->l('Karyawan / Petugas'), $this->l('Penjamin'), $this->l('Peminjam')] as $index => $title) {
                $x = $margin + $index * $signWidth;
                $this->a5Text($title, $x + $signWidth / 2, $signTop, 6.8, false, 'center', .35);
                $this->line($x + 7, $signTop - 25, $x + $signWidth - 7, $signTop - 25, .3);
                foreach ($nameLines[$index] as $row => $name) {
                    $this->a5Text($name, $x + $signWidth / 2, $signTop - 35 - $row * 8, 6.8, true, 'center');
                }
            }
            $this->line($margin, 24, $width - $margin, 24, .2);
            $this->a5Text('Snapshot: '.substr($this->contentHash, 0, 16), $margin, 15, 5.6, false, 'left', .45);
            $this->a5Text($this->l('Halaman').' 1 / 1 - A5', $width - $margin, 15, 5.6, false, 'right', .45);

            return $this->buildPdf([$this->stream], $width, $height, $fonts);
        }

        return $this->continuedAgreement($document, $snapshot, $issuer);
    }

    /**
     * Flow oversized snapshots across A5 pages without dropping units or contractual clauses.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array{name: string}  $issuer
     */
    private function continuedAgreement(TransactionDocument $document, array $snapshot, array $issuer): string
    {
        $branch = is_array($snapshot['branch'] ?? null) ? $snapshot['branch'] : [];
        $customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];
        $source = is_array($snapshot['source'] ?? null) ? $snapshot['source'] : [];
        $financial = is_array($snapshot['financial'] ?? null) ? $snapshot['financial'] : [];
        $collaterals = is_array($snapshot['collaterals'] ?? null) ? $snapshot['collaterals'] : [];
        $pages = [];
        $y = 0.0;
        $this->stream = '';
        $newPage = function () use (&$pages, &$y, $branch): void {
            if ($this->stream !== '') {
                $pages[] = $this->stream;
            }
            $this->stream = '';
            $y = $this->a5Block($this->upper($this->value($branch['name'] ?? 'Together Kamera')), 20, 570, 379.53, 10, 12, true);
            $y = $this->a5Block(__('direct_rental.agreement_title').' / '.$this->documentNumber, 20, $y - 3, 379.53, 7.5, 10, true);
            $this->line(20, $y, 399.53, $y, .5);
            $y -= 15;
        };
        $write = function (string $text, bool $bold = false, float $size = 7.5) use (&$y, $newPage): void {
            foreach ($this->a5Wrap($text, 379.53, $size, $bold) as $line) {
                if ($y < 43) {
                    $newPage();
                }
                $this->a5Text($line, 20, $y, $size, $bold);
                $y -= $size + 3;
            }
            $y -= 3;
        };
        $section = function (string $title) use (&$y, $newPage, $write): void {
            if ($y < 80) {
                $newPage();
            }
            $y -= 5;
            $this->fillRect(20, $y - 3, 379.53, 14, .94);
            $write($title, true);
            $y -= 3;
        };
        $newPage();
        $write($this->branchContact($branch), false, 6.8);
        $write($this->l('Diterbitkan').': '.$this->dateTime($document->issued_at).' WIB / '.$this->l('Versi').' '.$document->version, false, 6.8);
        $section($this->l('DATA PENYEWA').' / '.$this->l('DATA SEWA'));
        $write($this->l('Nama').': '.$this->value($customer['name'] ?? null), true);
        $write($this->l('Telepon').': '.$this->value($customer['phone'] ?? null));
        $write($this->l('Alamat').': '.$this->customerAddress($customer));
        $write($this->l('No. Sewa').': '.$this->value($source['reference'] ?? null).' / '.$this->l('Status').': '.$this->displayValue('status', $source['status'] ?? null));
        $write($this->l('Mulai').': '.$this->dateTime($source['checked_out_at'] ?? null).' / '.$this->l('Kembali').': '.$this->dateTime($source['due_at'] ?? null));
        $section($this->l('BARANG SEWA'));
        foreach (is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [] as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $write(($index + 1).'. '.$this->value($item['description'] ?? null).' / '.$this->l('Qty').' '.(int) ($item['quantity'] ?? 0).' / '.$this->money((float) ($item['total_amount'] ?? 0)), true);
            foreach (is_array($item['assets'] ?? null) ? $item['assets'] : [] as $asset) {
                if (is_array($asset)) {
                    $write(sprintf($this->l('Unit %s | SN %s | kondisi %s'), $this->value($asset['asset_code'] ?? null), $this->value($asset['serial_number'] ?? null), $this->displayValue('condition', $asset['checkout_condition'] ?? null)), false, 7);
                }
            }
        }
        $section($this->l('RINGKASAN BIAYA'));
        foreach ([
            'Subtotal' => $financial['subtotal'] ?? 0,
            'Diskon' => $financial['discount_amount'] ?? 0,
            'Pajak' => $financial['tax_amount'] ?? 0,
            'Total sewa' => $financial['total_amount'] ?? 0,
            'Terbayar' => $financial['rental_paid'] ?? $financial['paid_amount'] ?? 0,
            'Sisa tagihan' => $financial['balance_due'] ?? 0,
            'Deposit jaminan' => $financial['deposit_amount'] ?? $financial['deposit_required'] ?? 0,
            'Denda' => $financial['late_fee_amount'] ?? 0,
            'Kerusakan' => $financial['damage_fee_amount'] ?? 0,
        ] as $label => $amount) {
            $write($this->l($label).': '.$this->money((float) $amount), in_array($label, ['Total sewa', 'Sisa tagihan'], true));
        }
        foreach ($collaterals as $collateral) {
            if (is_array($collateral)) {
                $write($this->l('JAMINAN').': '.$this->value($collateral['type'] ?? null).' '.$this->value($collateral['number'] ?? null).' / '.$this->value($collateral['holder_name'] ?? null).' / '.$this->displayValue('status', $collateral['status'] ?? null));
            }
        }
        foreach (['rights' => 'agreement_rights_obligations', 'terms' => 'agreement_terms'] as $group => $key) {
            $clauses = is_array($snapshot[$key] ?? null) ? $snapshot[$key] : [];
            if ($clauses === []) {
                continue;
            }
            $section($this->l($group === 'rights' ? 'HAK DAN KEWAJIBAN PENYEWA' : 'KETENTUAN SEWA'));
            foreach (array_values($clauses) as $index => $clause) {
                $write(($index + 1).'. '.$this->agreementClause($group, $clause));
            }
        }
        $guarantor = isset($collaterals[0]) && is_array($collaterals[0]) ? $this->value($collaterals[0]['holder_name'] ?? null) : '-';
        $signatures = [[$this->l('Karyawan / Petugas'), $issuer['name']], [$this->l('Penjamin'), $guarantor], [$this->l('Peminjam'), $this->value($customer['name'] ?? null)]];
        $names = array_map(fn (array $signature): array => $this->a5Wrap($signature[1], 112, 7, true), $signatures);
        $required = 45 + max(array_map(count(...), $names)) * 9;
        if ($y - $required < 43) {
            $newPage();
        }
        foreach ($signatures as $index => [$title, $name]) {
            $x = 20 + $index * 126.51;
            $this->a5Text($title, $x + 63.25, $y - 5, 7, false, 'center');
            $this->line($x + 7, $y - 32, $x + 119, $y - 32, .3);
            foreach ($names[$index] as $row => $line) {
                $this->a5Text($line, $x + 63.25, $y - 43 - $row * 9, 7, true, 'center');
            }
        }
        $pages[] = $this->stream;
        foreach ($pages as $index => $content) {
            $this->stream = $content;
            $this->line(20, 24, 399.53, 24, .2);
            $this->a5Text('Snapshot: '.substr($this->contentHash, 0, 16), 20, 15, 5.6, false, 'left', .45);
            $this->a5Text($this->l('Halaman').' '.($index + 1).' / '.count($pages).' - A5', 399.53, 15, 5.6, false, 'right', .45);
            $pages[$index] = $this->stream;
        }

        return $this->buildPdf($pages, 419.53, 595.28, $this->agreementFonts());
    }

    /** @return array<string, array{name: string, widths: list<int>, bbox: list<int>, ascent: int, descent: int, cap_height: int, length: int, data: string}> */
    private function agreementFonts(): array
    {
        /** @var array<string, array{name: string, widths: list<int>, bbox: list<int>, ascent: int, descent: int, cap_height: int, length: int, data: string}> $fonts */
        $fonts = json_decode((string) file_get_contents(resource_path('pdf/agreement-fonts.json')), true, 512, JSON_THROW_ON_ERROR);

        return $fonts;
    }

    private function a5Width(string $text, float $size, bool $bold = false): float
    {
        static $fonts = null;
        $fonts ??= $this->agreementFonts();
        $width = 0;
        foreach (str_split($this->ascii($text)) as $character) {
            $width += $fonts[$bold ? 'bold' : 'regular']['widths'][ord($character) - 32] ?? 0;
        }

        return $width * $size / 1000;
    }

    /** @return list<string> */
    private function a5Wrap(string $text, float $width, float $size, bool $bold = false): array
    {
        $words = preg_split('/\s+/', trim($this->ascii($text))) ?: [''];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($this->a5Width($candidate, $size, $bold) <= $width) {
                $line = $candidate;

                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
                $line = '';
            }
            foreach (str_split($word) as $character) {
                if ($line !== '' && $this->a5Width($line.$character, $size, $bold) > $width) {
                    $lines[] = $line;
                    $line = '';
                }
                $line .= $character;
            }
        }
        if ($line !== '' || $lines === []) {
            $lines[] = $line;
        }

        return $lines;
    }

    private function a5Text(string $text, float $x, float $y, float $size, bool $bold = false, string $align = 'left', float $gray = .1): void
    {
        $width = $this->a5Width($text, $size, $bold);
        $x -= $align === 'right' ? $width : ($align === 'center' ? $width / 2 : 0);
        $this->stream .= sprintf("q %.2F g\n", $gray);
        $this->text($text, $x, $y, $size, $bold);
        $this->stream .= "Q\n";
    }

    private function a5Block(string $text, float $x, float $y, float $width, float $size, float $leading, bool $bold = false, float $gray = .1): float
    {
        foreach ($this->a5Wrap($text, $width, $size, $bold) as $line) {
            $this->a5Text($line, $x, $y, $size, $bold, 'left', $gray);
            $y -= $leading;
        }

        return $y;
    }

    /** @param array<string,mixed> $snapshot
     *  @param array{name:string} $issuer */
    private function agreement(array $snapshot, array $issuer): void
    {
        $source = is_array($snapshot['source'] ?? null) ? $snapshot['source'] : [];
        $customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];
        $financial = is_array($snapshot['financial'] ?? null) ? $snapshot['financial'] : [];
        $collaterals = is_array($snapshot['collaterals'] ?? null) ? $snapshot['collaterals'] : [];

        $this->infoPair(
            $this->l('DATA PENYEWA'),
            [
                [$this->l('Nama'), $this->value($customer['name'] ?? null)],
                [$this->l('No. Pelanggan'), $this->value($customer['customer_number'] ?? null)],
                [$this->l('Telepon'), $this->value($customer['phone'] ?? null)],
                [$this->l('Alamat'), $this->customerAddress($customer)],
            ],
            $this->l('DATA SEWA'),
            [
                [$this->l('No. Sewa'), $this->value($source['reference'] ?? null)],
                [$this->l('Tanggal sewa'), $this->dateTime($source['checked_out_at'] ?? null)],
                [$this->l('Tanggal kembali'), $this->dateTime($source['due_at'] ?? null)],
                [$this->l('Booking'), $this->value($source['booking_reference'] ?? null)],
            ],
        );

        $this->section($this->l('BARANG SEWA'));
        $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $this->paragraph(sprintf(
                '%d. %s | %s %d | %s',
                $i + 1,
                $this->value($item['description'] ?? null),
                $this->l('Qty'),
                (int) ($item['quantity'] ?? 0),
                $this->money((float) ($item['total_amount'] ?? 0)),
            ), 8, 10);

            $assets = is_array($item['assets'] ?? null) ? $item['assets'] : [];
            foreach ($assets as $asset) {
                if (! is_array($asset)) {
                    continue;
                }
                $this->paragraph(sprintf(
                    $this->l('Unit %s | SN %s | kondisi %s'),
                    $this->value($asset['asset_code'] ?? null),
                    $this->value($asset['serial_number'] ?? null),
                    $this->displayValue('condition', $asset['checkout_condition'] ?? null),
                ), 7.4, 9, self::MX + 8);
            }
        }

        if ($collaterals !== []) {
            $this->section($this->l('JAMINAN'));
            foreach ($collaterals as $i => $collateral) {
                if (! is_array($collateral)) {
                    continue;
                }
                $this->paragraph(sprintf(
                    $this->l('%d. %s - %s | atas nama %s | status %s'),
                    $i + 1,
                    $this->upper($this->value($collateral['type'] ?? null)),
                    $this->value($collateral['number'] ?? null),
                    $this->value($collateral['holder_name'] ?? null),
                    $this->displayValue('status', $collateral['status'] ?? null),
                ), 8, 10);
            }
        }

        $this->section($this->l('RINGKASAN BIAYA'));
        foreach ([
            $this->l('Total sewa') => $financial['total_amount'] ?? null,
            $this->l('Terbayar') => $financial['rental_paid'] ?? $financial['paid_amount'] ?? null,
            $this->l('Sisa tagihan') => $financial['balance_due'] ?? null,
            $this->l('Deposit') => $financial['deposit_amount'] ?? $financial['deposit_required'] ?? null,
            $this->l('Denda') => $financial['late_fee_amount'] ?? null,
            $this->l('Kerusakan') => $financial['damage_fee_amount'] ?? null,
        ] as $label => $value) {
            if ($value === null) {
                continue;
            }
            $this->text($label, self::MX + 6, $this->y, 8);
            $this->text($this->money((float) $value), 185, $this->y, 8, true, 'right', 100);
            $this->y -= 11;
        }

        $this->y -= 5;
        $this->terms($snapshot);

        $guarantor = '-';
        if (isset($collaterals[0]) && is_array($collaterals[0])) {
            $guarantor = $this->value($collaterals[0]['holder_name'] ?? null);
        }

        $this->sign3(
            [$this->l('Karyawan / Petugas'), $issuer['name']],
            [$this->l('Penjamin'), $guarantor],
            [$this->l('Peminjam'), $this->value($customer['name'] ?? null)],
        );
    }

    /** @param array<string,mixed> $snapshot */
    private function terms(array $snapshot): void
    {
        $rights = is_array($snapshot['agreement_rights_obligations'] ?? null)
            ? $snapshot['agreement_rights_obligations']
            : [];
        $terms = is_array($snapshot['agreement_terms'] ?? null) ? $snapshot['agreement_terms'] : [];

        // Dokumen lama hanya mempunyai agreement_terms. Pertahankan layout lama
        // agar versi historis tetap dapat dirender tanpa mengubah snapshot-nya.
        if ($rights === []) {
            $this->legacyTerms(array_values($terms));

            return;
        }

        if ($terms === []) {
            return;
        }

        $leftLines = $this->termLines(array_map(fn (mixed $clause): string => $this->agreementClause('rights', $clause), array_values($rights)), 238, 6.3, 1);
        $rightLines = $this->termLines(array_map(fn (mixed $clause): string => $this->agreementClause('terms', $clause), array_values($terms)), 238, 6.3, 1);
        $rows = max(count($leftLines), count($rightLines));
        $lineHeight = 8.0;
        $headerHeight = 22.0;
        $height = $headerHeight + max(64.0, $rows * $lineHeight);

        if ($this->y - $height < self::BOTTOM + 105) {
            $this->finishPage();
            $this->newPage();
            $this->continuedHeader($this->l('SURAT PERJANJIAN SEWA - HAK, KEWAJIBAN & KETENTUAN'));
        }

        $start = $this->y;
        $mid = self::W / 2;
        $leftX = self::MX;
        $rightX = $mid + 4;
        $leftWidth = $mid - self::MX - 4;
        $rightWidth = self::W - self::MX - $rightX;

        $this->fillRect($leftX, $start - 18, $leftWidth, 18, .94);
        $this->fillRect($rightX, $start - 18, $rightWidth, 18, .94);
        $this->text($this->l('HAK DAN KEWAJIBAN PENYEWA'), $leftX + 5, $start - 12, 7.2, true);
        $this->text($this->l('KETENTUAN SEWA'), $rightX + 5, $start - 12, 7.2, true);

        $textStart = $start - $headerHeight - 4;
        $bottom = $textStart - max(64.0, $rows * $lineHeight) + 4;
        $this->line($mid, $start + 2, $mid, $bottom, .2);

        for ($i = 0; $i < $rows; $i++) {
            if (isset($leftLines[$i])) {
                $this->text($leftLines[$i], $leftX + 4, $textStart - $i * $lineHeight, 6.3);
            }
            if (isset($rightLines[$i])) {
                $this->text($rightLines[$i], $rightX + 4, $textStart - $i * $lineHeight, 6.3);
            }
        }

        $this->y = $start - $height - 8;
    }

    /** @param list<mixed> $terms */
    private function legacyTerms(array $terms): void
    {
        if ($terms === []) {
            return;
        }

        $this->ensure(150);
        $this->section($this->l('HAK, KEWAJIBAN & KETENTUAN SEWA'));
        $split = (int) ceil(count($terms) / 2);
        $left = array_slice($terms, 0, $split);
        $right = array_slice($terms, $split);

        $ll = $this->termLines(array_map(fn (mixed $clause): string => $this->agreementClause('terms', $clause), $left), 230, 7.1, 1);
        $rr = $this->termLines(array_map(fn (mixed $clause): string => $this->agreementClause('terms', $clause), $right), 230, 7.1, $split + 1);
        $rows = max(count($ll), count($rr));
        $height = max(54, $rows * 9);

        if ($this->y - $height < self::BOTTOM + 90) {
            $this->finishPage();
            $this->newPage();
            $this->continuedHeader($this->l('HAK, KEWAJIBAN & KETENTUAN SEWA'));
        }

        $start = $this->y;
        $mid = self::W / 2;
        $this->line($mid, $start + 4, $mid, $start - $height + 4, .2);

        for ($i = 0; $i < $rows; $i++) {
            if (isset($ll[$i])) {
                $this->text($ll[$i], self::MX + 4, $start - $i * 9, 7.1);
            }
            if (isset($rr[$i])) {
                $this->text($rr[$i], $mid + 8, $start - $i * 9, 7.1);
            }
        }
        $this->y = $start - $height - 5;
    }

    /** @param list<mixed> $terms
     *  @return list<string> */
    private function termLines(array $terms, float $width, float $size, int $start): array
    {
        $out = [];
        foreach ($terms as $offset => $term) {
            foreach ($this->wrap(($start + $offset).'. '.(string) $term, $width, $size) as $line) {
                $out[] = $line;
            }
            $out[] = '';
        }

        return $out;
    }

    /** @param list<array{0:string,1:string}> $left
     *  @param list<array{0:string,1:string}> $right */
    private function infoPair(string $lt, array $left, string $rt, array $right): void
    {
        $cw = self::W - 2 * self::MX;
        $gap = 16.0;
        $col = ($cw - $gap) / 2;
        $lx = self::MX;
        $rx = self::MX + $col + $gap;

        $lp = $this->prepareRows($left, $col - 12);
        $rp = $this->prepareRows($right, $col - 12);
        $rows = max(count($lp), count($rp));
        $height = 28 + $rows * 10 + 8;

        $this->ensure($height + 10);
        $top = $this->y;
        $this->rect($lx, $top - $height, $col, $height, false, .3);
        $this->rect($rx, $top - $height, $col, $height, false, .3);
        $this->fillRect($lx, $top - 20, $col, 20, .94);
        $this->fillRect($rx, $top - 20, $col, 20, .94);
        $this->text($lt, $lx + 6, $top - 14, 8, true);
        $this->text($rt, $rx + 6, $top - 14, 8, true);

        $yy = $top - 31;
        for ($i = 0; $i < $rows; $i++) {
            if (isset($lp[$i])) {
                $this->text($lp[$i], $lx + 6, $yy, 7.5);
            }
            if (isset($rp[$i])) {
                $this->text($rp[$i], $rx + 6, $yy, 7.5);
            }
            $yy -= 10;
        }

        $this->y = $top - $height - 12;
    }

    /** @param list<array{0:string,1:string}> $rows
     *  @return list<string> */
    private function prepareRows(array $rows, float $width): array
    {
        $out = [];
        foreach ($rows as [$label, $value]) {
            if ($value === '-' && in_array($label, ['Selesai', 'Booking'], true)) {
                continue;
            }
            $prefix = $label.': ';
            $available = max(60.0, $width - $this->textWidth($prefix, 7.5));
            $wrapped = $this->wrap($value, $available, 7.5);
            foreach ($wrapped as $i => $line) {
                $out[] = $i === 0 ? $prefix.$line : str_repeat(' ', strlen($prefix)).$line;
            }
        }

        return $out;
    }

    /** @param list<string> $labels
     *  @param list<int|float> $widths */
    private function tableHeader(array $labels, array $widths): void
    {
        $this->ensure(28);
        $x = self::MX;
        $this->fillRect(self::MX, $this->y - 20, self::W - 2 * self::MX, 20, .93);
        foreach ($labels as $i => $label) {
            $this->text($label, $x + 5, $this->y - 14, 8, true);
            $x += (float) ($widths[$i] ?? 0);
        }
        $this->y -= 27;
    }

    private function section(string $title): void
    {
        $this->ensure(28);
        $this->fillRect(self::MX, $this->y - 19, self::W - 2 * self::MX, 19, .94);
        $this->text($title, self::MX + 6, $this->y - 13, 8.2, true);
        $this->y -= 27;
    }

    private function sign2(string $l1, string $n1, string $l2, string $n2): void
    {
        $this->ensure(88);
        $cw = self::W - 2 * self::MX;
        $col = $cw / 2;
        $this->text($l1, self::MX, $this->y, 8, false, 'center', $col);
        $this->text($l2, self::MX + $col, $this->y, 8, false, 'center', $col);
        $this->y -= 48;
        $this->line(self::MX + 28, $this->y, self::MX + $col - 28, $this->y, .4);
        $this->line(self::MX + $col + 28, $this->y, self::W - self::MX - 28, $this->y, .4);
        $this->y -= 12;
        $this->text($n1, self::MX, $this->y, 8, true, 'center', $col);
        $this->text($n2, self::MX + $col, $this->y, 8, true, 'center', $col);
        $this->y -= 15;
    }

    /** @param array{0:string,1:string} $a
     *  @param array{0:string,1:string} $b
     *  @param array{0:string,1:string} $c */
    private function sign3(array $a, array $b, array $c): void
    {
        $this->ensure(96);
        $cw = self::W - 2 * self::MX;
        $col = $cw / 3;
        foreach ([$a, $b, $c] as $i => $sig) {
            $x = self::MX + $i * $col;
            $this->text($sig[0], $x, $this->y, 7.5, false, 'center', $col);
        }
        $this->y -= 50;
        foreach ([$a, $b, $c] as $i => $sig) {
            $x = self::MX + $i * $col;
            $this->line($x + 12, $this->y, $x + $col - 12, $this->y, .4);
            $this->text($sig[1], $x, $this->y - 12, 7.2, true, 'center', $col);
        }
        $this->y -= 28;
    }

    private function paragraph(string $text, float $size = 8, float $lh = 10, float $x = self::MX): void
    {
        $width = self::W - self::MX - $x;
        foreach ($this->wrap($text, $width, $size) as $line) {
            $this->ensure($lh + 2);
            $this->text($line, $x, $this->y, $size);
            $this->y -= $lh;
        }
    }

    private function label(string $label, string $value, float $x, float $labelWidth, float $size = 8.5): void
    {
        $this->text($label, $x, $this->y, $size);
        $this->text(':', $x + $labelWidth - 7, $this->y, $size);
        $this->text($value, $x + $labelWidth, $this->y, $size);
    }

    /** @param array<string,mixed> $customer */
    private function customerAddress(array $customer): string
    {
        $address = is_array($customer['address'] ?? null) ? $customer['address'] : [];
        $parts = array_filter([
            $address['address'] ?? null,
            $address['village'] ?? null,
            $address['district'] ?? null,
            $address['city'] ?? null,
            $address['province'] ?? null,
            $address['postal_code'] ?? null,
        ], static fn (mixed $v): bool => is_string($v) && trim($v) !== '');

        return $parts === [] ? '-' : implode(', ', $parts);
    }

    /** @param array<string,mixed> $branch */
    private function branchContact(array $branch): string
    {
        $address = array_filter([
            $branch['address'] ?? null,
            $branch['city'] ?? null,
            $branch['province'] ?? null,
            $branch['postal_code'] ?? null,
        ], static fn (mixed $v): bool => is_string($v) && trim($v) !== '');
        $contact = array_filter([
            $branch['phone'] ?? null,
            $branch['email'] ?? null,
        ], static fn (mixed $v): bool => is_string($v) && trim($v) !== '');

        $parts = [];
        if ($address !== []) {
            $parts[] = implode(', ', $address);
        }
        if ($contact !== []) {
            $parts[] = implode(' | ', $contact);
        }

        return $parts === [] ? '-' : implode(' - ', $parts);
    }

    /** @return array{name:string} */
    private function issuer(TransactionDocument $document): array
    {
        $snapshot = $document->issuer_name_snapshot;
        if (is_string($snapshot) && trim($snapshot) !== '') {
            return ['name' => $snapshot];
        }

        $user = $document->relationLoaded('issuer')
            ? $document->issuer
            : User::query()->find($document->issued_by);
        $name = $user?->name;
        if (! is_string($name) || trim($name) === '') {
            $name = 'User #'.(string) $document->issued_by;
        }

        return ['name' => $name];
    }

    private function ensure(float $required): void
    {
        if ($this->y - $required >= self::BOTTOM + 34) {
            return;
        }
        $this->finishPage();
        $this->newPage();
        $this->continuedHeader('LANJUTAN DOKUMEN');
    }

    private function newPage(): void
    {
        $this->stream = '';
        $this->y = self::TOP;
        $this->page++;
    }

    private function continuedHeader(string $title): void
    {
        $cw = self::W - 2 * self::MX;
        $this->text($title, self::MX, $this->y, 9, true);
        $this->text($this->documentNumber, self::MX, $this->y, 8, false, 'right', $cw);
        $this->y -= 12;
        $this->line(self::MX, $this->y, self::W - self::MX, $this->y, .5);
        $this->y -= 14;
    }

    private function finishPage(): void
    {
        if ($this->stream === '') {
            return;
        }
        $fy = 24.0;
        $this->line(self::MX, $fy + 12, self::W - self::MX, $fy + 12, .2);
        $hash = $this->contentHash === '' ? '-' : substr($this->contentHash, 0, 16).'...';
        $this->text('Snapshot: '.$hash, self::MX, $fy, 6.3);
        $this->text($this->l('Halaman').' '.$this->page, self::MX, $fy, 6.3, false, 'right', self::W - 2 * self::MX);
        $this->pages[] = $this->stream;
        $this->stream = '';
    }

    private function text(string $text, float $x, float $y, float $size = 8, bool $bold = false, string $align = 'left', float $width = 0): void
    {
        $text = $this->ascii($text);
        if ($align !== 'left' && $width > 0) {
            $tw = $this->textWidth($text, $size);
            $x += $align === 'center' ? max(0, ($width - $tw) / 2) : max(0, $width - $tw);
        }
        $font = $bold ? 'F2' : 'F1';
        $this->stream .= sprintf(
            "BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $font, $size, $x, $y, $this->pdfEscape($text),
        );
    }

    private function line(float $x1, float $y1, float $x2, float $y2, float $width = .5): void
    {
        $this->stream .= sprintf("%.2F w %.2F %.2F m %.2F %.2F l S\n", $width, $x1, $y1, $x2, $y2);
    }

    private function rect(float $x, float $y, float $w, float $h, bool $fill = false, float $stroke = .5): void
    {
        if ($fill) {
            $this->fillRect($x, $y, $w, $h);

            return;
        }
        $this->stream .= sprintf("%.2F w %.2F %.2F %.2F %.2F re S\n", $stroke, $x, $y, $w, $h);
    }

    private function fillRect(float $x, float $y, float $w, float $h, float $gray = .95): void
    {
        $this->stream .= sprintf("q %.3F g %.2F %.2F %.2F %.2F re f Q\n", $gray, $x, $y, $w, $h);
    }

    /** @return list<string> */
    private function wrap(string $text, float $width, float $size): array
    {
        $text = trim($this->ascii($text));
        if ($text === '') {
            return [''];
        }
        $max = max(8, (int) floor($width / max(3.1, $size * .52)));

        return explode("\n", wordwrap($text, $max, "\n", true));
    }

    private function textWidth(string $text, float $size): float
    {
        return strlen($this->ascii($text)) * $size * .49;
    }

    private function truncate(string $value, int $chars): string
    {
        $value = $this->ascii($value);

        return strlen($value) <= $chars ? $value : substr($value, 0, $chars - 1).'~';
    }

    private function money(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }

    private function dateTime(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)
                ->timezone((string) config('app.timezone', 'Asia/Jakarta'))
                ->format('d/m/Y H:i');
        }
        if (! is_string($value) || trim($value) === '') {
            return '-';
        }
        try {
            return CarbonImmutable::parse($value)
                ->timezone((string) config('app.timezone', 'Asia/Jakarta'))
                ->format('d/m/Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }

    private function value(mixed $value): string
    {
        return $value === null || $value === '' ? '-' : (string) $value;
    }

    private function upper(string $value): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($value) : strtoupper($value);
    }

    private function ascii(string $value): string
    {
        $value = str_replace(['–', '—', '•', '“', '”', '‘', '’', "\t"], ['-', '-', '-', '"', '"', "'", "'", ' '], $value);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
    }

    /**
     * @param  list<string>  $pages
     * @param  array<string, array{name: string, widths: list<int>, bbox: list<int>, ascent: int, descent: int, cap_height: int, length: int, data: string}>  $embeddedFonts
     */
    private function buildPdf(array $pages, float $pageWidth = self::W, float $pageHeight = self::H, array $embeddedFonts = []): string
    {
        $objects = [];
        $pageIds = [];
        $contentIds = [];
        $next = $embeddedFonts === [] ? 5 : 9;

        foreach ($pages as $_) {
            $pageIds[] = $next;
            $contentIds[] = $next + 1;
            $next += 2;
        }

        $kids = implode(' ', array_map(static fn (int $id): string => $id.' 0 R', $pageIds));
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', $kids, count($pages));
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        if ($embeddedFonts !== []) {
            foreach (['regular', 'bold'] as $index => $style) {
                $font = $embeddedFonts[$style];
                $fontId = 3 + $index;
                $descriptorId = 5 + $index;
                $fileId = 7 + $index;
                $objects[$fontId] = sprintf('<< /Type /Font /Subtype /TrueType /BaseFont /%s /FirstChar 32 /LastChar 126 /Widths [%s] /Encoding /WinAnsiEncoding /FontDescriptor %d 0 R >>', $font['name'], implode(' ', $font['widths']), $descriptorId);
                $objects[$descriptorId] = sprintf('<< /Type /FontDescriptor /FontName /%s /Flags 32 /FontBBox [%s] /ItalicAngle 0 /Ascent %d /Descent %d /CapHeight %d /StemV %d /FontFile2 %d 0 R >>', $font['name'], implode(' ', $font['bbox']), $font['ascent'], $font['descent'], $font['cap_height'], $index === 0 ? 80 : 120, $fileId);
                $bytes = base64_decode($font['data'], true);
                if ($bytes === false) {
                    throw new \RuntimeException('Invalid embedded agreement font.');
                }
                $objects[$fileId] = '<< /Length '.strlen($bytes).' /Length1 '.$font['length']." /Filter /FlateDecode >>\nstream\n".$bytes."\nendstream";
            }
        }

        foreach ($pages as $i => $content) {
            $pid = $pageIds[$i];
            $cid = $contentIds[$i];
            $objects[$pid] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $pageWidth, $pageHeight, $cid,
            );
            $objects[$cid] = '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 ".$size."\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size ".$size." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }
}
