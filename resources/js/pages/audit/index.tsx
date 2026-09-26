import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronDown,
    ChevronUp,
    Clock3,
    Fingerprint,
    History,
    Search,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { stage5AuditLabel, Stage5Text, stage5Translate, stage5Date, stage5IntlLocale } from '@/components/stage5-text';
import { PaginationLinks } from '@/components/pagination-links';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppLocale } from '@/lib/i18n';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Option = { value: string; label: string };
type Branch = { id: number; code: string; name: string; is_active: boolean };
type Actor = { id: number; name: string; email: string };
type Change = { key: string; label: string; old: unknown; new: unknown };
type Activity = {
    id: number;
    event: string;
    event_label: string;
    module: string;
    module_label: string;
    description: string | null;
    actor: Actor | null;
    branch: Pick<Branch, 'id' | 'code' | 'name'> | null;
    subject: {
        type: string | null;
        label: string;
        id: number | null;
        url: string | null;
    };
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    changes: Change[];
    ip_address: string | null;
    user_agent: string | null;
    request_id: string | null;
    created_at: string;
};
type Pagination = {
    data: Activity[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
};
type Filters = {
    search: string;
    branch_id: number | null;
    actor_id: number | null;
    module: string;
    event: string;
    request_id: string;
    date_from: string;
    date_to: string;
};
type Props = {
    activities: Pagination;
    summary: {
        total: number;
        today: number;
        actors: number;
        with_changes: number;
    };
    branches: Branch[];
    actors: Actor[];
    modules: Option[];
    events: Array<Option & { module: string }>;
    filters: Filters;
};



export default function AuditTrailIndex({
    activities,
    summary,
    branches,
    actors,
    modules,
    events,
    filters,
}: Props) {
    const { locale: stage5Locale } = useAppLocale();
    const [openId, setOpenId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search);
    const [requestId, setRequestId] = useState(filters.request_id);
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const filteredEvents = useMemo(
        () =>
            filters.module
                ? events.filter((event) => event.module === filters.module)
                : events,
        [events, filters.module],
    );

    const apply = (patch: Partial<Filters> = {}) => {
        const next = {
            ...filters,
            ...patch,
            search,
            request_id: requestId,
            date_from: dateFrom,
            date_to: dateTo,
        };
        router.get(
            '/audit-trail',
            Object.fromEntries(
                Object.entries(next).filter(
                    ([, value]) => value !== '' && value !== null,
                ),
            ),
            { preserveState: true, replace: true },
        );
    };

    const clear = () => {
        setSearch('');
        setRequestId('');
        setDateFrom('');
        setDateTo('');
        router.get('/audit-trail', {}, { replace: true });
    };

    return (
        <>
            <Head title={stage5Translate("stage5.ui.e01eacc119b7", stage5Locale)} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <p className="text-sm font-medium text-primary">
                        <Stage5Text k="stage5.ui.580b76bbdf67" />
                    </p>
                    <h1 className="mt-1 flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <History className="size-6" />
                        <Stage5Text k="stage5.ui.91f30c56f30c" />
                    </h1>
                    <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                        <Stage5Text k="stage5.ui.7b9ac20e01c7" />
                    </p>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label={stage5Translate("stage5.ui.08901d88457c", stage5Locale)}
                        value={summary.total}
                        icon={History}
                    />
                    <SummaryCard
                        label={stage5Translate("stage5.ui.2c6ad1441fa8", stage5Locale)}
                        value={summary.today}
                        icon={Clock3}
                    />
                    <SummaryCard
                        label={stage5Translate("stage5.ui.3edd93bea8ae", stage5Locale)}
                        value={summary.actors}
                        icon={UserRound}
                    />
                    <SummaryCard
                        label={stage5Translate("stage5.ui.28aa101f736a", stage5Locale)}
                        value={summary.with_changes}
                        icon={ShieldCheck}
                    />
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage5Text k="stage5.ui.4b6b57782762" /></CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                apply();
                            }}
                            className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                        >
                            <Field label={stage5Translate("stage5.ui.3f2275d79afb", stage5Locale)}>
                                <div className="relative">
                                    <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        className="pl-9"
                                        placeholder={stage5Translate("stage5.ui.9c1514400aab", stage5Locale)}
                                    />
                                </div>
                            </Field>
                            <Field label={stage5Translate("stage5.ui.1387475bd674", stage5Locale)}>
                                <Select
                                    value={
                                        filters.branch_id?.toString() ?? 'all'
                                    }
                                    onValueChange={(value) =>
                                        apply({
                                            branch_id:
                                                value === 'all'
                                                    ? null
                                                    : Number(value),
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
                                                value={branch.id.toString()}
                                            >
                                                {branch.code} · {branch.name}
                                                {branch.is_active
                                                    ? ''
                                                    : ' (nonaktif)'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label={stage5Translate("stage5.ui.3edd93bea8ae", stage5Locale)}>
                                <Select
                                    value={
                                        filters.actor_id?.toString() ?? 'all'
                                    }
                                    onValueChange={(value) =>
                                        apply({
                                            actor_id:
                                                value === 'all'
                                                    ? null
                                                    : Number(value),
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            <Stage5Text k="stage5.ui.736c66c14331" />
                                        </SelectItem>
                                        {actors.map((actor) => (
                                            <SelectItem
                                                key={actor.id}
                                                value={actor.id.toString()}
                                            >
                                                {actor.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label={stage5Translate("stage5.ui.7182982745ee", stage5Locale)}>
                                <Select
                                    value={filters.module || 'all'}
                                    onValueChange={(value) =>
                                        apply({
                                            module:
                                                value === 'all' ? '' : value,
                                            event: '',
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            <Stage5Text k="stage5.ui.6a0fa78c0a41" />
                                        </SelectItem>
                                        {modules.map((module) => (
                                            <SelectItem
                                                key={module.value}
                                                value={module.value}
                                            >
                                                {stage5AuditLabel(module.value, module.label)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label={stage5Translate("stage5.ui.ad8919ace091", stage5Locale)}>
                                <Select
                                    value={filters.event || 'all'}
                                    onValueChange={(value) =>
                                        apply({
                                            event: value === 'all' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            <Stage5Text k="stage5.ui.2f9329356155" />
                                        </SelectItem>
                                        {filteredEvents.map((event) => (
                                            <SelectItem
                                                key={event.value}
                                                value={event.value}
                                            >
                                                {stage5AuditLabel(event.value, event.label)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label={stage5Translate("stage5.ui.63aa59d5d8b6", stage5Locale)}>
                                <Input
                                    value={requestId}
                                    onChange={(event) =>
                                        setRequestId(event.target.value)
                                    }
                                    placeholder="X-Request-Id"
                                />
                            </Field>
                            <Field label={stage5Translate("stage5.ui.30b35bf928d5", stage5Locale)}>
                                <Input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(event) =>
                                        setDateFrom(event.target.value)
                                    }
                                />
                            </Field>
                            <Field label={stage5Translate("stage5.ui.95b58818f0a3", stage5Locale)}>
                                <Input
                                    type="date"
                                    value={dateTo}
                                    onChange={(event) =>
                                        setDateTo(event.target.value)
                                    }
                                />
                            </Field>
                            <div className="flex gap-2 md:col-span-2 xl:col-span-4">
                                <Button type="submit">
                                    <Search /> <Stage5Text k="stage5.ui.9ff8760b6d49" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={clear}
                                >
                                    <Stage5Text k="stage5.ui.9c4a6ab48318" />
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage5Text k="stage5.ui.28c8a80889cf" /></CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1050px] text-sm">
                                <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.d546c40c22ca" /></th>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.3edd93bea8ae" /></th>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.1387475bd674" /></th>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.ad8919ace091" /></th>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.f5f7e53dcab1" /></th>
                                        <th className="px-3 py-3"><Stage5Text k="stage5.ui.4aed03cac49d" /></th>
                                        <th className="px-3 py-3 text-right">
                                            <Stage5Text k="stage5.ui.7c9a7c0610c1" />
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {activities.data.map((activity) => {
                                        const open = openId === activity.id;

                                        return (
                                            <AuditRows
                                                key={activity.id}
                                                activity={activity}
                                                open={open}
                                                onToggle={() =>
                                                    setOpenId(
                                                        open
                                                            ? null
                                                            : activity.id,
                                                    )
                                                }
                                            />
                                        );
                                    })}
                                    {activities.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-3 py-12 text-center text-muted-foreground"
                                            >
                                                <Stage5Text k="stage5.ui.1b4cea961a96" />
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <PaginationLinks
                            links={activities.links}
                            from={activities.from}
                            to={activities.to}
                            total={activities.total}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function AuditRows({
    activity,
    open,
    onToggle,
}: {
    activity: Activity;
    open: boolean;
    onToggle: () => void;
}) {
    const { locale: stage5Locale } = useAppLocale();

    return (
        <>
            <tr className="align-top">
                <td className="px-3 py-3 whitespace-nowrap">
                    {stage5Date(new Date(activity.created_at), stage5Locale)}
                </td>
                <td className="px-3 py-3">
                    <div className="font-medium">
                        {activity.actor?.name ?? 'System'}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {activity.actor?.email ?? 'Tanpa aktor'}
                    </div>
                </td>
                <td className="px-3 py-3">
                    {activity.branch
                        ? `${activity.branch.code} · ${activity.branch.name}`
                        : 'Company/System'}
                </td>
                <td className="px-3 py-3">
                    <Badge variant="secondary">{stage5AuditLabel(activity.module, activity.module_label)}</Badge>
                    <div className="mt-1 font-medium">
                        {stage5AuditLabel(activity.event, activity.event_label)}
                    </div>
                    <code className="text-xs text-muted-foreground">
                        {activity.event}
                    </code>
                </td>
                <td className="px-3 py-3">
                    <div>
                        {activity.subject.label}
                        {activity.subject.id ? ` #${activity.subject.id}` : ''}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {activity.description ?? '—'}
                    </div>
                    {activity.subject.url && (
                        <Link
                            href={activity.subject.url}
                            className="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            <Stage5Text k="stage5.ui.d33a5b9e6895" /> <ArrowUpRight className="size-3" />
                        </Link>
                    )}
                </td>
                <td className="px-3 py-3">
                    <div className="font-mono text-xs">
                        {activity.request_id ?? '—'}
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {activity.ip_address ?? 'IP —'}
                    </div>
                </td>
                <td className="px-3 py-3 text-right">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={onToggle}
                    >
                        {open ? <ChevronUp /> : <ChevronDown />}
                        {activity.changes.length}
                    </Button>
                </td>
            </tr>
            {open && (
                <tr className="bg-muted/20">
                    <td colSpan={7} className="px-4 py-4">
                        <AuditDetail activity={activity} />
                    </td>
                </tr>
            )}
        </>
    );
}

function AuditDetail({ activity }: { activity: Activity }) {
    const { locale: stage5Locale } = useAppLocale();

    return (
        <div className="space-y-4">
            <div className="grid gap-3 md:grid-cols-3">
                <Meta
                    label={stage5Translate("stage5.ui.63aa59d5d8b6", stage5Locale)}
                    value={activity.request_id ?? '—'}
                    icon={Fingerprint}
                />
                <Meta
                    label={stage5Translate("stage5.ui.11f51070cecf", stage5Locale)}
                    value={activity.ip_address ?? '—'}
                    icon={ShieldCheck}
                />
                <Meta
                    label="User Agent"
                    value={activity.user_agent ?? '—'}
                    icon={UserRound}
                />
            </div>
            {activity.changes.length > 0 ? (
                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2"><Stage5Text k="stage5.ui.c326a4660b67" /></th>
                                <th className="px-3 py-2"><Stage5Text k="stage5.ui.9a8474d94f35" /></th>
                                <th className="px-3 py-2"><Stage5Text k="stage5.ui.79b83a829990" /></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {activity.changes.map((change) => (
                                <tr key={change.key} className="align-top">
                                    <td className="px-3 py-2 font-medium">
                                        {change.label}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Value value={change.old} />
                                    </td>
                                    <td className="px-3 py-2">
                                        <Value value={change.new} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    <Stage5Text k="stage5.ui.ae5b53652ec7" />
                </p>
            )}
        </div>
    );
}

function Value({ value }: { value: unknown }) {
    if (value === null || value === undefined || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    if (typeof value === 'object') {
        return (
            <pre className="max-w-xl overflow-x-auto text-xs whitespace-pre-wrap">
                {JSON.stringify(value, null, 2)}
            </pre>
        );
    }

    return <span className="break-words">{String(value)}</span>;
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function SummaryCard({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number;
    icon: typeof History;
}) {
    const { locale: stage5Locale } = useAppLocale();

    return (
        <Card>
            <CardContent className="flex items-center justify-between p-5">
                <div>
                    <p className="text-xs text-muted-foreground uppercase">
                        {label}
                    </p>
                    <p className="mt-2 text-3xl font-semibold">
                        {value.toLocaleString(stage5IntlLocale(stage5Locale))}
                    </p>
                </div>
                <Icon className="size-5 text-muted-foreground" />
            </CardContent>
        </Card>
    );
}

function Meta({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: string;
    icon: typeof History;
}) {
    return (
        <div className="rounded-lg border bg-background p-3">
            <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                <Icon className="size-4" />
                {label}
            </div>
            <p className="mt-2 text-xs break-all">{value}</p>
        </div>
    );
}
