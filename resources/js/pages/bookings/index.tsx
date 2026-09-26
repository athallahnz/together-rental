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
import {
    Stage4Text,
    stage4Translate,
    stage4TranslateDynamic,
    stage4FormatDateTime,
    stage4ItemCount,
} from '@/components/stage4-text';
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
    const { locale: stage4Locale } = useAppLocale();

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
            <Head
                title={stage4Translate('stage4.ui.e38ea8eebe78', stage4Locale)}
            />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            <Stage4Text k="stage4.ui.f2ba8b28c155" />
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            <Stage4Text k="stage4.ui.2f266d8f6179" />
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            <Stage4Text k="stage4.ui.be99983aa074" />
                        </p>
                    </div>
                    {permissions.create && (
                        <Button asChild>
                            <Link href="/bookings/create">
                                <Plus />
                                <Stage4Text k="stage4.ui.eae4f64d5567" />
                            </Link>
                        </Button>
                    )}
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <SummaryCard
                        label={stage4Translate(
                            'stage4.ui.61cb469bb05f',
                            stage4Locale,
                        )}
                        value={summary.total}
                        icon={CalendarDays}
                    />
                    <SummaryCard
                        label={stage4Translate(
                            'stage4.ui.23d33e22acfc',
                            stage4Locale,
                        )}
                        value={summary.draft}
                        icon={Clock3}
                    />
                    <SummaryCard
                        label={stage4Translate(
                            'stage4.ui.b5f4b6bc6dec',
                            stage4Locale,
                        )}
                        value={summary.confirmed}
                        icon={CircleCheck}
                    />
                    <SummaryCard
                        label={stage4Translate(
                            'stage4.ui.73fb0a4fa8a3',
                            stage4Locale,
                        )}
                        value={summary.today}
                        icon={CalendarClock}
                    />
                    <SummaryCard
                        label={stage4Translate(
                            'stage4.ui.b45237e83022',
                            stage4Locale,
                        )}
                        value={summary.upcoming}
                        icon={CalendarDays}
                    />
                    <SummaryCard
                        label={stage4Translate(
                            'stage4.ui.488eb6459697',
                            stage4Locale,
                        )}
                        value={summary.expired}
                        icon={TimerOff}
                        attention={summary.expired > 0}
                    />
                </section>

                <FilterBar
                    title={stage4Translate(
                        'stage4.ui.1cc59e54a593',
                        stage4Locale,
                    )}
                    description={stage4Translate(
                        'stage4.ui.6cdcb2c64852',
                        stage4Locale,
                    )}
                    context={
                        <Badge variant="outline" className="w-fit">
                            <Building2 />
                            {selectedBranch?.name ??
                                stage4Translate(
                                    'stage4.ui.27d30aba48a4',
                                    stage4Locale,
                                )}
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
                                placeholder={stage4Translate(
                                    'stage4.ui.d8c824d3ee27',
                                    stage4Locale,
                                )}
                            />
                        </div>
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) => apply({ status: value })}
                        >
                            <SelectTrigger>
                                <SelectValue
                                    placeholder={stage4Translate(
                                        'stage4.ui.baa2adda4148',
                                        stage4Locale,
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    <Stage4Text k="stage4.ui.baa2adda4148" />
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
                                        {stage4TranslateDynamic(
                                            statusLabels[status],
                                            stage4Locale,
                                        )}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.period || 'all'}
                            onValueChange={(value) => apply({ period: value })}
                        >
                            <SelectTrigger>
                                <SelectValue
                                    placeholder={stage4Translate(
                                        'stage4.ui.85e181b57a56',
                                        stage4Locale,
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    <Stage4Text k="stage4.ui.e7225b6d509d" />
                                </SelectItem>
                                <SelectItem value="today">
                                    <Stage4Text k="stage4.ui.73fb0a4fa8a3" />
                                </SelectItem>
                                <SelectItem value="tomorrow">
                                    <Stage4Text k="stage4.ui.cfdbaebdc892" />
                                </SelectItem>
                                <SelectItem value="next7">
                                    <Stage4Text k="stage4.ui.b45237e83022" />
                                </SelectItem>
                                <SelectItem value="upcoming">
                                    <Stage4Text k="stage4.ui.3c5ba7839b4c" />
                                </SelectItem>
                                <SelectItem value="past">
                                    <Stage4Text k="stage4.ui.b648780b62e4" />
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.source || 'all'}
                            onValueChange={(value) => apply({ source: value })}
                        >
                            <SelectTrigger>
                                <SelectValue
                                    placeholder={stage4Translate(
                                        'stage4.ui.ff648afc53ef',
                                        stage4Locale,
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    <Stage4Text k="stage4.ui.7f8f0dfcaffd" />
                                </SelectItem>
                                <SelectItem value="counter">
                                    <Stage4Text k="stage4.ui.d4550c51b395" />
                                </SelectItem>
                                <SelectItem value="phone">
                                    <Stage4Text k="stage4.ui.396dc0e2976f" />
                                </SelectItem>
                                <SelectItem value="whatsapp">
                                    <Stage4Text k="stage4.ui.b336fc558722" />
                                </SelectItem>
                                <SelectItem value="website">
                                    <Stage4Text k="stage4.ui.2e8a57cc5c47" />
                                </SelectItem>
                                <SelectItem value="other">
                                    <Stage4Text k="stage4.ui.844f8a723473" />
                                </SelectItem>
                                <SelectItem value="direct">
                                    <Stage4Text k="stage4.ui.fd25629b8a39" />
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
                                <SelectValue
                                    placeholder={stage4Translate(
                                        'stage4.ui.27d30aba48a4',
                                        stage4Locale,
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    <Stage4Text k="stage4.ui.27d30aba48a4" />
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
                            <Stage4Text k="stage4.ui.3f2275d79afb" />
                        </Button>
                    </form>
                    {hasFilters && (
                        <Button
                            className="w-fit"
                            variant="ghost"
                            size="sm"
                            onClick={reset}
                        >
                            <X />
                            <Stage4Text k="stage4.ui.165a47f62b2d" />
                        </Button>
                    )}
                </FilterBar>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CalendarDays className="size-5" />
                            <Stage4Text k="stage4.ui.5cd285282368" />
                        </CardTitle>
                        <CardDescription>
                            <Stage4Text k="stage4.ui.77b45462de16" />
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full min-w-[980px] text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3">
                                            <Stage4Text k="stage4.ui.e38ea8eebe78" />
                                        </th>
                                        <th className="p-3">
                                            <Stage4Text k="stage4.ui.af0ab4433946" />
                                        </th>
                                        <th className="p-3">
                                            <Stage4Text k="stage4.ui.85e181b57a56" />
                                        </th>
                                        <th className="p-3">
                                            <Stage4Text k="stage4.ui.fd234457fe2a" />
                                        </th>
                                        <th className="p-3">
                                            <Stage4Text k="stage4.ui.b25928c69902" />
                                        </th>
                                        <th className="p-3">
                                            <Stage4Text k="stage4.ui.bae7d5be7082" />
                                        </th>
                                        <th className="p-3 text-right">
                                            <Stage4Text k="stage4.ui.60ad46d8cab9" />
                                        </th>
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
                                                    {stage4FormatDateTime(
                                                        new Date(
                                                            booking.starts_at,
                                                        ),
                                                        stage4Locale,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    <Stage4Text k="stage4.ui.40e47803c0dc" />{' '}
                                                    {stage4FormatDateTime(
                                                        new Date(
                                                            booking.ends_at,
                                                        ),
                                                        stage4Locale,
                                                    )}
                                                </p>
                                            </td>
                                            <td className="p-3">
                                                <p>
                                                    {stage4ItemCount(
                                                        booking.items_count ??
                                                            0,
                                                        stage4Locale,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {(booking.reservations_count ??
                                                        0) +
                                                        Number(
                                                            booking.bulk_units_count ??
                                                                0,
                                                        )}{' '}
                                                    <Stage4Text k="stage4.ui.23430ecfb355" />
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
                                                    {stage4TranslateDynamic(
                                                        statusLabels[
                                                            booking.status
                                                        ],
                                                        stage4Locale,
                                                    )}
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
                                                        <Stage4Text k="stage4.ui.7c9a7c0610c1" />
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
                                                <Stage4Text k="stage4.ui.f29239a83b11" />
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
