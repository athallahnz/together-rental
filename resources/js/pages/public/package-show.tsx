import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Clock3,
    Layers3,
    PackageCheck,
} from 'lucide-react';
import AvailabilityPlanner from '@/components/public/availability-planner';
import PublicShell from '@/components/public/public-shell';
import { useAppLocale } from '@/lib/i18n';
import { formatMoney } from '@/lib/locale-format';
import { publicAvailabilityLabel, publicRateDurationLabel } from '@/lib/public-i18n';
import type { PublicBranch, PublicPackageDetail } from '@/types';

type Props = {
    branch: PublicBranch | null;
    branches: PublicBranch[];
    package: PublicPackageDetail;
};

export default function PublicPackageShow({
    branch,
    branches,
    package: rentalPackage,
}: Props) {
    const { locale, tr } = useAppLocale();

    return (
        <PublicShell branch={branch} branches={branches}>
            <Head title={rentalPackage.seo_title}>
                {rentalPackage.seo_description && (
                    <meta
                        name="description"
                        content={rentalPackage.seo_description}
                    />
                )}
                <meta property="og:title" content={rentalPackage.seo_title} />
                {rentalPackage.image_url && (
                    <meta
                        property="og:image"
                        content={rentalPackage.image_url}
                    />
                )}
                <meta property="og:type" content="product" />
                <link
                    rel="canonical"
                    href={`/rental/packages/${rentalPackage.slug}`}
                />
                <script type="application/ld+json">
                    {JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'Product',
                        name: rentalPackage.name,
                        image: rentalPackage.image_url
                            ? [rentalPackage.image_url]
                            : [],
                        description:
                            rentalPackage.seo_description ??
                            rentalPackage.short_description,
                        isRelatedTo: rentalPackage.items.map(
                            (item) => item.name,
                        ),
                        offers:
                            rentalPackage.starting_price !== null
                                ? {
                                      '@type': 'Offer',
                                      priceCurrency: 'IDR',
                                      price: rentalPackage.starting_price,
                                      availability:
                                          rentalPackage.availability.status ===
                                          'unavailable'
                                              ? 'https://schema.org/OutOfStock'
                                              : 'https://schema.org/InStock',
                                  }
                                : undefined,
                    })}
                </script>
            </Head>

            <main>
                <section className="border-b border-black/5 bg-white">
                    <div className="mx-auto max-w-7xl px-5 py-5 lg:px-8">
                        <Link
                            href={
                                branch
                                    ? `/rental?branch=${branch.code}`
                                    : '/rental'
                            }
                            className="inline-flex items-center gap-2 text-sm font-medium text-neutral-500 transition hover:text-neutral-950"
                        >
                            <ArrowLeft className="size-4" /> {tr('public.detail.back')}
                        </Link>
                    </div>
                </section>

                <section className="mx-auto grid max-w-7xl gap-10 px-5 py-10 lg:grid-cols-[0.9fr_1.1fr] lg:px-8 lg:py-16">
                    <div className="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-[2rem] bg-neutral-950 p-10 text-white">
                        {rentalPackage.image_url ? (
                            <img
                                src={rentalPackage.image_url}
                                alt={rentalPackage.name}
                                className="max-h-[80%] max-w-[80%] object-contain"
                            />
                        ) : (
                            <Layers3
                                className="size-24 text-white/20"
                                strokeWidth={1.1}
                            />
                        )}
                    </div>

                    <div className="lg:py-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="rounded-full bg-neutral-950 px-3 py-1.5 text-xs font-semibold text-white">
                                {tr('public.common.rentalPackage')}
                            </span>
                            <span
                                className={`inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold ${
                                    rentalPackage.availability.status ===
                                    'available'
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-amber-50 text-amber-700'
                                }`}
                            >
                                <CheckCircle2 className="size-3.5" />{' '}
                                {publicAvailabilityLabel(rentalPackage.availability.status, locale)}
                            </span>
                        </div>
                        <h1 className="mt-6 text-4xl leading-tight font-semibold tracking-[-0.045em] sm:text-6xl">
                            {rentalPackage.name}
                        </h1>
                        {rentalPackage.short_description && (
                            <p className="mt-5 text-base leading-7 text-neutral-500 sm:text-lg sm:leading-8">
                                {rentalPackage.short_description}
                            </p>
                        )}

                        <div className="mt-8 rounded-[1.5rem] border border-black/7 bg-white p-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-xs text-neutral-500">
                                        {tr('public.detail.product.starting')}
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold">
                                        {rentalPackage.starting_price !== null
                                            ? formatMoney(
                                                  rentalPackage.starting_price, locale,
                                              )
                                            : tr('public.common.contactAdmin')}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-xs text-neutral-500">
                                        {tr('public.detail.package.included')}
                                    </p>
                                    <p className="mt-1 font-semibold">
                                        {rentalPackage.items_count} {tr('public.common.items')}
                                    </p>
                                </div>
                            </div>
                            {rentalPackage.rates.length > 0 && (
                                <div className="mt-5 grid gap-3 sm:grid-cols-3">
                                    {rentalPackage.rates.map((rate) => (
                                        <div
                                            key={rate.id}
                                            className="rounded-2xl bg-[#f7f7f3] p-4"
                                        >
                                            <p className="flex items-center gap-1 text-xs text-neutral-500">
                                                <Clock3 className="size-3.5" />{' '}
                                                {publicRateDurationLabel(rate.duration_label, locale)}
                                            </p>
                                            <p className="mt-2 font-semibold">
                                                {formatMoney(rate.amount, locale)}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {branch && (
                            <AvailabilityPlanner
                                branch={branch}
                                itemType="package"
                                slug={rentalPackage.slug}
                                itemName={rentalPackage.name}
                                rates={rentalPackage.rates}
                                currentAvailability={{
                                    ...rentalPackage.availability,
                                    total_units:
                                        rentalPackage.availability
                                            .available_units,
                                }}
                                fallbackInquiryUrl={rentalPackage.inquiry_url}
                            />
                        )}
                    </div>
                </section>

                <section className="border-y border-black/5 bg-white py-16 lg:py-20">
                    <div className="mx-auto grid max-w-7xl gap-12 px-5 lg:grid-cols-[0.75fr_1.25fr] lg:px-8">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.16em] text-neutral-400 uppercase">
                                {tr('public.detail.package.included')}
                            </p>
                            <h2 className="mt-3 text-3xl font-semibold tracking-tight">
                                {tr('public.detail.package.everything')}
                            </h2>
                            {rentalPackage.description && (
                                <p className="mt-5 text-base leading-8 whitespace-pre-line text-neutral-500">
                                    {rentalPackage.description}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-3">
                            {rentalPackage.items.map((item) => (
                                <article
                                    key={`${item.slug}-${item.quantity}`}
                                    className="grid grid-cols-[64px_1fr_auto] items-center gap-4 rounded-2xl border border-black/7 p-3"
                                >
                                    <div className="flex size-16 items-center justify-center overflow-hidden rounded-xl bg-[#f7f7f3] p-2">
                                        {item.image_url ? (
                                            <img
                                                src={item.image_url}
                                                alt=""
                                                className="size-full object-contain"
                                            />
                                        ) : (
                                            <PackageCheck className="size-6 text-neutral-300" />
                                        )}
                                    </div>
                                    <div className="min-w-0">
                                        <Link
                                            href={`/rental/products/${item.slug}?branch=${branch?.code ?? ''}`}
                                            className="font-semibold hover:underline"
                                        >
                                            {item.name}
                                        </Link>
                                        <p className="mt-1 text-xs text-neutral-500">
                                            {item.is_optional
                                                ? tr('public.detail.package.optional')
                                                : tr('public.detail.package.mandatory')}{' '}
                                            · {publicAvailabilityLabel(item.availability.status, locale)}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-neutral-100 px-3 py-1.5 text-xs font-semibold">
                                        {item.quantity}×
                                    </span>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>
            </main>
        </PublicShell>
    );
}
