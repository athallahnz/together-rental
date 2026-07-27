import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    CheckCircle2,
    CircleDashed,
    Database,
    FileSearch,
    GitMerge,
    ListChecks,
    LoaderCircle,
    Play,
    ScanSearch,
    ShieldAlert,
    UploadCloud,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type SourceTable = {
    id: number;
    source_table: string;
    target_table: string | null;
    status: string;
    parsed_rows: number;
    valid_rows: number;
    warning_rows: number;
    error_rows: number;
    imported_rows: number;
    summary: {
        label?: string;
        columns?: string[];
        executable?: boolean;
    } | null;
};

type Mapping = {
    id: number;
    mapping_type: string;
    source_value: string;
    target_table: string | null;
    target_id: number | null;
    transform_rule: Record<string, unknown> | null;
    is_confirmed: boolean;
};

type ImportEvent = {
    id: number;
    event: string;
    from_status: string | null;
    to_status: string | null;
    context: Record<string, unknown> | null;
    created_at: string;
};

type Batch = {
    id: string;
    source_system: string;
    source_filename: string;
    source_sha256: string;
    source_size: number;
    status: string;
    total_rows: number;
    valid_rows: number;
    warning_rows: number;
    error_rows: number;
    imported_rows: number;
    skipped_rows: number;
    options: { failed_step?: string } | null;
    summary: {
        verification?: {
            passed: boolean;
            checked_at: string;
            checks: Array<{
                code: string;
                label: string;
                expected: number | string;
                actual: number | string;
                difference?: string;
                status: 'passed' | 'failed';
            }>;
        };
    } | null;
    failure_message: string | null;
    previewed_at: string | null;
    validated_at: string | null;
    mapped_at: string | null;
    executed_at: string | null;
    verified_at: string | null;
    created_at: string;
    branch: { id: number; code: string; name: string };
    uploader: { id: number; name: string; email: string } | null;
    tables: SourceTable[];
    mappings: Mapping[];
    events: ImportEvent[];
};

type ImportRow = {
    id: number;
    source_table: string;
    legacy_key: string;
    row_number: number;
    normalized_payload: Record<string, unknown> | null;
    status: string;
    issue_count: number;
    target_table: string | null;
    target_id: number | null;
};

type ImportIssue = {
    id: number;
    severity: 'error' | 'warning' | 'info';
    code: string;
    field: string | null;
    message: string;
    original_value: string | null;
    status: string;
    row: {
        id: number;
        source_table: string;
        legacy_key: string;
        row_number: number;
    } | null;
};

type Pagination<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
};

type Props = {
    batch: Batch;
    rows: Pagination<ImportRow>;
    issues: Pagination<ImportIssue>;
    issueSummary: Array<{
        severity: string;
        status: string;
        aggregate: number;
    }>;
    filters: {
        source_table: string;
        row_status: string;
        issue_severity: string;
    };
    permissions: {
        view: boolean;
        upload: boolean;
        validate: boolean;
        execute: boolean;
    };
};

const steps = [
    { key: 'upload', label: 'Upload', icon: UploadCloud },
    { key: 'preview', label: 'Preview', icon: FileSearch },
    { key: 'validation', label: 'Validasi', icon: ListChecks },
    { key: 'mapping', label: 'Mapping Cabang', icon: GitMerge },
    { key: 'execution', label: 'Execute', icon: Play },
    { key: 'verification', label: 'Verifikasi', icon: ScanSearch },
];

const statusOrder: Record<string, number> = {
    uploaded: 0,
    queued_preview: 0,
    previewing: 0,
    previewed: 1,
    queued_validation: 1,
    validating: 1,
    validated: 2,
    mapped: 3,
    queued_execution: 3,
    executing: 3,
    executed: 4,
    queued_verification: 4,
    verifying: 4,
    verified: 5,
};

export default function LegacyImportShow({
    batch,
    rows,
    issues,
    issueSummary,
    filters,
    permissions,
}: Props) {
    const [activeAction, setActiveAction] = useState<string | null>(null);
    const currentStep =
        batch.status === 'failed'
            ? Math.max(
                  steps.findIndex(
                      (step) => step.key === batch.options?.failed_step,
                  ) - 1,
                  0,
              )
            : (statusOrder[batch.status] ?? 0);
    const nextAction = useMemo(
        () => resolveAction(batch, permissions),
        [batch, permissions],
    );

    useEffect(() => {
        if (
            !batch.status.startsWith('queued_') &&
            !['previewing', 'validating', 'executing', 'verifying'].includes(
                batch.status,
            )
        ) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload();
        }, 2500);

        return () => window.clearInterval(timer);
    }, [batch.status]);

    const runAction = () => {
        if (!nextAction) {
            return;
        }

        if (
            nextAction.key === 'execution' &&
            !window.confirm(
                'Execute akan menulis data RentalV1 ke tabel operasional V2 dalam satu transaksi. Lanjutkan?',
            )
        ) {
            return;
        }

        setActiveAction(nextAction.key);
        router.post(
            `/legacy-imports/${batch.id}/${nextAction.endpoint}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setActiveAction(null),
            },
        );
    };

    const updateFilter = (key: keyof Props['filters'], value: string) => {
        router.get(
            `/legacy-imports/${batch.id}`,
            { ...filters, [key]: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title={`Import ${batch.source_filename}`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header>
                    <Button asChild variant="ghost" size="sm" className="-ml-3">
                        <Link href="/legacy-imports">
                            <ArrowLeft />
                            Semua batch
                        </Link>
                    </Button>
                    <div className="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">
                                    {batch.source_system}
                                </Badge>
                                <StatusBadge status={batch.status} />
                                <Badge variant="outline">
                                    Cabang {batch.branch.code}
                                </Badge>
                            </div>
                            <h1 className="mt-3 max-w-3xl truncate text-2xl font-semibold tracking-tight">
                                {batch.source_filename}
                            </h1>
                            <p className="mt-2 font-mono text-xs text-muted-foreground">
                                Batch {batch.id} · SHA-256{' '}
                                {batch.source_sha256.slice(0, 20)}…
                            </p>
                        </div>

                        {nextAction && (
                            <Button
                                onClick={runAction}
                                disabled={activeAction !== null}
                                variant={
                                    nextAction.key === 'execution'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {activeAction ? (
                                    <LoaderCircle className="animate-spin" />
                                ) : (
                                    <nextAction.icon />
                                )}
                                {activeAction
                                    ? 'Sedang memproses…'
                                    : nextAction.label}
                            </Button>
                        )}
                    </div>
                </header>

                {batch.failure_message && (
                    <div className="flex gap-3 rounded-xl border border-destructive/40 bg-destructive/5 p-4 text-sm">
                        <ShieldAlert className="mt-0.5 size-5 shrink-0 text-destructive" />
                        <div>
                            <p className="font-medium text-destructive">
                                Proses terakhir gagal
                            </p>
                            <p className="mt-1 text-muted-foreground">
                                {batch.failure_message}
                            </p>
                        </div>
                    </div>
                )}

                <section className="grid gap-2 sm:grid-cols-3 xl:grid-cols-6">
                    {steps.map((step, index) => {
                        const Icon = step.icon;
                        const done = currentStep >= index;
                        const active =
                            currentStep === index &&
                            batch.status !== 'verified';

                        return (
                            <div
                                key={step.key}
                                className={cn(
                                    'rounded-xl border p-3',
                                    done && 'border-primary/30 bg-primary/5',
                                    active && 'ring-1 ring-primary/30',
                                )}
                            >
                                <div className="flex items-center gap-2">
                                    <div
                                        className={cn(
                                            'flex size-7 items-center justify-center rounded-full bg-muted',
                                            done &&
                                                'bg-primary text-primary-foreground',
                                        )}
                                    >
                                        {done ? (
                                            <Check className="size-4" />
                                        ) : (
                                            <Icon className="size-4" />
                                        )}
                                    </div>
                                    <span className="text-xs font-medium">
                                        {step.label}
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <Metric label="Total staging" value={batch.total_rows} />
                    <Metric
                        label="Valid"
                        value={batch.valid_rows}
                        tone="success"
                    />
                    <Metric
                        label="Warning"
                        value={batch.warning_rows}
                        tone="warning"
                    />
                    <Metric
                        label="Error"
                        value={batch.error_rows}
                        tone="danger"
                    />
                    <Metric label="Imported" value={batch.imported_rows} />
                    <Metric label="Skipped" value={batch.skipped_rows} />
                </section>

                {batch.status === 'validated' && batch.error_rows > 0 && (
                    <div className="flex gap-3 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm">
                        <AlertTriangle className="mt-0.5 size-5 shrink-0 text-destructive" />
                        <div>
                            <p className="font-medium">
                                Mapping belum dapat dikonfirmasi
                            </p>
                            <p className="mt-1 text-muted-foreground">
                                Terdapat {number(batch.error_rows)} baris error.
                                Periksa referensi atau kolom wajib pada antrean
                                isu.
                            </p>
                        </div>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Cakupan tabel sumber</CardTitle>
                        <CardDescription>
                            Ringkasan hasil parser, validasi, dan execute per
                            tabel RentalV1.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="px-3 py-3 font-medium">
                                        Entitas
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Target V2
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Parsed
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Valid
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Warning
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Error
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Imported
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {batch.tables.map((table) => (
                                    <tr
                                        key={table.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-3 py-3">
                                            <p className="font-medium">
                                                {table.summary?.label ??
                                                    table.source_table}
                                            </p>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                {table.source_table}
                                            </p>
                                        </td>
                                        <td className="px-3 py-3 font-mono text-xs">
                                            {table.target_table ?? 'Audit only'}
                                        </td>
                                        <td className="px-3 py-3">
                                            <StatusBadge
                                                status={table.status}
                                            />
                                        </td>
                                        <td className="px-3 py-3 text-right tabular-nums">
                                            {number(table.parsed_rows)}
                                        </td>
                                        <td className="px-3 py-3 text-right text-emerald-600 tabular-nums">
                                            {number(table.valid_rows)}
                                        </td>
                                        <td className="px-3 py-3 text-right text-amber-600 tabular-nums">
                                            {number(table.warning_rows)}
                                        </td>
                                        <td className="px-3 py-3 text-right text-destructive tabular-nums">
                                            {number(table.error_rows)}
                                        </td>
                                        <td className="px-3 py-3 text-right tabular-nums">
                                            {number(table.imported_rows)}
                                        </td>
                                    </tr>
                                ))}
                                {batch.tables.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-3 py-10 text-center text-muted-foreground"
                                        >
                                            Jalankan Preview untuk melihat tabel
                                            sumber.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {batch.mappings.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Mapping terkonfirmasi</CardTitle>
                            <CardDescription>
                                Semua data operasional diarahkan ke cabang{' '}
                                {batch.branch.code}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {batch.mappings.map((mapping) => (
                                <div
                                    key={mapping.id}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            {mapping.mapping_type.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </p>
                                        {mapping.is_confirmed && (
                                            <CheckCircle2 className="size-4 text-emerald-600" />
                                        )}
                                    </div>
                                    <p className="mt-2 text-sm font-medium">
                                        {mapping.source_value}
                                    </p>
                                    <p className="mt-1 font-mono text-xs text-muted-foreground">
                                        {mapping.target_table
                                            ? `${mapping.target_table}#${mapping.target_id}`
                                            : JSON.stringify(
                                                  mapping.transform_rule,
                                              )}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {batch.summary?.verification && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {batch.summary.verification.passed ? (
                                    <CheckCircle2 className="size-5 text-emerald-600" />
                                ) : (
                                    <AlertTriangle className="size-5 text-destructive" />
                                )}
                                Hasil verifikasi
                            </CardTitle>
                            <CardDescription>
                                Rekonsiliasi target ID, rental aktif, nominal,
                                dan pengembalian.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 lg:grid-cols-2">
                            {batch.summary.verification.checks.map((check) => (
                                <div
                                    key={check.code}
                                    className="flex items-start justify-between gap-4 rounded-lg border p-4"
                                >
                                    <div>
                                        <p className="text-sm font-medium">
                                            {check.label}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Expected {check.expected} · Actual{' '}
                                            {check.actual}
                                            {check.difference
                                                ? ` · Selisih ${check.difference}`
                                                : ''}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            check.status === 'passed'
                                                ? 'default'
                                                : 'destructive'
                                        }
                                    >
                                        {check.status === 'passed'
                                            ? 'PASS'
                                            : 'FAIL'}
                                    </Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <section className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Preview baris staging</CardTitle>
                            <CardDescription>
                                Data ternormalisasi; password legacy tidak
                                disimpan.
                            </CardDescription>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <NativeFilter
                                    value={filters.source_table}
                                    onChange={(value) =>
                                        updateFilter('source_table', value)
                                    }
                                    options={[
                                        ['', 'Semua tabel'],
                                        ...batch.tables.map(
                                            (table) =>
                                                [
                                                    table.source_table,
                                                    table.source_table,
                                                ] as [string, string],
                                        ),
                                    ]}
                                />
                                <NativeFilter
                                    value={filters.row_status}
                                    onChange={(value) =>
                                        updateFilter('row_status', value)
                                    }
                                    options={[
                                        ['', 'Semua status'],
                                        ['pending', 'Pending'],
                                        ['valid', 'Valid'],
                                        ['warning', 'Warning'],
                                        ['error', 'Error'],
                                        ['imported', 'Imported'],
                                        ['skipped', 'Skipped'],
                                    ]}
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {rows.data.map((row) => (
                                <div
                                    key={row.id}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-mono text-xs font-medium">
                                                {row.source_table}#
                                                {row.legacy_key}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Row {number(row.row_number)}
                                                {row.target_id
                                                    ? ` → ${row.target_table}#${row.target_id}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <StatusBadge status={row.status} />
                                    </div>
                                    <dl className="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                                        {Object.entries(
                                            row.normalized_payload ?? {},
                                        )
                                            .slice(0, 8)
                                            .map(([key, value]) => (
                                                <div
                                                    key={key}
                                                    className="min-w-0"
                                                >
                                                    <dt className="truncate text-muted-foreground">
                                                        {key}
                                                    </dt>
                                                    <dd className="mt-0.5 truncate font-medium">
                                                        {displayValue(value)}
                                                    </dd>
                                                </div>
                                            ))}
                                    </dl>
                                </div>
                            ))}
                            {rows.data.length === 0 && (
                                <EmptyState text="Belum ada baris staging untuk filter ini." />
                            )}
                            <Pagination links={rows.links} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertTriangle className="size-5" />
                                Antrean validasi
                            </CardTitle>
                            <CardDescription>
                                {number(issues.total)} isu ditemukan ·{' '}
                                {issueSummary
                                    .map(
                                        (item) =>
                                            `${item.severity}: ${number(Number(item.aggregate))}`,
                                    )
                                    .join(' · ') || 'belum divalidasi'}
                            </CardDescription>
                            <div className="mt-3">
                                <NativeFilter
                                    value={filters.issue_severity}
                                    onChange={(value) =>
                                        updateFilter('issue_severity', value)
                                    }
                                    options={[
                                        ['', 'Semua severity'],
                                        ['error', 'Error'],
                                        ['warning', 'Warning'],
                                        ['info', 'Info'],
                                    ]}
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {issues.data.map((issue) => (
                                <div
                                    key={issue.id}
                                    className={cn(
                                        'rounded-lg border p-3',
                                        issue.severity === 'error' &&
                                            'border-destructive/30 bg-destructive/5',
                                        issue.severity === 'warning' &&
                                            'border-amber-500/30 bg-amber-500/5',
                                    )}
                                >
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                issue.severity === 'error'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {issue.severity}
                                        </Badge>
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {issue.code}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm font-medium">
                                        {issue.message}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {issue.row
                                            ? `${issue.row.source_table}#${issue.row.legacy_key}`
                                            : 'Level tabel/batch'}
                                        {issue.field
                                            ? ` · field ${issue.field}`
                                            : ''}
                                    </p>
                                </div>
                            ))}
                            {issues.data.length === 0 && (
                                <EmptyState text="Belum ada isu untuk filter ini." />
                            )}
                            <Pagination links={issues.links} />
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Audit event</CardTitle>
                        <CardDescription>
                            Jejak perubahan status batch terbaru.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {batch.events.map((event) => (
                            <div
                                key={event.id}
                                className="flex gap-3 border-b pb-3 last:border-0 last:pb-0"
                            >
                                <CircleDashed className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {event.event.replaceAll('_', ' ')}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {event.from_status ?? '—'} →{' '}
                                        {event.to_status ?? '—'} ·{' '}
                                        {dateTime(event.created_at)}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function resolveAction(
    batch: Batch,
    permissions: Props['permissions'],
): {
    key: string;
    endpoint: string;
    label: string;
    icon: typeof Database;
} | null {
    const failedStep =
        batch.status === 'failed' ? batch.options?.failed_step : null;
    const effectiveStatus =
        failedStep === 'preview'
            ? 'uploaded'
            : failedStep === 'validation'
              ? 'previewed'
              : failedStep === 'mapping'
                ? 'validated'
                : failedStep === 'execution'
                  ? 'mapped'
                  : failedStep === 'verification'
                    ? 'executed'
                    : batch.status;

    if (effectiveStatus === 'uploaded' && permissions.validate) {
        return {
            key: 'preview',
            endpoint: 'preview',
            label: failedStep ? 'Ulangi Preview' : 'Buat Preview',
            icon: FileSearch,
        };
    }

    if (effectiveStatus === 'previewed' && permissions.validate) {
        return {
            key: 'validation',
            endpoint: 'validate',
            label: failedStep ? 'Ulangi Validasi' : 'Jalankan Validasi',
            icon: ListChecks,
        };
    }

    if (
        effectiveStatus === 'validated' &&
        batch.error_rows === 0 &&
        permissions.validate
    ) {
        return {
            key: 'mapping',
            endpoint: 'map-branch',
            label: `Konfirmasi Mapping ${batch.branch.code}`,
            icon: GitMerge,
        };
    }

    if (effectiveStatus === 'mapped' && permissions.execute) {
        return {
            key: 'execution',
            endpoint: 'execute',
            label: failedStep ? 'Ulangi Execute' : 'Execute ke Database V2',
            icon: Play,
        };
    }

    if (effectiveStatus === 'executed' && permissions.execute) {
        return {
            key: 'verification',
            endpoint: 'verify',
            label: 'Jalankan Verifikasi',
            icon: ScanSearch,
        };
    }

    return null;
}

function Metric({
    label,
    value,
    tone = 'default',
}: {
    label: string;
    value: number;
    tone?: 'default' | 'success' | 'warning' | 'danger';
}) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p
                className={cn(
                    'mt-2 text-2xl font-semibold tabular-nums',
                    tone === 'success' && 'text-emerald-600',
                    tone === 'warning' && 'text-amber-600',
                    tone === 'danger' && 'text-destructive',
                )}
            >
                {number(value)}
            </p>
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'error' || status === 'failed'
            ? 'destructive'
            : status === 'verified' ||
                status === 'valid' ||
                status === 'imported'
              ? 'default'
              : 'secondary';

    return (
        <Badge variant={variant}>
            {status.replaceAll('_', ' ').replace(/^\w/, (v) => v.toUpperCase())}
        </Badge>
    );
}

function NativeFilter({
    value,
    onChange,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    options: Array<[string, string]>;
}) {
    return (
        <select
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            {options.map(([optionValue, label]) => (
                <option key={optionValue} value={optionValue}>
                    {label}
                </option>
            ))}
        </select>
    );
}

function Pagination({
    links,
}: {
    links: Array<{ url: string | null; label: string; active: boolean }>;
}) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-end gap-1 pt-2">
            {links.map((link, index) =>
                link.url ? (
                    <Button
                        key={`${link.label}-${index}`}
                        asChild
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                    >
                        <Link
                            href={link.url}
                            preserveScroll
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    </Button>
                ) : (
                    <Button
                        key={`${link.label}-${index}`}
                        size="sm"
                        variant="outline"
                        disabled
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </div>
    );
}

function EmptyState({ text }: { text: string }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-10 text-center">
            <Database className="size-6 text-muted-foreground" />
            <p className="mt-3 text-sm text-muted-foreground">{text}</p>
        </div>
    );
}

function displayValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Ya' : 'Tidak';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function number(value: number) {
    return new Intl.NumberFormat('id-ID').format(value);
}

function dateTime(value: string) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

LegacyImportShow.layout = {
    breadcrumbs: [
        {
            title: 'Legacy Import',
            href: '/legacy-imports',
        },
        {
            title: 'Detail batch',
            href: '#',
        },
    ],
};
