import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Bot,
    Check,
    DatabaseZap,
    History,
    ImageIcon,
    RotateCcw,
    Search,
    ShieldCheck,
    Sparkles,
    Upload,
    WandSparkles,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { BrandMark } from '@/components/catalog/brand-mark';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { PaginationLinks } from '@/components/pagination-links';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { MetricCard } from '@/components/ui/metric-card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CatalogBrand, Pagination, Product } from '@/types';

type RunStatus = 'previewed' | 'reviewing' | 'executed' | 'rolled_back';
type CandidateStatus =
    | 'pending'
    | 'needs_review'
    | 'approved'
    | 'rejected'
    | 'executed'
    | 'rolled_back';

type EnrichmentRun = {
    id: number;
    status: RunStatus;
    scope: 'missing' | 'all';
    ai_requested: boolean;
    ai_provider: string | null;
    generated_count: number;
    approved_count: number;
    executed_count: number;
    verified_count: number;
    rollback_skipped_count: number;
    created_at: string;
    executed_at: string | null;
    rolled_back_at: string | null;
};

type Candidate = {
    id: number;
    product_id: number;
    normalized_source_name: string;
    suggested_brand: string | null;
    suggested_model: string | null;
    suggested_variant: string | null;
    confidence: string | number;
    source: 'rule' | 'manual' | 'ai';
    status: CandidateStatus;
    reasoning: string[] | null;
    product: Pick<
        Product,
        | 'id'
        | 'sku'
        | 'name'
        | 'brand'
        | 'model'
        | 'variant'
        | 'enrichment_status'
    >;
};

type Props = {
    run: EnrichmentRun | null;
    candidates: Pagination<Candidate> | null;
    summary: {
        total: number;
        high_confidence: number;
        needs_review: number;
        approved: number;
        executed: number;
    };
    filters: {
        run_id: number | null;
        status: string;
        search: string;
    };
    history: EnrichmentRun[];
    brands: CatalogBrand[];
    ai: {
        enabled: boolean;
        provider: string | null;
    };
    threshold: number;
};

export default function CatalogIntelligence({
    run,
    candidates,
    summary,
    filters,
    history,
    brands,
    ai,
    threshold,
}: Props) {
    const [scope, setScope] = useState<'missing' | 'all'>('missing');
    const [useAi, setUseAi] = useState(false);
    const [search, setSearch] = useState(filters.search);
    const busy = run?.status === 'executed' || run?.status === 'rolled_back';

    const generate = () => {
        router.post(
            '/catalog/intelligence/generate',
            { scope, use_ai: useAi },
            { preserveScroll: true },
        );
    };

    const filter = (status: string) => {
        router.get(
            '/catalog/intelligence',
            {
                run_id: run?.id,
                status: status === 'all' ? undefined : status,
                search: search || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Catalog Intelligence" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <Button asChild variant="ghost" className="mb-2 -ml-3">
                            <Link href="/catalog">
                                <ArrowLeft />
                                Kembali ke katalog
                            </Link>
                        </Button>
                        <p className="text-sm font-medium text-primary">
                            Module 6.1 · Deterministic First
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Catalog Intelligence
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Normalisasi nama legacy menjadi brand, canonical
                            model, dan varian melalui Preview → Review → Execute
                            → Verify. Produk lama tidak digabung atau dihapus.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Select
                            value={scope}
                            onValueChange={(value) =>
                                setScope(value as 'missing' | 'all')
                            }
                        >
                            <SelectTrigger className="w-52">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="missing">
                                    Hanya yang belum lengkap
                                </SelectItem>
                                <SelectItem value="all">
                                    Scan ulang semua produk
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button onClick={generate}>
                            <WandSparkles />
                            Buat preview baru
                        </Button>
                    </div>
                </header>

                <Alert>
                    <Bot />
                    <AlertTitle>
                        AI {ai.enabled ? 'tersedia' : 'nonaktif'}
                    </AlertTitle>
                    <AlertDescription>
                        Mesin deterministik selalu dijalankan lebih dahulu.
                        {ai.enabled
                            ? ` Provider ${ai.provider ?? 'terkonfigurasi'} hanya dipakai untuk kandidat confidence rendah.`
                            : ' Kontrak provider sudah disiapkan, tetapi tidak ada data yang dikirim ke layanan eksternal.'}
                    </AlertDescription>
                </Alert>

                {ai.enabled && (
                    <label className="flex items-center gap-3 rounded-xl border p-4 text-sm">
                        <input
                            type="checkbox"
                            checked={useAi}
                            onChange={(event) => setUseAi(event.target.checked)}
                            className="size-4"
                        />
                        Gunakan AI sebagai fallback pada preview berikutnya.
                    </label>
                )}

                <BrandVisualManager brands={brands} />

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        {
                            label: 'Total kandidat',
                            value: summary.total,
                            icon: DatabaseZap,
                        },
                        {
                            label: `Confidence ≥ ${threshold}%`,
                            value: summary.high_confidence,
                            icon: Sparkles,
                        },
                        {
                            label: 'Perlu review',
                            value: summary.needs_review,
                            icon: Search,
                        },
                        {
                            label: 'Disetujui',
                            value: summary.approved,
                            icon: Check,
                        },
                        {
                            label: 'Terverifikasi',
                            value: summary.executed,
                            icon: ShieldCheck,
                        },
                    ].map(({ label, value, icon: Icon }) => (
                        <MetricCard
                            key={label}
                            label={label}
                            value={value}
                            icon={Icon}
                            tone={
                                label === 'Perlu review' && Number(value) > 0
                                    ? 'warning'
                                    : 'neutral'
                            }
                        />
                    ))}
                </section>

                {run === null ? (
                    <Card>
                        <CardContent className="py-20 text-center">
                            <WandSparkles className="mx-auto size-10 text-muted-foreground" />
                            <h2 className="mt-4 font-semibold">
                                Belum ada enrichment run
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Buat preview pertama untuk memindai produk hasil
                                import RentalV1.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <RunActions run={run} busy={busy} />

                        <Card>
                            <CardHeader>
                                <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                                    <div>
                                        <CardTitle>
                                            Kandidat run #{run.id}
                                        </CardTitle>
                                        <CardDescription>
                                            Review hasil deterministik. Tidak
                                            ada perubahan produk sebelum
                                            Execute.
                                        </CardDescription>
                                    </div>
                                    <div
                                        data-slot="filter-grid"
                                        className="grid items-end gap-3 rounded-xl border bg-muted/25 p-3 sm:grid-cols-[12rem_minmax(14rem,1fr)_auto]"
                                    >
                                        <Select
                                            value={filters.status || 'all'}
                                            onValueChange={filter}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Semua status
                                                </SelectItem>
                                                <SelectItem value="needs_review">
                                                    Perlu review
                                                </SelectItem>
                                                <SelectItem value="pending">
                                                    Pending
                                                </SelectItem>
                                                <SelectItem value="approved">
                                                    Disetujui
                                                </SelectItem>
                                                <SelectItem value="executed">
                                                    Executed
                                                </SelectItem>
                                                <SelectItem value="rejected">
                                                    Ditolak
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="Cari SKU atau nama..."
                                            onKeyDown={(event) => {
                                                if (event.key === 'Enter') {
                                                    filter(
                                                        filters.status || 'all',
                                                    );
                                                }
                                            }}
                                        />
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                filter(filters.status || 'all')
                                            }
                                        >
                                            <Search />
                                            Cari
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {candidates?.data.length === 0 ? (
                                    <p className="py-16 text-center text-sm text-muted-foreground">
                                        Tidak ada kandidat pada filter ini.
                                    </p>
                                ) : (
                                    candidates?.data.map((candidate) => (
                                        <CandidateRow
                                            key={candidate.id}
                                            candidate={candidate}
                                            brands={brands}
                                            locked={busy}
                                            threshold={threshold}
                                        />
                                    ))
                                )}
                                {candidates && (
                                    <PaginationLinks
                                        links={candidates.links}
                                        from={candidates.from}
                                        to={candidates.to}
                                        total={candidates.total}
                                    />
                                )}
                            </CardContent>
                        </Card>
                    </>
                )}

                <RunHistory history={history} activeId={run?.id ?? null} />
            </div>
        </>
    );
}

function RunActions({ run, busy }: { run: EnrichmentRun; busy: boolean }) {
    const confirm = useConfirmDialog();

    return (
        <Card>
            <CardContent className="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-semibold">Run #{run.id}</p>
                        <StatusBadge status={run.status} />
                        <Badge variant="outline">
                            Scope:{' '}
                            {run.scope === 'missing'
                                ? 'belum lengkap'
                                : 'semua produk'}
                        </Badge>
                    </div>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {run.generated_count} kandidat · {run.approved_count}{' '}
                        disetujui · {run.verified_count} terverifikasi
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {!busy && (
                        <>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/catalog/intelligence/runs/${run.id}/approve-high-confidence`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Sparkles />
                                Setujui confidence tinggi
                            </Button>
                            <Button
                                disabled={run.approved_count === 0}
                                onClick={async () => {
                                    const confirmed = await confirm({
                                        title: 'Jalankan enrichment?',
                                        description: `${run.approved_count} kandidat yang disetujui akan diterapkan ke master produk dan diverifikasi.`,
                                        confirmLabel: 'Execute & verify',
                                    });

                                    if (!confirmed) {
                                        return;
                                    }

                                    router.post(
                                        `/catalog/intelligence/runs/${run.id}/execute`,
                                        {},
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                <DatabaseZap />
                                Execute & verify
                            </Button>
                        </>
                    )}
                    {run.status === 'executed' && (
                        <Button
                            variant="outline"
                            onClick={async () => {
                                const confirmed = await confirm({
                                    title: 'Rollback enrichment?',
                                    description:
                                        'Perubahan dari run ini akan dibatalkan sejauh data masih aman untuk dikembalikan.',
                                    confirmLabel: 'Rollback',
                                    variant: 'destructive',
                                });

                                if (!confirmed) {
                                    return;
                                }

                                router.post(
                                    `/catalog/intelligence/runs/${run.id}/rollback`,
                                    {},
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <RotateCcw />
                            Rollback
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function CandidateRow({
    candidate,
    brands,
    locked,
    threshold,
}: {
    candidate: Candidate;
    brands: CatalogBrand[];
    locked: boolean;
    threshold: number;
}) {
    const form = useForm({
        suggested_brand: candidate.suggested_brand ?? '',
        suggested_model: candidate.suggested_model ?? '',
        suggested_variant: candidate.suggested_variant ?? '',
        status: candidate.status as 'pending' | 'approved' | 'rejected',
    });
    const confidence = Number(candidate.confidence);
    const submit = (
        event: FormEvent,
        status: 'pending' | 'approved' | 'rejected',
    ) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, status }));
        form.patch(`/catalog/intelligence/candidates/${candidate.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <article className="grid gap-4 rounded-xl border p-4 xl:grid-cols-[minmax(14rem,1fr)_minmax(25rem,1.8fr)_auto] xl:items-center">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="truncate font-semibold">
                        {candidate.product.name}
                    </p>
                    <StatusBadge status={candidate.status} />
                </div>
                <p className="mt-1 font-mono text-xs text-muted-foreground">
                    {candidate.product.sku}
                </p>
                <p className="mt-2 text-xs text-muted-foreground">
                    Saat ini:{' '}
                    {[candidate.product.brand, candidate.product.model]
                        .filter(Boolean)
                        .join(' · ') || 'belum terisi'}
                </p>
                <div className="mt-3 flex flex-wrap gap-2">
                    <Badge
                        variant={
                            confidence >= threshold ? 'default' : 'secondary'
                        }
                    >
                        {confidence.toFixed(0)}% confidence
                    </Badge>
                    <Badge variant="outline">
                        {candidate.source === 'rule'
                            ? 'Deterministik'
                            : candidate.source === 'ai'
                              ? 'AI suggestion'
                              : 'Manual'}
                    </Badge>
                </div>
            </div>

            <form className="grid gap-3 md:grid-cols-3">
                <div>
                    <label className="mb-1.5 block text-xs font-medium">
                        Brand
                    </label>
                    <Input
                        list={`brands-${candidate.id}`}
                        value={form.data.suggested_brand}
                        disabled={locked}
                        onChange={(event) =>
                            form.setData('suggested_brand', event.target.value)
                        }
                    />
                    <datalist id={`brands-${candidate.id}`}>
                        {brands.map((brand) => (
                            <option key={brand.id} value={brand.name} />
                        ))}
                    </datalist>
                </div>
                <div>
                    <label className="mb-1.5 block text-xs font-medium">
                        Canonical model
                    </label>
                    <Input
                        value={form.data.suggested_model}
                        disabled={locked}
                        onChange={(event) =>
                            form.setData('suggested_model', event.target.value)
                        }
                    />
                </div>
                <div>
                    <label className="mb-1.5 block text-xs font-medium">
                        Varian
                    </label>
                    <Input
                        value={form.data.suggested_variant}
                        disabled={locked}
                        onChange={(event) =>
                            form.setData(
                                'suggested_variant',
                                event.target.value,
                            )
                        }
                    />
                </div>
                {candidate.reasoning && candidate.reasoning.length > 0 && (
                    <p className="text-xs leading-5 text-muted-foreground md:col-span-3">
                        {candidate.reasoning.join(' ')}
                    </p>
                )}
            </form>

            <div className="flex gap-2 xl:justify-end">
                {!locked && candidate.status !== 'rejected' && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={form.processing}
                        onClick={(event) => submit(event, 'rejected')}
                    >
                        <X />
                        Tolak
                    </Button>
                )}
                {!locked && candidate.status !== 'approved' && (
                    <Button
                        type="button"
                        size="sm"
                        disabled={form.processing}
                        onClick={(event) => submit(event, 'approved')}
                    >
                        <Check />
                        Setujui
                    </Button>
                )}
                {!locked && candidate.status === 'approved' && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={form.processing}
                        onClick={(event) => submit(event, 'approved')}
                    >
                        Simpan koreksi
                    </Button>
                )}
            </div>
        </article>
    );
}

function BrandVisualManager({ brands }: { brands: CatalogBrand[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ImageIcon className="size-5" />
                    Brand visual
                </CardTitle>
                <CardDescription>
                    Logo membantu pencarian visual pada katalog. Identitas dan
                    relasi data tetap menggunakan canonical brand ID.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {brands.length === 0 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        Belum ada canonical brand.
                    </p>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {brands.map((brand) => (
                            <BrandVisualCard key={brand.id} brand={brand} />
                        ))}
                    </div>
                )}
                <p className="mt-4 text-xs leading-5 text-muted-foreground">
                    Format aman: PNG, JPG, atau WebP maksimal 2 MB. Gunakan logo
                    berlatar transparan jika tersedia.
                </p>
            </CardContent>
        </Card>
    );
}

function BrandVisualCard({ brand }: { brand: CatalogBrand }) {
    const confirm = useConfirmDialog();
    const [fileInputKey, setFileInputKey] = useState(0);
    const form = useForm<{
        logo: File | null;
        sort_order: number;
    }>({
        logo: null,
        sort_order: brand.sort_order,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/catalog/brands/${brand.id}/visual`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.setData('logo', null);
                setFileInputKey((current) => current + 1);
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-xl border p-3 sm:grid-cols-[auto_minmax(0,1fr)]"
        >
            <BrandMark
                name={brand.name}
                logoUrl={brand.logo_url}
                className="size-14"
            />
            <div className="min-w-0">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p className="font-semibold">{brand.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {brand.products_count ?? 0} produk ·{' '}
                            {brand.models_count ?? 0} model
                        </p>
                    </div>
                    <label className="flex items-center gap-2 text-xs text-muted-foreground">
                        Urutan
                        <Input
                            type="number"
                            min="0"
                            max="9999"
                            className="h-8 w-20"
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData(
                                    'sort_order',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </label>
                </div>
                <Input
                    key={fileInputKey}
                    type="file"
                    accept=".png,.jpg,.jpeg,.webp"
                    className="mt-3"
                    onChange={(event) =>
                        form.setData('logo', event.target.files?.[0] ?? null)
                    }
                />
                {(form.errors.logo || form.errors.sort_order) && (
                    <p className="mt-1 text-xs text-destructive">
                        {form.errors.logo ?? form.errors.sort_order}
                    </p>
                )}
                <div className="mt-3 flex flex-wrap gap-2">
                    <Button type="submit" size="sm" disabled={form.processing}>
                        <Upload />
                        Simpan visual
                    </Button>
                    {brand.logo_url && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={form.processing}
                            onClick={async () => {
                                const confirmed = await confirm({
                                    title: 'Hapus logo brand?',
                                    description: `Logo ${brand.name} akan dihapus dari katalog.`,
                                    confirmLabel: 'Hapus logo',
                                    variant: 'destructive',
                                });

                                if (!confirmed) {
                                    return;
                                }

                                router.delete(
                                    `/catalog/brands/${brand.id}/logo`,
                                    {
                                        preserveScroll: true,
                                    },
                                );
                            }}
                        >
                            <X />
                            Hapus logo
                        </Button>
                    )}
                </div>
            </div>
        </form>
    );
}

function RunHistory({
    history,
    activeId,
}: {
    history: EnrichmentRun[];
    activeId: number | null;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <History className="size-5" />
                    Riwayat enrichment
                </CardTitle>
                <CardDescription>
                    Sepuluh preview terakhir tetap tersedia untuk audit.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-2">
                {history.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada riwayat.
                    </p>
                ) : (
                    history.map((item) => (
                        <Link
                            key={item.id}
                            href={`/catalog/intelligence?run_id=${item.id}`}
                            className={`flex flex-col gap-2 rounded-xl border p-3 transition hover:bg-muted/40 md:flex-row md:items-center md:justify-between ${
                                activeId === item.id ? 'bg-muted/50' : ''
                            }`}
                        >
                            <div>
                                <p className="font-medium">Run #{item.id}</p>
                                <p className="text-xs text-muted-foreground">
                                    {new Date(item.created_at).toLocaleString(
                                        'id-ID',
                                    )}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">
                                    {item.generated_count} kandidat
                                </span>
                                <StatusBadge status={item.status} />
                            </div>
                        </Link>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function StatusBadge({ status }: { status: string }) {
    const labels: Record<string, string> = {
        previewed: 'Preview',
        reviewing: 'Review',
        executed: 'Executed',
        rolled_back: 'Rolled back',
        pending: 'Pending',
        needs_review: 'Perlu review',
        approved: 'Disetujui',
        rejected: 'Ditolak',
    };

    return (
        <Badge
            variant={
                status === 'executed' || status === 'approved'
                    ? 'default'
                    : status === 'needs_review' || status === 'rejected'
                      ? 'secondary'
                      : 'outline'
            }
        >
            {labels[status] ?? status}
        </Badge>
    );
}
