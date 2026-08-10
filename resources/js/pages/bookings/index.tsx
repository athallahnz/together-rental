import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    CalendarClock,
    CalendarDays,
    CircleCheck,
    Clock3,
    Plus,
    Search,
    TimerOff,
    X,
} from 'lucide-react';
import { useState } from 'react';
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
import type { AccessBranch, BookingPagination, BookingStatus } from '@/types';

type Filters = {
    search: string;
    status: string;
    source: string;
    period: string;
    branch_id: number | null;
};

type Props = {
    bookings: BookingPagination;
    summary: {
        total: number;
        draft: number;
        confirmed: number;
        today: number;
        expired: number;
        upcoming: number;
    };
    filters: Filters;
    branches: AccessBranch[];
    permissions: { create: boolean; update: boolean; cancel: boolean };
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

const statusLabels: Record<BookingStatus, string> = {
    draft: 'Draft',
    confirmed: 'Terkonfirmasi',
    converted: 'Sudah checkout',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
    expired: 'Kedaluwarsa',
};

function statusVariant(status: BookingStatus) {
    if (status === 'confirmed') {
        return 'default' as const;
    }

    if (status === 'expired' || status === 'cancelled') {
        return 'destructive' as const;
    }

    if (status === 'converted' || status === 'completed') {
        return 'secondary' as const;
    }

    return 'outline' as const;
}

export default function BookingIndex({
    bookings,
    summary,
    filters,
    branches,
    permissions,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const apply = (next: Partial<Filters> = {}) => {
        const values = { ...filters, ...next, search };

        router.get(
            '/bookings',
            {
                search: values.search || undefined,
                status:
                    values.status && values.status !== 'all'
                        ? values.status
                        : undefined,
                source:
                    values.source && values.source !== 'all'
                        ? values.source
                        : undefined,
                period:
                    values.period && values.period !== 'all'
                        ? values.period
                        : undefined,
                branch_id: values.branch_id || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const reset = () => {
        setSearch('');
        router.get('/bookings', {}, { replace: true });
    };
    const hasFilters = Boolean(
        filters.search ||
        filters.status ||
        filters.source ||
        filters.period ||
        filters.branch_id,
    );
    const selectedBranch =
        branches.find((branch) => branch.id === filters.branch_id) ?? null;

    return (
        <>
            <Head title="Booking" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Operasional rental multi-cabang
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Booking Management
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            Pantau jadwal, reservasi, booking kedaluwarsa, dan
                            kebutuhan checkout dari seluruh cabang yang dapat
                            Anda akses.
                        </p>
                    </div>
                    {permissions.create && (
                        <Button asChild>
                            <Link href="/bookings/create">
                                <Plus />
                                Booking baru
                            </Link>
                        </Button>
                    )}
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <SummaryCard
                        label="Total booking"
                        value={summary.total}
                        icon={CalendarDays}
                    />
                    <SummaryCard
                        label="Draft"
                        value={summary.draft}
                        icon={Clock3}
                    />
                    <SummaryCard
                        label="Terkonfirmasi"
                        value={summary.confirmed}
                        icon={CircleCheck}
                    />
                    <SummaryCard
                        label="Mulai hari ini"
                        value={summary.today}
                        icon={CalendarClock}
                    />
                    <SummaryCard
                        label="7 hari ke depan"
                        value={summary.upcoming}
                        icon={CalendarDays}
                    />
                    <SummaryCard
                        label="Kedaluwarsa"
                        value={summary.expired}
                        icon={TimerOff}
                        attention={summary.expired > 0}
                    />
                </section>

                <FilterBar
                    title="Pusat pencarian operasional"
                    description="Cari nomor booking, pelanggan, produk, paket, kode aset, atau serial number."
                    context={
                        <Badge variant="outline" className="w-fit">
                            <Building2 />
                            {selectedBranch?.name ?? 'Semua cabang'}
                        </Badge>
                    }
                    contentClassName="grid-cols-1"
                >
                    <form
                        className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(260px,1.4fr)_190px_210px_190px_230px_auto]"
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
                                placeholder="Booking, pelanggan, produk, aset..."
                            />
                        </div>
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) => apply({ status: value })}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                                {(
                                    [
                                        'draft',
                                        'confirmed',
                                        'converted',
                                        'completed',
                                        'cancelled',
                                        'expired',
                                    ] as BookingStatus[]
                                ).map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {statusLabels[status]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.period || 'all'}
                            onValueChange={(value) => apply({ period: value })}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Periode" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua periode
                                </SelectItem>
                                <SelectItem value="today">
                                    Mulai hari ini
                                </SelectItem>
                                <SelectItem value="tomorrow">
                                    Mulai besok
                                </SelectItem>
                                <SelectItem value="next7">
                                    7 hari ke depan
                                </SelectItem>
                                <SelectItem value="upcoming">
                                    Semua mendatang
                                </SelectItem>
                                <SelectItem value="past">
                                    Sudah lewat
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.source || 'all'}
                            onValueChange={(value) => apply({ source: value })}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Sumber" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua sumber
                                </SelectItem>
                                <SelectItem value="counter">
                                    Booking counter
                                </SelectItem>
                                <SelectItem value="phone">Telepon</SelectItem>
                                <SelectItem value="whatsapp">
                                    WhatsApp
                                </SelectItem>
                                <SelectItem value="website">Website</SelectItem>
                                <SelectItem value="other">Lainnya</SelectItem>
                                <SelectItem value="direct">
                                    Rental langsung
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
                        <Button
                            className="w-fit"
                            variant="ghost"
                            size="sm"
                            onClick={reset}
                        >
                            <X /> Reset semua filter
                        </Button>
                    )}
                </FilterBar>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CalendarDays className="size-5" />
                            Daftar booking
                        </CardTitle>
                        <CardDescription>
                            Prioritaskan booking terkonfirmasi yang akan segera
                            mulai dan booking yang perlu ditindaklanjuti.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full min-w-[980px] text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3">Booking</th>
                                        <th className="p-3">Pelanggan</th>
                                        <th className="p-3">Periode</th>
                                        <th className="p-3">
                                            Item / reservasi
                                        </th>
                                        <th className="p-3">Total</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {bookings.data.map((booking) => (
                                        <tr
                                            key={booking.id}
                                            className="border-t align-top hover:bg-muted/20"
                                        >
                                            <td className="p-3">
                                                <Link
                                                    href={`/bookings/${booking.id}`}
                                                    className="font-semibold hover:underline"
                                                >
                                                    {booking.booking_number}
                                                </Link>
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {booking.branch?.code} ·{' '}
                                                    {booking.branch?.name}
                                                </div>
                                            </td>
                                            <td className="p-3">
                                                <p className="font-medium">
                                                    {booking.customer?.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {
                                                        booking.customer
                                                            ?.customer_number
                                                    }{' '}
                                                    ·{' '}
                                                    {booking.customer?.phone ??
                                                        '-'}
                                                </p>
                                            </td>
                                            <td className="p-3">
                                                <p>
                                                    {dateTime.format(
                                                        new Date(
                                                            booking.starts_at,
                                                        ),
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    s.d.{' '}
                                                    {dateTime.format(
                                                        new Date(
                                                            booking.ends_at,
                                                        ),
                                                    )}
                                                </p>
                                            </td>
                                            <td className="p-3">
                                                <p>
                                                    {booking.items_count ?? 0}{' '}
                                                    item
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {booking.reservations_count ??
                                                        0}{' '}
                                                    unit terreservasi
                                                </p>
                                            </td>
                                            <td className="p-3 font-medium">
                                                {money.format(
                                                    Number(
                                                        booking.total_amount,
                                                    ),
                                                )}
                                            </td>
                                            <td className="p-3">
                                                <Badge
                                                    variant={statusVariant(
                                                        booking.status,
                                                    )}
                                                >
                                                    {
                                                        statusLabels[
                                                            booking.status
                                                        ]
                                                    }
                                                </Badge>
                                            </td>
                                            <td className="p-3 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/bookings/${booking.id}`}
                                                    >
                                                        Detail
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                    {bookings.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="p-10 text-center text-sm text-muted-foreground"
                                            >
                                                Tidak ada booking yang sesuai
                                                dengan filter saat ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <PaginationLinks
                            links={bookings.links}
                            from={bookings.from}
                            to={bookings.to}
                            total={bookings.total}
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
}: {
    label: string;
    value: number;
    icon: typeof CalendarDays;
    attention?: boolean;
}) {
    return (
        <MetricCard
            label={label}
            value={value}
            icon={Icon}
            tone={attention ? 'danger' : 'neutral'}
        />
    );
}
