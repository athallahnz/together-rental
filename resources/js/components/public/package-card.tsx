import { Link } from '@inertiajs/react';
import { ArrowUpRight, Layers3 } from 'lucide-react';
import type { PublicBranch, PublicPackage } from '@/types';

const currency = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function PackageCard({
    rentalPackage,
    branch,
}: {
    rentalPackage: PublicPackage;
    branch: PublicBranch;
}) {
    return (
        <Link
            href={`/rental/packages/${rentalPackage.slug}?branch=${branch.code}`}
            className="group grid overflow-hidden rounded-[1.5rem] border border-black/7 bg-white md:grid-cols-[0.8fr_1.2fr]"
        >
            <div className="flex min-h-56 items-center justify-center bg-neutral-950 p-8">
                {rentalPackage.image_url ? (
                    <img
                        src={rentalPackage.image_url}
                        alt={rentalPackage.name}
                        className="max-h-44 max-w-full object-contain"
                    />
                ) : (
                    <Layers3
                        className="size-16 text-white/25"
                        strokeWidth={1.25}
                    />
                )}
            </div>
            <div className="flex flex-col justify-between p-6">
                <div>
                    <p className="text-xs font-semibold tracking-[0.18em] text-neutral-400 uppercase">
                        Paket Rental
                    </p>
                    <div className="mt-2 flex items-start justify-between gap-4">
                        <h3 className="text-xl font-semibold tracking-tight">
                            {rentalPackage.name}
                        </h3>
                        <ArrowUpRight className="size-5 shrink-0 text-neutral-400 transition group-hover:text-neutral-950" />
                    </div>
                    {rentalPackage.short_description && (
                        <p className="mt-3 line-clamp-3 text-sm leading-6 text-neutral-500">
                            {rentalPackage.short_description}
                        </p>
                    )}
                </div>
                <div className="mt-8 flex items-end justify-between border-t border-black/5 pt-4">
                    <div>
                        <p className="text-xs text-neutral-500">Mulai dari</p>
                        <p className="mt-1 font-semibold">
                            {rentalPackage.starting_price !== null
                                ? currency.format(rentalPackage.starting_price)
                                : 'Hubungi admin'}
                        </p>
                    </div>
                    <p className="text-xs text-neutral-500">
                        {rentalPackage.items_count} item
                    </p>
                </div>
            </div>
        </Link>
    );
}
