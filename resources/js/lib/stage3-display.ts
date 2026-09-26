import type { AppLocale } from '@/lib/i18n';

/** Presentation-only display helpers; never use these labels in API payloads. */
const assetStatuses: Record<string, [string, string]> = {
    available: ['Tersedia', 'Available'],
    reserved: ['Direservasi', 'Reserved'],
    rented: ['Disewa', 'Rented'],
    maintenance: ['Dalam perawatan', 'Under maintenance'],
    in_transit: ['Dalam pengiriman', 'In transit'],
    lost: ['Hilang', 'Lost'],
    damaged: ['Rusak', 'Damaged'],
    disposed: ['Dilepas', 'Disposed'],
    retired: ['Tidak digunakan', 'Retired'],
    sold: ['Terjual', 'Sold'],
};

const disposalMethods: Record<string, [string, string]> = {
    sold: ['Dijual', 'Sold'],
    write_off: ['Dihapusbukukan', 'Written off'],
    donated: ['Didonasikan', 'Donated'],
};

function displayValue(
    value: string,
    catalog: Record<string, [string, string]>,
    locale: AppLocale,
): string {
    const translation = catalog[value];

    return translation ? translation[locale === 'en' ? 1 : 0] : value;
}

export function stage3AssetStatus(value: string, locale: AppLocale): string {
    return displayValue(value, assetStatuses, locale);
}

export function stage3DisposalMethod(value: string, locale: AppLocale): string {
    return displayValue(value, disposalMethods, locale);
}

/** Backend UTC timestamps become Jakarta calendar dates; date-only values retain their calendar day. */
export function formatStage3Date(
    value: string | null | undefined,
    locale: AppLocale,
): string {
    if (!value) {
return '—';
}

    const dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(value);
    const date = new Date(dateOnly ? `${value}T12:00:00Z` : value);

    if (Number.isNaN(date.getTime())) {
return '—';
}

    return new Intl.DateTimeFormat(locale === 'en' ? 'en-GB' : 'id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: dateOnly ? 'UTC' : 'Asia/Jakarta',
    }).format(date);
}

const modules: Record<string, [string, string]> = {
    assets: ['Aset', 'Assets'],
    audit: ['Audit', 'Audit'],
    bookings: ['Pemesanan', 'Bookings'],
    branches: ['Cabang', 'Branches'],
    cash: ['Kas', 'Cash'],
    catalog: ['Katalog', 'Catalog'],
    company: ['Perusahaan', 'Company'],
    customers: ['Pelanggan', 'Customers'],
    documents: ['Dokumen', 'Documents'],
    employees: ['Karyawan', 'Employees'],
    expenses: ['Pengeluaran', 'Expenses'],
    finance: ['Keuangan', 'Finance'],
    inventory: ['Inventaris', 'Inventory'],
    'inventory-audits': ['Audit inventaris', 'Inventory audits'],
    legacy_imports: ['Impor data lama', 'Legacy imports'],
    notifications: ['Notifikasi', 'Notifications'],
    payments: ['Pembayaran', 'Payments'],
    products: ['Produk', 'Products'],
    refunds: ['Pengembalian dana', 'Refunds'],
    rentals: ['Penyewaan', 'Rentals'],
    reports: ['Laporan', 'Reports'],
    roles: ['Peran & hak akses', 'Roles & permissions'],
    settings: ['Pengaturan', 'Settings'],
    transfers: ['Transfer aset', 'Asset transfers'],
    users: ['Pengguna', 'Users'],
};

export function stage3PermissionModule(value: string, locale: AppLocale): string {
    return displayValue(value, modules, locale);
}

const permissionOverrides: Record<string, string> = {
    'assets.inspect': 'Periksa kondisi aset',
    'assets.manage': 'Kelola aset rental',
    'assets.view': 'Lihat aset rental',
    'audit.view': 'Lihat jejak audit',
    'branches.switch': 'Pindah cabang aktif',
    'bookings.cancel': 'Batalkan pemesanan',
    'bookings.create': 'Buat pemesanan',
    'bookings.update': 'Ubah pemesanan',
    'bookings.view': 'Lihat pemesanan',
    'cash.manage': 'Kelola sesi kas',
    'cash.view': 'Lihat sesi kas',
    'company.manage': 'Kelola perusahaan',
    'company.view': 'Lihat perusahaan',
    'customers.create': 'Buat pelanggan',
    'customers.delete': 'Arsipkan pelanggan',
    'customers.view': 'Lihat pelanggan',
    'documents.issue': 'Terbitkan dokumen transaksi',
    'documents.view': 'Lihat dokumen transaksi',
    'finance.dashboard.view': 'Lihat dasbor keuangan',
    'inventory-audits.count': 'Hitung stok inventaris',
    'inventory-audits.create': 'Buat audit inventaris',
    'inventory-audits.view': 'Lihat audit inventaris',
};

const verbs: Record<string, string> = {
    approve: 'Setujui',
    cancel: 'Batalkan',
    complete: 'Selesaikan',
    count: 'Hitung',
    create: 'Buat',
    delete: 'Hapus',
    dispatch: 'Kirim',
    inspect: 'Periksa',
    issue: 'Terbitkan',
    manage: 'Kelola',
    process: 'Proses',
    reopen: 'Buka ulang',
    request: 'Ajukan',
    resolve: 'Selesaikan',
    return: 'Kembalikan',
    switch: 'Pindah',
    update: 'Ubah',
    view: 'Lihat',
};

/** Built-in permissions receive display labels. Slugs, IDs, and custom role data remain unchanged. */
export function stage3PermissionName(
    slug: string,
    sourceName: string,
    locale: AppLocale,
): string {
    if (locale === 'en') {
return sourceName;
}

    if (permissionOverrides[slug]) {
return permissionOverrides[slug];
}

    const segments = slug.split('.');
    const action = segments[segments.length - 1];
    const module = segments.slice(0, -1).join('.');
    const noun = modules[module]?.[0];
    const verb = verbs[action];

    return verb && noun ? `${verb} ${noun.toLowerCase()}` : sourceName;
}
