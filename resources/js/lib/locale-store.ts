import { router } from '@inertiajs/react';
import { useEffect, useSyncExternalStore } from 'react';
import type { AppLocale } from '@/lib/i18n';

let effectiveLocale: AppLocale = 'id';
const subscribers = new Set<() => void>();

export function getEffectiveLocale(): AppLocale {
    return effectiveLocale;
}

export function setEffectiveLocale(locale: AppLocale): void {
    if (locale === effectiveLocale) {
        return;
    }

    effectiveLocale = locale;
    subscribers.forEach((subscriber) => subscriber());
}

function subscribe(subscriber: () => void): () => void {
    subscribers.add(subscriber);

    return () => {
        subscribers.delete(subscriber);
    };
}

/** For global portals (Toaster/ConfirmDialog) outside Inertia usePage context. */
export function useGlobalLocale(): AppLocale {
    const locale = useSyncExternalStore(subscribe, getEffectiveLocale, () => 'id' as const);

    useEffect(() => {
        const remove = router.on('navigate', (event) => {
            const page = event.detail.page;
            const locale = (page.props as { locale?: unknown }).locale;

            setEffectiveLocale(locale === 'en' ? 'en' : 'id');
        });

        return remove;
    }, []);

    return locale;
}
