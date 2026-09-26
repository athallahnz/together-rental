import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Clock3,
    History,
    WalletCards,
} from 'lucide-react';
import { stage5Choice, stage5Display, Stage5Text, stage5Date, stage5Money } from '@/components/stage5-text';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PaginationLinks } from '@/components/pagination-links';
import { useAppLocale } from '@/lib/i18n';
import type { PaginationLink } from '@/types';

type Person = { id: number; name: string } | null;

type CashRegister = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    branch: { id: number; code: string; name: string } | null;
};

type CashSessionRow = {
    id: number;
    status: 'open' | 'closed';
    opened_at: string;
    closed_at: string | null;
    opening_balance: string | number;
    incoming_total: string | number;
    outgoing_total: string | number;
    expected_closing_balance: string | number;
    actual_closing_balance: string | number | null;
    difference_amount: string | number;
    opening_notes: string | null;
    closing_notes: string | null;
    opener: Person;
    closer: Person;
};

type Props = {
    cashRegister: CashRegister;
    sessions: {
        data: CashSessionRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
};

const money = { format: stage5Money };


function formatMoney(value: string | number | null): string {
    return money.format(Number(value ?? 0));
}

function formatDate(value: string | null): string {
    return value ? stage5Date(new Date(value)) : '—';
}

export default function CashSessionHistory({ cashRegister, sessions }: Props) {
    const { locale: stage5Locale } = useAppLocale();

    return (
        <>
            <Head title={`${stage5Display('Riwayat Sesi Kas', stage5Locale)} ${cashRegister.code}`} />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/finance/master-data">
                                <ArrowLeft className="size-4" />
                                <Stage5Text k="stage5.ui.928d875498de" />
                            </Link>
                        </Button>
                        <div className="mt-3 flex flex-wrap items-center gap-3">
                            <div className="flex size-11 items-center justify-center rounded-xl bg-muted">
                                <WalletCards className="size-5" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold">
                                    <Stage5Text k="stage5.ui.f210b830c285" />
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {cashRegister.code} · {cashRegister.name} ·{' '}
                                    {cashRegister.branch?.code ?? '—'} —{' '}
                                    {cashRegister.branch?.name ?? '—'}
                                </p>
                            </div>
                        </div>
                    </div>
                    <Badge variant={cashRegister.is_active ? 'secondary' : 'outline'}>
                        {cashRegister.is_active
                            ? stage5Choice('Kasir aktif', 'Active cash register', stage5Locale)
                            : stage5Choice('Kasir nonaktif', 'Inactive cash register', stage5Locale)}
                    </Badge>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <History className="size-4" />
                            <Stage5Text k="stage5.ui.538125f86c6e" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {sessions.data.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                <Stage5Text k="stage5.ui.2c018a8fb86b" />
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-[1050px] text-sm">
                                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium"><Stage5Text k="stage5.ui.51340c1d0c10" /></th>
                                            <th className="px-4 py-3 font-medium"><Stage5Text k="stage5.ui.374027752cd7" /></th>
                                            <th className="px-4 py-3 font-medium"><Stage5Text k="stage5.ui.738b0b8e6254" /></th>
                                            <th className="px-4 py-3 font-medium"><Stage5Text k="stage5.ui.6bda2a3e6b9d" /></th>
                                            <th className="px-4 py-3 font-medium"><Stage5Text k="stage5.ui.75fe2e7ee821" /></th>
                                            <th className="px-4 py-3 font-medium"><Stage5Text k="stage5.ui.1f4e4ee3de7a" /></th>
                                            <th className="px-4 py-3 font-medium"><Stage5Text k="stage5.ui.8c5da973dcdb" /></th>
                                            <th className="px-4 py-3 font-medium"><Stage5Text k="stage5.ui.2a0ab753352f" /></th>
                                            <th className="px-4 py-3 font-medium"><Stage5Text k="stage5.ui.60ad46d8cab9" /></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {sessions.data.map((session) => (
                                            <tr key={session.id} className="align-top">
                                                <td className="px-4 py-3">
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">#{session.id}</span>
                                                            <Badge
                                                                variant={
                                                                    session.status === 'closed'
                                                                        ? 'outline'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {session.status === 'closed'
                                                                    ? 'Closed'
                                                                    : 'Open'}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            <Stage5Text k="stage5.ui.aad1a980791c" /> {session.opener?.name ?? '—'}
                                                        </p>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="space-y-1">
                                                        <span>{formatDate(session.opened_at)}</span>
                                                        {session.closed_at && (
                                                            <p className="text-xs text-muted-foreground">
                                                                <Stage5Text k="stage5.ui.06cde76b08c5" /> {formatDate(session.closed_at)}
                                                            </p>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {formatMoney(session.opening_balance)}
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {formatMoney(session.incoming_total)}
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {formatMoney(session.outgoing_total)}
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {formatMoney(session.expected_closing_balance)}
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {session.actual_closing_balance === null
                                                        ? '—'
                                                        : formatMoney(session.actual_closing_balance)}
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {session.status === 'closed'
                                                        ? formatMoney(session.difference_amount)
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Button size="sm" variant="outline" asChild>
                                                        <Link href={`/finance/cash-sessions/${session.id}`}>
                                                            <Stage5Text k="stage5.ui.7c9a7c0610c1" />
                                                            <ArrowRight className="size-4" />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <PaginationLinks
                            links={sessions.links}
                            from={sessions.from}
                            to={sessions.to}
                            total={sessions.total}
                        />
                    </CardContent>
                </Card>

                <div className="flex items-start gap-3 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
                    <Clock3 className="mt-0.5 size-4 shrink-0" />
                    <p>
                        <Stage5Text k="stage5.ui.68e2283c4bfe" />
                    </p>
                </div>
            </div>
        </>
    );
}
