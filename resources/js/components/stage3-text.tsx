import { usePage } from '@inertiajs/react';
import { translateKey } from '@/lib/i18n';
import type { AppLocale } from '@/lib/i18n';
import type { MessageKey } from '@/lib/i18n-catalog';

/** Stage 3 translations affect presentation only, not route/API/DB values. */
export type Stage3Key = Extract<MessageKey, `stage3.ui.${string}`>;

export function stage3Translate(
    key: Stage3Key,
    locale: AppLocale,
    values: Record<string, string | number> = {},
): string {
    return translateKey(key, locale, values);
}

export function Stage3Text({ k }: { k: Stage3Key }) {
    const { props } = usePage();
    const locale: AppLocale =
        (props as { locale?: string }).locale === 'en' ? 'en' : 'id';

    return translateKey(k, locale);
}
