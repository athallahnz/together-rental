import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Camera,
    ChevronDown,
    Clock3,
    Instagram,
    MapPin,
    Menu,
    MessageCircle,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import type { PublicBranch } from '@/types';

type Props = {
    branch: PublicBranch | null;
    branches: PublicBranch[];
    children: ReactNode;
};

export default function PublicShell({ branch, branches, children }: Props) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const { auth } = usePage().props;

    const switchBranch = (code: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('branch', code);
        router.get(
            `${url.pathname}${url.search}`,
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <div className="min-h-screen bg-[#f7f7f3] text-neutral-950 antialiased">
            <header className="sticky top-0 z-50 border-b border-black/5 bg-[#f7f7f3]/90 backdrop-blur-xl">
                <div className="mx-auto flex h-18 max-w-7xl items-center justify-between px-5 lg:px-8">
                    <Link
                        href={branch ? `/?branch=${branch.code}` : '/'}
                        className="flex items-center gap-3"
                    >
                        <img
                            src={branch?.logo_url ?? '/primary-logos.png'}
                            alt="Together Kamera"
                            className="size-10 rounded-xl object-cover shadow-sm ring-1 ring-black/5"
                        />
                        <div className="leading-tight">
                            <p className="font-semibold tracking-tight">
                                Together Kamera
                            </p>
                            <p className="text-[11px] text-neutral-500">
                                Rental & Creative Equipment
                            </p>
                        </div>
                    </Link>

                    <nav className="hidden items-center gap-8 text-sm font-medium lg:flex">
                        <Link
                            href={branch ? `/?branch=${branch.code}` : '/'}
                            className="transition hover:text-neutral-500"
                        >
                            Beranda
                        </Link>
                        <Link
                            href={
                                branch
                                    ? `/rental?branch=${branch.code}`
                                    : '/rental'
                            }
                            className="transition hover:text-neutral-500"
                        >
                            Katalog
                        </Link>
                        <a
                            href={`${branch ? `/?branch=${branch.code}` : '/'}#cara-rental`}
                            className="transition hover:text-neutral-500"
                        >
                            Cara Rental
                        </a>
                        <a
                            href={`${branch ? `/?branch=${branch.code}` : '/'}#lokasi`}
                            className="transition hover:text-neutral-500"
                        >
                            Lokasi
                        </a>
                    </nav>

                    <div className="hidden items-center gap-2 lg:flex">
                        {branches.length > 1 && branch && (
                            <label className="relative">
                                <span className="sr-only">Pilih cabang</span>
                                <select
                                    value={branch.code}
                                    onChange={(event) =>
                                        switchBranch(event.target.value)
                                    }
                                    className="h-10 appearance-none rounded-full border border-black/10 bg-white pr-9 pl-4 text-sm font-medium transition outline-none focus:border-black/30"
                                >
                                    {branches.map((item) => (
                                        <option key={item.id} value={item.code}>
                                            {item.code} · {item.name}
                                        </option>
                                    ))}
                                </select>
                                <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                            </label>
                        )}
                        {branch?.whatsapp_url && (
                            <a
                                href={branch.whatsapp_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex h-10 items-center gap-2 rounded-full bg-neutral-950 px-4 text-sm font-medium text-white transition hover:bg-neutral-800"
                            >
                                <MessageCircle className="size-4" />
                                Tanya Admin
                            </a>
                        )}
                        <Link
                            href={auth.user ? '/dashboard' : '/login'}
                            className="inline-flex h-10 items-center rounded-full border border-black/10 bg-white px-4 text-sm font-medium transition hover:border-black/25"
                        >
                            {auth.user ? 'Dashboard' : 'Masuk'}
                        </Link>
                    </div>

                    <button
                        type="button"
                        onClick={() => setMobileOpen((value) => !value)}
                        className="inline-flex size-10 items-center justify-center rounded-full border border-black/10 bg-white lg:hidden"
                        aria-label="Buka navigasi"
                    >
                        {mobileOpen ? (
                            <X className="size-5" />
                        ) : (
                            <Menu className="size-5" />
                        )}
                    </button>
                </div>

                {mobileOpen && (
                    <div className="border-t border-black/5 bg-[#f7f7f3] px-5 py-5 lg:hidden">
                        <div className="grid gap-2 text-sm font-medium">
                            <Link
                                href={branch ? `/?branch=${branch.code}` : '/'}
                                className="rounded-xl px-3 py-3 hover:bg-black/5"
                            >
                                Beranda
                            </Link>
                            <Link
                                href={
                                    branch
                                        ? `/rental?branch=${branch.code}`
                                        : '/rental'
                                }
                                className="rounded-xl px-3 py-3 hover:bg-black/5"
                            >
                                Katalog
                            </Link>
                            {branches.length > 1 && branch && (
                                <select
                                    value={branch.code}
                                    onChange={(event) =>
                                        switchBranch(event.target.value)
                                    }
                                    className="h-11 rounded-xl border border-black/10 bg-white px-3"
                                >
                                    {branches.map((item) => (
                                        <option key={item.id} value={item.code}>
                                            {item.code} · {item.name}
                                        </option>
                                    ))}
                                </select>
                            )}
                            {branch?.whatsapp_url && (
                                <a
                                    href={branch.whatsapp_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-2 inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-neutral-950 text-white"
                                >
                                    <MessageCircle className="size-4" /> Tanya
                                    Admin
                                </a>
                            )}
                        </div>
                    </div>
                )}
            </header>

            {children}

            <footer
                id="lokasi"
                className="border-t border-black/5 bg-neutral-950 text-white"
            >
                <div className="mx-auto grid max-w-7xl gap-10 px-5 py-14 lg:grid-cols-[1.2fr_1fr_1fr] lg:px-8">
                    <div>
                        <div className="flex items-center gap-3">
                            <img
                                src={branch?.logo_url ?? '/primary-logos.png'}
                                alt="Together Kamera"
                                className="size-12 rounded-2xl object-cover"
                            />
                            <div>
                                <p className="font-semibold">Together Kamera</p>
                                <p className="text-sm text-neutral-400">
                                    Rental equipment untuk karya yang lebih
                                    leluasa.
                                </p>
                            </div>
                        </div>
                        <p className="mt-6 max-w-md text-sm leading-6 text-neutral-400">
                            Kamera, lensa, lighting, audio, dan perlengkapan
                            produksi yang terawat dan siap digunakan.
                        </p>
                    </div>

                    <div>
                        <p className="text-sm font-semibold">Cabang aktif</p>
                        <div className="mt-4 grid gap-3 text-sm text-neutral-400">
                            <p className="flex gap-3">
                                <MapPin className="mt-0.5 size-4 shrink-0" />{' '}
                                {branch?.address ||
                                    'Informasi alamat segera tersedia.'}
                            </p>
                            <p className="flex gap-3">
                                <Clock3 className="size-4 shrink-0" />{' '}
                                {branch?.opening_hours ||
                                    'Jam operasional segera tersedia.'}
                            </p>
                        </div>
                        {branch?.maps_url && (
                            <a
                                href={branch.maps_url}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-5 inline-flex items-center gap-1 text-sm font-medium text-white hover:text-neutral-300"
                            >
                                Buka Google Maps{' '}
                                <ArrowUpRight className="size-4" />
                            </a>
                        )}
                    </div>

                    <div>
                        <p className="text-sm font-semibold">
                            Terhubung dengan kami
                        </p>
                        <div className="mt-4 grid gap-3 text-sm text-neutral-400">
                            {branch?.whatsapp_url && (
                                <a
                                    href={branch.whatsapp_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-3 hover:text-white"
                                >
                                    <MessageCircle className="size-4" />{' '}
                                    WhatsApp
                                </a>
                            )}
                            {branch?.instagram_url && (
                                <a
                                    href={branch.instagram_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-3 hover:text-white"
                                >
                                    <Instagram className="size-4" /> @
                                    {branch.instagram}
                                </a>
                            )}
                            <Link
                                href="/login"
                                className="flex items-center gap-3 hover:text-white"
                            >
                                <Camera className="size-4" /> Akses internal
                            </Link>
                        </div>
                    </div>
                </div>
                <div className="border-t border-white/10 px-5 py-5 text-center text-xs text-neutral-500">
                    © {new Date().getFullYear()} Together Kamera · Digital
                    experience by AnzArt Studio
                </div>
            </footer>
        </div>
    );
}
