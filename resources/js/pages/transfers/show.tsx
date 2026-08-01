import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Camera,
    CheckCircle2,
    CircleDollarSign,
    FileText,
    PackageCheck,
    Pencil,
    Send,
    ShieldCheck,
    Truck,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { TransferCameraDialog } from '@/components/transfers/transfer-camera-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RupiahInput } from '@/components/ui/rupiah-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    Transfer,
    TransferExpense,
    TransferItem,
    TransferPermissions,
    TransferStatus,
} from '@/types';

type CapturePolicy = {
    capture_mode: string;
    min_photos: number;
    require_waybill: boolean;
    allow_gallery_override: boolean;
};

type Props = {
    transfer: Transfer;
    dispatchSettings: CapturePolicy;
    receivingSettings: CapturePolicy;
    financialCategories: Array<{ id: number; code: string; name: string }>;
    paymentMethods: Array<{
        id: number;
        code: string;
        name: string;
        type: string;
        requires_reference: boolean;
    }>;
    cashSessions: Array<{
        id: number;
        register_name: string;
        opened_at: string;
    }>;
    permissions: TransferPermissions;
    currentBranchId: number;
};

type EvidenceRow = {
    item_id: number;
    condition: string;
    checklist: Record<string, boolean>;
    notes: string;
    capture_source: 'camera' | 'gallery' | 'override';
    photos: File[];
};

type ReceivingRow = EvidenceRow & {
    receiving_result:
        | 'accepted_good'
        | 'accepted_damaged'
        | 'incomplete'
        | 'missing'
        | 'rejected';
    quantity: number;
};

function buildReceivingRows(items: TransferItem[]): ReceivingRow[] {
    return items.map((item) => ({
        item_id: item.id,
        receiving_result: 'accepted_good',
        quantity: Math.max(1, item.quantity - item.received_quantity),
        condition: item.asset?.condition ?? 'good',
        checklist: {
            body: true,
            function: true,
            accessories: true,
        },
        notes: '',
        capture_source: 'camera',
        photos: [],
    }));
}

const statusLabels: Record<TransferStatus, string> = {
    draft: 'Draft',
    pending_approval: 'Menunggu Approval',
    approved: 'Disetujui / Hold',
    dispatched: 'In Transit',
    receiving: 'Penerimaan Parsial',
    discrepancy: 'Discrepancy',
    completed: 'Selesai',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});
const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function TransferShow({
    transfer,
    dispatchSettings,
    receivingSettings,
    financialCategories,
    paymentMethods,
    cashSessions,
    permissions,
    currentBranchId,
}: Props) {
    const [approvalNote, setApprovalNote] = useState('');
    const [dispatchOpen, setDispatchOpen] = useState(false);
    const [receivingOpen, setReceivingOpen] = useState(false);
    const [expenseOpen, setExpenseOpen] = useState(false);
    const [cameraTarget, setCameraTarget] = useState<{
        mode: 'dispatch' | 'receiving';
        index: number;
    } | null>(null);

    const activeItems = useMemo(
        () =>
            transfer.items.filter(
                (item) =>
                    !['received', 'resolved', 'discrepancy'].includes(
                        item.status,
                    ),
            ),
        [transfer.items],
    );

    const dispatch = useForm({
        shipping_method: transfer.shipping_method ?? 'internal',
        courier_name: transfer.courier_name ?? '',
        courier_phone: transfer.courier_phone ?? '',
        vehicle_number: transfer.vehicle_number ?? '',
        tracking_number: transfer.tracking_number ?? '',
        waybill_number: transfer.waybill_number ?? '',
        seal_number: transfer.seal_number ?? '',
        shipping_notes: transfer.shipping_notes ?? '',
        waybill_file: null as File | null,
        override_reason: '',
        inspections: transfer.items.map<EvidenceRow>((item) => ({
            item_id: item.id,
            condition: item.condition_before ?? item.asset?.condition ?? 'good',
            checklist: {
                body: true,
                function: true,
                accessories: true,
            },
            notes: '',
            capture_source: 'camera',
            photos: [],
        })),
    });

    const receiving = useForm({
        receiving_notes: transfer.receiving_notes ?? '',
        override_reason: '',
        items: buildReceivingRows(activeItems),
    });

    const expense = useForm({
        expense_branch_id: currentBranchId,
        financial_category_id:
            financialCategories.find(
                (category) => category.code === 'TRANSFER-SHIPPING',
            )?.id ??
            financialCategories[0]?.id ??
            0,
        expense_type: 'shipping',
        estimated_amount: 0,
        actual_amount: 0,
        vendor_name: '',
        external_reference: '',
        notes: '',
        proof: null as File | null,
    });

    const approvalFor = (side: 'origin' | 'destination') =>
        transfer.approvals.find(
            (approval) =>
                approval.side === side &&
                approval.revision_number === transfer.revision_number,
        );

    const decide = (
        side: 'origin' | 'destination',
        decision: 'approved' | 'rejected',
    ) => {
        const notes =
            decision === 'rejected'
                ? window.prompt(
                      'Alasan penolakan minimal 5 karakter:',
                      approvalNote,
                  )
                : approvalNote;

        if (decision === 'rejected' && (!notes || notes.trim().length < 5)) {
            return;
        }

        router.post(
            `/transfers/${transfer.id}/approvals`,
            {
                side,
                decision,
                notes,
                revision_number: transfer.revision_number,
            },
            { preserveScroll: true },
        );
    };

    const cancelTransfer = () => {
        const reason = window.prompt('Alasan pembatalan minimal 5 karakter:');

        if (!reason || reason.trim().length < 5) {
            return;
        }

        router.post(
            `/transfers/${transfer.id}/cancel`,
            { reason },
            { preserveScroll: true },
        );
    };

    const submitDispatch = (event: FormEvent) => {
        event.preventDefault();
        dispatch.post(`/transfers/${transfer.id}/dispatch`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setDispatchOpen(false),
        });
    };

    const submitReceiving = (event: FormEvent) => {
        event.preventDefault();
        receiving.post(`/transfers/${transfer.id}/receipts`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setReceivingOpen(false),
        });
    };

    const submitExpense = (event: FormEvent) => {
        event.preventDefault();
        expense.post(`/transfers/${transfer.id}/expenses`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                expense.reset();
                setExpenseOpen(false);
            },
        });
    };

    const updateDispatchEvidence = <K extends keyof EvidenceRow>(
        index: number,
        key: K,
        value: EvidenceRow[K],
    ) => {
        dispatch.setData((current) => {
            const next = [...current.inspections];
            const row = next[index];

            if (!row) {
                return current;
            }

            next[index] = { ...row, [key]: value };

            return { ...current, inspections: next };
        });
    };

    const updateReceivingEvidence = <K extends keyof ReceivingRow>(
        index: number,
        key: K,
        value: ReceivingRow[K],
    ) => {
        receiving.setData((current) => {
            const next = [...current.items];
            const row = next[index];

            if (!row) {
                return current;
            }

            next[index] = { ...row, [key]: value };

            return { ...current, items: next };
        });
    };

    const capturePhoto = (file: File) => {
        if (!cameraTarget) {
            return;
        }

        if (cameraTarget.mode === 'dispatch') {
            dispatch.setData((current) => {
                const next = [...current.inspections];
                const row = next[cameraTarget.index];

                if (!row) {
                    return current;
                }

                next[cameraTarget.index] = {
                    ...row,
                    photos: [...row.photos, file],
                    capture_source: 'camera',
                };

                return { ...current, inspections: next };
            });

            return;
        }

        receiving.setData((current) => {
            const next = [...current.items];
            const row = next[cameraTarget.index];

            if (!row) {
                return current;
            }

            next[cameraTarget.index] = {
                ...row,
                photos: [...row.photos, file],
                capture_source: 'camera',
            };

            return { ...current, items: next };
        });
    };

    const canEdit =
        permissions.update &&
        ['draft', 'pending_approval', 'approved', 'rejected'].includes(
            transfer.status,
        );
    const canDispatch =
        permissions.dispatch &&
        transfer.status === 'approved' &&
        currentBranchId === transfer.from_branch_id;
    const canReceive =
        permissions.receive &&
        ['dispatched', 'receiving', 'discrepancy'].includes(transfer.status) &&
        currentBranchId === transfer.to_branch_id;

    const openDispatch = () => {
        dispatch.clearErrors();
        setDispatchOpen(true);
    };

    const openReceiving = () => {
        receiving.clearErrors();
        receiving.setData('receiving_notes', transfer.receiving_notes ?? '');
        receiving.setData('override_reason', '');
        receiving.setData('items', buildReceivingRows(activeItems));
        setReceivingOpen(true);
    };

    return (
        <>
            <Head title={transfer.transfer_number} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold">
                                {transfer.transfer_number}
                            </h1>
                            <Badge variant="secondary">
                                {statusLabels[transfer.status]}
                            </Badge>
                            <Badge variant="outline">
                                Revisi {transfer.revision_number}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {transfer.origin_branch.name} →{' '}
                            {transfer.destination_branch.name}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit && (
                            <Button variant="outline" asChild>
                                <Link href={`/transfers/${transfer.id}/edit`}>
                                    <Pencil className="size-4" />
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {transfer.status === 'draft' && permissions.create && (
                            <Button
                                variant="secondary"
                                onClick={() =>
                                    router.post(
                                        `/transfers/${transfer.id}/submit`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Send className="size-4" />
                                Ajukan & Setujui
                            </Button>
                        )}
                        {permissions.cancel &&
                            [
                                'draft',
                                'pending_approval',
                                'approved',
                                'rejected',
                            ].includes(transfer.status) && (
                                <Button
                                    variant="destructive"
                                    onClick={cancelTransfer}
                                >
                                    <XCircle className="size-4" />
                                    Batalkan
                                </Button>
                            )}
                    </div>
                </div>

                {transfer.status === 'approved' && (
                    <Alert>
                        <ShieldCheck className="size-4" />
                        <AlertTitle>Aset sudah dikunci realtime</AlertTitle>
                        <AlertDescription>
                            Seluruh unit transfer berstatus In Transit dan tidak
                            dapat dipakai untuk booking, checkout, maintenance,
                            atau transfer lain. Lokasi fisik masih tercatat di
                            cabang asal sampai receiving selesai.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Ringkasan Transfer</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Detail
                                label="Cabang asal"
                                value={`${transfer.origin_branch.code} — ${transfer.origin_branch.name}`}
                            />
                            <Detail
                                label="Cabang tujuan"
                                value={`${transfer.destination_branch.code} — ${transfer.destination_branch.name}`}
                            />
                            <Detail
                                label="Rencana berangkat"
                                value={formatDate(transfer.planned_dispatch_at)}
                            />
                            <Detail
                                label="Estimasi tiba"
                                value={formatDate(transfer.expected_arrival_at)}
                            />
                            <Detail
                                label="Waktu dispatch"
                                value={formatDate(transfer.shipped_at)}
                            />
                            <Detail
                                label="Waktu receiving"
                                value={formatDate(transfer.received_at)}
                            />
                            <div className="sm:col-span-2">
                                <Detail
                                    label="Alasan"
                                    value={transfer.reason ?? '-'}
                                />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Pengiriman</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <Detail
                                label="Metode"
                                value={transfer.shipping_method ?? '-'}
                            />
                            <Detail
                                label="Kurir"
                                value={transfer.courier_name ?? '-'}
                            />
                            <Detail
                                label="Kendaraan"
                                value={transfer.vehicle_number ?? '-'}
                            />
                            <Detail
                                label="Surat jalan"
                                value={transfer.waybill_number ?? '-'}
                            />
                            <Detail
                                label="Resi"
                                value={transfer.tracking_number ?? '-'}
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Persetujuan Dua Pihak</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Input
                            value={approvalNote}
                            onChange={(event) =>
                                setApprovalNote(event.target.value)
                            }
                            placeholder="Catatan persetujuan opsional"
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <ApprovalCard
                                title="Cabang Asal"
                                branch={transfer.origin_branch.name}
                                approval={approvalFor('origin')}
                                canDecide={
                                    permissions.approve &&
                                    transfer.status === 'pending_approval' &&
                                    currentBranchId ===
                                        transfer.from_branch_id &&
                                    !approvalFor('origin')
                                }
                                onApprove={() => decide('origin', 'approved')}
                                onReject={() => decide('origin', 'rejected')}
                            />
                            <ApprovalCard
                                title="Cabang Tujuan"
                                branch={transfer.destination_branch.name}
                                approval={approvalFor('destination')}
                                canDecide={
                                    permissions.approve &&
                                    transfer.status === 'pending_approval' &&
                                    currentBranchId === transfer.to_branch_id &&
                                    !approvalFor('destination')
                                }
                                onApprove={() =>
                                    decide('destination', 'approved')
                                }
                                onReject={() =>
                                    decide('destination', 'rejected')
                                }
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Item dan Pemeriksaan</CardTitle>
                        <div className="flex gap-2">
                            {canDispatch && (
                                <Button onClick={openDispatch}>
                                    <Truck className="size-4" />
                                    Proses Dispatch
                                </Button>
                            )}
                            {canReceive && activeItems.length > 0 && (
                                <Button onClick={openReceiving}>
                                    <PackageCheck className="size-4" />
                                    Proses Receiving
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {transfer.items.map((item) => (
                            <ItemCard
                                key={item.id}
                                item={item}
                                transfer={transfer}
                                permissions={permissions}
                                currentBranchId={currentBranchId}
                            />
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Biaya Transfer</CardTitle>
                        {permissions.expense && (
                            <Button
                                variant="outline"
                                onClick={() => setExpenseOpen(true)}
                            >
                                <CircleDollarSign className="size-4" />
                                Catat Biaya
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {transfer.expenses.map((row) => (
                            <ExpenseRow
                                key={row.id}
                                expense={row}
                                transferId={transfer.id}
                                paymentMethods={paymentMethods}
                                cashSessions={cashSessions}
                                canManage={permissions.expense}
                            />
                        ))}
                        {transfer.expenses.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Belum ada biaya pengiriman yang dicatat.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Dokumen</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {transfer.documents.map((document) => (
                                <Button
                                    key={document.id}
                                    variant="ghost"
                                    className="w-full justify-start"
                                    asChild
                                >
                                    <a
                                        href={`/transfers/documents/${document.id}`}
                                    >
                                        <FileText className="size-4" />
                                        {document.original_name ??
                                            document.document_type}
                                    </a>
                                </Button>
                            ))}
                            {transfer.documents.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada dokumen transfer.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {transfer.status_histories?.map((history) => (
                                <div
                                    key={history.id}
                                    className="border-l-2 pl-3 text-sm"
                                >
                                    <p className="font-medium">
                                        {history.from_status ?? 'awal'} →{' '}
                                        {history.to_status}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {history.reason ?? '-'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {history.changer?.name ?? 'Sistem'} •{' '}
                                        {formatDate(history.changed_at)}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <DispatchDialog
                open={dispatchOpen}
                onOpenChange={setDispatchOpen}
                transfer={transfer}
                policy={{
                    ...dispatchSettings,
                    allow_gallery_override:
                        dispatchSettings.allow_gallery_override &&
                        permissions.override,
                }}
                form={dispatch}
                onSubmit={submitDispatch}
                onUpdateEvidence={updateDispatchEvidence}
                onOpenCamera={(index) =>
                    setCameraTarget({ mode: 'dispatch', index })
                }
            />
            <ReceivingDialog
                open={receivingOpen}
                onOpenChange={setReceivingOpen}
                items={activeItems}
                policy={{
                    ...receivingSettings,
                    allow_gallery_override:
                        receivingSettings.allow_gallery_override &&
                        permissions.override,
                }}
                form={receiving}
                onSubmit={submitReceiving}
                onUpdateEvidence={updateReceivingEvidence}
                onOpenCamera={(index) =>
                    setCameraTarget({ mode: 'receiving', index })
                }
            />
            <ExpenseDialog
                open={expenseOpen}
                onOpenChange={setExpenseOpen}
                transfer={transfer}
                categories={financialCategories}
                form={expense}
                onSubmit={submitExpense}
            />
            <TransferCameraDialog
                open={cameraTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCameraTarget(null);
                    }
                }}
                onCapture={capturePhoto}
                filenamePrefix={`${transfer.transfer_number}-${cameraTarget?.mode ?? 'photo'}`}
            />
        </>
    );
}

function ApprovalCard({
    title,
    branch,
    approval,
    canDecide,
    onApprove,
    onReject,
}: {
    title: string;
    branch: string;
    approval: Transfer['approvals'][number] | undefined;
    canDecide: boolean;
    onApprove: () => void;
    onReject: () => void;
}) {
    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-medium">{title}</p>
                    <p className="text-sm text-muted-foreground">{branch}</p>
                </div>
                <Badge variant={approval ? 'secondary' : 'outline'}>
                    {approval ? approval.decision : 'Menunggu'}
                </Badge>
            </div>
            {approval && (
                <div className="mt-3 text-sm">
                    <p>{approval.decider?.name ?? 'Sistem'}</p>
                    <p className="text-muted-foreground">
                        {formatDate(approval.decided_at)}
                    </p>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Hash: {approval.snapshot_hash.slice(0, 16)}…
                    </p>
                </div>
            )}
            {canDecide && (
                <div className="mt-4 flex gap-2">
                    <Button size="sm" onClick={onApprove}>
                        <CheckCircle2 className="size-4" />
                        Setujui
                    </Button>
                    <Button size="sm" variant="destructive" onClick={onReject}>
                        Tolak
                    </Button>
                </div>
            )}
        </div>
    );
}

function ItemCard({
    item,
    transfer,
    permissions,
    currentBranchId,
}: {
    item: TransferItem;
    transfer: Transfer;
    permissions: TransferPermissions;
    currentBranchId: number;
}) {
    const resolve = (action: string) => {
        const notes = window.prompt('Catatan penyelesaian minimal 5 karakter:');

        if (!notes || notes.trim().length < 5) {
            return;
        }

        router.post(
            `/transfers/${transfer.id}/items/${item.id}/resolve`,
            { resolution_action: action, notes },
            { preserveScroll: true },
        );
    };

    return (
        <div className="rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-medium">
                        {item.line_number}. {item.product.name}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {item.asset
                            ? `${item.asset.asset_code} • ${item.asset.serial_number ?? 'Tanpa serial'}`
                            : `${item.received_quantity}/${item.quantity} unit diterima`}
                    </p>
                </div>
                <Badge variant="outline">{item.status}</Badge>
            </div>
            <div className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                <Detail
                    label="Kondisi kirim"
                    value={item.condition_before ?? '-'}
                />
                <Detail
                    label="Kondisi terima"
                    value={item.condition_after ?? '-'}
                />
                <Detail label="Hasil" value={item.receiving_result ?? '-'} />
            </div>
            {item.inspections.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {item.inspections.flatMap((inspection) =>
                        inspection.media.map((media) => (
                            <a
                                key={media.id}
                                href={`/transfers/inspection-media/${media.id}`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Badge variant="secondary">
                                    <Camera className="mr-1 size-3" />
                                    {inspection.type} • {media.capture_source}
                                </Badge>
                            </a>
                        )),
                    )}
                </div>
            )}

            {item.status === 'discrepancy' && (
                <Alert variant="destructive" className="mt-4">
                    <AlertTitle>
                        Discrepancy: {item.discrepancy_type}
                    </AlertTitle>
                    <AlertDescription>
                        {item.discrepancy_notes ?? 'Perlu keputusan lanjutan.'}
                    </AlertDescription>
                </Alert>
            )}

            {item.status === 'discrepancy' &&
                permissions.resolve &&
                currentBranchId === transfer.to_branch_id && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            onClick={() => resolve('accept_at_destination')}
                        >
                            Terima di Tujuan
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => resolve('return_to_origin')}
                        >
                            Kembalikan ke Asal
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => resolve('mark_lost')}
                        >
                            Tandai Hilang
                        </Button>
                    </div>
                )}
        </div>
    );
}

function DispatchDialog({
    open,
    onOpenChange,
    transfer,
    policy,
    form,
    onSubmit,
    onUpdateEvidence,
    onOpenCamera,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transfer: Transfer;
    policy: CapturePolicy;
    form: ReturnType<
        typeof useForm<{
            shipping_method: string;
            courier_name: string;
            courier_phone: string;
            vehicle_number: string;
            tracking_number: string;
            waybill_number: string;
            seal_number: string;
            shipping_notes: string;
            waybill_file: File | null;
            override_reason: string;
            inspections: EvidenceRow[];
        }>
    >;
    onSubmit: (event: FormEvent) => void;
    onUpdateEvidence: <K extends keyof EvidenceRow>(
        index: number,
        key: K,
        value: EvidenceRow[K],
    ) => void;
    onOpenCamera: (index: number) => void;
}) {
    const errorMessages = Object.values(form.errors).filter(
        (message): message is string =>
            typeof message === 'string' && message.trim() !== '',
    );
    const incompleteEvidenceCount = form.data.inspections.filter(
        (inspection) => inspection.photos.length < policy.min_photos,
    ).length;
    const waybillMissing =
        policy.require_waybill && form.data.waybill_number.trim() === '';
    const confirmDisabled =
        form.processing || incompleteEvidenceCount > 0 || waybillMissing;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-5xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Dispatch Transfer</DialogTitle>
                </DialogHeader>
                <form className="space-y-5" onSubmit={onSubmit}>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Metode pengiriman">
                            <Select
                                value={form.data.shipping_method}
                                onValueChange={(value) =>
                                    form.setData('shipping_method', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="internal">
                                        Internal
                                    </SelectItem>
                                    <SelectItem value="courier">
                                        Kurir
                                    </SelectItem>
                                    <SelectItem value="expedition">
                                        Ekspedisi
                                    </SelectItem>
                                    <SelectItem value="other">
                                        Lainnya
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Nama kurir">
                            <Input
                                value={form.data.courier_name}
                                onChange={(event) =>
                                    form.setData(
                                        'courier_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Nomor kendaraan">
                            <Input
                                value={form.data.vehicle_number}
                                onChange={(event) =>
                                    form.setData(
                                        'vehicle_number',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Nomor surat jalan">
                            <Input
                                value={form.data.waybill_number}
                                onChange={(event) =>
                                    form.setData(
                                        'waybill_number',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Nomor resi">
                            <Input
                                value={form.data.tracking_number}
                                onChange={(event) =>
                                    form.setData(
                                        'tracking_number',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="File surat jalan">
                            <Input
                                type="file"
                                accept=".pdf,image/*"
                                onChange={(event) =>
                                    form.setData(
                                        'waybill_file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <EvidenceList
                        items={transfer.items}
                        rows={form.data.inspections}
                        policy={policy}
                        onUpdate={onUpdateEvidence}
                        onOpenCamera={onOpenCamera}
                    />
                    {policy.allow_gallery_override && (
                        <Field label="Alasan override galeri">
                            <Input
                                value={form.data.override_reason}
                                onChange={(event) =>
                                    form.setData(
                                        'override_reason',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    )}
                    {errorMessages.length > 0 && (
                        <Alert variant="destructive">
                            <AlertTitle>
                                Dispatch belum dapat diproses
                            </AlertTitle>
                            <AlertDescription>
                                <ul className="list-disc space-y-1 pl-5">
                                    {errorMessages.map((message) => (
                                        <li key={message}>{message}</li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    )}
                    {incompleteEvidenceCount > 0 && (
                        <p className="text-sm text-destructive">
                            Ambil minimal {policy.min_photos} foto pada setiap
                            item sebelum konfirmasi dispatch.
                        </p>
                    )}
                    {waybillMissing && (
                        <p className="text-sm text-destructive">
                            Nomor surat jalan wajib diisi.
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={confirmDisabled}>
                            Konfirmasi Dispatch
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ReceivingDialog({
    open,
    onOpenChange,
    items,
    policy,
    form,
    onSubmit,
    onUpdateEvidence,
    onOpenCamera,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    items: TransferItem[];
    policy: CapturePolicy;
    form: ReturnType<
        typeof useForm<{
            receiving_notes: string;
            override_reason: string;
            items: ReceivingRow[];
        }>
    >;
    onSubmit: (event: FormEvent) => void;
    onUpdateEvidence: <K extends keyof ReceivingRow>(
        index: number,
        key: K,
        value: ReceivingRow[K],
    ) => void;
    onOpenCamera: (index: number) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-5xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Receiving Transfer</DialogTitle>
                </DialogHeader>
                <form className="space-y-5" onSubmit={onSubmit}>
                    {items.map((item, index) => {
                        const row = form.data.items[index];

                        return (
                            <div
                                key={item.id}
                                className="space-y-3 rounded-lg border p-4"
                            >
                                <div>
                                    <p className="font-medium">
                                        {item.product.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {item.asset?.asset_code ??
                                            `${item.quantity} unit`}
                                    </p>
                                </div>
                                <div className="grid gap-3 md:grid-cols-3">
                                    <Field label="Hasil penerimaan">
                                        <Select
                                            value={row.receiving_result}
                                            onValueChange={(value) =>
                                                onUpdateEvidence(
                                                    index,
                                                    'receiving_result',
                                                    value as ReceivingRow['receiving_result'],
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="accepted_good">
                                                    Diterima baik
                                                </SelectItem>
                                                <SelectItem value="accepted_damaged">
                                                    Diterima rusak
                                                </SelectItem>
                                                <SelectItem value="incomplete">
                                                    Kelengkapan kurang
                                                </SelectItem>
                                                <SelectItem value="missing">
                                                    Belum tiba / hilang
                                                </SelectItem>
                                                <SelectItem value="rejected">
                                                    Ditolak
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    <Field label="Kondisi">
                                        <Select
                                            value={row.condition}
                                            onValueChange={(value) =>
                                                onUpdateEvidence(
                                                    index,
                                                    'condition',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="good">
                                                    Baik
                                                </SelectItem>
                                                <SelectItem value="fair">
                                                    Cukup
                                                </SelectItem>
                                                <SelectItem value="damaged">
                                                    Rusak
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    {!item.asset && (
                                        <Field label="Jumlah diterima">
                                            <Input
                                                type="number"
                                                min={1}
                                                max={
                                                    item.quantity -
                                                    item.received_quantity
                                                }
                                                value={row.quantity}
                                                onChange={(event) =>
                                                    onUpdateEvidence(
                                                        index,
                                                        'quantity',
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                            />
                                        </Field>
                                    )}
                                </div>
                                <EvidenceControls
                                    row={row}
                                    policy={policy}
                                    onUpdate={(key, value) =>
                                        onUpdateEvidence(
                                            index,
                                            key,
                                            value as ReceivingRow[typeof key],
                                        )
                                    }
                                    onOpenCamera={() => onOpenCamera(index)}
                                />
                            </div>
                        );
                    })}
                    <Field label="Catatan penerimaan">
                        <textarea
                            className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.receiving_notes}
                            onChange={(event) =>
                                form.setData(
                                    'receiving_notes',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    {policy.allow_gallery_override && (
                        <Field label="Alasan override galeri">
                            <Input
                                value={form.data.override_reason}
                                onChange={(event) =>
                                    form.setData(
                                        'override_reason',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button disabled={form.processing}>
                            Simpan Receiving
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EvidenceList({
    items,
    rows,
    policy,
    onUpdate,
    onOpenCamera,
}: {
    items: TransferItem[];
    rows: EvidenceRow[];
    policy: CapturePolicy;
    onUpdate: <K extends keyof EvidenceRow>(
        index: number,
        key: K,
        value: EvidenceRow[K],
    ) => void;
    onOpenCamera: (index: number) => void;
}) {
    return (
        <div className="space-y-3">
            <h3 className="font-medium">Pemeriksaan Keberangkatan</h3>
            {items.map((item, index) => (
                <div key={item.id} className="space-y-3 rounded-lg border p-4">
                    <div>
                        <p className="font-medium">{item.product.name}</p>
                        <p className="text-sm text-muted-foreground">
                            {item.asset?.asset_code ?? `${item.quantity} unit`}
                        </p>
                    </div>
                    <Field label="Kondisi">
                        <Select
                            value={rows[index].condition}
                            onValueChange={(value) =>
                                onUpdate(index, 'condition', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="good">Baik</SelectItem>
                                <SelectItem value="fair">Cukup</SelectItem>
                                <SelectItem value="damaged">Rusak</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    <EvidenceControls
                        row={rows[index]}
                        policy={policy}
                        onUpdate={(key, value) => onUpdate(index, key, value)}
                        onOpenCamera={() => onOpenCamera(index)}
                    />
                </div>
            ))}
        </div>
    );
}

function EvidenceControls({
    row,
    policy,
    onUpdate,
    onOpenCamera,
}: {
    row: EvidenceRow;
    policy: CapturePolicy;
    onUpdate: <K extends keyof EvidenceRow>(
        key: K,
        value: EvidenceRow[K],
    ) => void;
    onOpenCamera: () => void;
}) {
    const galleryAllowed =
        policy.capture_mode !== 'camera_required' ||
        policy.allow_gallery_override;

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    onClick={onOpenCamera}
                >
                    <Camera className="size-4" />
                    Buka Kamera
                </Button>
                {galleryAllowed && (
                    <Label className="inline-flex cursor-pointer items-center rounded-md border px-3 py-2 text-sm">
                        Pilih Galeri
                        <input
                            className="sr-only"
                            type="file"
                            accept="image/*"
                            multiple
                            onChange={(event) => {
                                const files = Array.from(
                                    event.currentTarget.files ?? [],
                                ) as File[];

                                onUpdate('photos', [...row.photos, ...files]);
                                onUpdate(
                                    'capture_source',
                                    policy.capture_mode === 'camera_required'
                                        ? 'override'
                                        : 'gallery',
                                );
                            }}
                        />
                    </Label>
                )}
                <Badge variant="outline">
                    {row.photos.length} foto • minimal {policy.min_photos}
                </Badge>
            </div>
            <Input
                placeholder="Catatan pemeriksaan"
                value={row.notes}
                onChange={(event) => onUpdate('notes', event.target.value)}
            />
        </div>
    );
}

function ExpenseDialog({
    open,
    onOpenChange,
    transfer,
    categories,
    form,
    onSubmit,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transfer: Transfer;
    categories: Array<{ id: number; code: string; name: string }>;
    form: ReturnType<
        typeof useForm<{
            expense_branch_id: number;
            financial_category_id: number;
            expense_type: string;
            estimated_amount: number;
            actual_amount: number;
            vendor_name: string;
            external_reference: string;
            notes: string;
            proof: File | null;
        }>
    >;
    onSubmit: (event: FormEvent) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Catat Biaya Transfer</DialogTitle>
                </DialogHeader>
                <form className="space-y-4" onSubmit={onSubmit}>
                    <Field label="Cabang penanggung biaya">
                        <Select
                            value={String(form.data.expense_branch_id)}
                            onValueChange={(value) =>
                                form.setData('expense_branch_id', Number(value))
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    value={String(transfer.from_branch_id)}
                                >
                                    {transfer.origin_branch.name}
                                </SelectItem>
                                <SelectItem
                                    value={String(transfer.to_branch_id)}
                                >
                                    {transfer.destination_branch.name}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Kategori keuangan">
                        <Select
                            value={String(form.data.financial_category_id)}
                            onValueChange={(value) =>
                                form.setData(
                                    'financial_category_id',
                                    Number(value),
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Jenis biaya">
                        <Select
                            value={form.data.expense_type}
                            onValueChange={(value) =>
                                form.setData('expense_type', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[
                                    'shipping',
                                    'packing',
                                    'insurance',
                                    'fuel',
                                    'toll',
                                    'courier',
                                    'other',
                                ].map((value) => (
                                    <SelectItem key={value} value={value}>
                                        {value}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Estimasi">
                        <RupiahInput
                            value={form.data.estimated_amount}
                            onValueChange={(value) =>
                                form.setData('estimated_amount', value)
                            }
                        />
                    </Field>
                    <Field label="Aktual">
                        <RupiahInput
                            value={form.data.actual_amount}
                            onValueChange={(value) =>
                                form.setData('actual_amount', value)
                            }
                        />
                    </Field>
                    <Field label="Vendor / penerima">
                        <Input
                            value={form.data.vendor_name}
                            onChange={(event) =>
                                form.setData('vendor_name', event.target.value)
                            }
                        />
                    </Field>
                    <Field label="Bukti biaya">
                        <Input
                            type="file"
                            accept=".pdf,image/*"
                            onChange={(event) =>
                                form.setData(
                                    'proof',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </Field>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button disabled={form.processing}>Simpan Biaya</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ExpenseRow({
    expense,
    transferId,
    paymentMethods,
    cashSessions,
    canManage,
}: {
    expense: TransferExpense;
    transferId: number;
    paymentMethods: Props['paymentMethods'];
    cashSessions: Props['cashSessions'];
    canManage: boolean;
}) {
    const pay = useForm({
        actual_amount: Number(expense.actual_amount),
        payment_method_id: paymentMethods[0]?.id ?? 0,
        cash_session_id: null as number | null,
        paid_at: new Date().toISOString().slice(0, 16),
        external_reference: expense.external_reference ?? '',
        notes: expense.notes ?? '',
        proof: null as File | null,
    });

    return (
        <div className="rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-medium">{expense.expense_type}</p>
                    <p className="text-sm text-muted-foreground">
                        {expense.expense_branch.name} •{' '}
                        {expense.vendor_name ?? 'Tanpa vendor'}
                    </p>
                </div>
                <div className="text-right">
                    <p className="font-medium">
                        {money.format(
                            Number(
                                expense.actual_amount ||
                                    expense.estimated_amount,
                            ),
                        )}
                    </p>
                    <Badge variant="outline">{expense.status}</Badge>
                </div>
            </div>
            {canManage &&
                expense.status !== 'paid' &&
                expense.status !== 'void' && (
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button className="mt-3" size="sm">
                                Bayar
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Bayar Biaya Transfer</DialogTitle>
                            </DialogHeader>
                            <form
                                className="space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    pay.post(
                                        `/transfers/${transferId}/expenses/${expense.id}/pay`,
                                        {
                                            forceFormData: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
                                <Field label="Nominal aktual">
                                    <RupiahInput
                                        value={pay.data.actual_amount}
                                        onValueChange={(value) =>
                                            pay.setData('actual_amount', value)
                                        }
                                    />
                                </Field>
                                <Field label="Metode pembayaran">
                                    <Select
                                        value={String(
                                            pay.data.payment_method_id,
                                        )}
                                        onValueChange={(value) =>
                                            pay.setData(
                                                'payment_method_id',
                                                Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {paymentMethods.map((method) => (
                                                <SelectItem
                                                    key={method.id}
                                                    value={String(method.id)}
                                                >
                                                    {method.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                {cashSessions.length > 0 && (
                                    <Field label="Sesi kas (opsional)">
                                        <Select
                                            value={
                                                pay.data.cash_session_id ===
                                                null
                                                    ? 'none'
                                                    : String(
                                                          pay.data
                                                              .cash_session_id,
                                                      )
                                            }
                                            onValueChange={(value) =>
                                                pay.setData(
                                                    'cash_session_id',
                                                    value === 'none'
                                                        ? null
                                                        : Number(value),
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">
                                                    Tanpa sesi kas
                                                </SelectItem>
                                                {cashSessions.map((session) => (
                                                    <SelectItem
                                                        key={session.id}
                                                        value={String(
                                                            session.id,
                                                        )}
                                                    >
                                                        {session.register_name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                )}
                                <Field label="Waktu pembayaran">
                                    <Input
                                        type="datetime-local"
                                        value={pay.data.paid_at}
                                        onChange={(event) =>
                                            pay.setData(
                                                'paid_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Bukti">
                                    <Input
                                        type="file"
                                        accept=".pdf,image/*"
                                        onChange={(event) =>
                                            pay.setData(
                                                'proof',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </Field>
                                <DialogFooter>
                                    <Button disabled={pay.processing}>
                                        Catat Pembayaran
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                )}
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 text-sm">{value}</p>
        </div>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function formatDate(value: string | null): string {
    return value ? dateTime.format(new Date(value)) : '-';
}
