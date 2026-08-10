import { Head, Link, router, useForm } from '@inertiajs/react';
import { ClipboardCheck, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PaginationLinks } from '@/components/pagination-links';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { FilterBar } from '@/components/ui/filter-bar';
import { MetricCard } from '@/components/ui/metric-card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    InventoryAuditBranch,
    InventoryAuditPagination,
    InventoryAuditPermissions,
    InventoryAuditStatus,
} from '@/types';

type Props = {
    audits: InventoryAuditPagination;
    summary: {
        active: number;
        submitted: number;
        approved: number;
        closed: number;
    };
    branches: InventoryAuditBranch[];
    filters: {
        search: string;
        status: string;
        branchId: number | null;
    };
    permissions: InventoryAuditPermissions;
};

const statusLabels: Record<InventoryAuditStatus, string> = {
    draft: 'Draft',
    in_progress: 'Sedang dihitung',
    submitted: 'Menunggu approval',
    approved: 'Disetujui',
    closed: 'Ditutup',
    cancelled: 'Dibatalkan',
};

const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function InventoryAuditIndex({
    audits,
    summary,
    branches,
    filters,
    permissions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm({
        branch_id: branches[0]?.id ?? 0,
        title: '',
        scheduled_at: '',
        notes: '',
    });

    const applyFilters = (changes: Record<string, string | number>) => {
        router.get(
            '/inventory-audits',
            {
                search,
                status: filters.status,
                branch_id: filters.branchId ?? '',
                ...changes,
            },
            { preserveState: true, replace: true },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/inventory-audits', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setDialogOpen(false);
            },
        });
    };

    return (
        <>
            <Head title="Stock Opname & Inventory Audit" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <ClipboardCheck className="size-6 text-primary" />
                            <h1 className="text-2xl font-semibold">
                                Stock Opname & Inventory Audit
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Pemeriksaan fisik aset, bukti kondisi, approval, dan
                            tindak lanjut temuan per cabang.
                        </p>
                    </div>
                    {permissions.create && (
                        <Button onClick={() => setDialogOpen(true)}>
                            <Plus className="size-4" />
                            Buat Stock Opname
                        </Button>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Summary label="Audit aktif" value={summary.active} />
                    <Summary
                        label="Menunggu approval"
                        value={summary.submitted}
                    />
                    <Summary
                        label="Perlu ditindaklanjuti"
                        value={summary.approved}
                    />
                    <Summary label="Sudah ditutup" value={summary.closed} />
                </div>

                <FilterBar
                    title="Filter stock opname"
                    description="Cari dokumen audit berdasarkan nomor, judul, status, dan cabang."
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters({});
                            }
                        }}
                        placeholder="Nomor atau judul audit..."
                    />
                    <Select
                        value={filters.status || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                status: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            {Object.entries(statusLabels).map(
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
                            filters.branchId === null
                                ? 'all'
                                : String(filters.branchId)
                        }
                        onValueChange={(value) =>
                            applyFilters({
                                branch_id: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Semua cabang" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua cabang</SelectItem>
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
                    <Button
                        variant="secondary"
                        onClick={() => applyFilters({})}
                    >
                        Cari
                    </Button>
                </FilterBar>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Stock Opname</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[860px] text-sm">
                                <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3">Dokumen</th>
                                        <th className="px-4 py-3">Cabang</th>
                                        <th className="px-4 py-3">Jadwal</th>
                                        <th className="px-4 py-3">Progress</th>
                                        <th className="px-4 py-3">Temuan</th>
                                        <th className="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {audits.data.map((audit) => (
                                        <tr
                                            key={audit.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/inventory-audits/${audit.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {audit.audit_number}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {audit.title}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                {audit.branch.code} —{' '}
                                                {audit.branch.name}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {audit.scheduled_at
                                                    ? dateTime.format(
                                                          new Date(
                                                              audit.scheduled_at,
                                                          ),
                                                      )
                                                    : 'Belum dijadwalkan'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {audit.counted_items_count ?? 0}{' '}
                                                / {audit.items_count ?? 0}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        (audit.findings_count ??
                                                            0) > 0
                                                            ? 'destructive'
                                                            : 'outline'
                                                    }
                                                >
                                                    {audit.findings_count ?? 0}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant="secondary">
                                                    {statusLabels[audit.status]}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                    {audits.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-4 py-12 text-center text-muted-foreground"
                                            >
                                                Belum ada stock opname sesuai
                                                filter.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="p-4">
                            <PaginationLinks
                                links={audits.links}
                                from={audits.from}
                                to={audits.to}
                                total={audits.total}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Buat Stock Opname</DialogTitle>
                        <DialogDescription>
                            Sistem akan mengunci snapshot aset dan stok quantity
                            saat dokumen dibuat.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submit}>
                        <Field label="Cabang" error={form.errors.branch_id}>
                            <Select
                                value={String(form.data.branch_id)}
                                onValueChange={(value) =>
                                    form.setData('branch_id', Number(value))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih cabang" />
                                </SelectTrigger>
                                <SelectContent>
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
                        </Field>
                        <Field label="Judul" error={form.errors.title}>
                            <Input
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                placeholder="Contoh: Stock Opname Bulanan Agustus"
                            />
                        </Field>
                        <Field label="Jadwal" error={form.errors.scheduled_at}>
                            <Input
                                type="datetime-local"
                                value={form.data.scheduled_at}
                                onChange={(event) =>
                                    form.setData(
                                        'scheduled_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Catatan" error={form.errors.notes}>
                            <textarea
                                className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                placeholder="Area, PIC, atau instruksi pemeriksaan..."
                            />
                        </Field>
                        <DialogFooter>
                            <Button type="submit" disabled={form.processing}>
                                Buat Snapshot Audit
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Summary({ label, value }: { label: string; value: number }) {
    return <MetricCard label={label} value={value} />;
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
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
