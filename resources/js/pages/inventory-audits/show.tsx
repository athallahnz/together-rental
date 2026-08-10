import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Camera,
    CheckCircle2,
    ClipboardCheck,
    FileWarning,
    LockKeyhole,
    Play,
    ScanLine,
    Send,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { TransferCameraDialog } from '@/components/transfers/transfer-camera-dialog';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { PaginationLinks } from '@/components/pagination-links';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    InventoryAudit,
    InventoryAuditItem,
    InventoryAuditItemPagination,
    InventoryAuditPermissions,
    InventoryAuditStatus,
    InventoryFindingStatus,
} from '@/types';

type Props = {
    audit: InventoryAudit;
    items: InventoryAuditItemPagination;
    summary: {
        total: number;
        pending: number;
        matched: number;
        findings: number;
        unresolved: number;
    };
    filters: {
        search: string;
        finding: string;
        trackingType: string;
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

const findingLabels: Record<InventoryFindingStatus, string> = {
    pending: 'Belum dihitung',
    matched: 'Sesuai',
    verified_offsite: 'Offsite terverifikasi',
    discrepancy: 'Selisih',
    missing: 'Tidak ditemukan',
    unexpected: 'Salah lokasi',
};

const issueLabels: Record<string, string> = {
    quantity_mismatch: 'Jumlah berbeda',
    asset_missing: 'Unit tidak ditemukan',
    wrong_branch: 'Cabang berbeda',
    unexpected_presence: 'Seharusnya tidak berada di lokasi',
    status_mismatch: 'Status berbeda',
    condition_mismatch: 'Kondisi berbeda',
};

const assetStatusLabels: Record<string, string> = {
    available: 'Tersedia',
    reserved: 'Direservasi',
    rented: 'Sedang disewa',
    maintenance: 'Maintenance',
    in_transit: 'In Transit',
    lost: 'Hilang',
    retired: 'Dipensiunkan',
};

const conditionLabels: Record<string, string> = {
    good: 'Baik',
    fair: 'Cukup',
    damaged: 'Rusak',
};

const resolutionLabels: Record<string, string> = {
    accept_no_change: 'Terima tanpa perubahan master',
    update_condition: 'Perbarui kondisi aset',
    create_maintenance: 'Buat work order maintenance',
    mark_lost: 'Tandai aset hilang',
    transfer_required: 'Wajib proses melalui Transfer Aset',
    status_review_required: 'Review status melalui workflow sumber',
    adjust_quantity: 'Sesuaikan stok quantity',
};

const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function InventoryAuditShow({
    audit,
    items,
    summary,
    filters,
    permissions,
}: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState(filters.search);
    const [countItem, setCountItem] = useState<InventoryAuditItem | null>(null);
    const [resolveItem, setResolveItem] = useState<InventoryAuditItem | null>(
        null,
    );
    const [cameraOpen, setCameraOpen] = useState(false);
    const [approvalOpen, setApprovalOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const scanForm = useForm({ code: '' });
    const countForm = useForm({
        counted_quantity: 0,
        observed_status: 'available',
        observed_condition: 'good',
        notes: '',
        capture_source: 'camera',
        photos: [] as File[],
    });
    const approvalForm = useForm({ approval_notes: '' });
    const cancelForm = useForm({ reason: '' });
    const resolveForm = useForm({
        resolution_action: 'accept_no_change',
        resolution_notes: '',
    });

    const applyFilters = (changes: Record<string, string>) => {
        router.get(
            `/inventory-audits/${audit.id}`,
            {
                search,
                finding: filters.finding,
                tracking_type: filters.trackingType,
                ...changes,
            },
            { preserveState: true, replace: true },
        );
    };

    const runAction = async (
        path: string,
        title: string,
        description: string,
    ) => {
        const accepted = await confirm({
            title,
            description,
            confirmLabel: 'Lanjutkan',
        });

        if (accepted) {
            router.post(path, {}, { preserveScroll: true });
        }
    };

    const submitScan = (event: FormEvent) => {
        event.preventDefault();
        scanForm.post(`/inventory-audits/${audit.id}/scan`, {
            preserveScroll: true,
            onSuccess: () => scanForm.reset(),
        });
    };

    const openCount = (item: InventoryAuditItem) => {
        setCountItem(item);
        countForm.clearErrors();
        countForm.setData({
            counted_quantity: item.counted_quantity ?? item.expected_quantity,
            observed_status:
                item.observed_status ?? item.expected_status ?? 'available',
            observed_condition:
                item.observed_condition ?? item.expected_condition ?? 'good',
            notes: item.notes ?? '',
            capture_source: 'camera',
            photos: [],
        });
    };

    const submitCount = (event: FormEvent) => {
        event.preventDefault();

        if (!countItem) {
            return;
        }

        countForm.post(
            `/inventory-audits/${audit.id}/items/${countItem.id}/count`,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setCountItem(null),
            },
        );
    };

    const submitApproval = (event: FormEvent) => {
        event.preventDefault();
        approvalForm.post(`/inventory-audits/${audit.id}/approve`, {
            preserveScroll: true,
            onSuccess: () => setApprovalOpen(false),
        });
    };

    const submitCancel = (event: FormEvent) => {
        event.preventDefault();
        cancelForm.post(`/inventory-audits/${audit.id}/cancel`, {
            preserveScroll: true,
            onSuccess: () => setCancelOpen(false),
        });
    };

    const openResolution = (item: InventoryAuditItem) => {
        const action = defaultResolution(item);

        setResolveItem(item);
        resolveForm.clearErrors();
        resolveForm.setData({
            resolution_action: action,
            resolution_notes: '',
        });
    };

    const submitResolution = (event: FormEvent) => {
        event.preventDefault();

        if (!resolveItem) {
            return;
        }

        resolveForm.post(
            `/inventory-audits/${audit.id}/items/${resolveItem.id}/resolve`,
            {
                preserveScroll: true,
                onSuccess: () => setResolveItem(null),
            },
        );
    };

    const capturePhoto = (file: File) => {
        countForm.setData('photos', [...countForm.data.photos, file]);
        countForm.setData('capture_source', 'camera');
    };

    const canCancel =
        permissions.cancel &&
        ['draft', 'in_progress', 'submitted'].includes(audit.status);

    return (
        <>
            <Head title={audit.audit_number} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <ClipboardCheck className="size-6 text-primary" />
                            <h1 className="text-2xl font-semibold">
                                {audit.audit_number}
                            </h1>
                            <Badge variant="secondary">
                                {statusLabels[audit.status]}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {audit.title} · {audit.branch.code} —{' '}
                            {audit.branch.name}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/inventory-audits">Kembali</Link>
                        </Button>
                        {audit.status === 'draft' && permissions.count && (
                            <Button
                                onClick={() =>
                                    void runAction(
                                        `/inventory-audits/${audit.id}/start`,
                                        'Mulai stock opname?',
                                        'Snapshot akan dibuka untuk pencatatan fisik. Data master tetap aman sampai temuan disetujui.',
                                    )
                                }
                            >
                                <Play className="size-4" />
                                Mulai
                            </Button>
                        )}
                        {audit.status === 'in_progress' &&
                            permissions.count && (
                                <Button
                                    disabled={summary.pending > 0}
                                    onClick={() =>
                                        void runAction(
                                            `/inventory-audits/${audit.id}/submit`,
                                            'Ajukan hasil stock opname?',
                                            'Setelah diajukan, hasil hitung terkunci dan membutuhkan approval petugas berbeda.',
                                        )
                                    }
                                >
                                    <Send className="size-4" />
                                    Ajukan Hasil
                                </Button>
                            )}
                        {audit.status === 'submitted' &&
                            permissions.approve && (
                                <Button onClick={() => setApprovalOpen(true)}>
                                    <ShieldCheck className="size-4" />
                                    Approve
                                </Button>
                            )}
                        {audit.status === 'approved' && permissions.resolve && (
                            <Button
                                disabled={summary.unresolved > 0}
                                onClick={() =>
                                    void runAction(
                                        `/inventory-audits/${audit.id}/close`,
                                        'Tutup stock opname?',
                                        'Dokumen dan seluruh hasil pemeriksaan akan dikunci sebagai histori final.',
                                    )
                                }
                            >
                                <LockKeyhole className="size-4" />
                                Tutup Audit
                            </Button>
                        )}
                        {canCancel && (
                            <Button
                                variant="destructive"
                                onClick={() => setCancelOpen(true)}
                            >
                                <XCircle className="size-4" />
                                Batalkan
                            </Button>
                        )}
                    </div>
                </div>

                {audit.status === 'submitted' && (
                    <Alert>
                        <ShieldCheck className="size-4" />
                        <AlertTitle>Four-eyes approval aktif</AlertTitle>
                        <AlertDescription>
                            Petugas yang mengajukan hasil tidak dapat menyetujui
                            dokumen yang sama.
                        </AlertDescription>
                    </Alert>
                )}

                {audit.status === 'approved' && summary.unresolved > 0 && (
                    <Alert>
                        <FileWarning className="size-4" />
                        <AlertTitle>
                            {summary.unresolved} temuan belum ditindaklanjuti
                        </AlertTitle>
                        <AlertDescription>
                            Audit baru dapat ditutup setelah seluruh temuan
                            memiliki keputusan resolusi.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <Summary label="Total snapshot" value={summary.total} />
                    <Summary label="Belum dihitung" value={summary.pending} />
                    <Summary label="Sesuai / offsite" value={summary.matched} />
                    <Summary label="Temuan" value={summary.findings} />
                    <Summary
                        label="Belum diselesaikan"
                        value={summary.unresolved}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Audit</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm md:grid-cols-2 lg:grid-cols-4">
                        <Info
                            label="Dibuat oleh"
                            value={audit.creator?.name ?? '—'}
                        />
                        <Info
                            label="Jadwal"
                            value={formatDate(audit.scheduled_at)}
                        />
                        <Info
                            label="Diajukan oleh"
                            value={audit.submitter?.name ?? '—'}
                        />
                        <Info
                            label="Disetujui oleh"
                            value={audit.approver?.name ?? '—'}
                        />
                        <div className="md:col-span-2 lg:col-span-4">
                            <Info label="Catatan" value={audit.notes ?? '—'} />
                        </div>
                    </CardContent>
                </Card>

                {audit.status === 'in_progress' && permissions.count && (
                    <Card>
                        <CardContent className="pt-6">
                            <form
                                className="flex flex-col gap-3 sm:flex-row"
                                onSubmit={submitScan}
                            >
                                <div className="flex-1 space-y-2">
                                    <Label htmlFor="asset-code">
                                        Scan / masukkan kode aset atau serial
                                    </Label>
                                    <Input
                                        id="asset-code"
                                        value={scanForm.data.code}
                                        onChange={(event) =>
                                            scanForm.setData(
                                                'code',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Contoh: PNG-CAM-001"
                                        autoComplete="off"
                                    />
                                    <InputError
                                        message={scanForm.errors.code}
                                    />
                                </div>
                                <Button
                                    className="sm:self-end"
                                    type="submit"
                                    variant="secondary"
                                    disabled={scanForm.processing}
                                >
                                    <ScanLine className="size-4" />
                                    Temukan Unit
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <FilterBar
                    title="Filter item pemeriksaan"
                    description="Cari unit atau produk lalu persempit berdasarkan hasil audit dan tipe stok."
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters({});
                            }
                        }}
                        placeholder="Aset, serial, produk, SKU..."
                    />
                    <Select
                        value={filters.finding || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                finding: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Semua hasil" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua hasil</SelectItem>
                            {Object.entries(findingLabels).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.trackingType || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                tracking_type: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Semua tipe stok" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua tipe stok</SelectItem>
                            <SelectItem value="serialized">
                                Serialized
                            </SelectItem>
                            <SelectItem value="quantity">Quantity</SelectItem>
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
                        <CardTitle>Item Pemeriksaan</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1120px] text-sm">
                                <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Produk / Unit
                                        </th>
                                        <th className="px-4 py-3">
                                            Ekspektasi
                                        </th>
                                        <th className="px-4 py-3">Aktual</th>
                                        <th className="px-4 py-3">Hasil</th>
                                        <th className="px-4 py-3">Bukti</th>
                                        <th className="px-4 py-3">
                                            Tindak lanjut
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.data.map((item) => (
                                        <AuditItemRow
                                            key={item.id}
                                            item={item}
                                            audit={audit}
                                            permissions={permissions}
                                            onCount={() => openCount(item)}
                                            onResolve={() =>
                                                openResolution(item)
                                            }
                                        />
                                    ))}
                                    {items.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-4 py-12 text-center text-muted-foreground"
                                            >
                                                Tidak ada item sesuai filter.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="p-4">
                            <PaginationLinks
                                links={items.links}
                                from={items.from}
                                to={items.to}
                                total={items.total}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={countItem !== null}
                onOpenChange={(open) => !open && setCountItem(null)}
            >
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Catat Pemeriksaan Fisik</DialogTitle>
                        <DialogDescription>
                            {countItem?.asset?.asset_code ??
                                countItem?.product.name}
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitCount}>
                        <Field
                            label={
                                countItem?.tracking_type === 'serialized'
                                    ? 'Keberadaan unit'
                                    : 'Jumlah fisik di cabang'
                            }
                            error={countForm.errors.counted_quantity}
                        >
                            {countItem?.tracking_type === 'serialized' ? (
                                <Select
                                    value={String(
                                        countForm.data.counted_quantity,
                                    )}
                                    onValueChange={(value) =>
                                        countForm.setData(
                                            'counted_quantity',
                                            Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">
                                            Unit ditemukan
                                        </SelectItem>
                                        <SelectItem value="0">
                                            Unit tidak ditemukan / offsite
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    type="number"
                                    min={0}
                                    value={countForm.data.counted_quantity}
                                    onChange={(event) =>
                                        countForm.setData(
                                            'counted_quantity',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            )}
                        </Field>
                        {countItem?.tracking_type === 'serialized' &&
                            countForm.data.counted_quantity === 1 && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        label="Status aktual"
                                        error={countForm.errors.observed_status}
                                    >
                                        <Select
                                            value={
                                                countForm.data.observed_status
                                            }
                                            onValueChange={(value) =>
                                                countForm.setData(
                                                    'observed_status',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(
                                                    assetStatusLabels,
                                                ).map(([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    <Field
                                        label="Kondisi aktual"
                                        error={
                                            countForm.errors.observed_condition
                                        }
                                    >
                                        <Select
                                            value={
                                                countForm.data
                                                    .observed_condition
                                            }
                                            onValueChange={(value) =>
                                                countForm.setData(
                                                    'observed_condition',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(
                                                    conditionLabels,
                                                ).map(([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                </div>
                            )}
                        <Field label="Catatan" error={countForm.errors.notes}>
                            <textarea
                                className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={countForm.data.notes}
                                onChange={(event) =>
                                    countForm.setData(
                                        'notes',
                                        event.target.value,
                                    )
                                }
                                placeholder="Lokasi rak, kelengkapan, atau detail temuan..."
                            />
                        </Field>
                        <div className="space-y-2">
                            <Label>Bukti kamera realtime</Label>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setCameraOpen(true)}
                                >
                                    <Camera className="size-4" />
                                    Buka Kamera
                                </Button>
                                <Badge variant="outline">
                                    {countForm.data.photos.length} foto baru
                                </Badge>
                            </div>
                            {countForm.data.photos.length > 0 && (
                                <ul className="space-y-1 text-xs text-muted-foreground">
                                    {countForm.data.photos.map(
                                        (photo, index) => (
                                            <li key={`${photo.name}-${index}`}>
                                                {index + 1}. {photo.name}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                            <InputError message={countForm.errors.photos} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                disabled={countForm.processing}
                            >
                                Simpan Pemeriksaan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={approvalOpen} onOpenChange={setApprovalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Approve Hasil Stock Opname</DialogTitle>
                        <DialogDescription>
                            Approval tidak langsung memindahkan lokasi atau
                            mengubah status aset. Temuan diproses satu per satu.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitApproval}>
                        <Field
                            label="Catatan approval"
                            error={approvalForm.errors.approval_notes}
                        >
                            <textarea
                                className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={approvalForm.data.approval_notes}
                                onChange={(event) =>
                                    approvalForm.setData(
                                        'approval_notes',
                                        event.target.value,
                                    )
                                }
                                placeholder="Wajib diisi jika terdapat temuan..."
                            />
                        </Field>
                        <DialogFooter>
                            <Button
                                type="submit"
                                disabled={approvalForm.processing}
                            >
                                Approve Hasil
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Batalkan Stock Opname</DialogTitle>
                        <DialogDescription>
                            Snapshot dan histori tetap tersimpan sebagai dokumen
                            dibatalkan.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitCancel}>
                        <Field
                            label="Alasan pembatalan"
                            error={cancelForm.errors.reason}
                        >
                            <textarea
                                className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={cancelForm.data.reason}
                                onChange={(event) =>
                                    cancelForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <DialogFooter>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={cancelForm.processing}
                            >
                                Batalkan Audit
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={resolveItem !== null}
                onOpenChange={(open) => !open && setResolveItem(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tindak Lanjut Temuan</DialogTitle>
                        <DialogDescription>
                            {resolveItem?.asset?.asset_code ??
                                resolveItem?.product.name}
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitResolution}>
                        <Field
                            label="Tindakan"
                            error={resolveForm.errors.resolution_action}
                        >
                            <Select
                                value={resolveForm.data.resolution_action}
                                onValueChange={(value) =>
                                    resolveForm.setData(
                                        'resolution_action',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {resolveItem &&
                                        resolutionOptions(resolveItem).map(
                                            (action) => (
                                                <SelectItem
                                                    key={action}
                                                    value={action}
                                                >
                                                    {resolutionLabels[action]}
                                                </SelectItem>
                                            ),
                                        )}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label="Catatan resolusi"
                            error={resolveForm.errors.resolution_notes}
                        >
                            <textarea
                                className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={resolveForm.data.resolution_notes}
                                onChange={(event) =>
                                    resolveForm.setData(
                                        'resolution_notes',
                                        event.target.value,
                                    )
                                }
                                placeholder="Keputusan, PIC, dan tindak lanjut yang dilakukan..."
                            />
                        </Field>
                        <DialogFooter>
                            <Button
                                type="submit"
                                disabled={resolveForm.processing}
                            >
                                Simpan Resolusi
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <TransferCameraDialog
                open={cameraOpen}
                onOpenChange={setCameraOpen}
                onCapture={capturePhoto}
                filenamePrefix={`stock-opname-${audit.audit_number}`}
            />
        </>
    );
}

function AuditItemRow({
    item,
    audit,
    permissions,
    onCount,
    onResolve,
}: {
    item: InventoryAuditItem;
    audit: InventoryAudit;
    permissions: InventoryAuditPermissions;
    onCount: () => void;
    onResolve: () => void;
}) {
    const isFinding = ['discrepancy', 'missing', 'unexpected'].includes(
        item.finding_status,
    );

    return (
        <tr className="border-b align-top last:border-0">
            <td className="px-4 py-4">
                <p className="font-medium">
                    {item.asset?.asset_code ?? item.product.name}
                </p>
                <p className="text-xs text-muted-foreground">
                    {item.asset
                        ? `${item.product.name} · ${item.asset.serial_number ?? 'Tanpa serial'}`
                        : `${item.product.sku} · stok quantity`}
                </p>
            </td>
            <td className="px-4 py-4">
                <p>{item.expected_branch?.code ?? audit.branch.code}</p>
                <p className="text-xs text-muted-foreground">
                    Qty {item.expected_quantity}
                    {item.expected_status
                        ? ` · ${assetStatusLabels[item.expected_status] ?? item.expected_status}`
                        : ''}
                    {item.expected_condition
                        ? ` · ${conditionLabels[item.expected_condition] ?? item.expected_condition}`
                        : ''}
                </p>
            </td>
            <td className="px-4 py-4">
                {item.counted_quantity === null ? (
                    '—'
                ) : (
                    <>
                        <p>Qty {item.counted_quantity}</p>
                        <p className="text-xs text-muted-foreground">
                            {item.observed_status
                                ? (assetStatusLabels[item.observed_status] ??
                                  item.observed_status)
                                : ''}
                            {item.observed_condition
                                ? ` · ${conditionLabels[item.observed_condition] ?? item.observed_condition}`
                                : ''}
                        </p>
                    </>
                )}
            </td>
            <td className="px-4 py-4">
                <Badge variant={findingVariant(item.finding_status)}>
                    {findingLabels[item.finding_status]}
                </Badge>
                {(item.issue_flags?.length ?? 0) > 0 && (
                    <ul className="mt-2 space-y-1 text-xs text-muted-foreground">
                        {item.issue_flags?.map((issue) => (
                            <li key={issue}>{issueLabels[issue] ?? issue}</li>
                        ))}
                    </ul>
                )}
            </td>
            <td className="px-4 py-4">
                {item.media.length > 0 ? (
                    <div className="flex max-w-40 flex-wrap gap-2">
                        {item.media.map((media, index) => (
                            <a
                                key={media.id}
                                href={`/inventory-audits/media/${media.id}`}
                                target="_blank"
                                rel="noreferrer"
                                className="block"
                            >
                                <img
                                    src={`/inventory-audits/media/${media.id}`}
                                    alt={`Bukti ${index + 1}`}
                                    className="size-12 rounded-md border object-cover"
                                />
                            </a>
                        ))}
                    </div>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </td>
            <td className="px-4 py-4">
                {item.resolved_at ? (
                    <div className="flex items-start gap-2">
                        <CheckCircle2 className="mt-0.5 size-4 text-emerald-600" />
                        <div>
                            <p className="text-xs font-medium">
                                {resolutionLabels[
                                    item.resolution_action ?? ''
                                ] ?? item.resolution_action}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {item.resolver?.name ?? '—'}
                            </p>
                        </div>
                    </div>
                ) : isFinding ? (
                    <span className="text-xs text-amber-700">
                        Menunggu tindak lanjut
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </td>
            <td className="px-4 py-4 text-right">
                {audit.status === 'in_progress' && permissions.count && (
                    <Button size="sm" variant="outline" onClick={onCount}>
                        <ClipboardCheck className="size-4" />
                        {item.counted_at ? 'Koreksi' : 'Hitung'}
                    </Button>
                )}
                {audit.status === 'approved' &&
                    permissions.resolve &&
                    isFinding &&
                    !item.resolved_at && (
                        <Button size="sm" onClick={onResolve}>
                            Tindak Lanjut
                        </Button>
                    )}
            </td>
        </tr>
    );
}

function Summary({ label, value }: { label: string; value: number }) {
    return <MetricCard label={label} value={value} />;
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 font-medium">{value}</p>
        </div>
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
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function formatDate(value: string | null): string {
    return value ? dateTime.format(new Date(value)) : '—';
}

function findingVariant(
    finding: InventoryFindingStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (['missing', 'unexpected'].includes(finding)) {
        return 'destructive';
    }

    if (finding === 'discrepancy') {
        return 'default';
    }

    if (finding === 'pending') {
        return 'outline';
    }

    return 'secondary';
}

function resolutionOptions(item: InventoryAuditItem): string[] {
    if (item.tracking_type === 'quantity') {
        return ['adjust_quantity', 'accept_no_change'];
    }

    if (item.finding_status === 'unexpected') {
        return ['transfer_required', 'accept_no_change'];
    }

    if (item.finding_status === 'missing') {
        return ['mark_lost', 'transfer_required', 'accept_no_change'];
    }

    const actions = [
        'update_condition',
        'create_maintenance',
        'accept_no_change',
    ];

    if (item.issue_flags?.includes('status_mismatch')) {
        actions.unshift('status_review_required');
    }

    return actions;
}

function defaultResolution(item: InventoryAuditItem): string {
    return resolutionOptions(item)[0] ?? 'accept_no_change';
}
