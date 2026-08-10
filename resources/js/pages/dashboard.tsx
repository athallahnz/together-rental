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

const numberFormatter = new Intl.NumberFormat('id-ID');
const currencyFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});
const compactCurrencyFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    notation: 'compact',
    maximumFractionDigits: 1,
});
const timeFormatter = new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
});
const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

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

const statusLabels: Record<string, string> = {
    active: 'Aktif',
    approved: 'Disetujui',
    cancelled: 'Dibatalkan',
    completed: 'Selesai',
    confirmed: 'Terkonfirmasi',
    correction_pending: 'Menunggu koreksi',
    draft: 'Draft',
    partial_return: 'Kembali sebagian',
    returned: 'Dikembalikan',
    void: 'Void',
};

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 11) {
        return 'Selamat pagi';
    }

    if (hour < 15) {
        return 'Selamat siang';
    }

    if (hour < 18) {
        return 'Selamat sore';
    }

    return 'Selamat malam';
}

function statusLabel(status: string): string {
    return (
        statusLabels[status] ??
        status
            .split('_')
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join(' ')
    );
}

function Delta({ value }: { value: number | null }) {
    if (value === null) {
        return (
            <span className="inline-flex items-center gap-1 text-xs font-medium text-sky-600 dark:text-sky-300">
                <ArrowUpRight className="size-3.5" />
                Baru periode ini
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
            {Math.abs(value).toLocaleString('id-ID')}% vs periode lalu
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
    const firstName = auth.user?.name?.trim().split(/\s+/)[0] ?? 'Tim';
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
            <Head title="Dashboard Operasional" />

            <main className="min-h-full space-y-5 p-4 md:p-6 xl:p-8">
                <section className="relative overflow-hidden rounded-2xl border border-slate-800 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.22),transparent_36%),linear-gradient(135deg,#0f172a,#111827_60%,#0b1120)] px-5 py-6 text-white shadow-xl shadow-slate-950/10 md:px-7 md:py-7">
                    <div className="absolute -right-20 -bottom-24 size-64 rounded-full border border-white/10" />
                    <div className="absolute -right-4 -bottom-12 size-40 rounded-full border border-white/10" />

                    <div className="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                        <div className="max-w-3xl">
                            <div className="mb-3 flex flex-wrap items-center gap-2">
                                <Badge className="border-white/10 bg-white/10 text-white hover:bg-white/10">
                                    <Activity className="size-3" />
                                    Command Center
                                </Badge>
                                <span className="text-xs text-slate-300">
                                    Diperbarui{' '}
                                    {dateTimeFormatter.format(
                                        new Date(generatedAt),
                                    )}
                                </span>
                            </div>
                            <p className="text-sm font-medium text-sky-300">
                                {greeting()}, {firstName}
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight md:text-3xl">
                                Apa yang perlu diselesaikan hari ini?
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-300 md:text-base">
                                Pantau booking, rental, arus kas, dan kondisi
                                aset dari satu tempat. Prioritas paling mendesak
                                sudah diurutkan untuk mempercepat alur kerja
                                tim.
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
                                        <SelectValue placeholder="Pilih cabang" />
                                    </SelectTrigger>
                                    <SelectContent align="end">
                                        {scope.allow_all_branches && (
                                            <SelectItem value="all">
                                                Semua cabang
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
                                Refresh
                            </Button>
                        </div>
                    </div>

                    <div className="relative mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-white/10 pt-4 text-xs text-slate-300">
                        <span className="inline-flex items-center gap-1.5">
                            <Building2 className="size-3.5 text-sky-300" />
                            {scope.label}
                        </span>
                        <span className="hidden size-1 rounded-full bg-slate-500 sm:block" />
                        <span>Analitik {period.label}</span>
                        <span className="hidden size-1 rounded-full bg-slate-500 sm:block" />
                        <span>Pembanding {period.comparison_label}</span>
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
                                    Aksi cepat
                                </h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Jalan pintas sesuai hak akses Anda.
                                </p>
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                            {quickActions.map((action) => {
                                const Icon = actionIcons[action.key];

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
                                            {action.title}
                                        </h3>
                                        <p className="mt-1 line-clamp-2 text-xs leading-5 text-muted-foreground">
                                            {action.description}
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
    return (
        <Card className="border-dashed">
            <CardContent className="flex flex-col items-center py-14 text-center">
                <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                    <Activity className="size-6 text-muted-foreground" />
                </div>
                <h2 className="mt-4 font-semibold">
                    Belum ada data operasional untuk ditampilkan
                </h2>
                <p className="mt-1 max-w-lg text-sm leading-6 text-muted-foreground">
                    Dashboard akan menyesuaikan isinya setelah role Anda
                    memperoleh akses ke Booking, Rental, Finance, Pelanggan,
                    atau Inventaris.
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
    return (
        <section
            aria-label="Ringkasan performa"
            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5"
        >
            {visibility.bookings && (
                <MetricCard
                    title="Booking bulan ini"
                    value={numberFormatter.format(overview.bookings_month)}
                    icon={CalendarCheck2}
                    iconClass="bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300"
                    detail={`${overview.booking_conversion_percent.toLocaleString('id-ID')}% terkonversi ke rental`}
                    footer={<Delta value={overview.bookings_change_percent} />}
                />
            )}
            {visibility.rentals && (
                <MetricCard
                    title="Rental aktif"
                    value={numberFormatter.format(overview.active_rentals)}
                    icon={ShoppingBag}
                    iconClass="bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300"
                    detail={`${numberFormatter.format(overview.due_today_rentals)} kembali hari ini`}
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
                                ? `${numberFormatter.format(overview.overdue_rentals)} terlambat`
                                : 'Tidak ada keterlambatan'}
                        </span>
                    }
                />
            )}
            {visibility.finance && (
                <MetricCard
                    title={`Arus kas neto · ${periodLabel}`}
                    value={compactCurrencyFormatter.format(
                        overview.net_revenue_month,
                    )}
                    icon={WalletCards}
                    iconClass="bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-300"
                    detail={`${currencyFormatter.format(overview.receivable_amount)} belum tertagih`}
                    footer={<Delta value={overview.revenue_change_percent} />}
                />
            )}
            {visibility.assets && (
                <MetricCard
                    title="Utilisasi unit"
                    value={`${overview.asset_utilization_percent.toLocaleString('id-ID')}%`}
                    icon={Boxes}
                    iconClass="bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-300"
                    detail={`${numberFormatter.format(overview.asset_rented)} disewa · ${numberFormatter.format(overview.asset_available)} tersedia`}
                    footer={
                        <span className="text-xs text-muted-foreground">
                            {numberFormatter.format(overview.asset_total)} unit
                            aktif terpantau
                        </span>
                    }
                />
            )}
            {visibility.customers && (
                <MetricCard
                    title="Pelanggan aktif"
                    value={numberFormatter.format(overview.customer_total)}
                    icon={UsersRound}
                    iconClass="bg-cyan-50 text-cyan-600 dark:bg-cyan-950/60 dark:text-cyan-300"
                    detail={`${numberFormatter.format(overview.customer_new_this_month)} pelanggan baru bulan ini`}
                    footer={
                        <Link
                            href="/customers"
                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                        >
                            Buka data pelanggan
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
    const total = items.reduce((sum, item) => sum + item.count, 0);

    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="border-b bg-muted/20 py-5">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CircleDollarSign className="size-4.5 text-primary" />
                            Fokus hari ini
                        </CardTitle>
                        <CardDescription className="mt-1">
                            Antrean kerja diurutkan dari prioritas tertinggi.
                        </CardDescription>
                    </div>
                    {total > 0 && (
                        <Badge variant="outline" className="bg-background">
                            {numberFormatter.format(total)} tindakan
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
                            Operasional terkendali
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Tidak ada antrean prioritas pada scope saat ini.
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
                <p className="truncate text-sm font-medium">{item.title}</p>
                <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                    {item.description}
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
    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="border-b bg-muted/20 py-5">
                <CardTitle className="flex items-center gap-2 text-base">
                    <CalendarClock className="size-4.5 text-primary" />
                    Jadwal hari ini
                </CardTitle>
                <CardDescription>
                    Pengambilan dan pengembalian unit.
                </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                {items.length === 0 ? (
                    <div className="flex flex-col items-center px-6 py-12 text-center">
                        <CalendarCheck2 className="size-9 text-muted-foreground/40" />
                        <p className="mt-3 text-sm font-medium">
                            Tidak ada jadwal hari ini
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Jadwal pickup dan return akan muncul di sini.
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
                    {pickup ? 'Pickup' : 'Return'} · {item.number}
                </p>
            </div>
            <div className="text-right">
                <p className="text-sm font-semibold tabular-nums">
                    {timeFormatter.format(new Date(item.scheduled_at))}
                </p>
                <p className="mt-0.5 text-[10px] text-muted-foreground">
                    {statusLabel(item.status)}
                </p>
            </div>
        </Link>
    );
}

function OperationalTrendChart({ data }: { data: DashboardTrendPoint[] }) {
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
                        Ritme operasional 14 hari
                    </CardTitle>
                    <CardDescription className="mt-1">
                        Jumlah booking dibuat dan rental di-checkout per hari.
                    </CardDescription>
                </div>
                <div className="hidden items-center gap-3 text-[11px] text-muted-foreground sm:flex">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-sky-500" />
                        Booking
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-indigo-500" />
                        Rental
                    </span>
                </div>
            </CardHeader>
            <CardContent className="px-3 pt-5 pb-3 md:px-5">
                {!hasData ? (
                    <div className="flex h-56 flex-col items-center justify-center text-center">
                        <Activity className="size-9 text-muted-foreground/35" />
                        <p className="mt-3 text-sm font-medium">
                            Belum ada transaksi pada 14 hari terakhir
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Tren akan terbentuk saat booking dan checkout mulai
                            tercatat.
                        </p>
                    </div>
                ) : (
                    <svg
                        viewBox={`0 0 ${width} ${height}`}
                        className="h-auto w-full overflow-visible"
                        role="img"
                        aria-label="Grafik tren booking dan rental 14 hari terakhir"
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
                                        {point.label}: {point.bookings} booking
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
                                        {point.label}: {point.rentals} rental
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
                                        {point.label}
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
    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="border-b bg-muted/20 py-5">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Boxes className="size-4.5 text-primary" />
                    Kesehatan inventaris
                </CardTitle>
                <CardDescription>
                    Distribusi status unit serialized aktif.
                </CardDescription>
            </CardHeader>
            <CardContent className="py-5">
                <div className="flex items-end justify-between">
                    <div>
                        <p className="text-3xl font-semibold tracking-tight">
                            {numberFormatter.format(total)}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Total unit dalam scope
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
                                <span className="truncate">{item.label}</span>
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
    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="flex-row items-center justify-between gap-4 border-b bg-muted/20 py-5">
                <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Building2 className="size-4.5 text-primary" />
                        Performa cabang
                    </CardTitle>
                    <CardDescription className="mt-1">
                        Perbandingan operasional pada bulan berjalan.
                    </CardDescription>
                </div>
                <Badge variant="outline" className="bg-background">
                    {rows.length} cabang
                </Badge>
            </CardHeader>
            <CardContent className="p-0">
                {rows.length === 0 ? (
                    <div className="py-12 text-center text-sm text-muted-foreground">
                        Belum ada cabang dalam scope dashboard.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[680px] text-sm">
                            <thead>
                                <tr className="border-b bg-muted/15 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                    <th className="px-5 py-3">Cabang</th>
                                    {visibility.bookings && (
                                        <th className="px-4 py-3 text-right">
                                            Booking
                                        </th>
                                    )}
                                    {visibility.rentals && (
                                        <>
                                            <th className="px-4 py-3 text-right">
                                                Rental aktif
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Terlambat
                                            </th>
                                        </>
                                    )}
                                    {visibility.assets && (
                                        <th className="px-4 py-3 text-right">
                                            Utilisasi
                                        </th>
                                    )}
                                    {visibility.finance && (
                                        <th className="px-5 py-3 text-right">
                                            Kas neto
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
                                                    {row.utilization_percent.toLocaleString(
                                                        'id-ID',
                                                    )}
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
    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-sm">
            <CardHeader className="border-b bg-muted/20 py-5">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Clock3 className="size-4.5 text-primary" />
                    Aktivitas terbaru
                </CardTitle>
                <CardDescription>
                    Transaksi terkini pada scope aktif.
                </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                {items.length === 0 ? (
                    <div className="py-12 text-center text-sm text-muted-foreground">
                        Belum ada aktivitas terbaru.
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
    const config: Record<
        DashboardRecentActivity['kind'],
        { icon: LucideIcon; label: string; className: string }
    > = {
        booking: {
            icon: CalendarCheck2,
            label: 'Booking',
            className:
                'bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300',
        },
        rental: {
            icon: ShoppingBag,
            label: 'Rental',
            className:
                'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300',
        },
        payment: {
            icon: HandCoins,
            label: 'Payment',
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
                    {current.label} · {item.number} · {statusLabel(item.status)}
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
