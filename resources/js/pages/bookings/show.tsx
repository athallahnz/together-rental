import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, LogOut, Pencil, XCircle } from 'lucide-react';
import { useState } from 'react';
import {
    Stage4Text,
    stage4Translate,
    stage4FormatDateTime,
    stage4TranslateDynamic,
} from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import { TransactionDocumentActions } from '@/components/documents/transaction-document-actions';
import { CashSessionSelect } from '@/components/finance/cash-session-select';
import type {
    CashSessionOption,
    PaymentMethodOption,
} from '@/components/finance/cash-session-select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { RupiahInput } from '@/components/ui/rupiah-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Booking } from '@/types';

type Props = {
    booking: Booking;
    permissions: {
        create: boolean;
        update: boolean;
        cancel: boolean;
        checkout: boolean;
        payment: boolean;
    };
    financialSummary: {
        rental_paid: number;
        deposit_paid: number;
        rental_refunded: number;
        deposit_refunded: number;
    };
    paymentMethods: PaymentMethodOption[];
    cashSessions: CashSessionOption[];
};
const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function BookingShow({
    booking,
    permissions,
    financialSummary,
    paymentMethods,
    cashSessions,
}: Props) {
    const { locale: stage4Locale } = useAppLocale();

    const [reason, setReason] = useState('');
    const [cancelling, setCancelling] = useState(false);
    const active = ['draft', 'confirmed'].includes(booking.status);
    const paymentForm = useForm({
        payment_amount: 0,
        deposit_paid: 0,
        payment_method_id: 0,
        cash_session_id: null as number | null,
        payment_reference: '',
        payment_notes: '',
    });
    const balanceDue = Math.max(
        0,
        Number(booking.total_amount) - financialSummary.rental_paid,
    );
    const depositDue = Math.max(
        0,
        Number(booking.deposit_required) - financialSummary.deposit_paid,
    );

    return (
        <>
            <Head title={booking.booking_number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/bookings">
                                <ArrowLeft />
                                <Stage4Text k="stage4.ui.5cd285282368" />
                            </Link>
                        </Button>
                        <div className="mt-3 flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">
                                {booking.booking_number}
                            </h1>
                            <Badge>
                                {stage4TranslateDynamic(
                                    booking.status,
                                    stage4Locale,
                                )}
                            </Badge>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {booking.branch?.name}
                            <Stage4Text k="stage4.ui.e21a079b3a50" />{' '}
                            {booking.customer?.name}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {permissions.update && booking.status === 'draft' && (
                            <Button variant="outline" asChild>
                                <Link href={`/bookings/${booking.id}/edit`}>
                                    <Pencil />
                                    <Stage4Text k="stage4.ui.5301648dcf6b" />
                                </Link>
                            </Button>
                        )}
                        {permissions.checkout &&
                            booking.status === 'confirmed' && (
                                <Button asChild>
                                    <Link
                                        href={`/rentals/checkout/${booking.id}`}
                                    >
                                        <LogOut />
                                        <Stage4Text k="stage4.ui.e52caf59c035" />
                                    </Link>
                                </Button>
                            )}
                        {permissions.update && booking.status === 'draft' && (
                            <Button
                                onClick={() =>
                                    router.post(
                                        `/bookings/${booking.id}/confirm`,
                                    )
                                }
                            >
                                <CheckCircle2 />
                                <Stage4Text k="stage4.ui.67b7bd6433f2" />
                            </Button>
                        )}
                    </div>
                </header>

                <TransactionDocumentActions
                    sourceType="booking"
                    sourceReference={booking.booking_number}
                />
                {booking.status === 'expired' && (
                    <Alert variant="destructive">
                        <XCircle className="size-4" />
                        <AlertTitle>
                            <Stage4Text k="stage4.ui.8be837ad9fff" />
                        </AlertTitle>
                        <AlertDescription>
                            <Stage4Text k="stage4.ui.d3aebf2e6cd3" />
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage4Text k="stage4.ui.92d937165b09" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p>
                                <b>
                                    <Stage4Text k="stage4.ui.328767d2bead" />
                                </b>{' '}
                                {stage4FormatDateTime(
                                    new Date(booking.starts_at),
                                    stage4Locale,
                                )}
                            </p>
                            <p>
                                <b>
                                    <Stage4Text k="stage4.ui.44a01a73b05e" />
                                </b>{' '}
                                {stage4FormatDateTime(
                                    new Date(booking.ends_at),
                                    stage4Locale,
                                )}
                            </p>
                            <p>
                                <b>
                                    <Stage4Text k="stage4.ui.0fb712917439" />
                                </b>{' '}
                                {stage4TranslateDynamic(
                                    booking.source,
                                    stage4Locale,
                                )}
                            </p>
                            <p>
                                <b>
                                    <Stage4Text k="stage4.ui.cf6b5fa84600" />
                                </b>{' '}
                                {booking.rate_plan?.name}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage4Text k="stage4.ui.af0ab4433946" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="font-medium">
                                {booking.customer?.name}
                            </p>
                            <p>{booking.customer?.customer_number}</p>
                            <p>{booking.customer?.phone || '-'}</p>
                            <p>
                                <Stage4Text k="stage4.ui.9511746039e4" />{' '}
                                {booking.customer?.risk_level || '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage4Text k="stage4.ui.c88d9cec0c60" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="flex justify-between">
                                <span>
                                    <Stage4Text k="stage4.ui.97f7359ed801" />
                                </span>
                                <b>{money.format(Number(booking.subtotal))}</b>
                            </p>
                            {Number(booking.discount_amount) > 0 && (
                                <p className="flex justify-between text-emerald-600">
                                    <span>
                                        <Stage4Text k="stage4.ui.6cc10ed56760" />
                                    </span>
                                    <b>
                                        -
                                        {money.format(
                                            Number(booking.discount_amount),
                                        )}
                                    </b>
                                </p>
                            )}
                            {booking.promotion && (
                                <p className="flex justify-between text-muted-foreground">
                                    <span>
                                        <Stage4Text k="stage4.ui.e0f4f87da3de" />
                                    </span>
                                    <b>{booking.promotion.code}</b>
                                </p>
                            )}
                            {!booking.promotion &&
                                booking.customer?.is_member && (
                                    <p className="text-xs text-muted-foreground">
                                        <Stage4Text k="stage4.ui.1f1cdd5cc4c9" />
                                    </p>
                                )}
                            <p className="flex justify-between">
                                <span>
                                    <Stage4Text k="stage4.ui.51d7d22341f4" />
                                </span>
                                <b>
                                    {money.format(
                                        Number(booking.deposit_required),
                                    )}
                                </b>
                            </p>
                            <p className="flex justify-between border-t pt-2 text-base">
                                <span>
                                    <Stage4Text k="stage4.ui.b25928c69902" />
                                </span>
                                <b>
                                    {money.format(Number(booking.total_amount))}
                                </b>
                            </p>
                            <p className="flex justify-between text-emerald-600">
                                <span>
                                    <Stage4Text k="stage4.ui.ccc4b12e0fb8" />
                                </span>
                                <b>
                                    {money.format(financialSummary.rental_paid)}
                                </b>
                            </p>
                            {financialSummary.rental_refunded > 0 && (
                                <p className="flex justify-between text-xs text-amber-700 dark:text-amber-400">
                                    <span>
                                        <Stage4Text k="stage4.ui.37510868761e" />
                                    </span>
                                    <span>
                                        {money.format(
                                            financialSummary.rental_refunded,
                                        )}
                                    </span>
                                </p>
                            )}
                            <p className="flex justify-between font-semibold">
                                <span>
                                    <Stage4Text k="stage4.ui.c0fd40e41b59" />
                                </span>
                                <b>{money.format(balanceDue)}</b>
                            </p>
                            <p className="flex justify-between text-muted-foreground">
                                <span>
                                    <Stage4Text k="stage4.ui.6f4d4e48915f" />
                                </span>
                                <b>
                                    {money.format(
                                        financialSummary.deposit_paid,
                                    )}
                                </b>
                            </p>
                        </CardContent>
                    </Card>
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle>
                            <Stage4Text k="stage4.ui.8d3fc7602767" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {booking.items.map((item) => (
                            <div
                                key={item.id}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex justify-between">
                                    <div>
                                        <p className="font-medium">
                                            {item.description}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.quantity}
                                            <Stage4Text k="stage4.ui.ee75f098d581" />{' '}
                                            {money.format(
                                                Number(item.unit_rate),
                                            )}
                                        </p>
                                    </div>
                                    <b>
                                        {money.format(
                                            Number(item.total_amount),
                                        )}
                                    </b>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {item.bulk_reservations?.map(
                                        (reservation) => (
                                            <Badge
                                                key={`bulk-${reservation.id}`}
                                                variant="secondary"
                                            >
                                                {reservation.product.name}
                                                <Stage4Text k="stage4.ui.7ed3a04cc02c" />{' '}
                                                {reservation.quantity}
                                                <Stage4Text k="stage4.ui.0df9eea0bad5" />
                                            </Badge>
                                        ),
                                    )}
                                    {item.reservations?.map((reservation) => (
                                        <Badge
                                            key={reservation.id}
                                            variant="secondary"
                                        >
                                            {reservation.asset.asset_code}
                                            <Stage4Text k="stage4.ui.e21a079b3a50" />{' '}
                                            {stage4TranslateDynamic(
                                                reservation.asset.condition,
                                                stage4Locale,
                                            )}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage4Text k="stage4.ui.03d06fe851ed" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {booking.payments?.length ? (
                                booking.payments.map((payment) => (
                                    <div
                                        key={payment.id}
                                        className="flex items-start justify-between border-b pb-3 text-sm"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {payment.type === 'deposit'
                                                    ? stage4Translate(
                                                          'stage4.ui.8aa57d61dfa3',
                                                          stage4Locale,
                                                      )
                                                    : stage4Translate(
                                                          'stage4.ui.855413f32255',
                                                          stage4Locale,
                                                      )}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {payment.payment_number}
                                                <Stage4Text k="stage4.ui.e21a079b3a50" />{' '}
                                                {payment.payment_method?.name ??
                                                    '-'}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <b>
                                                {money.format(
                                                    Number(payment.amount),
                                                )}
                                            </b>
                                            {payment.refunds
                                                ?.filter(
                                                    (refund) =>
                                                        refund.status ===
                                                        'paid',
                                                )
                                                .map((refund) => (
                                                    <p
                                                        key={refund.id}
                                                        className="text-xs text-amber-700 dark:text-amber-400"
                                                    >
                                                        <Stage4Text k="stage4.ui.e17c8ad0dc2e" />{' '}
                                                        {refund.refund_number}:
                                                        -
                                                        {money.format(
                                                            Number(
                                                                refund.amount,
                                                            ),
                                                        )}
                                                    </p>
                                                ))}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    <Stage4Text k="stage4.ui.b1f156bad5b7" />
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    {permissions.payment &&
                        active &&
                        (balanceDue > 0 || depositDue > 0) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        <Stage4Text k="stage4.ui.89ceb3850e02" />
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        className="space-y-3"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            paymentForm.post(
                                                `/bookings/${booking.id}/payments`,
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        paymentForm.reset(),
                                                },
                                            );
                                        }}
                                    >
                                        <RupiahInput
                                            placeholder={`${stage4Translate('stage4.ui.c0fd40e41b59', stage4Locale)} ${money.format(balanceDue)}`}
                                            value={
                                                paymentForm.data.payment_amount
                                            }
                                            onValueChange={(value) =>
                                                paymentForm.setData(
                                                    'payment_amount',
                                                    value,
                                                )
                                            }
                                        />
                                        <RupiahInput
                                            placeholder={`Deposit jaminan · kurang ${money.format(depositDue)}`}
                                            value={
                                                paymentForm.data.deposit_paid
                                            }
                                            onValueChange={(value) =>
                                                paymentForm.setData(
                                                    'deposit_paid',
                                                    value,
                                                )
                                            }
                                        />
                                        <Select
                                            value={String(
                                                paymentForm.data
                                                    .payment_method_id || '',
                                            )}
                                            onValueChange={(value) => {
                                                paymentForm.setData(
                                                    'payment_method_id',
                                                    Number(value),
                                                );
                                                paymentForm.setData(
                                                    'cash_session_id',
                                                    null,
                                                );
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder={stage4Translate(
                                                        'stage4.ui.53eb1a623ade',
                                                        stage4Locale,
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {paymentMethods.map(
                                                    (method) => (
                                                        <SelectItem
                                                            key={method.id}
                                                            value={String(
                                                                method.id,
                                                            )}
                                                        >
                                                            {method.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <CashSessionSelect
                                            paymentMethods={paymentMethods}
                                            cashSessions={cashSessions}
                                            branchId={booking.branch_id}
                                            paymentMethodId={
                                                paymentForm.data
                                                    .payment_method_id
                                            }
                                            value={
                                                paymentForm.data.cash_session_id
                                            }
                                            onValueChange={(value) =>
                                                paymentForm.setData(
                                                    'cash_session_id',
                                                    value,
                                                )
                                            }
                                            error={
                                                paymentForm.errors
                                                    .cash_session_id
                                            }
                                        />
                                        <Input
                                            placeholder={stage4Translate(
                                                'stage4.ui.e657fd4a7334',
                                                stage4Locale,
                                            )}
                                            value={
                                                paymentForm.data
                                                    .payment_reference
                                            }
                                            onChange={(event) =>
                                                paymentForm.setData(
                                                    'payment_reference',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <Button
                                            disabled={paymentForm.processing}
                                        >
                                            <Stage4Text k="stage4.ui.a24f4a17df98" />
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage4Text k="stage4.ui.b4a4bddfcda6" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {booking.status_histories?.map((history) => (
                                <div
                                    key={history.id}
                                    className="border-l-2 pl-3 text-sm"
                                >
                                    <p className="font-medium">
                                        {history.from_status
                                            ? stage4TranslateDynamic(
                                                  history.from_status,
                                                  stage4Locale,
                                              )
                                            : stage4Translate(
                                                  'stage4.ui.99bc6e24bcd6',
                                                  stage4Locale,
                                              )}{' '}
                                        {'→'}{' '}
                                        {stage4TranslateDynamic(
                                            history.to_status,
                                            stage4Locale,
                                        )}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {history.reason}
                                        <Stage4Text k="stage4.ui.e21a079b3a50" />{' '}
                                        {history.changer?.name ??
                                            stage4Translate(
                                                'stage4.ui.991f31a64b52',
                                                stage4Locale,
                                            )}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    {permissions.cancel && active && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    <Stage4Text k="stage4.ui.8a24cedef3d0" />
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <textarea
                                    className="min-h-24 w-full rounded-md border bg-transparent p-3 text-sm"
                                    placeholder={stage4Translate(
                                        'stage4.ui.ee41545fc5ae',
                                        stage4Locale,
                                    )}
                                    value={reason}
                                    onChange={(event) =>
                                        setReason(event.target.value)
                                    }
                                />
                                <Button
                                    variant="destructive"
                                    disabled={
                                        cancelling || reason.trim().length < 5
                                    }
                                    onClick={() =>
                                        router.post(
                                            `/bookings/${booking.id}/cancel`,
                                            { reason },
                                            {
                                                onStart: () =>
                                                    setCancelling(true),
                                                onFinish: () =>
                                                    setCancelling(false),
                                            },
                                        )
                                    }
                                >
                                    <XCircle />
                                    <Stage4Text k="stage4.ui.9bd9e72193c2" />
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
