import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Banknote,
    BookOpenCheck,
    CircleDollarSign,
    Plus,
    RefreshCcw,
    Search,
    WalletCards,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { PaginationLinks } from '@/components/pagination-links';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FilterBar } from '@/components/ui/filter-bar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MetricCard } from '@/components/ui/metric-card';
import { RupiahInput } from '@/components/ui/rupiah-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    CashTransactionPagination,
    FinanceBranch,
    FinancePaymentMethod,
    OperationalExpenseFilters,
    OperationalExpensePagination,
    OperationalExpenseStatus,
    OperationalExpenseSummary,
} from '@/types';

type Category = {
    id: number;
    code: string;
    name: string;
    type: string;
    is_active: boolean;
};

type CashSessionOption = {
    id: number;
    cash_register_id: number;
    status: string;
    opened_at: string;
    opening_balance: string;
    register: {
        id: number;
        branch_id: number;
        code: string;
        name: string;
        branch: FinanceBranch;
    };
};

type Props = {
    expenses: OperationalExpensePagination;
    cashTransactions: CashTransactionPagination;
    summary: OperationalExpenseSummary;
    branches: FinanceBranch[];
    categories: Category[];
    paymentMethods: FinancePaymentMethod[];
    openCashSessions: CashSessionOption[];
    filters: OperationalExpenseFilters;
    permissions: {
        manage: boolean;
        pay: boolean;
        void: boolean;
    };
    defaultBranchId: number | null;
};

type ExpenseForm = {
    branch_id: number;
    financial_category_id: number;
    amount: number;
    incurred_at: string;
    vendor_name: string;
    external_reference: string;
    notes: string;
    proof: File | null;
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
const statusLabel: Record<OperationalExpenseStatus, string> = {
    recorded: 'Recorded',
    paid: 'Paid',
    void: 'Void',
};

function localDateTime(value = new Date()): string {
    const offset = value.getTimezoneOffset() * 60_000;

    return new Date(value.getTime() - offset).toISOString().slice(0, 16);
}

export default function OperationalExpenseIndex({
    expenses,
    cashTransactions,
    summary,
    branches,
    categories,
    filters,
    permissions,
    defaultBranchId,
}: Props) {
    const [tab, setTab] = useState<'expenses' | 'cash'>('expenses');
    const [search, setSearch] = useState(filters.search);
    const activeCategories = useMemo(
        () => categories.filter((category) => category.is_active),
        [categories],
    );
    const initialBranchId = defaultBranchId ?? branches[0]?.id ?? 0;
    const form = useForm<ExpenseForm>({
        branch_id: initialBranchId,
        financial_category_id: activeCategories[0]?.id ?? 0,
        amount: 0,
        incurred_at: localDateTime(),
        vendor_name: '',
        external_reference: '',
        notes: '',
        proof: null,
    });

    const submitExpense = (event: FormEvent) => {
        event.preventDefault();
        form.post('/finance/expenses', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () =>
                form.reset(
                    'amount',
                    'vendor_name',
                    'external_reference',
                    'notes',
                    'proof',
                ),
        });
    };

    const applyFilters = (changes: Partial<OperationalExpenseFilters> = {}) => {
        router.get(
            '/finance/expenses',
            {
                search,
                branch_id: filters.branch_id ?? '',
                status: filters.status,
                financial_category_id: filters.financial_category_id ?? '',
                date_from: filters.date_from,
                date_to: filters.date_to,
                ...changes,
            },
            { preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        router.get(
            '/finance/expenses',
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Expense & Cash Center" />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <Banknote className="size-6 text-primary" />
                            Expense & Cash Center
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Catat pengeluaran operasional, bayarkan melalui
                            Payment Center, dan pantau cash ledger append-only.
                            Payment tunai wajib memakai sesi kas aktif;
                            non-tunai tidak membuat transaksi kas.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant={tab === 'expenses' ? 'default' : 'outline'}
                            onClick={() => setTab('expenses')}
                        >
                            <BookOpenCheck /> Expense
                        </Button>
                        <Button
                            type="button"
                            variant={tab === 'cash' ? 'default' : 'outline'}
                            onClick={() => setTab('cash')}
                        >
                            <WalletCards /> Cash Ledger
                        </Button>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label="Belum dibayar"
                        value={money.format(summary.recorded_amount)}
                        detail={`${summary.recorded_count.toLocaleString('id-ID')} expense recorded`}
                        icon={BookOpenCheck}
                        tone="warning"
                    />
                    <MetricCard
                        label="Expense dibayar"
                        value={money.format(summary.paid_amount)}
                        detail={`${summary.paid_count.toLocaleString('id-ID')} expense paid`}
                        icon={CircleDollarSign}
                        tone="danger"
                    />
                    <MetricCard
                        label="Cash In"
                        value={money.format(summary.cash_in)}
                        detail="Arus masuk pada cash ledger terfilter"
                        icon={WalletCards}
                        tone="success"
                    />
                    <MetricCard
                        label="Cash Out"
                        value={money.format(summary.cash_out)}
                        detail={`${summary.void_count.toLocaleString('id-ID')} expense void pada lingkup filter`}
                        icon={Banknote}
                        tone="danger"
                    />
                </section>

                {tab === 'expenses' && permissions.manage && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Catat pengeluaran operasional</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                                onSubmit={submitExpense}
                            >
                                <Field
                                    label="Cabang"
                                    error={form.errors.branch_id}
                                >
                                    <Select
                                        value={String(
                                            form.data.branch_id || '',
                                        )}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'branch_id',
                                                Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih cabang" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {branches.map((branch) => (
                                                <SelectItem
                                                    key={branch.id}
                                                    value={String(branch.id)}
                                                >
                                                    {branch.code} ·{' '}
                                                    {branch.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field
                                    label="Kategori expense"
                                    error={form.errors.financial_category_id}
                                >
                                    <Select
                                        value={String(
                                            form.data.financial_category_id ||
                                                '',
                                        )}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'financial_category_id',
                                                Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih kategori" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {activeCategories.map(
                                                (category) => (
                                                    <SelectItem
                                                        key={category.id}
                                                        value={String(
                                                            category.id,
                                                        )}
                                                    >
                                                        {category.code} ·{' '}
                                                        {category.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field
                                    label="Nominal"
                                    error={form.errors.amount}
                                >
                                    <RupiahInput
                                        value={form.data.amount}
                                        onValueChange={(value) =>
                                            form.setData('amount', value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Tanggal kejadian"
                                    error={form.errors.incurred_at}
                                >
                                    <Input
                                        type="datetime-local"
                                        value={form.data.incurred_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'incurred_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Vendor / penerima"
                                    error={form.errors.vendor_name}
                                >
                                    <Input
                                        value={form.data.vendor_name}
                                        placeholder="Opsional"
                                        onChange={(event) =>
                                            form.setData(
                                                'vendor_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Referensi dokumen"
                                    error={form.errors.external_reference}
                                >
                                    <Input
                                        value={form.data.external_reference}
                                        placeholder="No. nota/invoice, opsional"
                                        onChange={(event) =>
                                            form.setData(
                                                'external_reference',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Bukti privat"
                                    error={form.errors.proof}
                                >
                                    <Input
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        onChange={(event) =>
                                            form.setData(
                                                'proof',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Catatan"
                                    error={form.errors.notes}
                                >
                                    <Input
                                        value={form.data.notes}
                                        placeholder="Keperluan pengeluaran"
                                        onChange={(event) =>
                                            form.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <div className="md:col-span-2 xl:col-span-4">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        <Plus /> Simpan sebagai Recorded
                                    </Button>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Menyimpan expense belum mengubah saldo
                                        kas. Arus keluar baru tercatat setelah
                                        expense dibayar melalui workflow
                                        Payment.
                                    </p>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <FilterBar>
                    <div className="space-y-1.5 md:col-span-2">
                        <Label htmlFor="expense-search">Cari</Label>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="expense-search"
                                className="pl-9"
                                value={search}
                                placeholder="Nomor, vendor, referensi, catatan..."
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        applyFilters({
                                            search: event.currentTarget.value,
                                        });
                                    }
                                }}
                            />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Status</Label>
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                applyFilters({
                                    status: value === 'all' ? '' : value,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                                <SelectItem value="recorded">
                                    Recorded
                                </SelectItem>
                                <SelectItem value="paid">Paid</SelectItem>
                                <SelectItem value="void">Void</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Cabang</Label>
                        <Select
                            value={
                                filters.branch_id
                                    ? String(filters.branch_id)
                                    : 'all'
                            }
                            onValueChange={(value) =>
                                applyFilters({
                                    branch_id:
                                        value === 'all' ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
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
                    </div>
                    <div className="space-y-1.5">
                        <Label>Kategori</Label>
                        <Select
                            value={
                                filters.financial_category_id
                                    ? String(filters.financial_category_id)
                                    : 'all'
                            }
                            onValueChange={(value) =>
                                applyFilters({
                                    financial_category_id:
                                        value === 'all' ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua kategori
                                </SelectItem>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.code} · {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Dari tanggal</Label>
                        <Input
                            type="date"
                            value={filters.date_from}
                            onChange={(event) =>
                                applyFilters({ date_from: event.target.value })
                            }
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label>Sampai tanggal</Label>
                        <Input
                            type="date"
                            value={filters.date_to}
                            onChange={(event) =>
                                applyFilters({ date_to: event.target.value })
                            }
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={resetFilters}
                    >
                        <RefreshCcw /> Reset
                    </Button>
                </FilterBar>

                {tab === 'expenses' ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Riwayat pengeluaran</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[980px] text-sm">
                                    <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-3">
                                                Expense
                                            </th>
                                            <th className="px-3 py-3">
                                                Kategori
                                            </th>
                                            <th className="px-3 py-3">
                                                Vendor
                                            </th>
                                            <th className="px-3 py-3">
                                                Cabang
                                            </th>
                                            <th className="px-3 py-3">
                                                Status
                                            </th>
                                            <th className="px-3 py-3 text-right">
                                                Nominal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {expenses.data.map((expense) => (
                                            <tr
                                                key={expense.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-3 py-3">
                                                    <Link
                                                        href={`/finance/expenses/${expense.id}`}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {expense.expense_number}
                                                    </Link>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {dateTime.format(
                                                            new Date(
                                                                expense.incurred_at,
                                                            ),
                                                        )}
                                                    </p>
                                                </td>
                                                <td className="px-3 py-3">
                                                    {
                                                        expense
                                                            .financial_category
                                                            .name
                                                    }
                                                </td>
                                                <td className="px-3 py-3">
                                                    {expense.vendor_name ?? '—'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {expense.branch.code}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <StatusBadge
                                                        status={expense.status}
                                                    />
                                                </td>
                                                <td className="px-3 py-3 text-right font-semibold tabular-nums">
                                                    {money.format(
                                                        Number(expense.amount),
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                        {expenses.data.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="px-3 py-10 text-center text-muted-foreground"
                                                >
                                                    Belum ada pengeluaran pada
                                                    filter ini.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <PaginationLinks
                                links={expenses.links}
                                from={expenses.from}
                                to={expenses.to}
                                total={expenses.total}
                            />
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Cash Ledger · read-only</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-sm text-muted-foreground">
                                Ledger tidak memiliki tombol tambah/edit/hapus.
                                Baris kas hanya lahir dari Payment, Refund, dan
                                workflow keuangan yang tervalidasi.
                            </p>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1050px] text-sm">
                                    <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-3">
                                                Transaksi
                                            </th>
                                            <th className="px-3 py-3">
                                                Register / Cabang
                                            </th>
                                            <th className="px-3 py-3">
                                                Sumber
                                            </th>
                                            <th className="px-3 py-3">Arah</th>
                                            <th className="px-3 py-3 text-right">
                                                Nominal
                                            </th>
                                            <th className="px-3 py-3 text-right">
                                                Saldo setelah
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cashTransactions.data.map(
                                            (transaction) => (
                                                <tr
                                                    key={transaction.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="px-3 py-3">
                                                        <div className="font-medium">
                                                            {
                                                                transaction.transaction_number
                                                            }
                                                        </div>
                                                        <div className="mt-1 text-xs text-muted-foreground">
                                                            {dateTime.format(
                                                                new Date(
                                                                    transaction.occurred_at,
                                                                ),
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        {
                                                            transaction.session
                                                                .register.code
                                                        }{' '}
                                                        ·{' '}
                                                        {
                                                            transaction.session
                                                                .register.name
                                                        }
                                                        <div className="text-xs text-muted-foreground">
                                                            {
                                                                transaction
                                                                    .session
                                                                    .register
                                                                    .branch.code
                                                            }
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        {transaction.payment ? (
                                                            <Link
                                                                href={`/finance/payments/${transaction.payment.id}`}
                                                                className="hover:underline"
                                                            >
                                                                {
                                                                    transaction
                                                                        .payment
                                                                        .payment_number
                                                                }
                                                            </Link>
                                                        ) : transaction.refund ? (
                                                            <Link
                                                                href={`/finance/refunds/${transaction.refund.id}`}
                                                                className="hover:underline"
                                                            >
                                                                {
                                                                    transaction
                                                                        .refund
                                                                        .refund_number
                                                                }
                                                            </Link>
                                                        ) : (
                                                            '—'
                                                        )}
                                                        <div className="text-xs text-muted-foreground">
                                                            {transaction.type}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        <Badge
                                                            variant={
                                                                transaction.direction ===
                                                                'in'
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                        >
                                                            {transaction.direction.toUpperCase()}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-3 py-3 text-right font-semibold tabular-nums">
                                                        {transaction.direction ===
                                                        'out'
                                                            ? '-'
                                                            : '+'}
                                                        {money.format(
                                                            Number(
                                                                transaction.amount,
                                                            ),
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-3 text-right tabular-nums">
                                                        {money.format(
                                                            Number(
                                                                transaction.balance_after,
                                                            ),
                                                        )}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                        {cashTransactions.data.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="px-3 py-10 text-center text-muted-foreground"
                                                >
                                                    Belum ada cash transaction
                                                    pada filter ini.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <PaginationLinks
                                links={cashTransactions.links}
                                from={cashTransactions.from}
                                to={cashTransactions.to}
                                total={cashTransactions.total}
                            />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function StatusBadge({ status }: { status: OperationalExpenseStatus }) {
    const variant =
        status === 'void'
            ? 'destructive'
            : status === 'paid'
              ? 'secondary'
              : 'outline';

    return <Badge variant={variant}>{statusLabel[status]}</Badge>;
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
