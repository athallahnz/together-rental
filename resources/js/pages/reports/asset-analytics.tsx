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
import {
    Stage6Text,
    stage6Display,
    useStage6Numbers,
} from '@/components/stage6-text';
import { useAppLocale } from '@/lib/i18n';
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
import { FilterBar } from '@/components/ui/filter-bar';
import { MetricCard } from '@/components/ui/metric-card';
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

function statusLabel(value: string, locale: 'id' | 'en') {
    const labels: Record<string, string> = {
        available: 'Tersedia',
        reserved: 'Direservasi',
        rented: 'Disewa',
        maintenance: 'Pemeliharaan',
        retired: 'Pensiun',
        lost: 'Hilang',
    };

    return stage6Display(labels[value] ?? value, locale);
}

function conditionLabel(value: string, locale: 'id' | 'en') {
    const labels: Record<string, string> = {
        good: 'Baik',
        fair: 'Cukup',
        poor: 'Buruk',
        damaged: 'Rusak',
        critical: 'Kritis',
    };

    return stage6Display(labels[value] ?? value, locale);
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
    const { locale } = useAppLocale();
    const { money } = useStage6Numbers(1);
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
                aria-label={stage6Display(
                    'Grafik pendapatan terealisasi dan maintenance aset',
                    locale,
                )}
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
                    {asset?.product.name ?? (
                        <Stage6Text text="Belum ada data" />
                    )}
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
    const { locale } = useAppLocale();
    const { money, number, date } = useStage6Numbers(1);
    const percent = (value: number | null) =>
        value === null ? '—' : `${number.format(value)}%`;
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
            label: stage6Display('Investasi tercatat', locale),
            value: money.format(summary.total_investment),
            note: `${summary.priced_asset_count} ${stage6Display('dari', locale)} ${summary.asset_count} ${stage6Display('aset ·', locale)} ${percent(summary.investment_coverage_percent)} ${stage6Display('cakupan', locale)}`,
            icon: WalletCards,
        },
        {
            label: stage6Display('Pendapatan terealisasi', locale),
            value: money.format(summary.lifetime_revenue),
            note: `${money.format(summary.period_revenue)} ${stage6Display('periode ·', locale)} ${money.format(summary.open_lifetime_revenue)} ${stage6Display('berjalan', locale)}`,
            icon: CircleDollarSign,
        },
        {
            label: stage6Display('Kontribusi bersih', locale),
            value: money.format(summary.net_contribution),
            note: `${stage6Display('Pemeliharaan', locale)} ${money.format(summary.maintenance_cost)}`,
            icon: TrendingUp,
        },
        {
            label: stage6Display('Progress BEP', locale),
            value: percent(summary.bep_progress_percent),
            note: `${summary.investment_is_complete ? '' : stage6Display('Estimasi sementara · ', locale)}${summary.bep_asset_count} ${stage6Display('unit sudah BEP', locale)}`,
            icon: Target,
        },
        {
            label: stage6Display('ROI keseluruhan', locale),
            value: percent(summary.roi_percent),
            note: `${summary.investment_is_complete ? '' : stage6Display('Estimasi sementara · ', locale)}${stage6Display('Laba bersih ', locale)}${money.format(summary.net_profit)}`,
            icon: BarChart3,
        },
        {
            label: stage6Display('Utilisasi periode', locale),
            value: percent(summary.utilization_percent),
            note: `${summary.active_asset_count} ${stage6Display('unit aktif', locale)}`,
            icon: Gauge,
        },
    ];

    return (
        <>
            <Head title={stage6Display('Analitik Aset, ROI & BEP', locale)} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            {<Stage6Text text="Modul 7 · Intelijen Bisnis" />}
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            {<Stage6Text text="Analitik Aset, ROI & BEP" />}
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            {
                                <Stage6Text text="Ukur produktivitas unit, progres balik modal, utilisasi, biaya maintenance, dan rekomendasi tindakan per cabang." />
                            }
                        </p>
                    </div>
                    {permissions.export && (
                        <Button asChild variant="outline">
                            <a href={exportUrl}>
                                <Download />
                                {<Stage6Text text="Ekspor CSV" />}
                            </a>
                        </Button>
                    )}
                </header>

                <FilterBar
                    title={stage6Display('Filter analitik', locale)}
                    description={stage6Display(
                        'Periode memengaruhi pendapatan, tren, utilisasi, dan estimasi BEP; ROI serta BEP memakai data lifetime.',
                        locale,
                    )}
                    contentClassName="grid-cols-1"
                >
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-[minmax(220px,1.35fr)_minmax(155px,0.85fr)_minmax(155px,0.85fr)_minmax(240px,1.25fr)_minmax(180px,1fr)_minmax(155px,0.8fr)_minmax(155px,0.8fr)]">
                        <Input
                            className="min-w-0"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={stage6Display(
                                'Cari aset atau produk',
                                locale,
                            )}
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
                            onChange={(event) => setFrom(event.target.value)}
                        />
                        <Input
                            className="min-w-0"
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                        />
                        <Select value={branchId} onValueChange={setBranchId}>
                            <SelectTrigger className="w-full min-w-0">
                                <SelectValue
                                    placeholder={stage6Display(
                                        'Semua cabang',
                                        locale,
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {<Stage6Text text="Semua cabang" />}
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
                                <SelectValue
                                    placeholder={stage6Display(
                                        'Semua kategori',
                                        locale,
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {<Stage6Text text="Semua kategori" />}
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
                                <SelectValue
                                    placeholder={stage6Display(
                                        'Semua status',
                                        locale,
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {<Stage6Text text="Semua status" />}
                                </SelectItem>
                                <SelectItem value="available">
                                    {<Stage6Text text="Tersedia" />}
                                </SelectItem>
                                <SelectItem value="reserved">
                                    {<Stage6Text text="Direservasi" />}
                                </SelectItem>
                                <SelectItem value="rented">
                                    {<Stage6Text text="Disewa" />}
                                </SelectItem>
                                <SelectItem value="maintenance">
                                    {<Stage6Text text="Pemeliharaan" />}
                                </SelectItem>
                                <SelectItem value="retired">
                                    {<Stage6Text text="Pensiun" />}
                                </SelectItem>
                                <SelectItem value="lost">
                                    {<Stage6Text text="Hilang" />}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={condition} onValueChange={setCondition}>
                            <SelectTrigger className="w-full min-w-0">
                                <SelectValue
                                    placeholder={stage6Display(
                                        'Semua kondisi',
                                        locale,
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {<Stage6Text text="Semua kondisi" />}
                                </SelectItem>
                                <SelectItem value="good">
                                    {<Stage6Text text="Baik" />}
                                </SelectItem>
                                <SelectItem value="fair">
                                    {<Stage6Text text="Cukup" />}
                                </SelectItem>
                                <SelectItem value="poor">
                                    {<Stage6Text text="Buruk" />}
                                </SelectItem>
                                <SelectItem value="damaged">
                                    {<Stage6Text text="Rusak" />}
                                </SelectItem>
                                <SelectItem value="critical">
                                    {<Stage6Text text="Kritis" />}
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
                            {<Stage6Text text="Atur ulang" />}
                        </Button>
                        <Button
                            className="w-full sm:w-auto"
                            onClick={applyFilters}
                        >
                            <Search />
                            {<Stage6Text text="Terapkan" />}
                        </Button>
                    </div>
                </FilterBar>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {kpis.map((item) => {
                        const Icon = item.icon;
                        const Direction =
                            item.label === 'ROI keseluruhan'
                                ? metricDirection(summary.roi_percent)
                                : null;

                        return (
                            <MetricCard
                                key={item.label}
                                label={item.label}
                                value={item.value}
                                detail={item.note}
                                icon={Icon}
                                compact={item.value.length > 18}
                                footer={
                                    Direction ? (
                                        <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                            <Direction
                                                className={`size-4 ${
                                                    (summary.roi_percent ??
                                                        0) >= 0
                                                        ? 'text-emerald-600'
                                                        : 'text-destructive'
                                                }`}
                                            />
                                            {
                                                <Stage6Text text="Arah ROI keseluruhan" />
                                            }
                                        </span>
                                    ) : undefined
                                }
                            />
                        );
                    })}
                </section>

                <section className="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>
                                    {
                                        <Stage6Text text="Pendapatan vs maintenance" />
                                    }
                                </CardTitle>
                                <CardDescription>
                                    {
                                        <Stage6Text text="Tren bulanan berdasarkan tanggal checkout, approval perpanjangan, dan penyelesaian maintenance." />
                                    }
                                </CardDescription>
                            </div>
                            <div className="flex flex-col gap-2 text-xs">
                                <span className="flex items-center gap-2">
                                    <span className="size-2 rounded-full bg-primary" />
                                    {
                                        <Stage6Text text="Pendapatan terealisasi" />
                                    }
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="size-2 rounded-full bg-destructive" />
                                    {<Stage6Text text="Pemeliharaan" />}
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
                                <CardTitle>
                                    {<Stage6Text text="Keputusan Portofolio" />}
                                </CardTitle>
                                <CardDescription>
                                    {
                                        <Stage6Text text="Bedakan aset sehat, tindakan nyata, pemantauan, dan keputusan yang ditunda." />
                                    }
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                                {[
                                    {
                                        label: stage6Display(
                                            'Perlu tindakan operasional',
                                            locale,
                                        ),
                                        value: insights.business_action_count,
                                        note: `${insights.high_confidence_action_count} ${stage6Display('keyakinan tinggi', locale)}`,
                                        icon: Activity,
                                    },
                                    {
                                        label: stage6Display(
                                            'Aset sehat / pertahankan',
                                            locale,
                                        ),
                                        value: insights.healthy_asset_count,
                                        note: stage6Display(
                                            'telah melewati BEP',
                                            locale,
                                        ),
                                        icon: ShieldCheck,
                                    },
                                    {
                                        label: stage6Display(
                                            'Pantau menuju BEP',
                                            locale,
                                        ),
                                        value: insights.monitor_asset_count,
                                        note: stage6Display(
                                            'belum perlu intervensi',
                                            locale,
                                        ),
                                        icon: Target,
                                    },
                                    {
                                        label: stage6Display(
                                            'Keputusan ditunda',
                                            locale,
                                        ),
                                        value: insights.deferred_decision_count,
                                        note: stage6Display(
                                            'menunggu validasi investasi',
                                            locale,
                                        ),
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
                                <CardTitle>
                                    {<Stage6Text text="Rincian tindakan" />}
                                </CardTitle>
                                <CardDescription>
                                    {
                                        <Stage6Text text="Hanya intervensi yang benar-benar perlu dikerjakan." />
                                    }
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                                {[
                                    {
                                        label: stage6Display(
                                            'Tambah kapasitas',
                                            locale,
                                        ),
                                        value: insights.add_capacity_count,
                                        note: stage6Display(
                                            'permintaan kuat',
                                            locale,
                                        ),
                                        icon: TrendingUp,
                                    },
                                    {
                                        label: stage6Display(
                                            'Promosikan aset',
                                            locale,
                                        ),
                                        value: insights.promote_count,
                                        note: stage6Display(
                                            'utilisasi periode rendah',
                                            locale,
                                        ),
                                        icon: Activity,
                                    },
                                    {
                                        label: stage6Display(
                                            'Evaluasi penjualan',
                                            locale,
                                        ),
                                        value: insights.review_disposal_count,
                                        note: stage6Display(
                                            'usia dan BEP kurang sehat',
                                            locale,
                                        ),
                                        icon: Boxes,
                                    },
                                    {
                                        label: stage6Display(
                                            'Evaluasi servis',
                                            locale,
                                        ),
                                        value: insights.service_review_count,
                                        note: stage6Display(
                                            'kondisi atau biaya',
                                            locale,
                                        ),
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
                        <CardTitle>
                            {<Stage6Text text="Kualitas data" />}
                        </CardTitle>
                        <CardDescription>
                            {
                                <Stage6Text text="Catatan audit ditampilkan terpisah dan tidak menggantikan rekomendasi bisnis." />
                            }
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {[
                            {
                                label: stage6Display(
                                    'Aset dengan blocker',
                                    locale,
                                ),
                                value: insights.data_quality_blocker_asset_count,
                                note: `${insights.data_quality_issue_asset_count} ${stage6Display('aset punya catatan', locale)}`,
                                icon: Info,
                            },
                            {
                                label: stage6Display('Kualitas tinggi', locale),
                                value: insights.high_quality_asset_count,
                                note: stage6Display(
                                    'siap untuk keputusan',
                                    locale,
                                ),
                                icon: ShieldCheck,
                            },
                            {
                                label: stage6Display(
                                    'Interval memakai fallback',
                                    locale,
                                ),
                                value: insights.invalid_interval_count,
                                note: stage6Display(
                                    'assignment memakai due date',
                                    locale,
                                ),
                                icon: Activity,
                            },
                            {
                                label: stage6Display(
                                    'Rental legacy kedaluwarsa',
                                    locale,
                                ),
                                value: insights.stale_active_rental_count,
                                note: stage6Display(
                                    'masih berstatus aktif',
                                    locale,
                                ),
                                icon: Activity,
                            },
                            {
                                label: stage6Display(
                                    'Harga beli bermasalah',
                                    locale,
                                ),
                                value:
                                    insights.missing_purchase_price_count +
                                    insights.suspicious_purchase_price_count,
                                note: `${insights.missing_purchase_price_count} ${stage6Display('kosong ·', locale)} ${insights.suspicious_purchase_price_count} ${stage6Display('perlu validasi', locale)}`,
                                icon: WalletCards,
                            },
                            {
                                label: stage6Display(
                                    'Riwayat maintenance',
                                    locale,
                                ),
                                value:
                                    insights.maintenance_record_count > 0
                                        ? insights.maintenance_record_count
                                        : stage6Display('Belum ada', locale),
                                note: stage6Display(
                                    'data biaya teknis',
                                    locale,
                                ),
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
                                            <p className="text-sm break-words">
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
                        title={stage6Display(
                            'Pendapatan periode tertinggi',
                            locale,
                        )}
                        asset={insights.top_revenue}
                        value={
                            insights.top_revenue
                                ? money.format(
                                      insights.top_revenue.period_revenue,
                                  )
                                : '—'
                        }
                        description={stage6Display(
                            'pendapatan pada periode terpilih',
                            locale,
                        )}
                    />
                    <InsightCard
                        title={stage6Display('Utilisasi tertinggi', locale)}
                        asset={insights.top_utilization}
                        value={
                            insights.top_utilization
                                ? percent(
                                      insights.top_utilization
                                          .utilization_percent,
                                  )
                                : '—'
                        }
                        description={stage6Display(
                            'pemakaian valid terhadap jam operasional aset aktif',
                            locale,
                        )}
                    />
                    <InsightCard
                        title={stage6Display(
                            'Biaya maintenance tertinggi',
                            locale,
                        )}
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
                                ? stage6Display(
                                      'biaya maintenance lifetime',
                                      locale,
                                  )
                                : stage6Display(
                                      'belum ada maintenance selesai yang tercatat',
                                      locale,
                                  )
                        }
                    />
                </section>

                {branchPerformance.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {<Stage6Text text="Performa per cabang" />}
                            </CardTitle>
                            <CardDescription>
                                {
                                    <Stage6Text text="Perbandingan investasi, kontribusi bersih, progres BEP, dan utilisasi unit berdasarkan cabang saat ini." />
                                }
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full min-w-[820px] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="pb-3 font-medium">
                                            {<Stage6Text text="Cabang" />}
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            {<Stage6Text text="Aset" />}
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            {<Stage6Text text="Investasi" />}
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            {<Stage6Text text="Kontribusi" />}
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            {<Stage6Text text="BEP" />}
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            {<Stage6Text text="Utilisasi" />}
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
                                                        <Stage6Text text="Berjalan" />{' '}
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
                        <CardTitle>
                            {<Stage6Text text="ROI & BEP per aset" />}
                        </CardTitle>
                        <CardDescription>
                            {
                                <Stage6Text text="Pisahkan kelayakan data dari tindakan bisnis agar prioritas tidak tertutup oleh catatan legacy minor. Kolom aset tetap terlihat saat tabel digeser horizontal." />
                            }
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1880px] border-separate border-spacing-0 text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="sticky left-0 z-20 min-w-[250px] border-r border-b bg-card px-3 pb-3 font-medium">
                                            {<Stage6Text text="Aset" />}
                                        </th>
                                        <th className="min-w-[180px] border-b px-3 pb-3 font-medium">
                                            {<Stage6Text text="Cabang" />}
                                        </th>
                                        <th className="min-w-[130px] border-b px-3 pb-3 font-medium">
                                            {<Stage6Text text="Status" />}
                                        </th>
                                        <th className="min-w-[150px] border-b px-3 pb-3 text-right font-medium">
                                            {<Stage6Text text="Investasi" />}
                                        </th>
                                        <th className="min-w-[185px] border-b px-3 pb-3 text-right font-medium">
                                            {
                                                <Stage6Text text="Pendapatan terealisasi" />
                                            }
                                        </th>
                                        <th className="min-w-[135px] border-b px-3 pb-3 text-right font-medium">
                                            {<Stage6Text text="Pemeliharaan" />}
                                        </th>
                                        <th className="min-w-[90px] border-b px-3 pb-3 text-right font-medium">
                                            {<Stage6Text text="ROI" />}
                                        </th>
                                        <th className="min-w-[110px] border-b px-3 pb-3 text-right font-medium">
                                            {<Stage6Text text="BEP" />}
                                        </th>
                                        <th className="min-w-[180px] border-b px-3 pb-3 text-right font-medium">
                                            {<Stage6Text text="Utilisasi" />}
                                        </th>
                                        <th className="min-w-[135px] border-b px-3 pb-3 text-right font-medium whitespace-nowrap">
                                            {<Stage6Text text="Estimasi BEP" />}
                                        </th>
                                        <th className="min-w-[290px] border-b px-4 pb-3 font-medium whitespace-nowrap">
                                            {
                                                <Stage6Text text="Kualitas data" />
                                            }
                                        </th>
                                        <th className="min-w-[300px] border-b px-4 pb-3 font-medium whitespace-nowrap">
                                            {
                                                <Stage6Text text="Rekomendasi bisnis" />
                                            }
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
                                                        <Stage6Text text="Beli" />{' '}
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
                                                            locale,
                                                        )}
                                                    </Badge>
                                                    <span className="text-xs text-muted-foreground">
                                                        {conditionLabel(
                                                            asset.condition,
                                                            locale,
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
                                                        <Stage6Text text="Lebih rendah dari tarif" />{' '}
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
                                                    <Stage6Text text="Periode" />{' '}
                                                    {money.format(
                                                        asset.period_revenue,
                                                    )}
                                                </p>
                                                {asset.open_lifetime_revenue >
                                                    0 && (
                                                    <p className="text-xs text-amber-600 dark:text-amber-400">
                                                        <Stage6Text text="Berjalan" />{' '}
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
                                                    <Stage6Text text="Sisa" />{' '}
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
                                                    <Stage6Text text="jam ·" />{' '}
                                                    {
                                                        asset.realized_rental_count
                                                    }{' '}
                                                    <Stage6Text text="selesai" />
                                                    {asset.open_rental_count > 0
                                                        ? ` · ${asset.open_rental_count} ${stage6Display('berjalan', locale)}`
                                                        : ''}
                                                </p>
                                            </td>
                                            <td className="border-b px-3 py-4 text-right">
                                                {asset.remaining_to_bep <= 0
                                                    ? stage6Display(
                                                          'Sudah BEP',
                                                          locale,
                                                      )
                                                    : asset.estimated_bep_months ===
                                                        null
                                                      ? stage6Display(
                                                            'Belum terproyeksi',
                                                            locale,
                                                        )
                                                      : `${number.format(asset.estimated_bep_months)} ${stage6Display('bulan', locale)}`}
                                            </td>
                                            <td className="border-b px-4 py-4">
                                                <div
                                                    className={`max-w-[270px] rounded-lg border p-3 ${dataQualityClass(
                                                        asset.data_quality.tone,
                                                    )}`}
                                                >
                                                    <div className="flex items-center justify-between gap-2">
                                                        <p className="text-xs font-semibold">
                                                            <Stage6Text text="Skor" />{' '}
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
                                                            {
                                                                <Stage6Text text="Data siap digunakan untuk keputusan." />
                                                            }
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
                                                                    <Stage6Text text="catatan lain" />
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
                                    {
                                        <Stage6Text text="Tidak ada aset pada filter ini" />
                                    }
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {
                                        <Stage6Text text="Ubah periode, cabang, kategori, status, atau kata pencarian." />
                                    }
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
                            {<Stage6Text text="Metodologi perhitungan" />}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm text-muted-foreground md:grid-cols-2 xl:grid-cols-5">
                        <p>
                            <strong className="text-foreground">
                                {<Stage6Text text="Pendapatan:" />}
                            </strong>{' '}
                            {methodology.revenue}
                        </p>
                        <p>
                            <strong className="text-foreground">
                                {<Stage6Text text="ROI:" />}
                            </strong>{' '}
                            {methodology.roi}
                        </p>
                        <p>
                            <strong className="text-foreground">
                                {<Stage6Text text="BEP:" />}
                            </strong>{' '}
                            {methodology.bep}
                        </p>
                        <p>
                            <strong className="text-foreground">
                                {<Stage6Text text="Utilisasi:" />}
                            </strong>{' '}
                            {methodology.utilization}
                        </p>
                        <p>
                            <strong className="text-foreground">
                                {<Stage6Text text="Rekomendasi:" />}
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
