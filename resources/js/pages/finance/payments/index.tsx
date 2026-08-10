import { Head, Link, router } from '@inertiajs/react';
import { RefreshCcw, Search, WalletCards } from 'lucide-react';
import { useState } from 'react';
import { PaginationLinks } from '@/components/pagination-links';
import { MetricCard } from '@/components/ui/metric-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FilterBar } from '@/components/ui/filter-bar';
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
    PaymentCenterFilters,
    PaymentCenterPayment,
    PaymentCenterSummary,
    PaymentPagination,
} from '@/types';

type Props = {
    payments: PaymentPagination;
    summary: PaymentCenterSummary;
    branches: FinanceBranch[];
    paymentMethods: FinancePaymentMethod[];
    filters: PaymentCenterFilters;
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
const sourceLabels: Record<string, string> = {
    booking: 'Booking',
    rental_checkout: 'Checkout Rental',
    rental_return: 'Pengembalian',
    transfer_expense: 'Biaya Transfer',
};
const typeLabels: Record<string, string> = {
    rental: 'Pembayaran Rental',
    deposit: 'Deposit',
    transfer_expense: 'Biaya Transfer',
};

export default function PaymentCenterIndex({
    payments,
    summary,
    branches,
    paymentMethods,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (changes: Partial<PaymentCenterFilters> = {}) => {
        router.get(
            '/finance/payments',
            {
                search,
                branch_id: filters.branch_id ?? '',
                date_from: filters.date_from,
                date_to: filters.date_to,
                payment_method_id: filters.payment_method_id ?? '',
                status: filters.status,
                source_context: filters.source_context,
                ...changes,
            },
            { preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        router.get('/finance/payments', {}, { replace: true });
    };

    return (
        <>
            <Head title="Payment Center" />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <WalletCards className="size-6 text-primary" />
                            Payment Center
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Pantau seluruh pembayaran lintas Booking, Rental,
                            Return, dan Transfer tanpa mengubah histori ledger.
                        </p>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <SummaryCard
                        title="Total tercatat"
                        amount={summary.gross_amount}
                        description={`${summary.total_count} transaksi`}
                    />
                    <SummaryCard
                        title="Tunai aktif"
                        amount={summary.cash_amount}
                        description="Net payment cash completed"
                    />
                    <SummaryCard
                        title="Non-tunai aktif"
                        amount={summary.non_cash_amount}
                        description="Net payment non-cash completed"
                    />
                    <SummaryCard
                        title="Void"
                        amount={summary.void_amount}
                        description={`${summary.void_count} transaksi dibatalkan`}
                        tone="danger"
                    />
                    <SummaryCard
                        title="Net payment"
                        amount={summary.net_amount}
                        description="Pemasukan dikurangi pengeluaran aktif"
                        tone="primary"
                    />
                </section>

                <FilterBar
                    title="Filter transaksi"
                    description="Cari payment lintas modul lalu persempit berdasarkan sumber, cabang, metode, status, dan tanggal."
                    contentClassName="md:grid-cols-2 xl:grid-cols-4"
                >
                    <div className="flex gap-2 md:col-span-2">
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    applyFilters();
                                }
                            }}
                            placeholder="Payment, booking, rental, pelanggan, referensi..."
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
                            <SelectItem value="all">Semua status</SelectItem>
                            <SelectItem value="completed">Completed</SelectItem>
                            <SelectItem value="void">Void</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.source_context || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                source_context: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Semua sumber" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua sumber</SelectItem>
                            {Object.entries(sourceLabels).map(
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
                            <SelectItem value="all">Semua cabang</SelectItem>
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
                            <SelectItem value="all">Semua metode</SelectItem>
                            {paymentMethods.map((method) => (
                                <SelectItem
                                    key={method.id}
                                    value={String(method.id)}
                                >
                                    {method.name}
                                    {method.is_active === false
                                        ? ' (nonaktif)'
                                        : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <div className="space-y-1.5">
                        <Label htmlFor="payment-date-from">Dari tanggal</Label>
                        <Input
                            id="payment-date-from"
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
                        <Label htmlFor="payment-date-to">Sampai tanggal</Label>
                        <Input
                            id="payment-date-to"
                            type="date"
                            value={filters.date_to}
                            min={filters.date_from || undefined}
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
                        onClick={resetFilters}
                    >
                        <RefreshCcw className="size-4" />
                        Reset filter
                    </Button>
                </FilterBar>

                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat payment</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[960px] text-sm">
                                <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-3">Payment</th>
                                        <th className="px-3 py-3">Sumber</th>
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
                                    {payments.data.map((payment) => {
                                        const source = sourceReference(payment);

                                        return (
                                            <tr
                                                key={payment.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-3 py-3">
                                                    <Link
                                                        href={`/finance/payments/${payment.id}`}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {payment.payment_number}
                                                    </Link>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {dateTime.format(
                                                            new Date(
                                                                payment.paid_at,
                                                            ),
                                                        )}
                                                    </p>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <p>
                                                        {sourceLabels[
                                                            payment.source_context ??
                                                                ''
                                                        ] ?? 'Legacy'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {source.label}
                                                    </p>
                                                </td>
                                                <td className="px-3 py-3">
                                                    {payment.customer?.name ??
                                                        '—'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {
                                                        payment.payment_method
                                                            .name
                                                    }
                                                </td>
                                                <td className="px-3 py-3">
                                                    {payment.branch.code}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <Badge
                                                        variant={
                                                            payment.status ===
                                                            'void'
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {payment.status ===
                                                        'void'
                                                            ? 'Void'
                                                            : 'Completed'}
                                                    </Badge>
                                                </td>
                                                <td
                                                    className={`px-3 py-3 text-right font-semibold ${
                                                        payment.status ===
                                                        'void'
                                                            ? 'text-muted-foreground line-through'
                                                            : payment.direction ===
                                                                'out'
                                                              ? 'text-red-600 dark:text-red-400'
                                                              : 'text-emerald-600 dark:text-emerald-400'
                                                    }`}
                                                >
                                                    {money.format(
                                                        signedAmount(payment),
                                                    )}
                                                    <p className="mt-1 text-xs font-normal text-muted-foreground">
                                                        {typeLabels[
                                                            payment.type
                                                        ] ?? payment.type}
                                                    </p>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {payments.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="py-12 text-center text-muted-foreground"
                                            >
                                                Tidak ada payment sesuai filter.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <PaginationLinks
                            links={payments.links}
                            from={payments.from}
                            to={payments.to}
                            total={payments.total}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function SummaryCard({
    title,
    amount,
    description,
    tone = 'default',
}: {
    title: string;
    amount: number;
    description: string;
    tone?: 'default' | 'primary' | 'danger';
}) {
    return (
        <MetricCard
            label={title}
            value={money.format(amount)}
            detail={description}
            icon={WalletCards}
            compact
            tone={tone === 'default' ? 'neutral' : tone}
        />
    );
}

function signedAmount(payment: PaymentCenterPayment): number {
    const amount = Number(payment.amount);

    return payment.direction === 'out' ? -amount : amount;
}

function sourceReference(payment: PaymentCenterPayment): { label: string } {
    if (payment.rental) {
        return { label: payment.rental.rental_number };
    }

    if (payment.booking) {
        return { label: payment.booking.booking_number };
    }

    if (payment.transfer_expense?.transfer) {
        return { label: payment.transfer_expense.transfer.transfer_number };
    }

    return { label: payment.external_reference ?? 'Tanpa referensi sumber' };
}
