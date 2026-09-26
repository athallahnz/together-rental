import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useAppLocale } from '@/lib/i18n';
import type { AppLocale } from '@/lib/i18n';

type Props = { variant?: 'default' | 'public' };

export default function GuestLanguageSwitcher({ variant = 'default' }: Props) {
    const { locale, tr } = useAppLocale();
    const [saving, setSaving] = useState(false);

    const container = variant === 'public'
        ? 'border-black/10 bg-white text-neutral-950'
        : 'border-border bg-background text-foreground';

    const change = (next: AppLocale) => {
        if (saving || next === locale) {
            return;
        }

        setSaving(true);
        router.post('/language/guest', { locale: next }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div
            role="group"
            aria-label={tr('auth.language')}
            className={`inline-flex items-center gap-1 rounded-full border p-1 text-xs font-semibold ${container}`}
        >
            {(['id', 'en'] as const).map((choice) => (
                <button
                    key={choice}
                    type="button"
                    disabled={saving}
                    aria-pressed={locale === choice}
                    onClick={() => change(choice)}
                    className={`rounded-full px-2.5 py-1.5 transition ${locale === choice ? 'bg-neutral-900 text-white' : 'hover:bg-neutral-500/10'}`}
                >
                    {choice === 'id' ? 'ID' : 'EN'}
                </button>
            ))}
        </div>
    );
}
