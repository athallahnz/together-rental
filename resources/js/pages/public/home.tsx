import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    CalendarCheck2,
    Camera,
    CheckCircle2,
    Clock3,
    Headphones,
    Layers3,
    MessageCircle,
    Search,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import PackageCard from '@/components/public/package-card';
import ProductCard from '@/components/public/product-card';
import PublicShell from '@/components/public/public-shell';
import type {
    PublicBranch,
    PublicBrand,
    PublicCategory,
    PublicPackage,
    PublicProduct,
} from '@/types';

type Props = {
    branch: PublicBranch | null;
    branches: PublicBranch[];
    categories: PublicCategory[];
    brands: PublicBrand[];
    featuredProducts: PublicProduct[];
    featuredPackages: PublicPackage[];
    stats: {
        products: number;
        availableUnits: number;
        branches: number;
    };
};

const equipmentTypes = [
    { icon: Camera, label: 'Camera' },
    { icon: Layers3, label: 'Lens' },
    { icon: Headphones, label: 'Audio' },
];

const benefits = [
    {
        icon: ShieldCheck,
        title: 'Unit terawat',
        description:
            'Setiap alat dipantau kondisi dan statusnya sebelum digunakan.',
    },
    {
        icon: BadgeCheck,
        title: 'Harga transparan',
        description:
            'Pilihan durasi dan tarif dapat dilihat langsung pada katalog.',
    },
    {
        icon: Clock3,
        title: 'Layanan responsif',
        description:
            'Admin siap membantu konfirmasi jadwal dan rekomendasi alat.',
    },
];

const steps = [
    {
        icon: Search,
        title: 'Pilih alat',
        description:
            'Jelajahi katalog dan temukan alat sesuai kebutuhan produksi Anda.',
    },
    {
        icon: MessageCircle,
        title: 'Konfirmasi jadwal',
        description:
            'Hubungi admin untuk memastikan periode rental dan ketersediaan unit.',
    },
    {
        icon: CalendarCheck2,
        title: 'Ambil & berkarya',
        description:
            'Selesaikan administrasi, ambil alat, lalu fokus pada karya Anda.',
    },
];

export default function PublicHome({
    branch,
    branches,
    categories,
    brands,
    featuredProducts,
    featuredPackages,
    stats,
}: Props) {
    const catalogHref = branch ? `/rental?branch=${branch.code}` : '/rental';
    const description =
        branch?.hero_description ||
        'Rental kamera dan perlengkapan produksi yang terawat, transparan, dan siap digunakan.';

    return (
        <PublicShell branch={branch} branches={branches}>
            <Head title="Rental Kamera & Peralatan Kreatif">
                <meta name="description" content={description} />
                <meta
                    property="og:title"
                    content="Together Kamera — Rental Kamera & Peralatan Kreatif"
                />
                <meta property="og:description" content={description} />
                <meta
                    property="og:image"
                    content={branch?.logo_url ?? '/primary-logos.png'}
                />
                <meta property="og:type" content="website" />
                <link
                    rel="canonical"
                    href={branch ? `/?branch=${branch.code}` : '/'}
                />
                <script type="application/ld+json">
                    {JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'LocalBusiness',
                        name: branch?.name ?? 'Together Kamera',
                        image: branch?.logo_url ?? '/primary-logos.png',
                        address: branch?.address,
                        telephone: branch?.whatsapp,
                        openingHours: branch?.opening_hours,
                        sameAs: branch?.instagram_url
                            ? [branch.instagram_url]
                            : [],
                    })}
                </script>
            </Head>

            <main>
                <section className="relative overflow-hidden bg-neutral-950 text-white">
                    <div className="absolute inset-0 [background-image:radial-gradient(circle_at_20%_20%,rgba(56,189,248,.22),transparent_30%),radial-gradient(circle_at_80%_30%,rgba(168,85,247,.2),transparent_30%),radial-gradient(circle_at_55%_90%,rgba(250,204,21,.14),transparent_28%)] opacity-40" />
                    <div className="relative mx-auto grid min-h-[690px] max-w-7xl items-center gap-14 px-5 py-20 lg:grid-cols-[1.15fr_0.85fr] lg:px-8 lg:py-28">
                        <div>
                            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-white/70 backdrop-blur">
                                <Sparkles className="size-3.5" /> Together
                                Kamera · {branch?.city ?? 'Ponorogo'}
                            </div>
                            <h1 className="mt-7 max-w-4xl text-5xl leading-[0.98] font-semibold tracking-[-0.055em] sm:text-6xl lg:text-8xl">
                                {branch?.hero_title ??
                                    'Sewa alat kreatif tanpa ribet.'}
                            </h1>
                            <p className="mt-7 max-w-2xl text-base leading-7 text-white/60 sm:text-lg sm:leading-8">
                                {description}
                            </p>
                            <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href={catalogHref}
                                    className="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-white px-6 text-sm font-semibold text-neutral-950 transition hover:bg-neutral-200"
                                >
                                    Lihat katalog{' '}
                                    <ArrowRight className="size-4" />
                                </Link>
                                {branch?.whatsapp_url && (
                                    <a
                                        href={branch.whatsapp_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-white/15 bg-white/5 px-6 text-sm font-semibold text-white transition hover:bg-white/10"
                                    >
                                        <MessageCircle className="size-4" />{' '}
                                        Konsultasi kebutuhan
                                    </a>
                                )}
                            </div>

                            <div className="mt-12 grid max-w-2xl grid-cols-3 gap-6 border-t border-white/10 pt-7">
                                <div>
                                    <p className="text-2xl font-semibold sm:text-3xl">
                                        {stats.products}+
                                    </p>
                                    <p className="mt-1 text-xs text-white/45 sm:text-sm">
                                        Pilihan produk
                                    </p>
                                </div>
                                <div>
                                    <p className="text-2xl font-semibold sm:text-3xl">
                                        {stats.availableUnits}+
                                    </p>
                                    <p className="mt-1 text-xs text-white/45 sm:text-sm">
                                        Unit siap tersedia
                                    </p>
                                </div>
                                <div>
                                    <p className="text-2xl font-semibold sm:text-3xl">
                                        09–21
                                    </p>
                                    <p className="mt-1 text-xs text-white/45 sm:text-sm">
                                        Jam layanan
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="relative mx-auto w-full max-w-lg">
                            <div className="absolute -inset-8 rounded-full bg-cyan-400/10 blur-3xl" />
                            <div className="relative overflow-hidden rounded-[2.5rem] border border-white/10 bg-white/[0.06] p-6 shadow-2xl backdrop-blur-xl sm:p-9">
                                <div className="flex items-center justify-between">
                                    <p className="text-sm font-medium text-white/70">
                                        Creative equipment
                                    </p>
                                    <span className="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-medium text-emerald-300">
                                        Ready to rent
                                    </span>
                                </div>
                                <div className="mt-12 flex min-h-72 items-center justify-center">
                                    <img
                                        src={
                                            branch?.logo_url ??
                                            '/primary-logos.png'
                                        }
                                        alt="Together Kamera"
                                        className="w-64 rounded-[2.2rem] object-contain drop-shadow-2xl sm:w-80"
                                    />
                                </div>
                                <div className="mt-10 grid grid-cols-3 gap-3">
                                    {equipmentTypes.map((item) => {
                                        const Icon = item.icon;

                                        return (
                                            <div
                                                key={item.label}
                                                className="rounded-2xl border border-white/10 bg-white/5 p-4 text-center"
                                            >
                                                <Icon className="mx-auto size-5 text-white/60" />
                                                <p className="mt-2 text-xs text-white/50">
                                                    {item.label}
                                                </p>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="border-b border-black/5 bg-white">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-10 gap-y-5 px-5 py-7 lg:px-8">
                        <p className="text-xs font-semibold tracking-[0.18em] text-neutral-400 uppercase">
                            Brand pilihan
                        </p>
                        {brands.length > 0 ? (
                            brands.slice(0, 8).map((brand) => (
                                <Link
                                    key={brand.id}
                                    href={`${catalogHref}&brand=${brand.slug}`}
                                    className="flex h-9 items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-950"
                                >
                                    {brand.logo_url && (
                                        <img
                                            src={brand.logo_url}
                                            alt=""
                                            className="max-h-7 max-w-20 object-contain"
                                        />
                                    )}
                                    {!brand.logo_url && brand.name}
                                </Link>
                            ))
                        ) : (
                            <p className="text-sm text-neutral-400">
                                Canon · Sony · Fujifilm · Nikon · DJI
                            </p>
                        )}
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-5 py-20 lg:px-8 lg:py-28">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.18em] text-neutral-400 uppercase">
                                Jelajahi kebutuhanmu
                            </p>
                            <h2 className="mt-3 text-3xl font-semibold tracking-[-0.035em] sm:text-5xl">
                                Kategori populer.
                            </h2>
                        </div>
                        <Link
                            href={catalogHref}
                            className="inline-flex items-center gap-2 text-sm font-semibold hover:text-neutral-500"
                        >
                            Lihat semua <ArrowRight className="size-4" />
                        </Link>
                    </div>

                    <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {categories.length > 0 ? (
                            categories.map((category, index) => (
                                <Link
                                    key={category.id}
                                    href={`${catalogHref}&category=${category.slug}`}
                                    className="group relative min-h-52 overflow-hidden rounded-[1.75rem] bg-neutral-950 p-6 text-white"
                                >
                                    {category.image_url && (
                                        <img
                                            src={category.image_url}
                                            alt=""
                                            className="absolute inset-0 size-full object-cover opacity-40 transition duration-500 group-hover:scale-105"
                                        />
                                    )}
                                    <div
                                        className={`absolute inset-0 ${['bg-cyan-500/20', 'bg-violet-500/20', 'bg-amber-500/20', 'bg-rose-500/20'][index % 4]}`}
                                    />
                                    <div className="relative flex h-full flex-col justify-between">
                                        <div className="flex size-11 items-center justify-center rounded-2xl bg-white/10 backdrop-blur">
                                            <Camera className="size-5" />
                                        </div>
                                        <div>
                                            <h3 className="text-xl font-semibold">
                                                {category.name}
                                            </h3>
                                            <p className="mt-1 text-sm text-white/50">
                                                {category.products_count} produk
                                            </p>
                                        </div>
                                    </div>
                                </Link>
                            ))
                        ) : (
                            <div className="col-span-full rounded-3xl border border-dashed border-black/10 bg-white p-10 text-center text-sm text-neutral-500">
                                Kategori publik akan tampil setelah data katalog
                                tersedia.
                            </div>
                        )}
                    </div>
                </section>

                <section className="bg-white py-20 lg:py-28">
                    <div className="mx-auto max-w-7xl px-5 lg:px-8">
                        <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.18em] text-neutral-400 uppercase">
                                    Pilihan Together
                                </p>
                                <h2 className="mt-3 text-3xl font-semibold tracking-[-0.035em] sm:text-5xl">
                                    Alat yang sering dicari.
                                </h2>
                            </div>
                            <Link
                                href={catalogHref}
                                className="inline-flex items-center gap-2 text-sm font-semibold hover:text-neutral-500"
                            >
                                Buka katalog <ArrowRight className="size-4" />
                            </Link>
                        </div>

                        <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            {branch &&
                                featuredProducts.map((product) => (
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        branch={branch}
                                    />
                                ))}
                        </div>
                    </div>
                </section>

                {featuredPackages.length > 0 && branch && (
                    <section className="mx-auto max-w-7xl px-5 py-20 lg:px-8 lg:py-28">
                        <div className="max-w-2xl">
                            <p className="text-xs font-semibold tracking-[0.18em] text-neutral-400 uppercase">
                                Lebih praktis
                            </p>
                            <h2 className="mt-3 text-3xl font-semibold tracking-[-0.035em] sm:text-5xl">
                                Paket siap produksi.
                            </h2>
                            <p className="mt-4 text-base leading-7 text-neutral-500">
                                Kombinasi alat yang sudah disusun agar Anda
                                tidak perlu memilih satu per satu.
                            </p>
                        </div>
                        <div className="mt-10 grid gap-5 lg:grid-cols-2">
                            {featuredPackages.map((rentalPackage) => (
                                <PackageCard
                                    key={rentalPackage.id}
                                    rentalPackage={rentalPackage}
                                    branch={branch}
                                />
                            ))}
                        </div>
                    </section>
                )}

                <section
                    id="cara-rental"
                    className="bg-neutral-950 py-20 text-white lg:py-28"
                >
                    <div className="mx-auto max-w-7xl px-5 lg:px-8">
                        <div className="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.18em] text-white/40 uppercase">
                                    Alur sederhana
                                </p>
                                <h2 className="mt-3 text-3xl font-semibold tracking-[-0.035em] sm:text-5xl">
                                    Dari rencana menuju karya.
                                </h2>
                                <p className="mt-5 max-w-md leading-7 text-white/50">
                                    Kami membantu memastikan alat, jadwal, dan
                                    kebutuhan teknis Anda lebih tertata.
                                </p>
                            </div>
                            <div className="grid gap-4 md:grid-cols-3">
                                {steps.map((step, index) => {
                                    const Icon = step.icon;

                                    return (
                                        <article
                                            key={step.title}
                                            className="rounded-[1.5rem] border border-white/10 bg-white/[0.04] p-6"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex size-11 items-center justify-center rounded-2xl bg-white/10">
                                                    <Icon className="size-5" />
                                                </div>
                                                <span className="text-xs text-white/30">
                                                    0{index + 1}
                                                </span>
                                            </div>
                                            <h3 className="mt-8 font-semibold">
                                                {step.title}
                                            </h3>
                                            <p className="mt-2 text-sm leading-6 text-white/45">
                                                {step.description}
                                            </p>
                                        </article>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="bg-white py-20 lg:py-24">
                    <div className="mx-auto grid max-w-7xl gap-5 px-5 lg:grid-cols-3 lg:px-8">
                        {benefits.map((benefit) => {
                            const Icon = benefit.icon;

                            return (
                                <article
                                    key={benefit.title}
                                    className="rounded-[1.5rem] border border-black/7 p-6"
                                >
                                    <Icon className="size-5" />
                                    <h3 className="mt-7 font-semibold">
                                        {benefit.title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-neutral-500">
                                        {benefit.description}
                                    </p>
                                </article>
                            );
                        })}
                    </div>
                </section>

                <section className="px-5 py-16 lg:px-8 lg:py-24">
                    <div className="mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-gradient-to-br from-cyan-400 via-sky-500 to-blue-700 px-6 py-12 text-white sm:px-10 lg:flex lg:items-center lg:justify-between lg:px-14 lg:py-14">
                        <div className="max-w-2xl">
                            <div className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-medium backdrop-blur">
                                <CheckCircle2 className="size-3.5" /> Siap
                                mulai?
                            </div>
                            <h2 className="mt-5 text-3xl font-semibold tracking-[-0.035em] sm:text-5xl">
                                Ceritakan kebutuhan produksimu.
                            </h2>
                            <p className="mt-4 leading-7 text-white/75">
                                Admin Together Kamera akan membantu memilihkan
                                alat dan durasi rental yang paling sesuai.
                            </p>
                        </div>
                        {branch?.whatsapp_url && (
                            <a
                                href={branch.whatsapp_url}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-8 inline-flex h-12 items-center gap-2 rounded-full bg-white px-6 text-sm font-semibold text-neutral-950 transition hover:bg-neutral-100 lg:mt-0"
                            >
                                Hubungi via WhatsApp{' '}
                                <ArrowRight className="size-4" />
                            </a>
                        )}
                    </div>
                </section>
            </main>
        </PublicShell>
    );
}
