import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { enMessages, enPlural, idMessages, idPlural } from '@/lib/i18n-catalog';
import type { MessageKey, PluralKey } from '@/lib/i18n-catalog';
import { setEffectiveLocale } from '@/lib/locale-store';

export type AppLocale = 'id' | 'en';

// UAT-035 compatibility for existing source-text translations. New code uses
// semantic tr()/tp(); domain pages migrate in subsequent UAT-035B stages.
const english: Record<string, string> = {
    Ringkasan: 'Overview',
    Dashboard: 'Dashboard',
    Notifikasi: 'Notifications',
    'Operasional Rental': 'Rental Operations',
    Booking: 'Bookings',
    'Rental Control Center': 'Rental Control Center',
    'Rental In Store': 'In-Store Rental',
    'Invoice, Nota & Agreement': 'Invoices, Receipts & Agreements',
    Maintenance: 'Maintenance',
    Keuangan: 'Finance',
    'Finance Dashboard': 'Finance Dashboard',
    'Payment Center': 'Payment Center',
    'Expense & Cash Center': 'Expenses & Cash Center',
    'Refund Center': 'Refund Center',
    'Master Finance & Kasir': 'Finance & Cashier Master Data',
    'Data & Inventaris': 'Data & Inventory',
    Pelanggan: 'Customers',
    'Katalog & Harga': 'Catalog & Pricing',
    'Promosi & Diskon': 'Promotions & Discounts',
    'Siklus Aset': 'Asset Lifecycle',
    'Konten Katalog Publik': 'Public Catalog Content',
    'Kecerdasan Katalog': 'Catalog Intelligence',
    'Transfer Aset': 'Asset Transfers',
    'Stock Opname': 'Stocktaking',
    Laporan: 'Reports',
    'Reporting Center': 'Reporting Center',
    'Analitik Aset': 'Asset Analytics',
    Administrasi: 'Administration',
    Pengaturan: 'Settings',
    Cabang: 'Branches',
    Karyawan: 'Employees',
    Pengguna: 'Users',
    'Role & Hak Akses': 'Roles & Permissions',
    'Audit Trail': 'Audit Trail',
    'Legacy Import': 'Legacy Import',
    'Reset Data Operasional': 'Reset Operational Data',
    'Pusat Pengaturan': 'Settings Center',
    Profil: 'Profile',
    Keamanan: 'Security',
    Tampilan: 'Appearance',
    'Bahasa & Regional': 'Language & Region',
    'Pengaturan akun & sistem': 'Account & System Settings',
    'Kelola profil, keamanan, tampilan, serta konfigurasi operasional Together Kamera dari satu area yang konsisten.':
        'Manage your profile, security, appearance, and Together Kamera operating settings in one place.',
    'Pengaturan operasional tetap menggunakan source-of-truth domain masing-masing.':
        'Operational settings continue to use their respective domain sources of truth.',
    'Pengaturan bahasa': 'Language Settings',
    'Pilih bahasa aplikasi': 'Choose your application language',
    'Bahasa yang dipilih hanya berlaku untuk akun Anda, bukan seluruh cabang atau perusahaan.':
        'Your language preference applies only to your account, not your branch or company.',
    'Bahasa aplikasi': 'Application language',
    'Bahasa Indonesia': 'Bahasa Indonesia',
    'Simpan bahasa': 'Save Language',
    'Menyimpan...': 'Saving...',
    'Bahasa tersimpan': 'Language saved',
    'Bahasa aplikasi tidak berubah.':
        'The application language has not changed.',
    'Cakupan terjemahan saat ini': 'Current translation coverage',
    'Sidebar dan halaman pengaturan bahasa tersedia dalam Indonesia dan Inggris. Halaman operasional, validasi khusus modul, serta PDF akan diterjemahkan bertahap.':
        'The sidebar and language settings are available in Indonesian and English. Operational pages, module-specific validation, and PDFs will be translated in later phases.',
    'Pengaturan tampilan': 'Appearance Settings',
    'Atur preferensi tema antarmuka untuk akun Anda.':
        'Choose the interface theme for your account.',
    'Tema antarmuka': 'Interface Theme',
    'Pilihan ini hanya memengaruhi tampilan akun Anda dan tidak mengubah konfigurasi pengguna lain.':
        "This preference only affects your account and does not change other users' settings.",
};

export function translate(key: string, locale: AppLocale): string {
    return locale === 'en' ? (english[key] ?? key) : key;
}

type InterpolationValues = Record<string, string | number>;

function interpolate(message: string, values: InterpolationValues): string {
    return message.replace(
        /\{([a-zA-Z][a-zA-Z0-9_]*)\}/g,
        (_match, name: string) => {
            if (!Object.prototype.hasOwnProperty.call(values, name)) {
                throw new Error(`Missing i18n placeholder: ${name}`);
            }

            return String(values[name]);
        },
    );
}

/** Unknown semantic keys fail loudly rather than silently displaying the key. */
export function translateKey(
    key: MessageKey,
    locale: AppLocale,
    values: InterpolationValues = {},
): string {
    if (!Object.prototype.hasOwnProperty.call(idMessages, key)) {
        throw new Error(`Missing i18n key: ${key}`);
    }

    const message = locale === 'en' ? enMessages[key] : idMessages[key];

    if (!message) {
        throw new Error(`Missing ${locale} translation for ${key}`);
    }

    return interpolate(message, values);
}

export function translatePlural(
    key: PluralKey,
    count: number,
    locale: AppLocale,
): string {
    if (!Object.prototype.hasOwnProperty.call(idPlural, key)) {
        throw new Error(`Missing plural i18n key: ${key}`);
    }

    const category =
        new Intl.PluralRules(locale === 'en' ? 'en-US' : 'id-ID').select(
            count,
        ) === 'one'
            ? 'one'
            : 'other';
    const forms = locale === 'en' ? enPlural[key] : idPlural[key];

    return interpolate(forms[category], { count });
}

export function useAppLocale() {
    const page = usePage();
    const locale: AppLocale =
        (page.props as { locale?: string }).locale === 'en' ? 'en' : 'id';

    useEffect(() => {
        document.documentElement.lang = locale;
        setEffectiveLocale(locale);
    }, [locale]);

    return {
        locale,
        t: (key: string) => translate(key, locale),
        tr: (key: MessageKey, values?: InterpolationValues) =>
            translateKey(key, locale, values),
        tp: (key: PluralKey, count: number) =>
            translatePlural(key, count, locale),
    };
}
