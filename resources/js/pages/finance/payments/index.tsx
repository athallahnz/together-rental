import { Head, Link, router } from '@inertiajs/react';
import { RefreshCcw, Search, WalletCards } from 'lucide-react';
import { useState } from 'react';
import {
    stage5Choice,
    stage5Display,
    Stage5Text,
    stage5Translate,
    stage5Date,
    stage5Money,
} from '@/components/stage5-text';
import { PaginationLinks } from '@/components/pagination-links';
import { MetricCard } from '@/components/ui/metric-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FilterBar } from '@/components/ui/filter-bar';
import { useAppLocale } from '@/lib/i18n';
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

const money = { format: stage5Money };

const sourceLabels: Record<string, string> = {
    booking: 'Booking',
    rental_checkout: 'Checkout Rental',
    rental_return: 'Pengembalian',
    rental_extension: 'Perpanjangan Rental',
    transfer_expense: 'Biaya Transfer',
    operational_expense: 'Pengeluaran Operasional',
};
const typeLabels: Record<string, string> = {
    rental: 'Pembayaran Rental',
    deposit: 'Deposit',
    transfer_expense: 'Biaya Transfer',
    operational_expense: 'Pengeluaran Operasional',
};

export default function PaymentCenterIndex({
    payments,
    summary,
    branches,
    paymentMethods,
    filters,
}: Props) {
    const { locale: stage5Locale } = useAppLocale();
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
            <Head
                title={stage5Translate('stage5.ui.78a5e1538bc3', stage5Locale)}
            />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <WalletCards className="size-6 text-primary" />
                            <Stage5Text k="stage5.ui.78a5e1538bc3" />
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            <Stage5Text k="stage5.ui.fe31c38207df" />
                        </p>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <SummaryCard
                        title={stage5Translate(
                            'stage5.ui.a20f18610f7d',
                            stage5Locale,
                        )}
                        amount={summary.gross_amount}
                        description={`${summary.total_count} transaksi`}
                    />
                    <SummaryCard
                        title={stage5Translate(
                            'stage5.ui.3a15bec74e36',
                            stage5Locale,
                        )}
                        amount={summary.cash_amount}
                        description={stage5Translate(
                            'stage5.ui.ee995e5c6766',
                            stage5Locale,
                        )}
                    />
                    <SummaryCard
                        title={stage5Translate(
                            'stage5.ui.d8720c3314ba',
                            stage5Locale,
                        )}
                        amount={summary.non_cash_amount}
                        description={stage5Translate(
                            'stage5.ui.7cd0307fadd4',
                            stage5Locale,
                        )}
                    />
                    <SummaryCard
                        title={stage5Translate(
                            'stage5.ui.207c7c00630b',
                            stage5Locale,
                        )}
                        amount={summary.void_amount}
                        description={stage5Choice(
                            `${summary.void_count} transaksi dibatalkan`,
                            `${summary.void_count} voided transactions`,
                            stage5Locale,
                        )}
                        tone="danger"
                    />
                    <SummaryCard
                        title={stage5Translate(
                            'stage5.ui.c2aaa04e9659',
                            stage5Locale,
                        )}
                        amount={summary.net_amount}
                        description={stage5Translate(
                            'stage5.ui.cdf55ff98be2',
                            stage5Locale,
                        )}
                        tone="primary"
                    />
                </section>

                <FilterBar
                    title={stage5Translate(
                        'stage5.ui.bea3febe483a',
                        stage5Locale,
                    )}
                    description={stage5Translate(
                        'stage5.ui.7fd3d77a8762',
                        stage5Locale,
                    )}
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
                            placeholder={stage5Translate(
                                'stage5.ui.f82573d63fa1',
                                stage5Locale,
                            )}
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => applyFilters()}
                        >
                            <Search className="size-4" />
                            <Stage5Text k="stage5.ui.3f2275d79afb" />
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
                            <SelectValue
                                placeholder={stage5Translate(
                                    'stage5.ui.baa2adda4148',
                                    stage5Locale,
                                )}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                <Stage5Text k="stage5.ui.baa2adda4148" />
                            </SelectItem>
                            <SelectItem value="completed">
                                <Stage5Text k="stage5.ui.1798b3ba42ee" />
                            </SelectItem>
                            <SelectItem value="void">
                                <Stage5Text k="stage5.ui.207c7c00630b" />
                            </SelectItem>
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
                            <SelectValue
                                placeholder={stage5Translate(
                                    'stage5.ui.7f8f0dfcaffd',
                                    stage5Locale,
                                )}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                <Stage5Text k="stage5.ui.7f8f0dfcaffd" />
                            </SelectItem>
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
                            <SelectValue
                                placeholder={stage5Translate(
                                    'stage5.ui.27d30aba48a4',
                                    stage5Locale,
                                )}
                            />
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
                            <SelectValue
                                placeholder={stage5Translate(
                                    'stage5.ui.816684ab79ec',
                                    stage5Locale,
                                )}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                <Stage5Text k="stage5.ui.816684ab79ec" />
                            </SelectItem>
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
                        <Label htmlFor="payment-date-from">
                            <Stage5Text k="stage5.ui.30b35bf928d5" />
                        </Label>
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
                        <Label htmlFor="payment-date-to">
                            <Stage5Text k="stage5.ui.95b58818f0a3" />
                        </Label>
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
                        <Stage5Text k="stage5.ui.9c4a6ab48318" />
                    </Button>
                </FilterBar>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            <Stage5Text k="stage5.ui.24a2fe1047a8" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[960px] text-sm">
                                <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-3">
                                            <Stage5Text k="stage5.ui.b41a92bed032" />
                                        </th>
                                        <th className="px-3 py-3">
                                            <Stage5Text k="stage5.ui.ff648afc53ef" />
                                        </th>
                                        <th className="px-3 py-3">
                                            <Stage5Text k="stage5.ui.af0ab4433946" />
                                        </th>
                                        <th className="px-3 py-3">
                                            <Stage5Text k="stage5.ui.5ac33f2c588b" />
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
                                                        {stage5Date(
                                                            new Date(
                                                                payment.paid_at,
                                                            ),
                                                            stage5Locale,
                                                        )}
                                                    </p>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <p>
                                                        {stage5Display(
                                                            sourceLabels[
                                                                payment.source_context ??
                                                                    ''
                                                            ] ?? 'Legacy',
                                                            stage5Locale,
                                                        )}
                                                    </p>
                                                    {source.href ? (
                                                        <Link
                                                            href={source.href}
                                                            className="text-xs text-muted-foreground hover:underline"
                                                        >
                                                            {source.label}
                                                        </Link>
                                                    ) : (
                                                        <p className="text-xs text-muted-foreground">
                                                            {source.label}
                                                        </p>
                                                    )}
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
                                                            ? stage5Display(
                                                                  'Void',
                                                                  stage5Locale,
                                                              )
                                                            : stage5Display(
                                                                  'completed',
                                                                  stage5Locale,
                                                              )}
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
                                                        {stage5Display(
                                                            typeLabels[
                                                                payment.type
                                                            ] ?? payment.type,
                                                            stage5Locale,
                                                        )}
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
                                                <Stage5Text k="stage5.ui.3626fa444926" />
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

function sourceReference(payment: PaymentCenterPayment): {
    label: string;
    href: string | null;
} {
    if (payment.rental_extension && payment.rental) {
        return {
            label: payment.rental_extension.extension_number,
            href: `/rentals/${payment.rental.id}`,
        };
    }

    if (payment.rental) {
        return {
            label: payment.rental.rental_number,
            href: `/rentals/${payment.rental.id}`,
        };
    }

    if (payment.booking) {
        return {
            label: payment.booking.booking_number,
            href: `/bookings/${payment.booking.id}`,
        };
    }

    if (payment.operational_expense) {
        return {
            label: payment.operational_expense.expense_number,
            href: `/finance/expenses/${payment.operational_expense.id}`,
        };
    }

    if (payment.transfer_expense?.transfer) {
        return {
            label: payment.transfer_expense.transfer.transfer_number,
            href: `/transfers/${payment.transfer_expense.transfer.id}`,
        };
    }

    return {
        label: payment.external_reference ?? 'Tanpa referensi sumber',
        href: null,
    };
}
