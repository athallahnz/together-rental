import { usePage } from '@inertiajs/react';
import { translateKey } from '@/lib/i18n';
import type { AppLocale } from '@/lib/i18n';
import { getEffectiveLocale } from '@/lib/locale-store';
import type { MessageKey } from '@/lib/i18n-catalog';

export type Stage5Key = Extract<MessageKey, `stage5.ui.${string}`>;

/** Presentation only: API status, category, permission, and ledger values remain unchanged. */
export function stage5Translate(
    key: Stage5Key,
    locale: AppLocale = getEffectiveLocale(),
): string {
    return translateKey(key, locale);
}

export function Stage5Text({ k }: { k: Stage5Key }) {
    const { props } = usePage();
    const locale: AppLocale =
        (props as { locale?: string }).locale === 'en' ? 'en' : 'id';

    return stage5Translate(k, locale);
}

/** Translate only recognized interface labels, never user-authored free text. */
const knownLabels: Record<string, { id: string; en: string }> = {
    '% gross · refund': { id: '% gross · refund', en: '% gross · refunds' },
    '% vs periode lalu': {
        id: '% vs periode lalu',
        en: '% vs previous period',
    },
    '. Pengajuan harus disetujui oleh pengguna lain sebelum payout.': {
        id: '. Pengajuan harus disetujui oleh pengguna lain sebelum payout.',
        en: '. Another authorized user must approve the request before payout.',
    },
    '1 hari': { id: '1 hari', en: '1 day' },
    '1 jam': { id: '1 jam', en: '1 hour' },
    '7 hari': { id: '7 hari', en: '7 days' },
    Abaikan: { id: 'Abaikan', en: 'Ignore' },
    'Acuan waktu': { id: 'Acuan waktu', en: 'Reference time' },
    Administrasi: { id: 'Administrasi', en: 'Administration' },
    'Ajukan Refund': { id: 'Ajukan Refund', en: 'Request refund' },
    Aksi: { id: 'Aksi', en: 'Actions' },
    'Aksi destruktif khusus non-production': {
        id: 'Aksi destruktif khusus non-production',
        en: 'Destructive action for non-production only',
    },
    Aktif: { id: 'Aktif', en: 'Active' },
    'Aktifkan notifikasi email': {
        id: 'Aktifkan notifikasi email',
        en: 'Enable email notifications',
    },
    Aktivitas: { id: 'Aktivitas', en: 'Activity' },
    'Aktivitas keuangan terbaru': {
        id: 'Aktivitas keuangan terbaru',
        en: 'Recent financial activity',
    },
    Aktor: { id: 'Aktor', en: 'Actor' },
    Aktual: { id: 'Aktual', en: 'Actual' },
    'Akun ini tidak memiliki izin untuk mengunggah sumber legacy.': {
        id: 'Akun ini tidak memiliki izin untuk mengunggah sumber legacy.',
        en: 'This account is not permitted to upload legacy data.',
    },
    Alasan: { id: 'Alasan', en: 'Reason' },
    'Alasan refund': { id: 'Alasan refund', en: 'Refund reason' },
    'Alasan resolusi (tersimpan di audit)': {
        id: 'Alasan resolusi (tersimpan di audit)',
        en: 'Resolution reason (recorded in audit)',
    },
    'Alasan void': { id: 'Alasan void', en: 'Void reason' },
    'Antrean validasi': { id: 'Antrean validasi', en: 'Validation queue' },
    'Antrian dan indikator yang perlu ditindaklanjuti.': {
        id: 'Antrian dan indikator yang perlu ditindaklanjuti.',
        en: 'Queues and indicators requiring follow-up.',
    },
    Arah: { id: 'Arah', en: 'Direction' },
    'Arus kas bersih': { id: 'Arus kas bersih', en: 'Net cash flow' },
    'Arus masuk dan keluar berdasarkan konteks asal.': {
        id: 'Arus masuk dan keluar berdasarkan konteks asal.',
        en: 'Cash inflows and outflows by source context.',
    },
    'Aset serialized': { id: 'Aset serialized', en: 'Serialized assets' },
    'Aturan Reminder': { id: 'Aturan Reminder', en: 'Reminder rules' },
    'Audit Trail': { id: 'Audit Trail', en: 'Audit trail' },
    'Audit Trail Center': { id: 'Pusat Jejak Audit', en: 'Audit Trail Center' },
    'Audit event': { id: 'Audit event', en: 'Audit events' },
    'Audit timeline': { id: 'Audit timeline', en: 'Audit timeline' },
    'Audit trail': { id: 'Jejak audit', en: 'Audit trail' },
    'Baru pada periode ini': {
        id: 'Baru pada periode ini',
        en: 'New in this period',
    },
    Batal: { id: 'Batal', en: 'Cancel' },
    Batalkan: { id: 'Batalkan', en: 'Cancel' },
    'Batalkan refund': { id: 'Batalkan refund', en: 'Cancel refund' },
    Batch: { id: 'Kelompok impor', en: 'Batch' },
    Bayar: { id: 'Bayar', en: 'Pay' },
    'Bayar Refund': { id: 'Bayar Refund', en: 'Pay refund' },
    'Bayar expense': { id: 'Bayar expense', en: 'Pay expense' },
    'Bayar refund': { id: 'Bayar refund', en: 'Pay refund' },
    'Belum ada activity log.': {
        id: 'Belum ada activity log.',
        en: 'No activity log yet.',
    },
    'Belum ada aktivitas audit untuk payment ini.': {
        id: 'Belum ada aktivitas audit untuk payment ini.',
        en: 'No audit activity for this payment.',
    },
    'Belum ada aktivitas pada periode ini.': {
        id: 'Belum ada aktivitas pada periode ini.',
        en: 'No activity in this period.',
    },
    'Belum ada batch import.': {
        id: 'Belum ada batch import.',
        en: 'No import batches yet.',
    },
    'Belum ada cash transaction pada filter ini.': {
        id: 'Belum ada cash transaction pada filter ini.',
        en: 'No cash transactions match these filters.',
    },
    'Belum ada histori sesi kas.': {
        id: 'Belum ada histori sesi kas.',
        en: 'No cash session history.',
    },
    'Belum ada payment.': {
        id: 'Belum ada pembayaran.',
        en: 'No payments yet.',
    },
    'Belum ada pengeluaran pada filter ini.': {
        id: 'Belum ada pengeluaran pada filter ini.',
        en: 'No expenses match these filters.',
    },
    'Belum ada refund untuk payment ini.': {
        id: 'Belum ada refund untuk payment ini.',
        en: 'No refunds for this payment.',
    },
    'Belum ada sesi kas aktif untuk cabang ini.': {
        id: 'Belum ada sesi kas aktif untuk cabang ini.',
        en: 'No active cash session for this branch.',
    },
    'Belum ada transaksi pada periode ini.': {
        id: 'Belum ada transaksi pada periode ini.',
        en: 'No transactions in this period.',
    },
    'Belum dibaca': { id: 'Belum dibaca', en: 'Unread' },
    'Belum dibayar': { id: 'Belum dibayar', en: 'Unpaid' },
    'Belum memengaruhi kas': {
        id: 'Belum memengaruhi kas',
        en: 'Cash balance unaffected',
    },
    'Biaya Marketing': { id: 'Biaya Marketing', en: 'Marketing expense' },
    'Biaya Transfer': { id: 'Biaya transfer', en: 'Transfer expense' },
    'Biaya Transfer Aset': {
        id: 'Biaya transfer aset',
        en: 'Asset transfer expense',
    },
    Booking: { id: 'Booking', en: 'Bookings' },
    'Booking akan dibatalkan': {
        id: 'Booking akan dibatalkan',
        en: 'Booking will be cancelled',
    },
    'Buat placeholder untuk': {
        id: 'Buat placeholder untuk',
        en: 'Create placeholder for',
    },
    'Buka Payment': { id: 'Buka Payment', en: 'Open payment' },
    'Buka sesi': { id: 'Buka sesi', en: 'Open session' },
    'Buka sumber': { id: 'Buka sumber', en: 'Open source' },
    'Bukti payout': { id: 'Bukti payout', en: 'Payout proof' },
    'Bukti privat': { id: 'Bukti privat', en: 'Private supporting document' },
    Cabang: { id: 'Cabang', en: 'Branches' },
    'Cakupan tabel sumber': {
        id: 'Cakupan tabel sumber',
        en: 'Source table coverage',
    },
    'Cara membaca angka': {
        id: 'Cara membaca angka',
        en: 'Understanding the figures',
    },
    Cari: { id: 'Cari', en: 'Search' },
    'Cari judul atau isi...': {
        id: 'Cari judul atau isi...',
        en: 'Search title or content...',
    },
    Aktifkan: { id: 'Aktifkan', en: 'Activate' },
    Nonaktifkan: { id: 'Nonaktifkan', en: 'Deactivate' },
    Tunai: { id: 'Tunai', en: 'Cash' },
    'Transfer bank': { id: 'Transfer bank', en: 'Bank transfer' },
    'Kartu debit/kredit': {
        id: 'Kartu debit/kredit',
        en: 'Debit / credit card',
    },
    Lainnya: { id: 'Lainnya', en: 'Other' },
    Pendapatan: { id: 'Pendapatan', en: 'Income' },
    Pengeluaran: { id: 'Pengeluaran', en: 'Expense' },
    Liabilitas: { id: 'Liabilitas', en: 'Liability' },
    'Referensi wajib': { id: 'Referensi wajib', en: 'Reference required' },
    'Referensi opsional': {
        id: 'Referensi opsional',
        en: 'Reference optional',
    },
    Pengembalian: { id: 'Pengembalian', en: 'Rental return' },
    'belum pernah dibuka': { id: 'belum pernah dibuka', en: 'never opened' },
    'Choose File': { id: 'Pilih berkas', en: 'Choose file' },
    'Cash In': { id: 'Kas masuk', en: 'Cash In' },
    'Cash Ledger': { id: 'Buku kas', en: 'Cash Ledger' },
    'Cash Ledger & Audit': {
        id: 'Buku kas & audit',
        en: 'Cash Ledger & Audit',
    },
    'Cash Ledger · read-only': {
        id: 'Buku kas · hanya baca',
        en: 'Cash Ledger · read-only',
    },
    'Cash Out': { id: 'Kas keluar', en: 'Cash Out' },
    'Cash register & sesi': {
        id: 'Kasir & sesi',
        en: 'Cash register & sessions',
    },
    'Cash register & sesi kas': {
        id: 'Kasir & sesi kas',
        en: 'Cash registers & sessions',
    },
    'Catat pengeluaran operasional': {
        id: 'Catat pengeluaran operasional',
        en: 'Record operational expense',
    },
    Catatan: { id: 'Catatan', en: 'Notes' },
    'Catatan payment': { id: 'Catatan payment', en: 'Payment notes' },
    'Catatan payout': { id: 'Catatan payout', en: 'Payout notes' },
    'Catatan pembukaan': { id: 'Catatan pembukaan', en: 'Opening notes' },
    'Catatan penutupan': { id: 'Catatan penutupan', en: 'Closing notes' },
    'Catatan sesi': { id: 'Catatan sesi', en: 'Session notes' },
    'Catatan tambahan': { id: 'Catatan tambahan', en: 'Additional notes' },
    'Checkout Rental': { id: 'Checkout rental', en: 'Rental checkout' },
    'Cocok untuk transfer, QRIS, kartu, dan kanal non-tunai lainnya.': {
        id: 'Cocok untuk transfer, QRIS, kartu, dan kanal non-tunai lainnya.',
        en: 'For transfers, QRIS, cards and other non-cash channels.',
    },
    Completed: { id: 'Selesai', en: 'Completed' },
    'Contoh: Shift pagi': {
        id: 'Contoh: Shift pagi',
        en: 'Example: morning shift',
    },
    'Daftar refund': { id: 'Daftar refund', en: 'Refund list' },
    'Dari tanggal': { id: 'Dari tanggal', en: 'From date' },
    'Data ternormalisasi; password legacy tidak disimpan.': {
        id: 'Data ternormalisasi; password legacy tidak disimpan.',
        en: 'Normalized data; legacy passwords are not stored.',
    },
    'Data yang dibersihkan': {
        id: 'Data yang dibersihkan',
        en: 'Data to be cleared',
    },
    'Dengan perubahan': { id: 'Dengan perubahan', en: 'With changes' },
    'Deposit Rental': { id: 'Deposit rental', en: 'Rental deposit' },
    'Deposit masih ditahan': {
        id: 'Deposit masih ditahan',
        en: 'Deposits still held',
    },
    Detail: { id: 'Detail', en: 'Details' },
    'Detail Payment': { id: 'Detail Payment', en: 'Payment details' },
    'Detail Refund': { id: 'Detail Refund', en: 'Refund details' },
    'Detail batch': { id: 'Detail batch', en: 'Batch details' },
    'Detail pengeluaran': { id: 'Detail pengeluaran', en: 'Expense details' },
    'Dibayar oleh': { id: 'Dibayar oleh', en: 'Paid by' },
    Dibuat: { id: 'Dibuat', en: 'Created' },
    'Dibuat oleh': { id: 'Dibuat oleh', en: 'Created by' },
    Dibuka: { id: 'Dibuka', en: 'Opened' },
    'Dibuka oleh': { id: 'Dibuka oleh', en: 'Reopened by' },
    'Dibuka pada': { id: 'Dibuka pada', en: 'Opened at' },
    Diingatkan: { id: 'Diingatkan', en: 'Reminded' },
    Diperbarui: { id: 'Diperbarui', en: 'Updated' },
    'Diterima / dicatat oleh': {
        id: 'Diterima / dicatat oleh',
        en: 'Received / recorded by',
    },
    Ditunda: { id: 'Ditunda', en: 'Snoozed' },
    'Ditunda sampai': { id: 'Ditunda sampai', en: 'Snoozed until' },
    Ditutup: { id: 'Ditutup', en: 'Closed' },
    'Ditutup oleh': { id: 'Ditutup oleh', en: 'Closed by' },
    'Ditutup pada': { id: 'Ditutup pada', en: 'Closed at' },
    'Dokumen & payment': { id: 'Dokumen & payment', en: 'Documents & payment' },
    'Dompet Digital': { id: 'Dompet Digital', en: 'Digital wallet' },
    Edit: { id: 'Edit', en: 'Edit' },
    'Edit expense recorded': {
        id: 'Edit expense recorded',
        en: 'Edit recorded expense',
    },
    Ekspektasi: { id: 'Ekspektasi', en: 'Expected' },
    'Email hanya dikirim jika alamat sudah terverifikasi.': {
        id: 'Email hanya dikirim jika alamat sudah terverifikasi.',
        en: 'Email is sent only to verified addresses.',
    },
    Entitas: { id: 'Entitas', en: 'Entity' },
    Error: { id: 'Kesalahan', en: 'Error' },
    Event: { id: 'Peristiwa', en: 'Event' },
    'Event ini tidak membawa pasangan before/after.': {
        id: 'Event ini tidak membawa pasangan before/after.',
        en: 'This event has no before/after values.',
    },
    Execute: { id: 'Eksekusi', en: 'Execute' },
    'Execute dalam antrean': {
        id: 'Eksekusi dalam antrean',
        en: 'Execution queued',
    },
    'Execute import RentalV1?': {
        id: 'Jalankan impor RentalV1?',
        en: 'Execute import RentalV1?',
    },
    Expected: { id: 'Diharapkan', en: 'Expected' },
    Expense: { id: 'Pengeluaran', en: 'Expense' },
    'Expense & Cash Center': {
        id: 'Pusat Pengeluaran & Kas',
        en: 'Expense & Cash Center',
    },
    'Expense dibayar': { id: 'Pengeluaran dibayar', en: 'Paid expenses' },
    'Expense telah di-void': {
        id: 'Expense telah di-void',
        en: 'Expense has been voided',
    },
    Field: { id: 'Field', en: 'Field' },
    'File dump .sql': { id: 'File dump .sql', en: 'SQL dump file (.sql)' },
    'Filter audit': { id: 'Filter audit', en: 'Audit filters' },
    'Filter dashboard': { id: 'Filter dashboard', en: 'Dashboard filters' },
    'Filter inbox': { id: 'Filter inbox', en: 'Inbox filters' },
    'Filter refund': { id: 'Filter refund', en: 'Refund filters' },
    'Filter transaksi': { id: 'Filter transaksi', en: 'Transaction filters' },
    'Finance Center': { id: 'Pusat Keuangan', en: 'Finance Center' },
    'Finance Control Center': {
        id: 'Pusat Pengendalian Keuangan',
        en: 'Finance Control Center',
    },
    'Finance Dashboard': { id: 'Dasbor Keuangan', en: 'Finance Dashboard' },
    'Finance Master': { id: 'Master keuangan', en: 'Finance Master' },
    Gagal: { id: 'Gagal', en: 'Failed' },
    'Ganti bukti': { id: 'Ganti bukti', en: 'Replace evidence' },
    'Gross collections': { id: 'Penerimaan bruto', en: 'Gross collections' },
    'Guardrail aktif untuk menjaga database baru.': {
        id: 'Guardrail aktif untuk menjaga database baru.',
        en: 'Safeguards are enabled to protect the new database.',
    },
    'Hari ini': { id: 'Hari ini', en: 'Today' },
    'Hasil verifikasi': { id: 'Hasil verifikasi', en: 'Verification results' },
    'Histori finansial tetap dilindungi': {
        id: 'Histori finansial tetap dilindungi',
        en: 'Financial history remains protected',
    },
    'Histori sesi': { id: 'Histori sesi', en: 'Session history' },
    'Histori tidak dihapus.': {
        id: 'Histori tidak dihapus.',
        en: 'History is preserved.',
    },
    'IP Address': { id: 'Alamat IP', en: 'IP Address' },
    Imported: { id: 'Diimpor', en: 'Imported' },
    Inbox: { id: 'Inbox', en: 'Inbox' },
    'Inbox aktif': { id: 'Inbox aktif', en: 'Active inbox' },
    'Inbox notifikasi kosong': {
        id: 'Inbox notifikasi kosong',
        en: 'Notification inbox is empty',
    },
    Informasi: { id: 'Informasi', en: 'Information' },
    'Integritas cash ledger': {
        id: 'Integritas buku kas',
        en: 'Cash ledger integrity',
    },
    'Inti sistem': { id: 'Inti sistem', en: 'System core' },
    Inventaris: { id: 'Inventaris', en: 'Inventory' },
    'Jalankan Preview untuk melihat tabel sumber.': {
        id: 'Jalankan Preview untuk melihat tabel sumber.',
        en: 'Run preview to view source tables.',
    },
    'Jalankan Verifikasi': {
        id: 'Jalankan Verifikasi',
        en: 'Run verification',
    },
    'Jalankan pemindaian': { id: 'Jalankan pemindaian', en: 'Run scan' },
    'Jejak perubahan status batch terbaru.': {
        id: 'Jejak perubahan status batch terbaru.',
        en: 'Recent batch status changes.',
    },
    'Jelaskan alasan (minimal 10 karakter).': {
        id: 'Jelaskan alasan (minimal 10 karakter).',
        en: 'Explain the reason (at least 10 characters).',
    },
    'Jelaskan alasan refund (minimal 10 karakter).': {
        id: 'Jelaskan alasan refund (minimal 10 karakter).',
        en: 'Explain the refund reason (at least 10 characters).',
    },
    'Jelaskan bila ada selisih': {
        id: 'Jelaskan bila ada selisih',
        en: 'Explain any variance',
    },
    'Jelaskan kesalahan dan alasan koreksi (minimal 10 karakter).': {
        id: 'Jelaskan kesalahan dan alasan koreksi (minimal 10 karakter).',
        en: 'Explain the error and correction reason (at least 10 characters).',
    },
    Jenis: { id: 'Jenis', en: 'Type' },
    'Jumlah ledger': { id: 'Jumlah ledger', en: 'Ledger total' },
    'Kas keluar': { id: 'Kas keluar', en: 'Cash out' },
    'Kas masuk': { id: 'Kas masuk', en: 'Cash in' },
    'Kasir Front Desk': { id: 'Kasir Front Desk', en: 'Front desk register' },
    'Kasir aktif': { id: 'Kasir aktif', en: 'Active registers' },
    Kategori: { id: 'Kategori', en: 'Category' },
    'Kategori aktif': { id: 'Kategori aktif', en: 'Active category' },
    'Kategori expense': { id: 'Kategori pengeluaran', en: 'Expense category' },
    'Kategori keuangan': { id: 'Kategori keuangan', en: 'Finance category' },
    'Kategori yang dibisukan': {
        id: 'Kategori yang dibisukan',
        en: 'Muted categories',
    },
    Keluar: { id: 'Keluar', en: 'Log out' },
    Kembali: { id: 'Kembali', en: 'Back' },
    'Keperluan pengeluaran': {
        id: 'Keperluan pengeluaran',
        en: 'Expense purpose',
    },
    Keterangan: { id: 'Keterangan', en: 'Description' },
    Ketik: { id: 'Ketik', en: 'Type' },
    Keuangan: { id: 'Keuangan', en: 'Finance' },
    'Keuangan transaksi': {
        id: 'Keuangan transaksi',
        en: 'Transaction finances',
    },
    Kode: { id: 'Kode', en: 'Code' },
    'Kode dan tipe akan dikunci setelah metode dipakai transaksi.': {
        id: 'Kode dan tipe akan dikunci setelah metode dipakai transaksi.',
        en: 'Codes and types are locked once a method is used in transactions.',
    },
    'Kode dan tipe dikunci setelah kategori tercatat pada transaksi.': {
        id: 'Kode dan tipe dikunci setelah kategori tercatat pada transaksi.',
        en: 'Codes and types are locked once a category is used in transactions.',
    },
    'Kode register': { id: 'Kode register', en: 'Register code' },
    'Komposisi metode pembayaran': {
        id: 'Komposisi metode pembayaran',
        en: 'Payment method breakdown',
    },
    'Konfirmasi Approval': {
        id: 'Konfirmasi Approval',
        en: 'Confirm approval',
    },
    'Konfirmasi reset': { id: 'Konfirmasi reset', en: 'Reset confirmation' },
    Konteks: { id: 'Konteks', en: 'Context' },
    'Kontribusi terhadap gross collections pada periode.': {
        id: 'Kontribusi terhadap gross collections pada periode.',
        en: 'Share of gross collections in this period.',
    },
    'Koreksi Pembayaran — booking tetap pada statusnya': {
        id: 'Koreksi Pembayaran — booking tetap pada statusnya',
        en: 'Payment correction — booking status remains unchanged',
    },
    Kota: { id: 'Kota', en: 'City' },
    'Kota / cabang tujuan': {
        id: 'Kota / cabang tujuan',
        en: 'Destination city / branch',
    },
    Kritis: { id: 'Kritis', en: 'Critical' },
    'Lead time (menit)': { id: 'Lead time (menit)', en: 'Lead time (minutes)' },
    Ledger: { id: 'Buku besar', en: 'Ledger' },
    'Ledger expected': {
        id: 'Saldo buku kas yang diharapkan',
        en: 'Ledger expected',
    },
    'Ledger kas': { id: 'Ledger kas', en: 'Cash ledger' },
    'Legacy Import': { id: 'Impor Data Lama', en: 'Legacy Import' },
    'Legacy Import RentalV1': {
        id: 'Impor Data Lama RentalV1',
        en: 'Legacy Import RentalV1',
    },
    Lingkup: { id: 'Lingkup', en: 'Scope' },
    'Lingkup reset': { id: 'Lingkup reset', en: 'Reset scope' },
    'Lokasi aset tidak dipindahkan.': {
        id: 'Lokasi aset tidak dipindahkan.',
        en: 'Asset locations remain unchanged.',
    },
    Maintenance: { id: 'Perawatan', en: 'Maintenance' },
    'Maksimal refund yang masih tersedia adalah': {
        id: 'Maksimal refund yang masih tersedia adalah',
        en: 'Maximum remaining refundable amount is',
    },
    'Mapping Cabang': { id: 'Mapping Cabang', en: 'Branch mapping' },
    'Mapping belum dapat dikonfirmasi': {
        id: 'Mapping belum dapat dikonfirmasi',
        en: 'Mapping cannot be confirmed',
    },
    'Mapping dikonfirmasi': {
        id: 'Pemetaan dikonfirmasi',
        en: 'Mapping confirmed',
    },
    'Mapping terkonfirmasi': {
        id: 'Mapping terkonfirmasi',
        en: 'Mapping confirmed',
    },
    'Masih refundable': { id: 'Masih refundable', en: 'Remaining refundable' },
    'Master Finance & Kasir': {
        id: 'Master Keuangan & Kasir',
        en: 'Master Finance & Kasir',
    },
    'Master data tetap aman': {
        id: 'Master data tetap aman',
        en: 'Master data is preserved',
    },
    Masuk: { id: 'Masuk', en: 'Log in' },
    'Masukkan password saat ini': {
        id: 'Masukkan password saat ini',
        en: 'Enter current password',
    },
    'Memproses preview': { id: 'Sedang mempratinjau', en: 'Previewing' },
    Memvalidasi: { id: 'Sedang memvalidasi', en: 'Validating' },
    Memverifikasi: { id: 'Sedang memverifikasi', en: 'Verifying' },
    Mengeksekusi: { id: 'Sedang mengeksekusi', en: 'Executing' },
    'Mengunggah file': { id: 'Mengunggah file', en: 'Uploading file' },
    'Menunggu approval': { id: 'Menunggu approval', en: 'Pending approval' },
    Metode: { id: 'Metode', en: 'Method' },
    'Metode aktif': { id: 'Metode aktif', en: 'Active methods' },
    'Metode bayar': { id: 'Metode bayar', en: 'Payment method' },
    'Metode payout': { id: 'Metode payout', en: 'Payout method' },
    'Metode pembayaran': { id: 'Metode pembayaran', en: 'Payment method' },
    'Minimal 10 karakter': {
        id: 'Minimal 10 karakter',
        en: 'At least 10 characters',
    },
    Modal: { id: 'Modal', en: 'Opening cash' },
    'Modal awal': { id: 'Modal awal', en: 'Opening cash' },
    Modul: { id: 'Modul', en: 'Module' },
    'Nama kategori': { id: 'Nama kategori', en: 'Category name' },
    'Nama metode': { id: 'Nama metode', en: 'Method name' },
    'Nama register': { id: 'Nama register', en: 'Register name' },
    Net: { id: 'Bersih', en: 'Net' },
    'Net payment': { id: 'Pembayaran bersih', en: 'Net payment' },
    'Net payment cash completed': {
        id: 'Pembayaran tunai bersih selesai',
        en: 'Net payment cash completed',
    },
    'Net payment non-cash completed': {
        id: 'Pembayaran non-tunai bersih selesai',
        en: 'Net payment non-cash completed',
    },
    'No. nota/invoice, opsional': {
        id: 'No. nota/invoice, opsional',
        en: 'Receipt/invoice number, optional',
    },
    Nominal: { id: 'Nominal', en: 'Amount' },
    'Nominal payment': { id: 'Nominal payment', en: 'Payment amount' },
    'Nominal refund': { id: 'Nominal refund', en: 'Refund amount' },
    Nomor: { id: 'Nomor', en: 'Number' },
    'Nomor pelanggan': { id: 'Nomor pelanggan', en: 'Customer number' },
    'Nomor transfer / settlement': {
        id: 'Nomor transfer / settlement',
        en: 'Transfer / settlement number',
    },
    'Nomor, vendor, referensi, catatan...': {
        id: 'Nomor, vendor, referensi, catatan...',
        en: 'Number, vendor, reference, notes...',
    },
    'Non-tunai aktif': {
        id: 'Non-tunai aktif',
        en: 'Active non-cash payments',
    },
    Nonaktif: { id: 'Nonaktif', en: 'Inactive' },
    'Normalisasi kondisi aset menjadi good': {
        id: 'Normalisasi kondisi aset menjadi good',
        en: 'Normalize asset condition to good',
    },
    'Notification & Reminder Center': {
        id: 'Pusat Notifikasi & Pengingat',
        en: 'Notification & Reminder Center',
    },
    Oleh: { id: 'Oleh', en: 'By' },
    Opsional: { id: 'Opsional', en: 'Optional' },
    Outstanding: { id: 'Belum terselesaikan', en: 'Outstanding' },
    PREFIX: { id: 'PREFIX', en: 'PREFIX' },
    'PREFIX import': { id: 'PREFIX import', en: 'Import prefix' },
    Paid: { id: 'Dibayar', en: 'Paid' },
    Parsed: { id: 'Terurai', en: 'Parsed' },
    'Password Super Admin': {
        id: 'Password Super Admin',
        en: 'Super Admin password',
    },
    Payment: { id: 'Pembayaran', en: 'Payment' },
    'Payment Center': { id: 'Pusat Pembayaran', en: 'Payment Center' },
    'Payment OUT sebesar': {
        id: 'Payment OUT sebesar',
        en: 'Outgoing payment of',
    },
    'Payment Sumber': { id: 'Payment Sumber', en: 'Source payment' },
    'Payment completed': { id: 'Pembayaran selesai', en: 'Payment completed' },
    'Payment dan refund terbaru pada periode terpilih.': {
        id: 'Payment dan refund terbaru pada periode terpilih.',
        en: 'Recent payments and refunds for the selected period.',
    },
    'Payment keluar': { id: 'Payment keluar', en: 'Outgoing payments' },
    'Payment masuk': { id: 'Payment masuk', en: 'Incoming payments' },
    'Payment non-tunai tidak membentuk cash ledger.': {
        id: 'Payment non-tunai tidak membentuk cash ledger.',
        en: 'Non-cash payments do not create cash ledger entries.',
    },
    'Payment telah di-void': {
        id: 'Payment telah di-void',
        en: 'Payment has been voided',
    },
    'Payment void': { id: 'Pembayaran void', en: 'Payment void' },
    'Payment, booking, rental, pelanggan, referensi...': {
        id: 'Payment, booking, rental, pelanggan, referensi...',
        en: 'Payment, booking, rental, customer, reference...',
    },
    'Payout selesai': { id: 'Payout selesai', en: 'Payout completed' },
    Pelanggan: { id: 'Pelanggan', en: 'Customers' },
    'Pemasukan dikurangi pengeluaran aktif': {
        id: 'Pemasukan dikurangi pengeluaran aktif',
        en: 'Incoming minus outgoing active payments',
    },
    'Pembatalan Booking — stok dilepas saat disetujui': {
        id: 'Pembatalan Booking — stok dilepas saat disetujui',
        en: 'Booking cancellation — release stock upon approval',
    },
    'Pembayaran Rental': { id: 'Pembayaran rental', en: 'Rental payment' },
    'Pemulihan inventaris': {
        id: 'Pemulihan inventaris',
        en: 'Inventory recovery',
    },
    'Penerima:': { id: 'Penerima:', en: 'Recipients:' },
    'Penerimaan rental': { id: 'Penerimaan rental', en: 'Rental receipts' },
    Pengaju: { id: 'Pengaju', en: 'Requested by' },
    'Pengeluaran Operasional': {
        id: 'Pengeluaran operasional',
        en: 'Operational expense',
    },
    'Pengembalian Rental': { id: 'Pengembalian rental', en: 'Rental return' },
    'Penolakan melepaskan nominal yang sebelumnya direservasi.': {
        id: 'Penolakan melepaskan nominal yang sebelumnya direservasi.',
        en: 'Rejection releases the amount previously reserved.',
    },
    Perawatan: { id: 'Perawatan', en: 'Maintenance' },
    'Perbandingan arus kas periode dan piutang saat ini.': {
        id: 'Perbandingan arus kas periode dan piutang saat ini.',
        en: 'Compare period cash flow with current receivables.',
    },
    'Perbandingan periode sebelumnya:': {
        id: 'Perbandingan periode sebelumnya:',
        en: 'Comparison with previous period:',
    },
    'Performa cabang': { id: 'Performa cabang', en: 'Branch performance' },
    Peringatan: { id: 'Peringatan', en: 'Warning' },
    'Peringatan kritis': { id: 'Peringatan kritis', en: 'Critical alerts' },
    'Perlu perhatian': { id: 'Perlu perhatian', en: 'Needs attention' },
    'Perpanjangan Rental': {
        id: 'Perpanjangan rental',
        en: 'Rental extension',
    },
    'Perubahan master ditolak': {
        id: 'Perubahan master ditolak',
        en: 'Master data changes rejected',
    },
    Petugas: { id: 'Petugas', en: 'Operator' },
    'Pilih cabang': { id: 'Pilih cabang', en: 'Select branch' },
    'Pilih kategori': { id: 'Pilih kategori', en: 'Select category' },
    'Pilih kota/cabang yang benar sebelum upload. Maksimal': {
        id: 'Pilih kota/cabang yang benar sebelum upload. Maksimal',
        en: 'Select the correct city/branch before uploading. Maximum',
    },
    'Pilih metode': { id: 'Pilih metode', en: 'Select method' },
    'Pilih sesi kas': { id: 'Pilih sesi kas', en: 'Select cash session' },
    'Pilih tujuan refund': {
        id: 'Pilih tujuan refund',
        en: 'Select refund purpose',
    },
    Piutang: { id: 'Piutang', en: 'Receivables' },
    'Piutang melewati jatuh tempo': {
        id: 'Piutang melewati jatuh tempo',
        en: 'Overdue receivables',
    },
    'Piutang rental saat ini': {
        id: 'Piutang rental saat ini',
        en: 'Current rental receivables',
    },
    'Preferensi Notifikasi Saya': {
        id: 'Preferensi Notifikasi Saya',
        en: 'My notification preferences',
    },
    'Preferensi Saya': { id: 'Preferensi Saya', en: 'My preferences' },
    Preview: { id: 'Pratinjau', en: 'Preview' },
    'Preview baris staging': {
        id: 'Preview baris staging',
        en: 'Staging row preview',
    },
    'Preview dalam antrean': {
        id: 'Pratinjau dalam antrean',
        en: 'Preview queued',
    },
    'Preview tersedia': { id: 'Pratinjau tersedia', en: 'Preview available' },
    Prioritas: { id: 'Prioritas', en: 'Priority' },
    'Prioritas minimum email': {
        id: 'Prioritas minimum email',
        en: 'Minimum email priority',
    },
    'Proses terakhir gagal': {
        id: 'Proses terakhir gagal',
        en: 'Last process failed',
    },
    'Proteksi import': { id: 'Proteksi import', en: 'Import safeguards' },
    'Read-only history': { id: 'Riwayat hanya baca', en: 'Read-only history' },
    Recorded: { id: 'Tercatat', en: 'Recorded' },
    Referensi: { id: 'Referensi', en: 'Reference' },
    'Referensi dokumen': { id: 'Referensi dokumen', en: 'Document reference' },
    'Referensi eksternal': {
        id: 'Referensi eksternal',
        en: 'External reference',
    },
    'Referensi payout': { id: 'Referensi payout', en: 'Payout reference' },
    'Referensi pembayaran': {
        id: 'Referensi pembayaran',
        en: 'Payment reference',
    },
    'Referensi transaksi': {
        id: 'Referensi transaksi',
        en: 'Transaction reference',
    },
    Refund: { id: 'Pengembalian dana', en: 'Refund' },
    'Refund Center': { id: 'Pusat Pengembalian Dana', en: 'Refund Center' },
    'Refund Payment': {
        id: 'Kembalikan dana pembayaran',
        en: 'Refund Payment',
    },
    'Refund diajukan': { id: 'Refund diajukan', en: 'Refund requested' },
    'Refund dibatalkan': { id: 'Refund dibatalkan', en: 'Refund cancelled' },
    'Refund dibayarkan': { id: 'Refund dibayarkan', en: 'Refunds paid' },
    'Refund disetujui': { id: 'Refund disetujui', en: 'Refund approved' },
    'Refund ditolak': { id: 'Refund ditolak', en: 'Refund rejected' },
    'Refund menunggu approval': {
        id: 'Refund menunggu approval',
        en: 'Refunds pending approval',
    },
    'Refund outstanding': {
        id: 'Pengembalian dana tertunda',
        en: 'Refund outstanding',
    },
    'Refund paid': { id: 'Refund paid', en: 'Paid refunds' },
    'Refund siap dibayarkan': {
        id: 'Refund siap dibayarkan',
        en: 'Refunds ready for payout',
    },
    'Refund, payment, pelanggan, referensi...': {
        id: 'Refund, payment, pelanggan, referensi...',
        en: 'Refund, payment, customer, reference...',
    },
    Register: { id: 'Kasir', en: 'Register' },
    'Register / Cabang': { id: 'Register / Cabang', en: 'Register / Branch' },
    'Rekonsiliasi target ID, rental aktif, nominal, dan pengembalian.': {
        id: 'Rekonsiliasi target ID, rental aktif, nominal, dan pengembalian.',
        en: 'Reconciliation of target IDs, active rentals, amounts, and returns.',
    },
    Rental: { id: 'Rental', en: 'Rentals' },
    Request: { id: 'Permintaan', en: 'Request' },
    'Request ID': { id: 'ID Permintaan', en: 'Request ID' },
    'Requested + approved': {
        id: 'Diajukan + disetujui',
        en: 'Requested + approved',
    },
    Reset: { id: 'Reset', en: 'Reset' },
    'Reset Data Operasional': {
        id: 'Reset Data Operasional',
        en: 'Reset Operational Data',
    },
    'Reset data operasional sekarang?': {
        id: 'Reset data operasional sekarang?',
        en: 'Reset operational data now?',
    },
    'Reset filter': { id: 'Reset filter', en: 'Reset filters' },
    'Reset tidak mengubah fondasi bisnis dan identitas aset.': {
        id: 'Reset tidak mengubah fondasi bisnis dan identitas aset.',
        en: 'Reset does not alter core business data or asset identities.',
    },
    'Ringkasan hasil parser, validasi, dan execute per tabel RentalV1.': {
        id: 'Ringkasan hasil parser, validasi, dan execute per tabel RentalV1.',
        en: 'Parser, validation, and execution results by RentalV1 table.',
    },
    'Riwayat batch import': {
        id: 'Riwayat batch import',
        en: 'Import batch history',
    },
    'Riwayat payment': { id: 'Riwayat pembayaran', en: 'Payment history' },
    'Riwayat pengeluaran': { id: 'Riwayat pengeluaran', en: 'Expense history' },
    'Riwayat sesi': { id: 'Riwayat sesi', en: 'Session history' },
    'Riwayat sesi kas': { id: 'Riwayat sesi kas', en: 'Cash session history' },
    Row: { id: 'Baris', en: 'Row' },
    Rows: { id: 'Baris', en: 'Rows' },
    Saldo: { id: 'Saldo', en: 'Balance' },
    'Saldo aktual': { id: 'Saldo aktual', en: 'Actual balance' },
    'Saldo awal': { id: 'Saldo awal', en: 'Opening balance' },
    'Saldo ekspektasi': { id: 'Saldo ekspektasi', en: 'Expected balance' },
    'Saldo ekspektasi sekarang': {
        id: 'Saldo ekspektasi sekarang',
        en: 'Current expected balance',
    },
    'Saldo pembukaan': { id: 'Saldo pembukaan', en: 'Opening balance' },
    'Saldo setelah': { id: 'Saldo setelah', en: 'Balance after' },
    'Saldo setelah transaksi:': {
        id: 'Saldo setelah transaksi:',
        en: 'Balance after transaction:',
    },
    'Sampai tanggal': { id: 'Sampai tanggal', en: 'To date' },
    'Saya memastikan database ini berasal dari operasional': {
        id: 'Saya memastikan database ini berasal dari operasional',
        en: 'I confirm this database came from operations in',
    },
    Sebelum: { id: 'Sebelum', en: 'Before' },
    'Sedang diproses': { id: 'Sedang diproses', en: 'Processing' },
    'Sedang ditunda': { id: 'Sedang ditunda', en: 'Snoozed' },
    Selisih: { id: 'Selisih', en: 'Discrepancy' },
    'Selisih penutupan kas': {
        id: 'Selisih penutupan kas',
        en: 'Cash closing variances',
    },
    'Semua aktor': { id: 'Semua aktor', en: 'All actors' },
    'Semua batch': { id: 'Semua batch', en: 'All batches' },
    'Semua cabang': { id: 'Semua cabang', en: 'All branches' },
    'Semua cabang perusahaan': {
        id: 'Semua cabang perusahaan',
        en: 'All company branches',
    },
    'Semua cabang yang dapat diakses': {
        id: 'Semua cabang yang dapat diakses',
        en: 'All accessible branches',
    },
    'Semua data operasional diarahkan ke': {
        id: 'Semua data operasional diarahkan ke',
        en: 'All operational data is directed to',
    },
    'Semua event': { id: 'Semua event', en: 'All events' },
    'Semua kategori': { id: 'Semua kategori', en: 'All categories' },
    'Semua metode': { id: 'Semua metode', en: 'All methods' },
    'Semua modul': { id: 'Semua modul', en: 'All modules' },
    'Semua prioritas': { id: 'Semua prioritas', en: 'All priorities' },
    'Semua status': { id: 'Semua status', en: 'All statuses' },
    'Semua sumber': { id: 'Semua sumber', en: 'All sources' },
    Sesi: { id: 'Sesi', en: 'Session' },
    'Sesi Kas & Ledger': {
        id: 'Sesi Kas & Ledger',
        en: 'Cash sessions & ledger',
    },
    'Sesi kas #': { id: 'Sesi kas #', en: 'Cash session #' },
    'Sesi kas aktif': { id: 'Sesi kas aktif', en: 'Active cash session' },
    'Sesi kas terbuka': { id: 'Sesi kas terbuka', en: 'Open cash sessions' },
    'Sesi terbuka': { id: 'Sesi terbuka', en: 'Open session' },
    'Sesuai filter aktif': {
        id: 'Sesuai filter aktif',
        en: 'Based on active filters',
    },
    Sesudah: { id: 'Sesudah', en: 'After' },
    Setujui: { id: 'Setujui', en: 'Approve' },
    'Setujui refund?': { id: 'Setujui refund?', en: 'Approve refund?' },
    'Siap dibayar': { id: 'Siap dibayar', en: 'Ready for payout' },
    'Simpan kategori': { id: 'Simpan kategori', en: 'Save category' },
    'Simpan metode': { id: 'Simpan metode', en: 'Save method' },
    'Simpan perubahan': { id: 'Simpan perubahan', en: 'Save changes' },
    'Simpan register': { id: 'Simpan register', en: 'Save register' },
    'Simpan sebagai Recorded': {
        id: 'Simpan sebagai Recorded',
        en: 'Save as recorded',
    },
    Sistem: { id: 'Sistem', en: 'System' },
    Skipped: { id: 'Dilewati', en: 'Skipped' },
    Status: { id: 'Status', en: 'Status' },
    'Status approved': { id: 'Status disetujui', en: 'Status approved' },
    'Status requested': { id: 'Status diajukan', en: 'Status requested' },
    'Status sesi': { id: 'Status sesi', en: 'Session status' },
    'Stock opname': { id: 'Stock opname', en: 'Stocktaking' },
    'Stok bulk': { id: 'Stok bulk', en: 'Bulk inventory' },
    Subjek: { id: 'Subjek', en: 'Subject' },
    'Sudah dibaca': { id: 'Sudah dibaca', en: 'Read' },
    'Sudah dibayar': { id: 'Sudah dibayar', en: 'Paid' },
    'Sudah dieksekusi': { id: 'Dieksekusi', en: 'Executed' },
    Sumber: { id: 'Sumber', en: 'Source' },
    'Sumber Transaksi': { id: 'Sumber Transaksi', en: 'Transaction source' },
    'Sumber transaksi': { id: 'Sumber transaksi', en: 'Transaction sources' },
    'Tambah data': { id: 'Tambah data', en: 'Add record' },
    'Tandai dibaca': { id: 'Tandai dibaca', en: 'Mark as read' },
    'Tandai semua dibaca': {
        id: 'Tandai semua dibaca',
        en: 'Mark all as read',
    },
    'Tanggal bayar': { id: 'Tanggal bayar', en: 'Payment date' },
    'Tanggal kejadian': { id: 'Tanggal kejadian', en: 'Event date' },
    'Target V2': { id: 'Target V2', en: 'V2 target' },
    'Target:': { id: 'Target:', en: 'Target:' },
    Terapkan: { id: 'Terapkan', en: 'Apply' },
    Terdapat: { id: 'Terdapat', en: 'There are' },
    Tervalidasi: { id: 'Tervalidasi', en: 'Validated' },
    Terverifikasi: { id: 'Terverifikasi', en: 'Verified' },
    'Tidak ada aktivitas sesuai filter.': {
        id: 'Tidak ada aktivitas sesuai filter.',
        en: 'No activity matches these filters.',
    },
    'Tidak ada bukti terlampir.': {
        id: 'Tidak ada bukti terlampir.',
        en: 'No evidence attached.',
    },
    'Tidak ada cabang aktif dengan akses import untuk akun ini.': {
        id: 'Tidak ada cabang aktif dengan akses import untuk akun ini.',
        en: 'No active branches with import access for this account.',
    },
    'Tidak ada payment sesuai filter.': {
        id: 'Tidak ada payment sesuai filter.',
        en: 'No payments match these filters.',
    },
    'Tidak ada refund sesuai filter.': {
        id: 'Tidak ada refund sesuai filter.',
        en: 'No refunds match these filters.',
    },
    'Tidak ada reminder yang sesuai dengan filter saat ini.': {
        id: 'Tidak ada reminder yang sesuai dengan filter saat ini.',
        en: 'No reminders match these filters.',
    },
    'Tidak ada sesi aktif. Sesi terakhir:': {
        id: 'Tidak ada sesi aktif. Sesi terakhir:',
        en: 'No active sessions. Last session:',
    },
    'Tidak ada transaksi kas pada sesi ini.': {
        id: 'Tidak ada transaksi kas pada sesi ini.',
        en: 'No cash transactions for this session.',
    },
    'Timeline Workflow': { id: 'Linimasa alur kerja', en: 'Timeline Workflow' },
    'Timeline aktivitas': { id: 'Timeline aktivitas', en: 'Activity timeline' },
    Tipe: { id: 'Tipe', en: 'Type' },
    Tolak: { id: 'Tolak', en: 'Reject' },
    'Tolak refund': { id: 'Tolak refund', en: 'Reject refund' },
    'Total pengajuan': { id: 'Total pengajuan', en: 'Total requested' },
    'Total staging': { id: 'Total staging', en: 'Total staging' },
    'Total tercatat': { id: 'Total tercatat', en: 'Total recorded' },
    Transaksi: { id: 'Transaksi', en: 'Transactions' },
    Transfer: { id: 'Transfer', en: 'Transfer' },
    'Transfer aset': { id: 'Transfer aset', en: 'Asset transfers' },
    'Tren arus kas': { id: 'Tren arus kas', en: 'Cash flow trend' },
    'Tren payment masuk, payment keluar, refund, dan arus kas bersih': {
        id: 'Tren payment masuk, payment keluar, refund, dan arus kas bersih',
        en: 'Trend of incoming payments, outgoing payments, refunds, and net cash flow',
    },
    'Tujuan import & PREFIX': {
        id: 'Tujuan import & PREFIX',
        en: 'Import destination & prefix',
    },
    'Tujuan refund': { id: 'Tujuan refund', en: 'Refund purpose' },
    'Tujuan refund DP (wajib)': {
        id: 'Tujuan refund DP (wajib)',
        en: 'Down payment refund purpose (required)',
    },
    'Tujuan:': { id: 'Tujuan:', en: 'Destination:' },
    'Tunai aktif': { id: 'Tunai aktif', en: 'Active cash payments' },
    'Tunda...': { id: 'Tunda...', en: 'Snooze...' },
    Tutup: { id: 'Tutup', en: 'Close' },
    'Tutup sesi': { id: 'Tutup sesi', en: 'Close session' },
    'Ulangi setiap (menit)': {
        id: 'Ulangi setiap (menit)',
        en: 'Repeat every (minutes)',
    },
    'Unduh bukti payout': {
        id: 'Unduh bukti payout',
        en: 'Download payout proof',
    },
    Upload: { id: 'Unggah', en: 'Upload' },
    'Upload SQL RentalV1': {
        id: 'Upload SQL RentalV1',
        en: 'Upload RentalV1 SQL',
    },
    Urutan: { id: 'Urutan', en: 'Order' },
    'Utilitas UAT / Development': {
        id: 'Utilitas UAT / Development',
        en: 'UAT / Development utility',
    },
    Valid: { id: 'Valid', en: 'Valid' },
    Validasi: { id: 'Validasi', en: 'Validation' },
    'Validasi dalam antrean': {
        id: 'Validasi dalam antrean',
        en: 'Validation queued',
    },
    Variance: { id: 'Selisih', en: 'Variance' },
    Vendor: { id: 'Pemasok', en: 'Vendor' },
    'Vendor / penerima': { id: 'Vendor / penerima', en: 'Vendor / payee' },
    Verifikasi: { id: 'Verifikasi', en: 'Verify' },
    'Verifikasi dalam antrean': {
        id: 'Verifikasi dalam antrean',
        en: 'Verification queued',
    },
    Void: { id: 'Dibatalkan (void)', en: 'Void' },
    'Void Payment': { id: 'Void pembayaran', en: 'Void Payment' },
    'Void expense': { id: 'Void expense', en: 'Void expense' },
    'Void tidak tersedia': {
        id: 'Void tidak tersedia',
        en: 'Void unavailable',
    },
    'Wajib referensi transaksi': {
        id: 'Wajib referensi transaksi',
        en: 'Transaction reference required',
    },
    Waktu: { id: 'Waktu', en: 'Time' },
    'Waktu & petugas': { id: 'Waktu & petugas', en: 'Time & operator' },
    'Waktu tenang mulai': { id: 'Waktu tenang mulai', en: 'Quiet hours start' },
    'Waktu tenang selesai': {
        id: 'Waktu tenang selesai',
        en: 'Quiet hours end',
    },
    Warning: { id: 'Peringatan', en: 'Warning' },
    'akan dibuat. Metode CASH juga menulis satu baris cash ledger OUT.': {
        id: 'akan dibuat. Metode CASH juga menulis satu baris cash ledger OUT.',
        en: 'will be created. CASH also writes one OUT entry to the cash ledger.',
    },
    'baris error. Periksa referensi atau kolom wajib pada antrean isu.': {
        id: 'baris error. Periksa referensi atau kolom wajib pada antrean isu.',
        en: 'error rows. Review references or required columns in the issue queue.',
    },
    'baris yatim': { id: 'baris yatim', en: 'orphan rows' },
    'biaya transfer ·': { id: 'biaya transfer ·', en: 'transfer expenses ·' },
    'cabang tersedia': { id: 'cabang tersedia', en: 'available branches' },
    'dan PREFIX': { id: 'dan PREFIX', en: 'and the prefix' },
    'dengan PREFIX': { id: 'dengan PREFIX', en: 'with prefix' },
    'event, aktor, cabang, subject...': {
        id: 'event, aktor, cabang, subject...',
        en: 'event, actor, branch, subject...',
    },
    'expense operasional': {
        id: 'expense operasional',
        en: 'operational expenses',
    },
    'histori sesi': { id: 'histori sesi', en: 'session history' },
    'isu ditemukan ·': { id: 'isu ditemukan ·', en: 'issues found ·' },
    item: { id: 'item', en: 'items' },
    kali: { id: 'kali', en: 'times' },
    'ledger ·': { id: 'ledger ·', en: 'ledger ·' },
    pada: { id: 'pada', en: 'on' },
    'payment ·': { id: 'payment ·', en: 'payment ·' },
    referensi: { id: 'referensi', en: 'references' },
    refund: { id: 'pengembalian dana', en: 'refund' },
    sampai: { id: 'sampai', en: 'to' },
    'sudah benar.': { id: 'sudah benar.', en: 'are correct.' },
    transaksi: { id: 'transaksi', en: 'transactions' },
    '· Actual': { id: '· Actual', en: '· Actual' },
    '· Environment:': { id: '· Environment:', en: '· Environment:' },
    '· PREFIX': { id: '· PREFIX', en: '· Prefix' },
    '· dibuka': { id: '· dibuka', en: '· opened' },
    '· keluar': { id: '· keluar', en: '· out' },
    '· urutan': { id: '· urutan', en: '· order' },
    'Sesi Kas': { id: 'Sesi kas', en: 'Cash session' },
    'Riwayat Sesi Kas': { id: 'Riwayat sesi kas', en: 'Cash session history' },
    Import: { id: 'Impor', en: 'Import' },
    Ya: { id: 'Ya', en: 'Yes' },
    Tidak: { id: 'Tidak', en: 'No' },
};

/** Display-only conversion for API status and enum codes; unknown values are preserved verbatim. */
const statusLabels: Record<string, { id: string; en: string }> = {
    draft: { id: 'Draf', en: 'Draft' },
    requested: { id: 'Diajukan', en: 'Requested' },
    pending: { id: 'Menunggu', en: 'Pending' },
    pending_approval: { id: 'Menunggu persetujuan', en: 'Pending approval' },
    approved: { id: 'Disetujui', en: 'Approved' },
    rejected: { id: 'Ditolak', en: 'Rejected' },
    cancelled: { id: 'Dibatalkan', en: 'Cancelled' },
    completed: { id: 'Selesai', en: 'Completed' },
    paid: { id: 'Dibayar', en: 'Paid' },
    void: { id: 'Void', en: 'Void' },
    recorded: { id: 'Tercatat', en: 'Recorded' },
    open: { id: 'Terbuka', en: 'Open' },
    closed: { id: 'Ditutup', en: 'Closed' },
    critical: { id: 'Kritis', en: 'Critical' },
    warning: { id: 'Peringatan', en: 'Warning' },
    info: { id: 'Informasi', en: 'Information' },
    unread: { id: 'Belum dibaca', en: 'Unread' },
    read: { id: 'Sudah dibaca', en: 'Read' },
    snoozed: { id: 'Ditunda', en: 'Snoozed' },
    dismissed: { id: 'Diabaikan', en: 'Dismissed' },
    booked: { id: 'Dipesan', en: 'Booked' },
    rental: { id: 'Rental', en: 'Rental' },
    booking: { id: 'Booking', en: 'Booking' },
    rental_checkout: { id: 'Checkout rental', en: 'Rental checkout' },
    rental_return: { id: 'Pengembalian rental', en: 'Rental return' },
    rental_extension: { id: 'Perpanjangan rental', en: 'Rental extension' },
    transfer_expense: { id: 'Biaya transfer', en: 'Transfer expense' },
    operational_expense: {
        id: 'Pengeluaran operasional',
        en: 'Operational expense',
    },
    uploaded: { id: 'Diunggah', en: 'Uploaded' },
    queued_preview: { id: 'Pratinjau dalam antrean', en: 'Preview queued' },
    previewing: { id: 'Sedang mempratinjau', en: 'Previewing' },
    previewed: { id: 'Pratinjau tersedia', en: 'Preview available' },
    queued_validation: {
        id: 'Validasi dalam antrean',
        en: 'Validation queued',
    },
    validating: { id: 'Sedang memvalidasi', en: 'Validating' },
    validated: { id: 'Tervalidasi', en: 'Validated' },
    mapped: { id: 'Pemetaan terkonfirmasi', en: 'Mapping confirmed' },
    queued_execution: { id: 'Eksekusi dalam antrean', en: 'Execution queued' },
    executing: { id: 'Sedang mengeksekusi', en: 'Executing' },
    executed: { id: 'Dieksekusi', en: 'Executed' },
    queued_verification: {
        id: 'Verifikasi dalam antrean',
        en: 'Verification queued',
    },
    verifying: { id: 'Sedang memverifikasi', en: 'Verifying' },
    verified: { id: 'Terverifikasi', en: 'Verified' },
    failed: { id: 'Gagal', en: 'Failed' },
    skipped: { id: 'Dilewati', en: 'Skipped' },
    cash: { id: 'Tunai', en: 'Cash' },
    non_cash: { id: 'Non-tunai', en: 'Non-cash' },
    inflow: { id: 'Kas masuk', en: 'Cash in' },
    outflow: { id: 'Kas keluar', en: 'Cash out' },
};

export function stage5Display(
    value: string | null | undefined,
    locale: AppLocale = getEffectiveLocale(),
): string {
    if (!value) {
        return '—';
    }

    const label = statusLabels[value.toLowerCase()];

    return label ? label[locale] : (knownLabels[value]?.[locale] ?? value);
}

/** Time zone fixed for the application's operational timezone. Dates are presentation-only. */
export function stage5Date(
    value: Date | string | number | null | undefined,
    locale: AppLocale = getEffectiveLocale(),
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale === 'en' ? 'en-GB' : 'id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(date);
}

/** Keep Indonesian rupiah as the currency while respecting decimal/grouping locale. */
export function stage5Money(
    value: number,
    locale: AppLocale = getEffectiveLocale(),
): string {
    return (
        'Rp ' +
        new Intl.NumberFormat(locale === 'en' ? 'en-US' : 'id-ID', {
            maximumFractionDigits: 0,
        }).format(value)
    );
}

export function stage5IntlLocale(
    locale: AppLocale = getEffectiveLocale(),
): string {
    return locale === 'en' ? 'en-US' : 'id-ID';
}

export function stage5Number(
    value: number,
    locale: AppLocale = getEffectiveLocale(),
): string {
    return new Intl.NumberFormat(stage5IntlLocale(locale)).format(value);
}

/** Known default rule copy is localized without overriding customized rule names. */
const notificationRules: Record<
    string,
    { title: [string, string]; description: [string, string] }
> = {
    'booking.starting_soon': {
        title: ['Booking segera dimulai', 'Booking starting soon'],
        description: [
            'Mengingatkan tim operasional sebelum jadwal pengambilan booking.',
            'Reminds operations staff before scheduled booking pickup.',
        ],
    },
    'rental.due_soon': {
        title: [
            'Rental mendekati jatuh tempo',
            'Rental nearing return deadline',
        ],
        description: [
            'Mengingatkan tim sebelum batas pengembalian rental.',
            'Reminds staff before the rental return deadline.',
        ],
    },
    'rental.overdue': {
        title: ['Rental terlambat', 'Overdue rental'],
        description: [
            'Memberi peringatan untuk rental aktif yang melewati jatuh tempo.',
            'Warns about active rentals past their return deadline.',
        ],
    },
    'rental.balance_due': {
        title: ['Saldo rental belum lunas', 'Outstanding rental balance'],
        description: [
            'Mengingatkan saldo rental yang masih harus dibayar.',
            'Reminds staff about outstanding rental balances.',
        ],
    },
    'refund.awaiting_approval': {
        title: ['Refund menunggu persetujuan', 'Refund awaiting approval'],
        description: [
            'Memberi tahu approver saat ada permintaan refund baru.',
            'Notifies approvers of new refund requests.',
        ],
    },
    'refund.awaiting_payment': {
        title: ['Refund siap diproses', 'Refund ready for payout'],
        description: [
            'Memberi tahu kasir saat refund telah disetujui.',
            'Notifies cashiers when a refund is approved.',
        ],
    },
    'cash.open_too_long': {
        title: ['Sesi kas terbuka terlalu lama', 'Cash session open too long'],
        description: [
            'Mengingatkan sesi kas yang belum ditutup setelah batas operasional.',
            'Reminds staff about cash sessions left open beyond operating hours.',
        ],
    },
    'transfer.awaiting_approval': {
        title: ['Transfer menunggu approval', 'Transfer awaiting approval'],
        description: [
            'Mengingatkan approver atas pengajuan transfer antar-cabang.',
            'Reminds approvers of inter-branch transfer requests.',
        ],
    },
    'transfer.dispatch_due': {
        title: ['Transfer siap diberangkatkan', 'Transfer ready for dispatch'],
        description: [
            'Mengingatkan jadwal keberangkatan transfer yang telah disetujui.',
            'Reminds staff of approved transfer departure schedules.',
        ],
    },
    'transfer.arrival_overdue': {
        title: ['Transfer melewati estimasi tiba', 'Transfer arrival overdue'],
        description: [
            'Memberi peringatan transfer yang belum diterima setelah estimasi tiba.',
            'Warns about transfers not received by their estimated arrival time.',
        ],
    },
    'maintenance.stale': {
        title: [
            'Maintenance belum ditindaklanjuti',
            'Maintenance awaiting follow-up',
        ],
        description: [
            'Mengingatkan maintenance aktif yang tidak selesai dalam batas waktu.',
            'Reminds staff about maintenance not completed within the allotted time.',
        ],
    },
    'inventory.scheduled': {
        title: ['Jadwal stock opname', 'Scheduled stocktaking'],
        description: [
            'Mengingatkan stock opname draft yang segera dijalankan.',
            'Reminds staff of upcoming draft stocktaking.',
        ],
    },
    'inventory.awaiting_approval': {
        title: [
            'Stock opname menunggu approval',
            'Stocktaking awaiting approval',
        ],
        description: [
            'Mengingatkan hasil stock opname yang telah diajukan.',
            'Reminds approvers about submitted stocktaking results.',
        ],
    },
    'inventory.unresolved_findings': {
        title: [
            'Temuan stock opname belum selesai',
            'Unresolved stocktaking findings',
        ],
        description: [
            'Mengingatkan temuan yang belum ditindaklanjuti setelah approval.',
            'Reminds staff about findings still unresolved after approval.',
        ],
    },
};

export function stage5RuleLabel(
    code: string,
    fallback: string,
    field: 'title' | 'description',
    locale: AppLocale = getEffectiveLocale(),
): string {
    const rule = notificationRules[code];

    if (!rule) {
        return fallback;
    }

    const [indonesian, english] = rule[field];

    return fallback === indonesian
        ? locale === 'en'
            ? english
            : indonesian
        : fallback;
}

/** Preserve page links; translate only paginator control words. */
export function stage5PaginatorLabel(
    label: string,
    locale: AppLocale = getEffectiveLocale(),
): string {
    return locale === 'en'
        ? label
              .replace(/Sebelumnya/g, 'Previous')
              .replace(/Berikutnya/g, 'Next')
        : label
              .replace(/Previous/g, 'Sebelumnya')
              .replace(/Next/g, 'Berikutnya');
}

/** Localize interpolated explanatory copy without changing numeric or business values. */
export function stage5Choice(
    indonesian: string,
    english: string,
    locale: AppLocale = getEffectiveLocale(),
): string {
    return locale === 'en' ? english : indonesian;
}

/** Localize recognized audit event/module codes for presentation, leaving all raw codes unchanged. */
const auditModules: Record<string, string> = {
    booking: 'Pemesanan',
    rental: 'Penyewaan',
    payment: 'Pembayaran',
    refund: 'Pengembalian dana',
    customer: 'Pelanggan',
    product: 'Produk',
    asset: 'Aset',
    user: 'Pengguna',
    transfer: 'Transfer aset',
    branch: 'Cabang',
    operational_expense: 'Pengeluaran operasional',
    maintenance: 'Perawatan',
    inventory: 'Inventaris',
    stock: 'Stok',
    cash: 'Kas',
    cash_session: 'Sesi kas',
    role: 'Peran',
    financial_category: 'Kategori keuangan',
    transaction_document: 'Dokumen transaksi',
    import: 'Impor',
    other: 'Lainnya',
};
const auditActions: Record<string, string> = {
    created: 'dibuat',
    updated: 'diperbarui',
    deleted: 'dihapus',
    approved: 'disetujui',
    cancelled: 'dibatalkan',
    canceled: 'dibatalkan',
    voided: 'dibatalkan',
    rejected: 'ditolak',
    paid: 'dibayar',
    returned: 'dikembalikan',
    received: 'diterima',
    dispatched: 'dikirim',
    confirmed: 'dikonfirmasi',
    closed: 'ditutup',
    opened: 'dibuka',
    processed: 'diproses',
    recorded: 'dicatat',
    submitted: 'diajukan',
    verified: 'diverifikasi',
};
export function stage5AuditLabel(
    code: string,
    fallback: string,
    locale: AppLocale = getEffectiveLocale(),
): string {
    if (locale === 'en') {
        return fallback;
    }

    const components = code.split('.');

    if (components.length === 1) {
        return auditModules[code] ?? fallback;
    }

    const module = auditModules[components[0]];
    const action = auditActions[components[components.length - 1]];

    return module && action ? `${module} ${action}` : fallback;
}
