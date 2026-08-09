import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    CircleDollarSign,
    CreditCard,
    Info,
    ReceiptText,
    RotateCcw,
    ShieldCheck,
    TrendingUp,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
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
    FinanceDashboardActivity,
    FinanceDashboardPageProps,
    FinanceDashboardTrendPoint,
} from '@/types';

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const number = new Intl.NumberFormat('id-ID');

const dateTime = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function Delta({
    value,
    inverse = false,
}: {
    value: number | null;
    inverse?: boolean;
}) {
    if (value === null) {
        return (
            <span className="text-xs text-muted-foreground">
                Baru pada periode ini
            </span>
        );
    }

    const isPositive = value >= 0;
    const isGood = inverse ? !isPositive : isPositive;
    const Icon = isPositive ? ArrowUpRight : ArrowDownRight;

    return (
        <span
            className={`inline-flex items-center gap-1 text-xs font-medium ${
                isGood
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-destructive'
            }`}
        >
            <Icon className="size-3.5" />
            {Math.abs(value).toLocaleString('id-ID', {
                maximumFractionDigits: 1,
            })}
            % vs periode lalu
        </span>
    );
}

function KpiCard({
    label,
    value,
    note,
    icon: Icon,
    delta,
    inverseDelta = false,
    href,
}: {
    label: string;
    value: string;
    note: string;
    icon: LucideIcon;
    delta?: number | null;
    inverseDelta?: boolean;
    href?: string;
}) {
    const content = (
        <Card className="h-full transition-colors hover:border-primary/40">
            <CardHeader className="flex flex-row items-start justify-between gap-4 pb-3">
                <div>
                    <CardDescription>{label}</CardDescription>
                    <CardTitle className="mt-2 text-2xl">{value}</CardTitle>
                </div>
                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-5" />
                </div>
            </CardHeader>
            <CardContent className="space-y-2">
                {delta !== undefined && (
                    <Delta value={delta} inverse={inverseDelta} />
                )}
                <p className="text-xs leading-5 text-muted-foreground">
                    {note}
                </p>
            </CardContent>
        </Card>
    );

    return href ? <Link href={href}>{content}</Link> : content;
}

function TrendChart({ data }: { data: FinanceDashboardTrendPoint[] }) {
    const width = 980;
    const height = 310;
    const padding = 48;
    const values = data.flatMap((point) => [
        point.collections,
        -point.expenses,
        -point.refunds,
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
    const path = (value: (point: FinanceDashboardTrendPoint) => number) =>
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
                    <span className="size-2.5 rounded-full bg-emerald-500" />
                    Payment masuk
                </span>
                <span className="flex items-center gap-2">
                    <span className="size-2.5 rounded-full bg-primary" />
                    Arus kas bersih
                </span>
                <span className="flex items-center gap-2">
                    <span className="size-2.5 rounded-full bg-amber-500" />
                    Payment keluar
                </span>
                <span className="flex items-center gap-2">
                    <span className="size-2.5 rounded-full bg-destructive" />
                    Refund paid
                </span>
            </div>

            <div className="overflow-x-auto">
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    className="min-w-[760px]"
                    role="img"
                    aria-label="Tren payment masuk, payment keluar, refund, dan arus kas bersih"
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
                        className="stroke-foreground/40"
                        strokeWidth="1.5"
                    />
                    <path
                        d={path((point) => point.collections)}
                        fill="none"
                        className="stroke-emerald-500"
                        strokeWidth="3.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                    <path
                        d={path((point) => point.net)}
                        fill="none"
                        className="stroke-primary"
                        strokeWidth="4"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                    <path
                        d={path((point) => -point.expenses)}
                        fill="none"
                        className="stroke-amber-500"
                        strokeWidth="2.5"
                        strokeDasharray="8 6"
                    />
                    <path
                        d={path((point) => -point.refunds)}
                        fill="none"
                        className="stroke-destructive"
                        strokeWidth="2.5"
                        strokeDasharray="4 5"
                    />

                    {data.map((point, index) =>
                        index % labelEvery === 0 ||
                        index === data.length - 1 ? (
                            <text
                                key={point.key}
                                x={x(index)}
                                y={height - 12}
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

function activityStatusClass(activity: FinanceDashboardActivity) {
    if (activity.status === 'void' || activity.status === 'cancelled') {
        return 'border-border bg-muted text-muted-foreground';
    }

    if (activity.status === 'rejected') {
        return 'border-destructive/30 bg-destructive/10 text-destructive';
    }

    if (activity.status === 'requested' || activity.status === 'approved') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
}

export default function FinanceDashboard({
    summary,
    comparison,
    trend,
    paymentMethods,
    sourceContexts,
    branchPerformance,
    recentActivity,
    attention,
    filters,
    branches,
    generatedAt,
    methodology,
}: FinanceDashboardPageProps) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [branchId, setBranchId] = useState(
        filters.branch_id?.toString() ?? 'all',
    );
    const baseQuery = useMemo(
        () => ({
            date_from: filters.from,
            date_to: filters.to,
            branch_id: filters.branch_id ?? undefined,
        }),
        [filters],
    );
    const queryString = (query: Record<string, string | number | undefined>) =>
        new URLSearchParams(
            Object.entries(query).reduce<Record<string, string>>(
                (result, [key, value]) => {
                    if (value !== undefined) {
                        result[key] = String(value);
                    }

                    return result;
                },
                {},
            ),
        ).toString();
    const paymentsUrl = (extra: Record<string, string | number> = {}) =>
        `/finance/payments?${queryString({ ...baseQuery, ...extra })}`;
    const refundsUrl = (status?: string) =>
        `/finance/refunds?${queryString({
            branch_id: filters.branch_id ?? undefined,
            status,
        })}`;
    const applyFilters = () => {
        router.get(
            '/finance/dashboard',
            {
                from,
                to,
                branch_id: branchId === 'all' ? undefined : Number(branchId),
            },
            { preserveState: true, replace: true },
        );
    };
    const resetFilters = () => router.get('/finance/dashboard');
    const integrityAlerts =
        attention.ledger_integrity.cash_payment_without_ledger +
        attention.ledger_integrity.cash_refund_without_ledger;

    return (
        <>
            <Head title="Finance Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Finance · Phase 5
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Finance Dashboard
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Pantau penerimaan, pengeluaran, refund, piutang,
                            deposit, dan kesehatan ledger dalam satu ringkasan
                            lintas cabang yang dapat ditelusuri.
                        </p>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Diperbarui {dateTime.format(new Date(generatedAt))}
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Filter dashboard
                        </CardTitle>
                        <CardDescription>
                            Periode memengaruhi transaksi, tren, dan
                            perbandingan. Piutang, deposit aktif, refund
                            outstanding, serta sesi kas adalah posisi saat ini.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 md:grid-cols-[minmax(160px,0.8fr)_minmax(160px,0.8fr)_minmax(240px,1.2fr)_auto_auto]">
                            <Input
                                type="date"
                                value={from}
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                            />
                            <Input
                                type="date"
                                value={to}
                                onChange={(event) => setTo(event.target.value)}
                            />
                            <Select
                                value={branchId}
                                onValueChange={setBranchId}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Semua cabang" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua cabang yang dapat diakses
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
                            <Button onClick={applyFilters}>Terapkan</Button>
                            <Button variant="outline" onClick={resetFilters}>
                                <RotateCcw />
                                Reset
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <KpiCard
                        label="Gross collections"
                        value={money.format(summary.gross_collections)}
                        note={`${number.format(summary.inbound_payment_count)} payment masuk · rata-rata ${money.format(summary.average_collection)}`}
                        icon={CircleDollarSign}
                        delta={comparison.gross_collections_percent}
                        href={paymentsUrl({ status: 'completed' })}
                    />
                    <KpiCard
                        label="Arus kas bersih"
                        value={money.format(summary.net_cash_flow)}
                        note={`Keluar ${money.format(summary.operating_outflows)} · refund ${money.format(summary.paid_refunds)}`}
                        icon={TrendingUp}
                        delta={comparison.net_cash_flow_percent}
                        href={paymentsUrl({ status: 'completed' })}
                    />
                    <KpiCard
                        label="Penerimaan rental"
                        value={money.format(summary.rental_collections)}
                        note={`Di luar deposit ${money.format(summary.deposit_collections)}`}
                        icon={WalletCards}
                        delta={comparison.rental_collections_percent}
                        href={paymentsUrl({ status: 'completed' })}
                    />
                    <KpiCard
                        label="Refund dibayarkan"
                        value={money.format(summary.paid_refunds)}
                        note={`${number.format(summary.paid_refund_count)} refund paid pada periode`}
                        icon={ReceiptText}
                        delta={comparison.paid_refunds_percent}
                        inverseDelta
                        href={refundsUrl('paid')}
                    />
                    <KpiCard
                        label="Piutang rental saat ini"
                        value={money.format(summary.receivable_amount)}
                        note={`${number.format(summary.receivable_count)} rental masih memiliki saldo`}
                        icon={CreditCard}
                        href="/rentals"
                    />
                    <KpiCard
                        label="Deposit masih ditahan"
                        value={money.format(summary.deposit_held)}
                        note="Payment deposit completed dikurangi refund deposit paid"
                        icon={ShieldCheck}
                        href={paymentsUrl({ status: 'completed' })}
                    />
                </section>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-xs text-muted-foreground">
                                Payment completed
                            </p>
                            <p className="mt-2 text-xl font-semibold">
                                {number.format(summary.completed_payment_count)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-xs text-muted-foreground">
                                Payment void
                            </p>
                            <p className="mt-2 text-xl font-semibold">
                                {number.format(summary.void_count)} ·{' '}
                                {money.format(summary.void_amount)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-xs text-muted-foreground">
                                Refund outstanding
                            </p>
                            <p className="mt-2 text-xl font-semibold">
                                {number.format(
                                    summary.outstanding_refund_count,
                                )}{' '}
                                ·{' '}
                                {money.format(
                                    summary.outstanding_refund_amount,
                                )}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-xs text-muted-foreground">
                                Sesi kas terbuka
                            </p>
                            <p className="mt-2 text-xl font-semibold">
                                {number.format(summary.open_cash_session_count)}
                            </p>
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Tren arus kas</CardTitle>
                        <CardDescription>
                            Payment void dikeluarkan dari grafik. Refund baru
                            mengurangi arus kas ketika statusnya paid.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TrendChart data={trend} />
                    </CardContent>
                </Card>

                <section className="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Komposisi metode pembayaran</CardTitle>
                            <CardDescription>
                                Kontribusi terhadap gross collections pada
                                periode.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {paymentMethods.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada transaksi pada periode ini.
                                </p>
                            ) : (
                                paymentMethods.map((method) => (
                                    <Link
                                        key={method.id}
                                        href={paymentsUrl({
                                            payment_method_id: method.id,
                                            status: 'completed',
                                        })}
                                        className="block space-y-2 rounded-lg border p-3 transition-colors hover:border-primary/40"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {method.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {method.code} ·{' '}
                                                    {number.format(
                                                        method.transaction_count,
                                                    )}{' '}
                                                    transaksi
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-semibold">
                                                    {money.format(
                                                        method.collections,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Net{' '}
                                                    {money.format(method.net)}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-primary"
                                                style={{
                                                    width: `${Math.min(method.share_percent, 100)}%`,
                                                }}
                                            />
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {method.share_percent}% gross ·
                                            refund{' '}
                                            {money.format(method.refunds)}
                                        </p>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Perlu perhatian</CardTitle>
                            <CardDescription>
                                Antrian dan indikator yang perlu
                                ditindaklanjuti.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {[
                                {
                                    label: 'Refund menunggu approval',
                                    count: attention.requested_refunds.count,
                                    value: attention.requested_refunds.amount,
                                    href: refundsUrl('requested'),
                                },
                                {
                                    label: 'Refund siap dibayarkan',
                                    count: attention.approved_refunds.count,
                                    value: attention.approved_refunds.amount,
                                    href: refundsUrl('approved'),
                                },
                                {
                                    label: 'Piutang melewati jatuh tempo',
                                    count: attention.overdue_receivables.count,
                                    value: attention.overdue_receivables.amount,
                                    href: '/rentals',
                                },
                                {
                                    label: 'Selisih penutupan kas',
                                    count: attention.cash_differences.count,
                                    value: attention.cash_differences.amount,
                                },
                            ].map((item) => {
                                const content = (
                                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {item.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {number.format(item.count)} item
                                            </p>
                                        </div>
                                        <p className="text-sm font-semibold">
                                            {money.format(item.value)}
                                        </p>
                                    </div>
                                );

                                return item.href ? (
                                    <Link key={item.label} href={item.href}>
                                        {content}
                                    </Link>
                                ) : (
                                    <div key={item.label}>{content}</div>
                                );
                            })}

                            <div
                                className={`rounded-lg border p-4 ${
                                    integrityAlerts === 0
                                        ? 'border-emerald-500/30 bg-emerald-500/5'
                                        : 'border-destructive/30 bg-destructive/5'
                                }`}
                            >
                                <div className="flex items-start gap-3">
                                    <ShieldCheck className="mt-0.5 size-5 shrink-0" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            Integritas cash ledger
                                        </p>
                                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                            {integrityAlerts === 0
                                                ? 'PASS — seluruh payment dan refund tunai memiliki ledger.'
                                                : `${integrityAlerts} transaksi tunai tidak memiliki ledger sumber.`}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                {branchPerformance.length > 1 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Performa cabang</CardTitle>
                            <CardDescription>
                                Perbandingan arus kas periode dan piutang saat
                                ini.
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
                                            Transaksi
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Masuk
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Keluar
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Refund
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Net
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Piutang
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {branchPerformance.map((branch) => (
                                        <tr
                                            key={branch.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-4">
                                                <Link
                                                    className="font-medium hover:text-primary"
                                                    href={`/finance/dashboard?${queryString(
                                                        {
                                                            from: filters.from,
                                                            to: filters.to,
                                                            branch_id:
                                                                branch.id,
                                                        },
                                                    )}`}
                                                >
                                                    {branch.code} ·{' '}
                                                    {branch.name}
                                                </Link>
                                            </td>
                                            <td className="py-4 text-right">
                                                {number.format(
                                                    branch.transaction_count,
                                                )}
                                            </td>
                                            <td className="py-4 text-right">
                                                {money.format(
                                                    branch.collections,
                                                )}
                                            </td>
                                            <td className="py-4 text-right">
                                                {money.format(branch.outflows)}
                                            </td>
                                            <td className="py-4 text-right">
                                                {money.format(branch.refunds)}
                                            </td>
                                            <td className="py-4 text-right font-semibold">
                                                {money.format(branch.net)}
                                            </td>
                                            <td className="py-4 text-right">
                                                {money.format(
                                                    branch.receivables,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                <section className="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Sumber transaksi</CardTitle>
                            <CardDescription>
                                Arus masuk dan keluar berdasarkan konteks asal.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {sourceContexts.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada transaksi pada periode ini.
                                </p>
                            ) : (
                                sourceContexts.map((source) => (
                                    <Link
                                        key={source.context}
                                        href={paymentsUrl({
                                            ...(source.context === 'unknown'
                                                ? {}
                                                : {
                                                      source_context:
                                                          source.context,
                                                  }),
                                            status: 'completed',
                                        })}
                                        className="flex items-start justify-between gap-4 rounded-lg border p-3 transition-colors hover:border-primary/40"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {source.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {number.format(
                                                    source.transaction_count,
                                                )}{' '}
                                                transaksi
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-semibold">
                                                {money.format(source.net)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Masuk{' '}
                                                {money.format(source.inflow)} ·
                                                keluar{' '}
                                                {money.format(source.outflow)}
                                            </p>
                                        </div>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Aktivitas keuangan terbaru</CardTitle>
                            <CardDescription>
                                Payment dan refund terbaru pada periode
                                terpilih.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {recentActivity.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada aktivitas pada periode ini.
                                </p>
                            ) : (
                                recentActivity.map((activity) => (
                                    <Link
                                        key={`${activity.kind}-${activity.id}`}
                                        href={activity.href}
                                        className="flex items-center justify-between gap-4 rounded-lg border p-3 transition-colors hover:border-primary/40"
                                    >
                                        <div className="flex min-w-0 items-start gap-3">
                                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                                                {activity.kind === 'payment' ? (
                                                    <CircleDollarSign className="size-4" />
                                                ) : (
                                                    <ReceiptText className="size-4" />
                                                )}
                                            </div>
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="truncate text-sm font-medium">
                                                        {activity.number}
                                                    </p>
                                                    <Badge
                                                        variant="outline"
                                                        className={activityStatusClass(
                                                            activity,
                                                        )}
                                                    >
                                                        {activity.status}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 truncate text-xs text-muted-foreground">
                                                    {activity.branch_code} ·{' '}
                                                    {activity.customer_name ??
                                                        'Tanpa pelanggan'}{' '}
                                                    ·{' '}
                                                    {dateTime.format(
                                                        new Date(
                                                            activity.occurred_at,
                                                        ),
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <p
                                            className={`shrink-0 text-sm font-semibold ${
                                                activity.direction === 'in' &&
                                                activity.status !== 'void'
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-destructive'
                                            }`}
                                        >
                                            {activity.direction === 'in' &&
                                            activity.status !== 'void'
                                                ? '+'
                                                : '-'}
                                            {money.format(activity.amount)}
                                        </p>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </section>

                <Card className="border-dashed">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Info className="size-4" />
                            Cara membaca angka
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-xs leading-5 text-muted-foreground md:grid-cols-2">
                        {Object.values(methodology).map((item) => (
                            <p key={item}>{item}</p>
                        ))}
                        <p className="md:col-span-2">
                            Perbandingan periode sebelumnya: {comparison.from}{' '}
                            sampai {comparison.to}.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

FinanceDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Finance Dashboard',
            href: '/finance/dashboard',
        },
    ],
};
