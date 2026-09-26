import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    ArrowUpRight,
    BellRing,
    Boxes,
    Building2,
    CalendarCheck2,
    CalendarClock,
    CheckCircle2,
    CircleDollarSign,
    Clock3,
    CreditCard,
    HandCoins,
    PackageCheck,
    PackageOpen,
    RefreshCw,
    ShoppingBag,
    TrendingDown,
    TrendingUp,
    Truck,
    UsersRound,
    WalletCards,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { MetricCard as UiMetricCard } from '@/components/ui/metric-card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { useAppLocale, translateKey } from '@/lib/i18n';
import type { AppLocale } from '@/lib/i18n';
import type { MessageKey } from '@/lib/i18n-catalog';
import { intlLocale } from '@/lib/locale-format';
import { dashboard } from '@/routes';
import type {
    DashboardAssetHealth,
    DashboardAttentionItem,
    DashboardAttentionTone,
    DashboardBranchPerformance,
    DashboardOverview,
    DashboardQuickAction,
    DashboardRecentActivity,
    DashboardScheduleItem,
    DashboardTrendPoint,
    DashboardVisibility,
    OperationalDashboardPageProps,
} from '@/types';

function createFormatters(locale: AppLocale) {
    const intl = intlLocale(locale);

    return {
        numberFormatter: new Intl.NumberFormat(intl),
        currencyFormatter: new Intl.NumberFormat(intl, {
            style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
        }),
        compactCurrencyFormatter: new Intl.NumberFormat(intl, {
            style: 'currency', currency: 'IDR', notation: 'compact', maximumFractionDigits: 1,
        }),
        timeFormatter: new Intl.DateTimeFormat(intl, {
            hour: '2-digit', minute: '2-digit',
        }),
        dateTimeFormatter: new Intl.DateTimeFormat(intl, {
            dateStyle: 'medium', timeStyle: 'short',
        }),
        shortDateFormatter: new Intl.DateTimeFormat(intl, {
            day: 'numeric', month: 'short',
        }),
    };
}

const formattersByLocale = {
    id: createFormatters('id'),
    en: createFormatters('en'),
};

function useDashboardLocale() {
    const { locale, tr, tp } = useAppLocale();

    return { locale, tr, tp, ...formattersByLocale[locale] };
}

const actionCopy: Record<DashboardQuickAction['key'], { title: MessageKey; description: MessageKey }> = {
    booking: { title: 'dashboard.action.booking.title', description: 'dashboard.action.booking.description' },
    'direct-rental': { title: 'dashboard.action.direct-rental.title', description: 'dashboard.action.direct-rental.description' },
    return: { title: 'dashboard.action.return.title', description: 'dashboard.action.return.description' },
    payment: { title: 'dashboard.action.payment.title', description: 'dashboard.action.payment.description' },
    transfer: { title: 'dashboard.action.transfer.title', description: 'dashboard.action.transfer.description' },
    'stock-opname': { title: 'dashboard.action.stock-opname.title', description: 'dashboard.action.stock-opname.description' },
    maintenance: { title: 'dashboard.action.maintenance.title', description: 'dashboard.action.maintenance.description' },
    notification: { title: 'dashboard.action.notification.title', description: 'dashboard.action.notification.description' },
};

const attentionCopy: Record<string, { title: MessageKey; description: MessageKey }> = {
    'rental-overdue': { title: 'dashboard.attention.rental-overdue.title', description: 'dashboard.attention.rental-overdue.description' },
    'rental-due-today': { title: 'dashboard.attention.rental-due-today.title', description: 'dashboard.attention.rental-due-today.description' },
    'booking-pickup': { title: 'dashboard.attention.booking-pickup.title', description: 'dashboard.attention.booking-pickup.description' },
    receivable: { title: 'dashboard.attention.receivable.title', description: 'dashboard.attention.receivable.description' },
    'refund-approval': { title: 'dashboard.attention.refund-approval.title', description: 'dashboard.attention.refund-approval.description' },
    'refund-payment': { title: 'dashboard.attention.refund-payment.title', description: 'dashboard.attention.refund-payment.description' },
    'transfer-approval': { title: 'dashboard.attention.transfer-approval.title', description: 'dashboard.attention.transfer-approval.description' },
    'transfer-dispatch': { title: 'dashboard.attention.transfer-dispatch.title', description: 'dashboard.attention.transfer-dispatch.description' },
    'maintenance-open': { title: 'dashboard.attention.maintenance-open.title', description: 'dashboard.attention.maintenance-open.description' },
    'inventory-approval': { title: 'dashboard.attention.inventory-approval.title', description: 'dashboard.attention.inventory-approval.description' },
    'critical-notification': { title: 'dashboard.attention.critical-notification.title', description: 'dashboard.attention.critical-notification.description' },
};

const assetStatusCopy: Record<string, MessageKey> = {
    available: 'dashboard.assetStatus.available',
    reserved: 'dashboard.assetStatus.reserved',
    rented: 'dashboard.assetStatus.rented',
    maintenance: 'dashboard.assetStatus.maintenance',
    in_transit: 'dashboard.assetStatus.in_transit',
    lost: 'dashboard.assetStatus.lost',
};

const actionIcons: Record<DashboardQuickAction['key'], LucideIcon> = {
    booking: CalendarCheck2,
    'direct-rental': ShoppingBag,
    return: PackageCheck,
    payment: CreditCard,
    transfer: Truck,
    'stock-opname': Boxes,
    maintenance: Wrench,
    notification: BellRing,
};

const actionStyles: Record<DashboardQuickAction['key'], string> = {
    booking: 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
    'direct-rental':
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300',
    return: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    payment:
        'bg-violet-50 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
    transfer: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-300',
    'stock-opname':
        'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    maintenance:
        'bg-orange-50 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300',
    notification:
        'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
};

const attentionStyles: Record<
    DashboardAttentionTone,
    { icon: string; count: string; dot: string }
> = {
    critical: {
        icon: 'bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-300',
        count: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300',
        dot: 'bg-rose-500',
    },
    warning: {
        icon: 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-300',
        count: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300',
        dot: 'bg-amber-500',
    },
    info: {
        icon: 'bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300',
        count: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/60 dark:text-sky-300',
        dot: 'bg-sky-500',
    },
    neutral: {
        icon: 'bg-muted text-muted-foreground',
        count: 'border-border bg-muted text-foreground',
        dot: 'bg-slate-400',
    },
};

const assetStyles: Record<string, string> = {
    available: 'bg-emerald-500',
    reserved: 'bg-sky-500',
    rented: 'bg-indigo-500',
    maintenance: 'bg-amber-500',
    in_transit: 'bg-cyan-500',
    lost: 'bg-rose-500',
};

const statusLabels: Record<string, MessageKey> = {
    active: 'dashboard.status.active',
    approved: 'dashboard.status.approved',
    cancelled: 'dashboard.status.cancelled',
    completed: 'dashboard.status.completed',
    confirmed: 'dashboard.status.confirmed',
    correction_pending: 'dashboard.status.correction_pending',
    draft: 'dashboard.status.draft',
    partial_return: 'dashboard.status.partial_return',
    returned: 'dashboard.status.returned',
    void: 'dashboard.status.void',
    paid: 'dashboard.status.paid',
    pending: 'dashboard.status.pending',
    requested: 'dashboard.status.requested',
    overdue: 'dashboard.status.overdue',
    in_progress: 'dashboard.status.in_progress',
    rejected: 'dashboard.status.rejected',
};

function greeting(locale: AppLocale): string {
    const hour = new Date().getHours();
    const key: MessageKey = hour < 11
        ? 'dashboard.greeting.morning'
        : hour < 15
          ? 'dashboard.greeting.noon'
          : hour < 18
            ? 'dashboard.greeting.afternoon'
            : 'dashboard.greeting.evening';

    return translateKey(key, locale);
}

function statusLabel(status: string, locale: AppLocale): string {
    const key = statusLabels[status];

    return key
        ? translateKey(key, locale)
        : status.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
}

function Delta({ value }: { value: number | null }) {
    const { tr, numberFormatter } = useDashboardLocale();

    if (value === null) {
        return (
            <span className="inline-flex items-center gap-1 text-xs font-medium text-sky-600 dark:text-sky-300">
                <ArrowUpRight className="size-3.5" />
                {tr('dashboard.delta.new')}
            </span>
        );
    }

    const positive = value >= 0;
    const Icon = positive ? TrendingUp : TrendingDown;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 text-xs font-medium',
                positive
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-rose-600 dark:text-rose-400',
            )}
        >
            <Icon className="size-3.5" />
            {tr('dashboard.delta.compare', { percent: numberFormatter.format(Math.abs(value)) })}
        </span>
    );
}

export default function Dashboard({
    visibility,
    overview,
    quickActions,
    attention,
    trend,
    assetHealth,
    todaySchedule,
    branchPerformance,
    recentActivity,
    branches,
    filters,
    scope,
    period,
    generatedAt,
}: OperationalDashboardPageProps) {
    const { auth } = usePage().props;
    const { locale, tr, dateTimeFormatter } = useDashboardLocale();
    const firstName = auth.user?.name?.trim().split(/\s+/)[0] ?? tr('dashboard.team');
    const hasOperationalAccess = Object.values(visibility).some(Boolean);

    const changeBranch = (value: string) => {
        router.get('/dashboard', value === 'all' ? {} : { branch_id: value }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const refresh = () => {
        router.get(
            '/dashboard',
            filters.branch_id === null ? {} : { branch_id: filters.branch_id },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={tr('dashboard.head')} />

            <main className="min-h-full space-y-5 p-4 md:p-6 xl:p-8">
                <section className="relative overflow-hidden rounded-2xl border border-slate-800 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.22),transparent_36%),linear-gradient(135deg,#0f172a,#111827_60%,#0b1120)] px-5 py-6 text-white shadow-xl shadow-slate-950/10 md:px-7 md:py-7">
                    <div className="absolute -right-20 -bottom-24 size-64 rounded-full border border-white/10" />
                    <div className="absolute -right-4 -bottom-12 size-40 rounded-full border border-white/10" />

                    <div className="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                        <div className="max-w-3xl">
                            <div className="mb-3 flex flex-wrap items-center gap-2">
                                <Badge className="border-white/10 bg-white/10 text-white hover:bg-white/10">
                                    <Activity className="size-3" />
                                    {tr('dashboard.commandCenter')}
                                </Badge>
                                <span className="text-xs text-slate-300">
                                    {tr('dashboard.updated')}{' '}
                                    {dateTimeFormatter.format(
                                        new Date(generatedAt),
                                    )}
                                </span>
                            </div>
                            <p className="text-sm font-medium text-sky-300">
                                {greeting(locale)}, {firstName}
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight md:text-3xl">
                                {tr('dashboard.hero.title')}
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-300 md:text-base">
                                {tr('dashboard.hero.description')}
                            </p>
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            {branches.length > 0 && (
                                <Select
                                    value={
                                        filters.branch_id === null
                                            ? 'all'
                                            : String(filters.branch_id)
                                    }
                                    onValueChange={changeBranch}
                                >
                                    <SelectTrigger className="h-10 w-full border-white/15 bg-white/10 text-white shadow-none hover:bg-white/15 sm:w-64 [&_svg]:text-slate-300">
                                        <Building2 className="size-4 text-sky-300" />
                                        <SelectValue placeholder={tr('dashboard.selectBranch')} />
                                    </SelectTrigger>
                                    <SelectContent align="end">
                                        {scope.allow_all_branches && (
                                            <SelectItem value="all">
                                                {tr('dashboard.allBranches')}
                                            </SelectItem>
                                        )}
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
                            )}
                            <Button
                                variant="outline"
                                className="h-10 border-white/15 bg-white/10 text-white shadow-none hover:bg-white/20 hover:text-white"
                                onClick={refresh}
                            >
                                <RefreshCw className="size-4" />
                                {tr('dashboard.refresh')}
                            </Button>
                        </div>
                    </div>

                    <div className="relative mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-white/10 pt-4 text-xs text-slate-300">
                        <span className="inline-flex items-center gap-1.5">
                            <Building2 className="size-3.5 text-sky-300" />
                            {scope.is_company_scope && filters.branch_id === null ? tr('dashboard.allAccessibleBranches') : scope.label}
                        </span>
                        <span className="hidden size-1 rounded-full bg-slate-500 sm:block" />
                        <span>{tr('dashboard.analytics', { period: period.label })}</span>
                        <span className="hidden size-1 rounded-full bg-slate-500 sm:block" />
                        <span>{tr('dashboard.comparison', { period: period.comparison_label })}</span>
                    </div>
                </section>

                {quickActions.length > 0 && (
                    <section aria-labelledby="quick-actions-title">
                        <div className="mb-3 flex items-end justify-between gap-3">
                            <div>
                                <h2
                                    id="quick-actions-title"
                                    className="text-base font-semibold tracking-tight"
                                >
                                    {tr('dashboard.quickActions')}
                                </h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {tr('dashboard.quickDescription')}
                                </p>
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                            {quickActions.map((action) => {
                                const Icon = actionIcons[action.key];
                                const copy = actionCopy[action.key];

                                return (
                                    <Link
                                        key={action.key}
                                        href={action.href}
                                        className="group rounded-xl border bg-card p-3.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-foreground/15 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <span
                                                className={cn(
                                                    'flex size-9 items-center justify-center rounded-lg',
                                                    actionStyles[action.key],
                                                )}
                                            >
                                                <Icon className="size-4.5" />
                                            </span>
                                            <ArrowUpRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                        </div>
                                        <h3 className="mt-3 text-sm font-semibold">
                                            {tr(copy.title)}
                                        </h3>
                                        <p className="mt-1 line-clamp-2 text-xs leading-5 text-muted-foreground">
                                            {tr(copy.description)}
                                        </p>
                                    </Link>
                                );
                            })}
                        </div>
                    </section>
                )}

                {!hasOperationalAccess ? (
                    <NoOperationalAccess />
                ) : (
                    <>
                        <OverviewGrid
                            visibility={visibility}
                            overview={overview}
                            periodLabel={period.label}
                        />

                        <section className="grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.75fr)]">
                            <AttentionPanel items={attention} />
                            <TodaySchedule items={todaySchedule} />
                        </section>

                        <section className="grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.75fr)]">
                            <OperationalTrendChart data={trend} />
                            {visibility.assets && (
                                <AssetHealthPanel
                                    items={assetHealth}
                                    total={overview.asset_total}
                                />
                            )}
                        </section>

                        <section className="grid gap-5 2xl:grid-cols-[minmax(0,1.4fr)_minmax(360px,0.6fr)]">
                            <BranchPerformanceTable
                                rows={branchPerformance}
                                visibility={visibility}
                            />
                            <RecentActivityPanel items={recentActivity} />
                        </section>
                    </>
                )}
            </main>
        </>
    );
}

function NoOperationalAccess() {
    const { tr } = useDashboardLocale();

    return (
        <Card className="border-dashed">
            <CardContent className="flex flex-col items-center py-14 text-center">
                <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                    <Activity className="size-6 text-muted-foreground" />
                </div>
                <h2 className="mt-4 font-semibold">
                    {tr('dashboard.noAccess.title')}
                </h2>
                <p className="mt-1 max-w-lg text-sm leading-6 text-muted-foreground">
                    {tr('dashboard.noAccess.description')}
                </p>
            </CardContent>
        </Card>
    );
}

function OverviewGrid({
    visibility,
    overview,
    periodLabel,
}: {
    visibility: DashboardVisibility;
    overview: DashboardOverview;
    periodLabel: string;
}) {
    const { tr, numberFormatter, currencyFormatter, compactCurrencyFormatter } = useDashboardLocale();

    return (
        <section
            aria-label={tr('dashboard.metrics.performance')}
            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5"
        >
            {visibility.bookings && (
                <MetricCard
                    title={tr('dashboard.metrics.bookings')}
                    value={numberFormatter.format(overview.bookings_month)}
                    icon={CalendarCheck2}
                    iconClass="bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300"
                    detail={tr('dashboard.metrics.conversion', { percent: numberFormatter.format(overview.booking_conversion_percent) })}
                    footer={<Delta value={overview.bookings_change_percent} />}
                />
            )}
            {visibility.rentals && (
                <MetricCard
                    title={tr('dashboard.metrics.activeRentals')}
                    value={numberFormatter.format(overview.active_rentals)}
                    icon={ShoppingBag}
                    iconClass="bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300"
                    detail={tr('dashboard.metrics.dueToday', { count: numberFormatter.format(overview.due_today_rentals) })}
                    footer={
                        <span
                            className={cn(
                                'text-xs font-medium',
                                overview.overdue_rentals > 0
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : 'text-emerald-600 dark:text-emerald-400',
                            )}
                        >
                            {overview.overdue_rentals > 0
                                ? tr('dashboard.metrics.overdue', { count: numberFormatter.format(overview.overdue_rentals) })
                                : tr('dashboard.metrics.noOverdue')}
                        </span>
                    }
                />
            )}
            {visibility.finance && (
                <MetricCard
                    title={tr('dashboard.metrics.netCash', { period: periodLabel })}
                    value={compactCurrencyFormatter.format(
                        overview.net_revenue_month,
                    )}
                    icon={WalletCards}
                    iconClass="bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-300"
                    detail={tr('dashboard.metrics.receivables', { amount: currencyFormatter.format(overview.receivable_amount) })}
                    footer={<Delta value={overview.revenue_change_percent} />}
                />
            )}
            {visibility.assets && (
                <MetricCard
                    title={tr('dashboard.metrics.utilization')}
                    value={`${numberFormatter.format(overview.asset_utilization_percent)}%`}
                    icon={Boxes}
                    iconClass="bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-300"
                    detail={tr('dashboard.metrics.assetSplit', { rented: numberFormatter.format(overview.asset_rented), available: numberFormatter.format(overview.asset_available) })}
                    footer={
                        <span className="text-xs text-muted-foreground">
                            {tr('dashboard.metrics.assetTotal', { count: numberFormatter.format(overview.asset_total) })}
                        </span>
                    }
                />
            )}
            {visibility.customers && (
                <MetricCard
                    title={tr('dashboard.metrics.activeCustomers')}
                    value={numberFormatter.format(overview.customer_total)}
                    icon={UsersRound}
                    iconClass="bg-cyan-50 text-cyan-600 dark:bg-cyan-950/60 dark:text-cyan-300"
                    detail={tr('dashboard.metrics.newCustomers', { count: numberFormatter.format(overview.customer_new_this_month) })}
                    footer={
                        <Link
                            href="/customers"
                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                        >
                            {tr('dashboard.metrics.openCustomers')}
                            <ArrowRight className="size-3" />
                        </Link>
                    }
                />
            )}
        </section>
    );
}

function MetricCard({
    title,
    value,
    icon: Icon,
    iconClass,
    detail,
    footer,
}: {
    title: string;
    value: string;
    icon: LucideIcon;
    iconClass: string;
    detail: string;
    footer: React.ReactNode;
}) {
    return (
        <UiMetricCard
            label={title}
            value={value}
            detail={detail}
            footer={footer}
            icon={Icon}
            iconClassName={iconClass}
            compact={value.length > 18}
        />
    );
}

function AttentionPanel({ items }: { items: DashboardAttentionItem[] }) {
    const { tr, tp } = useDashboardLocale();
    const total = items.reduce((sum, item) => sum + item.count, 0);

    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="border-b bg-muted/20 py-5">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CircleDollarSign className="size-4.5 text-primary" />
                            {tr('dashboard.focus.title')}
                        </CardTitle>
                        <CardDescription className="mt-1">
                            {tr('dashboard.focus.description')}
                        </CardDescription>
                    </div>
                    {total > 0 && (
                        <Badge variant="outline" className="bg-background">
                            {tp('dashboard.actionCount', total)}
                        </Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent className="p-0">
                {items.length === 0 ? (
                    <div className="flex flex-col items-center px-6 py-12 text-center">
                        <div className="flex size-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-300">
                            <CheckCircle2 className="size-5" />
                        </div>
                        <p className="mt-3 text-sm font-medium">
                            {tr('dashboard.focus.emptyTitle')}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {tr('dashboard.focus.emptyDescription')}
                        </p>
                    </div>
                ) : (
                    <div className="divide-y">
                        {items.map((item) => (
                            <AttentionRow key={item.key} item={item} />
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function AttentionRow({ item }: { item: DashboardAttentionItem }) {
    const { tr, numberFormatter } = useDashboardLocale();
    const copy = attentionCopy[item.key];
    const style = attentionStyles[item.tone];

    return (
        <Link
            href={item.href}
            className="group flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-muted/35 md:px-5"
        >
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-xl',
                    style.icon,
                )}
            >
                <span className={cn('size-2 rounded-full', style.dot)} />
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{copy ? tr(copy.title) : item.title}</p>
                <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                    {copy ? tr(copy.description) : item.description}
                </p>
            </div>
            <span
                className={cn(
                    'flex min-w-8 items-center justify-center rounded-full border px-2 py-1 text-xs font-semibold',
                    style.count,
                )}
            >
                {numberFormatter.format(item.count)}
            </span>
            <ArrowRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
        </Link>
    );
}

function TodaySchedule({ items }: { items: DashboardScheduleItem[] }) {
    const { tr } = useDashboardLocale();

    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="border-b bg-muted/20 py-5">
                <CardTitle className="flex items-center gap-2 text-base">
                    <CalendarClock className="size-4.5 text-primary" />
                    {tr('dashboard.schedule.title')}
                </CardTitle>
                <CardDescription>
                    {tr('dashboard.schedule.description')}
                </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                {items.length === 0 ? (
                    <div className="flex flex-col items-center px-6 py-12 text-center">
                        <CalendarCheck2 className="size-9 text-muted-foreground/40" />
                        <p className="mt-3 text-sm font-medium">
                            {tr('dashboard.schedule.emptyTitle')}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {tr('dashboard.schedule.emptyDescription')}
                        </p>
                    </div>
                ) : (
                    <div className="divide-y">
                        {items.map((item) => (
                            <ScheduleRow
                                key={`${item.kind}-${item.id}`}
                                item={item}
                            />
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ScheduleRow({ item }: { item: DashboardScheduleItem }) {
    const { locale, tr, timeFormatter } = useDashboardLocale();
    const pickup = item.kind === 'pickup';
    const Icon = pickup ? PackageOpen : PackageCheck;

    return (
        <Link
            href={item.href}
            className="group flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-muted/35"
        >
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-xl',
                    pickup
                        ? 'bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300'
                        : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-300',
                )}
            >
                <Icon className="size-4" />
            </span>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className="truncate text-sm font-medium">
                        {item.customer}
                    </p>
                    <Badge variant="outline" className="h-5 px-1.5 text-[10px]">
                        {item.branch}
                    </Badge>
                </div>
                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                    {pickup ? tr('dashboard.schedule.pickup') : tr('dashboard.schedule.return')} · {item.number}
                </p>
            </div>
            <div className="text-right">
                <p className="text-sm font-semibold tabular-nums">
                    {timeFormatter.format(new Date(item.scheduled_at))}
                </p>
                <p className="mt-0.5 text-[10px] text-muted-foreground">
                    {statusLabel(item.status, locale)}
                </p>
            </div>
        </Link>
    );
}

function OperationalTrendChart({ data }: { data: DashboardTrendPoint[] }) {
    const { tr, shortDateFormatter } = useDashboardLocale();
    const width = 760;
    const height = 240;
    const padding = { top: 20, right: 18, bottom: 38, left: 36 };
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;
    const maxValue = Math.max(
        1,
        ...data.flatMap((point) => [point.bookings, point.rentals]),
    );
    const x = (index: number) =>
        padding.left +
        (data.length <= 1
            ? plotWidth / 2
            : (index / (data.length - 1)) * plotWidth);
    const y = (value: number) =>
        padding.top + plotHeight - (value / maxValue) * plotHeight;
    const bookingPoints = data
        .map((point, index) => `${x(index)},${y(point.bookings)}`)
        .join(' ');
    const rentalPoints = data
        .map((point, index) => `${x(index)},${y(point.rentals)}`)
        .join(' ');
    const hasData = data.some(
        (point) => point.bookings > 0 || point.rentals > 0,
    );

    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="flex-row items-start justify-between gap-4 border-b bg-muted/20 py-5">
                <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Activity className="size-4.5 text-primary" />
                        {tr('dashboard.chart.title')}
                    </CardTitle>
                    <CardDescription className="mt-1">
                        {tr('dashboard.chart.description')}
                    </CardDescription>
                </div>
                <div className="hidden items-center gap-3 text-[11px] text-muted-foreground sm:flex">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-sky-500" />
                        {tr('dashboard.chart.booking')}
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-indigo-500" />
                        {tr('dashboard.chart.rental')}
                    </span>
                </div>
            </CardHeader>
            <CardContent className="px-3 pt-5 pb-3 md:px-5">
                {!hasData ? (
                    <div className="flex h-56 flex-col items-center justify-center text-center">
                        <Activity className="size-9 text-muted-foreground/35" />
                        <p className="mt-3 text-sm font-medium">
                            {tr('dashboard.chart.emptyTitle')}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {tr('dashboard.chart.emptyDescription')}
                        </p>
                    </div>
                ) : (
                    <svg
                        viewBox={`0 0 ${width} ${height}`}
                        className="h-auto w-full overflow-visible"
                        role="img"
                        aria-label={tr('dashboard.chart.aria')}
                    >
                        {[0, 0.25, 0.5, 0.75, 1].map((ratio) => {
                            const lineY = padding.top + plotHeight * ratio;
                            const label = Math.round(maxValue * (1 - ratio));

                            return (
                                <g key={ratio}>
                                    <line
                                        x1={padding.left}
                                        x2={width - padding.right}
                                        y1={lineY}
                                        y2={lineY}
                                        className="stroke-border"
                                        strokeDasharray="3 5"
                                        vectorEffect="non-scaling-stroke"
                                    />
                                    <text
                                        x={padding.left - 9}
                                        y={lineY + 3}
                                        textAnchor="end"
                                        className="fill-muted-foreground text-[9px]"
                                    >
                                        {label}
                                    </text>
                                </g>
                            );
                        })}
                        <polyline
                            points={bookingPoints}
                            className="fill-none stroke-sky-500"
                            strokeWidth="2.5"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                            vectorEffect="non-scaling-stroke"
                        />
                        <polyline
                            points={rentalPoints}
                            className="fill-none stroke-indigo-500"
                            strokeWidth="2.5"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                            vectorEffect="non-scaling-stroke"
                        />
                        {data.map((point, index) => (
                            <g key={point.date}>
                                <circle
                                    cx={x(index)}
                                    cy={y(point.bookings)}
                                    r="3"
                                    className="fill-card stroke-sky-500"
                                    strokeWidth="2"
                                    vectorEffect="non-scaling-stroke"
                                >
                                    <title>
                                        {shortDateFormatter.format(new Date(`${point.date}T12:00:00`))}: {tr('dashboard.chart.tooltipBooking', { count: point.bookings })}
                                    </title>
                                </circle>
                                <circle
                                    cx={x(index)}
                                    cy={y(point.rentals)}
                                    r="3"
                                    className="fill-card stroke-indigo-500"
                                    strokeWidth="2"
                                    vectorEffect="non-scaling-stroke"
                                >
                                    <title>
                                        {shortDateFormatter.format(new Date(`${point.date}T12:00:00`))}: {tr('dashboard.chart.tooltipRental', { count: point.rentals })}
                                    </title>
                                </circle>
                                {(index === 0 ||
                                    index === data.length - 1 ||
                                    index % 3 === 0) && (
                                    <text
                                        x={x(index)}
                                        y={height - 12}
                                        textAnchor="middle"
                                        className="fill-muted-foreground text-[9px]"
                                    >
                                        {shortDateFormatter.format(new Date(`${point.date}T12:00:00`))}
                                    </text>
                                )}
                            </g>
                        ))}
                    </svg>
                )}
            </CardContent>
        </Card>
    );
}

function AssetHealthPanel({
    items,
    total,
}: {
    items: DashboardAssetHealth[];
    total: number;
}) {
    const { tr, numberFormatter } = useDashboardLocale();

    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="border-b bg-muted/20 py-5">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Boxes className="size-4.5 text-primary" />
                    {tr('dashboard.asset.title')}
                </CardTitle>
                <CardDescription>
                    {tr('dashboard.asset.description')}
                </CardDescription>
            </CardHeader>
            <CardContent className="py-5">
                <div className="flex items-end justify-between">
                    <div>
                        <p className="text-3xl font-semibold tracking-tight">
                            {numberFormatter.format(total)}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {tr('dashboard.asset.total')}
                        </p>
                    </div>
                    <PackageCheck className="size-8 text-muted-foreground/35" />
                </div>

                <div className="mt-5 flex h-2.5 overflow-hidden rounded-full bg-muted">
                    {items
                        .filter((item) => item.count > 0)
                        .map((item) => (
                            <span
                                key={item.key}
                                className={cn(
                                    'h-full min-w-1',
                                    assetStyles[item.key] ?? 'bg-slate-400',
                                )}
                                style={{
                                    width:
                                        total === 0
                                            ? '0%'
                                            : `${(item.count / total) * 100}%`,
                                }}
                            />
                        ))}
                </div>

                <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                    {items.map((item) => (
                        <div
                            key={item.key}
                            className="flex items-center justify-between gap-3"
                        >
                            <span className="flex min-w-0 items-center gap-2 text-xs text-muted-foreground">
                                <span
                                    className={cn(
                                        'size-2 shrink-0 rounded-full',
                                        assetStyles[item.key] ?? 'bg-slate-400',
                                    )}
                                />
                                <span className="truncate">{assetStatusCopy[item.key] ? tr(assetStatusCopy[item.key]) : item.label}</span>
                            </span>
                            <span className="text-xs font-semibold tabular-nums">
                                {numberFormatter.format(item.count)}
                            </span>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function BranchPerformanceTable({
    rows,
    visibility,
}: {
    rows: DashboardBranchPerformance[];
    visibility: DashboardVisibility;
}) {
    const { tr, tp, numberFormatter, compactCurrencyFormatter } = useDashboardLocale();

    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="flex-row items-center justify-between gap-4 border-b bg-muted/20 py-5">
                <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Building2 className="size-4.5 text-primary" />
                        {tr('dashboard.branch.title')}
                    </CardTitle>
                    <CardDescription className="mt-1">
                        {tr('dashboard.branch.description')}
                    </CardDescription>
                </div>
                <Badge variant="outline" className="bg-background">
                    {tp('dashboard.branchCount', rows.length)}
                </Badge>
            </CardHeader>
            <CardContent className="p-0">
                {rows.length === 0 ? (
                    <div className="py-12 text-center text-sm text-muted-foreground">
                        {tr('dashboard.branch.empty')}
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[680px] text-sm">
                            <thead>
                                <tr className="border-b bg-muted/15 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                    <th className="px-5 py-3">{tr('dashboard.branch.column')}</th>
                                    {visibility.bookings && (
                                        <th className="px-4 py-3 text-right">
                                            {tr('dashboard.chart.booking')}
                                        </th>
                                    )}
                                    {visibility.rentals && (
                                        <>
                                            <th className="px-4 py-3 text-right">
                                                {tr('dashboard.metrics.activeRentals')}
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                {tr('dashboard.branch.overdue')}
                                            </th>
                                        </>
                                    )}
                                    {visibility.assets && (
                                        <th className="px-4 py-3 text-right">
                                            {tr('dashboard.branch.utilization')}
                                        </th>
                                    )}
                                    {visibility.finance && (
                                        <th className="px-5 py-3 text-right">
                                            {tr('dashboard.branch.netCash')}
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="transition-colors hover:bg-muted/20"
                                    >
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border bg-muted/30 font-mono text-[10px] font-semibold">
                                                    {row.code.slice(0, 4)}
                                                </span>
                                                <span className="font-medium">
                                                    {row.name}
                                                </span>
                                            </div>
                                        </td>
                                        {visibility.bookings && (
                                            <td className="px-4 py-4 text-right font-medium tabular-nums">
                                                {numberFormatter.format(
                                                    row.bookings,
                                                )}
                                            </td>
                                        )}
                                        {visibility.rentals && (
                                            <>
                                                <td className="px-4 py-4 text-right font-medium tabular-nums">
                                                    {numberFormatter.format(
                                                        row.active_rentals,
                                                    )}
                                                </td>
                                                <td
                                                    className={cn(
                                                        'px-4 py-4 text-right font-medium tabular-nums',
                                                        row.overdue_rentals >
                                                            0 &&
                                                            'text-rose-600 dark:text-rose-400',
                                                    )}
                                                >
                                                    {numberFormatter.format(
                                                        row.overdue_rentals,
                                                    )}
                                                </td>
                                            </>
                                        )}
                                        {visibility.assets && (
                                            <td className="px-4 py-4 text-right">
                                                <span className="inline-flex min-w-14 justify-center rounded-full bg-muted px-2 py-1 text-xs font-semibold tabular-nums">
                                                    {numberFormatter.format(row.utilization_percent)}
                                                    %
                                                </span>
                                            </td>
                                        )}
                                        {visibility.finance && (
                                            <td className="px-5 py-4 text-right font-semibold tabular-nums">
                                                {compactCurrencyFormatter.format(
                                                    row.revenue,
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function RecentActivityPanel({ items }: { items: DashboardRecentActivity[] }) {
    const { tr } = useDashboardLocale();

    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="border-b bg-muted/20 py-5">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Clock3 className="size-4.5 text-primary" />
                    {tr('dashboard.recent.title')}
                </CardTitle>
                <CardDescription>
                    {tr('dashboard.recent.description')}
                </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                {items.length === 0 ? (
                    <div className="py-12 text-center text-sm text-muted-foreground">
                        {tr('dashboard.recent.empty')}
                    </div>
                ) : (
                    <div className="divide-y">
                        {items.map((item) => (
                            <ActivityRow
                                key={`${item.kind}-${item.id}`}
                                item={item}
                            />
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ActivityRow({ item }: { item: DashboardRecentActivity }) {
    const { locale, tr, compactCurrencyFormatter, dateTimeFormatter } = useDashboardLocale();
    const config: Record<
        DashboardRecentActivity['kind'],
        { icon: LucideIcon; label: string; className: string }
    > = {
        booking: {
            icon: CalendarCheck2,
            label: tr('dashboard.chart.booking'),
            className:
                'bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300',
        },
        rental: {
            icon: ShoppingBag,
            label: tr('dashboard.chart.rental'),
            className:
                'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300',
        },
        payment: {
            icon: HandCoins,
            label: tr('dashboard.recent.payment'),
            className:
                'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-300',
        },
    };
    const current = config[item.kind];
    const Icon = current.icon;

    return (
        <Link
            href={item.href}
            className="group flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-muted/35"
        >
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-xl',
                    current.className,
                )}
            >
                <Icon className="size-4" />
            </span>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className="truncate text-sm font-medium">
                        {item.customer ?? current.label}
                    </p>
                    <span className="text-[10px] text-muted-foreground">
                        {item.branch}
                    </span>
                </div>
                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                    {current.label} · {item.number} · {statusLabel(item.status, locale)}
                </p>
            </div>
            <div className="text-right">
                <p className="text-xs font-semibold tabular-nums">
                    {compactCurrencyFormatter.format(item.amount)}
                </p>
                <p className="mt-0.5 text-[10px] text-muted-foreground">
                    {dateTimeFormatter.format(new Date(item.occurred_at))}
                </p>
            </div>
        </Link>
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
