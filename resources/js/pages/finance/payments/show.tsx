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
import {
    stage5Display,
    Stage5Text,
    stage5Translate,
    stage5Date,
    stage5Money,
} from '@/components/stage5-text';
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
import { useAppLocale } from '@/lib/i18n';
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
        purpose: 'booking_cancellation' | 'payment_correction' | null;
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

const money = { format: stage5Money };

const sourceLabels: Record<string, string> = {
    booking: 'Booking',
    rental_checkout: 'Checkout Rental',
    rental_return: 'Pengembalian Rental',
    rental_extension: 'Perpanjangan Rental',
    transfer_expense: 'Biaya Transfer Aset',
    operational_expense: 'Pengeluaran Operasional',
};
const typeLabels: Record<string, string> = {
    rental: 'Pembayaran Rental',
    deposit: 'Deposit Rental',
    transfer_expense: 'Biaya Transfer',
    operational_expense: 'Pengeluaran Operasional',
};

export default function PaymentCenterShow({
    payment,
    activities,
    voidEligibility,
    refundEligibility,
    paymentMethods,
    permissions,
}: Props) {
    const { locale: stage5Locale } = useAppLocale();
    const [voidDialogOpen, setVoidDialogOpen] = useState(false);
    const [refundDialogOpen, setRefundDialogOpen] = useState(false);
    const voidForm = useForm({ reason: '' });
    const refundForm = useForm({
        amount: '',
        payment_method_id: String(payment.payment_method_id),
        purpose: '',
        reason: '',
        notes: '',
    });
    const bookingDp =
        payment.booking_id !== null &&
        payment.source_context === 'booking' &&
        payment.type === 'rental';
    const cancellationAvailable =
        bookingDp &&
        ['draft', 'confirmed', 'cancelled'].includes(
            payment.booking?.status ?? '',
        );
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
                                <Stage5Text k="stage5.ui.78a5e1538bc3" />
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
                            {stage5Date(
                                new Date(payment.paid_at),
                                stage5Locale,
                            )}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.requestRefund && (
                            <Button onClick={() => setRefundDialogOpen(true)}>
                                <RotateCcw className="size-4" />
                                <Stage5Text k="stage5.ui.9ef202f871b3" />
                            </Button>
                        )}
                        {permissions.void && (
                            <Button
                                variant="destructive"
                                onClick={() => setVoidDialogOpen(true)}
                            >
                                <Ban className="size-4" />
                                <Stage5Text k="stage5.ui.e3a66905fa72" />
                            </Button>
                        )}
                    </div>
                </header>

                {payment.status === 'void' && (
                    <Alert variant="destructive">
                        <Ban className="size-4" />
                        <AlertTitle>
                            <Stage5Text k="stage5.ui.d08f6f1021c1" />
                        </AlertTitle>
                        <AlertDescription>
                            {payment.void_reason ??
                                'Alasan void tidak tersedia.'}
                            {payment.voided_at && (
                                <span className="mt-1 block">
                                    <Stage5Text k="stage5.ui.aad1a980791c" />{' '}
                                    {payment.voider?.name ?? 'Sistem'}{' '}
                                    <Stage5Text k="stage5.ui.ed79f0f92534" />{' '}
                                    {stage5Date(
                                        new Date(payment.voided_at),
                                        stage5Locale,
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
                        <AlertTitle>
                            <Stage5Text k="stage5.ui.bad514aff9dc" />
                        </AlertTitle>
                        <AlertDescription>
                            {voidEligibility.reason}
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>
                                <Stage5Text k="stage5.ui.844dbba9d8ec" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.1795d163388f',
                                    stage5Locale,
                                )}
                                value={money.format(signedPaymentAmount)}
                                emphasis
                            />
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.fabb2b5c779a',
                                    stage5Locale,
                                )}
                                value={stage5Display(
                                    typeLabels[payment.type] ?? payment.type,
                                    stage5Locale,
                                )}
                            />
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.5ac33f2c588b',
                                    stage5Locale,
                                )}
                                value={`${payment.payment_method.name} (${payment.payment_method.code})`}
                            />
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.b7964404a785',
                                    stage5Locale,
                                )}
                                value={
                                    payment.financial_category
                                        ? `${payment.financial_category.code} — ${payment.financial_category.name}`
                                        : '—'
                                }
                            />
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.3b251765602d',
                                    stage5Locale,
                                )}
                                value={payment.external_reference ?? '—'}
                            />
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.3d4782fa2f70',
                                    stage5Locale,
                                )}
                                value={payment.receiver?.name ?? 'Sistem'}
                            />
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.af0ab4433946',
                                    stage5Locale,
                                )}
                                value={payment.customer?.name ?? '—'}
                            />
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.92218a799243',
                                    stage5Locale,
                                )}
                                value={payment.customer?.customer_number ?? '—'}
                            />
                            <div className="sm:col-span-2">
                                <Info
                                    label={stage5Translate(
                                        'stage5.ui.9f09aefd0dd4',
                                        stage5Locale,
                                    )}
                                    value={payment.notes ?? '—'}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage5Text k="stage5.ui.ad1a90983285" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.d4d23aee0b07',
                                    stage5Locale,
                                )}
                                value={stage5Display(
                                    sourceLabels[
                                        payment.source_context ?? ''
                                    ] ?? 'Legacy / tidak diketahui',
                                    stage5Locale,
                                )}
                            />
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.3166201d7baf',
                                    stage5Locale,
                                )}
                                value={source.label}
                            />
                            {source.href && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={source.href}>
                                        <Stage5Text k="stage5.ui.d33a5b9e6895" />
                                        <ExternalLink className="size-4" />
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            <Stage5Text k="stage5.ui.f484f1589ffc" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.3c78f49c2760',
                                    stage5Locale,
                                )}
                                value={money.format(
                                    refundEligibility.paid_refund_amount,
                                )}
                            />
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.09e5ddbe1057',
                                    stage5Locale,
                                )}
                                value={money.format(
                                    refundEligibility.reserved_refund_amount,
                                )}
                            />
                            <Info
                                label={stage5Translate(
                                    'stage5.ui.c8b6af554e4d',
                                    stage5Locale,
                                )}
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
                                            <th className="py-2">
                                                <Stage5Text k="stage5.ui.e17c8ad0dc2e" />
                                            </th>
                                            <th className="py-2">
                                                <Stage5Text k="stage5.ui.5ac33f2c588b" />
                                            </th>
                                            <th className="py-2">
                                                <Stage5Text k="stage5.ui.bae7d5be7082" />
                                            </th>
                                            <th className="py-2 text-right">
                                                <Stage5Text k="stage5.ui.1795d163388f" />
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
                                                    {refund.purpose && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {refund.purpose ===
                                                            'booking_cancellation'
                                                                ? 'Pembatalan Booking'
                                                                : 'Koreksi Pembayaran'}
                                                        </p>
                                                    )}
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
                                <Stage5Text k="stage5.ui.6b21efb1a372" />
                            </p>
                        )}
                        {!refundEligibility.allowed &&
                            refundEligibility.reason && (
                                <p className="text-sm text-muted-foreground">
                                    {refundEligibility.reason}
                                </p>
                            )}
                    </CardContent>
                </Card>

                <section className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage5Text k="stage5.ui.774cb2029193" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {payment.cash_session ? (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Info
                                        label={stage5Translate(
                                            'stage5.ui.d672995a1465',
                                            stage5Locale,
                                        )}
                                        value={`${payment.cash_session.register.code} — ${payment.cash_session.register.name}`}
                                    />
                                    <Info
                                        label={stage5Translate(
                                            'stage5.ui.292da2fd4603',
                                            stage5Locale,
                                        )}
                                        value={payment.cash_session.status}
                                    />
                                    <Info
                                        label={stage5Translate(
                                            'stage5.ui.374027752cd7',
                                            stage5Locale,
                                        )}
                                        value={stage5Date(
                                            new Date(
                                                payment.cash_session.opened_at,
                                            ),
                                            stage5Locale,
                                        )}
                                    />
                                    <Info
                                        label={stage5Translate(
                                            'stage5.ui.0e97e7214c08',
                                            stage5Locale,
                                        )}
                                        value={
                                            payment.cash_session.closed_at
                                                ? stage5Date(
                                                      new Date(
                                                          payment.cash_session
                                                              .closed_at,
                                                      ),
                                                      stage5Locale,
                                                  )
                                                : 'Masih aktif'
                                        }
                                    />
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    <Stage5Text k="stage5.ui.d1f10cd1f0d9" />
                                </p>
                            )}

                            {payment.cash_transactions.length > 0 && (
                                <div className="overflow-x-auto border-t pt-4">
                                    <table className="w-full min-w-[560px] text-sm">
                                        <thead className="text-left text-muted-foreground">
                                            <tr>
                                                <th className="py-2">
                                                    <Stage5Text k="stage5.ui.1aa2f31ee7cc" />
                                                </th>
                                                <th className="py-2">
                                                    <Stage5Text k="stage5.ui.c86c93709b3d" />
                                                </th>
                                                <th className="py-2 text-right">
                                                    <Stage5Text k="stage5.ui.1795d163388f" />
                                                </th>
                                                <th className="py-2 text-right">
                                                    <Stage5Text k="stage5.ui.8b0fcd0c1f89" />
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
                            <CardTitle>
                                <Stage5Text k="stage5.ui.e01eacc119b7" />
                            </CardTitle>
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
                                                {stage5Date(
                                                    new Date(
                                                        activity.created_at,
                                                    ),
                                                    stage5Locale,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                                {activities.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        <Stage5Text k="stage5.ui.24e1c2852051" />
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
                            <Stage5Text k="stage5.ui.9ef202f871b3" />{' '}
                            {payment.payment_number}
                        </DialogTitle>
                        <DialogDescription>
                            <Stage5Text k="stage5.ui.93fc47a981b1" />{' '}
                            {money.format(refundEligibility.refundable_amount)}
                            <Stage5Text k="stage5.ui.63bdda617579" />
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitRefund}>
                        {bookingDp && (
                            <div className="space-y-2">
                                <Label>
                                    <Stage5Text k="stage5.ui.2c3b6a6f8734" />
                                </Label>
                                <Select
                                    value={refundForm.data.purpose}
                                    onValueChange={(value) =>
                                        refundForm.setData('purpose', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder={stage5Translate(
                                                'stage5.ui.3790f31f0139',
                                                stage5Locale,
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {cancellationAvailable && (
                                            <SelectItem value="booking_cancellation">
                                                <Stage5Text k="stage5.ui.948babbd77f3" />
                                            </SelectItem>
                                        )}
                                        <SelectItem value="payment_correction">
                                            <Stage5Text k="stage5.ui.4eac1385db6f" />
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={refundForm.errors.purpose}
                                />
                                {refundForm.data.purpose ===
                                    'booking_cancellation' && (
                                    <Alert>
                                        <AlertTriangle className="size-4" />
                                        <AlertTitle>
                                            <Stage5Text k="stage5.ui.badc4290067f" />
                                        </AlertTitle>
                                        <AlertDescription>
                                            <Stage5Text k="stage5.ui.3f6af5f706e5" />
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="refund-amount">
                                <Stage5Text k="stage5.ui.1795d163388f" />
                            </Label>
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
                                placeholder={stage5Translate(
                                    'stage5.ui.3b8f9bd8120d',
                                    stage5Locale,
                                )}
                            />
                            <InputError message={refundForm.errors.amount} />
                        </div>
                        <div className="space-y-2">
                            <Label>
                                <Stage5Text k="stage5.ui.06867a894580" />
                            </Label>
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
                                    <SelectValue
                                        placeholder={stage5Translate(
                                            'stage5.ui.cfabec6a6763',
                                            stage5Locale,
                                        )}
                                    />
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
                            <Label htmlFor="refund-reason">
                                <Stage5Text k="stage5.ui.25118d647ee3" />
                            </Label>
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
                                placeholder={stage5Translate(
                                    'stage5.ui.03b7aa1b137c',
                                    stage5Locale,
                                )}
                            />
                            <InputError message={refundForm.errors.reason} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="refund-request-notes">
                                <Stage5Text k="stage5.ui.e2149d87871f" />
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
                                <Stage5Text k="stage5.ui.1433539c3b8f" />
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    refundForm.processing ||
                                    Number(refundForm.data.amount) <= 0 ||
                                    (bookingDp && !refundForm.data.purpose) ||
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
                        <DialogTitle>
                            <Stage5Text k="stage5.ui.207c7c00630b" />{' '}
                            {payment.payment_number}
                        </DialogTitle>
                        <DialogDescription>
                            <Stage5Text k="stage5.ui.da2aa61b1ef8" />
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitVoid}>
                        <div className="space-y-2">
                            <Label htmlFor="void-reason">
                                <Stage5Text k="stage5.ui.c2a53eee88e3" />
                            </Label>
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
                                placeholder={stage5Translate(
                                    'stage5.ui.e8f3b6e4be4c',
                                    stage5Locale,
                                )}
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
                                <Stage5Text k="stage5.ui.1433539c3b8f" />
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

    if (payment.operational_expense) {
        return {
            label: payment.operational_expense.expense_number,
            href: `/finance/expenses/${payment.operational_expense.id}`,
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
    const { locale: stage5Locale } = useAppLocale();
    const variant =
        status === 'rejected'
            ? 'destructive'
            : status === 'cancelled'
              ? 'outline'
              : status === 'requested'
                ? 'secondary'
                : 'default';

    return (
        <Badge variant={variant}>{stage5Display(status, stage5Locale)}</Badge>
    );
}

function activityLabel(event: string): string {
    const labels: Record<string, string> = {
        'payment.voided': 'Payment di-void',
    };

    return labels[event] ?? event;
}
