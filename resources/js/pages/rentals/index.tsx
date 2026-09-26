import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CalendarClock,
    CheckCircle2,
    CircleDollarSign,
    PackageCheck,
    Plus,
    Search,
    ShoppingBag,
    TimerReset,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Stage4Text, stage4Translate, stage4TranslateDynamic, stage4FormatDateTime, stage4ItemCount } from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import { PaginationLinks } from '@/components/pagination-links';
import { FilterBar } from '@/components/ui/filter-bar';
import { MetricCard } from '@/components/ui/metric-card';
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
import type { AccessBranch, Pagination } from '@/types';

type RentalRow = {
    id: number;
    rental_number: string;
    status: string;
    checked_out_at: string;
    due_at: string;
    total_amount: string;
    balance_due: string;
    items_count: number;
    is_overdue: boolean;
    due_state: 'overdue' | 'due_today' | 'due_soon' | 'on_track' | 'closed';
    source_label: string;
    branch: AccessBranch;
    customer: {
        customer_number: string;
        name: string;
        phone: string | null;
    };
    booking?: { booking_number: string; source: string } | null;
};

type Filters = {
    search: string;
    status: string;
    operational_state: string;
    payment_state: string;
    source: string;
    checkout_period: string;
    branch_id: number | null;
};

type Props = {
    rentals: Pagination<RentalRow>;
    summary: {
        active: number;
        overdue: number;
        dueToday: number;
        partialReturn: number;
        correctionPending: number;
        closed: number;
        outstandingAmount: number;
    };
    filters: Filters;
    branches: AccessBranch[];
    permissions: { create: boolean };
};

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const statusLabel: Record<string, string> = {
    active: 'Aktif',
    partial_return: 'Pengembalian sebagian',
    correction_pending: 'Menunggu koreksi',
    returned: 'Sudah kembali',
    completed: 'Selesai',
};

function statusVariant(status: string) {
    if (status === 'active') {
        return 'default' as const;
    }

    if (status === 'partial_return' || status === 'correction_pending') {
        return 'outline' as const;
    }

    return 'secondary' as const;
}

function dueStateLabel(state: RentalRow['due_state']) {
    return {
        overdue: 'Lewat jatuh tempo',
        due_today: 'Jatuh tempo hari ini',
        due_soon: 'Jatuh tempo < 24 jam',
        on_track: 'Masih dalam jadwal',
        closed: 'Transaksi ditutup',
    }[state];
}

export default function RentalIndex({
    rentals,
    summary,
    filters,
    branches,
    permissions,
}: Props) {
    const { locale: stage4Locale } = useAppLocale();

    const [search, setSearch] = useState(filters.search);

    const apply = (next: Partial<Filters> = {}) => {
        const values = { ...filters, ...next, search };

        router.get(
            '/rentals',
            {
                search: values.search || undefined,
                status:
                    values.status && values.status !== 'all'
                        ? values.status
                        : undefined,
                operational_state:
                    values.operational_state &&
                    values.operational_state !== 'all'
                        ? values.operational_state
                        : undefined,
                payment_state:
                    values.payment_state && values.payment_state !== 'all'
                        ? values.payment_state
                        : undefined,
                source:
                    values.source && values.source !== 'all'
                        ? values.source
                        : undefined,
                checkout_period:
                    values.checkout_period && values.checkout_period !== 'all'
                        ? values.checkout_period
                        : undefined,
                branch_id: values.branch_id || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    const reset = () => {
        setSearch('');
        router.get('/rentals', {}, { replace: true });
    };
    const selectedBranch =
        branches.find((branch) => branch.id === filters.branch_id) ?? null;
    const hasFilters = Boolean(
        filters.search ||
        filters.status ||
        filters.operational_state ||
        filters.payment_state ||
        filters.source ||
        filters.checkout_period ||
        filters.branch_id,
    );

    return (
        <>
            <Head title={stage4Translate("stage4.ui.e703935c66bf", stage4Locale)} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary"><Stage4Text k="stage4.ui.b38dd60e9971" />
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold"><Stage4Text k="stage4.ui.e33d53015c42" />
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground"><Stage4Text k="stage4.ui.54c24ed70f3b" />
                        </p>
                    </div>
                    {permissions.create && (
                        <Button asChild>
                            <Link href="/rentals/direct/create">
                                <Plus /><Stage4Text k="stage4.ui.fd25629b8a39" />
                            </Link>
                        </Button>
                    )}
                </header>

                {summary.overdue > 0 && (
                    <Card className="border-destructive/30 bg-destructive/[0.03]">
                        <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex gap-3">
                                <AlertTriangle className="mt-0.5 size-5 text-destructive" />
                                <div>
                                    <p className="font-semibold">
                                        {summary.overdue}{' '}<Stage4Text k="stage4.ui.ced72ae92775" />
                                    </p>
                                    <p className="text-sm text-muted-foreground"><Stage4Text k="stage4.ui.a4b5f1171d1c" />
                                    </p>
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    apply({ operational_state: 'overdue' })
                                }
                            ><Stage4Text k="stage4.ui.b7e0532f37b4" />
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
                    <SummaryCard
                        label={stage4Translate("stage4.ui.f2c455b53bdd", stage4Locale)}
                        value={summary.active}
                        icon={ShoppingBag}
                    />
                    <SummaryCard
                        label={stage4Translate("stage4.ui.07217c77199f", stage4Locale)}
                        value={summary.overdue}
                        icon={AlertTriangle}
                        attention={summary.overdue > 0}
                    />
                    <SummaryCard
                        label={stage4Translate("stage4.ui.7363e72a1abb", stage4Locale)}
                        value={summary.dueToday}
                        icon={CalendarClock}
                    />
                    <SummaryCard
                        label={stage4Translate("stage4.ui.bcdd13c8aa84", stage4Locale)}
                        value={summary.partialReturn}
                        icon={PackageCheck}
                    />
                    <SummaryCard
                        label={stage4Translate("stage4.ui.4e2fd9a9ddeb", stage4Locale)}
                        value={summary.correctionPending}
                        icon={TimerReset}
                    />
                    <SummaryCard
                        label={stage4Translate("stage4.ui.153db5843671", stage4Locale)}
                        value={summary.closed}
                        icon={CheckCircle2}
                    />
                    <SummaryCard
                        label={stage4Translate("stage4.ui.6f4acc7bef42", stage4Locale)}
                        value={money.format(summary.outstandingAmount)}
                        icon={CircleDollarSign}
                        compact
                    />
                </section>

                <FilterBar
                    title={stage4Translate("stage4.ui.348e3295d626", stage4Locale)}
                    description={stage4Translate("stage4.ui.580126f82ca4", stage4Locale)}
                    context={
                        <Badge variant="outline" className="w-fit">
                            <Building2 />{' '}
                            {selectedBranch?.name ?? stage4Translate("stage4.ui.27d30aba48a4", stage4Locale)}
                        </Badge>
                    }
                    contentClassName="grid-cols-1"
                >
                    <form
                        className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(260px,1.5fr)_190px_210px_190px_180px_180px_230px_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply();
                        }}
                    >
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                className="pl-9"
                                placeholder={stage4Translate("stage4.ui.05bc9145293e", stage4Locale)}
                            />
                        </div>
                        <Select
                            value={filters.operational_state || 'all'}
                            onValueChange={(value) =>
                                apply({ operational_state: value })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={stage4Translate("stage4.ui.f9cd492228d5", stage4Locale)} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"><Stage4Text k="stage4.ui.dc2afe7ec281" />
                                </SelectItem>
                                <SelectItem value="active"><Stage4Text k="stage4.ui.b1c48ec1abd9" />
                                </SelectItem>
                                <SelectItem value="overdue"><Stage4Text k="stage4.ui.becb656b7939" />
                                </SelectItem>
                                <SelectItem value="due_today"><Stage4Text k="stage4.ui.7363e72a1abb" />
                                </SelectItem>
                                <SelectItem value="due_soon"><Stage4Text k="stage4.ui.564bd339bddd" />
                                </SelectItem>
                                <SelectItem value="closed"><Stage4Text k="stage4.ui.207c57b0cc2e" />
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) => apply({ status: value })}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={stage4Translate("stage4.ui.aff17f5198f2", stage4Locale)} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"><Stage4Text k="stage4.ui.baa2adda4148" />
                                </SelectItem>
                                {[
                                    'active',
                                    'partial_return',
                                    'correction_pending',
                                    'returned',
                                    'completed',
                                ].map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {stage4TranslateDynamic(statusLabel[status], stage4Locale)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.payment_state || 'all'}
                            onValueChange={(value) =>
                                apply({ payment_state: value })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={stage4Translate("stage4.ui.f0874594eb78", stage4Locale)} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"><Stage4Text k="stage4.ui.5dd2b05158d3" />
                                </SelectItem>
                                <SelectItem value="outstanding"><Stage4Text k="stage4.ui.fb8f5f8a4b8e" />
                                </SelectItem>
                                <SelectItem value="paid"><Stage4Text k="stage4.ui.e065b60384ad" /></SelectItem>
                                <SelectItem value="overpaid"><Stage4Text k="stage4.ui.0f378897a3f6" />
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.source || 'all'}
                            onValueChange={(value) => apply({ source: value })}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={stage4Translate("stage4.ui.ff648afc53ef", stage4Locale)} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"><Stage4Text k="stage4.ui.7f8f0dfcaffd" />
                                </SelectItem>
                                <SelectItem value="direct"><Stage4Text k="stage4.ui.fd25629b8a39" />
                                </SelectItem>
                                <SelectItem value="booking"><Stage4Text k="stage4.ui.e52caf59c035" />
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.checkout_period || 'all'}
                            onValueChange={(value) =>
                                apply({ checkout_period: value })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={stage4Translate("stage4.ui.3ac8e9e58c5a", stage4Locale)} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"><Stage4Text k="stage4.ui.9c09ca580623" />
                                </SelectItem>
                                <SelectItem value="today"><Stage4Text k="stage4.ui.2c6ad1441fa8" /></SelectItem>
                                <SelectItem value="last7"><Stage4Text k="stage4.ui.1dc3ae18814b" />
                                </SelectItem>
                                <SelectItem value="last30"><Stage4Text k="stage4.ui.98c14c956935" />
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.branch_id?.toString() ?? 'all'}
                            onValueChange={(value) =>
                                apply({
                                    branch_id:
                                        value === 'all' ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={stage4Translate("stage4.ui.27d30aba48a4", stage4Locale)} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"><Stage4Text k="stage4.ui.27d30aba48a4" />
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
                        <Button type="submit">
                            <Search /><Stage4Text k="stage4.ui.3f2275d79afb" />
                        </Button>
                    </form>
                    {hasFilters && (
                        <Button
                            className="w-fit"
                            variant="ghost"
                            size="sm"
                            onClick={reset}
                        >
                            <X /><Stage4Text k="stage4.ui.165a47f62b2d" />
                        </Button>
                    )}
                </FilterBar>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage4Text k="stage4.ui.c903fbea15fc" /></CardTitle>
                        <CardDescription><Stage4Text k="stage4.ui.c855053b55c1" />
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3">
                            {rentals.data.map((rental) => (
                                <div
                                    key={rental.id}
                                    className={`grid gap-4 rounded-xl border p-4 lg:grid-cols-[1.35fr_1fr_1fr_auto] lg:items-center ${
                                        rental.is_overdue
                                            ? 'border-destructive/35 bg-destructive/[0.025]'
                                            : ''
                                    }`}
                                >
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                href={`/rentals/${rental.id}`}
                                                className="font-semibold hover:underline"
                                            >
                                                {rental.rental_number}
                                            </Link>
                                            <Badge
                                                variant={statusVariant(
                                                    rental.status,
                                                )}
                                            >
                                                {stage4TranslateDynamic(rental.status, stage4Locale)}
                                            </Badge>
                                            <Badge
                                                variant={
                                                    rental.is_overdue
                                                        ? 'destructive'
                                                        : 'outline'
                                                }
                                            >
                                                {stage4TranslateDynamic(dueStateLabel(
                                                    rental.due_state,
                                                ), stage4Locale)}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {rental.customer.name} ·{' '}
                                            {rental.customer.customer_number} ·{' '}
                                            {rental.customer.phone ?? '-'}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {rental.branch.code} ·{' '}
                                            {rental.branch.name} ·{' '}
                                            {rental.source_label}
                                        </p>
                                    </div>
                                    <div className="text-sm">
                                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase"><Stage4Text k="stage4.ui.92d937165b09" />
                                        </p>
                                        <p className="mt-1"><Stage4Text k="stage4.ui.3ac8e9e58c5a" />{' '}
                                            {stage4FormatDateTime(new Date(rental.checked_out_at), stage4Locale)}
                                        </p>
                                        <p
                                            className={
                                                rental.is_overdue
                                                    ? 'mt-1 font-medium text-destructive'
                                                    : 'mt-1 text-muted-foreground'
                                            }
                                        ><Stage4Text k="stage4.ui.c43a6e25b712" />{' '}
                                            {stage4FormatDateTime(new Date(rental.due_at), stage4Locale)}
                                        </p>
                                    </div>
                                    <div className="text-sm">
                                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase"><Stage4Text k="stage4.ui.0a8204b7320f" />
                                        </p>
                                        <p className="mt-1 font-semibold">
                                            {money.format(
                                                Number(rental.total_amount),
                                            )}
                                        </p>
                                        <p
                                            className={
                                                Number(rental.balance_due) > 0
                                                    ? 'mt-1 text-amber-700 dark:text-amber-400'
                                                    : 'mt-1 text-muted-foreground'
                                            }
                                        >
                                            {Number(rental.balance_due) > 0
                                                ? `${stage4Translate("stage4.ui.b8cc324b1af5", stage4Locale)} ${money.format(Number(rental.balance_due))}`
                                                : Number(rental.balance_due) < 0
                                                  ? `${stage4Translate("stage4.ui.608c0f802588", stage4Locale)} ${money.format(Math.abs(Number(rental.balance_due)))}`
                                                  : stage4Translate("stage4.ui.e065b60384ad", stage4Locale)}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {stage4ItemCount(rental.items_count, stage4Locale)}
                                        </p>
                                    </div>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/rentals/${rental.id}`}><Stage4Text k="stage4.ui.7c9a7c0610c1" />
                                        </Link>
                                    </Button>
                                </div>
                            ))}
                            {rentals.data.length === 0 && (
                                <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"><Stage4Text k="stage4.ui.e6a2d73476bf" />
                                </div>
                            )}
                        </div>
                        <PaginationLinks
                            links={rentals.links}
                            from={rentals.from}
                            to={rentals.to}
                            total={rentals.total}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function SummaryCard({
    label,
    value,
    icon: Icon,
    attention = false,
    compact = false,
}: {
    label: string;
    value: number | string;
    icon: typeof ShoppingBag;
    attention?: boolean;
    compact?: boolean;
}) {
    return (
        <MetricCard
            label={label}
            value={value}
            icon={Icon}
            compact={compact}
            tone={attention ? 'danger' : 'neutral'}
        />
    );
}
