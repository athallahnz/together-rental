import { usePage } from '@inertiajs/react';
import { useAppLocale } from '@/lib/i18n';
import type { AppLocale } from '@/lib/i18n';
import { intlLocale } from '@/lib/locale-format';

/** Only application-owned labels are translated. Customer and product text stays untouched. */
const english: Record<string, string> = {
    Aksi: 'Actions',
    'Analitik ROI/BEP': 'ROI/Break-even analytics',
    'Arus kas bersih': 'Net cash flow',
    Cabang: 'Branch',
    'Dari tanggal': 'From date',
    Detail: 'Details',
    'Pusat Laporan dan Ekspor Terintegrasi':
        'Integrated Reporting & Export Center',
    'Kas bersih': 'Net cash',
    'Kategori keuangan': 'Financial category',
    Keluar: 'Outflows',
    'Laporan formal operasional, keuangan, kas, aset, dan transfer antar-cabang dari satu sumber data yang dapat ditelusuri kembali ke transaksi asal.':
        'Formal operations, finance, cash, asset and interbranch transfer reports from one traceable source of transaction data.',
    'Laporan Manajemen': 'Management Reporting',
    'Metode pembayaran': 'Payment method',
    'Metodologi & jejak audit': 'Methodology & audit trail',
    'Nilai kontrak rental dibandingkan dengan payment masuk dan arus kas bersih.':
        'Rental contract value compared with incoming payments and net cash flow.',
    'Nilai rental': 'Rental value',
    'Payment masuk': 'Incoming payments',
    Pencarian: 'Search',
    'Performa cabang': 'Branch performance',
    Piutang: 'Receivables',
    Rental: 'Rentals',
    Reset: 'Reset',
    'Ringkasan nilai rental, kas bersih, piutang, dan biaya maintenance per cabang.':
        'Rental value, net cash, receivables and maintenance costs by branch.',
    'Sampai tanggal': 'To date',
    'Semua cabang': 'All branches',
    'Semua kategori': 'All categories',
    'Semua metode': 'All methods',
    'Semua status': 'All statuses',
    Status: 'Status',
    'Terapkan filter': 'Apply filters',
    'Tidak ada data laporan': 'No report data',
    'Tren lintas modul': 'Cross-module trends',
    'Ubah periode atau filter untuk melihat data lainnya.':
        'Change the period or filters to see more data.',
    'Analitik Aset, ROI & BEP': 'Asset Analytics, ROI & Break-even',
    'Arah ROI keseluruhan': 'Overall ROI direction',
    Aset: 'Assets',
    BEP: 'Break-even',
    'BEP:': 'Break-even:',
    Baik: 'Good',
    'Bedakan aset sehat, tindakan nyata, pemantauan, dan keputusan yang ditunda.':
        'Distinguish healthy assets, immediate actions, monitoring and deferred decisions.',
    Buruk: 'Poor',
    'Catatan audit ditampilkan terpisah dan tidak menggantikan rekomendasi bisnis.':
        'Audit notes are shown separately and do not replace business recommendations.',
    Cukup: 'Fair',
    'Data siap digunakan untuk keputusan.':
        'Data is ready for decision making.',
    Direservasi: 'Reserved',
    Disewa: 'Rented',
    'Estimasi BEP': 'Estimated break-even',
    'Export CSV': 'Export CSV',
    'Hanya intervensi yang benar-benar perlu dikerjakan.':
        'Only interventions that need action.',
    Hilang: 'Lost',
    Investasi: 'Investment',
    'Keputusan Portofolio': 'Portfolio decisions',
    Kontribusi: 'Contribution',
    Kritis: 'Critical',
    'Kualitas data': 'Data quality',
    Maintenance: 'Maintenance',
    'Metodologi perhitungan': 'Calculation methodology',
    'Modul 7 · Business Intelligence': 'Module 7 · Business Intelligence',
    'Pendapatan terealisasi': 'Realized revenue',
    'Pendapatan vs maintenance': 'Revenue vs maintenance',
    'Pendapatan:': 'Revenue:',
    Pensiun: 'Retired',
    'Perbandingan investasi, kontribusi bersih, progres BEP, dan utilisasi unit berdasarkan cabang saat ini.':
        'Compare investment, net contribution, break-even progress and asset utilization by current branch.',
    'Performa per cabang': 'Performance by branch',
    'Pisahkan kelayakan data dari tindakan bisnis agar prioritas tidak tertutup oleh catatan legacy minor. Kolom aset tetap terlihat saat tabel digeser horizontal.':
        'Separate data quality from business actions so minor legacy issues do not obscure priorities. Asset columns stay visible while scrolling.',
    ROI: 'ROI',
    'ROI & BEP per aset': 'ROI & break-even by asset',
    'ROI:': 'ROI:',
    'Rekomendasi bisnis': 'Business recommendation',
    'Rekomendasi:': 'Recommendation:',
    'Rincian tindakan': 'Action details',
    Rusak: 'Damaged',
    'Semua kondisi': 'All conditions',
    Terapkan: 'Apply',
    Tersedia: 'Available',
    'Tidak ada aset pada filter ini': 'No assets match these filters',
    'Tren bulanan berdasarkan tanggal checkout, approval perpanjangan, dan penyelesaian maintenance.':
        'Monthly trends by checkout, extension approval and maintenance completion date.',
    'Ubah periode, cabang, kategori, status, atau kata pencarian.':
        'Change the period, branch, category, status or search term.',
    'Ukur produktivitas unit, progres balik modal, utilisasi, biaya maintenance, dan rekomendasi tindakan per cabang.':
        'Measure asset productivity, break-even progress, utilization, maintenance costs and recommended actions by branch.',
    Utilisasi: 'Utilization',
    'Utilisasi:': 'Utilization:',
    'Filter laporan': 'Report filters',
    'Pusat Laporan Terintegrasi': 'Integrated Reporting Center',
    'Rentang maksimal 367 hari. Filter cabang mengikuti hak akses pengguna.':
        'Maximum range: 367 days. Branch filters follow user access permissions.',
    'Nomor transaksi, pelanggan, aset, register...':
        'Transaction number, customer, asset, register...',
    'Cari aset atau produk': 'Search assets or products',
    'Grafik pendapatan terealisasi dan maintenance aset':
        'Chart of realized revenue and asset maintenance',
    'Tren nilai rental, payment masuk, dan arus kas bersih':
        'Trend of rental value, incoming payments and net cash flow',
    'Investasi tercatat': 'Recorded investment',
    'Kontribusi bersih': 'Net contribution',
    'Progress BEP': 'Break-even progress',
    'ROI keseluruhan': 'Overall ROI',
    'Utilisasi periode': 'Period utilization',
    'Estimasi sementara · ': 'Provisional estimate · ',
    'unit sudah BEP': 'units at break-even',
    'Profit bersih ': 'Net profit ',
    'unit aktif': 'active units',
    dari: 'of',
    'aset ·': 'assets ·',
    cakupan: 'coverage',
    'periode ·': 'in period ·',
    berjalan: 'ongoing',
    Berjalan: 'Ongoing',
    Beli: 'Purchase',
    'Lebih rendah dari tarif': 'Below rental rate',
    Periode: 'Period',
    Sisa: 'Remaining',
    'jam ·': 'hours ·',
    selesai: 'completed',
    Skor: 'Score',
    'catatan lain': 'other notes',
    baris: 'rows',
    'Dibuat ': 'Generated ',
    'Setiap baris memiliki tautan detail ke transaksi sumber.':
        'Each row links to its source transaction.',
    'Perlu tindakan operasional': 'Operational action needed',
    'Aset sehat / pertahankan': 'Healthy assets / retain',
    'Pantau menuju BEP': 'Monitor toward break-even',
    'Keputusan ditunda': 'Decision deferred',
    'keyakinan tinggi': 'high confidence',
    'telah melewati BEP': 'past break-even',
    'belum perlu intervensi': 'no intervention needed',
    'menunggu validasi investasi': 'awaiting investment validation',
    'Tambah kapasitas': 'Add capacity',
    'Promosikan aset': 'Promote asset',
    'Evaluasi penjualan': 'Review sale',
    'Evaluasi servis': 'Review service',
    'permintaan kuat': 'strong demand',
    'utilisasi periode rendah': 'low period utilization',
    'usia dan BEP kurang sehat': 'age and break-even need review',
    'kondisi atau biaya': 'condition or cost',
    'Aset dengan blocker': 'Assets with blockers',
    'Kualitas tinggi': 'High quality',
    'Interval memakai fallback': 'Intervals using fallback',
    'Rental legacy kedaluwarsa': 'Overdue legacy rentals',
    'Harga beli bermasalah': 'Purchase price issues',
    'Riwayat maintenance': 'Maintenance history',
    'siap untuk keputusan': 'ready for decisions',
    'assignment memakai due date': 'assignments use due date',
    'masih berstatus aktif': 'still active',
    'data biaya teknis': 'technical cost data',
    'aset punya catatan': 'assets with notes',
    'kosong ·': 'missing ·',
    'perlu validasi': 'need validation',
    'Filter analitik': 'Analytics filters',
    'Periode memengaruhi pendapatan, tren, utilisasi, dan estimasi BEP; ROI serta BEP memakai data lifetime.':
        'The period affects revenue, trends, utilization and estimated break-even; ROI and break-even use lifetime data.',
    'Pendapatan periode tertinggi': 'Highest period revenue',
    'pendapatan pada periode terpilih': 'revenue in the selected period',
    'Utilisasi tertinggi': 'Highest utilization',
    'pemakaian valid terhadap jam operasional aset aktif':
        'valid usage against active asset operating hours',
    'Biaya maintenance tertinggi': 'Highest maintenance cost',
    'Belum ada data': 'No data yet',
    'Belum ada': 'None yet',
    bulan: 'months',
    'biaya maintenance lifetime': 'lifetime maintenance cost',
    'belum ada maintenance selesai yang tercatat':
        'no completed maintenance recorded',
    'Sudah BEP': 'Break-even reached',
    'Belum terproyeksi': 'Not yet projected',
    Pemeliharaan: 'Maintenance',
    Aktif: 'Active',
    Disetujui: 'Approved',
    Dibatalkan: 'Cancelled',
    Ditutup: 'Closed',
    Selesai: 'Completed',
    Dikonfirmasi: 'Confirmed',
    Dikonversi: 'Converted',
    Draf: 'Draft',
    Kedaluwarsa: 'Expired',
    Gagal: 'Failed',
    'Sedang diproses': 'In progress',
    'Dalam perjalanan': 'In transit',
    Terbuka: 'Open',
    Terlambat: 'Overdue',
    Dibayar: 'Paid',
    Menunggu: 'Pending',
    Dibukukan: 'Posted',
    Diterima: 'Received',
    Ditolak: 'Rejected',
    Diajukan: 'Requested',
    Dikembalikan: 'Returned',
    Dikirim: 'Submitted',
    'Pembayaran masuk': 'Incoming payments',
    'Pengembalian dana dibayar': 'Paid refunds',
    'Biaya pemeliharaan': 'Maintenance costs',
    'Modul 7 · Intelijen Bisnis': 'Module 7 · Business Intelligence',
    'Ekspor CSV': 'Export CSV',
    'Laba bersih ': 'Net profit ',
    'Nilai penyewaan': 'Rental value',
    Penyewaan: 'Rentals',
    'Atur ulang': 'Reset',
};

export function stage6Display(text: string, locale: AppLocale): string {
    if (locale === 'id') {
        return text;
    }

    return english[text] ?? text;
}

export function Stage6Text({ text }: { text: string }) {
    const { props } = usePage();

    return stage6Display(
        text,
        (props as { locale?: string }).locale === 'en' ? 'en' : 'id',
    );
}

export function useStage6Numbers(maximumFractionDigits = 0) {
    const { locale } = useAppLocale();
    const language = intlLocale(locale);

    return {
        money: new Intl.NumberFormat(language, {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }),
        number: new Intl.NumberFormat(language, { maximumFractionDigits }),
        date: new Intl.DateTimeFormat(language, {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }),
        dateTime: new Intl.DateTimeFormat(language, {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }),
    };
}
