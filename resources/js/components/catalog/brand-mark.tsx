import { useState } from 'react';
import { cn } from '@/lib/utils';

export function BrandMark({
    name,
    logoUrl,
    className,
}: {
    name: string;
    logoUrl?: string | null;
    className?: string;
}) {
    const [failedLogoUrl, setFailedLogoUrl] = useState<string | null>(null);

    return (
        <span
            className={cn(
                'flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-white p-1.5 text-[10px] font-semibold tracking-wide text-neutral-700',
                className,
            )}
            title={name}
        >
            {logoUrl && failedLogoUrl !== logoUrl ? (
                <img
                    src={logoUrl}
                    alt={`Logo ${name}`}
                    className="size-full object-contain"
                    loading="lazy"
                    onError={() => setFailedLogoUrl(logoUrl)}
                />
            ) : (
                <span aria-label={`Logo ${name} belum tersedia`}>
                    {brandInitials(name)}
                </span>
            )}
        </span>
    );
}

function brandInitials(name: string) {
    const words = name.trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return '?';
    }

    return words
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
}
