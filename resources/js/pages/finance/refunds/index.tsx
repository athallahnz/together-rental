import { Head, Link, router } from '@inertiajs/react';
import { RefreshCcw, RotateCcw, Search } from 'lucide-react';
import { useState } from 'react';
import { PaginationLinks } from '@/components/pagination-links';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    FinanceBranch,
    FinancePaymentMethod,
    RefundCenterFilters,
    RefundCenterSummary,
    RefundPagination,
    RefundStatus,
} from '@/types';

type Props = {
    refunds: RefundPagination;
    summary: RefundCenterSummary;
    branches: FinanceBranch[];
    paymentMethods: FinancePaymentMethod[];
    filters: RefundCenterFilters;
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
const statusLabels: Record<RefundStatus, string> = {
    requested: 'Requested',
    approved: 'Approved',
    rejected: 'Rejected',
    paid: 'Paid',
    cancelled: 'Cancelled',
};

export default function RefundCenterIndex({
    refunds,
    summary,
    branches,
    paymentMethods,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (changes: Partial<RefundCenterFilters> = {}) => {
        router.get(
            '/finance/refunds',
            {
                search,
                branch_id: filters.branch_id ?? '',
                date_from: filters.date_from,
                date_to: filters.date_to,
                payment_method_id: filters.payment_method_id ?? '',
                status: filters.status,
                ...changes,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Refund Center" />
            <div className="space-y-6 p-4 md:p-6">
                <header>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        <RotateCcw className="size-6 text-primary" />
                        Refund Center
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Kelola pengajuan, approval, dan pembayaran refund tanpa
                        mengubah histori payment maupun sesi kas lama.
                    </p>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <SummaryCard
                        title="Total pengajuan"
                        value={String(summary.total_count)}
                        description="Sesuai filter aktif"
                    />
                    <SummaryCard
                        title="Menunggu approval"
                        value={String(summary.requested_count)}
                        description="Status requested"
                        tone="warning"
                    />
                    <SummaryCard
                        title="Siap dibayar"
                        value={String(summary.approved_count)}
                        description="Status approved"
                        tone="primary"
                    />
                    <SummaryCard
                        title="Outstanding"
                        value={money.format(summary.outstanding_amount)}
                        description="Requested + approved"
                        tone="warning"
                    />
                    <SummaryCard
                        title="Sudah dibayar"
                        value={money.format(summary.paid_amount)}
                        description={`${summary.paid_count} refund paid`}
                        tone="success"
                    />
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Filter refund</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div className="flex gap-2 md:col-span-2">
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
                                placeholder="Refund, payment, pelanggan, referensi..."
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => applyFilters()}
                            >
                                <Search className="size-4" />
                                Cari
                            </Button>
                        </div>
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                applyFilters({
                                    status: value === 'all' ? '' : value,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                                {Object.entries(statusLabels).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <Select
                            value={
                                filters.branch_id === null
                                    ? 'all'
                                    : String(filters.branch_id)
                            }
                            onValueChange={(value) =>
                                applyFilters({
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
                                        {branch.code} — {branch.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={
                                filters.payment_method_id === null
                                    ? 'all'
                                    : String(filters.payment_method_id)
                            }
                            onValueChange={(value) =>
                                applyFilters({
                                    payment_method_id:
                                        value === 'all' ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua metode" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua metode
                                </SelectItem>
                                {paymentMethods.map((method) => (
                                    <SelectItem
                                        key={method.id}
                                        value={String(method.id)}
                                    >
                                        {method.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <div className="space-y-1.5">
                            <Label htmlFor="refund-date-from">
                                Dari tanggal
                            </Label>
                            <Input
                                id="refund-date-from"
                                type="date"
                                value={filters.date_from}
                                onChange={(event) =>
                                    applyFilters({
                                        date_from: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="refund-date-to">
                                Sampai tanggal
                            </Label>
                            <Input
                                id="refund-date-to"
                                type="date"
                                min={filters.date_from || undefined}
                                value={filters.date_to}
                                onChange={(event) =>
                                    applyFilters({
                                        date_to: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setSearch('');
                                router.get(
                                    '/finance/refunds',
                                    {},
                                    { replace: true },
                                );
                            }}
                        >
                            <RefreshCcw className="size-4" />
                            Reset filter
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar refund</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[980px] text-sm">
                                <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-3">Refund</th>
                                        <th className="px-3 py-3">Payment</th>
                                        <th className="px-3 py-3">Pelanggan</th>
                                        <th className="px-3 py-3">Metode</th>
                                        <th className="px-3 py-3">Cabang</th>
                                        <th className="px-3 py-3">Status</th>
                                        <th className="px-3 py-3 text-right">
                                            Nominal
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {refunds.data.map((refund) => (
                                        <tr
                                            key={refund.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="px-3 py-3">
                                                <Link
                                                    href={`/finance/refunds/${refund.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {refund.refund_number}
                                                </Link>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {dateTime.format(
                                                        new Date(
                                                            refund.created_at,
                                                        ),
                                                    )}
                                                </p>
                                            </td>
                                            <td className="px-3 py-3">
                                                <Link
                                                    href={`/finance/payments/${refund.payment.id}`}
                                                    className="hover:underline"
                                                >
                                                    {
                                                        refund.payment
                                                            .payment_number
                                                    }
                                                </Link>
                                                <p className="text-xs text-muted-foreground capitalize">
                                                    {refund.refund_type}
                                                </p>
                                            </td>
                                            <td className="px-3 py-3">
                                                {refund.payment.customer
                                                    ?.name ?? '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                {refund.payment_method.name}
                                            </td>
                                            <td className="px-3 py-3">
                                                {refund.branch.code}
                                            </td>
                                            <td className="px-3 py-3">
                                                <StatusBadge
                                                    status={refund.status}
                                                />
                                            </td>
                                            <td className="px-3 py-3 text-right font-semibold">
                                                {money.format(
                                                    Number(refund.amount),
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {refunds.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="py-12 text-center text-muted-foreground"
                                            >
                                                Tidak ada refund sesuai filter.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <PaginationLinks
                            links={refunds.links}
                            from={refunds.from}
                            to={refunds.to}
                            total={refunds.total}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function SummaryCard({
    title,
    value,
    description,
    tone = 'default',
}: {
    title: string;
    value: string;
    description: string;
    tone?: 'default' | 'primary' | 'warning' | 'success';
}) {
    const toneClass =
        tone === 'primary'
            ? 'text-primary'
            : tone === 'warning'
              ? 'text-amber-600 dark:text-amber-400'
              : tone === 'success'
                ? 'text-emerald-600 dark:text-emerald-400'
                : '';

    return (
        <Card className="gap-3 py-5">
            <CardContent>
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {title}
                </p>
                <p className={`mt-2 text-xl font-semibold ${toneClass}`}>
                    {value}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    {description}
                </p>
            </CardContent>
        </Card>
    );
}

function StatusBadge({ status }: { status: RefundStatus }) {
    const variant =
        status === 'rejected'
            ? 'destructive'
            : status === 'cancelled'
              ? 'outline'
              : status === 'requested'
                ? 'secondary'
                : 'default';

    return <Badge variant={variant}>{statusLabels[status]}</Badge>;
}
