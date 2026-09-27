import { Head, Link, router } from '@inertiajs/react';
import {
    BarChart3,
    BookOpenCheck,
    Building2,
    CircleDollarSign,
    Download,
    FileSpreadsheet,
    Info,
    Landmark,
    PackageSearch,
    ReceiptText,
    RotateCcw,
    Search,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
    IntegratedReportingPageProps,
    ReportColumn,
    ReportKey,
    ReportRow,
    ReportSummaryCard,
    ReportTrendPoint,
    ReportValue,
} from '@/types';

const statusLabels: Record<string, string> = {
    active: 'Aktif',
    approved: 'Disetujui',
    available: 'Tersedia',
    cancelled: 'Dibatalkan',
    closed: 'Ditutup',
    completed: 'Selesai',
    confirmed: 'Dikonfirmasi',
    converted: 'Dikonversi',
    damaged: 'Rusak',
    draft: 'Draf',
    expired: 'Kedaluwarsa',
    failed: 'Gagal',
    fair: 'Cukup',
    good: 'Baik',
    in_progress: 'Sedang diproses',
    in_transit: 'Dalam perjalanan',
    lost: 'Hilang',
    maintenance: 'Pemeliharaan',
    open: 'Terbuka',
    partial_return: 'Dikembalikan sebagian',
    correction_pending: 'Menunggu koreksi',
    overdue: 'Terlambat',
    paid: 'Dibayar',
    pending: 'Menunggu',
    poor: 'Buruk',
    posted: 'Dibukukan',
    received: 'Diterima',
    rejected: 'Ditolak',
    rented: 'Disewa',
    requested: 'Diajukan',
    reserved: 'Direservasi',
    retired: 'Pensiun',
    returned: 'Dikembalikan',
    submitted: 'Dikirim',
    void: 'Dibatalkan',
};

function statusClass(value: string) {
    if (
        [
            'completed',
            'paid',
            'returned',
            'received',
            'closed',
            'available',
            'good',
        ].includes(value)
    ) {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (
        ['void', 'cancelled', 'rejected', 'lost', 'damaged', 'poor'].includes(
            value,
        )
    ) {
        return 'border-destructive/30 bg-destructive/10 text-destructive';
    }

    if (
        [
            'requested',
            'approved',
            'overdue',
            'in_transit',
            'maintenance',
            'fair',
        ].includes(value)
    ) {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-border bg-muted text-muted-foreground';
}

function formatDate(
    value: ReportValue,
    withTime: boolean,
    locale: 'id' | 'en',
) {
    const { date, dateTime } = {
        date: new Intl.DateTimeFormat(locale === 'en' ? 'en-US' : 'id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }),
        dateTime: new Intl.DateTimeFormat(locale === 'en' ? 'en-US' : 'id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }),
    };

    if (value === null || value === '') {
        return '—';
    }

    const parsed = new Date(String(value));

    return Number.isNaN(parsed.getTime())
        ? String(value)
        : (withTime ? dateTime : date).format(parsed);
}

function CellValue({
    column,
    value,
}: {
    column: ReportColumn;
    value: ReportValue;
}) {
    const { locale } = useAppLocale();
    const { money, number } = useStage6Numbers();

    if (value === null || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    if (column.type === 'money') {
        return (
            <span className="font-medium whitespace-nowrap tabular-nums">
                {money.format(Number(value))}
            </span>
        );
    }

    if (column.type === 'number') {
        return (
            <span className="whitespace-nowrap tabular-nums">
                {number.format(Number(value))}
            </span>
        );
    }

    if (column.type === 'date' || column.type === 'datetime') {
        return (
            <span className="whitespace-nowrap">
                {formatDate(value, column.type === 'datetime', locale)}
            </span>
        );
    }

    if (column.type === 'status') {
        const status = String(value);

        return (
            <Badge variant="outline" className={statusClass(status)}>
                {stage6Display(
                    statusLabels[status] ?? status.replaceAll('_', ' '),
                    locale,
                )}
            </Badge>
        );
    }

    if (column.type === 'direction') {
        const direction = String(value);
        const incoming = direction === 'in';
        const neutral = direction === 'increase' || direction === 'decrease';
        const label = {
            in: 'Masuk',
            out: 'Keluar',
            increase: 'Penambah',
            decrease: 'Pengurang',
        }[direction];

        return (
            <Badge
                variant="outline"
                className={
                    neutral
                        ? 'border-border bg-muted text-muted-foreground'
                        : incoming
                          ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                          : 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                }
            >
                {stage6Display(label ?? direction, locale)}
            </Badge>
        );
    }

    return <span className="min-w-32 whitespace-normal">{String(value)}</span>;
}

const summaryIcons: LucideIcon[] = [
    BookOpenCheck,
    ReceiptText,
    CircleDollarSign,
    WalletCards,
    Landmark,
    RotateCcw,
    PackageSearch,
    BarChart3,
];

function SummaryCard({
    card,
    icon: Icon,
}: {
    card: ReportSummaryCard;
    icon: LucideIcon;
}) {
    const { money, number } = useStage6Numbers();

    return (
        <MetricCard
            label={card.label}
            value={
                card.type === 'money'
                    ? money.format(card.value)
                    : number.format(card.value)
            }
            detail={card.note}
            icon={Icon}
            compact={card.type === 'money'}
            tone="primary"
        />
    );
}

function TrendChart({ data }: { data: ReportTrendPoint[] }) {
    const { locale } = useAppLocale();
    const { money } = useStage6Numbers();
    const width = 980;
    const height = 300;
    const padding = 46;
    const values = data.flatMap((point) => [
        point.rental_value,
        point.collections,
        point.net,
    ]);
    const minValue = Math.min(0, ...values);
    const maxValue = Math.max(1, ...values);
    const range = Math.max(maxValue - minValue, 1);
    const x = (index: number) =>
        data.length <= 1
            ? width / 2
            : padding + (index / (data.length - 1)) * (width - padding * 2);
    const y = (value: number) =>
        padding + ((maxValue - value) / range) * (height - padding * 2);
    const path = (value: (point: ReportTrendPoint) => number) =>
        data
            .map(
                (point, index) =>
                    `${index === 0 ? 'M' : 'L'} ${x(index)} ${y(value(point))}`,
            )
            .join(' ');
    const labelEvery = Math.max(1, Math.ceil(data.length / 8));

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-x-5 gap-y-2 text-xs text-muted-foreground">
                <span className="flex items-center gap-2">
                    <span className="size-2.5 rounded-full bg-violet-500" />
                    {<Stage6Text text="Nilai penyewaan" />}
                </span>
                <span className="flex items-center gap-2">
                    <span className="size-2.5 rounded-full bg-emerald-500" />
                    {<Stage6Text text="Pembayaran masuk" />}
                </span>
                <span className="flex items-center gap-2">
                    <span className="size-2.5 rounded-full bg-primary" />
                    {<Stage6Text text="Arus kas bersih" />}
                </span>
            </div>

            <div className="overflow-x-auto">
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    className="min-w-[760px]"
                    role="img"
                    aria-label={stage6Display(
                        'Tren nilai rental, payment masuk, dan arus kas bersih',
                        locale,
                    )}
                >
                    {[0, 0.25, 0.5, 0.75, 1].map((ratio) => {
                        const value = minValue + range * ratio;
                        const lineY = y(value);

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
                                    {money.format(value)}
                                </text>
                            </g>
                        );
                    })}
                    <line
                        x1={padding}
                        x2={width - padding}
                        y1={y(0)}
                        y2={y(0)}
                        className="stroke-foreground/30"
                    />
                    <path
                        d={path((point) => point.rental_value)}
                        fill="none"
                        className="stroke-violet-500"
                        strokeWidth="3"
                        strokeDasharray="8 5"
                    />
                    <path
                        d={path((point) => point.collections)}
                        fill="none"
                        className="stroke-emerald-500"
                        strokeWidth="3.5"
                    />
                    <path
                        d={path((point) => point.net)}
                        fill="none"
                        className="stroke-primary"
                        strokeWidth="4"
                    />
                    {data.map((point, index) =>
                        index % labelEvery === 0 ||
                        index === data.length - 1 ? (
                            <text
                                key={point.key}
                                x={x(index)}
                                y={height - 10}
                                textAnchor="middle"
                                className="fill-muted-foreground text-[11px]"
                            >
                                {point.label}
                            </text>
                        ) : null,
                    )}
                </svg>
            </div>
        </div>
    );
}

function ReportTable({
    columns,
    rows,
}: {
    columns: ReportColumn[];
    rows: ReportRow[];
}) {
    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full min-w-max text-sm">
                <thead className="bg-muted/70 text-left text-xs tracking-wide text-muted-foreground uppercase">
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column.key}
                                className="px-4 py-3 font-medium"
                            >
                                {column.label}
                            </th>
                        ))}
                        <th className="px-4 py-3 text-right font-medium">
                            {<Stage6Text text="Aksi" />}
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((row) => (
                        <tr
                            key={row.id}
                            className="transition-colors hover:bg-muted/35"
                        >
                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className="max-w-64 px-4 py-3 align-top"
                                >
                                    <CellValue
                                        column={column}
                                        value={row.values[column.key] ?? null}
                                    />
                                </td>
                            ))}
                            <td className="px-4 py-3 text-right align-top">
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={row.href}>
                                        {<Stage6Text text="Detail" />}
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                    ))}
                    {rows.length === 0 && (
                        <tr>
                            <td
                                colSpan={columns.length + 1}
                                className="px-6 py-14 text-center"
                            >
                                <p className="font-medium">
                                    {
                                        <Stage6Text text="Tidak ada data laporan" />
                                    }
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {
                                        <Stage6Text text="Ubah periode atau filter untuk melihat data lainnya." />
                                    }
                                </p>
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

export default function IntegratedReportingCenter({
    summary,
    trend,
    branchPerformance,
    columns,
    rows,
    statusOptions,
    reportMeta,
    methodology,
    filters,
    branches,
    paymentMethods,
    financialCategories,
    permissions,
    generatedAt,
    tabs,
}: IntegratedReportingPageProps) {
    const { locale } = useAppLocale();
    const { money, number, dateTime } = useStage6Numbers();
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [branchId, setBranchId] = useState(
        filters.branch_id?.toString() ?? 'all',
    );
    const [status, setStatus] = useState(filters.status || 'all');
    const [paymentMethodId, setPaymentMethodId] = useState(
        filters.payment_method_id?.toString() ?? 'all',
    );
    const [categoryId, setCategoryId] = useState(
        filters.category_id?.toString() ?? 'all',
    );
    const [search, setSearch] = useState(filters.search);
    const query = useMemo(
        () => ({
            from,
            to,
            branch_id: branchId === 'all' ? undefined : Number(branchId),
            report: filters.report,
            status: status === 'all' ? undefined : status,
            payment_method_id:
                paymentMethodId === 'all' ? undefined : Number(paymentMethodId),
            category_id: categoryId === 'all' ? undefined : Number(categoryId),
            search: search || undefined,
        }),
        [
            branchId,
            categoryId,
            filters.report,
            from,
            paymentMethodId,
            search,
            status,
            to,
        ],
    );
    const queryString = useMemo(
        () =>
            new URLSearchParams(
                Object.entries(query).reduce<Record<string, string>>(
                    (params, [key, value]) => {
                        if (value !== undefined) {
                            params[key] = String(value);
                        }

                        return params;
                    },
                    {},
                ),
            ).toString(),
        [query],
    );
    const applyFilters = () => {
        router.get('/reports', query, { preserveState: true, replace: true });
    };
    const resetFilters = () => {
        router.get('/reports', { report: filters.report });
    };
    const changeTab = (report: ReportKey) => {
        router.get(
            '/reports',
            {
                from,
                to,
                branch_id: branchId === 'all' ? undefined : Number(branchId),
                report,
            },
            { preserveState: false, replace: true },
        );
    };
    const showFinanceFilters = filters.report === 'finance';

    return (
        <>
            <Head title={stage6Display('Pusat Laporan Terintegrasi', locale)} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            {<Stage6Text text="Laporan Manajemen" />}
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            {
                                <Stage6Text text="Pusat Laporan dan Ekspor Terintegrasi" />
                            }
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            {
                                <Stage6Text text="Laporan formal operasional, keuangan, kas, aset, dan transfer antar-cabang dari satu sumber data yang dapat ditelusuri kembali ke transaksi asal." />
                            }
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/reports/asset-analytics">
                                <BarChart3 className="size-4" />
                                {<Stage6Text text="Analitik ROI/BEP" />}
                            </Link>
                        </Button>
                        {permissions.export && (
                            <>
                                <Button variant="outline" asChild>
                                    <a
                                        href={`/reports/export?${queryString}&format=excel`}
                                    >
                                        <FileSpreadsheet className="size-4" />
                                        Excel
                                    </a>
                                </Button>
                                <Button asChild>
                                    <a
                                        href={`/reports/export?${queryString}&format=pdf`}
                                    >
                                        <Download className="size-4" />
                                        PDF
                                    </a>
                                </Button>
                            </>
                        )}
                    </div>
                </header>

                <FilterBar
                    title={stage6Display('Filter laporan', locale)}
                    description={stage6Display(
                        'Rentang maksimal 367 hari. Filter cabang mengikuti hak akses pengguna.',
                        locale,
                    )}
                    contentClassName="md:grid-cols-2 xl:grid-cols-4"
                >
                    <label className="space-y-1.5 text-xs font-medium">
                        {<Stage6Text text="Dari tanggal" />}
                        <Input
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                        />
                    </label>
                    <label className="space-y-1.5 text-xs font-medium">
                        {<Stage6Text text="Sampai tanggal" />}
                        <Input
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                        />
                    </label>
                    <label className="space-y-1.5 text-xs font-medium">
                        {<Stage6Text text="Cabang" />}
                        <Select value={branchId} onValueChange={setBranchId}>
                            <SelectTrigger>
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
                                        value={String(branch.id)}
                                    >
                                        {branch.code} · {branch.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </label>
                    <label className="space-y-1.5 text-xs font-medium">
                        {<Stage6Text text="Status" />}
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger>
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
                                {statusOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </label>
                    {showFinanceFilters && (
                        <>
                            <label className="space-y-1.5 text-xs font-medium">
                                {<Stage6Text text="Metode pembayaran" />}
                                <Select
                                    value={paymentMethodId}
                                    onValueChange={setPaymentMethodId}
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder={stage6Display(
                                                'Semua metode',
                                                locale,
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            {<Stage6Text text="Semua metode" />}
                                        </SelectItem>
                                        {paymentMethods.map((method) => (
                                            <SelectItem
                                                key={method.id}
                                                value={String(method.id)}
                                            >
                                                {method.code} · {method.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </label>
                            <label className="space-y-1.5 text-xs font-medium">
                                {<Stage6Text text="Kategori keuangan" />}
                                <Select
                                    value={categoryId}
                                    onValueChange={setCategoryId}
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder={stage6Display(
                                                'Semua kategori',
                                                locale,
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            {
                                                <Stage6Text text="Semua kategori" />
                                            }
                                        </SelectItem>
                                        {financialCategories.map((category) => (
                                            <SelectItem
                                                key={category.id}
                                                value={String(category.id)}
                                            >
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </label>
                        </>
                    )}
                    <label className="space-y-1.5 text-xs font-medium xl:col-span-2">
                        {<Stage6Text text="Pencarian" />}
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        applyFilters();
                                    }
                                }}
                                className="pl-9"
                                placeholder={stage6Display(
                                    'Nomor transaksi, pelanggan, aset, register...',
                                    locale,
                                )}
                            />
                        </div>
                    </label>
                    <div className="flex items-end gap-2 xl:col-span-4">
                        <Button onClick={applyFilters}>
                            {<Stage6Text text="Terapkan filter" />}
                        </Button>
                        <Button variant="outline" onClick={resetFilters}>
                            <RotateCcw className="size-4" />
                            {<Stage6Text text="Atur ulang" />}
                        </Button>
                    </div>
                </FilterBar>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {summary.map((card, index) => (
                        <SummaryCard
                            key={card.key}
                            card={card}
                            icon={summaryIcons[index % summaryIcons.length]}
                        />
                    ))}
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {<Stage6Text text="Tren lintas modul" />}
                        </CardTitle>
                        <CardDescription>
                            {
                                <Stage6Text text="Nilai kontrak rental dibandingkan dengan payment masuk dan arus kas bersih." />
                            }
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TrendChart data={trend} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {<Stage6Text text="Performa cabang" />}
                        </CardTitle>
                        <CardDescription>
                            {
                                <Stage6Text text="Ringkasan nilai rental, kas bersih, piutang, dan biaya maintenance per cabang." />
                            }
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full min-w-[920px] text-sm">
                            <thead className="border-b text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="py-3 pr-4 font-medium">
                                        {<Stage6Text text="Cabang" />}
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        {<Stage6Text text="Penyewaan" />}
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        {<Stage6Text text="Nilai penyewaan" />}
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        {<Stage6Text text="Pembayaran masuk" />}
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        {<Stage6Text text="Keluar" />}
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        {<Stage6Text text="Kas bersih" />}
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        {<Stage6Text text="Piutang" />}
                                    </th>
                                    <th className="py-3 pl-4 text-right font-medium">
                                        {<Stage6Text text="Pemeliharaan" />}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {branchPerformance.map((branch) => (
                                    <tr key={branch.id}>
                                        <td className="py-3 pr-4 font-medium">
                                            <span className="flex items-center gap-2">
                                                <Building2 className="size-4 text-primary" />
                                                {branch.code} · {branch.name}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {number.format(branch.rental_count)}
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium tabular-nums">
                                            {money.format(branch.rental_value)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {money.format(branch.collections)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {money.format(branch.outflows)}
                                        </td>
                                        <td
                                            className={`px-4 py-3 text-right font-medium tabular-nums ${
                                                branch.net < 0
                                                    ? 'text-destructive'
                                                    : 'text-emerald-600 dark:text-emerald-400'
                                            }`}
                                        >
                                            {money.format(branch.net)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {money.format(branch.receivables)}
                                        </td>
                                        <td className="py-3 pl-4 text-right tabular-nums">
                                            {money.format(
                                                branch.maintenance_cost,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <section className="space-y-4">
                    <div className="flex gap-2 overflow-x-auto pb-1">
                        {tabs.map((tab) => (
                            <Button
                                key={tab.key}
                                variant={
                                    filters.report === tab.key
                                        ? 'default'
                                        : 'outline'
                                }
                                className="shrink-0"
                                onClick={() => changeTab(tab.key)}
                            >
                                {tab.label}
                            </Button>
                        ))}
                    </div>

                    <Card>
                        <CardHeader className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <CardTitle className="text-base">
                                    {reportMeta.label}
                                </CardTitle>
                                <CardDescription className="mt-1 max-w-3xl">
                                    {reportMeta.description}
                                </CardDescription>
                            </div>
                            <Badge variant="outline">
                                {number.format(reportMeta.row_count)}{' '}
                                {stage6Display('baris', locale)}
                            </Badge>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <ReportTable columns={columns} rows={rows.data} />
                            <PaginationLinks
                                links={rows.links}
                                from={rows.from}
                                to={rows.to}
                                total={rows.total}
                            />
                        </CardContent>
                    </Card>
                </section>

                <Card className="border-primary/20 bg-primary/5">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Info className="size-4 text-primary" />
                            {<Stage6Text text="Metodologi & jejak audit" />}
                        </CardTitle>
                        <CardDescription>
                            {stage6Display('Dibuat ', locale)}
                            {dateTime.format(new Date(generatedAt))}.{' '}
                            {stage6Display(
                                'Setiap baris memiliki tautan detail ke transaksi sumber.',
                                locale,
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-xs leading-5 text-muted-foreground md:grid-cols-2">
                        {Object.values(methodology).map((item) => (
                            <p key={item}>{item}</p>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
