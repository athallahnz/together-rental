import type { AppLocale } from '@/lib/i18n';

/** Presentation labels only. API values and historical records are never rewritten. */
const bookingStatuses: Record<string, [string, string]> = {
    draft: ['Draf', 'Draft'],
    confirmed: ['Dikonfirmasi', 'Confirmed'],
    converted: ['Dikonversi', 'Converted'],
    completed: ['Selesai', 'Completed'],
    cancelled: ['Dibatalkan', 'Cancelled'],
    expired: ['Kedaluwarsa', 'Expired'],
};

const rentalStatuses: Record<string, [string, string]> = {
    draft: ['Draf', 'Draft'],
    active: ['Aktif', 'Active'],
    partial_return: ['Dikembalikan sebagian', 'Partially returned'],
    returned: ['Dikembalikan', 'Returned'],
    completed: ['Selesai', 'Completed'],
    cancelled: ['Dibatalkan', 'Cancelled'],
};

const loyaltyTiers: Record<string, [string, string]> = {
    regular: ['Reguler', 'Regular'],
    silver: ['Perak', 'Silver'],
    gold: ['Emas', 'Gold'],
    platinum: ['Platinum', 'Platinum'],
};

export function stage3CustomerHistoryStatus(
    value: string,
    kind: 'rental' | 'booking',
    locale: AppLocale,
): string {
    const labels = kind === 'rental' ? rentalStatuses : bookingStatuses;
    const translation = labels[value];

    return translation ? translation[locale === 'en' ? 1 : 0] : value;
}

export function stage3LoyaltyTier(value: string, locale: AppLocale): string {
    const translation = loyaltyTiers[value.toLowerCase()];

    return translation ? translation[locale === 'en' ? 1 : 0] : value;
}
