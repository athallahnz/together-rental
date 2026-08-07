import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type {
    AssetCalendarEvent,
    AssetCalendarStatus,
    InternalAssetCalendarPayload,
} from '@/types';

type AssetUnit = {
    id: number;
    asset_code: string;
    serial_number: string | null;
    status: string;
    condition: string;
    is_active: boolean;
    current_branch?: { id: number; code: string; name: string } | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    asset: AssetUnit | null;
};

const statusMeta: Record<
    AssetCalendarStatus,
    { label: string; cell: string; dot: string }
> = {
    booked: {
        label: 'Terbooking',
        cell: 'border-sky-200 bg-sky-50 dark:border-sky-900 dark:bg-sky-950/30',
        dot: 'bg-sky-500',
    },
    rented: {
        label: 'Tersewa',
        cell: 'border-violet-200 bg-violet-50 dark:border-violet-900 dark:bg-violet-950/30',
        dot: 'bg-violet-500',
    },
    maintenance: {
        label: 'Maintenance',
        cell: 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30',
        dot: 'bg-amber-500',
    },
    in_transit: {
        label: 'Dalam transfer',
        cell: 'border-cyan-200 bg-cyan-50 dark:border-cyan-900 dark:bg-cyan-950/30',
        dot: 'bg-cyan-500',
    },
};

const priority: AssetCalendarStatus[] = [
    'maintenance',
    'rented',
    'booked',
    'in_transit',
];

export function AssetSmartCalendarDialog({ open, onOpenChange, asset }: Props) {
    const [month, setMonth] = useState(currentMonth());
    const [data, setData] = useState<InternalAssetCalendarPayload | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!asset || !open) {
            return;
        }

        const controller = new AbortController();

        void fetch(
            `/catalog/assets/${asset.id}/calendar?month=${encodeURIComponent(month)}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Kalender aset tidak dapat dimuat.');
                }

                const payload = (await response.json()) as {
                    data: InternalAssetCalendarPayload;
                };

                if (!controller.signal.aborted) {
                    setData(payload.data);
                }
            })
            .catch((reason: unknown) => {
                if (controller.signal.aborted) {
                    return;
                }

                setError(
                    reason instanceof Error
                        ? reason.message
                        : 'Kalender aset tidak dapat dimuat.',
                );
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [asset, month, open]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <CalendarDays className="size-5" />
                        Smart Calendar · {asset?.asset_code ?? 'Aset'}
                    </DialogTitle>
                    <DialogDescription>
                        Jadwal per unit dari booking, rental, maintenance, dan
                        perpindahan antar-cabang.
                    </DialogDescription>
                </DialogHeader>

                <CalendarToolbar
                    month={month}
                    onPrevious={() => {
                        setLoading(true);
                        setError(null);
                        setMonth(shiftMonth(month, -1));
                    }}
                    onNext={() => {
                        setLoading(true);
                        setError(null);
                        setMonth(shiftMonth(month, 1));
                    }}
                />

                {loading && (
                    <div className="flex min-h-64 items-center justify-center text-sm text-muted-foreground">
                        <LoaderCircle className="mr-2 size-5 animate-spin" />
                        Memuat kalender unit...
                    </div>
                )}
                {!loading && error && (
                    <div className="rounded-xl border border-destructive/30 bg-destructive/[0.03] p-5 text-sm text-destructive">
                        {error}
                    </div>
                )}
                {!loading && data && (
                    <>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <InfoCard
                                label="Unit"
                                value={data.asset.asset_code ?? '-'}
                            />
                            <InfoCard
                                label="Status saat ini"
                                value={data.asset.status}
                            />
                            <InfoCard
                                label="Cabang operasional"
                                value={
                                    data.asset.branch
                                        ? `${data.asset.branch.code} · ${data.asset.branch.name}`
                                        : '-'
                                }
                            />
                        </div>
                        <CalendarMonth month={month} events={data.events} />
                        <EventList events={data.events} />
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

export function CalendarMonth({
    month,
    events,
}: {
    month: string;
    events: AssetCalendarEvent[];
}) {
    const days = useMemo(() => monthCells(month), [month]);

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap gap-2">
                {priority.map((status) => (
                    <Badge key={status} variant="outline" className="gap-2">
                        <span
                            className={`size-2 rounded-full ${statusMeta[status].dot}`}
                        />
                        {statusMeta[status].label}
                    </Badge>
                ))}
                <Badge variant="outline" className="gap-2">
                    <span className="size-2 rounded-full bg-emerald-500" />
                    Tersedia
                </Badge>
            </div>
            <div className="grid grid-cols-7 gap-1 text-center text-xs font-medium text-muted-foreground">
                {['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map(
                    (day) => (
                        <div key={day} className="py-2">
                            {day}
                        </div>
                    ),
                )}
            </div>
            <div className="grid grid-cols-7 gap-1">
                {days.map((day) => {
                    const dayEvents = day.inMonth
                        ? events.filter((event) =>
                              eventOverlapsDate(event, day.date),
                          )
                        : [];
                    const primary = primaryStatus(dayEvents);
                    const meta = primary ? statusMeta[primary] : null;

                    return (
                        <div
                            key={day.key}
                            className={`min-h-24 rounded-lg border p-2 ${
                                !day.inMonth
                                    ? 'border-transparent bg-muted/20 text-muted-foreground/40'
                                    : (meta?.cell ?? 'bg-background')
                            }`}
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold">
                                    {day.day}
                                </span>
                                {dayEvents.length > 1 && (
                                    <span className="text-[10px] text-muted-foreground">
                                        +{dayEvents.length}
                                    </span>
                                )}
                            </div>
                            {day.inMonth && (
                                <div className="mt-3 space-y-1">
                                    {dayEvents.slice(0, 2).map((event) => (
                                        <div
                                            key={event.id}
                                            className="flex items-center gap-1 text-[10px] leading-4"
                                        >
                                            <span
                                                className={`size-1.5 shrink-0 rounded-full ${statusMeta[event.status].dot}`}
                                            />
                                            <span className="truncate">
                                                {event.label}
                                            </span>
                                        </div>
                                    ))}
                                    {dayEvents.length === 0 && (
                                        <span className="text-[10px] text-emerald-700 dark:text-emerald-400">
                                            Tersedia
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export function CalendarToolbar({
    month,
    onPrevious,
    onNext,
}: {
    month: string;
    onPrevious: () => void;
    onNext: () => void;
}) {
    return (
        <div className="flex items-center justify-between rounded-xl border bg-muted/20 p-2">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onPrevious}
                aria-label="Bulan sebelumnya"
            >
                <ChevronLeft />
            </Button>
            <p className="font-semibold">{monthLabel(month)}</p>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onNext}
                aria-label="Bulan berikutnya"
            >
                <ChevronRight />
            </Button>
        </div>
    );
}

function EventList({ events }: { events: AssetCalendarEvent[] }) {
    if (events.length === 0) {
        return (
            <div className="rounded-xl border border-dashed p-5 text-sm text-muted-foreground">
                Tidak ada booking, rental, maintenance, atau transfer pada bulan
                ini.
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <p className="text-sm font-semibold">Detail agenda unit</p>
            {events.map((event) => (
                <div
                    key={event.id}
                    className="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div className="flex items-start gap-3">
                        <span
                            className={`mt-1.5 size-2.5 shrink-0 rounded-full ${statusMeta[event.status].dot}`}
                        />
                        <div>
                            <p className="text-sm font-medium">{event.label}</p>
                            <p className="text-xs text-muted-foreground">
                                {formatDateTime(event.starts_at)} —{' '}
                                {event.is_open_ended
                                    ? 'masih berlangsung'
                                    : formatDateTime(event.ends_at)}
                            </p>
                        </div>
                    </div>
                    {event.href && (
                        <Button size="sm" variant="outline" asChild>
                            <Link href={event.href}>Buka transaksi</Link>
                        </Button>
                    )}
                </div>
            ))}
        </div>
    );
}

function InfoCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border bg-muted/20 p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-semibold">{value}</p>
        </div>
    );
}

function primaryStatus(
    events: AssetCalendarEvent[],
): AssetCalendarStatus | null {
    return (
        priority.find((status) =>
            events.some((event) => event.status === status),
        ) ?? null
    );
}

function eventOverlapsDate(event: AssetCalendarEvent, date: Date) {
    const start = new Date(event.starts_at);
    const end = new Date(event.ends_at);
    const dayStart = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    );
    const dayEnd = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
        23,
        59,
        59,
        999,
    );

    return start <= dayEnd && end >= dayStart;
}

function monthCells(month: string) {
    const [year, monthNumber] = month.split('-').map(Number);
    const first = new Date(year, monthNumber - 1, 1);
    const mondayOffset = (first.getDay() + 6) % 7;
    const start = new Date(year, monthNumber - 1, 1 - mondayOffset);

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start);
        date.setDate(start.getDate() + index);

        return {
            key: `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`,
            date,
            day: date.getDate(),
            inMonth: date.getMonth() === monthNumber - 1,
        };
    });
}

function currentMonth() {
    const date = new Date();

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export function shiftMonth(month: string, delta: number) {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + delta, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function monthLabel(month: string) {
    const [year, monthNumber] = month.split('-').map(Number);

    return new Intl.DateTimeFormat('id-ID', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, monthNumber - 1, 1));
}

function formatDateTime(value: string) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
