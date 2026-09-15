import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Download,
    FileCheck2,
    FileText,
    ReceiptText,
    Search,
    ShieldCheck,
} from 'lucide-react';
import { useMemo } from 'react';
import type { FormEvent } from 'react';
import { PaginationLinks } from '@/components/pagination-links';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Branch = {
    id: number;
    code: string;
    name: string;
};

type DocumentRow = {
    id: number;
    branch_id: number;
    document_number: string;
    document_type: 'invoice' | 'receipt' | 'agreement';
    source_type: 'booking' | 'rental' | 'payment';
    source_id: number;
    source_reference: string;
    version: number;
    content_hash: string;
    issued_at: string;
    branch: Branch;
    issuer?: { id: number; name: string } | null;
    pdf_url: string;
    source_url: string | null;
};

type Pagination = {
    current_page: number;
    data: DocumentRow[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

type Props = {
    documents: Pagination;
    summary: {
        total: number;
        invoice: number;
        receipt: number;
        agreement: number;
    };
    branches: Branch[];
    filters: {
        search: string;
        branch_id: number | null;
        document_type: string;
        source_type: string;
    };
    canIssue: boolean;
};

const typeLabels: Record<DocumentRow['document_type'], string> = {
    invoice: 'Invoice',
    receipt: 'Nota Pembayaran',
    agreement: 'Agreement Rental',
};

const sourceLabels: Record<DocumentRow['source_type'], string> = {
    booking: 'Booking',
    rental: 'Rental',
    payment: 'Payment',
};

const localDateTime = (value: string) =>
    new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

export default function TransactionDocumentsIndex({
    documents,
    summary,
    branches,
    filters,
    canIssue,
}: Props) {
    const issueForm = useForm({
        document_type: 'invoice' as DocumentRow['document_type'],
        source_type: 'booking' as DocumentRow['source_type'],
        source_reference: '',
    });

    const allowedSources = useMemo(() => {
        if (issueForm.data.document_type === 'receipt') {
            return ['payment'] as DocumentRow['source_type'][];
        }

        if (issueForm.data.document_type === 'agreement') {
            return ['rental'] as DocumentRow['source_type'][];
        }

        return ['booking', 'rental'] as DocumentRow['source_type'][];
    }, [issueForm.data.document_type]);

    const setDocumentType = (value: DocumentRow['document_type']) => {
        issueForm.setData('document_type', value);
        const source =
            value === 'receipt'
                ? 'payment'
                : value === 'agreement'
                  ? 'rental'
                  : 'booking';
        issueForm.setData('source_type', source);
    };

    const submitIssue = (event: FormEvent) => {
        event.preventDefault();
        issueForm.post('/documents', {
            preserveScroll: true,
            onSuccess: () => issueForm.reset('source_reference'),
        });
    };

    const applyFilters = (patch: Partial<Props['filters']>) => {
        router.get(
            '/documents',
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Invoice, Nota & Agreement" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <p className="text-sm font-medium text-primary">
                        Dokumen Transaksi
                    </p>
                    <h1 className="mt-1 flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <FileText className="size-6" />
                        Invoice, Nota & Agreement
                    </h1>
                    <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Dokumen diterbitkan dari snapshot transaksi. Dokumen
                        lama tidak berubah saat harga, pembayaran, atau data
                        transaksi diperbarui; perubahan menghasilkan versi baru.
                    </p>
                </header>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Total dokumen"
                        value={summary.total}
                        icon={FileText}
                    />
                    <SummaryCard
                        label="Invoice"
                        value={summary.invoice}
                        icon={ReceiptText}
                    />
                    <SummaryCard
                        label="Nota"
                        value={summary.receipt}
                        icon={FileCheck2}
                    />
                    <SummaryCard
                        label="Agreement"
                        value={summary.agreement}
                        icon={ShieldCheck}
                    />
                </div>

                {canIssue && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Terbitkan dokumen</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitIssue}
                                className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                            >
                                <Field
                                    label="Jenis dokumen"
                                    error={issueForm.errors.document_type}
                                >
                                    <Select
                                        value={issueForm.data.document_type}
                                        onValueChange={(value) =>
                                            setDocumentType(
                                                value as DocumentRow['document_type'],
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="invoice">
                                                Invoice
                                            </SelectItem>
                                            <SelectItem value="receipt">
                                                Nota Pembayaran
                                            </SelectItem>
                                            <SelectItem value="agreement">
                                                Agreement Rental
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field
                                    label="Sumber"
                                    error={issueForm.errors.source_type}
                                >
                                    <Select
                                        value={issueForm.data.source_type}
                                        onValueChange={(value) =>
                                            issueForm.setData(
                                                'source_type',
                                                value as DocumentRow['source_type'],
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {allowedSources.map((source) => (
                                                <SelectItem
                                                    key={source}
                                                    value={source}
                                                >
                                                    {sourceLabels[source]}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field
                                    label="Nomor sumber"
                                    error={issueForm.errors.source_reference}
                                >
                                    <Input
                                        value={issueForm.data.source_reference}
                                        onChange={(event) =>
                                            issueForm.setData(
                                                'source_reference',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder={
                                            issueForm.data.source_type ===
                                            'booking'
                                                ? 'Nomor booking'
                                                : issueForm.data.source_type ===
                                                    'rental'
                                                  ? 'Nomor rental'
                                                  : 'Nomor payment'
                                        }
                                    />
                                </Field>

                                <div className="flex items-end">
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={issueForm.processing}
                                    >
                                        <FileCheck2 />
                                        Terbitkan snapshot
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat dokumen</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    defaultValue={filters.search}
                                    className="pl-9"
                                    placeholder="Cari nomor dokumen / sumber..."
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters({
                                                search: event.currentTarget
                                                    .value,
                                            });
                                        }
                                    }}
                                />
                            </div>
                            <Select
                                value={filters.document_type || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        document_type:
                                            value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua jenis
                                    </SelectItem>
                                    <SelectItem value="invoice">
                                        Invoice
                                    </SelectItem>
                                    <SelectItem value="receipt">
                                        Nota
                                    </SelectItem>
                                    <SelectItem value="agreement">
                                        Agreement
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.source_type || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        source_type:
                                            value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua sumber
                                    </SelectItem>
                                    <SelectItem value="booking">
                                        Booking
                                    </SelectItem>
                                    <SelectItem value="rental">
                                        Rental
                                    </SelectItem>
                                    <SelectItem value="payment">
                                        Payment
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={
                                    filters.branch_id
                                        ? String(filters.branch_id)
                                        : 'all'
                                }
                                onValueChange={(value) =>
                                    applyFilters({
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
                                        Semua cabang
                                    </SelectItem>
                                    {branches.map((branch) => (
                                        <SelectItem
                                            key={branch.id}
                                            value={String(branch.id)}
                                        >
                                            {branch.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full min-w-[980px] text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3">Dokumen</th>
                                        <th className="p-3">Jenis</th>
                                        <th className="p-3">Sumber</th>
                                        <th className="p-3">Cabang</th>
                                        <th className="p-3">Diterbitkan</th>
                                        <th className="p-3">Hash</th>
                                        <th className="p-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {documents.data.map((document) => (
                                        <tr
                                            key={document.id}
                                            className="border-t align-top"
                                        >
                                            <td className="p-3">
                                                <div className="font-semibold">
                                                    {document.document_number}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    Versi {document.version}
                                                </div>
                                            </td>
                                            <td className="p-3">
                                                {
                                                    typeLabels[
                                                        document.document_type
                                                    ]
                                                }
                                            </td>
                                            <td className="p-3">
                                                <div>
                                                    {
                                                        sourceLabels[
                                                            document.source_type
                                                        ]
                                                    }
                                                </div>
                                                {document.source_url ? (
                                                    <Link
                                                        href={
                                                            document.source_url
                                                        }
                                                        className="text-xs text-primary hover:underline"
                                                    >
                                                        {
                                                            document.source_reference
                                                        }
                                                    </Link>
                                                ) : (
                                                    <div className="text-xs text-muted-foreground">
                                                        {
                                                            document.source_reference
                                                        }
                                                    </div>
                                                )}
                                            </td>
                                            <td className="p-3">
                                                {document.branch.name}
                                            </td>
                                            <td className="p-3">
                                                <div>
                                                    {localDateTime(
                                                        document.issued_at,
                                                    )}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {document.issuer?.name ??
                                                        'Sistem'}
                                                </div>
                                            </td>
                                            <td className="p-3 font-mono text-xs text-muted-foreground">
                                                {document.content_hash.slice(
                                                    0,
                                                    12,
                                                )}
                                                …
                                            </td>
                                            <td className="p-3 text-right">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a href={document.pdf_url}>
                                                        <Download /> PDF
                                                    </a>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                    {documents.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="p-8 text-center text-muted-foreground"
                                            >
                                                Belum ada dokumen untuk filter
                                                ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <PaginationLinks
                            links={documents.links}
                            from={documents.from}
                            to={documents.to}
                            total={documents.total}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function SummaryCard({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number;
    icon: typeof FileText;
}) {
    return (
        <Card>
            <CardContent className="flex items-center justify-between p-5">
                <div>
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="mt-1 text-2xl font-semibold">
                        {value.toLocaleString('id-ID')}
                    </p>
                </div>
                <Icon className="size-5 text-muted-foreground" />
            </CardContent>
        </Card>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
