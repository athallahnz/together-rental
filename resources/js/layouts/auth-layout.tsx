import { usePage } from '@inertiajs/react';
import GuestLanguageSwitcher from '@/components/guest-language-switcher';
import { useAppLocale } from '@/lib/i18n';
import type { MessageKey } from '@/lib/i18n-catalog';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const { tr } = useAppLocale();
    const { auth } = usePage().props;
    const label = (value: string) => value.startsWith('auth.')
        ? tr(value as MessageKey)
        : value;

    return (
        <div className="relative min-h-svh">
            {!auth.user && (
                <div className="absolute top-4 right-4 z-30">
                    <GuestLanguageSwitcher />
                </div>
            )}
            <AuthLayoutTemplate title={label(title)} description={label(description)}>
                {children}
            </AuthLayoutTemplate>
        </div>
    );
}
