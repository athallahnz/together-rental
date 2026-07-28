import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    Boxes,
    CircleDollarSign,
    Download,
    Gauge,
    Info,
    ShieldCheck,
    RotateCcw,
    Search,
    Target,
    TrendingUp,
    WalletCards,
    Wrench,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { PaginationLinks } from '@/components/pagination-links';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AssetAnalyticsPageProps,
    AssetAnalyticsRow,
    AssetAnalyticsTrendPoint,
    AssetRecommendation,
} from '@/types';

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const number = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 1,
});

const date = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

function percent(value: number | null) {
    return value === null ? '—' : `${number.format(value)}%`;
}

function statusLabel(value: string) {
    const labels: Record<string, string> = {
        available: 'Tersedia',
        reserved: 'Direservasi',
        rented: 'Disewa',
        maintenance: 'Maintenance',
        retired: 'Pensiun',
        lost: 'Hilang',
    };

    return labels[value] ?? value;
}

function conditionLabel(value: string) {
    const labels: Record<string, string> = {
        good: 'Baik',
        fair: 'Cukup',
        poor: 'Buruk',
        damaged: 'Rusak',
        critical: 'Kritis',
    };

    return labels[value] ?? value;
}

function recommendationClass(tone: AssetRecommendation['tone']) {
    return {
        success:
            'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        warning:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        danger: 'border-destructive/30 bg-destructive/10 text-destructive',
        neutral: 'border-border bg-muted text-muted-foreground',
    }[tone];
}

function dataQualityClass(tone: AssetAnalyticsRow['data_quality']['tone']) {
    return {
        success: 'border-emerald-500/30 bg-emerald-500/5',
        warning: 'border-amber-500/30 bg-amber-500/5',
        danger: 'border-destructive/30 bg-destructive/5',
    }[tone];
}

function confidenceClass(confidence: AssetRecommendation['confidence']) {
    return {
        high: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        medium: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        low: 'border-destructive/30 bg-destructive/10 text-destructive',
    }[confidence];
}

function metricDirection(value: number | null) {
    if (value === null) {
        return null;
    }

    return value >= 0 ? ArrowUpRight : ArrowDownRight;
}

function TrendChart({ data }: { data: AssetAnalyticsTrendPoint[] }) {
    const width = 960;
    const height = 280;
    const padding = 36;
    const maxValue = Math.max(
        ...data.flatMap((item) => [item.revenue, item.maintenance]),
        1,
    );
    const x = (index: number) =>
        data.length <= 1
            ? width / 2
            : padding + (index / (data.length - 1)) * (width - padding * 2);
    const y = (value: number) =>
        height - padding - (value / maxValue) * (height - padding * 2);
    const revenuePath = data
        .map(
            (item, index) =>
                `${index === 0 ? 'M' : 'L'} ${x(index)} ${y(item.revenue)}`,
        )
        .join(' ');
    const maintenancePath = data
        .map(
            (item, index) =>
                `${index === 0 ? 'M' : 'L'} ${x(index)} ${y(item.maintenance)}`,
        )
        .join(' ');

    return (
        <div className="overflow-x-auto">
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="min-w-[720px]"
                role="img"
                aria-label="Grafik pendapatan terealisasi dan maintenance aset"
            >
                {[0, 0.25, 0.5, 0.75, 1].map((ratio) => {
                    const lineY = y(maxValue * ratio);

                    return (
                        <g key={ratio}>
                            <line
                                x1={padding}
                                x2={width - padding}
                                y1={lineY}
                                y2={lineY}
                                className="stroke-border"
                                strokeDasharray="4 6"
                            />
                            <text
                                x={padding}
                                y={lineY - 7}
                                className="fill-muted-foreground text-[11px]"
                            >
                                {money.format(maxValue * ratio)}
                            </text>
                        </g>
                    );
                })}
                <path
                    d={revenuePath}
                    fill="none"
                    className="stroke-primary"
                    strokeWidth="4"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                <path
                    d={maintenancePath}
                    fill="none"
                    className="stroke-destructive"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeDasharray="8 7"
                />
                {data.map((item, index) => (
                    <g key={item.key}>
                        <circle
                            cx={x(index)}
                            cy={y(item.revenue)}
                            r="5"
                            className="fill-primary"
                        />
                        <text
                            x={x(index)}
                            y={height - 10}
                            textAnchor="middle"
                            className="fill-muted-foreground text-[11px]"
                        >
                            {item.label}
                        </text>
                    </g>
                ))}
            </svg>
        </div>
    );
}

function InsightCard({
    title,
    asset,
    value,
    description,
}: {
    title: string;
    asset: AssetAnalyticsRow | null;
    value: string;
    description: string;
}) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <CardDescription>{title}</CardDescription>
                <CardTitle className="text-lg">
                    {asset?.product.name ?? 'Belum ada data'}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-xl font-semibold">{value}</p>
                <p className="mt-2 text-xs leading-5 text-muted-foreground">
                    {asset
                        ? `${asset.asset_code} · ${description}`
                        : description}
                </p>
            </CardContent>
        </Card>
    );
}

export default function AssetAnalytics({
    summary,
    assets,
    trend,
    branchPerformance,
    insights,
    filters,
    branches,
    categories,
    permissions,
    methodology,
}: AssetAnalyticsPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [branchId, setBranchId] = useState(
        filters.branch_id?.toString() ?? 'all',
    );
    const [categoryId, setCategoryId] = useState(
        filters.category_id?.toString() ?? 'all',
    );
    const [status, setStatus] = useState(filters.status || 'all');
    const [condition, setCondition] = useState(filters.condition || 'all');

    const query = useMemo(
        () => ({
            from,
            to,
            branch_id: branchId === 'all' ? undefined : Number(branchId),
            category_id: categoryId === 'all' ? undefined : Number(categoryId),
            status: status === 'all' ? undefined : status,
            condition: condition === 'all' ? undefined : condition,
            search: search || undefined,
        }),
        [branchId, categoryId, condition, from, search, status, to],
    );

    const applyFilters = () => {
        router.get('/reports/asset-analytics', query, {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        router.get('/reports/asset-analytics');
    };

    const exportUrl = `/reports/asset-analytics/export?${new URLSearchParams(
        Object.entries(query).reduce<Record<string, string>>(
            (params, [key, value]) => {
                if (value !== undefined) {
                    params[key] = String(value);
                }

                return params;
            },
            {},
        ),
    ).toString()}`;

    const kpis = [
        {
            label: 'Investasi tercatat',
            value: money.format(summary.total_investment),
            note: `${summary.priced_asset_count} dari ${summary.asset_count} aset · ${percent(summary.investment_coverage_percent)} cakupan`,
            icon: WalletCards,
        },
        {
            label: 'Pendapatan terealisasi',
            value: money.format(summary.lifetime_revenue),
            note: `${money.format(summary.period_revenue)} periode · ${money.format(summary.open_lifetime_revenue)} berjalan`,
            icon: CircleDollarSign,
        },
        {
            label: 'Kontribusi bersih',
            value: money.format(summary.net_contribution),
            note: `Maintenance ${money.format(summary.maintenance_cost)}`,
            icon: TrendingUp,
        },
        {
            label: 'Progress BEP',
            value: percent(summary.bep_progress_percent),
            note: `${summary.investment_is_complete ? '' : 'Estimasi sementara · '}${summary.bep_asset_count} unit sudah BEP`,
            icon: Target,
        },
        {
            label: 'ROI keseluruhan',
            value: percent(summary.roi_percent),
            note: `${summary.investment_is_complete ? '' : 'Estimasi sementara · '}Profit bersih ${money.format(summary.net_profit)}`,
            icon: BarChart3,
        },
        {
            label: 'Utilisasi periode',
            value: percent(summary.utilization_percent),
            note: `${summary.active_asset_count} unit aktif`,
            icon: Gauge,
        },
    ];

    return (
        <>
            <Head title="Analitik Aset, ROI & BEP" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Modul 7 · Business Intelligence
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Analitik Aset, ROI & BEP
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Ukur produktivitas unit, progres balik modal,
                            utilisasi, biaya maintenance, dan rekomendasi
                            tindakan per cabang.
                        </p>
                    </div>
                    {permissions.export && (
                        <Button asChild variant="outline">
                            <a href={exportUrl}>
                                <Download />
                                Export CSV
                            </a>
                        </Button>
                    )}
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Filter analitik
                        </CardTitle>
                        <CardDescription>
                            Periode memengaruhi pendapatan periode, tren,
                            utilisasi, dan estimasi BEP. ROI serta BEP memakai
                            data lifetime.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-[minmax(220px,1.35fr)_minmax(155px,0.85fr)_minmax(155px,0.85fr)_minmax(240px,1.25fr)_minmax(180px,1fr)_minmax(155px,0.8fr)_minmax(155px,0.8fr)]">
                            <Input
                                className="min-w-0"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari aset atau produk"
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        applyFilters();
                                    }
                                }}
                            />
                            <Input
                                className="min-w-0"
                                type="date"
                                value={from}
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                            />
                            <Input
                                className="min-w-0"
                                type="date"
                                value={to}
                                onChange={(event) => setTo(event.target.value)}
                            />
                            <Select
                                value={branchId}
                                onValueChange={setBranchId}
                            >
                                <SelectTrigger className="w-full min-w-0">
                                    <SelectValue placeholder="Semua cabang" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua cabang
                                    </SelectItem>
                                    {branches.map((branch) => (
                                        <SelectItem
                                            key={branch.id}
                                            value={branch.id.toString()}
                                        >
                                            {branch.code} · {branch.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={categoryId}
                                onValueChange={setCategoryId}
                            >
                                <SelectTrigger className="w-full min-w-0">
                                    <SelectValue placeholder="Semua kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua kategori
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={category.id.toString()}
                                        >
                                            {category.code} · {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger className="w-full min-w-0">
                                    <SelectValue placeholder="Semua status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua status
                                    </SelectItem>
                                    <SelectItem value="available">
                                        Tersedia
                                    </SelectItem>
                                    <SelectItem value="reserved">
                                        Direservasi
                                    </SelectItem>
                                    <SelectItem value="rented">
                                        Disewa
                                    </SelectItem>
                                    <SelectItem value="maintenance">
                                        Maintenance
                                    </SelectItem>
                                    <SelectItem value="retired">
                                        Pensiun
                                    </SelectItem>
                                    <SelectItem value="lost">Hilang</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={condition}
                                onValueChange={setCondition}
                            >
                                <SelectTrigger className="w-full min-w-0">
                                    <SelectValue placeholder="Semua kondisi" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua kondisi
                                    </SelectItem>
                                    <SelectItem value="good">Baik</SelectItem>
                                    <SelectItem value="fair">Cukup</SelectItem>
                                    <SelectItem value="poor">Buruk</SelectItem>
                                    <SelectItem value="damaged">
                                        Rusak
                                    </SelectItem>
                                    <SelectItem value="critical">
                                        Kritis
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-end">
                            <Button
                                className="w-full sm:w-auto"
                                variant="outline"
                                onClick={resetFilters}
                            >
                                <RotateCcw />
                                Reset
                            </Button>
                            <Button
                                className="w-full sm:w-auto"
                                onClick={applyFilters}
                            >
                                <Search />
                                Terapkan
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    {kpis.map((item) => {
                        const Icon = item.icon;
                        const Direction =
                            item.label === 'ROI keseluruhan'
                                ? metricDirection(summary.roi_percent)
                                : null;

                        return (
                            <Card key={item.label}>
                                <CardContent className="p-5">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex size-10 items-center justify-center rounded-lg bg-muted">
                                            <Icon className="size-5" />
                                        </div>
                                        {Direction && (
                                            <Direction
                                                className={`size-4 ${
                                                    (summary.roi_percent ??
                                                        0) >= 0
                                                        ? 'text-emerald-600'
                                                        : 'text-destructive'
                                                }`}
                                            />
                                        )}
                                    </div>
                                    <p className="mt-5 text-xs text-muted-foreground">
                                        {item.label}
                                    </p>
                                    <p className="mt-1 text-xl font-semibold tracking-tight">
                                        {item.value}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {item.note}
                                    </p>
                                </CardContent>
                            </Card>
                        );
                    })}
                </section>

                <section className="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>Pendapatan vs maintenance</CardTitle>
                                <CardDescription>
                                    Tren bulanan berdasarkan tanggal checkout,
                                    approval perpanjangan, dan penyelesaian
                                    maintenance.
                                </CardDescription>
                            </div>
                            <div className="flex flex-col gap-2 text-xs">
                                <span className="flex items-center gap-2">
                                    <span className="size-2 rounded-full bg-primary" />
                                    Pendapatan terealisasi
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="size-2 rounded-full bg-destructive" />
                                    Maintenance
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <TrendChart data={trend} />
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-4">
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle>Keputusan Portofolio</CardTitle>
                                <CardDescription>
                                    Bedakan aset sehat, tindakan nyata,
                                    pemantauan, dan keputusan yang ditunda.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                                {[
                                    {
                                        label: 'Perlu tindakan operasional',
                                        value: insights.business_action_count,
                                        note: `${insights.high_confidence_action_count} keyakinan tinggi`,
                                        icon: Activity,
                                    },
                                    {
                                        label: 'Aset sehat / pertahankan',
                                        value: insights.healthy_asset_count,
                                        note: 'telah melewati BEP',
                                        icon: ShieldCheck,
                                    },
                                    {
                                        label: 'Pantau menuju BEP',
                                        value: insights.monitor_asset_count,
                                        note: 'belum perlu intervensi',
                                        icon: Target,
                                    },
                                    {
                                        label: 'Keputusan ditunda',
                                        value: insights.deferred_decision_count,
                                        note: 'menunggu validasi investasi',
                                        icon: Info,
                                    },
                                ].map((item) => {
                                    const Icon = item.icon;

                                    return (
                                        <div
                                            key={item.label}
                                            className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                <Icon className="size-4 shrink-0 text-muted-foreground" />
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm">
                                                        {item.label}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {item.note}
                                                    </p>
                                                </div>
                                            </div>
                                            <span className="text-lg font-semibold">
                                                {item.value}
                                            </span>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle>Rincian tindakan</CardTitle>
                                <CardDescription>
                                    Hanya intervensi yang benar-benar perlu
                                    dikerjakan.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                                {[
                                    {
                                        label: 'Tambah kapasitas',
                                        value: insights.add_capacity_count,
                                        note: 'permintaan kuat',
                                        icon: TrendingUp,
                                    },
                                    {
                                        label: 'Promosikan aset',
                                        value: insights.promote_count,
                                        note: 'utilisasi periode rendah',
                                        icon: Activity,
                                    },
                                    {
                                        label: 'Evaluasi penjualan',
                                        value: insights.review_disposal_count,
                                        note: 'usia dan BEP kurang sehat',
                                        icon: Boxes,
                                    },
                                    {
                                        label: 'Evaluasi servis',
                                        value: insights.service_review_count,
                                        note: 'kondisi atau biaya',
                                        icon: Wrench,
                                    },
                                ].map((item) => {
                                    const Icon = item.icon;

                                    return (
                                        <div
                                            key={item.label}
                                            className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                <Icon className="size-4 shrink-0 text-muted-foreground" />
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm">
                                                        {item.label}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {item.note}
                                                    </p>
                                                </div>
                                            </div>
                                            <span className="text-lg font-semibold">
                                                {item.value}
                                            </span>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle>Kualitas data</CardTitle>
                        <CardDescription>
                            Catatan audit ditampilkan terpisah dan tidak
                            menggantikan rekomendasi bisnis.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                        {[
                            {
                                label: 'Aset dengan blocker',
                                value: insights.data_quality_blocker_asset_count,
                                note: `${insights.data_quality_issue_asset_count} aset punya catatan`,
                                icon: Info,
                            },
                            {
                                label: 'Kualitas tinggi',
                                value: insights.high_quality_asset_count,
                                note: 'siap untuk keputusan',
                                icon: ShieldCheck,
                            },
                            {
                                label: 'Interval memakai fallback',
                                value: insights.invalid_interval_count,
                                note: 'assignment memakai due date',
                                icon: Activity,
                            },
                            {
                                label: 'Rental legacy kedaluwarsa',
                                value: insights.stale_active_rental_count,
                                note: 'masih berstatus aktif',
                                icon: Activity,
                            },
                            {
                                label: 'Harga beli bermasalah',
                                value:
                                    insights.missing_purchase_price_count +
                                    insights.suspicious_purchase_price_count,
                                note: `${insights.missing_purchase_price_count} kosong · ${insights.suspicious_purchase_price_count} perlu validasi`,
                                icon: WalletCards,
                            },
                            {
                                label: 'Riwayat maintenance',
                                value:
                                    insights.maintenance_record_count > 0
                                        ? insights.maintenance_record_count
                                        : 'Belum ada',
                                note: 'data biaya teknis',
                                icon: Boxes,
                            },
                        ].map((item) => {
                            const Icon = item.icon;

                            return (
                                <div
                                    key={item.label}
                                    className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <Icon className="size-4 shrink-0 text-muted-foreground" />
                                        <div className="min-w-0">
                                            <p className="truncate text-sm">
                                                {item.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.note}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="text-lg font-semibold">
                                        {item.value}
                                    </span>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                <section className="grid gap-4 lg:grid-cols-3">
                    <InsightCard
                        title="Pendapatan periode tertinggi"
                        asset={insights.top_revenue}
                        value={
                            insights.top_revenue
                                ? money.format(
                                      insights.top_revenue.period_revenue,
                                  )
                                : '—'
                        }
                        description="pendapatan pada periode terpilih"
                    />
                    <InsightCard
                        title="Utilisasi tertinggi"
                        asset={insights.top_utilization}
                        value={
                            insights.top_utilization
                                ? percent(
                                      insights.top_utilization
                                          .utilization_percent,
                                  )
                                : '—'
                        }
                        description="pemakaian valid terhadap jam operasional aset aktif"
                    />
                    <InsightCard
                        title="Biaya maintenance tertinggi"
                        asset={insights.highest_maintenance}
                        value={
                            insights.highest_maintenance
                                ? money.format(
                                      insights.highest_maintenance
                                          .maintenance_cost,
                                  )
                                : '—'
                        }
                        description={
                            insights.highest_maintenance
                                ? 'biaya maintenance lifetime'
                                : 'belum ada maintenance selesai yang tercatat'
                        }
                    />
                </section>

                {branchPerformance.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Performa per cabang</CardTitle>
                            <CardDescription>
                                Perbandingan investasi, kontribusi bersih,
                                progres BEP, dan utilisasi unit berdasarkan
                                cabang saat ini.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full min-w-[820px] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="pb-3 font-medium">
                                            Cabang
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Aset
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Investasi
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Kontribusi
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            BEP
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Utilisasi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {branchPerformance.map((item) => (
                                        <tr
                                            key={item.branch.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-4">
                                                <p className="font-medium">
                                                    {item.branch.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {item.branch.code}
                                                </p>
                                            </td>
                                            <td className="py-4 text-right">
                                                {item.active_asset_count}/
                                                {item.asset_count}
                                            </td>
                                            <td className="py-4 text-right">
                                                {money.format(item.investment)}
                                            </td>
                                            <td className="py-4 text-right">
                                                <p className="font-medium">
                                                    {money.format(
                                                        item.net_contribution,
                                                    )}
                                                </p>
                                                {item.open_revenue > 0 && (
                                                    <p className="text-xs text-muted-foreground">
                                                        Berjalan{' '}
                                                        {money.format(
                                                            item.open_revenue,
                                                        )}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="py-4 text-right">
                                                {percent(
                                                    item.bep_progress_percent,
                                                )}
                                            </td>
                                            <td className="py-4 text-right">
                                                {percent(
                                                    item.utilization_percent,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>ROI & BEP per aset</CardTitle>
                        <CardDescription>
                            Pisahkan kelayakan data dari tindakan bisnis agar
                            prioritas tidak tertutup oleh catatan legacy minor.
                            Kolom aset tetap terlihat saat tabel digeser
                            horizontal.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1880px] border-separate border-spacing-0 text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="sticky left-0 z-20 min-w-[250px] border-r border-b bg-card px-3 pb-3 font-medium">
                                            Aset
                                        </th>
                                        <th className="min-w-[180px] border-b px-3 pb-3 font-medium">
                                            Cabang
                                        </th>
                                        <th className="min-w-[130px] border-b px-3 pb-3 font-medium">
                                            Status
                                        </th>
                                        <th className="min-w-[150px] border-b px-3 pb-3 text-right font-medium">
                                            Investasi
                                        </th>
                                        <th className="min-w-[185px] border-b px-3 pb-3 text-right font-medium">
                                            Pendapatan terealisasi
                                        </th>
                                        <th className="min-w-[135px] border-b px-3 pb-3 text-right font-medium">
                                            Maintenance
                                        </th>
                                        <th className="min-w-[90px] border-b px-3 pb-3 text-right font-medium">
                                            ROI
                                        </th>
                                        <th className="min-w-[110px] border-b px-3 pb-3 text-right font-medium">
                                            BEP
                                        </th>
                                        <th className="min-w-[180px] border-b px-3 pb-3 text-right font-medium">
                                            Utilisasi
                                        </th>
                                        <th className="min-w-[135px] border-b px-3 pb-3 text-right font-medium whitespace-nowrap">
                                            Estimasi BEP
                                        </th>
                                        <th className="min-w-[290px] border-b px-4 pb-3 font-medium whitespace-nowrap">
                                            Kualitas data
                                        </th>
                                        <th className="min-w-[300px] border-b px-4 pb-3 font-medium whitespace-nowrap">
                                            Rekomendasi bisnis
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {assets.data.map((asset) => (
                                        <tr
                                            key={asset.id}
                                            className="group align-top"
                                        >
                                            <td className="sticky left-0 z-10 border-r border-b bg-card px-3 py-4 group-hover:bg-muted/30">
                                                <p className="font-medium">
                                                    {asset.product.name}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {asset.asset_code} ·{' '}
                                                    {asset.product.sku}
                                                </p>
                                                {asset.purchase_date && (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Beli{' '}
                                                        {date.format(
                                                            new Date(
                                                                asset.purchase_date,
                                                            ),
                                                        )}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="border-b px-3 py-4">
                                                <p>{asset.branch.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {asset.branch.code}
                                                </p>
                                            </td>
                                            <td className="border-b px-3 py-4">
                                                <div className="flex flex-col items-start gap-1">
                                                    <Badge variant="outline">
                                                        {statusLabel(
                                                            asset.status,
                                                        )}
                                                    </Badge>
                                                    <span className="text-xs text-muted-foreground">
                                                        {conditionLabel(
                                                            asset.condition,
                                                        )}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="border-b px-3 py-4 text-right">
                                                <p>
                                                    {money.format(
                                                        asset.purchase_price,
                                                    )}
                                                </p>
                                                {asset.purchase_price_suspicious && (
                                                    <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                                        Lebih rendah dari tarif{' '}
                                                        {money.format(
                                                            asset.max_rental_rate,
                                                        )}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="border-b px-3 py-4 text-right">
                                                <p className="font-medium">
                                                    {money.format(
                                                        asset.lifetime_revenue,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Periode{' '}
                                                    {money.format(
                                                        asset.period_revenue,
                                                    )}
                                                </p>
                                                {asset.open_lifetime_revenue >
                                                    0 && (
                                                    <p className="text-xs text-amber-600 dark:text-amber-400">
                                                        Berjalan{' '}
                                                        {money.format(
                                                            asset.open_lifetime_revenue,
                                                        )}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="border-b px-3 py-4 text-right">
                                                {money.format(
                                                    asset.maintenance_cost,
                                                )}
                                            </td>
                                            <td className="border-b px-3 py-4 text-right font-medium">
                                                {percent(asset.roi_percent)}
                                            </td>
                                            <td className="border-b px-3 py-4 text-right">
                                                <p className="font-medium">
                                                    {percent(
                                                        asset.bep_progress_percent,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Sisa{' '}
                                                    {money.format(
                                                        asset.remaining_to_bep,
                                                    )}
                                                </p>
                                            </td>
                                            <td className="border-b px-3 py-4 text-right">
                                                <p className="font-medium">
                                                    {percent(
                                                        asset.utilization_percent,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {number.format(
                                                        asset.rented_hours,
                                                    )}{' '}
                                                    jam ·{' '}
                                                    {
                                                        asset.realized_rental_count
                                                    }{' '}
                                                    selesai
                                                    {asset.open_rental_count > 0
                                                        ? ` · ${asset.open_rental_count} berjalan`
                                                        : ''}
                                                </p>
                                            </td>
                                            <td className="border-b px-3 py-4 text-right">
                                                {asset.remaining_to_bep <= 0
                                                    ? 'Sudah BEP'
                                                    : asset.estimated_bep_months ===
                                                        null
                                                      ? 'Belum terproyeksi'
                                                      : `${number.format(asset.estimated_bep_months)} bulan`}
                                            </td>
                                            <td className="border-b px-4 py-4">
                                                <div
                                                    className={`max-w-[270px] rounded-lg border p-3 ${dataQualityClass(
                                                        asset.data_quality.tone,
                                                    )}`}
                                                >
                                                    <div className="flex items-center justify-between gap-2">
                                                        <p className="text-xs font-semibold">
                                                            Skor{' '}
                                                            {
                                                                asset
                                                                    .data_quality
                                                                    .score
                                                            }
                                                            /100
                                                        </p>
                                                        <Badge
                                                            variant="outline"
                                                            className={confidenceClass(
                                                                asset
                                                                    .data_quality
                                                                    .confidence,
                                                            )}
                                                        >
                                                            {
                                                                asset
                                                                    .data_quality
                                                                    .confidence_label
                                                            }
                                                        </Badge>
                                                    </div>
                                                    {asset.data_quality.issues
                                                        .length === 0 ? (
                                                        <p className="mt-2 text-[11px] leading-4 text-emerald-700 dark:text-emerald-300">
                                                            Data siap digunakan
                                                            untuk keputusan.
                                                        </p>
                                                    ) : (
                                                        <div className="mt-2 space-y-1">
                                                            {asset.data_quality.issues
                                                                .slice(0, 2)
                                                                .map(
                                                                    (issue) => (
                                                                        <p
                                                                            key={
                                                                                issue.code
                                                                            }
                                                                            className="text-[11px] leading-4 text-muted-foreground"
                                                                        >
                                                                            {issue.count >
                                                                            1
                                                                                ? `${issue.count} × `
                                                                                : ''}
                                                                            {
                                                                                issue.label
                                                                            }
                                                                        </p>
                                                                    ),
                                                                )}
                                                            {asset.data_quality
                                                                .issues.length >
                                                                2 && (
                                                                <p className="text-[11px] text-muted-foreground">
                                                                    +
                                                                    {asset
                                                                        .data_quality
                                                                        .issues
                                                                        .length -
                                                                        2}{' '}
                                                                    catatan lain
                                                                </p>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="border-b px-4 py-4">
                                                <div
                                                    className={`max-w-[270px] rounded-lg border p-3 ${recommendationClass(
                                                        asset.recommendation
                                                            .tone,
                                                    )}`}
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <p className="text-xs font-semibold">
                                                            {
                                                                asset
                                                                    .recommendation
                                                                    .label
                                                            }
                                                        </p>
                                                        <Badge
                                                            variant="outline"
                                                            className={confidenceClass(
                                                                asset
                                                                    .recommendation
                                                                    .confidence,
                                                            )}
                                                        >
                                                            {
                                                                asset
                                                                    .recommendation
                                                                    .confidence_label
                                                            }
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-2 text-[11px] leading-4 opacity-80">
                                                        {
                                                            asset.recommendation
                                                                .description
                                                        }
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {assets.data.length === 0 && (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <Boxes className="size-10 text-muted-foreground" />
                                <p className="mt-4 font-medium">
                                    Tidak ada aset pada filter ini
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Ubah periode, cabang, kategori, status, atau
                                    kata pencarian.
                                </p>
                            </div>
                        )}

                        <PaginationLinks
                            links={assets.links}
                            from={assets.from}
                            to={assets.to}
                            total={assets.total}
                        />
                    </CardContent>
                </Card>

                <Card className="border-dashed">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Info className="size-4" />
                            Metodologi perhitungan
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm text-muted-foreground md:grid-cols-2 xl:grid-cols-5">
                        <p>
                            <strong className="text-foreground">
                                Pendapatan:
                            </strong>{' '}
                            {methodology.revenue}
                        </p>
                        <p>
                            <strong className="text-foreground">ROI:</strong>{' '}
                            {methodology.roi}
                        </p>
                        <p>
                            <strong className="text-foreground">BEP:</strong>{' '}
                            {methodology.bep}
                        </p>
                        <p>
                            <strong className="text-foreground">
                                Utilisasi:
                            </strong>{' '}
                            {methodology.utilization}
                        </p>
                        <p>
                            <strong className="text-foreground">
                                Rekomendasi:
                            </strong>{' '}
                            {methodology.recommendation}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AssetAnalytics.layout = {
    breadcrumbs: [
        {
            title: 'Laporan',
            href: '/reports/asset-analytics',
        },
        {
            title: 'Analitik Aset',
            href: '/reports/asset-analytics',
        },
    ],
};
