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
import {
    stage5Choice,
    stage5Display,
    Stage5Text,
    stage5Translate,
    stage5Date,
    stage5Money,
    stage5IntlLocale,
} from '@/components/stage5-text';
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
import { useAppLocale } from '@/lib/i18n';
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

const money = { format: stage5Money };

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
    const { locale: stage5Locale } = useAppLocale();
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
            <Head
                title={stage5Translate('stage5.ui.18927b067117', stage5Locale)}
            />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <Banknote className="size-6 text-primary" />
                            <Stage5Text k="stage5.ui.18927b067117" />
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                            <Stage5Text k="stage5.ui.4a5facff232f" />
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant={tab === 'expenses' ? 'default' : 'outline'}
                            onClick={() => setTab('expenses')}
                        >
                            <BookOpenCheck />{' '}
                            <Stage5Text k="stage5.ui.a0db8e68b834" />
                        </Button>
                        <Button
                            type="button"
                            variant={tab === 'cash' ? 'default' : 'outline'}
                            onClick={() => setTab('cash')}
                        >
                            <WalletCards />{' '}
                            <Stage5Text k="stage5.ui.c9d440879f01" />
                        </Button>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label={stage5Translate(
                            'stage5.ui.f22109b83333',
                            stage5Locale,
                        )}
                        value={money.format(summary.recorded_amount)}
                        detail={stage5Choice(
                            `${summary.recorded_count.toLocaleString(stage5IntlLocale(stage5Locale))} pengeluaran tercatat`,
                            `${summary.recorded_count.toLocaleString(stage5IntlLocale(stage5Locale))} recorded expenses`,
                            stage5Locale,
                        )}
                        icon={BookOpenCheck}
                        tone="warning"
                    />
                    <MetricCard
                        label={stage5Translate(
                            'stage5.ui.9059094e4350',
                            stage5Locale,
                        )}
                        value={money.format(summary.paid_amount)}
                        detail={stage5Choice(
                            `${summary.paid_count.toLocaleString(stage5IntlLocale(stage5Locale))} pengeluaran dibayar`,
                            `${summary.paid_count.toLocaleString(stage5IntlLocale(stage5Locale))} paid expenses`,
                            stage5Locale,
                        )}
                        icon={CircleDollarSign}
                        tone="danger"
                    />
                    <MetricCard
                        label={stage5Translate(
                            'stage5.ui.cf5d476cb93c',
                            stage5Locale,
                        )}
                        value={money.format(summary.cash_in)}
                        detail={stage5Choice(
                            'Arus masuk pada buku kas terfilter',
                            'Cash inflow in the filtered ledger',
                            stage5Locale,
                        )}
                        icon={WalletCards}
                        tone="success"
                    />
                    <MetricCard
                        label={stage5Translate(
                            'stage5.ui.e6b54fb57f0f',
                            stage5Locale,
                        )}
                        value={money.format(summary.cash_out)}
                        detail={stage5Choice(
                            `${summary.void_count.toLocaleString(stage5IntlLocale(stage5Locale))} pengeluaran dibatalkan sesuai filter`,
                            `${summary.void_count.toLocaleString(stage5IntlLocale(stage5Locale))} voided expenses in the filtered scope`,
                            stage5Locale,
                        )}
                        icon={Banknote}
                        tone="danger"
                    />
                </section>

                {tab === 'expenses' && permissions.manage && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage5Text k="stage5.ui.dc3c634b9e74" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                                onSubmit={submitExpense}
                            >
                                <Field
                                    label={stage5Translate(
                                        'stage5.ui.1387475bd674',
                                        stage5Locale,
                                    )}
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
                                            <SelectValue
                                                placeholder={stage5Translate(
                                                    'stage5.ui.f53404d2ddcf',
                                                    stage5Locale,
                                                )}
                                            />
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
                                    label={stage5Translate(
                                        'stage5.ui.f2b93c76303e',
                                        stage5Locale,
                                    )}
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
                                            <SelectValue
                                                placeholder={stage5Translate(
                                                    'stage5.ui.5322c62fbfeb',
                                                    stage5Locale,
                                                )}
                                            />
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
                                    label={stage5Translate(
                                        'stage5.ui.1795d163388f',
                                        stage5Locale,
                                    )}
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
                                    label={stage5Translate(
                                        'stage5.ui.08206fcf8a87',
                                        stage5Locale,
                                    )}
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
                                    label={stage5Translate(
                                        'stage5.ui.e3f18544463f',
                                        stage5Locale,
                                    )}
                                    error={form.errors.vendor_name}
                                >
                                    <Input
                                        value={form.data.vendor_name}
                                        placeholder={stage5Translate(
                                            'stage5.ui.cf048762964b',
                                            stage5Locale,
                                        )}
                                        onChange={(event) =>
                                            form.setData(
                                                'vendor_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label={stage5Translate(
                                        'stage5.ui.e2579d653a27',
                                        stage5Locale,
                                    )}
                                    error={form.errors.external_reference}
                                >
                                    <Input
                                        value={form.data.external_reference}
                                        placeholder={stage5Translate(
                                            'stage5.ui.4dc91241f058',
                                            stage5Locale,
                                        )}
                                        onChange={(event) =>
                                            form.setData(
                                                'external_reference',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label={stage5Translate(
                                        'stage5.ui.e254e37b8d68',
                                        stage5Locale,
                                    )}
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
                                    label={stage5Translate(
                                        'stage5.ui.9f09aefd0dd4',
                                        stage5Locale,
                                    )}
                                    error={form.errors.notes}
                                >
                                    <Input
                                        value={form.data.notes}
                                        placeholder={stage5Translate(
                                            'stage5.ui.a20b32e340ed',
                                            stage5Locale,
                                        )}
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
                                        <Plus />{' '}
                                        <Stage5Text k="stage5.ui.895e0ebb1197" />
                                    </Button>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        <Stage5Text k="stage5.ui.cc40daa8dbc7" />
                                    </p>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <FilterBar
                    title={stage5Choice(
                        'Filter & pencarian',
                        'Filters & search',
                        stage5Locale,
                    )}
                    description={stage5Choice(
                        'Persempit data untuk menemukan pekerjaan yang perlu ditindak.',
                        'Narrow results to find expenses and ledger entries requiring action.',
                        stage5Locale,
                    )}
                >
                    <div className="space-y-1.5 md:col-span-2">
                        <Label htmlFor="expense-search">
                            <Stage5Text k="stage5.ui.3f2275d79afb" />
                        </Label>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="expense-search"
                                className="pl-9"
                                value={search}
                                placeholder={stage5Translate(
                                    'stage5.ui.672052db0c40',
                                    stage5Locale,
                                )}
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
                        <Label>
                            <Stage5Text k="stage5.ui.bae7d5be7082" />
                        </Label>
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
                                    <Stage5Text k="stage5.ui.baa2adda4148" />
                                </SelectItem>
                                <SelectItem value="recorded">
                                    <Stage5Text k="stage5.ui.d5383ea7af4c" />
                                </SelectItem>
                                <SelectItem value="paid">
                                    <Stage5Text k="stage5.ui.dc9d4584a554" />
                                </SelectItem>
                                <SelectItem value="void">
                                    <Stage5Text k="stage5.ui.207c7c00630b" />
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label>
                            <Stage5Text k="stage5.ui.1387475bd674" />
                        </Label>
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
                                    <Stage5Text k="stage5.ui.27d30aba48a4" />
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
                        <Label>
                            <Stage5Text k="stage5.ui.b7964404a785" />
                        </Label>
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
                                    <Stage5Text k="stage5.ui.3ee43aaffaaf" />
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
                        <Label>
                            <Stage5Text k="stage5.ui.30b35bf928d5" />
                        </Label>
                        <Input
                            type="date"
                            value={filters.date_from}
                            onChange={(event) =>
                                applyFilters({ date_from: event.target.value })
                            }
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label>
                            <Stage5Text k="stage5.ui.95b58818f0a3" />
                        </Label>
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
                        <RefreshCcw /> <Stage5Text k="stage5.ui.44c57abd888a" />
                    </Button>
                </FilterBar>

                {tab === 'expenses' ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage5Text k="stage5.ui.f3bcb8ba671d" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[980px] text-sm">
                                    <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-3">
                                                <Stage5Text k="stage5.ui.a0db8e68b834" />
                                            </th>
                                            <th className="px-3 py-3">
                                                <Stage5Text k="stage5.ui.b7964404a785" />
                                            </th>
                                            <th className="px-3 py-3">
                                                <Stage5Text k="stage5.ui.d96159ff30af" />
                                            </th>
                                            <th className="px-3 py-3">
                                                <Stage5Text k="stage5.ui.1387475bd674" />
                                            </th>
                                            <th className="px-3 py-3">
                                                <Stage5Text k="stage5.ui.bae7d5be7082" />
                                            </th>
                                            <th className="px-3 py-3 text-right">
                                                <Stage5Text k="stage5.ui.1795d163388f" />
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
                                                        {stage5Date(
                                                            new Date(
                                                                expense.incurred_at,
                                                            ),
                                                            stage5Locale,
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
                                                    <Stage5Text k="stage5.ui.005d5d86f8e9" />
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
                            <CardTitle>
                                <Stage5Text k="stage5.ui.44faccfc5cb6" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-sm text-muted-foreground">
                                <Stage5Text k="stage5.ui.c6b7fb287a1c" />
                            </p>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1050px] text-sm">
                                    <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-3">
                                                <Stage5Text k="stage5.ui.05ad114b6398" />
                                            </th>
                                            <th className="px-3 py-3">
                                                <Stage5Text k="stage5.ui.5a007da5dc90" />
                                            </th>
                                            <th className="px-3 py-3">
                                                <Stage5Text k="stage5.ui.ff648afc53ef" />
                                            </th>
                                            <th className="px-3 py-3">
                                                <Stage5Text k="stage5.ui.c86c93709b3d" />
                                            </th>
                                            <th className="px-3 py-3 text-right">
                                                <Stage5Text k="stage5.ui.1795d163388f" />
                                            </th>
                                            <th className="px-3 py-3 text-right">
                                                <Stage5Text k="stage5.ui.65068eea081e" />
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
                                                            {stage5Date(
                                                                new Date(
                                                                    transaction.occurred_at,
                                                                ),
                                                                stage5Locale,
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
                                                    <Stage5Text k="stage5.ui.f166c9a49c0d" />
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
    const { locale: stage5Locale } = useAppLocale();
    const variant =
        status === 'void'
            ? 'destructive'
            : status === 'paid'
              ? 'secondary'
              : 'outline';

    return (
        <Badge variant={variant}>{stage5Display(status, stage5Locale)}</Badge>
    );
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
