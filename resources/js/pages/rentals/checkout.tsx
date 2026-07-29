import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import type { Booking } from '@/types';

type PaymentMethod = {
    id: number;
    name: string;
    requires_reference: boolean;
};
type AssetInput = {
    asset_id: number;
    condition: 'excellent' | 'good' | 'fair';
    notes: string;
};
type Props = {
    booking: Booking;
    paymentMethods: PaymentMethod[];
    financialSummary: {
        rental_paid: number;
        deposit_paid: number;
        balance_due: number;
        deposit_due: number;
    };
};
const localNow = () => {
    const date = new Date();

    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

    return date.toISOString().slice(0, 16);
};
const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function RentalCheckout({
    booking,
    paymentMethods,
    financialSummary,
}: Props) {
    const reservedAssets = booking.items.flatMap((item) =>
        (item.reservations ?? []).map((reservation) => ({
            reservation,
            item,
        })),
    );
    const form = useForm({
        checked_out_at: localNow(),
        checkout_notes: '',
        assets: reservedAssets.map(({ reservation }): AssetInput => ({
            asset_id: reservation.asset.id,
            condition: ['excellent', 'good', 'fair'].includes(
                reservation.asset.condition,
            )
                ? (reservation.asset.condition as AssetInput['condition'])
                : 'good',
            notes: '',
        })),
        payment_amount: 0,
        deposit_paid: 0,
        payment_method_id: 0,
        payment_reference: '',
        payment_notes: '',
    });
    const updateAsset = (index: number, patch: Partial<AssetInput>) =>
        form.setData(
            'assets',
            form.data.assets.map((asset, position) =>
                position === index ? { ...asset, ...patch } : asset,
            ),
        );
    const remainingBalance = Math.max(
        0,
        financialSummary.balance_due - form.data.payment_amount,
    );
    const hasNewPayment =
        form.data.payment_amount > 0 || form.data.deposit_paid > 0;

    return (
        <>
            <Head title={`Checkout ${booking.booking_number}`} />
            <form
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`/rentals/checkout/${booking.id}`);
                }}
            >
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/bookings/${booking.id}`}>
                                <ArrowLeft />
                                Kembali ke booking
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-2xl font-semibold">
                            Checkout booking
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {booking.booking_number} · {booking.customer?.name}{' '}
                            · {booking.branch?.name}
                        </p>
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        <LogOut />
                        Checkout menjadi rental aktif
                    </Button>
                </header>

                <section className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Jadwal aktual</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Label>Waktu checkout</Label>
                            <Input
                                type="datetime-local"
                                value={form.data.checked_out_at}
                                onChange={(event) =>
                                    form.setData(
                                        'checked_out_at',
                                        event.target.value,
                                    )
                                }
                            />
                            {form.errors.checked_out_at && (
                                <p className="text-sm text-destructive">
                                    {form.errors.checked_out_at}
                                </p>
                            )}
                            <p className="text-sm text-muted-foreground">
                                Batas kembali:{' '}
                                {new Date(booking.ends_at).toLocaleString(
                                    'id-ID',
                                )}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Nilai rental</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="flex justify-between">
                                <span>Total</span>
                                <b>
                                    {money.format(Number(booking.total_amount))}
                                </b>
                            </p>
                            <p className="flex justify-between">
                                <span>Deposit disarankan</span>
                                <b>
                                    {money.format(
                                        Number(booking.deposit_required),
                                    )}
                                </b>
                            </p>
                            <p className="flex justify-between text-emerald-600">
                                <span>DP sewa sudah masuk</span>
                                <b>
                                    {money.format(financialSummary.rental_paid)}
                                </b>
                            </p>
                            <p className="flex justify-between font-semibold">
                                <span>Sisa tagihan</span>
                                <b>
                                    {money.format(financialSummary.balance_due)}
                                </b>
                            </p>
                            <p className="flex justify-between text-muted-foreground">
                                <span>Deposit sudah masuk</span>
                                <b>
                                    {money.format(
                                        financialSummary.deposit_paid,
                                    )}
                                </b>
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Pembayaran awal</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <RupiahInput
                                placeholder={`Pembayaran rental · maks ${money.format(financialSummary.balance_due)}`}
                                value={form.data.payment_amount}
                                onValueChange={(value) =>
                                    form.setData('payment_amount', value)
                                }
                            />
                            <RupiahInput
                                placeholder={`Deposit tambahan · maks ${money.format(financialSummary.deposit_due)}`}
                                value={form.data.deposit_paid}
                                onValueChange={(value) =>
                                    form.setData('deposit_paid', value)
                                }
                            />
                            {form.errors.payment_amount && (
                                <p className="text-sm text-destructive">
                                    {form.errors.payment_amount}
                                </p>
                            )}
                            {form.errors.deposit_paid && (
                                <p className="text-sm text-destructive">
                                    {form.errors.deposit_paid}
                                </p>
                            )}
                            <Select
                                value={String(
                                    form.data.payment_method_id || '',
                                )}
                                onValueChange={(value) =>
                                    form.setData(
                                        'payment_method_id',
                                        Number(value),
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Metode pembayaran" />
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
                            <Input
                                placeholder="Referensi transfer/QRIS"
                                value={form.data.payment_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'payment_reference',
                                        event.target.value,
                                    )
                                }
                            />
                            {form.errors.payment_method_id && (
                                <p className="text-sm text-destructive">
                                    {form.errors.payment_method_id}
                                </p>
                            )}
                            {form.errors.payment_reference && (
                                <p className="text-sm text-destructive">
                                    {form.errors.payment_reference}
                                </p>
                            )}
                            {hasNewPayment &&
                                form.data.payment_method_id === 0 && (
                                    <p className="text-sm text-amber-700">
                                        Pilih metode pembayaran untuk mencatat
                                        pembayaran ini.
                                    </p>
                                )}
                            {remainingBalance > 0 && (
                                <div className="flex gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                    <p>
                                        Setelah checkout masih ada tagihan{' '}
                                        <b>{money.format(remainingBalance)}</b>.
                                        Rental tetap dapat diaktifkan dan
                                        pelunasan dicatat kemudian.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Verifikasi unit dan kondisi awal</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {reservedAssets.map(({ reservation, item }, index) => (
                            <div
                                key={reservation.asset.id}
                                className="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_180px_1fr]"
                            >
                                <div>
                                    <p className="font-medium">
                                        {reservation.asset.asset_code}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {item.description}
                                        {reservation.asset.serial_number
                                            ? ` · SN ${reservation.asset.serial_number}`
                                            : ''}
                                    </p>
                                </div>
                                <Select
                                    value={form.data.assets[index]?.condition}
                                    onValueChange={(
                                        value: AssetInput['condition'],
                                    ) =>
                                        updateAsset(index, { condition: value })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="excellent">
                                            Sangat baik
                                        </SelectItem>
                                        <SelectItem value="good">
                                            Baik
                                        </SelectItem>
                                        <SelectItem value="fair">
                                            Cukup
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    placeholder="Kelengkapan/catatan unit"
                                    value={form.data.assets[index]?.notes ?? ''}
                                    onChange={(event) =>
                                        updateAsset(index, {
                                            notes: event.target.value,
                                        })
                                    }
                                />
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Catatan checkout</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <textarea
                            className="min-h-24 w-full rounded-md border bg-transparent p-3 text-sm"
                            value={form.data.checkout_notes}
                            onChange={(event) =>
                                form.setData(
                                    'checkout_notes',
                                    event.target.value,
                                )
                            }
                            placeholder="Catatan umum dan kelengkapan yang dibawa."
                        />
                    </CardContent>
                </Card>
            </form>
        </>
    );
}
