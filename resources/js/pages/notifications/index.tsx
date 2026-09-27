import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    BellRing,
    Check,
    CheckCheck,
    Clock3,
    ExternalLink,
    Mail,
    RefreshCw,
    Settings2,
    SlidersHorizontal,
    Trash2,
    Undo2,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import {
    stage5Choice,
    stage5Display,
    stage5RuleLabel,
    Stage5Text,
    stage5Translate,
    stage5Date,
} from '@/components/stage5-text';
import InputError from '@/components/input-error';
import { PaginationLinks } from '@/components/pagination-links';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { FilterBar } from '@/components/ui/filter-bar';
import { MetricCard } from '@/components/ui/metric-card';
import { Label } from '@/components/ui/label';
import { useAppLocale } from '@/lib/i18n';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    NotificationCategory,
    NotificationItem,
    NotificationPagination,
    NotificationPreference,
    NotificationRule,
    NotificationSeverity,
} from '@/types';

type Props = {
    messages: NotificationPagination;
    summary: {
        total: number;
        unread: number;
        critical: number;
        snoozed: number;
    };
    filters: {
        search: string;
        category: string;
        severity: string;
        state: string;
        branch_id: number | null;
    };
    categories: NotificationCategory[];
    severities: NotificationSeverity[];
    preference: NotificationPreference;
    rules: NotificationRule[];
    permissions: {
        manage: boolean;
    };
};

type Tab = 'inbox' | 'preferences' | 'rules';

const categoryLabels: Record<NotificationCategory, string> = {
    booking: 'Booking',
    rental: 'Rental',
    finance: 'Keuangan',
    transfer: 'Transfer',
    maintenance: 'Maintenance',
    inventory: 'Inventaris',
    system: 'Sistem',
};

const severityLabels: Record<NotificationSeverity, string> = {
    info: 'Informasi',
    warning: 'Peringatan',
    critical: 'Kritis',
};

function severityVariant(severity: NotificationSeverity) {
    if (severity === 'critical') {
        return 'destructive' as const;
    }

    if (severity === 'warning') {
        return 'secondary' as const;
    }

    return 'outline' as const;
}

function severityBorder(severity: NotificationSeverity) {
    if (severity === 'critical') {
        return 'border-l-destructive';
    }

    if (severity === 'warning') {
        return 'border-l-amber-500';
    }

    return 'border-l-sky-500';
}

export default function NotificationCenter({
    messages,
    summary,
    filters,
    categories,
    severities,
    preference,
    rules,
    permissions,
}: Props) {
    const { locale: stage5Locale } = useAppLocale();
    const { auth } = usePage().props;
    const [tab, setTab] = useState<Tab>('inbox');
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (changes: Record<string, string | number>) => {
        router.get(
            '/notifications',
            {
                search,
                category: filters.category,
                severity: filters.severity,
                state: filters.state,
                branch_id: filters.branch_id ?? '',
                ...changes,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head
                title={stage5Translate('stage5.ui.4907a6b6d8fc', stage5Locale)}
            />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <BellRing className="size-6 text-primary" />
                            <h1 className="text-2xl font-semibold">
                                <Stage5Text k="stage5.ui.4907a6b6d8fc" />
                            </h1>
                        </div>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            <Stage5Text k="stage5.ui.95714a80ed62" />
                        </p>
                    </div>
                    {permissions.manage && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    '/notifications/generate',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <RefreshCw className="size-4" />
                            <Stage5Text k="stage5.ui.c5ae7dc003ee" />
                        </Button>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.375d213b4a8b',
                            stage5Locale,
                        )}
                        value={summary.total}
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.e5ae91935245',
                            stage5Locale,
                        )}
                        value={summary.unread}
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.31e52fc1ccf5',
                            stage5Locale,
                        )}
                        value={summary.critical}
                        critical
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.b6c1c8d3db49',
                            stage5Locale,
                        )}
                        value={summary.snoozed}
                    />
                </div>

                <div className="flex flex-wrap gap-2 border-b pb-3">
                    <TabButton
                        active={tab === 'inbox'}
                        onClick={() => setTab('inbox')}
                        icon={BellRing}
                    >
                        <Stage5Text k="stage5.ui.44caf74675ce" />
                    </TabButton>
                    <TabButton
                        active={tab === 'preferences'}
                        onClick={() => setTab('preferences')}
                        icon={Settings2}
                    >
                        <Stage5Text k="stage5.ui.930ec77fa364" />
                    </TabButton>
                    {permissions.manage && (
                        <TabButton
                            active={tab === 'rules'}
                            onClick={() => setTab('rules')}
                            icon={SlidersHorizontal}
                        >
                            <Stage5Text k="stage5.ui.bef9dbc92899" />
                        </TabButton>
                    )}
                </div>

                {tab === 'inbox' && (
                    <div className="space-y-4">
                        <FilterBar
                            title={stage5Translate(
                                'stage5.ui.3c66b920b3ff',
                                stage5Locale,
                            )}
                            description={stage5Translate(
                                'stage5.ui.9c0f6fcd4610',
                                stage5Locale,
                            )}
                            contentClassName="sm:grid-cols-2 xl:grid-cols-6"
                        >
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        applyFilters({});
                                    }
                                }}
                                placeholder={stage5Translate(
                                    'stage5.ui.f664ced63a69',
                                    stage5Locale,
                                )}
                                className="xl:col-span-2"
                            />
                            <FilterSelect
                                value={filters.category || 'all'}
                                placeholder={stage5Translate(
                                    'stage5.ui.3ee43aaffaaf',
                                    stage5Locale,
                                )}
                                options={categories.map((category) => ({
                                    value: category,
                                    label: stage5Display(
                                        categoryLabels[category],
                                        stage5Locale,
                                    ),
                                }))}
                                onChange={(value) =>
                                    applyFilters({
                                        category: value === 'all' ? '' : value,
                                    })
                                }
                            />
                            <FilterSelect
                                value={filters.severity || 'all'}
                                placeholder={stage5Translate(
                                    'stage5.ui.6635e0faecfa',
                                    stage5Locale,
                                )}
                                options={severities.map((severity) => ({
                                    value: severity,
                                    label: stage5Display(
                                        severityLabels[severity],
                                        stage5Locale,
                                    ),
                                }))}
                                onChange={(value) =>
                                    applyFilters({
                                        severity: value === 'all' ? '' : value,
                                    })
                                }
                            />
                            <FilterSelect
                                value={filters.state || 'all'}
                                placeholder={stage5Translate(
                                    'stage5.ui.baa2adda4148',
                                    stage5Locale,
                                )}
                                options={[
                                    {
                                        value: 'unread',
                                        label: stage5Translate(
                                            'stage5.ui.e5ae91935245',
                                            stage5Locale,
                                        ),
                                    },
                                    {
                                        value: 'read',
                                        label: stage5Translate(
                                            'stage5.ui.3b29ff9fdcce',
                                            stage5Locale,
                                        ),
                                    },
                                    {
                                        value: 'snoozed',
                                        label: stage5Translate(
                                            'stage5.ui.977c21bd43c8',
                                            stage5Locale,
                                        ),
                                    },
                                    {
                                        value: 'dismissed',
                                        label: stage5Translate(
                                            'stage5.ui.0e97e7214c08',
                                            stage5Locale,
                                        ),
                                    },
                                ]}
                                onChange={(value) =>
                                    applyFilters({ state: value })
                                }
                            />
                            <FilterSelect
                                value={
                                    filters.branch_id === null
                                        ? 'all'
                                        : String(filters.branch_id)
                                }
                                placeholder={stage5Translate(
                                    'stage5.ui.27d30aba48a4',
                                    stage5Locale,
                                )}
                                options={auth.branches.map((branch) => ({
                                    value: String(branch.id),
                                    label: branch.code + ' · ' + branch.name,
                                }))}
                                onChange={(value) =>
                                    applyFilters({
                                        branch_id: value === 'all' ? '' : value,
                                    })
                                }
                            />
                        </FilterBar>

                        {summary.unread > 0 && (
                            <div className="flex justify-end">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            '/notifications/mark-all-read',
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <CheckCheck className="size-4" />
                                    <Stage5Text k="stage5.ui.9e0e2358c834" />
                                </Button>
                            </div>
                        )}

                        {messages.data.length === 0 ? (
                            <Card>
                                <CardContent className="flex flex-col items-center gap-3 py-14 text-center">
                                    <BellRing className="size-10 text-muted-foreground/50" />
                                    <div>
                                        <p className="font-medium">
                                            <Stage5Text k="stage5.ui.98f2e4187027" />
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            <Stage5Text k="stage5.ui.22e432d869e6" />
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="space-y-3">
                                {messages.data.map((message) => (
                                    <MessageCard
                                        key={message.id}
                                        message={message}
                                    />
                                ))}
                            </div>
                        )}

                        <PaginationLinks
                            links={messages.links}
                            from={messages.from}
                            to={messages.to}
                            total={messages.total}
                        />
                    </div>
                )}

                {tab === 'preferences' && (
                    <PreferencePanel
                        preference={preference}
                        categories={categories}
                        severities={severities}
                    />
                )}

                {tab === 'rules' && permissions.manage && (
                    <div className="grid gap-4 xl:grid-cols-2">
                        {rules.map((rule) => (
                            <RuleEditor
                                key={rule.id}
                                rule={rule}
                                severities={severities}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function MessageCard({ message }: { message: NotificationItem }) {
    const { locale: stage5Locale } = useAppLocale();
    const isUnread = message.read_at === null;

    return (
        <Card
            className={
                'gap-3 border-l-4 py-4 ' +
                severityBorder(message.severity) +
                (isUnread ? ' bg-muted/20' : ' opacity-80')
            }
        >
            <CardContent className="space-y-3 px-4 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2
                                className={
                                    isUnread ? 'font-semibold' : 'font-medium'
                                }
                            >
                                {message.title}
                            </h2>
                            {isUnread && (
                                <span className="size-2 rounded-full bg-primary" />
                            )}
                            <Badge variant={severityVariant(message.severity)}>
                                {stage5Display(message.severity, stage5Locale)}
                            </Badge>
                            <Badge variant="outline">
                                {stage5Display(
                                    categoryLabels[message.category],
                                    stage5Locale,
                                )}
                            </Badge>
                            {message.branch && (
                                <Badge variant="outline">
                                    {message.branch.code}
                                </Badge>
                            )}
                        </div>
                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                            {message.body}
                        </p>
                        <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            <span>
                                <Stage5Text k="stage5.ui.9d77dbd733a3" />{' '}
                                {stage5Date(
                                    new Date(message.last_triggered_at),
                                    stage5Locale,
                                )}
                            </span>
                            {message.due_at && (
                                <span>
                                    <Stage5Text k="stage5.ui.50c63df51c98" />{' '}
                                    {stage5Date(
                                        new Date(message.due_at),
                                        stage5Locale,
                                    )}
                                </span>
                            )}
                            {message.occurrences > 1 && (
                                <span>
                                    <Stage5Text k="stage5.ui.e59d1ed21ba8" />{' '}
                                    {message.occurrences}{' '}
                                    <Stage5Text k="stage5.ui.e7e971e55af1" />
                                </span>
                            )}
                            {message.snoozed_until &&
                                new Date(message.snoozed_until) >
                                    new Date() && (
                                    <span>
                                        <Stage5Text k="stage5.ui.e373b21adfb4" />{' '}
                                        {stage5Date(
                                            new Date(message.snoozed_until),
                                            stage5Locale,
                                        )}
                                    </span>
                                )}
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                    {message.action_url && (
                        <Button size="sm" asChild>
                            <Link
                                href={message.action_url}
                                onClick={() =>
                                    router.patch(
                                        '/notifications/' +
                                            message.id +
                                            '/read',
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <ExternalLink className="size-3.5" />
                                <Stage5Text k="stage5.ui.d33a5b9e6895" />
                            </Link>
                        </Button>
                    )}
                    {isUnread ? (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.patch(
                                    '/notifications/' + message.id + '/read',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Check className="size-3.5" />
                            <Stage5Text k="stage5.ui.77f0b190ab89" />
                        </Button>
                    ) : (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.patch(
                                    '/notifications/' + message.id + '/unread',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Undo2 className="size-3.5" />
                            <Stage5Text k="stage5.ui.e5ae91935245" />
                        </Button>
                    )}
                    <Select
                        value=""
                        onValueChange={(value) =>
                            router.post(
                                '/notifications/' + message.id + '/snooze',
                                { minutes: Number(value) },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <SelectTrigger className="h-8 w-36 text-xs">
                            <Clock3 className="size-3.5" />
                            <SelectValue
                                placeholder={stage5Translate(
                                    'stage5.ui.4e912cb03a60',
                                    stage5Locale,
                                )}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="60">
                                <Stage5Text k="stage5.ui.8122b3bc2fe4" />
                            </SelectItem>
                            <SelectItem value="1440">
                                <Stage5Text k="stage5.ui.4e32dc13c238" />
                            </SelectItem>
                            <SelectItem value="10080">
                                <Stage5Text k="stage5.ui.7a462da65851" />
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        size="sm"
                        variant="ghost"
                        className="text-destructive"
                        onClick={() =>
                            router.delete('/notifications/' + message.id, {
                                preserveScroll: true,
                            })
                        }
                    >
                        <Trash2 className="size-3.5" />
                        <Stage5Text k="stage5.ui.06cde76b08c5" />
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function PreferencePanel({
    preference,
    categories,
    severities,
}: {
    preference: NotificationPreference;
    categories: NotificationCategory[];
    severities: NotificationSeverity[];
}) {
    const { locale: stage5Locale } = useAppLocale();
    const form = useForm({
        email_enabled: preference.email_enabled,
        email_min_severity: preference.email_min_severity,
        muted_categories: preference.muted_categories,
        quiet_hours_start: preference.quiet_hours_start ?? '',
        quiet_hours_end: preference.quiet_hours_end ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/notifications/preferences', { preserveScroll: true });
    };

    const toggleCategory = (category: NotificationCategory) => {
        form.setData(
            'muted_categories',
            form.data.muted_categories.includes(category)
                ? form.data.muted_categories.filter((item) => item !== category)
                : [...form.data.muted_categories, category],
        );
    };

    return (
        <Card className="max-w-4xl">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Mail className="size-5" />
                    <Stage5Text k="stage5.ui.1ec647cb28dd" />
                </CardTitle>
                <CardDescription>
                    <Stage5Text k="stage5.ui.c33b9b05e588" />
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form className="space-y-6" onSubmit={submit}>
                    <label className="flex items-start gap-3 rounded-lg border p-4">
                        <input
                            type="checkbox"
                            checked={form.data.email_enabled}
                            onChange={(event) =>
                                form.setData(
                                    'email_enabled',
                                    event.target.checked,
                                )
                            }
                            className="mt-1 size-4"
                        />
                        <span>
                            <span className="block text-sm font-medium">
                                <Stage5Text k="stage5.ui.169158592b7d" />
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                <Stage5Text k="stage5.ui.04100b1e0712" />
                            </span>
                        </span>
                    </label>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label>
                                <Stage5Text k="stage5.ui.ad2cdff9957d" />
                            </Label>
                            <Select
                                value={form.data.email_min_severity}
                                onValueChange={(value: NotificationSeverity) =>
                                    form.setData('email_min_severity', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {severities.map((severity) => (
                                        <SelectItem
                                            key={severity}
                                            value={severity}
                                        >
                                            {stage5Display(
                                                severityLabels[severity],
                                                stage5Locale,
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={form.errors.email_min_severity}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="quiet-start">
                                <Stage5Text k="stage5.ui.a926c6df289f" />
                            </Label>
                            <Input
                                id="quiet-start"
                                type="time"
                                value={form.data.quiet_hours_start}
                                onChange={(event) =>
                                    form.setData(
                                        'quiet_hours_start',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.quiet_hours_start}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="quiet-end">
                                <Stage5Text k="stage5.ui.a6fd7ff0a48a" />
                            </Label>
                            <Input
                                id="quiet-end"
                                type="time"
                                value={form.data.quiet_hours_end}
                                onChange={(event) =>
                                    form.setData(
                                        'quiet_hours_end',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.quiet_hours_end} />
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div>
                            <Label>
                                <Stage5Text k="stage5.ui.b4d8c9a0d587" />
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                <Stage5Text k="stage5.ui.88485d13bbef" />
                            </p>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {categories
                                .filter((category) => category !== 'system')
                                .map((category) => (
                                    <label
                                        key={category}
                                        className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={form.data.muted_categories.includes(
                                                category,
                                            )}
                                            onChange={() =>
                                                toggleCategory(category)
                                            }
                                        />
                                        {stage5Display(
                                            categoryLabels[category],
                                            stage5Locale,
                                        )}
                                    </label>
                                ))}
                        </div>
                        <InputError message={form.errors.muted_categories} />
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing
                            ? stage5Choice(
                                  'Menyimpan...',
                                  'Saving...',
                                  stage5Locale,
                              )
                            : stage5Choice(
                                  'Simpan preferensi',
                                  'Save preferences',
                                  stage5Locale,
                              )}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function RuleEditor({
    rule,
    severities,
}: {
    rule: NotificationRule;
    severities: NotificationSeverity[];
}) {
    const { locale: stage5Locale } = useAppLocale();
    const form = useForm({
        is_enabled: rule.is_enabled,
        severity: rule.severity,
        lead_minutes: rule.lead_minutes,
        repeat_minutes: rule.repeat_minutes,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/notifications/rules/' + rule.id, { preserveScroll: true });
    };

    return (
        <Card className={form.data.is_enabled ? '' : 'opacity-70'}>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle>
                                {stage5RuleLabel(
                                    rule.code,
                                    rule.name,
                                    'title',
                                    stage5Locale,
                                )}
                            </CardTitle>
                            <Badge variant="outline">
                                {stage5Display(
                                    categoryLabels[rule.category],
                                    stage5Locale,
                                )}
                            </Badge>
                        </div>
                        <CardDescription className="mt-2">
                            {stage5RuleLabel(
                                rule.code,
                                rule.description,
                                'description',
                                stage5Locale,
                            )}
                        </CardDescription>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_enabled}
                            onChange={(event) =>
                                form.setData('is_enabled', event.target.checked)
                            }
                        />
                        <Stage5Text k="stage5.ui.89f29d42adb5" />
                    </label>
                </div>
            </CardHeader>
            <CardContent>
                <form className="space-y-4" onSubmit={submit}>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="space-y-2">
                            <Label>
                                <Stage5Text k="stage5.ui.51cb5d20e6cb" />
                            </Label>
                            <Select
                                value={form.data.severity}
                                onValueChange={(value: NotificationSeverity) =>
                                    form.setData('severity', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {severities.map((severity) => (
                                        <SelectItem
                                            key={severity}
                                            value={severity}
                                        >
                                            {stage5Display(
                                                severityLabels[severity],
                                                stage5Locale,
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>
                                <Stage5Text k="stage5.ui.3fb56e744bbd" />
                            </Label>
                            <Input
                                type="number"
                                min={0}
                                max={43200}
                                value={form.data.lead_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'lead_minutes',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>
                                <Stage5Text k="stage5.ui.d5a816caf986" />
                            </Label>
                            <Input
                                type="number"
                                min={5}
                                max={43200}
                                value={form.data.repeat_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'repeat_minutes',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                        <p className="text-xs text-muted-foreground">
                            <Stage5Text k="stage5.ui.d85617e54a61" />{' '}
                            <code>{rule.recipient_permission}</code>
                        </p>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing
                                ? stage5Choice(
                                      'Menyimpan...',
                                      'Saving...',
                                      stage5Locale,
                                  )
                                : stage5Choice(
                                      'Simpan aturan',
                                      'Save rule',
                                      stage5Locale,
                                  )}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function SummaryCard({
    label,
    value,
    critical = false,
}: {
    label: string;
    value: number;
    critical?: boolean;
}) {
    return (
        <MetricCard
            label={label}
            value={value}
            icon={BellRing}
            tone={critical && value > 0 ? 'danger' : 'neutral'}
        />
    );
}

function TabButton({
    active,
    onClick,
    icon: Icon,
    children,
}: {
    active: boolean;
    onClick: () => void;
    icon: typeof BellRing;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant={active ? 'default' : 'ghost'}
            onClick={onClick}
        >
            <Icon className="size-4" />
            {children}
        </Button>
    );
}

function FilterSelect({
    value,
    placeholder,
    options,
    onChange,
}: {
    value: string;
    placeholder: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{placeholder}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
