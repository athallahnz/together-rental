import { Link } from '@inertiajs/react';
import { ArrowUpRight, Camera, CheckCircle2, Clock3 } from 'lucide-react';
import type { PublicBranch, PublicProduct } from '@/types';

const currency = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function ProductCard({
    product,
    branch,
}: {
    product: PublicProduct;
    branch: PublicBranch;
}) {
    return (
        <article className="group overflow-hidden rounded-[1.5rem] border border-black/7 bg-white shadow-[0_1px_0_rgba(0,0,0,0.03)] transition hover:-translate-y-1 hover:shadow-xl hover:shadow-black/5">
            <Link
                href={`/rental/products/${product.slug}?branch=${branch.code}`}
                className="block"
            >
                <div className="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-neutral-100 to-neutral-200">
                    {product.image_url ? (
                        <img
                            src={product.image_url}
                            alt={product.name}
                            className="size-full object-contain p-8 transition duration-500 group-hover:scale-105"
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center">
                            <Camera
                                className="size-14 text-neutral-300"
                                strokeWidth={1.25}
                            />
                        </div>
                    )}
                    <div className="absolute top-4 left-4 flex gap-2">
                        {product.is_featured && (
                            <span className="rounded-full bg-neutral-950 px-3 py-1.5 text-[11px] font-semibold text-white">
                                Pilihan
                            </span>
                        )}
                        <span
                            className={`inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-[11px] font-semibold ${
                                product.availability.status === 'available'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-amber-50 text-amber-700'
                            }`}
                        >
                            <CheckCircle2 className="size-3" />{' '}
                            {product.availability.label}
                        </span>
                    </div>
                </div>
                <div className="p-5">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <p className="text-xs font-medium tracking-wide text-neutral-500 uppercase">
                                {product.category?.name ??
                                    product.brand ??
                                    'Peralatan'}
                            </p>
                            <h3 className="mt-1 line-clamp-2 text-lg font-semibold tracking-tight">
                                {product.name}
                            </h3>
                        </div>
                        <ArrowUpRight className="mt-1 size-5 shrink-0 text-neutral-400 transition group-hover:text-neutral-950" />
                    </div>
                    {product.short_description && (
                        <p className="mt-3 line-clamp-2 text-sm leading-6 text-neutral-500">
                            {product.short_description}
                        </p>
                    )}
                    <div className="mt-5 flex items-end justify-between border-t border-black/5 pt-4">
                        <div>
                            <p className="text-xs text-neutral-500">
                                Mulai dari
                            </p>
                            <p className="mt-1 font-semibold">
                                {product.starting_price !== null
                                    ? currency.format(product.starting_price)
                                    : 'Hubungi admin'}
                            </p>
                        </div>
                        {product.rates[0] && (
                            <p className="flex items-center gap-1 text-xs text-neutral-500">
                                <Clock3 className="size-3.5" />{' '}
                                {product.rates[0].duration_label}
                            </p>
                        )}
                    </div>
                </div>
            </Link>
        </article>
    );
}
