import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Building2, Camera, ShieldCheck } from 'lucide-react';
import { dashboard, login } from '@/routes';

const highlights = [
    {
        icon: Building2,
        title: 'Operasional terpusat',
        description:
            'Kelola cabang, staf, transaksi, dan laporan dari satu sistem.',
    },
    {
        icon: Camera,
        title: 'Aset lebih terkendali',
        description:
            'Pantau unit, ketersediaan, perpindahan, dan kondisi per cabang.',
    },
    {
        icon: ShieldCheck,
        title: 'Akses sesuai peran',
        description:
            'Hak akses pengguna dibatasi berdasarkan cabang dan tanggung jawab.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Rental Management Multi-Branch" />

            <main className="min-h-screen bg-neutral-950 text-white">
                <div className="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-10">
                    <header className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-white text-neutral-950">
                                <Camera className="size-5" />
                            </div>
                            <div>
                                <p className="font-semibold tracking-tight">
                                    Together Rental
                                </p>
                                <p className="text-xs text-neutral-400">
                                    Central Management System
                                </p>
                            </div>
                        </div>

                        <Link
                            href={auth.user ? dashboard() : login()}
                            className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-5 py-2.5 text-sm font-medium transition hover:bg-white hover:text-neutral-950"
                        >
                            {auth.user ? 'Buka dashboard' : 'Masuk'}
                            <ArrowRight className="size-4" />
                        </Link>
                    </header>

                    <section className="flex flex-1 flex-col justify-center py-20">
                        <div className="max-w-4xl">
                            <p className="mb-6 text-sm font-medium tracking-[0.2em] text-neutral-400 uppercase">
                                Together Kamera · Multi-Branch
                            </p>
                            <h1 className="text-5xl leading-[1.05] font-semibold tracking-[-0.04em] sm:text-6xl lg:text-8xl">
                                Satu kendali untuk seluruh operasional rental.
                            </h1>
                            <p className="mt-8 max-w-2xl text-lg leading-8 text-neutral-400">
                                Fondasi aplikasi web terpusat untuk booking,
                                rental, aset, pembayaran, kas, dan migrasi data
                                dari seluruh cabang.
                            </p>
                        </div>

                        <div className="mt-16 grid gap-px overflow-hidden rounded-2xl border border-white/10 bg-white/10 md:grid-cols-3">
                            {highlights.map((item) => {
                                const Icon = item.icon;

                                return (
                                    <article
                                        key={item.title}
                                        className="bg-neutral-950 p-7"
                                    >
                                        <Icon className="mb-8 size-5 text-neutral-400" />
                                        <h2 className="font-medium">
                                            {item.title}
                                        </h2>
                                        <p className="mt-2 text-sm leading-6 text-neutral-500">
                                            {item.description}
                                        </p>
                                    </article>
                                );
                            })}
                        </div>
                    </section>

                    <footer className="flex flex-col gap-2 border-t border-white/10 pt-6 text-xs text-neutral-500 sm:flex-row sm:items-center sm:justify-between">
                        <p>Internal system · Authorized access only</p>
                        <p>Digital experience by AnzArt Studio</p>
                    </footer>
                </div>
            </main>
        </>
    );
}
