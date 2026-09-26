import { Head, Link, router } from '@inertiajs/react';
import { RefreshCcw, RotateCcw, Search } from 'lucide-react';
import { useState } from 'react';
import { stage5Choice, stage5Display, Stage5Text, stage5Translate, stage5Date, stage5Money } from '@/components/stage5-text';
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

const money = { format: stage5Money };

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
    const { locale: stage5Locale } = useAppLocale();
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
            <Head title={stage5Translate("stage5.ui.81bd652019ba", stage5Locale)} />
            <div className="space-y-6 p-4 md:p-6">
                <header>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        <RotateCcw className="size-6 text-primary" />
                        <Stage5Text k="stage5.ui.81bd652019ba" />
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        <Stage5Text k="stage5.ui.e2093b521181" />
                    </p>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <SummaryCard
                        title={stage5Translate("stage5.ui.e2c758898156", stage5Locale)}
                        value={String(summary.total_count)}
                        description={stage5Translate("stage5.ui.f817d256981e", stage5Locale)}
                    />
                    <SummaryCard
                        title={stage5Translate("stage5.ui.6618acf15388", stage5Locale)}
                        value={String(summary.requested_count)}
                        description={stage5Translate("stage5.ui.1c9437f1f921", stage5Locale)}
                        tone="warning"
                    />
                    <SummaryCard
                        title={stage5Translate("stage5.ui.cfdc44a6e5cf", stage5Locale)}
                        value={String(summary.approved_count)}
                        description={stage5Translate("stage5.ui.af7171f8a532", stage5Locale)}
                        tone="primary"
                    />
                    <SummaryCard
                        title={stage5Translate("stage5.ui.f8ee57ec8645", stage5Locale)}
                        value={money.format(summary.outstanding_amount)}
                        description={stage5Translate("stage5.ui.1a9b03355bdc", stage5Locale)}
                        tone="warning"
                    />
                    <SummaryCard
                        title={stage5Translate("stage5.ui.3c78f49c2760", stage5Locale)}
                        value={money.format(summary.paid_amount)}
                        description={stage5Choice(`${summary.paid_count} refund dibayar`, `${summary.paid_count} refunds paid`, stage5Locale)}
                        tone="success"
                    />
                </section>

                <FilterBar
                    title={stage5Translate("stage5.ui.49ca00bd551e", stage5Locale)}
                    description={stage5Translate("stage5.ui.561abf2da7e3", stage5Locale)}
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
                            placeholder={stage5Translate("stage5.ui.e3a505e8f23b", stage5Locale)}
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
                            <SelectValue placeholder={stage5Translate("stage5.ui.baa2adda4148", stage5Locale)} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all"><Stage5Text k="stage5.ui.baa2adda4148" /></SelectItem>
                            {Object.entries(statusLabels).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {stage5Display(label, stage5Locale)}
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
                            <SelectValue placeholder={stage5Translate("stage5.ui.27d30aba48a4", stage5Locale)} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all"><Stage5Text k="stage5.ui.27d30aba48a4" /></SelectItem>
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
                            <SelectValue placeholder={stage5Translate("stage5.ui.816684ab79ec", stage5Locale)} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all"><Stage5Text k="stage5.ui.816684ab79ec" /></SelectItem>
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
                        <Label htmlFor="refund-date-from"><Stage5Text k="stage5.ui.30b35bf928d5" /></Label>
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
                        <Label htmlFor="refund-date-to"><Stage5Text k="stage5.ui.95b58818f0a3" /></Label>
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
                        <Stage5Text k="stage5.ui.9c4a6ab48318" />
                    </Button>
                </FilterBar>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage5Text k="stage5.ui.f3d663d22d8f" /></CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[980px] text-sm">
                                <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.e17c8ad0dc2e" /></th>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.b41a92bed032" /></th>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.af0ab4433946" /></th>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.5ac33f2c588b" /></th>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.1387475bd674" /></th>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.bae7d5be7082" /></th>
                                        <th className="px-3 py-3 text-right">
                                            <Stage5Text k="stage5.ui.1795d163388f" />
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
                                                    {stage5Date(
                                                        new Date(
                                                            refund.created_at,
                                                        ),
                                                        stage5Locale,
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
                                                <Stage5Text k="stage5.ui.0f3393b2758f" />
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
    return (
        <MetricCard
            label={title}
            value={value}
            detail={description}
            compact
            tone={tone === 'default' ? 'neutral' : tone}
        />
    );
}

function StatusBadge({ status }: { status: RefundStatus }) {
    const { locale: stage5Locale } = useAppLocale();
    const variant =
        status === 'rejected'
            ? 'destructive'
            : status === 'cancelled'
              ? 'outline'
              : status === 'requested'
                ? 'secondary'
                : 'default';

    return <Badge variant={variant}>{stage5Display(status, stage5Locale)}</Badge>;
}
