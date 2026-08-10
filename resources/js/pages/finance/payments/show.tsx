import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    ExternalLink,
    ReceiptText,
    RotateCcw,
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
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    FinancePaymentMethod,
    PaymentCenterPayment,
    RefundStatus,
} from '@/types';

type PaymentDetail = PaymentCenterPayment & {
    proof_path: string | null;
    financial_category: {
        id: number;
        code: string;
        name: string;
        type: string;
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
    receiver: { id: number; name: string } | null;
    voider: { id: number; name: string } | null;
    cash_transactions: Array<{
        id: number;
        transaction_number: string;
        direction: 'in' | 'out';
        type: string;
        amount: string;
        balance_after: string;
        occurred_at: string;
        description: string | null;
    }>;
    refunds: Array<{
        id: number;
        refund_number: string;
        refund_type: 'full' | 'partial';
        amount: string;
        status: RefundStatus;
        reason: string;
        created_at: string;
        processed_at: string | null;
        payment_method: FinancePaymentMethod;
        requester: { id: number; name: string } | null;
        approver: { id: number; name: string } | null;
        processor: { id: number; name: string } | null;
    }>;
};

type Activity = {
    id: number;
    event: string;
    description: string | null;
    old_values: string | null;
    new_values: string | null;
    created_at: string;
    actor_name: string | null;
};

type Props = {
    payment: PaymentDetail;
    activities: Activity[];
    voidEligibility: {
        allowed: boolean;
        field: string;
        reason: string | null;
    };
    refundEligibility: {
        allowed: boolean;
        field: string;
        reason: string | null;
        payment_amount: number;
        paid_refund_amount: number;
        reserved_refund_amount: number;
        refundable_amount: number;
    };
    paymentMethods: FinancePaymentMethod[];
    permissions: { void: boolean; requestRefund: boolean };
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
const sourceLabels: Record<string, string> = {
    booking: 'Booking',
    rental_checkout: 'Checkout Rental',
    rental_return: 'Pengembalian Rental',
    rental_extension: 'Perpanjangan Rental',
    transfer_expense: 'Biaya Transfer Aset',
};
const typeLabels: Record<string, string> = {
    rental: 'Pembayaran Rental',
    deposit: 'Deposit Rental',
    transfer_expense: 'Biaya Transfer',
};
const refundStatusLabels: Record<RefundStatus, string> = {
    requested: 'Requested',
    approved: 'Approved',
    rejected: 'Rejected',
    paid: 'Paid',
    cancelled: 'Cancelled',
};

export default function PaymentCenterShow({
    payment,
    activities,
    voidEligibility,
    refundEligibility,
    paymentMethods,
    permissions,
}: Props) {
    const [voidDialogOpen, setVoidDialogOpen] = useState(false);
    const [refundDialogOpen, setRefundDialogOpen] = useState(false);
    const voidForm = useForm({ reason: '' });
    const refundForm = useForm({
        amount: '',
        payment_method_id: String(payment.payment_method_id),
        reason: '',
        notes: '',
    });
    const source = sourceReference(payment);
    const signedPaymentAmount =
        payment.direction === 'out'
            ? -Number(payment.amount)
            : Number(payment.amount);

    const submitVoid = (event: FormEvent) => {
        event.preventDefault();
        voidForm.post(`/finance/payments/${payment.id}/void`, {
            preserveScroll: true,
            onSuccess: () => {
                voidForm.reset();
                setVoidDialogOpen(false);
            },
        });
    };

    const submitRefund = (event: FormEvent) => {
        event.preventDefault();
        refundForm.post(`/finance/payments/${payment.id}/refunds`, {
            preserveScroll: true,
            onSuccess: () => {
                refundForm.reset();
                setRefundDialogOpen(false);
            },
        });
    };

    return (
        <>
            <Head title={payment.payment_number} />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/finance/payments">
                                <ArrowLeft className="size-4" />
                                Payment Center
                            </Link>
                        </Button>
                        <div className="mt-3 flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold">
                                {payment.payment_number}
                            </h1>
                            <Badge
                                variant={
                                    payment.status === 'void'
                                        ? 'destructive'
                                        : 'secondary'
                                }
                            >
                                {payment.status === 'void'
                                    ? 'Void'
                                    : 'Completed'}
                            </Badge>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {payment.branch.code} — {payment.branch.name} ·{' '}
                            {dateTime.format(new Date(payment.paid_at))}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.requestRefund && (
                            <Button onClick={() => setRefundDialogOpen(true)}>
                                <RotateCcw className="size-4" />
                                Ajukan Refund
                            </Button>
                        )}
                        {permissions.void && (
                            <Button
                                variant="destructive"
                                onClick={() => setVoidDialogOpen(true)}
                            >
                                <Ban className="size-4" />
                                Void Payment
                            </Button>
                        )}
                    </div>
                </header>

                {payment.status === 'void' && (
                    <Alert variant="destructive">
                        <Ban className="size-4" />
                        <AlertTitle>Payment telah di-void</AlertTitle>
                        <AlertDescription>
                            {payment.void_reason ??
                                'Alasan void tidak tersedia.'}
                            {payment.voided_at && (
                                <span className="mt-1 block">
                                    Oleh {payment.voider?.name ?? 'Sistem'} pada{' '}
                                    {dateTime.format(
                                        new Date(payment.voided_at),
                                    )}
                                    .
                                </span>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {payment.status === 'completed' && !voidEligibility.allowed && (
                    <Alert>
                        <AlertTriangle className="size-4" />
                        <AlertTitle>Void tidak tersedia</AlertTitle>
                        <AlertDescription>
                            {voidEligibility.reason}
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Detail Payment</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <Info
                                label="Nominal"
                                value={money.format(signedPaymentAmount)}
                                emphasis
                            />
                            <Info
                                label="Jenis"
                                value={typeLabels[payment.type] ?? payment.type}
                            />
                            <Info
                                label="Metode"
                                value={`${payment.payment_method.name} (${payment.payment_method.code})`}
                            />
                            <Info
                                label="Kategori"
                                value={
                                    payment.financial_category
                                        ? `${payment.financial_category.code} — ${payment.financial_category.name}`
                                        : '—'
                                }
                            />
                            <Info
                                label="Referensi eksternal"
                                value={payment.external_reference ?? '—'}
                            />
                            <Info
                                label="Diterima / dicatat oleh"
                                value={payment.receiver?.name ?? 'Sistem'}
                            />
                            <Info
                                label="Pelanggan"
                                value={payment.customer?.name ?? '—'}
                            />
                            <Info
                                label="Nomor pelanggan"
                                value={payment.customer?.customer_number ?? '—'}
                            />
                            <div className="sm:col-span-2">
                                <Info
                                    label="Catatan"
                                    value={payment.notes ?? '—'}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Sumber Transaksi</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Info
                                label="Konteks"
                                value={
                                    sourceLabels[
                                        payment.source_context ?? ''
                                    ] ?? 'Legacy / tidak diketahui'
                                }
                            />
                            <Info label="Referensi" value={source.label} />
                            {source.href && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={source.href}>
                                        Buka sumber
                                        <ExternalLink className="size-4" />
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Refund Payment</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Info
                                label="Sudah dibayar"
                                value={money.format(
                                    refundEligibility.paid_refund_amount,
                                )}
                            />
                            <Info
                                label="Sedang diproses"
                                value={money.format(
                                    refundEligibility.reserved_refund_amount,
                                )}
                            />
                            <Info
                                label="Masih refundable"
                                value={money.format(
                                    refundEligibility.refundable_amount,
                                )}
                                emphasis
                            />
                        </div>
                        {payment.refunds.length > 0 ? (
                            <div className="overflow-x-auto border-t pt-4">
                                <table className="w-full min-w-[720px] text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr>
                                            <th className="py-2">Refund</th>
                                            <th className="py-2">Metode</th>
                                            <th className="py-2">Status</th>
                                            <th className="py-2 text-right">
                                                Nominal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {payment.refunds.map((refund) => (
                                            <tr
                                                key={refund.id}
                                                className="border-t"
                                            >
                                                <td className="py-3">
                                                    <Link
                                                        href={`/finance/refunds/${refund.id}`}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {refund.refund_number}
                                                    </Link>
                                                    <p className="text-xs text-muted-foreground capitalize">
                                                        {refund.refund_type} ·{' '}
                                                        {refund.requester
                                                            ?.name ?? 'Sistem'}
                                                    </p>
                                                </td>
                                                <td className="py-3">
                                                    {refund.payment_method.name}
                                                </td>
                                                <td className="py-3">
                                                    <RefundStatusBadge
                                                        status={refund.status}
                                                    />
                                                </td>
                                                <td className="py-3 text-right font-semibold">
                                                    {money.format(
                                                        Number(refund.amount),
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Belum ada refund untuk payment ini.
                            </p>
                        )}
                        {!refundEligibility.allowed &&
                            payment.status === 'completed' && (
                                <p className="text-sm text-muted-foreground">
                                    {refundEligibility.reason}
                                </p>
                            )}
                    </CardContent>
                </Card>

                <section className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Sesi Kas & Ledger</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {payment.cash_session ? (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Info
                                        label="Register"
                                        value={`${payment.cash_session.register.code} — ${payment.cash_session.register.name}`}
                                    />
                                    <Info
                                        label="Status sesi"
                                        value={payment.cash_session.status}
                                    />
                                    <Info
                                        label="Dibuka"
                                        value={dateTime.format(
                                            new Date(
                                                payment.cash_session.opened_at,
                                            ),
                                        )}
                                    />
                                    <Info
                                        label="Ditutup"
                                        value={
                                            payment.cash_session.closed_at
                                                ? dateTime.format(
                                                      new Date(
                                                          payment.cash_session
                                                              .closed_at,
                                                      ),
                                                  )
                                                : 'Masih aktif'
                                        }
                                    />
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Payment non-tunai tidak membentuk cash
                                    ledger.
                                </p>
                            )}

                            {payment.cash_transactions.length > 0 && (
                                <div className="overflow-x-auto border-t pt-4">
                                    <table className="w-full min-w-[560px] text-sm">
                                        <thead className="text-left text-muted-foreground">
                                            <tr>
                                                <th className="py-2">Ledger</th>
                                                <th className="py-2">Arah</th>
                                                <th className="py-2 text-right">
                                                    Nominal
                                                </th>
                                                <th className="py-2 text-right">
                                                    Saldo
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {payment.cash_transactions.map(
                                                (transaction) => (
                                                    <tr
                                                        key={transaction.id}
                                                        className="border-t"
                                                    >
                                                        <td className="py-2">
                                                            <p className="font-medium">
                                                                {
                                                                    transaction.transaction_number
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {transaction.description ??
                                                                    transaction.type}
                                                            </p>
                                                        </td>
                                                        <td className="py-2 uppercase">
                                                            {
                                                                transaction.direction
                                                            }
                                                        </td>
                                                        <td className="py-2 text-right">
                                                            {money.format(
                                                                Number(
                                                                    transaction.amount,
                                                                ),
                                                            )}
                                                        </td>
                                                        <td className="py-2 text-right">
                                                            {money.format(
                                                                Number(
                                                                    transaction.balance_after,
                                                                ),
                                                            )}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Audit Trail</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {activities.map((activity) => (
                                    <div
                                        key={activity.id}
                                        className="flex gap-3 border-b pb-4 last:border-0 last:pb-0"
                                    >
                                        <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-muted">
                                            <ReceiptText className="size-4" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">
                                                {activityLabel(activity.event)}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
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
                                    </div>
                                ))}
                                {activities.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada aktivitas audit untuk payment
                                        ini.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </div>

            <Dialog open={refundDialogOpen} onOpenChange={setRefundDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Ajukan Refund {payment.payment_number}
                        </DialogTitle>
                        <DialogDescription>
                            Maksimal refund yang masih tersedia adalah{' '}
                            {money.format(refundEligibility.refundable_amount)}.
                            Pengajuan harus disetujui oleh pengguna lain sebelum
                            payout.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitRefund}>
                        <div className="space-y-2">
                            <Label htmlFor="refund-amount">Nominal</Label>
                            <Input
                                id="refund-amount"
                                type="number"
                                min="1"
                                max={refundEligibility.refundable_amount}
                                step="1"
                                value={refundForm.data.amount}
                                onChange={(event) =>
                                    refundForm.setData(
                                        'amount',
                                        event.target.value,
                                    )
                                }
                                placeholder="Nominal refund"
                            />
                            <InputError message={refundForm.errors.amount} />
                        </div>
                        <div className="space-y-2">
                            <Label>Metode payout</Label>
                            <Select
                                value={refundForm.data.payment_method_id}
                                onValueChange={(value) =>
                                    refundForm.setData(
                                        'payment_method_id',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih metode" />
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
                            <InputError
                                message={refundForm.errors.payment_method_id}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="refund-reason">Alasan refund</Label>
                            <textarea
                                id="refund-reason"
                                value={refundForm.data.reason}
                                onChange={(event) =>
                                    refundForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                rows={4}
                                maxLength={1000}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                                placeholder="Jelaskan alasan refund (minimal 10 karakter)."
                            />
                            <InputError message={refundForm.errors.reason} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="refund-request-notes">
                                Catatan tambahan
                            </Label>
                            <textarea
                                id="refund-request-notes"
                                value={refundForm.data.notes}
                                onChange={(event) =>
                                    refundForm.setData(
                                        'notes',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                maxLength={3000}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                            />
                            <InputError message={refundForm.errors.notes} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRefundDialogOpen(false)}
                                disabled={refundForm.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    refundForm.processing ||
                                    Number(refundForm.data.amount) <= 0 ||
                                    refundForm.data.reason.trim().length < 10
                                }
                            >
                                {refundForm.processing
                                    ? 'Mengajukan...'
                                    : 'Ajukan Refund'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={voidDialogOpen} onOpenChange={setVoidDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Void {payment.payment_number}</DialogTitle>
                        <DialogDescription>
                            Payment tidak akan dihapus. Sistem akan menandai
                            payment sebagai void, membalik total operasional,
                            dan menambahkan reversal ledger untuk transaksi
                            tunai.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitVoid}>
                        <div className="space-y-2">
                            <Label htmlFor="void-reason">Alasan void</Label>
                            <textarea
                                id="void-reason"
                                value={voidForm.data.reason}
                                onChange={(event) =>
                                    voidForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                rows={5}
                                maxLength={1000}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                                placeholder="Jelaskan kesalahan dan alasan koreksi (minimal 10 karakter)."
                                autoFocus
                            />
                            <InputError message={voidForm.errors.reason} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setVoidDialogOpen(false)}
                                disabled={voidForm.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={
                                    voidForm.processing ||
                                    voidForm.data.reason.trim().length < 10
                                }
                            >
                                {voidForm.processing
                                    ? 'Memproses...'
                                    : 'Konfirmasi Void'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
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

function sourceReference(payment: PaymentDetail): {
    label: string;
    href: string | null;
} {
    if (payment.rental_extension && payment.rental) {
        return {
            label: payment.rental_extension.extension_number,
            href: `/rentals/${payment.rental.id}`,
        };
    }

    if (payment.rental) {
        return {
            label: payment.rental.rental_number,
            href: `/rentals/${payment.rental.id}`,
        };
    }

    if (payment.booking) {
        return {
            label: payment.booking.booking_number,
            href: `/bookings/${payment.booking.id}`,
        };
    }

    if (payment.transfer_expense?.transfer) {
        return {
            label: payment.transfer_expense.transfer.transfer_number,
            href: `/transfers/${payment.transfer_expense.transfer.id}`,
        };
    }

    return {
        label: payment.external_reference ?? 'Tanpa referensi sumber',
        href: null,
    };
}

function RefundStatusBadge({ status }: { status: RefundStatus }) {
    const variant =
        status === 'rejected'
            ? 'destructive'
            : status === 'cancelled'
              ? 'outline'
              : status === 'requested'
                ? 'secondary'
                : 'default';

    return <Badge variant={variant}>{refundStatusLabels[status]}</Badge>;
}

function activityLabel(event: string): string {
    const labels: Record<string, string> = {
        'payment.voided': 'Payment di-void',
    };

    return labels[event] ?? event;
}
