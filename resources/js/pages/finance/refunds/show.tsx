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
import { stage5Display, Stage5Text, stage5Translate, stage5Date, stage5Money } from '@/components/stage5-text';
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
import { useAppLocale } from '@/lib/i18n';
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

const money = { format: stage5Money };


export default function RefundCenterShow({
    refund,
    activities,
    openCashSessions,
    permissions,
}: Props) {
    const { locale: stage5Locale } = useAppLocale();
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
                                <Stage5Text k="stage5.ui.81bd652019ba" />
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
                            {refund.purpose && (
                                <Badge variant="outline">
                                    {refund.purpose === 'booking_cancellation'
                                        ? 'Pembatalan Booking'
                                        : 'Koreksi Pembayaran'}
                                </Badge>
                            )}
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {refund.branch.code} — {refund.branch.name} ·{' '}
                            {stage5Date(new Date(refund.created_at), stage5Locale)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.approve && (
                            <Button onClick={() => setApproveOpen(true)}>
                                <Check className="size-4" />
                                <Stage5Text k="stage5.ui.1f9ce43818de" />
                            </Button>
                        )}
                        {permissions.reject && (
                            <Button
                                variant="destructive"
                                onClick={() => setRejectOpen(true)}
                            >
                                <X className="size-4" />
                                <Stage5Text k="stage5.ui.e2f73daf8ad0" />
                            </Button>
                        )}
                        {permissions.process && (
                            <Button onClick={() => setProcessOpen(true)}>
                                <WalletCards className="size-4" />
                                <Stage5Text k="stage5.ui.cfe5f4063c3c" />
                            </Button>
                        )}
                        {permissions.cancel && (
                            <Button
                                variant="outline"
                                onClick={() => setCancelOpen(true)}
                            >
                                <Ban className="size-4" />
                                <Stage5Text k="stage5.ui.dbe47c83d4ca" />
                            </Button>
                        )}
                    </div>
                </header>

                <StatusAlert refund={refund} />

                <section className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle><Stage5Text k="stage5.ui.5c01e89d492e" /></CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <Info
                                label={stage5Translate("stage5.ui.3b8f9bd8120d", stage5Locale)}
                                value={money.format(Number(refund.amount))}
                                emphasis
                            />
                            <Info
                                label={stage5Translate("stage5.ui.06867a894580", stage5Locale)}
                                value={`${refund.payment_method.name} (${refund.payment_method.code})`}
                            />
                            <Info
                                label={stage5Translate("stage5.ui.6248898d670d", stage5Locale)}
                                value={
                                    refund.purpose === 'booking_cancellation'
                                        ? 'Pembatalan Booking'
                                        : refund.purpose ===
                                            'payment_correction'
                                          ? 'Koreksi Pembayaran'
                                          : 'Legacy / tidak diklasifikasikan'
                                }
                            />
                            <Info
                                label={stage5Translate("stage5.ui.74e55868378d", stage5Locale)}
                                value={refund.requester?.name ?? 'Sistem'}
                            />
                            <Info
                                label={stage5Translate("stage5.ui.fb188273559d", stage5Locale)}
                                value={refund.external_reference ?? '—'}
                            />
                            <div className="sm:col-span-2">
                                <Info label={stage5Translate("stage5.ui.3faa833b08be", stage5Locale)} value={refund.reason} />
                            </div>
                            <div className="sm:col-span-2">
                                <Info
                                    label={stage5Translate("stage5.ui.9f09aefd0dd4", stage5Locale)}
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
                                            <Stage5Text k="stage5.ui.bf720fb72c8f" />
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
                            <CardTitle><Stage5Text k="stage5.ui.dee3788ad0d2" /></CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Info
                                label={stage5Translate("stage5.ui.b41a92bed032", stage5Locale)}
                                value={refund.payment.payment_number}
                            />
                            <Info
                                label={stage5Translate("stage5.ui.0aa9fd910b79", stage5Locale)}
                                value={money.format(
                                    Number(refund.payment.amount),
                                )}
                            />
                            <Info
                                label={stage5Translate("stage5.ui.af0ab4433946", stage5Locale)}
                                value={refund.payment.customer?.name ?? '—'}
                            />
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={`/finance/payments/${refund.payment.id}`}
                                >
                                    <Stage5Text k="stage5.ui.6a1ec440cc67" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle><Stage5Text k="stage5.ui.7f09922cb63d" /></CardTitle>
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
                                            {stage5Date(new Date(item.at), stage5Locale)}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle><Stage5Text k="stage5.ui.09e6d3688124" /></CardTitle>
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
                                        <Stage5Text k="stage5.ui.74de293e54ec" />{' '}
                                        {money.format(
                                            Number(transaction.balance_after),
                                        )}
                                    </p>
                                </div>
                            ))}
                            {refund.cash_transactions.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    <Stage5Text k="stage5.ui.476cbfd4a75b" />
                                </p>
                            )}
                            <div className="border-t pt-4">
                                <p className="mb-3 text-sm font-medium">
                                    <Stage5Text k="stage5.ui.33de865a8d82" />
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
                                                {stage5Date(
                                                    new Date(
                                                        activity.created_at,
                                                    ),
                                                    stage5Locale,
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
                        <DialogTitle><Stage5Text k="stage5.ui.b288e5304669" /></DialogTitle>
                        <DialogDescription>
                            {refund.purpose === 'booking_cancellation'
                                ? 'Persetujuan ini langsung membatalkan Booking dan melepaskan reservasi stok. Payout belum dilakukan; DP dan security deposit yang belum direfund tetap menjadi kewajiban.'
                                : 'Setelah disetujui, refund siap diproses oleh petugas payout. Pengaju dan penyetuju tercatat terpisah.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitApprove}>
                        <InputError
                            message={Object.values(approveForm.errors).find(
                                (error): error is string =>
                                    typeof error === 'string',
                            )}
                        />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setApproveOpen(false)}
                            >
                                <Stage5Text k="stage5.ui.1433539c3b8f" />
                            </Button>
                            <Button
                                type="submit"
                                disabled={approveForm.processing}
                            >
                                <Stage5Text k="stage5.ui.3f1d3685c698" />
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ReasonDialog
                open={rejectOpen}
                onOpenChange={setRejectOpen}
                title={stage5Translate("stage5.ui.ad78efded11f", stage5Locale)}
                description={stage5Translate("stage5.ui.52c3c813ed33", stage5Locale)}
                form={rejectForm}
                onSubmit={submitReject}
                action="Konfirmasi Penolakan"
                destructive
            />
            <ReasonDialog
                open={cancelOpen}
                onOpenChange={setCancelOpen}
                title={stage5Translate("stage5.ui.cf902c834084", stage5Locale)}
                description={stage5Translate("stage5.ui.a23a6cc9e59b", stage5Locale)}
                form={cancelForm}
                onSubmit={submitCancel}
                action="Konfirmasi Pembatalan"
            />

            <Dialog open={processOpen} onOpenChange={setProcessOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle><Stage5Text k="stage5.ui.6d9ef647f176" /></DialogTitle>
                        <DialogDescription>
                            {cashRefund
                                ? 'Payout tunai akan dicatat sebagai ledger keluar pada sesi kas aktif yang dipilih.'
                                : 'Refund non-tunai wajib memiliki referensi transaksi dan bukti payout.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitProcess}>
                        {cashRefund ? (
                            <div className="space-y-2">
                                <Label><Stage5Text k="stage5.ui.bdf75fb65c7f" /></Label>
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
                                        <SelectValue placeholder={stage5Translate("stage5.ui.cfde4a0267a8", stage5Locale)} />
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
                                    <Stage5Text k="stage5.ui.69574bceb649" />
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
                                    placeholder={stage5Translate("stage5.ui.7d75ae9aa8c2", stage5Locale)}
                                />
                                <InputError
                                    message={
                                        processForm.errors.external_reference
                                    }
                                />
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="refund-proof"><Stage5Text k="stage5.ui.15a56195e84d" /></Label>
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
                            <Label htmlFor="refund-notes"><Stage5Text k="stage5.ui.a4956ec48ff0" /></Label>
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
                                <Stage5Text k="stage5.ui.1433539c3b8f" />
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
    const { locale: stage5Locale } = useAppLocale();

    if (refund.status === 'rejected') {
        return (
            <Alert variant="destructive">
                <X className="size-4" />
                <AlertTitle><Stage5Text k="stage5.ui.91b8a88a6b7b" /></AlertTitle>
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
                <AlertTitle><Stage5Text k="stage5.ui.dcfffb19d0b4" /></AlertTitle>
                <AlertDescription>
                    {refund.cancellation_reason ?? 'Alasan tidak tersedia.'}
                    {refund.purpose === 'booking_cancellation' &&
                        refund.booking?.status === 'cancelled' && (
                            <span className="mt-2 block">
                                <Stage5Text k="stage5.ui.c7149e8a0cfd" />
                            </span>
                        )}
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert>
            <ReceiptText className="size-4" />
            <AlertTitle>{stage5Display(refund.status, stage5Locale)}</AlertTitle>
            <AlertDescription>
                {refund.status === 'requested'
                    ? 'Menunggu keputusan approver yang berbeda dari pengaju.'
                    : refund.status === 'approved'
                      ? refund.purpose === 'booking_cancellation'
                          ? 'Booking telah dibatalkan dan stok dilepas. Refund belum dibayarkan; selesaikan payout serta refund deposit terpisah bila ada.'
                          : 'Refund siap dibayarkan melalui metode payout terpilih.'
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
    const { locale: stage5Locale } = useAppLocale();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <form className="space-y-4" onSubmit={onSubmit}>
                    <div className="space-y-2">
                        <Label><Stage5Text k="stage5.ui.3faa833b08be" /></Label>
                        <textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            rows={4}
                            maxLength={1000}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder={stage5Translate("stage5.ui.3da9b0c182ad", stage5Locale)}
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            <Stage5Text k="stage5.ui.c43a6e25b712" />
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
    const { locale: stage5Locale } = useAppLocale();
    const variant =
        status === 'rejected'
            ? 'destructive'
            : status === 'cancelled'
              ? 'outline'
              : status === 'requested'
                ? 'secondary'
                : 'default';

    return <Badge variant={variant}>{stage5Display(status, stage5Locale)}</Badge>;
}

function timeline(refund: RefundDetail) {
    const items = [
        {
            label: stage5Translate("stage5.ui.ecd3b4eee5d6"),
            actor: refund.requester?.name ?? 'Sistem',
            at: refund.created_at,
        },
    ];

    if (refund.approved_at) {
        items.push({
            label: stage5Translate("stage5.ui.46b5a30fc1e1"),
            actor: refund.approver?.name ?? 'Sistem',
            at: refund.approved_at,
        });
    }

    if (refund.rejected_at) {
        items.push({
            label: stage5Translate("stage5.ui.91b8a88a6b7b"),
            actor: refund.rejecter?.name ?? 'Sistem',
            at: refund.rejected_at,
        });
    }

    if (refund.processed_at) {
        items.push({
            label: stage5Translate("stage5.ui.64ef1e1f9e5d"),
            actor: refund.processor?.name ?? 'Sistem',
            at: refund.processed_at,
        });
    }

    if (refund.cancelled_at) {
        items.push({
            label: stage5Translate("stage5.ui.dcfffb19d0b4"),
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
