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
import { stage5Choice, stage5Display, Stage5Text, stage5Translate, stage5Date, stage5Money, stage5Number, stage5IntlLocale } from '@/components/stage5-text';
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
import { useAppLocale } from '@/lib/i18n';
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

const money = { format: stage5Money };

const number = { format: stage5Number };



function Delta({
    value,
    inverse = false,
}: {
    value: number | null;
    inverse?: boolean;
}) {
    const { locale: stage5Locale } = useAppLocale();

    if (value === null) {
        return (
            <span className="text-xs text-muted-foreground">
                <Stage5Text k="stage5.ui.9c48027a79e1" />
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
            {Math.abs(value).toLocaleString(stage5IntlLocale(stage5Locale), {
                maximumFractionDigits: 1,
            })}
            <Stage5Text k="stage5.ui.6b452bb956e2" />
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
    return (
        <MetricCard
            label={label}
            value={value}
            detail={note}
            icon={Icon}
            href={href}
            tone="primary"
            compact={value.length > 18}
            footer={
                delta !== undefined ? (
                    <Delta value={delta} inverse={inverseDelta} />
                ) : undefined
            }
        />
    );
}

function TrendChart({ data }: { data: FinanceDashboardTrendPoint[] }) {
    const { locale: stage5Locale } = useAppLocale();
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
                    <Stage5Text k="stage5.ui.b20a4ff937cb" />
                </span>
                <span className="flex items-center gap-2">
                    <span className="size-2.5 rounded-full bg-primary" />
                    <Stage5Text k="stage5.ui.87cc18989577" />
                </span>
                <span className="flex items-center gap-2">
                    <span className="size-2.5 rounded-full bg-amber-500" />
                    <Stage5Text k="stage5.ui.bffab1d92ea6" />
                </span>
                <span className="flex items-center gap-2">
                    <span className="size-2.5 rounded-full bg-destructive" />
                    <Stage5Text k="stage5.ui.b89ff1f0350e" />
                </span>
            </div>

            <div className="overflow-x-auto">
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    className="min-w-[760px]"
                    role="img"
                    aria-label={stage5Translate("stage5.ui.6f23934d2c7f", stage5Locale)}
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
    const { locale: stage5Locale } = useAppLocale();
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
            <Head title={stage5Translate("stage5.ui.fd67685200d8", stage5Locale)} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            <Stage5Text k="stage5.ui.00112f3a86da" />
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            <Stage5Text k="stage5.ui.fd67685200d8" />
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            <Stage5Text k="stage5.ui.a702256b61db" />
                        </p>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        <Stage5Text k="stage5.ui.9d77dbd733a3" /> {stage5Date(new Date(generatedAt), stage5Locale)}
                    </p>
                </header>

                <FilterBar
                    title={stage5Translate("stage5.ui.5491040aa52f", stage5Locale)}
                    description={stage5Translate("stage5.ui.a49cf6781561", stage5Locale)}
                    contentClassName="grid-cols-1"
                >
                    <div className="grid items-end gap-3 sm:grid-cols-2 md:grid-cols-[minmax(160px,0.8fr)_minmax(160px,0.8fr)_minmax(240px,1.2fr)_auto_auto]">
                        <Input
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                        />
                        <Input
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                        />
                        <Select value={branchId} onValueChange={setBranchId}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder={stage5Translate("stage5.ui.27d30aba48a4", stage5Locale)} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    <Stage5Text k="stage5.ui.1b8906804eb6" />
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
                        <Button onClick={applyFilters}><Stage5Text k="stage5.ui.9ff8760b6d49" /></Button>
                        <Button variant="outline" onClick={resetFilters}>
                            <RotateCcw />
                            <Stage5Text k="stage5.ui.44c57abd888a" />
                        </Button>
                    </div>
                </FilterBar>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <KpiCard
                        label={stage5Translate("stage5.ui.4d2913fb691c", stage5Locale)}
                        value={money.format(summary.gross_collections)}
                        note={stage5Choice(`${number.format(summary.inbound_payment_count)} pembayaran masuk · rata-rata ${money.format(summary.average_collection)}`, `${number.format(summary.inbound_payment_count)} incoming payments · average ${money.format(summary.average_collection)}`, stage5Locale)}
                        icon={CircleDollarSign}
                        delta={comparison.gross_collections_percent}
                        href={paymentsUrl({ status: 'completed' })}
                    />
                    <KpiCard
                        label={stage5Translate("stage5.ui.87cc18989577", stage5Locale)}
                        value={money.format(summary.net_cash_flow)}
                        note={stage5Choice(`Keluar ${money.format(summary.operating_outflows)} · refund ${money.format(summary.paid_refunds)}`, `Outflow ${money.format(summary.operating_outflows)} · refunds ${money.format(summary.paid_refunds)}`, stage5Locale)}
                        icon={TrendingUp}
                        delta={comparison.net_cash_flow_percent}
                        href={paymentsUrl({ status: 'completed' })}
                    />
                    <KpiCard
                        label={stage5Translate("stage5.ui.d25b7b5a395f", stage5Locale)}
                        value={money.format(summary.rental_collections)}
                        note={stage5Choice(`Di luar deposit ${money.format(summary.deposit_collections)}`, `Excluding deposits ${money.format(summary.deposit_collections)}`, stage5Locale)}
                        icon={WalletCards}
                        delta={comparison.rental_collections_percent}
                        href={paymentsUrl({ status: 'completed' })}
                    />
                    <KpiCard
                        label={stage5Translate("stage5.ui.37ab7b7dc5a2", stage5Locale)}
                        value={money.format(summary.paid_refunds)}
                        note={stage5Choice(`${number.format(summary.paid_refund_count)} refund dibayar pada periode ini`, `${number.format(summary.paid_refund_count)} refunds paid this period`, stage5Locale)}
                        icon={ReceiptText}
                        delta={comparison.paid_refunds_percent}
                        inverseDelta
                        href={refundsUrl('paid')}
                    />
                    <KpiCard
                        label={stage5Translate("stage5.ui.44ceed1ce138", stage5Locale)}
                        value={money.format(summary.receivable_amount)}
                        note={stage5Choice(`${number.format(summary.receivable_count)} rental masih memiliki saldo`, `${number.format(summary.receivable_count)} rentals have outstanding balances`, stage5Locale)}
                        icon={CreditCard}
                        href="/rentals"
                    />
                    <KpiCard
                        label={stage5Translate("stage5.ui.8c3a0f04b258", stage5Locale)}
                        value={money.format(summary.deposit_held)}
                        note="Payment deposit completed dikurangi refund deposit paid"
                        icon={ShieldCheck}
                        href={paymentsUrl({ status: 'completed' })}
                    />
                </section>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label={stage5Translate("stage5.ui.456b71b47640", stage5Locale)}
                        value={number.format(summary.completed_payment_count)}
                        icon={WalletCards}
                    />
                    <MetricCard
                        label={stage5Translate("stage5.ui.1f476996de6f", stage5Locale)}
                        value={`${number.format(summary.void_count)} · ${money.format(summary.void_amount)}`}
                        icon={CreditCard}
                        tone={summary.void_count > 0 ? 'danger' : 'neutral'}
                        compact
                    />
                    <MetricCard
                        label={stage5Translate("stage5.ui.8ec8976719f2", stage5Locale)}
                        value={`${number.format(summary.outstanding_refund_count)} · ${money.format(summary.outstanding_refund_amount)}`}
                        icon={ReceiptText}
                        tone={
                            summary.outstanding_refund_count > 0
                                ? 'warning'
                                : 'neutral'
                        }
                        compact
                    />
                    <MetricCard
                        label={stage5Translate("stage5.ui.abbefd3a4ddf", stage5Locale)}
                        value={number.format(summary.open_cash_session_count)}
                        icon={ShieldCheck}
                    />
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage5Text k="stage5.ui.cf370b8e0710" /></CardTitle>
                        <CardDescription>
                            <Stage5Text k="stage5.ui.dd6375bb4dd1" />
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TrendChart data={trend} />
                    </CardContent>
                </Card>

                <section className="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle><Stage5Text k="stage5.ui.3e1348d4aace" /></CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.36b7aea6d400" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {paymentMethods.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    <Stage5Text k="stage5.ui.6431b3964817" />
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
                                                    <Stage5Text k="stage5.ui.6cf4f5f27a49" />
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-semibold">
                                                    {money.format(
                                                        method.collections,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    <Stage5Text k="stage5.ui.9bb81c2eccbe" />{' '}
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
                                            {method.share_percent}<Stage5Text k="stage5.ui.a6c19426de43" />{' '}
                                            {money.format(method.refunds)}
                                        </p>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle><Stage5Text k="stage5.ui.a380122e07b4" /></CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.de6d1f2e8458" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {[
                                {
                                    label: stage5Translate("stage5.ui.97f1cc7b073b", stage5Locale),
                                    count: attention.requested_refunds.count,
                                    value: attention.requested_refunds.amount,
                                    href: refundsUrl('requested'),
                                },
                                {
                                    label: stage5Translate("stage5.ui.16b5949f4ae4", stage5Locale),
                                    count: attention.approved_refunds.count,
                                    value: attention.approved_refunds.amount,
                                    href: refundsUrl('approved'),
                                },
                                {
                                    label: stage5Translate("stage5.ui.fae9766497c8", stage5Locale),
                                    count: attention.overdue_receivables.count,
                                    value: attention.overdue_receivables.amount,
                                    href: '/rentals',
                                },
                                {
                                    label: stage5Translate("stage5.ui.a68d9ccad678", stage5Locale),
                                    count: attention.cash_differences.count,
                                    value: attention.cash_differences.amount,
                                },
                            ].map((item) => {
                                const content = (
                                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {stage5Display(item.label, stage5Locale)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {number.format(item.count)} <Stage5Text k="stage5.ui.3a7d9767b123" />
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
                                            <Stage5Text k="stage5.ui.3831e8eff775" />
                                        </p>
                                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                            {integrityAlerts === 0
                                                ? stage5Choice('PASS — seluruh pembayaran dan pengembalian dana tunai memiliki buku kas.', 'PASS — all cash payments and refunds have cash ledger entries.', stage5Locale)
                                                : `${stage5Choice(`${integrityAlerts} transaksi tunai tidak memiliki sumber buku kas.`, `${integrityAlerts} cash transactions are missing a source ledger.`, stage5Locale)}`}
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
                            <CardTitle><Stage5Text k="stage5.ui.c1f902bdf25d" /></CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.f87d58c2935a" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full min-w-[820px] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="pb-3 font-medium">
                                            <Stage5Text k="stage5.ui.1387475bd674" />
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            <Stage5Text k="stage5.ui.05ad114b6398" />
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            <Stage5Text k="stage5.ui.f2dd30734a6a" />
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            <Stage5Text k="stage5.ui.a421ce222c82" />
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            <Stage5Text k="stage5.ui.e17c8ad0dc2e" />
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            <Stage5Text k="stage5.ui.9bb81c2eccbe" />
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            <Stage5Text k="stage5.ui.e7615f6f4a10" />
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
                            <CardTitle><Stage5Text k="stage5.ui.721bd26651ea" /></CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.008fbb7c9686" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {sourceContexts.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    <Stage5Text k="stage5.ui.6431b3964817" />
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
                                                {stage5Display(source.label, stage5Locale)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {number.format(
                                                    source.transaction_count,
                                                )}{' '}
                                                <Stage5Text k="stage5.ui.6cf4f5f27a49" />
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-semibold">
                                                {money.format(source.net)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                <Stage5Text k="stage5.ui.f2dd30734a6a" />{' '}
                                                {money.format(source.inflow)} <Stage5Text k="stage5.ui.2f95b7333dd1" />{' '}
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
                            <CardTitle><Stage5Text k="stage5.ui.61fbacf9b515" /></CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.df2f596d351d" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {recentActivity.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    <Stage5Text k="stage5.ui.285c492fbb8e" />
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
                                                        {stage5Display(activity.status, stage5Locale)}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 truncate text-xs text-muted-foreground">
                                                    {activity.branch_code} ·{' '}
                                                    {activity.customer_name ??
                                                        stage5Choice('Tanpa pelanggan', 'No customer', stage5Locale)}{' '}
                                                    ·{' '}
                                                    {stage5Date(
                                                        new Date(
                                                            activity.occurred_at,
                                                        ),
                                                        stage5Locale,
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
                            <Stage5Text k="stage5.ui.ac1038297455" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-xs leading-5 text-muted-foreground md:grid-cols-2">
                        {Object.values(methodology).map((item) => (
                            <p key={item}>{item}</p>
                        ))}
                        <p className="md:col-span-2">
                            <Stage5Text k="stage5.ui.1125de06aadd" /> {comparison.from}{' '}
                            <Stage5Text k="stage5.ui.e28f87c89929" /> {comparison.to}.
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
