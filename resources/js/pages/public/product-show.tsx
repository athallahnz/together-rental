import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Camera,
    CheckCircle2,
    Clock3,
    ShieldCheck,
} from 'lucide-react';
import AvailabilityPlanner from '@/components/public/availability-planner';
import ProductCard from '@/components/public/product-card';
import PublicShell from '@/components/public/public-shell';
import type { PublicBranch, PublicProduct, PublicProductDetail } from '@/types';

type Props = {
    branch: PublicBranch | null;
    branches: PublicBranch[];
    product: PublicProductDetail;
    relatedProducts: PublicProduct[];
};

const currency = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function PublicProductShow({
    branch,
    branches,
    product,
    relatedProducts,
}: Props) {
    return (
        <PublicShell branch={branch} branches={branches}>
            <Head title={product.seo_title}>
                {product.seo_description && (
                    <meta
                        name="description"
                        content={product.seo_description}
                    />
                )}
                <meta property="og:title" content={product.seo_title} />
                {product.seo_description && (
                    <meta
                        property="og:description"
                        content={product.seo_description}
                    />
                )}
                {product.image_url && (
                    <meta property="og:image" content={product.image_url} />
                )}
                <meta property="og:type" content="product" />
                <link
                    rel="canonical"
                    href={`/rental/products/${product.slug}`}
                />
                <script type="application/ld+json">
                    {JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'Product',
                        name: product.name,
                        image: product.image_url
                            ? [product.image_url, ...product.gallery]
                            : product.gallery,
                        description:
                            product.seo_description ??
                            product.short_description,
                        brand: product.brand
                            ? { '@type': 'Brand', name: product.brand }
                            : undefined,
                        offers:
                            product.starting_price !== null
                                ? {
                                      '@type': 'Offer',
                                      priceCurrency: 'IDR',
                                      price: product.starting_price,
                                      availability:
                                          product.availability.status ===
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
                            <ArrowLeft className="size-4" /> Kembali ke katalog
                        </Link>
                    </div>
                </section>

                <section className="mx-auto grid max-w-7xl gap-10 px-5 py-10 lg:grid-cols-[1.05fr_0.95fr] lg:px-8 lg:py-16">
                    <div>
                        <div className="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-black/5">
                            {product.image_url ? (
                                <img
                                    src={product.image_url}
                                    alt={product.name}
                                    className="max-h-[80%] max-w-[80%] object-contain"
                                />
                            ) : (
                                <Camera
                                    className="size-24 text-neutral-200"
                                    strokeWidth={1.1}
                                />
                            )}
                        </div>
                        {product.gallery.length > 0 && (
                            <div className="mt-4 grid grid-cols-4 gap-3">
                                {product.gallery.slice(0, 4).map((image) => (
                                    <div
                                        key={image}
                                        className="aspect-square overflow-hidden rounded-2xl border border-black/7 bg-white p-3"
                                    >
                                        <img
                                            src={image}
                                            alt=""
                                            className="size-full object-contain"
                                        />
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="lg:py-4">
                        <div className="flex flex-wrap items-center gap-2">
                            {product.category && (
                                <Link
                                    href={`/rental?branch=${branch?.code ?? ''}&category=${product.category.slug}`}
                                    className="rounded-full bg-neutral-200/70 px-3 py-1.5 text-xs font-semibold text-neutral-700"
                                >
                                    {product.category.name}
                                </Link>
                            )}
                            <span
                                className={`inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold ${
                                    product.availability.status === 'available'
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-amber-50 text-amber-700'
                                }`}
                            >
                                <CheckCircle2 className="size-3.5" />{' '}
                                {product.availability.label}
                            </span>
                        </div>

                        <p className="mt-6 text-sm font-semibold tracking-[0.14em] text-neutral-400 uppercase">
                            {[product.brand, product.model]
                                .filter(Boolean)
                                .join(' · ') || 'Creative equipment'}
                        </p>
                        <h1 className="mt-2 text-4xl leading-tight font-semibold tracking-[-0.045em] sm:text-6xl">
                            {product.name}
                        </h1>
                        {product.short_description && (
                            <p className="mt-5 text-base leading-7 text-neutral-500 sm:text-lg sm:leading-8">
                                {product.short_description}
                            </p>
                        )}

                        <div className="mt-8 rounded-[1.5rem] border border-black/7 bg-white p-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-xs text-neutral-500">
                                        Harga mulai
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold">
                                        {product.starting_price !== null
                                            ? currency.format(
                                                  product.starting_price,
                                              )
                                            : 'Hubungi admin'}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-xs text-neutral-500">
                                        Unit tersedia
                                    </p>
                                    <p className="mt-1 font-semibold">
                                        {product.availability.available_units}{' '}
                                        unit
                                    </p>
                                </div>
                            </div>

                            {product.rates.length > 0 && (
                                <div className="mt-5 grid gap-3 sm:grid-cols-3">
                                    {product.rates.map((rate) => (
                                        <div
                                            key={rate.id}
                                            className="rounded-2xl bg-[#f7f7f3] p-4"
                                        >
                                            <p className="flex items-center gap-1 text-xs text-neutral-500">
                                                <Clock3 className="size-3.5" />{' '}
                                                {rate.duration_label}
                                            </p>
                                            <p className="mt-2 font-semibold">
                                                {currency.format(rate.amount)}
                                            </p>
                                            {rate.deposit_amount > 0 && (
                                                <p className="mt-1 text-[11px] text-neutral-400">
                                                    Deposit{' '}
                                                    {currency.format(
                                                        rate.deposit_amount,
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {branch && (
                            <AvailabilityPlanner
                                branch={branch}
                                itemType="product"
                                slug={product.slug}
                                itemName={product.name}
                                rates={product.rates}
                                currentAvailability={product.availability}
                                fallbackInquiryUrl={product.inquiry_url}
                            />
                        )}

                        <div className="mt-5">
                            <Link
                                href={
                                    branch
                                        ? `/rental?branch=${branch.code}`
                                        : '/rental'
                                }
                                className="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-black/10 bg-white px-6 text-sm font-semibold"
                            >
                                Lihat alat lain{' '}
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>

                        <div className="mt-8 flex items-start gap-3 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900">
                            <ShieldCheck className="mt-0.5 size-5 shrink-0" />
                            <p className="leading-6">
                                Ketersediaan periode dihitung dari booking,
                                rental aktif, dan kapasitas cabang. Admin tetap
                                melakukan konfirmasi final sebelum transaksi
                                dibuat.
                            </p>
                        </div>
                    </div>
                </section>

                {(product.description ||
                    Object.keys(product.specifications).length > 0) && (
                    <section className="border-y border-black/5 bg-white py-16 lg:py-20">
                        <div className="mx-auto grid max-w-7xl gap-12 px-5 lg:grid-cols-2 lg:px-8">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.16em] text-neutral-400 uppercase">
                                    Tentang produk
                                </p>
                                <h2 className="mt-3 text-3xl font-semibold tracking-tight">
                                    Detail yang perlu diketahui.
                                </h2>
                                <p className="mt-5 text-base leading-8 whitespace-pre-line text-neutral-500">
                                    {product.description ||
                                        'Informasi detail produk akan segera dilengkapi.'}
                                </p>
                            </div>
                            {Object.keys(product.specifications).length > 0 && (
                                <div className="rounded-[1.5rem] border border-black/7 p-6">
                                    <p className="font-semibold">Spesifikasi</p>
                                    <dl className="mt-5 divide-y divide-black/5">
                                        {Object.entries(
                                            product.specifications,
                                        ).map(([label, value]) => (
                                            <div
                                                key={label}
                                                className="grid grid-cols-[0.8fr_1.2fr] gap-4 py-3 text-sm"
                                            >
                                                <dt className="text-neutral-500">
                                                    {label}
                                                </dt>
                                                <dd className="font-medium">
                                                    {value}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {relatedProducts.length > 0 && branch && (
                    <section className="mx-auto max-w-7xl px-5 py-16 lg:px-8 lg:py-24">
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.16em] text-neutral-400 uppercase">
                                    Pilihan lainnya
                                </p>
                                <h2 className="mt-2 text-3xl font-semibold tracking-tight">
                                    Produk serupa.
                                </h2>
                            </div>
                            <Link
                                href={`/rental?branch=${branch.code}`}
                                className="text-sm font-semibold hover:text-neutral-500"
                            >
                                Lihat semua
                            </Link>
                        </div>
                        <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            {relatedProducts.map((item) => (
                                <ProductCard
                                    key={item.id}
                                    product={item}
                                    branch={branch}
                                />
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </PublicShell>
    );
}
