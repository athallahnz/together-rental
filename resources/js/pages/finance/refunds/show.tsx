import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Check,
    Download,
    ReceiptText,
    WalletCards,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RefundCenterRefund, RefundStatus } from '@/types';

type RefundDetail = RefundCenterRefund & {
    rejection_reason: string | null;
    cancellation_reason: string | null;
    proof_original_name: string | null;
    proof_mime_type: string | null;
    proof_size: number | null;
    booking: {
        id: number;
        booking_number: string;
        status: string;
        total_amount: string;
    } | null;
    rental: {
        id: number;
        rental_number: string;
        status: string;
        total_amount: string;
        balance_due: string;
    } | null;
    cash_session: {
        id: number;
        status: string;
        opened_at: string;
        closed_at: string | null;
        register: {
            id: number;
            code: string;
            name: string;
        };
    } | null;
    rejecter: { id: number; name: string } | null;
    canceller: { id: number; name: string } | null;
    cash_transactions: Array<{
        id: number;
        transaction_number: string;
        direction: 'out';
        type: string;
        amount: string;
        balance_after: string;
        occurred_at: string;
        description: string | null;
        creator: { id: number; name: string } | null;
    }>;
};

type Activity = {
    id: number;
    event: string;
    created_at: string;
    actor_name: string | null;
};

type OpenCashSession = {
    id: number;
    opened_at: string;
    opening_balance: string;
    register: {
        id: number;
        code: string;
        name: string;
    };
};

type Props = {
    refund: RefundDetail;
    activities: Activity[];
    openCashSessions: OpenCashSession[];
    permissions: {
        approve: boolean;
        reject: boolean;
        process: boolean;
        cancel: boolean;
    };
};

type ReasonForm = {
    data: { reason: string };
    errors: { reason?: string };
    processing: boolean;
    setData: (key: 'reason', value: string) => void;
};

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});
const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'long',
    timeStyle: 'short',
});
const statusLabels: Record<RefundStatus, string> = {
    requested: 'Requested',
    approved: 'Approved',
    rejected: 'Rejected',
    paid: 'Paid',
    cancelled: 'Cancelled',
};

export default function RefundCenterShow({
    refund,
    activities,
    openCashSessions,
    permissions,
}: Props) {
    const [approveOpen, setApproveOpen] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);
    const [processOpen, setProcessOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const approveForm = useForm({});
    const rejectForm = useForm({ reason: '' });
    const cancelForm = useForm({ reason: '' });
    const processForm = useForm<{
        cash_session_id: string;
        external_reference: string;
        notes: string;
        proof: File | null;
    }>({
        cash_session_id: '',
        external_reference: '',
        notes: '',
        proof: null,
    });
    const cashRefund = refund.payment_method.type === 'cash';

    const submitApprove = (event: FormEvent) => {
        event.preventDefault();
        approveForm.post(`/finance/refunds/${refund.id}/approve`, {
            preserveScroll: true,
            onSuccess: () => setApproveOpen(false),
        });
    };
    const submitReject = (event: FormEvent) => {
        event.preventDefault();
        rejectForm.post(`/finance/refunds/${refund.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                rejectForm.reset();
                setRejectOpen(false);
            },
        });
    };
    const submitCancel = (event: FormEvent) => {
        event.preventDefault();
        cancelForm.post(`/finance/refunds/${refund.id}/cancel`, {
            preserveScroll: true,
            onSuccess: () => {
                cancelForm.reset();
                setCancelOpen(false);
            },
        });
    };
    const submitProcess = (event: FormEvent) => {
        event.preventDefault();
        processForm.post(`/finance/refunds/${refund.id}/process`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                processForm.reset();
                setProcessOpen(false);
            },
        });
    };

    return (
        <>
            <Head title={refund.refund_number} />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/finance/refunds">
                                <ArrowLeft className="size-4" />
                                Refund Center
                            </Link>
                        </Button>
                        <div className="mt-3 flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold">
                                {refund.refund_number}
                            </h1>
                            <StatusBadge status={refund.status} />
                            <Badge variant="outline" className="capitalize">
                                {refund.refund_type}
                            </Badge>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {refund.branch.code} — {refund.branch.name} ·{' '}
                            {dateTime.format(new Date(refund.created_at))}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.approve && (
                            <Button onClick={() => setApproveOpen(true)}>
                                <Check className="size-4" />
                                Setujui
                            </Button>
                        )}
                        {permissions.reject && (
                            <Button
                                variant="destructive"
                                onClick={() => setRejectOpen(true)}
                            >
                                <X className="size-4" />
                                Tolak
                            </Button>
                        )}
                        {permissions.process && (
                            <Button onClick={() => setProcessOpen(true)}>
                                <WalletCards className="size-4" />
                                Bayar Refund
                            </Button>
                        )}
                        {permissions.cancel && (
                            <Button
                                variant="outline"
                                onClick={() => setCancelOpen(true)}
                            >
                                <Ban className="size-4" />
                                Batalkan
                            </Button>
                        )}
                    </div>
                </header>

                <StatusAlert refund={refund} />

                <section className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Detail Refund</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <Info
                                label="Nominal refund"
                                value={money.format(Number(refund.amount))}
                                emphasis
                            />
                            <Info
                                label="Metode payout"
                                value={`${refund.payment_method.name} (${refund.payment_method.code})`}
                            />
                            <Info
                                label="Pengaju"
                                value={refund.requester?.name ?? 'Sistem'}
                            />
                            <Info
                                label="Referensi payout"
                                value={refund.external_reference ?? '—'}
                            />
                            <div className="sm:col-span-2">
                                <Info label="Alasan" value={refund.reason} />
                            </div>
                            <div className="sm:col-span-2">
                                <Info
                                    label="Catatan"
                                    value={refund.notes ?? '—'}
                                />
                            </div>
                            {refund.proof_path && (
                                <div className="sm:col-span-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={`/finance/refunds/${refund.id}/proof`}
                                        >
                                            <Download className="size-4" />
                                            Unduh bukti payout
                                        </a>
                                    </Button>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {refund.proof_original_name ??
                                            'Bukti refund'}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Sumber</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Info
                                label="Payment"
                                value={refund.payment.payment_number}
                            />
                            <Info
                                label="Nominal payment"
                                value={money.format(
                                    Number(refund.payment.amount),
                                )}
                            />
                            <Info
                                label="Pelanggan"
                                value={refund.payment.customer?.name ?? '—'}
                            />
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={`/finance/payments/${refund.payment.id}`}
                                >
                                    Buka Payment
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline Workflow</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {timeline(refund).map((item) => (
                                <div
                                    key={item.label}
                                    className="flex gap-3 border-b pb-4 last:border-0 last:pb-0"
                                >
                                    <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-muted">
                                        <ReceiptText className="size-4" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium">
                                            {item.label}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {item.actor} ·{' '}
                                            {dateTime.format(new Date(item.at))}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Cash Ledger & Audit</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {refund.cash_transactions.map((transaction) => (
                                <div
                                    key={transaction.id}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-sm font-medium">
                                            {transaction.transaction_number}
                                        </p>
                                        <p className="font-semibold text-red-600 dark:text-red-400">
                                            -
                                            {money.format(
                                                Number(transaction.amount),
                                            )}
                                        </p>
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Saldo setelah transaksi:{' '}
                                        {money.format(
                                            Number(transaction.balance_after),
                                        )}
                                    </p>
                                </div>
                            ))}
                            {refund.cash_transactions.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Refund non-tunai tidak membentuk cash
                                    ledger, atau payout belum diproses.
                                </p>
                            )}
                            <div className="border-t pt-4">
                                <p className="mb-3 text-sm font-medium">
                                    Audit trail
                                </p>
                                <div className="space-y-3">
                                    {activities.map((activity) => (
                                        <div key={activity.id}>
                                            <p className="text-sm">
                                                {activityLabel(activity.event)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {activity.actor_name ??
                                                    'Sistem'}{' '}
                                                ·{' '}
                                                {dateTime.format(
                                                    new Date(
                                                        activity.created_at,
                                                    ),
                                                )}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </div>

            <Dialog open={approveOpen} onOpenChange={setApproveOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Setujui refund?</DialogTitle>
                        <DialogDescription>
                            Setelah disetujui, refund siap diproses oleh petugas
                            payout. Pengaju dan penyetuju tercatat terpisah.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitApprove}>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setApproveOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={approveForm.processing}
                            >
                                Konfirmasi Approval
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ReasonDialog
                open={rejectOpen}
                onOpenChange={setRejectOpen}
                title="Tolak refund"
                description="Penolakan melepaskan nominal yang sebelumnya direservasi."
                form={rejectForm}
                onSubmit={submitReject}
                action="Konfirmasi Penolakan"
                destructive
            />
            <ReasonDialog
                open={cancelOpen}
                onOpenChange={setCancelOpen}
                title="Batalkan refund"
                description="Refund requested atau approved akan dibatalkan tanpa menghapus histori."
                form={cancelForm}
                onSubmit={submitCancel}
                action="Konfirmasi Pembatalan"
            />

            <Dialog open={processOpen} onOpenChange={setProcessOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Bayar refund</DialogTitle>
                        <DialogDescription>
                            {cashRefund
                                ? 'Payout tunai akan dicatat sebagai ledger keluar pada sesi kas aktif yang dipilih.'
                                : 'Refund non-tunai wajib memiliki referensi transaksi dan bukti payout.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitProcess}>
                        {cashRefund ? (
                            <div className="space-y-2">
                                <Label>Sesi kas aktif</Label>
                                <Select
                                    value={processForm.data.cash_session_id}
                                    onValueChange={(value) =>
                                        processForm.setData(
                                            'cash_session_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih sesi kas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {openCashSessions.map((session) => (
                                            <SelectItem
                                                key={session.id}
                                                value={String(session.id)}
                                            >
                                                {session.register.code} —{' '}
                                                {session.register.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={processForm.errors.cash_session_id}
                                />
                            </div>
                        ) : (
                            <div className="space-y-2">
                                <Label htmlFor="refund-reference">
                                    Referensi transaksi
                                </Label>
                                <Input
                                    id="refund-reference"
                                    value={processForm.data.external_reference}
                                    onChange={(event) =>
                                        processForm.setData(
                                            'external_reference',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={100}
                                    placeholder="Nomor transfer / settlement"
                                />
                                <InputError
                                    message={
                                        processForm.errors.external_reference
                                    }
                                />
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="refund-proof">Bukti payout</Label>
                            <Input
                                id="refund-proof"
                                type="file"
                                accept="application/pdf,image/jpeg,image/png,image/webp"
                                onChange={(event) =>
                                    processForm.setData(
                                        'proof',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={processForm.errors.proof} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="refund-notes">Catatan payout</Label>
                            <textarea
                                id="refund-notes"
                                value={processForm.data.notes}
                                onChange={(event) =>
                                    processForm.setData(
                                        'notes',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                maxLength={3000}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                            />
                            <InputError message={processForm.errors.notes} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setProcessOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    processForm.processing ||
                                    processForm.data.proof === null ||
                                    (cashRefund
                                        ? processForm.data.cash_session_id ===
                                          ''
                                        : processForm.data.external_reference.trim() ===
                                          '')
                                }
                            >
                                {processForm.processing
                                    ? 'Memproses...'
                                    : 'Konfirmasi Payout'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function StatusAlert({ refund }: { refund: RefundDetail }) {
    if (refund.status === 'rejected') {
        return (
            <Alert variant="destructive">
                <X className="size-4" />
                <AlertTitle>Refund ditolak</AlertTitle>
                <AlertDescription>
                    {refund.rejection_reason ?? 'Alasan tidak tersedia.'}
                </AlertDescription>
            </Alert>
        );
    }

    if (refund.status === 'cancelled') {
        return (
            <Alert>
                <Ban className="size-4" />
                <AlertTitle>Refund dibatalkan</AlertTitle>
                <AlertDescription>
                    {refund.cancellation_reason ?? 'Alasan tidak tersedia.'}
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert>
            <ReceiptText className="size-4" />
            <AlertTitle>{statusLabels[refund.status]}</AlertTitle>
            <AlertDescription>
                {refund.status === 'requested'
                    ? 'Menunggu keputusan approver yang berbeda dari pengaju.'
                    : refund.status === 'approved'
                      ? 'Refund siap dibayarkan melalui metode payout terpilih.'
                      : 'Payout telah tercatat dan histori refund dikunci.'}
            </AlertDescription>
        </Alert>
    );
}

function ReasonDialog({
    open,
    onOpenChange,
    title,
    description,
    form,
    onSubmit,
    action,
    destructive = false,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    form: ReasonForm;
    onSubmit: (event: FormEvent) => void;
    action: string;
    destructive?: boolean;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <form className="space-y-4" onSubmit={onSubmit}>
                    <div className="space-y-2">
                        <Label>Alasan</Label>
                        <textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            rows={4}
                            maxLength={1000}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="Jelaskan alasan (minimal 10 karakter)."
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Kembali
                        </Button>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={
                                form.processing ||
                                form.data.reason.trim().length < 10
                            }
                        >
                            {action}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Info({
    label,
    value,
    emphasis = false,
}: {
    label: string;
    value: ReactNode;
    emphasis?: boolean;
}) {
    return (
        <div>
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <div
                className={
                    emphasis
                        ? 'mt-1 text-xl font-semibold'
                        : 'mt-1 text-sm font-medium'
                }
            >
                {value}
            </div>
        </div>
    );
}

function StatusBadge({ status }: { status: RefundStatus }) {
    const variant =
        status === 'rejected'
            ? 'destructive'
            : status === 'cancelled'
              ? 'outline'
              : status === 'requested'
                ? 'secondary'
                : 'default';

    return <Badge variant={variant}>{statusLabels[status]}</Badge>;
}

function timeline(refund: RefundDetail) {
    const items = [
        {
            label: 'Refund diajukan',
            actor: refund.requester?.name ?? 'Sistem',
            at: refund.created_at,
        },
    ];

    if (refund.approved_at) {
        items.push({
            label: 'Refund disetujui',
            actor: refund.approver?.name ?? 'Sistem',
            at: refund.approved_at,
        });
    }

    if (refund.rejected_at) {
        items.push({
            label: 'Refund ditolak',
            actor: refund.rejecter?.name ?? 'Sistem',
            at: refund.rejected_at,
        });
    }

    if (refund.processed_at) {
        items.push({
            label: 'Payout selesai',
            actor: refund.processor?.name ?? 'Sistem',
            at: refund.processed_at,
        });
    }

    if (refund.cancelled_at) {
        items.push({
            label: 'Refund dibatalkan',
            actor: refund.canceller?.name ?? 'Sistem',
            at: refund.cancelled_at,
        });
    }

    return items;
}

function activityLabel(event: string): string {
    const labels: Record<string, string> = {
        'refund.requested': 'Refund diajukan',
        'refund.approved': 'Refund disetujui',
        'refund.rejected': 'Refund ditolak',
        'refund.paid': 'Refund dibayarkan',
        'refund.cancelled': 'Refund dibatalkan',
    };

    return labels[event] ?? event;
}
