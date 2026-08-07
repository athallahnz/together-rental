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
const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
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
            <Head title="Rental" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Operasional rental harian
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Rental Control Center
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            Pantau rental aktif, keterlambatan, sisa tagihan,
                            pengembalian sebagian, dan transaksi antar-cabang
                            dalam satu layar.
                        </p>
                    </div>
                    {permissions.create && (
                        <Button asChild>
                            <Link href="/rentals/direct/create">
                                <Plus /> Rental langsung
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
                                        {summary.overdue} rental melewati jatuh
                                        tempo
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Prioritaskan konfirmasi pengembalian
                                        atau tindak lanjut pelanggan sebelum
                                        menerima booking baru pada unit terkait.
                                    </p>
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    apply({ operational_state: 'overdue' })
                                }
                            >
                                Lihat overdue
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
                    <SummaryCard
                        label="Rental berjalan"
                        value={summary.active}
                        icon={ShoppingBag}
                    />
                    <SummaryCard
                        label="Overdue"
                        value={summary.overdue}
                        icon={AlertTriangle}
                        attention={summary.overdue > 0}
                    />
                    <SummaryCard
                        label="Jatuh tempo hari ini"
                        value={summary.dueToday}
                        icon={CalendarClock}
                    />
                    <SummaryCard
                        label="Partial return"
                        value={summary.partialReturn}
                        icon={PackageCheck}
                    />
                    <SummaryCard
                        label="Menunggu koreksi"
                        value={summary.correctionPending}
                        icon={TimerReset}
                    />
                    <SummaryCard
                        label="Transaksi ditutup"
                        value={summary.closed}
                        icon={CheckCircle2}
                    />
                    <SummaryCard
                        label="Sisa tagihan aktif"
                        value={money.format(summary.outstandingAmount)}
                        icon={CircleDollarSign}
                        compact
                    />
                </section>

                <Card className="border-primary/15">
                    <CardHeader className="pb-4">
                        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <CardTitle className="text-base">
                                    Search engine operasional
                                </CardTitle>
                                <CardDescription>
                                    Cari rental, pelanggan, booking asal,
                                    produk, kode aset, atau serial number.
                                </CardDescription>
                            </div>
                            <Badge variant="outline" className="w-fit">
                                <Building2 />{' '}
                                {selectedBranch?.name ?? 'Semua cabang'}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <form
                            className="grid gap-2 xl:grid-cols-[minmax(260px,1.5fr)_190px_210px_190px_180px_180px_230px_auto]"
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
                                    placeholder="Rental, pelanggan, booking, aset..."
                                />
                            </div>
                            <Select
                                value={filters.operational_state || 'all'}
                                onValueChange={(value) =>
                                    apply({ operational_state: value })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Kondisi harian" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua kondisi
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Aktif & belum jatuh tempo
                                    </SelectItem>
                                    <SelectItem value="overdue">
                                        Lewat jatuh tempo
                                    </SelectItem>
                                    <SelectItem value="due_today">
                                        Jatuh tempo hari ini
                                    </SelectItem>
                                    <SelectItem value="due_soon">
                                        Jatuh tempo &lt; 24 jam
                                    </SelectItem>
                                    <SelectItem value="closed">
                                        Sudah ditutup
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) =>
                                    apply({ status: value })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Status transaksi" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua status
                                    </SelectItem>
                                    {[
                                        'active',
                                        'partial_return',
                                        'correction_pending',
                                        'returned',
                                        'completed',
                                    ].map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {statusLabel[status]}
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
                                    <SelectValue placeholder="Pembayaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua pembayaran
                                    </SelectItem>
                                    <SelectItem value="outstanding">
                                        Masih ada tagihan
                                    </SelectItem>
                                    <SelectItem value="paid">Lunas</SelectItem>
                                    <SelectItem value="overpaid">
                                        Kelebihan bayar
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.source || 'all'}
                                onValueChange={(value) =>
                                    apply({ source: value })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Sumber" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua sumber
                                    </SelectItem>
                                    <SelectItem value="direct">
                                        Rental langsung
                                    </SelectItem>
                                    <SelectItem value="booking">
                                        Checkout booking
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
                                    <SelectValue placeholder="Checkout" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua checkout
                                    </SelectItem>
                                    <SelectItem value="today">
                                        Hari ini
                                    </SelectItem>
                                    <SelectItem value="last7">
                                        7 hari terakhir
                                    </SelectItem>
                                    <SelectItem value="last30">
                                        30 hari terakhir
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.branch_id?.toString() ?? 'all'}
                                onValueChange={(value) =>
                                    apply({
                                        branch_id:
                                            value === 'all'
                                                ? null
                                                : Number(value),
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Semua cabang" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua cabang
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
                                <Search />
                                Cari
                            </Button>
                        </form>
                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={reset}>
                                <X /> Reset semua filter
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar transaksi rental</CardTitle>
                        <CardDescription>
                            Rental overdue otomatis ditempatkan lebih awal agar
                            mudah ditindaklanjuti.
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
                                                {statusLabel[rental.status] ??
                                                    rental.status}
                                            </Badge>
                                            <Badge
                                                variant={
                                                    rental.is_overdue
                                                        ? 'destructive'
                                                        : 'outline'
                                                }
                                            >
                                                {dueStateLabel(
                                                    rental.due_state,
                                                )}
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
                                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Jadwal
                                        </p>
                                        <p className="mt-1">
                                            Checkout{' '}
                                            {dateTime.format(
                                                new Date(rental.checked_out_at),
                                            )}
                                        </p>
                                        <p
                                            className={
                                                rental.is_overdue
                                                    ? 'mt-1 font-medium text-destructive'
                                                    : 'mt-1 text-muted-foreground'
                                            }
                                        >
                                            Kembali{' '}
                                            {dateTime.format(
                                                new Date(rental.due_at),
                                            )}
                                        </p>
                                    </div>
                                    <div className="text-sm">
                                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Keuangan
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
                                                ? `Sisa ${money.format(Number(rental.balance_due))}`
                                                : Number(rental.balance_due) < 0
                                                  ? `Refund ${money.format(Math.abs(Number(rental.balance_due)))}`
                                                  : 'Lunas'}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {rental.items_count} item
                                        </p>
                                    </div>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/rentals/${rental.id}`}>
                                            Detail
                                        </Link>
                                    </Button>
                                </div>
                            ))}
                            {rentals.data.length === 0 && (
                                <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                                    Tidak ada rental yang sesuai dengan filter
                                    saat ini.
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
        <Card className={attention ? 'border-destructive/30' : undefined}>
            <CardContent className="flex items-center justify-between p-5">
                <div>
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {label}
                    </p>
                    <p
                        className={
                            compact
                                ? 'mt-2 text-base font-semibold'
                                : 'mt-2 text-2xl font-semibold'
                        }
                    >
                        {value}
                    </p>
                </div>
                <div className="flex size-10 items-center justify-center rounded-xl bg-muted">
                    <Icon
                        className={
                            attention
                                ? 'size-5 text-destructive'
                                : 'size-5 text-muted-foreground'
                        }
                    />
                </div>
            </CardContent>
        </Card>
    );
}
