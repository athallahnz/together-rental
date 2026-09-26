import type { AppLocale } from '@/lib/i18n';

export function intlLocale(locale: AppLocale): string {
    return locale === 'en' ? 'en-US' : 'id-ID';
}

export function formatNumber(
    value: number,
    locale: AppLocale,
    options: Intl.NumberFormatOptions = {},
): string {
    return new Intl.NumberFormat(intlLocale(locale), options).format(value);
}

/** Currency stays IDR; locale changes separators, never the stored amount. */
export function formatMoney(value: number, locale: AppLocale): string {
    return `Rp ${formatNumber(value, locale, { maximumFractionDigits: 2 })}`;
}

type DateValue = string | number | Date | null | undefined;

function asDate(value: DateValue): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = value instanceof Date ? value : new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

/** Specify timeZone for branch-specific display; otherwise preserve browser time zone. */
export function formatDate(
    value: DateValue,
    locale: AppLocale,
    options: Intl.DateTimeFormatOptions = {},
): string {
    const date = asDate(value);

    const usesSpecificDateParts = ['year', 'month', 'day', 'weekday', 'era'].some(
        (field) => Object.prototype.hasOwnProperty.call(options, field),
    );

    return date
        ? new Intl.DateTimeFormat(intlLocale(locale), {
              ...(!usesSpecificDateParts && !options.dateStyle
                  ? { dateStyle: 'medium' as const }
                  : {}),
              ...options,
          }).format(date)
        : '—';
}

export function formatDateTime(
    value: DateValue,
    locale: AppLocale,
    options: Intl.DateTimeFormatOptions = {},
): string {
    const date = asDate(value);

    const hasDateParts = ['year', 'month', 'day', 'weekday', 'era'].some(
        (field) => Object.prototype.hasOwnProperty.call(options, field),
    );
    const hasTimeParts = ['hour', 'minute', 'second', 'dayPeriod'].some(
        (field) => Object.prototype.hasOwnProperty.call(options, field),
    );

    return date
        ? new Intl.DateTimeFormat(intlLocale(locale), {
              ...(!hasDateParts && !options.dateStyle
                  ? { dateStyle: 'medium' as const }
                  : {}),
              ...(!hasTimeParts && !options.timeStyle
                  ? { timeStyle: 'short' as const }
                  : {}),
              ...options,
          }).format(date)
        : '—';
}
