import { Link, usePage } from '@inertiajs/react';
import {
    CircleUserRound,
    Globe2,
    LockKeyhole,
    Palette,
    Settings2,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useAppLocale } from '@/lib/i18n';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { auth } = usePage().props;
    const { tr } = useAppLocale();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const canOpenSettingsCenter =
        auth.permissions['company.view'] ||
        auth.permissions['company.manage'] ||
        auth.permissions['branches.manage'] ||
        auth.permissions['transfers.settings'] ||
        auth.permissions['notifications.manage'] ||
        auth.permissions['roles.view'];

    const sidebarNavItems: NavItem[] = [
        ...(canOpenSettingsCenter
            ? [
                  {
                      title: tr('nav.settingsCenter'),
                      href: '/settings-center',
                      icon: Settings2,
                  },
              ]
            : []),
        {
            title: tr('nav.language'),
            href: '/settings/language',
            icon: Globe2,
        },
        {
            title: tr('nav.profile'),
            href: edit(),
            icon: CircleUserRound,
        },
        {
            title: tr('nav.security'),
            href: editSecurity(),
            icon: LockKeyhole,
        },
        {
            title: tr('nav.appearance'),
            href: editAppearance(),
            icon: Palette,
        },
    ];

    return (
        <div className="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <header className="mb-7 border-b pb-6">
                <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Settings2 className="size-4" />
                    {tr('nav.settings')}
                </div>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                    {tr('nav.accountSystemSettings')}
                </h1>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                    {tr('nav.settingsDescription')}
                </p>
            </header>

            <div className="grid gap-7 lg:grid-cols-[240px_minmax(0,1fr)] xl:gap-9">
                <aside className="min-w-0 lg:sticky lg:top-6 lg:self-start">
                    <div className="rounded-xl border bg-card p-2 shadow-sm">
                        <nav
                            className="grid gap-1 sm:grid-cols-2 lg:grid-cols-1"
                            aria-label={tr('nav.settings')}
                        >
                            {sidebarNavItems.map((item, index) => (
                                <Button
                                    key={`${toUrl(item.href)}-${index}`}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn(
                                        'h-10 w-full justify-start gap-2 px-3 text-muted-foreground',
                                        {
                                            'bg-muted text-foreground shadow-xs hover:bg-muted':
                                                isCurrentOrParentUrl(item.href),
                                        },
                                    )}
                                >
                                    <Link href={item.href}>
                                        {item.icon && (
                                            <item.icon className="size-4 shrink-0" />
                                        )}
                                        <span className="truncate">
                                            {item.title}
                                        </span>
                                    </Link>
                                </Button>
                            ))}
                        </nav>
                    </div>

                    <p className="mt-3 hidden px-3 text-xs leading-5 text-muted-foreground lg:block">
                        {tr('nav.domainSettingsNote')}
                    </p>
                </aside>

                <main className="min-w-0">
                    <div className="space-y-6">{children}</div>
                </main>
            </div>
        </div>
    );
}
