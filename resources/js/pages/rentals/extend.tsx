import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarPlus, CircleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { CashSessionSelect } from '@/components/finance/cash-session-select';
import type {
    CashSessionOption,
    PaymentMethodOption,
} from '@/components/finance/cash-session-select';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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

type Rental = {
    id: number;
    rental_number: string;
    status: string;
    due_at: string;
    balance_due: string;
    branch: { id: number; code: string; name: string };
    customer: {
        customer_number: string;
        name: string;
        phone: string | null;
        is_member?: boolean;
        member_number?: string | null;
    };
    rate_plan: {
        code: string;
        name: string;
        duration_unit: string;
        duration_value: number;
    };
};

type ExtensionItem = {
    id: number;
    description: string;
    product_name: string;
    out_quantity: number;
    current_due_at: string;
    unit_rate: number;
    assets: Array<{
        id: number;
        asset_code: string;
        serial_number: string | null;
    }>;
};

type Props = {
    rental: Rental;
    items: ExtensionItem[];
    paymentMethods: PaymentMethodOption[];
    cashSessions: CashSessionOption[];
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

export default function RentalExtensionCreate({
    rental,
    items,
    paymentMethods,
    cashSessions,
}: Props) {
    const form = useForm({
        duration_units: 1,
        promotion_code: '',
        item_ids: items.map((item) => item.id),
        payment_amount: 0,
        payment_method_id: 0,
        cash_session_id: null as number | null,
        payment_reference: '',
        payment_notes: '',
        notes: '',
    });
    const selectedMethod = paymentMethods.find(
        (method) => method.id === form.data.payment_method_id,
    );
    const selectedItems = items.filter((item) =>
        form.data.item_ids.includes(item.id),
    );
    const estimatedTotal = selectedItems.reduce(
        (sum, item) =>
            sum +
            item.unit_rate *
                Math.max(1, Number(form.data.duration_units) || 1) *
                item.out_quantity,
        0,
    );
    const estimatedBalance =
        Number(rental.balance_due) + estimatedTotal - form.data.payment_amount;

    const toggleItem = (itemId: number) => {
        form.setData(
            'item_ids',
            form.data.item_ids.includes(itemId)
                ? form.data.item_ids.filter((id) => id !== itemId)
                : [...form.data.item_ids, itemId],
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/rentals/${rental.id}/extensions`);
    };

    return (
        <>
            <Head title={`Perpanjang ${rental.rental_number}`} />
            <form
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={submit}
            >
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/rentals/${rental.id}`}>
                                <ArrowLeft />
                                Kembali ke rental
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-2xl font-semibold">
                            Perpanjangan rental
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {rental.rental_number} · {rental.customer.name} ·{' '}
                            {rental.branch.name}
                        </p>
                    </div>
                    <Button
                        type="submit"
                        disabled={
                            form.processing || form.data.item_ids.length === 0
                        }
                    >
                        <CalendarPlus />
                        Setujui perpanjangan
                    </Button>
                </header>

                <Alert>
                    <CircleAlert className="size-4" />
                    <AlertTitle>Preflight otomatis sebelum disimpan</AlertTitle>
                    <AlertDescription>
                        Sistem akan mengecek setiap unit terhadap Smart
                        Calendar. Perpanjangan dibatalkan secara atomik jika
                        periode tambahan berbenturan dengan booking,
                        maintenance, rental lain, atau transfer aset.
                    </AlertDescription>
                </Alert>

                <section className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Durasi tambahan</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="space-y-2">
                                <Label htmlFor="duration_units">
                                    Jumlah unit durasi
                                </Label>
                                <Input
                                    id="duration_units"
                                    type="number"
                                    min={1}
                                    max={365}
                                    value={form.data.duration_units}
                                    onChange={(event) =>
                                        form.setData(
                                            'duration_units',
                                            Math.max(
                                                1,
                                                Number(event.target.value) || 1,
                                            ),
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.duration_units}
                                />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Rate plan: <b>{rental.rate_plan.name}</b> · 1
                                unit = {rental.rate_plan.duration_value}{' '}
                                {rental.rate_plan.duration_unit}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Deadline rental saat ini:{' '}
                                {dateTime.format(new Date(rental.due_at))}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Estimasi biaya</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="flex justify-between">
                                <span>Perpanjangan</span>
                                <b>{money.format(estimatedTotal)}</b>
                            </p>
                            <p className="flex justify-between">
                                <span>Saldo sebelumnya</span>
                                <b>
                                    {money.format(Number(rental.balance_due))}
                                </b>
                            </p>
                            <p className="flex justify-between">
                                <span>Dibayar sekarang</span>
                                <b>{money.format(form.data.payment_amount)}</b>
                            </p>
                            <p className="flex justify-between border-t pt-2 font-semibold">
                                <span>Estimasi saldo setelah extension</span>
                                <b>{money.format(estimatedBalance)}</b>
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Nilai final dihitung ulang di server dari
                                snapshot rate rental dan jumlah unit yang masih
                                keluar.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Pembayaran opsional</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="space-y-2">
                                <Label htmlFor="promotion_code">
                                    Kode promo
                                </Label>
                                <Input
                                    id="promotion_code"
                                    value={form.data.promotion_code}
                                    onChange={(event) =>
                                        form.setData(
                                            'promotion_code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="Opsional"
                                />
                                <InputError
                                    message={form.errors.promotion_code}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {rental.customer.is_member
                                        ? 'Member aktif mendapat diskon 10% otomatis. Nilai final dihitung server.'
                                        : 'Promo divalidasi server saat perpanjangan disimpan.'}
                                </p>
                            </div>
                            <RupiahInput
                                value={form.data.payment_amount}
                                onValueChange={(value) =>
                                    form.setData('payment_amount', value)
                                }
                                placeholder="Bayar biaya perpanjangan"
                            />
                            <InputError message={form.errors.payment_amount} />
                            <Select
                                value={String(
                                    form.data.payment_method_id || '',
                                )}
                                onValueChange={(value) => {
                                    form.setData(
                                        'payment_method_id',
                                        Number(value),
                                    );
                                    form.setData('cash_session_id', null);
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih metode pembayaran" />
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
                                message={form.errors.payment_method_id}
                            />
                            <CashSessionSelect
                                paymentMethods={paymentMethods}
                                cashSessions={cashSessions}
                                branchId={rental.branch.id}
                                paymentMethodId={form.data.payment_method_id}
                                value={form.data.cash_session_id}
                                onValueChange={(value) =>
                                    form.setData('cash_session_id', value)
                                }
                                error={form.errors.cash_session_id}
                            />
                            {selectedMethod?.requires_reference && (
                                <div className="space-y-2">
                                    <Label htmlFor="payment_reference">
                                        Referensi pembayaran
                                    </Label>
                                    <Input
                                        id="payment_reference"
                                        value={form.data.payment_reference}
                                        onChange={(event) =>
                                            form.setData(
                                                'payment_reference',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.payment_reference}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Item yang diperpanjang</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {items.map((item) => {
                            const selected = form.data.item_ids.includes(
                                item.id,
                            );
                            const lineEstimate =
                                item.unit_rate *
                                Math.max(
                                    1,
                                    Number(form.data.duration_units) || 1,
                                ) *
                                item.out_quantity;

                            return (
                                <label
                                    key={item.id}
                                    className="flex cursor-pointer gap-3 rounded-lg border p-4"
                                >
                                    <input
                                        type="checkbox"
                                        className="mt-1 size-4"
                                        checked={selected}
                                        onChange={() => toggleItem(item.id)}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p className="font-medium">
                                                    {item.description}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {item.out_quantity} unit
                                                    masih keluar · deadline{' '}
                                                    {dateTime.format(
                                                        new Date(
                                                            item.current_due_at,
                                                        ),
                                                    )}
                                                </p>
                                            </div>
                                            <div className="text-right text-sm">
                                                <p className="font-semibold">
                                                    {money.format(lineEstimate)}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {money.format(
                                                        item.unit_rate,
                                                    )}{' '}
                                                    / unit durasi / aset
                                                </p>
                                            </div>
                                        </div>
                                        <div className="mt-3 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                            {item.assets.map((asset) => (
                                                <span
                                                    key={asset.id}
                                                    className="rounded-md bg-muted px-2 py-1"
                                                >
                                                    {asset.asset_code}
                                                    {asset.serial_number
                                                        ? ` · SN ${asset.serial_number}`
                                                        : ''}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                </label>
                            );
                        })}
                        <InputError message={form.errors.item_ids} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Catatan</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="notes">Catatan perpanjangan</Label>
                            <textarea
                                id="notes"
                                className="flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="payment_notes">
                                Catatan pembayaran
                            </Label>
                            <textarea
                                id="payment_notes"
                                className="flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                value={form.data.payment_notes}
                                onChange={(event) =>
                                    form.setData(
                                        'payment_notes',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.payment_notes} />
                        </div>
                    </CardContent>
                </Card>
            </form>
        </>
    );
}
