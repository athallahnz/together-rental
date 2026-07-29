import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    AlertTriangle,
    PackageCheck,
    RotateCcw,
    ShieldCheck,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RupiahInput } from '@/components/ui/rupiah-input';

type Rental = {
    id: number;
    rental_number: string;
    status: string;
    checked_out_at: string;
    due_at: string;
    subtotal: string;
    total_amount: string;
    paid_amount: string;
    deposit_amount: string;
    balance_due: string;
    notes: string | null;
    returns: Array<{
        id: number;
        return_number: string;
        type: string;
        status: string;
        returned_at: string;
        total_charge_amount: string;
    }>;
    branch: { name: string };
    customer: { name: string; customer_number: string; phone: string | null };
    booking?: { booking_number: string; source: string } | null;
    rate_plan?: { name: string } | null;
    items: Array<{
        id: number;
        description: string;
        quantity: number;
        total_amount: string;
        assets: Array<{
            id: number;
            checkout_condition: string;
            notes: string | null;
            asset: {
                asset_code: string;
                serial_number: string | null;
                status: string;
            };
        }>;
    }>;
    status_histories: Array<{
        id: number;
        from_status: string | null;
        to_status: string;
        reason: string | null;
        changer?: { name: string } | null;
    }>;
    financial_adjustments: Array<{
        id: number;
        adjustment_number: string;
        component: 'charge' | 'payment' | 'deposit';
        direction: 'increase' | 'decrease';
        amount: string;
        balance_before: string;
        balance_after: string;
        reason: string;
        notes: string | null;
        created_at: string;
        creator: { name: string };
    }>;
    operational_corrections: Array<{
        id: number;
        correction_number: string;
        status: 'open' | 'completed';
        reason: string;
        opened_at: string;
        finalized_at: string | null;
        original_return: { return_number: string };
        replacement_return?: { return_number: string } | null;
        opener: { name: string };
        finalizer?: { name: string } | null;
    }>;
};
type Props = {
    rental: Rental;
    permissions: {
        return: boolean;
        correctCompleted: boolean;
        reopenReturn: boolean;
    };
};
const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function RentalShow({ rental, permissions }: Props) {
    const correction = useForm<{
        component: string;
        direction: string;
        amount: number;
        reason: string;
        notes: string;
    }>({
        component: 'charge',
        direction: 'increase',
        amount: 0,
        reason: '',
        notes: '',
    });
    const operational = useForm<{
        rental_return_id: string;
        reason: string;
    }>({
        rental_return_id:
            rental.returns
                .find((item) => item.status === 'completed')
                ?.id.toString() ?? '',
        reason: '',
    });
    const submitCorrection = (event: FormEvent) => {
        event.preventDefault();
        correction.post(`/rentals/${rental.id}/financial-corrections`, {
            preserveScroll: true,
            onSuccess: () => correction.reset(),
        });
    };
    const submitOperationalCorrection = (event: FormEvent) => {
        event.preventDefault();
        operational.post(`/rentals/${rental.id}/operational-corrections`);
    };
    const refundDue = Number(rental.balance_due) < 0;

    return (
        <>
            <Head title={rental.rental_number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/rentals">
                            <ArrowLeft />
                            Daftar rental
                        </Link>
                    </Button>
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">
                                {rental.rental_number}
                            </h1>
                            <Badge>{rental.status}</Badge>
                        </div>
                        {permissions.return &&
                            [
                                'active',
                                'partial_return',
                                'correction_pending',
                            ].includes(rental.status) && (
                                <Button asChild>
                                    <Link href={`/rentals/${rental.id}/return`}>
                                        <PackageCheck />
                                        Proses pengembalian
                                    </Link>
                                </Button>
                            )}
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {rental.customer.name} · {rental.branch.name}
                    </p>
                </header>
                <section className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Jadwal</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p>
                                <b>Checkout:</b>{' '}
                                {new Date(rental.checked_out_at).toLocaleString(
                                    'id-ID',
                                )}
                            </p>
                            <p>
                                <b>Batas kembali:</b>{' '}
                                {new Date(rental.due_at).toLocaleString(
                                    'id-ID',
                                )}
                            </p>
                            <p>
                                <b>Rate:</b> {rental.rate_plan?.name ?? '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Asal transaksi</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p>
                                {rental.booking?.source === 'direct'
                                    ? 'Rental langsung'
                                    : 'Checkout booking'}
                            </p>
                            <p>{rental.booking?.booking_number}</p>
                            <p>
                                {rental.customer.customer_number} ·{' '}
                                {rental.customer.phone ?? '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Pembayaran</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="flex justify-between">
                                <span>Total</span>
                                <b>
                                    {money.format(Number(rental.total_amount))}
                                </b>
                            </p>
                            <p className="flex justify-between">
                                <span>Dibayar</span>
                                <b>
                                    {money.format(Number(rental.paid_amount))}
                                </b>
                            </p>
                            <p className="flex justify-between">
                                <span>Deposit</span>
                                <b>
                                    {money.format(
                                        Number(rental.deposit_amount),
                                    )}
                                </b>
                            </p>
                            <p className="flex justify-between border-t pt-2">
                                <span>
                                    {refundDue
                                        ? 'Kelebihan bayar / refund'
                                        : 'Sisa'}
                                </span>
                                <b>
                                    {money.format(
                                        Math.abs(Number(rental.balance_due)),
                                    )}
                                </b>
                            </p>
                        </CardContent>
                    </Card>
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle>Unit yang dibawa</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {rental.items.map((item) => (
                            <div
                                key={item.id}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex justify-between">
                                    <p className="font-medium">
                                        {item.description}
                                    </p>
                                    <b>
                                        {money.format(
                                            Number(item.total_amount),
                                        )}
                                    </b>
                                </div>
                                <div className="mt-3 grid gap-2 md:grid-cols-2">
                                    {item.assets.map((line) => (
                                        <div
                                            key={line.id}
                                            className="rounded-md bg-muted p-3 text-sm"
                                        >
                                            <p className="font-medium">
                                                {line.asset.asset_code}
                                            </p>
                                            <p className="text-muted-foreground">
                                                Kondisi{' '}
                                                {line.checkout_condition}
                                                {line.asset.serial_number
                                                    ? ` · SN ${line.asset.serial_number}`
                                                    : ''}
                                            </p>
                                            {line.notes && <p>{line.notes}</p>}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                {rental.returns.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Riwayat pengembalian</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {rental.returns.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex flex-col justify-between gap-2 rounded-lg border p-4 sm:flex-row"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {item.return_number}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.type} ·{' '}
                                            {new Date(
                                                item.returned_at,
                                            ).toLocaleString('id-ID')}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <b>
                                            {money.format(
                                                Number(
                                                    item.total_charge_amount,
                                                ),
                                            )}
                                        </b>
                                        <Badge variant="outline">
                                            {item.status}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
                {rental.operational_corrections.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Riwayat koreksi operasional</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {rental.operational_corrections.map((item) => (
                                <div
                                    key={item.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="font-medium">
                                                {item.correction_number}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {
                                                    item.original_return
                                                        .return_number
                                                }
                                                {item.replacement_return
                                                    ? ` → ${item.replacement_return.return_number}`
                                                    : ' → menunggu finalisasi'}
                                            </p>
                                        </div>
                                        <Badge>{item.status}</Badge>
                                    </div>
                                    <p className="mt-3 text-sm">
                                        {item.reason}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Dibuka oleh {item.opener.name}
                                        {item.finalizer
                                            ? ` · Difinalisasi oleh ${item.finalizer.name}`
                                            : ''}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
                {permissions.reopenReturn &&
                    ['returned', 'completed'].includes(rental.status) && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <RotateCcw className="size-5" />
                                    Buka kembali pengembalian
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Alert className="mb-5">
                                    <AlertTriangle />
                                    <AlertTitle>
                                        Koreksi operasional terkontrol
                                    </AlertTitle>
                                    <AlertDescription>
                                        Digunakan untuk memperbaiki waktu,
                                        kondisi, unit, catatan, atau inspeksi.
                                        Nominal tetap dikelola melalui koreksi
                                        keuangan.
                                    </AlertDescription>
                                </Alert>
                                <form
                                    className="grid gap-4"
                                    onSubmit={submitOperationalCorrection}
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="rental_return_id">
                                            Return yang dikoreksi
                                        </Label>
                                        <select
                                            id="rental_return_id"
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                                            value={
                                                operational.data
                                                    .rental_return_id
                                            }
                                            onChange={(event) =>
                                                operational.setData(
                                                    'rental_return_id',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            {rental.returns
                                                .filter(
                                                    (item) =>
                                                        item.status ===
                                                        'completed',
                                                )
                                                .map((item) => (
                                                    <option
                                                        key={item.id}
                                                        value={item.id}
                                                    >
                                                        {item.return_number}
                                                    </option>
                                                ))}
                                        </select>
                                        {operational.errors
                                            .rental_return_id && (
                                            <p className="text-sm text-destructive">
                                                {
                                                    operational.errors
                                                        .rental_return_id
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="operational_reason">
                                            Alasan pembukaan kembali
                                        </Label>
                                        <textarea
                                            id="operational_reason"
                                            className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                            value={operational.data.reason}
                                            onChange={(event) =>
                                                operational.setData(
                                                    'reason',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Wajib diisi minimal 10 karakter."
                                        />
                                        {operational.errors.reason && (
                                            <p className="text-sm text-destructive">
                                                {operational.errors.reason}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={
                                                operational.processing ||
                                                operational.data
                                                    .rental_return_id === '' ||
                                                operational.data.reason.trim()
                                                    .length < 10
                                            }
                                        >
                                            <RotateCcw />
                                            Buka dan lanjutkan koreksi
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}
                {rental.financial_adjustments.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Riwayat koreksi keuangan</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {rental.financial_adjustments.map((item) => (
                                <div
                                    key={item.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-medium">
                                                {item.adjustment_number}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {item.component} ·{' '}
                                                {item.direction} ·{' '}
                                                {item.creator.name}
                                            </p>
                                        </div>
                                        <b>
                                            {money.format(Number(item.amount))}
                                        </b>
                                    </div>
                                    <p className="mt-3 text-sm">
                                        {item.reason}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Saldo{' '}
                                        {money.format(
                                            Number(item.balance_before),
                                        )}
                                        {' → '}
                                        {money.format(
                                            Number(item.balance_after),
                                        )}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
                {permissions.correctCompleted &&
                    ['returned', 'completed'].includes(rental.status) && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShieldCheck className="size-5" />
                                    Koreksi transaksi selesai
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Alert className="mb-5">
                                    <AlertTitle>Koreksi terkontrol</AlertTitle>
                                    <AlertDescription>
                                        Histori pembayaran asli tidak berubah.
                                        Setiap koreksi dicatat sebagai
                                        adjustment ledger dan audit Super Admin.
                                    </AlertDescription>
                                </Alert>
                                <form
                                    className="grid gap-4 md:grid-cols-2"
                                    onSubmit={submitCorrection}
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="component">
                                            Komponen
                                        </Label>
                                        <select
                                            id="component"
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                                            value={correction.data.component}
                                            onChange={(event) =>
                                                correction.setData(
                                                    'component',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="charge">
                                                Tagihan / biaya
                                            </option>
                                            <option value="payment">
                                                Pembayaran
                                            </option>
                                            <option value="deposit">
                                                Deposit
                                            </option>
                                        </select>
                                        {correction.errors.component && (
                                            <p className="text-sm text-destructive">
                                                {correction.errors.component}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="direction">
                                            Perubahan
                                        </Label>
                                        <select
                                            id="direction"
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                                            value={correction.data.direction}
                                            onChange={(event) =>
                                                correction.setData(
                                                    'direction',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="increase">
                                                Tambah
                                            </option>
                                            <option value="decrease">
                                                Kurangi
                                            </option>
                                        </select>
                                        {correction.errors.direction && (
                                            <p className="text-sm text-destructive">
                                                {correction.errors.direction}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <Label htmlFor="amount">
                                            Nominal koreksi
                                        </Label>
                                        <RupiahInput
                                            id="amount"
                                            min={1}
                                            value={correction.data.amount}
                                            onValueChange={(value) =>
                                                correction.setData(
                                                    'amount',
                                                    value,
                                                )
                                            }
                                        />
                                        {correction.errors.amount && (
                                            <p className="text-sm text-destructive">
                                                {correction.errors.amount}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <Label htmlFor="reason">
                                            Alasan koreksi
                                        </Label>
                                        <textarea
                                            id="reason"
                                            className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            value={correction.data.reason}
                                            onChange={(event) =>
                                                correction.setData(
                                                    'reason',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Wajib diisi minimal 10 karakter."
                                        />
                                        {correction.errors.reason && (
                                            <p className="text-sm text-destructive">
                                                {correction.errors.reason}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <Label htmlFor="notes">
                                            Catatan internal (opsional)
                                        </Label>
                                        <Input
                                            id="notes"
                                            value={correction.data.notes}
                                            onChange={(event) =>
                                                correction.setData(
                                                    'notes',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="md:col-span-2">
                                        <Button
                                            type="submit"
                                            disabled={
                                                correction.processing ||
                                                correction.data.amount <= 0 ||
                                                correction.data.reason.trim()
                                                    .length < 10
                                            }
                                        >
                                            Simpan koreksi terkontrol
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}
            </div>
        </>
    );
}
