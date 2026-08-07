import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, LogOut, Pencil, XCircle } from 'lucide-react';
import { useState } from 'react';
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
    financialSummary: { rental_paid: number; deposit_paid: number };
    paymentMethods: Array<{
        id: number;
        name: string;
        requires_reference: boolean;
    }>;
};
const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});
const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'full',
    timeStyle: 'short',
});

export default function BookingShow({
    booking,
    permissions,
    financialSummary,
    paymentMethods,
}: Props) {
    const [reason, setReason] = useState('');
    const [cancelling, setCancelling] = useState(false);
    const active = ['draft', 'confirmed'].includes(booking.status);
    const paymentForm = useForm({
        payment_amount: 0,
        deposit_paid: 0,
        payment_method_id: 0,
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
                                Daftar booking
                            </Link>
                        </Button>
                        <div className="mt-3 flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">
                                {booking.booking_number}
                            </h1>
                            <Badge>{booking.status}</Badge>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {booking.branch?.name} · {booking.customer?.name}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {permissions.update && booking.status === 'draft' && (
                            <Button variant="outline" asChild>
                                <Link href={`/bookings/${booking.id}/edit`}>
                                    <Pencil />
                                    Edit
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
                                        Checkout booking
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
                                Konfirmasi
                            </Button>
                        )}
                    </div>
                </header>
                {booking.status === 'expired' && (
                    <Alert variant="destructive">
                        <XCircle className="size-4" />
                        <AlertTitle>Booking kedaluwarsa</AlertTitle>
                        <AlertDescription>
                            Periode booking telah berakhir dan reservasi tidak
                            lagi boleh digunakan untuk checkout. Buat booking
                            baru bila pelanggan tetap membutuhkan unit.
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Jadwal</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p>
                                <b>Mulai:</b>{' '}
                                {dateTime.format(new Date(booking.starts_at))}
                            </p>
                            <p>
                                <b>Selesai:</b>{' '}
                                {dateTime.format(new Date(booking.ends_at))}
                            </p>
                            <p>
                                <b>Sumber:</b> {booking.source}
                            </p>
                            <p>
                                <b>Rate:</b> {booking.rate_plan?.name}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Pelanggan</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="font-medium">
                                {booking.customer?.name}
                            </p>
                            <p>{booking.customer?.customer_number}</p>
                            <p>{booking.customer?.phone || '-'}</p>
                            <p>Risk: {booking.customer?.risk_level || '-'}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Nilai booking</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="flex justify-between">
                                <span>Subtotal</span>
                                <b>{money.format(Number(booking.subtotal))}</b>
                            </p>
                            <p className="flex justify-between">
                                <span>Deposit wajib</span>
                                <b>
                                    {money.format(
                                        Number(booking.deposit_required),
                                    )}
                                </b>
                            </p>
                            <p className="flex justify-between border-t pt-2 text-base">
                                <span>Total</span>
                                <b>
                                    {money.format(Number(booking.total_amount))}
                                </b>
                            </p>
                            <p className="flex justify-between text-emerald-600">
                                <span>DP sewa masuk</span>
                                <b>
                                    {money.format(financialSummary.rental_paid)}
                                </b>
                            </p>
                            <p className="flex justify-between font-semibold">
                                <span>Sisa tagihan</span>
                                <b>{money.format(balanceDue)}</b>
                            </p>
                            <p className="flex justify-between text-muted-foreground">
                                <span>Deposit jaminan masuk</span>
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
                        <CardTitle>Item dan unit terreservasi</CardTitle>
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
                                            {item.quantity} ×{' '}
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
                                    {item.reservations?.map((reservation) => (
                                        <Badge
                                            key={reservation.id}
                                            variant="secondary"
                                        >
                                            {reservation.asset.asset_code} ·{' '}
                                            {reservation.asset.condition}
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
                            <CardTitle>Riwayat pembayaran</CardTitle>
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
                                                    ? 'Deposit jaminan'
                                                    : 'Pembayaran sewa'}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {payment.payment_number} ·{' '}
                                                {payment.payment_method?.name ??
                                                    '-'}
                                            </p>
                                        </div>
                                        <b>
                                            {money.format(
                                                Number(payment.amount),
                                            )}
                                        </b>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada pembayaran.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    {permissions.payment &&
                        active &&
                        (balanceDue > 0 || depositDue > 0) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Terima pembayaran</CardTitle>
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
                                            placeholder={`DP/pembayaran sewa · sisa ${money.format(balanceDue)}`}
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
                                            onValueChange={(value) =>
                                                paymentForm.setData(
                                                    'payment_method_id',
                                                    Number(value),
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Metode pembayaran" />
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
                                        <Input
                                            placeholder="Referensi transfer/QRIS"
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
                                            Simpan pembayaran
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Riwayat status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {booking.status_histories?.map((history) => (
                                <div
                                    key={history.id}
                                    className="border-l-2 pl-3 text-sm"
                                >
                                    <p className="font-medium">
                                        {history.from_status ?? 'awal'} →{' '}
                                        {history.to_status}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {history.reason} ·{' '}
                                        {history.changer?.name ?? 'Sistem'}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    {permissions.cancel && active && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Batalkan booking</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <textarea
                                    className="min-h-24 w-full rounded-md border bg-transparent p-3 text-sm"
                                    placeholder="Alasan pembatalan minimal 5 karakter"
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
                                    Batalkan dan lepas reservasi
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
