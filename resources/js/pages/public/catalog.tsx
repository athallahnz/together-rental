import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Camera,
    ChevronLeft,
    ChevronRight,
    PackageOpen,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import PackageCard from '@/components/public/package-card';
import ProductCard from '@/components/public/product-card';
import PublicShell from '@/components/public/public-shell';
import type {
    PublicBranch,
    PublicBrand,
    PublicCatalogFilters,
    PublicCategory,
    PublicPackage,
    PublicPaginator,
    PublicProduct,
} from '@/types';

type Props = {
    branch: PublicBranch | null;
    branches: PublicBranch[];
    products: PublicPaginator<PublicProduct>;
    categories: PublicCategory[];
    brands: PublicBrand[];
    packages: PublicPackage[];
    filters: PublicCatalogFilters;
};

const selectClass =
    'h-11 min-w-0 rounded-xl border border-black/10 bg-white px-3 text-sm outline-none transition focus:border-black/30';

export default function PublicCatalog({
    branch,
    branches,
    products,
    categories,
    brands,
    packages,
    filters,
}: Props) {
    const [form, setForm] = useState<PublicCatalogFilters>(filters);
    const [mobileFilters, setMobileFilters] = useState(false);
    const activeFilterCount = useMemo(
        () =>
            [
                form.category,
                form.brand,
                form.availability === 'available' ? 'available' : '',
            ].filter(Boolean).length,
        [form],
    );

    const submit = (event?: FormEvent<HTMLFormElement>) => {
        event?.preventDefault();
        router.get('/rental', form, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
        setMobileFilters(false);
    };

    const reset = () => {
        const next: PublicCatalogFilters = {
            search: '',
            branch: branch?.code ?? '',
            category: '',
            brand: '',
            availability: 'all',
            sort: 'recommended',
        };
        setForm(next);
        router.get('/rental', next, { replace: true });
    };

    return (
        <PublicShell branch={branch} branches={branches}>
            <Head title="Katalog Rental">
                <meta
                    name="description"
                    content="Jelajahi katalog kamera, lensa, lighting, audio, dan perlengkapan kreatif Together Kamera."
                />
            </Head>

            <main>
                <section className="border-b border-black/5 bg-white">
                    <div className="mx-auto max-w-7xl px-5 py-14 lg:px-8 lg:py-20">
                        <p className="text-xs font-semibold tracking-[0.18em] text-neutral-400 uppercase">
                            Katalog publik · {branch?.name ?? 'Together Kamera'}
                        </p>
                        <div className="mt-3 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h1 className="text-4xl font-semibold tracking-[-0.045em] sm:text-6xl">
                                    Alat yang tepat untuk setiap karya.
                                </h1>
                                <p className="mt-4 max-w-2xl text-base leading-7 text-neutral-500">
                                    Harga, pilihan durasi, dan ketersediaan unit
                                    terhubung langsung dengan data operasional
                                    cabang.
                                </p>
                            </div>
                            <div className="rounded-2xl border border-black/7 bg-[#f7f7f3] px-5 py-4 text-sm">
                                <p className="text-neutral-500">Menampilkan</p>
                                <p className="mt-1 font-semibold">
                                    {products.total} produk publik
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-5 py-8 lg:px-8 lg:py-12">
                    <form
                        onSubmit={submit}
                        className="rounded-[1.5rem] border border-black/7 bg-white p-4 shadow-sm sm:p-5"
                    >
                        <div className="flex gap-3">
                            <label className="relative min-w-0 flex-1">
                                <Search className="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-neutral-400" />
                                <input
                                    type="search"
                                    value={form.search}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            search: event.target.value,
                                        })
                                    }
                                    placeholder="Cari kamera, lensa, brand, atau model..."
                                    className="h-12 w-full rounded-xl border border-black/10 bg-[#fafaf8] pr-4 pl-11 text-sm transition outline-none placeholder:text-neutral-400 focus:border-black/30"
                                />
                            </label>
                            <button
                                type="button"
                                onClick={() =>
                                    setMobileFilters((value) => !value)
                                }
                                className="relative inline-flex size-12 shrink-0 items-center justify-center rounded-xl border border-black/10 lg:hidden"
                                aria-label="Buka filter"
                            >
                                <SlidersHorizontal className="size-4" />
                                {activeFilterCount > 0 && (
                                    <span className="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-neutral-950 text-[10px] font-semibold text-white">
                                        {activeFilterCount}
                                    </span>
                                )}
                            </button>
                            <button
                                type="submit"
                                className="hidden h-12 items-center gap-2 rounded-xl bg-neutral-950 px-6 text-sm font-semibold text-white lg:inline-flex"
                            >
                                Cari <ArrowRight className="size-4" />
                            </button>
                        </div>

                        <div
                            className={`${mobileFilters ? 'grid' : 'hidden'} mt-4 gap-3 border-t border-black/5 pt-4 lg:grid lg:grid-cols-5`}
                        >
                            <select
                                value={form.category}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        category: event.target.value,
                                    })
                                }
                                className={selectClass}
                            >
                                <option value="">Semua kategori</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.slug}
                                    >
                                        {category.name} (
                                        {category.products_count})
                                    </option>
                                ))}
                            </select>
                            <select
                                value={form.brand}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        brand: event.target.value,
                                    })
                                }
                                className={selectClass}
                            >
                                <option value="">Semua brand</option>
                                {brands.map((brand) => (
                                    <option key={brand.id} value={brand.slug}>
                                        {brand.name} ({brand.products_count})
                                    </option>
                                ))}
                            </select>
                            <select
                                value={form.availability}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        availability: event.target
                                            .value as PublicCatalogFilters['availability'],
                                    })
                                }
                                className={selectClass}
                            >
                                <option value="all">Semua ketersediaan</option>
                                <option value="available">
                                    Tersedia saat ini
                                </option>
                            </select>
                            <select
                                value={form.sort}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        sort: event.target
                                            .value as PublicCatalogFilters['sort'],
                                    })
                                }
                                className={selectClass}
                            >
                                <option value="recommended">Rekomendasi</option>
                                <option value="name">Nama A–Z</option>
                                <option value="price_low">
                                    Harga terendah
                                </option>
                                <option value="price_high">
                                    Harga tertinggi
                                </option>
                            </select>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={reset}
                                    className="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-black/10 text-sm font-medium"
                                >
                                    <X className="size-4" /> Reset
                                </button>
                                <button
                                    type="submit"
                                    className="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-neutral-950 text-sm font-semibold text-white lg:hidden"
                                >
                                    Terapkan
                                </button>
                            </div>
                        </div>
                    </form>

                    {products.data.length > 0 && branch ? (
                        <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {products.data.map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                    branch={branch}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="mt-8 flex min-h-80 flex-col items-center justify-center rounded-[1.75rem] border border-dashed border-black/10 bg-white px-6 text-center">
                            <div className="flex size-14 items-center justify-center rounded-2xl bg-neutral-100">
                                <Camera className="size-6 text-neutral-400" />
                            </div>
                            <h2 className="mt-5 text-lg font-semibold">
                                Produk belum ditemukan
                            </h2>
                            <p className="mt-2 max-w-md text-sm leading-6 text-neutral-500">
                                Ubah kata pencarian atau reset filter untuk
                                melihat pilihan lainnya.
                            </p>
                            <button
                                type="button"
                                onClick={reset}
                                className="mt-5 text-sm font-semibold underline underline-offset-4"
                            >
                                Reset filter
                            </button>
                        </div>
                    )}

                    {products.last_page > 1 && (
                        <nav
                            className="mt-10 flex items-center justify-between border-t border-black/5 pt-6"
                            aria-label="Pagination katalog"
                        >
                            <p className="text-sm text-neutral-500">
                                {products.from}–{products.to} dari{' '}
                                {products.total}
                            </p>
                            <div className="flex gap-2">
                                {products.prev_page_url ? (
                                    <Link
                                        href={products.prev_page_url}
                                        preserveScroll
                                        className="inline-flex size-10 items-center justify-center rounded-xl border border-black/10 bg-white"
                                    >
                                        <ChevronLeft className="size-4" />
                                    </Link>
                                ) : (
                                    <span className="inline-flex size-10 items-center justify-center rounded-xl border border-black/5 text-neutral-300">
                                        <ChevronLeft className="size-4" />
                                    </span>
                                )}
                                <span className="inline-flex h-10 items-center rounded-xl border border-black/10 bg-white px-4 text-sm font-medium">
                                    {products.current_page} /{' '}
                                    {products.last_page}
                                </span>
                                {products.next_page_url ? (
                                    <Link
                                        href={products.next_page_url}
                                        preserveScroll
                                        className="inline-flex size-10 items-center justify-center rounded-xl border border-black/10 bg-white"
                                    >
                                        <ChevronRight className="size-4" />
                                    </Link>
                                ) : (
                                    <span className="inline-flex size-10 items-center justify-center rounded-xl border border-black/5 text-neutral-300">
                                        <ChevronRight className="size-4" />
                                    </span>
                                )}
                            </div>
                        </nav>
                    )}
                </section>

                {packages.length > 0 && branch && (
                    <section className="border-t border-black/5 bg-white py-16 lg:py-20">
                        <div className="mx-auto max-w-7xl px-5 lg:px-8">
                            <div className="flex items-center gap-3">
                                <div className="flex size-11 items-center justify-center rounded-2xl bg-neutral-950 text-white">
                                    <PackageOpen className="size-5" />
                                </div>
                                <div>
                                    <p className="text-xs font-semibold tracking-[0.16em] text-neutral-400 uppercase">
                                        Paket praktis
                                    </p>
                                    <h2 className="mt-1 text-2xl font-semibold tracking-tight">
                                        Kombinasi alat siap produksi
                                    </h2>
                                </div>
                            </div>
                            <div className="mt-8 grid gap-5 lg:grid-cols-2">
                                {packages.slice(0, 4).map((rentalPackage) => (
                                    <PackageCard
                                        key={rentalPackage.id}
                                        rentalPackage={rentalPackage}
                                        branch={branch}
                                    />
                                ))}
                            </div>
                        </div>
                    </section>
                )}
            </main>
        </PublicShell>
    );
}
