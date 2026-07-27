import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    DatabaseZap,
    FileCode2,
    ShieldCheck,
    UploadCloud,
} from 'lucide-react';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';

type Batch = {
    id: string;
    source_filename: string;
    source_size: number;
    source_sha256: string;
    status: string;
    total_rows: number;
    valid_rows: number;
    warning_rows: number;
    error_rows: number;
    imported_rows: number;
    created_at: string;
    uploader: { name: string; email: string } | null;
    branch: { code: string; name: string };
};

type Pagination<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
};

type Props = {
    branch: { id: number; code: string; name: string };
    batches: Pagination<Batch>;
    maxUploadMegabytes: number;
    permissions: {
        view: boolean;
        upload: boolean;
        validate: boolean;
        execute: boolean;
    };
};

const statusLabels: Record<string, string> = {
    uploaded: 'Uploaded',
    queued_preview: 'Preview dalam antrean',
    previewing: 'Memproses preview',
    previewed: 'Preview tersedia',
    queued_validation: 'Validasi dalam antrean',
    validating: 'Memvalidasi',
    validated: 'Tervalidasi',
    mapped: 'Mapping dikonfirmasi',
    queued_execution: 'Execute dalam antrean',
    executing: 'Mengeksekusi',
    executed: 'Sudah dieksekusi',
    queued_verification: 'Verifikasi dalam antrean',
    verifying: 'Memverifikasi',
    verified: 'Terverifikasi',
    failed: 'Gagal',
};

export default function LegacyImportIndex({
    branch,
    batches,
    maxUploadMegabytes,
    permissions,
}: Props) {
    return (
        <>
            <Head title="Legacy Import RentalV1" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Database Rental Management V2
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Legacy Import RentalV1
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Migrasikan database desktop lama melalui tahapan
                            Upload, Preview, Validasi, Mapping {branch.code},
                            Execute, dan Verifikasi.
                        </p>
                    </div>
                    <Badge variant="outline" className="h-7 px-3">
                        Cabang {branch.code} · {branch.name}
                    </Badge>
                </header>

                <section className="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UploadCloud className="size-5" />
                                Upload SQL RentalV1
                            </CardTitle>
                            <CardDescription>
                                Maksimal {maxUploadMegabytes} MB. File disimpan
                                privat dan tidak pernah dieksekusi ke MySQL.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {permissions.upload ? (
                                <Form
                                    action="/legacy-imports"
                                    method="post"
                                    options={{
                                        preserveScroll: true,
                                    }}
                                    className="space-y-4"
                                >
                                    {({ processing, errors, progress }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="sql_file">
                                                    File dump .sql
                                                </Label>
                                                <Input
                                                    id="sql_file"
                                                    name="sql_file"
                                                    type="file"
                                                    accept=".sql"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.sql_file}
                                                />
                                            </div>

                                            {progress && (
                                                <div>
                                                    <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                                                        <span>
                                                            Mengunggah file
                                                        </span>
                                                        <span>
                                                            {
                                                                progress.percentage
                                                            }
                                                            %
                                                        </span>
                                                    </div>
                                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className="h-full bg-primary transition-all"
                                                            style={{
                                                                width: `${progress.percentage}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            )}

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <UploadCloud />
                                                {processing
                                                    ? 'Mengunggah…'
                                                    : 'Upload dan buat batch'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Akun ini tidak memiliki izin untuk
                                    mengunggah sumber legacy.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Proteksi import</CardTitle>
                            <CardDescription>
                                Guardrail aktif untuk menjaga database baru.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {[
                                [
                                    FileCode2,
                                    'Allowlist parser',
                                    'Hanya tabel RentalV1 yang dikenali yang dibaca.',
                                ],
                                [
                                    ShieldCheck,
                                    'Password tidak dipindahkan',
                                    'Hash password dan API token lama dibuang sebelum staging.',
                                ],
                                [
                                    DatabaseZap,
                                    'SHA-256 & ID map',
                                    'File duplikat dan eksekusi record ganda diblokir.',
                                ],
                            ].map(([Icon, title, description]) => (
                                <div key={String(title)} className="flex gap-3">
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                                        <Icon className="size-4" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium">
                                            {String(title)}
                                        </p>
                                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                            {String(description)}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat batch import</CardTitle>
                        <CardDescription>
                            Setiap keputusan mapping dan eksekusi tersimpan
                            dalam audit event.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="px-3 py-3 font-medium">
                                        Sumber
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Rows
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Warning
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Error
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Dibuat
                                    </th>
                                    <th className="px-3 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {batches.data.map((batch) => (
                                    <tr
                                        key={batch.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-3 py-4">
                                            <p className="max-w-72 truncate font-medium">
                                                {batch.source_filename}
                                            </p>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                {batch.id.slice(0, 13)}…
                                            </p>
                                        </td>
                                        <td className="px-3 py-4">
                                            <StatusBadge
                                                status={batch.status}
                                            />
                                        </td>
                                        <td className="px-3 py-4 text-right tabular-nums">
                                            {number(batch.total_rows)}
                                        </td>
                                        <td className="px-3 py-4 text-right text-amber-600 tabular-nums">
                                            {number(batch.warning_rows)}
                                        </td>
                                        <td className="px-3 py-4 text-right text-destructive tabular-nums">
                                            {number(batch.error_rows)}
                                        </td>
                                        <td className="px-3 py-4 text-muted-foreground">
                                            {dateTime(batch.created_at)}
                                        </td>
                                        <td className="px-3 py-4 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={`/legacy-imports/${batch.id}`}
                                                >
                                                    Detail
                                                    <ArrowRight />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {batches.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-3 py-12 text-center text-muted-foreground"
                                        >
                                            Belum ada batch import.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>

                        <Pagination links={batches.links} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'failed'
            ? 'destructive'
            : status === 'verified'
              ? 'default'
              : 'secondary';

    return (
        <Badge variant={variant}>
            {statusLabels[status] ?? status.replaceAll('_', ' ')}
        </Badge>
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
        <div className="mt-5 flex flex-wrap justify-end gap-1">
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

function number(value: number) {
    return new Intl.NumberFormat('id-ID').format(value);
}

function dateTime(value: string) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

LegacyImportIndex.layout = {
    breadcrumbs: [
        {
            title: 'Legacy Import',
            href: '/legacy-imports',
        },
    ],
};
