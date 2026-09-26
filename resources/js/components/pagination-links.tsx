import { Link } from '@inertiajs/react';
import { useAppLocale } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PaginationLink } from '@/types';

export function PaginationLinks({
    links,
    from,
    to,
    total,
}: {
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}) {
    const { locale } = useAppLocale();
    const localizeNavigation = (label: string): string =>
        label
            .replace(
                /\bPrevious\b/g,
                locale === 'en' ? 'Previous' : 'Sebelumnya',
            )
            .replace(/\bNext\b/g, locale === 'en' ? 'Next' : 'Berikutnya');

    if (total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-muted-foreground">
                {locale === 'en' ? 'Showing' : 'Menampilkan'} {from ?? 0}–
                {to ?? 0} {locale === 'en' ? 'of' : 'dari'} {total}{' '}
                {locale === 'en' ? 'records' : 'data'}
            </p>
            <div className="flex flex-wrap gap-1">
                {links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url}
                            preserveScroll
                            className={cn(
                                'inline-flex min-w-9 items-center justify-center rounded-md border px-3 py-1.5 text-xs font-medium transition-colors hover:bg-muted',
                                link.active &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary',
                            )}
                            dangerouslySetInnerHTML={{
                                __html: localizeNavigation(link.label),
                            }}
                        />
                    ) : (
                        <span
                            key={`${link.label}-${index}`}
                            className="inline-flex min-w-9 items-center justify-center rounded-md border px-3 py-1.5 text-xs text-muted-foreground opacity-50"
                            dangerouslySetInnerHTML={{
                                __html: localizeNavigation(link.label),
                            }}
                        />
                    ),
                )}
            </div>
        </div>
    );
}
