import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowLeft,
    ArrowUpFromLine,
    Clock3,
    History,
    ReceiptText,
    UserRound,
    WalletCards,
} from 'lucide-react';
import type { ReactNode } from 'react';
import {
    stage5Display,
    Stage5Text,
    stage5Translate,
    stage5Date,
    stage5Money,
    stage5IntlLocale,
} from '@/components/stage5-text';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppLocale } from '@/lib/i18n';

type Person = { id: number; name: string } | null;

type Props = {
    cashRegister: {
        id: number;
        code: string;
        name: string;
        branch: { id: number; code: string; name: string } | null;
    };
    session: {
        id: number;
        status: 'open' | 'closed';
        opened_at: string;
        closed_at: string | null;
        opening_balance: string | number;
        incoming_total: string | number;
        outgoing_total: string | number;
        ledger_expected_balance: string | number;
        expected_closing_balance: string | number;
        actual_closing_balance: string | number | null;
        difference_amount: string | number;
        opening_notes: string | null;
        closing_notes: string | null;
        opener: Person;
        closer: Person;
    };
    transactions: Array<{
        id: number;
        transaction_number: string;
        direction: 'in' | 'out';
        type: string;
        amount: string | number;
        balance_after: string | number;
        occurred_at: string;
        description: string | null;
        payment_id: number | null;
        refund_id: number | null;
        creator: Person;
    }>;
};

const money = { format: stage5Money };

function formatMoney(value: string | number | null): string {
    return money.format(Number(value ?? 0));
}

function formatDate(value: string | null): string {
    return value ? stage5Date(new Date(value)) : '—';
}

export default function CashSessionDetail({
    cashRegister,
    session,
    transactions,
}: Props) {
    const { locale: stage5Locale } = useAppLocale();
    const isClosed = session.status === 'closed';

    return (
        <>
            <Head
                title={`${stage5Display('Sesi Kas', stage5Locale)} #${session.id}`}
            />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={`/finance/cash-registers/${cashRegister.id}/sessions`}
                            >
                                <ArrowLeft className="size-4" />
                                <Stage5Text k="stage5.ui.6f1a56a6161e" />
                            </Link>
                        </Button>
                        <div className="mt-3 flex flex-wrap items-center gap-3">
                            <div className="flex size-11 items-center justify-center rounded-xl bg-muted">
                                <WalletCards className="size-5" />
                            </div>
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-2xl font-semibold">
                                        <Stage5Text k="stage5.ui.ad2a6ab5762c" />
                                        {session.id}
                                    </h1>
                                    <Badge
                                        variant={
                                            isClosed ? 'outline' : 'secondary'
                                        }
                                    >
                                        {stage5Display(
                                            isClosed ? 'closed' : 'open',
                                            stage5Locale,
                                        )}
                                    </Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {cashRegister.code} · {cashRegister.name} ·{' '}
                                    {cashRegister.branch?.code ?? '—'} —{' '}
                                    {cashRegister.branch?.name ?? '—'}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-lg border bg-muted/30 px-4 py-3 text-right text-xs text-muted-foreground">
                        <div className="flex items-center gap-2">
                            <History className="size-4" />
                            <Stage5Text k="stage5.ui.f24332f45e42" />
                        </div>
                    </div>
                </header>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        label={stage5Translate(
                            'stage5.ui.2331f39d0b15',
                            stage5Locale,
                        )}
                        value={formatMoney(session.opening_balance)}
                    />
                    <Metric
                        label={stage5Translate(
                            'stage5.ui.6bda2a3e6b9d',
                            stage5Locale,
                        )}
                        value={formatMoney(session.incoming_total)}
                        icon={<ArrowDownToLine className="size-4" />}
                    />
                    <Metric
                        label={stage5Translate(
                            'stage5.ui.75fe2e7ee821',
                            stage5Locale,
                        )}
                        value={formatMoney(session.outgoing_total)}
                        icon={<ArrowUpFromLine className="size-4" />}
                    />
                    <Metric
                        label={stage5Translate(
                            'stage5.ui.8ecd5baeb467',
                            stage5Locale,
                        )}
                        value={formatMoney(session.expected_closing_balance)}
                    />
                    <Metric
                        label={stage5Translate(
                            'stage5.ui.4345d43dd7c7',
                            stage5Locale,
                        )}
                        value={
                            session.actual_closing_balance === null
                                ? '—'
                                : formatMoney(session.actual_closing_balance)
                        }
                    />
                    <Metric
                        label={stage5Translate(
                            'stage5.ui.eb39bbf6ccbb',
                            stage5Locale,
                        )}
                        value={
                            isClosed
                                ? formatMoney(session.difference_amount)
                                : '—'
                        }
                    />
                    <Metric
                        label={stage5Translate(
                            'stage5.ui.42de3534490e',
                            stage5Locale,
                        )}
                        value={formatMoney(session.ledger_expected_balance)}
                    />
                    <Metric
                        label={stage5Translate(
                            'stage5.ui.284668330d13',
                            stage5Locale,
                        )}
                        value={transactions.length.toLocaleString(
                            stage5IntlLocale(stage5Locale),
                        )}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Clock3 className="size-4" />
                                <Stage5Text k="stage5.ui.3a327fd87f85" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Detail
                                label={stage5Translate(
                                    'stage5.ui.0583cf5ecc3e',
                                    stage5Locale,
                                )}
                                value={formatDate(session.opened_at)}
                            />
                            <Detail
                                label={stage5Translate(
                                    'stage5.ui.e96b9599edf8',
                                    stage5Locale,
                                )}
                                value={formatDate(session.closed_at)}
                            />
                            <Detail
                                label={stage5Translate(
                                    'stage5.ui.31e0a5724f12',
                                    stage5Locale,
                                )}
                                value={session.opener?.name ?? '—'}
                                icon={<UserRound className="size-4" />}
                            />
                            <Detail
                                label={stage5Translate(
                                    'stage5.ui.c5dc2ae9b7ff',
                                    stage5Locale,
                                )}
                                value={session.closer?.name ?? '—'}
                                icon={<UserRound className="size-4" />}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ReceiptText className="size-4" />
                                <Stage5Text k="stage5.ui.fdd2eeb8a6a3" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <Detail
                                label={stage5Translate(
                                    'stage5.ui.f2395dbb1a19',
                                    stage5Locale,
                                )}
                                value={session.opening_notes || '—'}
                            />
                            <Detail
                                label={stage5Translate(
                                    'stage5.ui.649f4544764c',
                                    stage5Locale,
                                )}
                                value={session.closing_notes || '—'}
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            <Stage5Text k="stage5.ui.24eed099da35" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {transactions.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                <Stage5Text k="stage5.ui.455f7bf28691" />
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-[950px] text-sm">
                                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                <Stage5Text k="stage5.ui.d546c40c22ca" />
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                <Stage5Text k="stage5.ui.8d334718eadf" />
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                <Stage5Text k="stage5.ui.c86c93709b3d" />
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                <Stage5Text k="stage5.ui.d809b1515dab" />
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                <Stage5Text k="stage5.ui.1795d163388f" />
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                <Stage5Text k="stage5.ui.65068eea081e" />
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                <Stage5Text k="stage5.ui.72f900301e55" />
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                <Stage5Text k="stage5.ui.557f56ddb658" />
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {transactions.map((transaction) => (
                                            <tr
                                                key={transaction.id}
                                                className="align-top"
                                            >
                                                <td className="px-4 py-3">
                                                    {formatDate(
                                                        transaction.occurred_at,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {
                                                        transaction.transaction_number
                                                    }
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="outline">
                                                        {transaction.direction ===
                                                        'in'
                                                            ? 'Masuk'
                                                            : 'Keluar'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {transaction.type}
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {formatMoney(
                                                        transaction.amount,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {formatMoney(
                                                        transaction.balance_after,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {transaction.creator
                                                        ?.name ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {transaction.description ||
                                                        '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
                    <Stage5Text k="stage5.ui.9248f5a3036d" />
                </div>
            </div>
        </>
    );
}

function Metric({
    label,
    value,
    icon,
}: {
    label: string;
    value: string;
    icon?: ReactNode;
}) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {label}
                    </p>
                    {icon}
                </div>
                <p className="mt-2 text-xl font-semibold tabular-nums">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

function Detail({
    label,
    value,
    icon,
}: {
    label: string;
    value: string;
    icon?: ReactNode;
}) {
    return (
        <div>
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 flex items-center gap-2 text-sm font-medium break-words">
                {icon}
                {value}
            </p>
        </div>
    );
}
