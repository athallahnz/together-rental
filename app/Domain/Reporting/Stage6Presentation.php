<?php

namespace App\Domain\Reporting;

/** Localizes report presentation after aggregation; raw rows and calculations remain intact. */
final class Stage6Presentation
{
    /** @var array<string, string> */
    private const ID = [
        'Rental tercatat' => 'Penyewaan tercatat',
        'Nilai rental' => 'Nilai penyewaan',
        'Rental valid pada periode.' => 'Penyewaan valid pada periode.',
        'Total nilai kontrak rental pada periode.' => 'Total nilai kontrak penyewaan pada periode.',
        'Booking, Rental & Return' => 'Pemesanan, Penyewaan & Pengembalian',
        'Rental & Return' => 'Penyewaan & Pengembalian',
        'Booking' => 'Pemesanan',
        'Rental' => 'Penyewaan',
        'Return' => 'Pengembalian',
        'Payment masuk' => 'Pembayaran masuk',
        'Payment completed berarah masuk.' => 'Pembayaran masuk yang telah selesai.',
        'Masuk dikurangi keluar dan refund paid.' => 'Kas masuk dikurangi kas keluar dan pengembalian dana yang dibayar.',
        'Refund paid' => 'Pengembalian dana dibayar',
        'Tidak ada refund paid pada periode.' => 'Tidak ada pengembalian dana yang dibayar pada periode.',
        'Biaya maintenance' => 'Biaya pemeliharaan',
        'Tidak ada biaya maintenance pada periode.' => 'Tidak ada biaya pemeliharaan pada periode.',
        'Payment completed masuk dikurangi payment completed keluar dan refund paid. Payment void dan refund selain paid tidak memengaruhi arus kas.' => 'Pembayaran masuk yang selesai dikurangi pembayaran keluar yang selesai dan pengembalian dana yang dibayar. Pembayaran yang dibatalkan dan pengembalian dana yang belum dibayar tidak memengaruhi arus kas.',
        'Piutang menampilkan saldo rental positif sampai akhir periode dan mengecualikan draft, cancelled, void, serta rejected.' => 'Piutang menampilkan saldo penyewaan positif sampai akhir periode dan mengecualikan transaksi draf, dibatalkan, dibatalkan pembayarannya, serta ditolak.',
        'Nilai rental memakai total_amount pada rental yang checkout/tercatat dalam periode dan tidak berstatus draft, cancelled, void, atau rejected.' => 'Nilai penyewaan menggunakan total_amount pada penyewaan yang keluar atau tercatat dalam periode dan tidak berstatus draf, dibatalkan, dibatalkan pembayarannya, atau ditolak.',
        'Deposit ditampilkan terpisah dari penerimaan rental dan saldo deposit held dihitung dari payment deposit completed dikurangi refund deposit paid.' => 'Deposit ditampilkan terpisah dari penerimaan penyewaan. Deposit yang ditahan adalah pembayaran deposit yang selesai dikurangi pengembalian deposit yang dibayar.',
        'Laporan konsolidasi hanya menggunakan cabang yang dapat diakses pengguna. Pengguna branch-scoped otomatis terkunci pada cabang aktif.' => 'Laporan konsolidasi hanya menggunakan cabang yang dapat diakses pengguna. Pengguna dengan akses per cabang dibatasi pada cabang aktif.',
        'Payment, refund, metode pembayaran, kategori, arah dana, dan status ledger.' => 'Pembayaran, pengembalian dana, metode pembayaran, kategori, arah dana, dan status buku besar.',
        'Saldo rental yang masih berjalan, umur piutang, dan deposit kontrak.' => 'Saldo penyewaan yang masih berjalan, umur piutang, dan deposit kontrak.',
        'Kondisi aset, investasi, frekuensi maintenance, dan biaya aktual periode.' => 'Kondisi aset, investasi, frekuensi pemeliharaan, dan biaya aktual periode.',
        'Pergerakan aset lintas cabang, biaya pengiriman, dan discrepancy penerimaan.' => 'Pergerakan aset lintas cabang, biaya pengiriman, dan selisih penerimaan.',
        'Progres stock opname, temuan fisik, dan penyelesaian discrepancy per cabang.' => 'Progres pemeriksaan stok, temuan fisik, dan penyelesaian selisih per cabang.',
        'Keuangan & Ledger' => 'Keuangan & Buku Besar',
        'Aset & Maintenance' => 'Aset & Pemeliharaan',
        'Stock Opname' => 'Pemeriksaan Stok',
    ];

    /** @var array<string, string> */
    private const STATUS_ID = [
        'active' => 'Aktif', 'approved' => 'Disetujui', 'available' => 'Tersedia',
        'cancelled' => 'Dibatalkan', 'closed' => 'Ditutup', 'completed' => 'Selesai',
        'confirmed' => 'Dikonfirmasi', 'converted' => 'Dikonversi', 'critical' => 'Kritis',
        'damaged' => 'Rusak', 'draft' => 'Draf', 'expired' => 'Kedaluwarsa',
        'failed' => 'Gagal', 'fair' => 'Cukup', 'good' => 'Baik',
        'in_progress' => 'Sedang diproses', 'in_transit' => 'Dalam perjalanan',
        'lost' => 'Hilang', 'maintenance' => 'Pemeliharaan', 'open' => 'Terbuka',
        'partial_return' => 'Dikembalikan sebagian', 'correction_pending' => 'Menunggu koreksi',
        'overdue' => 'Terlambat', 'paid' => 'Dibayar', 'pending' => 'Menunggu',
        'poor' => 'Buruk', 'posted' => 'Dibukukan', 'received' => 'Diterima',
        'rejected' => 'Ditolak', 'rented' => 'Disewa', 'requested' => 'Diajukan',
        'reserved' => 'Direservasi', 'retired' => 'Pensiun',
        'returned' => 'Dikembalikan', 'void' => 'Dibatalkan',
        'submitted' => 'Dikirim',
    ];

    /** @var array<string, string> */
    private const STATUS_EN = [
        'active' => 'Active', 'approved' => 'Approved', 'available' => 'Available',
        'cancelled' => 'Cancelled', 'closed' => 'Closed', 'completed' => 'Completed',
        'confirmed' => 'Confirmed', 'converted' => 'Converted', 'critical' => 'Critical',
        'damaged' => 'Damaged', 'draft' => 'Draft', 'expired' => 'Expired',
        'failed' => 'Failed', 'fair' => 'Fair', 'good' => 'Good',
        'in_progress' => 'In progress', 'in_transit' => 'In transit',
        'lost' => 'Lost', 'maintenance' => 'Maintenance', 'open' => 'Open',
        'partial_return' => 'Partially returned', 'correction_pending' => 'Pending correction',
        'overdue' => 'Overdue', 'paid' => 'Paid', 'pending' => 'Pending',
        'poor' => 'Poor', 'posted' => 'Posted', 'received' => 'Received',
        'rejected' => 'Rejected', 'rented' => 'Rented', 'requested' => 'Requested',
        'reserved' => 'Reserved', 'retired' => 'Retired',
        'returned' => 'Returned', 'void' => 'Void',
        'submitted' => 'Submitted',
    ];

    /** @var array<string, string> */
    private const EN = [
        'Rental tercatat' => 'Recorded rentals', 'Nilai rental' => 'Rental value',
        'Payment masuk' => 'Incoming payments', 'Arus kas bersih' => 'Net cash flow',
        'Piutang berjalan' => 'Outstanding receivables', 'Refund paid' => 'Paid refunds',
        'Deposit ditahan' => 'Deposits held', 'Biaya maintenance' => 'Maintenance costs',
        'Rental valid pada periode.' => 'Valid rentals during the period.',
        'Total nilai kontrak rental pada periode.' => 'Total rental contract value during the period.',
        'Payment completed berarah masuk.' => 'Completed incoming payments.',
        'Masuk dikurangi keluar dan refund paid.' => 'Inflows less outflows and paid refunds.',
        'Tidak ada piutang dalam cakupan.' => 'No receivables in scope.',
        'Tidak ada refund paid pada periode.' => 'No paid refunds during the period.',
        'Tidak ada deposit dalam cakupan.' => 'No deposits in scope.',
        'Tidak ada biaya maintenance pada periode.' => 'No maintenance costs during the period.',
        'Rekonstruksi dari event (perlu rekonsiliasi UAT)' => 'Reconstructed from events (UAT reconciliation required)',
        'Payment completed masuk dikurangi payment completed keluar dan refund paid. Payment void dan refund selain paid tidak memengaruhi arus kas.' => 'Completed incoming payments less completed outgoing payments and paid refunds. Voided payments and other refunds do not affect cash flow.',
        'Piutang menampilkan saldo rental positif sampai akhir periode dan mengecualikan draft, cancelled, void, serta rejected.' => 'Receivables show positive rental balances at period end, excluding draft, cancelled, void and rejected rentals.',
        'Saldo per akhir tanggal dihitung dari kontrak dan payment rental/refund/void bertanggal efektif. Deposit jaminan tidak mengurangi tagihan sewa. Perubahan nilai tanpa riwayat yang cukup ditandai BELUM DAPAT DIVERIFIKASI; nominalnya tidak dimasukkan ke subtotal. Kolom deposit hanya menampilkan nilai kontrak yang tersimpan saat ini.' => 'Balances as of the selected date use contracts and effectively dated rental payments, refunds and voids. Security deposits do not reduce rental charges. Changes without sufficient history are UNVERIFIED and excluded from the subtotal. The deposit column shows the currently stored contract amount.',
        'Nilai rental memakai total_amount pada rental yang checkout/tercatat dalam periode dan tidak berstatus draft, cancelled, void, atau rejected.' => 'Rental value uses total_amount for rentals checked out or recorded in the period, excluding draft, cancelled, void and rejected statuses.',
        'Deposit ditampilkan terpisah dari penerimaan rental dan saldo deposit held dihitung dari payment deposit completed dikurangi refund deposit paid.' => 'Deposits are shown separately from rental receipts. Held deposits equal completed deposit payments less paid deposit refunds.',
        'Laporan konsolidasi hanya menggunakan cabang yang dapat diakses pengguna. Pengguna branch-scoped otomatis terkunci pada cabang aktif.' => 'Consolidated reports use only accessible branches. Branch-scoped users are restricted to their active branch.',
        'Keuangan & Ledger' => 'Finance & Ledger', 'Piutang & Deposit' => 'Receivables & Deposits',
        'Sesi Kas' => 'Cash Sessions', 'Aset & Maintenance' => 'Assets & Maintenance',
        'Transfer Antar-Cabang' => 'Interbranch Transfers', 'Transfer Cabang' => 'Branch Transfers',
        'Stock Opname' => 'Stocktaking', 'Booking, Rental & Return' => 'Bookings, Rentals & Returns',
        'Keuangan' => 'Finance', 'Rental & Return' => 'Rentals & Returns',
        'Payment, refund, metode pembayaran, kategori, arah dana, dan status ledger.' => 'Payments, refunds, payment methods, categories, cash direction and ledger status.',
        'Saldo rental yang masih berjalan, umur piutang, dan deposit kontrak.' => 'Outstanding rental balances, receivable age and contract deposits.',
        'Rekonsiliasi sesi kas per register, kasir, dan selisih penutupan.' => 'Cash session reconciliation by register and cashier, including closing differences.',
        'Kondisi aset, investasi, frekuensi maintenance, dan biaya aktual periode.' => 'Asset condition, investment, maintenance frequency and actual period costs.',
        'Pergerakan aset lintas cabang, biaya pengiriman, dan discrepancy penerimaan.' => 'Interbranch asset movement, shipping costs and receipt discrepancies.',
        'Progres stock opname, temuan fisik, dan penyelesaian discrepancy per cabang.' => 'Stocktaking progress, physical findings and discrepancy resolution by branch.',
        'Nilai kontrak, status rental, waktu checkout/return, denda, pembayaran, dan saldo.' => 'Contract value, rental status, checkout and return time, charges, payments and balance.',
        'Jenis' => 'Type', 'Nomor' => 'Number', 'Referensi' => 'Reference',
        'Pelanggan' => 'Customer', 'Cabang' => 'Branch', 'Status' => 'Status',
        'Waktu Transaksi' => 'Transaction time', 'Mulai/Tempo' => 'Start/Due',
        'Selesai/Kembali' => 'Completed/Returned', 'Nilai' => 'Value',
        'Denda/Biaya' => 'Penalties/Fees', 'Terbayar' => 'Paid', 'Saldo' => 'Balance',
        'Sumber' => 'Source', 'Metode' => 'Method', 'Kategori' => 'Category',
        'Arah' => 'Direction', 'Waktu' => 'Time', 'Jumlah' => 'Amount',
        'No. Rental' => 'Rental No.', 'Jatuh Tempo' => 'Due date', 'Hari Lewat' => 'Days overdue',
        'Nilai Rental' => 'Rental value', 'Piutang' => 'Receivables', 'Deposit' => 'Deposit',
        'Dasar historis' => 'Historical basis', 'Register' => 'Register', 'Kasir' => 'Cashier',
        'Dibuka' => 'Opened', 'Ditutup' => 'Closed', 'Saldo Awal' => 'Opening balance',
        'Kas Masuk' => 'Cash in', 'Kas Keluar' => 'Cash out', 'Ekspektasi' => 'Expected',
        'Aktual' => 'Actual', 'Selisih' => 'Difference', 'Kode Aset' => 'Asset code',
        'Produk' => 'Product', 'Kondisi' => 'Condition', 'Harga Beli' => 'Purchase price',
        'Jml. Service' => 'Service count', 'Biaya Service' => 'Service cost',
        'Service Terakhir' => 'Last service', 'No. Transfer' => 'Transfer No.',
        'Asal' => 'Origin', 'Tujuan' => 'Destination', 'Diajukan' => 'Requested',
        'Dikirim' => 'Dispatched', 'Diterima' => 'Received', 'Unit' => 'Units',
        'Biaya Aktual' => 'Actual cost', 'Discrepancy' => 'Discrepancies',
        'No. Audit' => 'Audit No.', 'Judul' => 'Title', 'Jadwal' => 'Scheduled',
        'Dimulai' => 'Started', 'Disetujui' => 'Approved', 'Snapshot' => 'Snapshot',
        'Dihitung' => 'Counted', 'Temuan' => 'Findings', 'Belum Selesai' => 'Unresolved',
        'Aktif' => 'Active', 'Tersedia' => 'Available', 'Dibatalkan' => 'Cancelled',
        'Selesai' => 'Completed', 'Dikonfirmasi' => 'Confirmed', 'Rusak' => 'Damaged',
        'Cukup' => 'Fair', 'Baik' => 'Good', 'Dalam Perjalanan' => 'In transit',
        'Hilang' => 'Lost', 'Terbuka' => 'Open', 'Terlambat' => 'Overdue',
        'Dibayar' => 'Paid', 'Buruk' => 'Poor', 'Ditolak' => 'Rejected',
        'Disewa' => 'Rented', 'Direservasi' => 'Reserved', 'Pensiun' => 'Retired',
        'Dikembalikan' => 'Returned', 'Kritis' => 'Critical', 'Tinggi' => 'High',
        'Sedang' => 'Medium', 'Rendah' => 'Low', 'Ya' => 'Yes', 'Tidak' => 'No',
        'Harga beli belum tersedia' => 'Purchase price unavailable',
        'Harga beli perlu validasi' => 'Purchase price needs validation',
        'Harga beli lebih rendah daripada tarif sewa tertinggi produk.' => 'Purchase price is below the product’s highest rental rate.',
        'Interval memakai fallback' => 'Intervals using fallback',
        'Porsi interval bermasalah mencapai sedikitnya 10% dari histori rental.' => 'Problematic intervals account for at least 10% of rental history.',
        'Ada interval lama yang dikoreksi dengan due date, tetapi porsinya masih kecil.' => 'Some older intervals use the due date, but their share is still small.',
        'Rental aktif legacy kedaluwarsa' => 'Overdue active legacy rentals',
        'Jumlah dan porsi rental aktif kedaluwarsa cukup besar untuk memengaruhi keputusan utilisasi.' => 'Overdue active rentals may affect utilization decisions.',
        'Ada transaksi legacy aktif kedaluwarsa, tetapi tidak mendominasi histori unit.' => 'There are overdue active legacy rentals, but they do not dominate the unit history.',
        'Evaluasi servis' => 'Review service', 'Evaluasi penjualan' => 'Review sale',
        'Promosikan aset' => 'Promote asset', 'Pertahankan produktivitas' => 'Maintain productivity',
        'Pertimbangkan tambah unit' => 'Consider adding units',
        'Tunda keputusan investasi' => 'Defer investment decision',
        'Pantau sambil validasi histori' => 'Monitor while validating history',
        'Pantau menuju BEP' => 'Monitor toward break-even',
        'Kondisi unit membutuhkan pemeriksaan teknis sebelum dipertahankan dalam operasional.' => 'The unit needs a technical inspection before continued operation.',
        'Rasio biaya maintenance terhadap pendapatan sudah tinggi dan perlu evaluasi ekonomi.' => 'Maintenance costs are high relative to revenue and need an economic review.',
        'Utilisasi dan pendapatan terealisasi menunjukkan permintaan yang kuat pada periode terpilih.' => 'Utilization and realized revenue indicate strong demand in the selected period.',
        'Aset berusia lebih dari satu tahun dengan utilisasi dan progres BEP yang masih rendah sehingga kelayakan untuk dipertahankan perlu ditinjau.' => 'This asset is over a year old with low utilization and break-even progress; review whether to retain it.',
        'Belum ada pendapatan terealisasi pada periode dan utilisasi unit masih rendah. Prioritaskan promosi sebelum mempertimbangkan relokasi atau penjualan.' => 'No realized revenue in the period and low utilization. Prioritize promotion before relocation or sale.',
        'Aset telah melewati BEP berdasarkan pendapatan terealisasi.' => 'The asset has passed break-even based on realized revenue.',
        'Keputusan jual, tambah, atau evaluasi BEP sebaiknya menunggu data investasi yang tervalidasi.' => 'Wait for validated investment data before deciding to sell, expand or review break-even.',
        'Sinyal operasional belum cukup kuat karena sebagian histori pemakaian masih memerlukan validasi.' => 'Operating signals are inconclusive because some usage history still needs validation.',
        'Performa masih dalam jalur normal; pantau pendapatan, utilisasi, dan maintenance.' => 'Performance is within the normal range; monitor revenue, utilization and maintenance.',
        'ROI dan BEP belum dapat dinilai sampai nilai investasi dilengkapi.' => 'ROI and break-even cannot be assessed until investment values are completed.',
        'Pendapatan Terealisasi Lifetime' => 'Lifetime realized revenue',
        'Pendapatan Terealisasi Periode' => 'Period realized revenue',
        'Pendapatan Berjalan Lifetime' => 'Lifetime ongoing revenue',
        'Pendapatan Berjalan Periode' => 'Period ongoing revenue',
        'Biaya Maintenance' => 'Maintenance cost', 'Kontribusi Bersih' => 'Net contribution',
        'Progress BEP (%)' => 'Break-even progress (%)',
        'Sisa Menuju BEP' => 'Remaining to break-even',
        'Utilisasi Periode (%)' => 'Period utilization (%)',
        'Jumlah Rental' => 'Rental count', 'Rental Terealisasi' => 'Realized rentals',
        'Rental Berjalan' => 'Ongoing rentals', 'Interval Fallback' => 'Fallback intervals',
        'Rental Legacy Aktif Kedaluwarsa' => 'Overdue active legacy rentals',
        'Harga Beli Mencurigakan' => 'Suspicious purchase price',
        'Skor Kualitas Data' => 'Data quality score',
        'Keyakinan Data' => 'Data confidence', 'Blocker Data' => 'Data blocker',
        'Catatan Kualitas Data' => 'Data quality notes',
        'Estimasi Bulan BEP' => 'Estimated months to break-even',
        'Rekomendasi Bisnis' => 'Business recommendation',
        'Keyakinan Rekomendasi' => 'Recommendation confidence',
    ];

    public function text(string $value): string
    {
        if (app()->getLocale() !== 'en') {
            if (isset(self::ID[$value])) {
                return self::ID[$value];
            }

            $value = preg_replace('/^(\d+) refund dibayarkan\.$/', '$1 pengembalian dana dibayarkan.', $value) ?? $value;

            return preg_replace('/^(\d+) rental masih memiliki saldo\.$/', '$1 penyewaan masih memiliki saldo.', $value) ?? $value;
        }

        if (isset(self::EN[$value])) {
            return self::EN[$value];
        }

        $patterns = [
            '/^Piutang per (.+)$/' => 'Receivables as of $1',
            '/^Subtotal rekonstruksi (\d+) rental; (\d+) rental belum dapat diverifikasi dan tidak masuk subtotal\.$/' => 'Reconstructed subtotal from $1 rentals; $2 rentals cannot be verified and are excluded.',
            '/^(\d+) rental masih memiliki saldo\.$/' => '$1 rentals still have a balance.',
            '/^(\d+) refund dibayarkan\.$/' => '$1 refunds paid.',
            '/^Penerimaan deposit periode: Rp (.+)\.$/' => 'Period deposit receipts: Rp $1.',
            '/^Denda\/kerusakan rental: Rp (.+)\.$/' => 'Rental penalties/damage: Rp $1.',
            '/^Posisi historis (.+) berdasarkan event bertanggal\. (\d+) rental direkonstruksi, (\d+) belum dapat diverifikasi\..*$/' => 'Historical position $1 based on dated events. $2 rentals reconstructed; $3 cannot be verified. Receivables only include reconstructed rows.',
        ];
        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $value)) {
                return (string) preg_replace($pattern, $replacement, $value);
            }
        }

        return $value;
    }

    public static function status(string $value, string $locale): string
    {
        $labels = $locale === 'en' ? self::STATUS_EN : self::STATUS_ID;

        return $labels[$value] ?? str_replace('_', ' ', $value);
    }

    /** @param array<string, mixed> $result
     *  @return array<string, mixed> */
    public function report(array $result): array
    {
        /** @var list<array{label: string, note: string}> $summary */
        $summary = $result['summary'];
        foreach ($summary as &$card) {
            $card['label'] = $this->text($card['label']);
            $card['note'] = $this->text($card['note']);
        }
        unset($card);
        $result['summary'] = $summary;

        /** @var list<array{label: string}> $columns */
        $columns = $result['columns'];
        foreach ($columns as &$column) {
            $column['label'] = $this->text($column['label']);
        }
        unset($column);
        $result['columns'] = $columns;

        /** @var list<array{value: string, label: string}> $options */
        $options = $result['statusOptions'];
        foreach ($options as &$option) {
            $option['label'] = self::status((string) $option['value'], app()->getLocale());
        }
        unset($option);
        $result['statusOptions'] = $options;

        /** @var array{label: string, description: string} $meta */
        $meta = $result['reportMeta'];
        foreach (['label', 'description'] as $key) {
            $meta[$key] = $this->text($meta[$key]);
        }
        $result['reportMeta'] = $meta;

        /** @var array<string, string> $methodology */
        $methodology = $result['methodology'];
        foreach ($methodology as &$description) {
            $description = $this->text($description);
        }
        unset($description);
        $result['methodology'] = $methodology;

        /** @var list<array{values: array<string, mixed>}> $rows */
        $rows = $result['rows'];
        foreach ($rows as &$row) {
            $kind = $row['values']['kind'] ?? null;
            if (is_string($kind)) {
                $row['values']['kind'] = $this->text($kind);
            }
            if (app()->getLocale() === 'en') {
                $quality = $row['values']['history_quality'] ?? null;
                if (is_string($quality)) {
                    $row['values']['history_quality'] = str_starts_with($quality, 'BELUM DAPAT DIVERIFIKASI: ')
                        ? 'UNVERIFIED: '.substr($quality, strlen('BELUM DAPAT DIVERIFIKASI: '))
                        : $this->text($quality);
                }
            }
        }
        unset($row);
        $result['rows'] = $rows;

        return $result;
    }

    /** @param array<string, mixed> $result
     *  @return array<string, mixed> */
    public function analytics(array $result): array
    {
        /** @var list<array{data_quality: array{issues: list<array{label: string, description: string}>, confidence_label: string}, recommendation: array{label: string, description: string, confidence_label: string}}> $assets */
        $assets = $result['assets'];
        foreach ($assets as &$asset) {
            foreach ($asset['data_quality']['issues'] as &$issue) {
                $issue['label'] = $this->text($issue['label']);
                $issue['description'] = $this->text($issue['description']);
            }
            unset($issue);
            foreach (['label', 'description', 'confidence_label'] as $field) {
                $asset['recommendation'][$field] = $this->text($asset['recommendation'][$field]);
            }
            $asset['data_quality']['confidence_label'] = $this->text($asset['data_quality']['confidence_label']);
        }
        unset($asset);
        $result['assets'] = $assets;

        return $result;
    }
}
