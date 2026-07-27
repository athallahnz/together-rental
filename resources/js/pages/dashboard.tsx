import { Head } from '@inertiajs/react';
import { Building2, Database, GitBranch, ShieldCheck } from 'lucide-react';
import { dashboard } from '@/routes';

const foundations = [
    {
        icon: Building2,
        title: 'Multi-branch',
        description:
            'Company, cabang, role, dan akses pengguna sudah menjadi scope data utama.',
    },
    {
        icon: Database,
        title: 'Rental Management V2',
        description:
            'Database baru disiapkan melalui migration Laravel yang terurut.',
    },
    {
        icon: GitBranch,
        title: 'Legacy migration',
        description:
            'Workflow Upload sampai Verifikasi RentalV1 sudah siap untuk Ponorogo.',
    },
    {
        icon: ShieldCheck,
        title: 'Internal access',
        description:
            'Registrasi publik dinonaktifkan; akun dikelola administrator.',
    },
];

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header>
                    <p className="text-sm text-muted-foreground">
                        Together Rental
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        Rental Management V2
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                        Fondasi multi-branch dan modul Legacy Import RentalV1
                        sudah siap digunakan untuk migrasi data Ponorogo.
                    </p>
                </header>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {foundations.map((item) => {
                        const Icon = item.icon;

                        return (
                            <article
                                key={item.title}
                                className="rounded-xl border border-sidebar-border/70 bg-card p-5"
                            >
                                <div className="mb-8 flex size-10 items-center justify-center rounded-lg bg-muted">
                                    <Icon className="size-5" />
                                </div>
                                <h2 className="font-medium">{item.title}</h2>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    {item.description}
                                </p>
                            </article>
                        );
                    })}
                </section>

                <section className="rounded-xl border border-dashed border-sidebar-border/70 p-6">
                    <p className="text-sm font-medium">
                        Legacy Import Ponorogo
                    </p>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Buka menu Legacy Import, upload dump RentalV1, lalu
                        jalankan Preview → Validasi → Mapping Cabang → Execute →
                        Verifikasi. Pastikan proses queue tetap berjalan selama
                        batch diproses.
                    </p>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
