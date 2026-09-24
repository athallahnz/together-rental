<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $document->document_number }}</title>
    <style>
        @page { margin: 18mm 17mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; line-height: 1.35; color: #111; }
        h1, h2, h3, p { margin: 0; }
        .header { text-align: center; margin-bottom: 10px; }
        .brand { font-size: 15px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; }
        .branch { margin-top: 2px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .contact { margin-top: 2px; font-size: 8px; }
        .rule { border-top: 1px solid #111; margin: 7px 0; }
        .title { text-align: center; font-size: 13px; font-weight: 700; letter-spacing: .8px; margin: 7px 0 9px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { vertical-align: top; padding: 1px 2px; }
        .meta .label { width: 86px; color: #333; }
        .meta .sep { width: 7px; }
        .two-col td { width: 50%; vertical-align: top; }
        .box { border: 1px solid #111; padding: 7px; }
        .section-title { font-size: 9px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; }
        .items { margin-top: 8px; }
        .items th, .items td { border: 1px solid #111; padding: 4px 5px; vertical-align: top; }
        .items th { font-size: 8px; text-transform: uppercase; background: #f2f2f2; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .summary { width: 48%; margin-left: auto; margin-top: 7px; }
        .summary td { padding: 2px 3px; }
        .summary .total td { border-top: 1px solid #111; padding-top: 4px; font-weight: 700; }
        .terms { margin-top: 9px; }
        .terms td { width: 50%; vertical-align: top; padding: 0 7px 0 0; }
        .terms ol { margin: 0; padding-left: 16px; }
        .terms li { margin-bottom: 3px; text-align: justify; }
        .signatures { margin-top: 22px; page-break-inside: avoid; }
        .signatures td { width: 33.333%; text-align: center; vertical-align: bottom; padding: 0 8px; }
        .sign-space { height: 44px; }
        .name { font-weight: 700; text-decoration: underline; }
        .muted { color: #555; }
        .snapshot { margin-top: 12px; padding-top: 5px; border-top: 1px dashed #777; font-size: 7px; color: #555; }
        .badge { display: inline-block; border: 1px solid #444; padding: 1px 4px; font-size: 7px; }
    </style>
</head>
<body>
@php
    $branch = is_array($snapshot['branch'] ?? null) ? $snapshot['branch'] : [];
    $source = is_array($snapshot['source'] ?? null) ? $snapshot['source'] : [];
    $customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];
    $customerAddress = is_array($customer['address'] ?? null) ? $customer['address'] : [];
    $financial = is_array($snapshot['financial'] ?? null) ? $snapshot['financial'] : [];
    $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
    $collaterals = is_array($snapshot['collaterals'] ?? null) ? $snapshot['collaterals'] : [];
    $payment = is_array($snapshot['payment'] ?? null) ? $snapshot['payment'] : [];
    $related = is_array($snapshot['related'] ?? null) ? $snapshot['related'] : [];
    $terms = is_array($snapshot['agreement_terms'] ?? null) ? $snapshot['agreement_terms'] : [];
    $title = match ($document->document_type) {
        'invoice' => 'INVOICE',
        'receipt' => 'NOTA PEMBAYARAN',
        'agreement' => 'PERJANJIAN SEWA / RENTAL AGREEMENT',
        default => 'DOKUMEN TRANSAKSI',
    };
    $rupiah = static fn ($value): string => 'Rp '.number_format((float) ($value ?? 0), 0, ',', '.');
    $fmtDate = static function ($value, bool $withTime = true): string {
        if (!is_string($value) || trim($value) === '') return '-';
        try {
            $date = \Carbon\CarbonImmutable::parse($value)->timezone('Asia/Jakarta');
            return $date->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    };
    $branchAddress = collect([
        $branch['address'] ?? null,
        $branch['city'] ?? null,
        $branch['province'] ?? null,
        $branch['postal_code'] ?? null,
    ])->filter()->implode(', ');
    $customerAddressText = collect([
        $customerAddress['address'] ?? null,
        $customerAddress['village'] ?? null,
        $customerAddress['district'] ?? null,
        $customerAddress['city'] ?? null,
        $customerAddress['province'] ?? null,
        $customerAddress['postal_code'] ?? null,
    ])->filter()->implode(', ');
    $guarantorName = '-';
    foreach ($collaterals as $collateral) {
        if (is_array($collateral) && !empty($collateral['holder_name'])) {
            $guarantorName = (string) $collateral['holder_name'];
            break;
        }
    }
@endphp

<div class="header">
    <div class="brand">{{ strtoupper((string) ($branch['name'] ?? 'TOGETHER KAMERA')) }}</div>
    @if($branchAddress !== '')
        <div class="branch">{{ $branchAddress }}</div>
    @endif
    <div class="contact">
        {{ collect([$branch['phone'] ?? null, $branch['email'] ?? null])->filter()->implode(' · ') }}
    </div>
</div>
<div class="rule"></div>
<div class="title">{{ $title }}</div>

<table class="two-col">
    <tr>
        <td style="padding-right: 7px;">
            <div class="box">
                <div class="section-title">Data Pelanggan</div>
                <table class="meta">
                    <tr><td class="label">Nama</td><td class="sep">:</td><td>{{ $customer['name'] ?? '-' }}</td></tr>
                    <tr><td class="label">No. Pelanggan</td><td class="sep">:</td><td>{{ $customer['customer_number'] ?? '-' }}</td></tr>
                    <tr><td class="label">No. Telp</td><td class="sep">:</td><td>{{ $customer['phone'] ?? '-' }}</td></tr>
                    <tr><td class="label">Alamat</td><td class="sep">:</td><td>{{ $customerAddressText !== '' ? $customerAddressText : '-' }}</td></tr>
                </table>
            </div>
        </td>
        <td style="padding-left: 7px;">
            <div class="box">
                <div class="section-title">Informasi Dokumen</div>
                <table class="meta">
                    <tr><td class="label">No. Dokumen</td><td class="sep">:</td><td>{{ $document->document_number }}</td></tr>
                    <tr><td class="label">Versi</td><td class="sep">:</td><td>{{ $document->version }}</td></tr>
                    <tr><td class="label">Referensi</td><td class="sep">:</td><td>{{ $source['reference'] ?? $document->source_reference }}</td></tr>
                    <tr><td class="label">Status</td><td class="sep">:</td><td>{{ strtoupper((string) ($source['status'] ?? '-')) }}</td></tr>
                    <tr><td class="label">Diterbitkan</td><td class="sep">:</td><td>{{ $fmtDate($document->issued_at?->toIso8601String()) }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

@if($document->document_type === 'receipt')
    <div class="box" style="margin-top: 9px;">
        <div class="section-title">Bukti Pembayaran</div>
        <table class="meta">
            <tr><td class="label">No. Payment</td><td class="sep">:</td><td>{{ $payment['payment_number'] ?? $source['reference'] ?? '-' }}</td></tr>
            <tr><td class="label">Tanggal Bayar</td><td class="sep">:</td><td>{{ $fmtDate($payment['paid_at'] ?? $source['paid_at'] ?? null) }}</td></tr>
            <tr><td class="label">Metode</td><td class="sep">:</td><td>{{ data_get($payment, 'method.name', '-') }}</td></tr>
            <tr><td class="label">Referensi</td><td class="sep">:</td><td>{{ $payment['external_reference'] ?? '-' }}</td></tr>
            <tr><td class="label">Booking</td><td class="sep">:</td><td>{{ $related['booking_reference'] ?? '-' }}</td></tr>
            <tr><td class="label">Rental</td><td class="sep">:</td><td>{{ $related['rental_reference'] ?? '-' }}</td></tr>
            <tr><td class="label"><strong>Nominal</strong></td><td class="sep">:</td><td><strong>{{ $rupiah($payment['amount'] ?? 0) }}</strong></td></tr>
        </table>
    </div>
@else
    <table class="items">
        <thead>
            <tr>
                <th style="width: 6%;">No</th>
                <th>Barang Sewa</th>
                <th style="width: 9%;">Qty</th>
                <th style="width: 18%;">Harga</th>
                <th style="width: 20%;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
        @forelse($items as $index => $item)
            @php($item = is_array($item) ? $item : [])
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item['description'] ?? '-' }}</strong>
                    @if(!empty($item['sku']))<div class="muted">SKU: {{ $item['sku'] }}</div>@endif
                    @if(is_array($item['assets'] ?? null) && count($item['assets']) > 0)
                        <div class="muted">
                            Unit:
                            @foreach($item['assets'] as $asset)
                                @if(is_array($asset))
                                    {{ $asset['asset_code'] ?? '-' }}@if(!empty($asset['serial_number'])) ({{ $asset['serial_number'] }})@endif{{ !$loop->last ? ', ' : '' }}
                                @endif
                            @endforeach
                        </div>
                    @endif
                </td>
                <td class="center">{{ (int) ($item['quantity'] ?? 0) }}</td>
                <td class="num">{{ $rupiah($item['unit_rate'] ?? 0) }}</td>
                <td class="num">{{ $rupiah($item['total_amount'] ?? 0) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="center muted">Tidak ada item.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr><td>Subtotal</td><td class="num">{{ $rupiah($financial['subtotal'] ?? 0) }}</td></tr>
        <tr><td>Diskon</td><td class="num">{{ $rupiah($financial['discount_amount'] ?? 0) }}</td></tr>
        @if(array_key_exists('deposit_required', $financial))
            <tr><td>Deposit/Jaminan</td><td class="num">{{ $rupiah($financial['deposit_required'] ?? 0) }}</td></tr>
        @elseif(array_key_exists('deposit_amount', $financial))
            <tr><td>Deposit/Jaminan</td><td class="num">{{ $rupiah($financial['deposit_amount'] ?? 0) }}</td></tr>
        @endif
        @if(array_key_exists('late_fee_amount', $financial))
            <tr><td>Denda</td><td class="num">{{ $rupiah(((float) ($financial['late_fee_amount'] ?? 0)) + ((float) ($financial['damage_fee_amount'] ?? 0))) }}</td></tr>
        @endif
        <tr class="total"><td>Total</td><td class="num">{{ $rupiah($financial['total_amount'] ?? 0) }}</td></tr>
        <tr><td>Dibayar</td><td class="num">{{ $rupiah($financial['paid_amount'] ?? $financial['rental_paid'] ?? 0) }}</td></tr>
        <tr><td><strong>Sisa</strong></td><td class="num"><strong>{{ $rupiah($financial['balance_due'] ?? 0) }}</strong></td></tr>
    </table>
@endif

@if($document->document_type === 'agreement')
    @if(count($collaterals) > 0)
        <div class="box" style="margin-top: 8px;">
            <div class="section-title">Jaminan</div>
            @foreach($collaterals as $index => $collateral)
                @if(is_array($collateral))
                    <div>{{ $index + 1 }}. {{ strtoupper((string) ($collateral['type'] ?? 'Jaminan')) }} — {{ $collateral['holder_name'] ?? '-' }} ({{ $collateral['number'] ?? '-' }})</div>
                @endif
            @endforeach
        </div>
    @endif

    <div class="terms">
        <div class="section-title">Hak, Kewajiban & Ketentuan Sewa</div>
        @php($split = (int) ceil(max(1, count($terms)) / 2))
        <table>
            <tr>
                <td>
                    <ol>
                    @foreach(array_slice($terms, 0, $split) as $term)
                        <li>{{ $term }}</li>
                    @endforeach
                    </ol>
                </td>
                <td>
                    <ol start="{{ $split + 1 }}">
                    @foreach(array_slice($terms, $split) as $term)
                        <li>{{ $term }}</li>
                    @endforeach
                    </ol>
                </td>
            </tr>
        </table>
    </div>
@endif

<table class="signatures">
    <tr>
        @if($document->document_type === 'agreement')
            <td>
                <div>Karyawan / Petugas</div><div class="sign-space"></div>
                <div class="name">{{ $issuerName }}</div>
                <div class="muted">Penerbit dokumen</div>
            </td>
            <td>
                <div>Penjamin</div><div class="sign-space"></div>
                <div class="name">{{ $guarantorName }}</div>
            </td>
            <td>
                <div>Peminjam</div><div class="sign-space"></div>
                <div class="name">{{ $customer['name'] ?? '-' }}</div>
            </td>
        @elseif($document->document_type === 'receipt')
            <td></td>
            <td>
                <div>Diterima oleh</div><div class="sign-space"></div>
                <div class="name">{{ $issuerName }}</div>
                <div class="muted">Petugas / penerbit</div>
            </td>
            <td>
                <div>Pelanggan</div><div class="sign-space"></div>
                <div class="name">{{ $customer['name'] ?? '-' }}</div>
            </td>
        @else
            <td></td>
            <td>
                <div>Diterbitkan oleh</div><div class="sign-space"></div>
                <div class="name">{{ $issuerName }}</div>
                <div class="muted">Petugas / penerbit</div>
            </td>
            <td>
                <div>Pelanggan</div><div class="sign-space"></div>
                <div class="name">{{ $customer['name'] ?? '-' }}</div>
            </td>
        @endif
    </tr>
</table>

<div class="snapshot">
    Dokumen snapshot immutable · SHA-256 {{ $document->content_hash }} · {{ $document->document_number }} / V{{ $document->version }}.
    Nama petugas merupakan user yang menerbitkan versi dokumen ini.
</div>
</body>
</html>
