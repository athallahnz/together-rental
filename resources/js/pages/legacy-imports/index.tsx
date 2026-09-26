import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    DatabaseZap,
    FileCode2,
    MapPin,
    ShieldCheck,
    UploadCloud,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    stage5Choice,
    stage5PaginatorLabel,
    stage5Number,
    stage5Date,
    stage5Display,
    Stage5Text,
    stage5Translate,
} from '@/components/stage5-text';
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
import { useAppLocale } from '@/lib/i18n';

type BranchOption = {
    id: number;
    code: string;
    name: string;
    city: string;
    suggested_prefix: string;
    prefix_locked: boolean;
};

type Batch = {
    id: string;
    source_filename: string;
    source_size: number;
    source_sha256: string;
    source_city: string | null;
    import_prefix: string | null;
    status: string;
    total_rows: number;
    valid_rows: number;
    warning_rows: number;
    error_rows: number;
    imported_rows: number;
    created_at: string;
    uploader: { name: string; email: string } | null;
    branch: { code: string; name: string; city: string | null };
};

type Pagination<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
};

type Props = {
    branches: BranchOption[];
    defaultBranchId: number | null;
    batches: Pagination<Batch>;
    maxUploadMegabytes: number;
    permissions: {
        view: boolean;
        upload: boolean;
        validate: boolean;
        execute: boolean;
    };
};

export default function LegacyImportIndex({
    branches,
    defaultBranchId,
    batches,
    maxUploadMegabytes,
    permissions,
}: Props) {
    const { locale: stage5Locale } = useAppLocale();
    const initialBranch =
        branches.find((branch) => branch.id === defaultBranchId) ?? branches[0];
    const [selectedBranchId, setSelectedBranchId] = useState(
        initialBranch?.id ?? 0,
    );
    const [importPrefix, setImportPrefix] = useState(
        initialBranch?.suggested_prefix ?? '',
    );
    const selectedBranch = useMemo(
        () => branches.find((branch) => branch.id === selectedBranchId) ?? null,
        [branches, selectedBranchId],
    );

    const changeBranch = (branchId: number) => {
        setSelectedBranchId(branchId);
        const branch = branches.find((item) => item.id === branchId);

        setImportPrefix(branch?.suggested_prefix ?? '');
    };

    return (
        <>
            <Head
                title={stage5Translate('stage5.ui.1a71ed4aa718', stage5Locale)}
            />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            <Stage5Text k="stage5.ui.580b76bbdf67" />
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            <Stage5Text k="stage5.ui.1a71ed4aa718" />
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            <Stage5Text k="stage5.ui.b165ac31abb0" />
                        </p>
                    </div>
                    <Badge variant="outline" className="h-7 px-3">
                        {branches.length}{' '}
                        <Stage5Text k="stage5.ui.44d3894c59ae" />
                    </Badge>
                </header>

                <section className="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UploadCloud className="size-5" />
                                <Stage5Text k="stage5.ui.97e282e6af6d" />
                            </CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.e7db6b30860a" />{' '}
                                {maxUploadMegabytes}{' '}
                                <Stage5Text k="stage5.ui.0dd949d2ec49" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {permissions.upload ? (
                                branches.length > 0 ? (
                                    <Form
                                        action="/legacy-imports"
                                        method="post"
                                        options={{ preserveScroll: true }}
                                        className="space-y-4"
                                    >
                                        {({ processing, errors, progress }) => (
                                            <>
                                                <div className="grid gap-4 md:grid-cols-[1fr_180px]">
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="branch_id">
                                                            <Stage5Text k="stage5.ui.fcf175c12573" />
                                                        </Label>
                                                        <select
                                                            id="branch_id"
                                                            name="branch_id"
                                                            value={
                                                                selectedBranchId
                                                            }
                                                            onChange={(event) =>
                                                                changeBranch(
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                                )
                                                            }
                                                            className="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                            required
                                                        >
                                                            {branches.map(
                                                                (branch) => (
                                                                    <option
                                                                        key={
                                                                            branch.id
                                                                        }
                                                                        value={
                                                                            branch.id
                                                                        }
                                                                    >
                                                                        {branch.city ||
                                                                            'Kota belum diisi'}{' '}
                                                                        —{' '}
                                                                        {
                                                                            branch.code
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {
                                                                            branch.name
                                                                        }
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                        <InputError
                                                            message={
                                                                errors.branch_id
                                                            }
                                                        />
                                                    </div>

                                                    <div className="grid gap-2">
                                                        <Label htmlFor="import_prefix">
                                                            <Stage5Text k="stage5.ui.54491fdec612" />
                                                        </Label>
                                                        <Input
                                                            id="import_prefix"
                                                            name="import_prefix"
                                                            value={importPrefix}
                                                            onChange={(event) =>
                                                                setImportPrefix(
                                                                    event.target.value
                                                                        .toUpperCase()
                                                                        .replace(
                                                                            /[^A-Z]/g,
                                                                            '',
                                                                        )
                                                                        .slice(
                                                                            0,
                                                                            3,
                                                                        ),
                                                                )
                                                            }
                                                            maxLength={3}
                                                            minLength={3}
                                                            pattern="[A-Z]{3}"
                                                            autoComplete="off"
                                                            className="font-mono uppercase"
                                                            placeholder="PNG"
                                                            readOnly={
                                                                selectedBranch?.prefix_locked ??
                                                                false
                                                            }
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.import_prefix
                                                            }
                                                        />
                                                        {selectedBranch?.prefix_locked && (
                                                            <p className="text-xs text-muted-foreground">
                                                                <Stage5Text k="stage5.ui.9b244da11251" />{' '}
                                                                {
                                                                    selectedBranch.suggested_prefix
                                                                }
                                                                .
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>

                                                {selectedBranch && (
                                                    <div className="flex gap-3 rounded-lg border bg-muted/30 p-3 text-sm">
                                                        <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                                        <div>
                                                            <p className="font-medium">
                                                                <Stage5Text k="stage5.ui.17848205f216" />{' '}
                                                                {selectedBranch.city ||
                                                                    'Kota belum diisi'}
                                                            </p>
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {
                                                                    selectedBranch.code
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    selectedBranch.name
                                                                }{' '}
                                                                <Stage5Text k="stage5.ui.a8ef9fece60b" />{' '}
                                                                <span className="font-mono font-semibold">
                                                                    {importPrefix ||
                                                                        '---'}
                                                                </span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                )}

                                                <div className="grid gap-2">
                                                    <Label htmlFor="sql_file">
                                                        <Stage5Text k="stage5.ui.feb5330e8531" />
                                                    </Label>
                                                    <Input
                                                        id="sql_file"
                                                        name="sql_file"
                                                        type="file"
                                                        accept=".sql"
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.sql_file
                                                        }
                                                    />
                                                </div>

                                                {progress && (
                                                    <div>
                                                        <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                                                            <span>
                                                                <Stage5Text k="stage5.ui.0152ec282094" />
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
                                                    disabled={
                                                        processing ||
                                                        importPrefix.length !==
                                                            3
                                                    }
                                                >
                                                    <UploadCloud />
                                                    {processing
                                                        ? stage5Choice(
                                                              'Mengunggah…',
                                                              'Uploading…',
                                                              stage5Locale,
                                                          )
                                                        : stage5Choice(
                                                              'Unggah dan buat batch',
                                                              'Upload and create batch',
                                                              stage5Locale,
                                                          )}
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        <Stage5Text k="stage5.ui.95a53d7b10ab" />
                                    </p>
                                )
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    <Stage5Text k="stage5.ui.2b8e53ea98e6" />
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage5Text k="stage5.ui.f9fc8c5ae6af" />
                            </CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.971b11394d08" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {[
                                [
                                    FileCode2,
                                    'Allowlist parser',
                                    stage5Choice(
                                        'Hanya tabel RentalV1 yang dikenali yang dibaca.',
                                        'Only recognized RentalV1 tables are read.',
                                        stage5Locale,
                                    ),
                                ],
                                [
                                    ShieldCheck,
                                    stage5Choice(
                                        'Kota & PREFIX terkunci sebelum eksekusi',
                                        'City and prefix locked before execution',
                                        stage5Locale,
                                    ),
                                    stage5Choice(
                                        'Jika tujuan diubah setelah pratinjau, data sementara dihapus dan wajib dipratinjau ulang.',
                                        'If the destination changes after preview, staged data is reset and preview must be repeated.',
                                        stage5Locale,
                                    ),
                                ],
                                [
                                    DatabaseZap,
                                    stage5Choice(
                                        'SHA-256 & pemetaan ID per cabang',
                                        'SHA-256 & branch-specific ID mapping',
                                        stage5Locale,
                                    ),
                                    stage5Choice(
                                        'Berkas duplikat dan pemetaan ID ganda diblokir.',
                                        'Duplicate files and duplicate record ID mappings are blocked.',
                                        stage5Locale,
                                    ),
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
                        <CardTitle>
                            <Stage5Text k="stage5.ui.cfa0a46b60f6" />
                        </CardTitle>
                        <CardDescription>
                            <Stage5Text k="stage5.ui.bf8e3d90d088" />
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full min-w-[1040px] text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="px-3 py-3 font-medium">
                                        <Stage5Text k="stage5.ui.ff648afc53ef" />
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        <Stage5Text k="stage5.ui.1f1ad92e0a04" />
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        <Stage5Text k="stage5.ui.890121a7a9c7" />
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        <Stage5Text k="stage5.ui.bae7d5be7082" />
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        <Stage5Text k="stage5.ui.52d0b35277ee" />
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        <Stage5Text k="stage5.ui.e9c45563358e" />
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        <Stage5Text k="stage5.ui.7f2f6a15cf8d" />
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        <Stage5Text k="stage5.ui.5264ac49ed12" />
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
                                                {batch.branch.code} ·{' '}
                                                {batch.id.slice(0, 13)}…
                                            </p>
                                        </td>
                                        <td className="px-3 py-4">
                                            <p className="font-medium">
                                                {batch.source_city ||
                                                    batch.branch.city ||
                                                    '—'}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {batch.branch.name}
                                            </p>
                                        </td>
                                        <td className="px-3 py-4">
                                            <Badge
                                                variant="outline"
                                                className="font-mono"
                                            >
                                                {batch.import_prefix || '---'}
                                            </Badge>
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
                                                    <Stage5Text k="stage5.ui.7c9a7c0610c1" />
                                                    <ArrowRight />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {batches.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="px-3 py-12 text-center text-muted-foreground"
                                        >
                                            <Stage5Text k="stage5.ui.5ac392996ea9" />
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
    const { locale: stage5Locale } = useAppLocale();
    const variant =
        status === 'failed'
            ? 'destructive'
            : status === 'verified'
              ? 'default'
              : 'secondary';

    return (
        <Badge variant={variant}>{stage5Display(status, stage5Locale)}</Badge>
    );
}

function Pagination({
    links,
}: {
    links: Array<{ url: string | null; label: string; active: boolean }>;
}) {
    const { locale: stage5Locale } = useAppLocale();

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
                            dangerouslySetInnerHTML={{
                                __html: stage5PaginatorLabel(
                                    link.label,
                                    stage5Locale,
                                ),
                            }}
                        />
                    </Button>
                ) : (
                    <Button
                        key={`${link.label}-${index}`}
                        size="sm"
                        variant="outline"
                        disabled
                        dangerouslySetInnerHTML={{
                            __html: stage5PaginatorLabel(
                                link.label,
                                stage5Locale,
                            ),
                        }}
                    />
                ),
            )}
        </div>
    );
}

function number(value: number) {
    return stage5Number(value);
}

function dateTime(value: string) {
    return stage5Date(value);
}

LegacyImportIndex.layout = {
    breadcrumbs: [
        {
            title: 'Legacy Import',
            href: '/legacy-imports',
        },
    ],
};
