import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, PackageCheck } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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

type Unit = {
    id: number;
    checkout_condition: string;
    asset: {
        asset_code: string;
        serial_number: string | null;
        condition: string;
    };
};
type Rental = {
    id: number;
    rental_number: string;
    due_at: string;
    balance_due: string;
    customer: { name: string };
    branch: { name: string };
    items: Array<{ id: number; description: string; assets: Unit[] }>;
};
type PaymentMethod = {
    id: number;
    name: string;
    requires_reference: boolean;
};
type ReturnLine = {
    rental_item_asset_id: number;
    selected: boolean;
    condition: string;
    late_fee_amount: number;
    damage_fee_amount: number;
    cleaning_fee_amount: number;
    notes: string;
};
type FormData = {
    returned_at: string;
    notes: string;
    discount_amount: number;
    payment_amount: number;
    payment_method_id: string | null;
    payment_reference: string;
    items: ReturnLine[];
};

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});
const localDateTime = () => {
    const date = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);

    return date.toISOString().slice(0, 16);
};

export default function RentalReturn({
    rental,
    paymentMethods,
}: {
    rental: Rental;
    paymentMethods: PaymentMethod[];
}) {
    const units = rental.items.flatMap((item) =>
        item.assets.map((unit) => ({ ...unit, description: item.description })),
    );
    const form = useForm<FormData>({
        returned_at: localDateTime(),
        notes: '',
        discount_amount: 0,
        payment_amount: 0,
        payment_method_id: '',
        payment_reference: '',
        items: units.map((unit) => ({
            rental_item_asset_id: unit.id,
            selected: true,
            condition: unit.asset.condition || 'good',
            late_fee_amount: 0,
            damage_fee_amount: 0,
            cleaning_fee_amount: 0,
            notes: '',
        })),
    });
    const selected = form.data.items.filter((item) => item.selected);
    const isFinalReturn = selected.length === units.length;
    const charges = selected.reduce(
        (sum, item) =>
            sum +
            Number(item.late_fee_amount) +
            Number(item.damage_fee_amount) +
            Number(item.cleaning_fee_amount),
        0,
    );
    const finalCharge = Math.max(
        0,
        charges - Number(form.data.discount_amount),
    );
    const projectedBalance =
        Number(rental.balance_due) +
        finalCharge -
        Number(form.data.payment_amount);
    const finalReturnHasBalance = isFinalReturn && projectedBalance > 0.009;
    const setLine = (index: number, patch: Partial<ReturnLine>) => {
        const items = [...form.data.items];
        items[index] = { ...items[index], ...patch };
        form.setData('items', items);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            payment_method_id: data.payment_method_id || null,
            items: data.items.filter((item) => item.selected),
        }));
        form.post(`/rentals/${rental.id}/return`);
    };

    return (
        <>
            <Head title={`Pengembalian ${rental.rental_number}`} />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <header>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={`/rentals/${rental.id}`}>
                            <ArrowLeft />
                            Detail rental
                        </Link>
                    </Button>
                    <h1 className="mt-3 text-2xl font-semibold">
                        Proses pengembalian
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {rental.rental_number} · {rental.customer.name} ·{' '}
                        {rental.branch.name}
                    </p>
                </header>

                {new Date(rental.due_at) < new Date() && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>Rental melewati batas kembali</AlertTitle>
                        <AlertDescription>
                            Masukkan denda keterlambatan pada unit terkait
                            sesuai kebijakan cabang.
                        </AlertDescription>
                    </Alert>
                )}
                <InputError message={form.errors.items} />
                <InputError
                    message={(form.errors as Record<string, string>).rental}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Unit yang diterima</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {units.map((unit, index) => (
                            <div
                                key={unit.id}
                                className="space-y-4 rounded-lg border p-4"
                            >
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        checked={
                                            form.data.items[index].selected
                                        }
                                        onCheckedChange={(value) =>
                                            setLine(index, {
                                                selected: value === true,
                                            })
                                        }
                                    />
                                    <div>
                                        <p className="font-medium">
                                            {unit.asset.asset_code}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {unit.description}
                                            {unit.asset.serial_number
                                                ? ` · SN ${unit.asset.serial_number}`
                                                : ''}
                                        </p>
                                    </div>
                                </div>
                                {form.data.items[index].selected && (
                                    <div className="grid gap-4 md:grid-cols-4">
                                        <Field label="Kondisi kembali">
                                            <Select
                                                value={
                                                    form.data.items[index]
                                                        .condition
                                                }
                                                onValueChange={(condition) =>
                                                    setLine(index, {
                                                        condition,
                                                    })
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
                                                    <SelectItem value="damaged">
                                                        Rusak
                                                    </SelectItem>
                                                    <SelectItem value="lost">
                                                        Hilang
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <MoneyField
                                            label="Denda terlambat"
                                            value={
                                                form.data.items[index]
                                                    .late_fee_amount
                                            }
                                            onChange={(late_fee_amount) =>
                                                setLine(index, {
                                                    late_fee_amount,
                                                })
                                            }
                                        />
                                        <MoneyField
                                            label="Biaya kerusakan"
                                            value={
                                                form.data.items[index]
                                                    .damage_fee_amount
                                            }
                                            onChange={(damage_fee_amount) =>
                                                setLine(index, {
                                                    damage_fee_amount,
                                                })
                                            }
                                        />
                                        <MoneyField
                                            label="Biaya cleaning"
                                            value={
                                                form.data.items[index]
                                                    .cleaning_fee_amount
                                            }
                                            onChange={(cleaning_fee_amount) =>
                                                setLine(index, {
                                                    cleaning_fee_amount,
                                                })
                                            }
                                        />
                                        <div className="md:col-span-4">
                                            <Label>Catatan pemeriksaan</Label>
                                            <Input
                                                value={
                                                    form.data.items[index].notes
                                                }
                                                onChange={(event) =>
                                                    setLine(index, {
                                                        notes: event.target
                                                            .value,
                                                    })
                                                }
                                                placeholder="Kelengkapan, kerusakan, atau catatan unit"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <section className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Penyelesaian transaksi</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label="Waktu kembali">
                                <Input
                                    type="datetime-local"
                                    value={form.data.returned_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'returned_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.returned_at} />
                            </Field>
                            <MoneyField
                                label="Diskon biaya"
                                value={form.data.discount_amount}
                                onChange={(value) =>
                                    form.setData('discount_amount', value)
                                }
                            />
                            <InputError message={form.errors.discount_amount} />
                            <MoneyField
                                label="Pembayaran diterima"
                                value={form.data.payment_amount}
                                onChange={(value) =>
                                    form.setData('payment_amount', value)
                                }
                            />
                            <InputError message={form.errors.payment_amount} />
                            <Field label="Metode pembayaran">
                                <Select
                                    value={form.data.payment_method_id ?? ''}
                                    onValueChange={(value) =>
                                        form.setData('payment_method_id', value)
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
                                    message={form.errors.payment_method_id}
                                />
                            </Field>
                            <div className="sm:col-span-2">
                                <Label>Referensi pembayaran</Label>
                                <Input
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
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Ringkasan akhir</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <Summary
                                label="Saldo sebelumnya"
                                value={rental.balance_due}
                            />
                            <Summary
                                label="Biaya pengembalian"
                                value={finalCharge}
                            />
                            <Summary
                                label="Pembayaran"
                                value={-form.data.payment_amount}
                            />
                            <Summary
                                label="Sisa setelah proses"
                                value={projectedBalance}
                            />
                            {finalReturnHasBalance && (
                                <Alert variant="destructive">
                                    <AlertTriangle />
                                    <AlertTitle>Pelunasan wajib</AlertTitle>
                                    <AlertDescription>
                                        Ini adalah pengembalian unit terakhir.
                                        Sisa tagihan{' '}
                                        {money.format(projectedBalance)} wajib
                                        dilunasi sebelum pengembalian dapat
                                        diproses.
                                    </AlertDescription>
                                </Alert>
                            )}
                            {!isFinalReturn && projectedBalance > 0 && (
                                <Alert>
                                    <AlertTriangle />
                                    <AlertTitle>
                                        Pengembalian parsial
                                    </AlertTitle>
                                    <AlertDescription>
                                        Pengembalian parsial tetap dapat
                                        diproses. Saldo{' '}
                                        {money.format(projectedBalance)} wajib
                                        dilunasi ketika unit terakhir
                                        dikembalikan.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </CardContent>
                    </Card>
                </section>
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            selected.length === 0 ||
                            finalReturnHasBalance
                        }
                    >
                        <PackageCheck />
                        {form.processing
                            ? 'Memproses...'
                            : 'Simpan pengembalian'}
                    </Button>
                </div>
            </form>
        </>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function MoneyField({
    label,
    value,
    onChange,
}: {
    label: string;
    value: number;
    onChange: (value: number) => void;
}) {
    return (
        <Field label={label}>
            <RupiahInput min={0} value={value} onValueChange={onChange} />
        </Field>
    );
}

function Summary({ label, value }: { label: string; value: number | string }) {
    return (
        <p className="flex justify-between border-b pb-2">
            <span>{label}</span>
            <b>{money.format(Number(value))}</b>
        </p>
    );
}
