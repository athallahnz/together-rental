import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, PackageCheck } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { CashSessionSelect } from '@/components/finance/cash-session-select';
import type {
    CashSessionOption,
    PaymentMethodOption,
} from '@/components/finance/cash-session-select';
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
        id: number;
        product_id: number;
        asset_code: string;
        serial_number: string | null;
        condition: string;
    };
};
type Rental = {
    id: number;
    branch_id: number;
    rental_number: string;
    due_at: string;
    balance_due: string;
    customer: { name: string };
    branch: { name: string };
    collaterals: Array<{
        id: number;
        type: string;
        number: string;
        holder_name: string | null;
        received_at: string | null;
    }>;
    items: Array<{ id: number; description: string; assets: Unit[] }>;
};
type OperationalCorrection = {
    correction_number: string;
    reason: string;
    original_return: {
        return_number: string;
        returned_at: string;
    };
};
type ReturnLine = {
    rental_item_asset_id: number;
    replacement_asset_id: number | null;
    selected: boolean;
    condition: string;
    late_fee_amount: number;
    damage_fee_amount: number;
    cleaning_fee_amount: number;
    notes: string;
};
type ReplacementAsset = {
    id: number;
    product_id: number;
    asset_code: string;
    serial_number: string | null;
};
type FormData = {
    returned_at: string;
    notes: string;
    discount_amount: number;
    payment_amount: number;
    payment_method_id: string | null;
    cash_session_id: number | null;
    payment_reference: string;
    returned_collateral_ids: number[];
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
    cashSessions,
    operationalCorrection,
    replacementAssets,
}: {
    rental: Rental;
    paymentMethods: PaymentMethodOption[];
    cashSessions: CashSessionOption[];
    operationalCorrection: OperationalCorrection | null;
    replacementAssets: ReplacementAsset[];
}) {
    const correctionMode = operationalCorrection !== null;
    const units = rental.items.flatMap((item) =>
        item.assets.map((unit) => ({ ...unit, description: item.description })),
    );
    const form = useForm<FormData>({
        returned_at: correctionMode
            ? new Date(operationalCorrection.original_return.returned_at)
                  .toISOString()
                  .slice(0, 16)
            : localDateTime(),
        notes: '',
        discount_amount: 0,
        payment_amount: 0,
        payment_method_id: '',
        cash_session_id: null,
        payment_reference: '',
        returned_collateral_ids: [],
        items: units.map((unit) => ({
            rental_item_asset_id: unit.id,
            replacement_asset_id: unit.asset.id,
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
    const finalReturnHasHeldCollateral =
        !correctionMode &&
        isFinalReturn &&
        form.data.returned_collateral_ids.length !== rental.collaterals.length;
    const toggleCollateral = (id: number, checked: boolean) => {
        form.setData(
            'returned_collateral_ids',
            checked
                ? [...form.data.returned_collateral_ids, id]
                : form.data.returned_collateral_ids.filter(
                      (collateralId) => collateralId !== id,
                  ),
        );
    };
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

                {correctionMode && (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>
                            Koreksi operasional{' '}
                            {operationalCorrection.correction_number}
                        </AlertTitle>
                        <AlertDescription>
                            Merevisi{' '}
                            {
                                operationalCorrection.original_return
                                    .return_number
                            }
                            . Semua unit wajib difinalisasi bersama. Nominal
                            tetap Rp0 dan koreksi keuangan harus dilakukan
                            melalui adjustment ledger.
                        </AlertDescription>
                    </Alert>
                )}
                {!correctionMode && new Date(rental.due_at) < new Date() && (
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
                <InputError
                    message={
                        (form.errors as Record<string, string>)
                            .returned_collateral_ids
                    }
                />

                {!correctionMode && rental.collaterals.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Pengembalian jaminan fisik</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Centang hanya jaminan yang benar-benar
                                diserahkan kembali ke pelanggan pada proses ini.
                            </p>
                            {rental.collaterals.map((collateral) => {
                                const checked =
                                    form.data.returned_collateral_ids.includes(
                                        collateral.id,
                                    );

                                return (
                                    <label
                                        key={collateral.id}
                                        className="flex items-start gap-3 rounded-lg border p-4"
                                    >
                                        <Checkbox
                                            checked={checked}
                                            onCheckedChange={(value) =>
                                                toggleCollateral(
                                                    collateral.id,
                                                    value === true,
                                                )
                                            }
                                        />
                                        <span className="text-sm">
                                            <span className="block font-medium">
                                                {collateral.type} ·{' '}
                                                {collateral.number}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {collateral.holder_name ??
                                                    rental.customer.name}
                                            </span>
                                        </span>
                                    </label>
                                );
                            })}
                            {finalReturnHasHeldCollateral && (
                                <Alert variant="destructive">
                                    <AlertTriangle />
                                    <AlertTitle>
                                        Jaminan masih ditahan
                                    </AlertTitle>
                                    <AlertDescription>
                                        Karena ini pengembalian unit terakhir,
                                        seluruh jaminan fisik wajib dikonfirmasi
                                        sudah dikembalikan.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </CardContent>
                    </Card>
                )}

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
                                        disabled={correctionMode}
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
                                        {correctionMode && (
                                            <Field label="Unit aktual">
                                                <Select
                                                    value={String(
                                                        form.data.items[index]
                                                            .replacement_asset_id,
                                                    )}
                                                    onValueChange={(value) =>
                                                        setLine(index, {
                                                            replacement_asset_id:
                                                                Number(value),
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            value={String(
                                                                unit.asset.id,
                                                            )}
                                                        >
                                                            {
                                                                unit.asset
                                                                    .asset_code
                                                            }{' '}
                                                            (tercatat)
                                                        </SelectItem>
                                                        {replacementAssets
                                                            .filter(
                                                                (asset) =>
                                                                    asset.product_id ===
                                                                    unit.asset
                                                                        .product_id,
                                                            )
                                                            .map((asset) => (
                                                                <SelectItem
                                                                    key={
                                                                        asset.id
                                                                    }
                                                                    value={String(
                                                                        asset.id,
                                                                    )}
                                                                >
                                                                    {
                                                                        asset.asset_code
                                                                    }
                                                                    {asset.serial_number
                                                                        ? ` · SN ${asset.serial_number}`
                                                                        : ''}
                                                                </SelectItem>
                                                            ))}
                                                    </SelectContent>
                                                </Select>
                                            </Field>
                                        )}
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
                                        {!correctionMode && (
                                            <>
                                                <MoneyField
                                                    label="Denda terlambat"
                                                    value={
                                                        form.data.items[index]
                                                            .late_fee_amount
                                                    }
                                                    onChange={(
                                                        late_fee_amount,
                                                    ) =>
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
                                                    onChange={(
                                                        damage_fee_amount,
                                                    ) =>
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
                                                    onChange={(
                                                        cleaning_fee_amount,
                                                    ) =>
                                                        setLine(index, {
                                                            cleaning_fee_amount,
                                                        })
                                                    }
                                                />
                                            </>
                                        )}
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
                            {!correctionMode && (
                                <>
                                    <MoneyField
                                        label="Diskon biaya"
                                        value={form.data.discount_amount}
                                        onChange={(value) =>
                                            form.setData(
                                                'discount_amount',
                                                value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.discount_amount}
                                    />
                                    <MoneyField
                                        label="Pembayaran diterima"
                                        value={form.data.payment_amount}
                                        onChange={(value) =>
                                            form.setData(
                                                'payment_amount',
                                                value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.payment_amount}
                                    />
                                    <Field label="Metode pembayaran">
                                        <Select
                                            value={
                                                form.data.payment_method_id ??
                                                ''
                                            }
                                            onValueChange={(value) => {
                                                form.setData(
                                                    'payment_method_id',
                                                    value,
                                                );
                                                form.setData(
                                                    'cash_session_id',
                                                    null,
                                                );
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih metode" />
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
                                        <InputError
                                            message={
                                                form.errors.payment_method_id
                                            }
                                        />
                                    </Field>
                                    <CashSessionSelect
                                        paymentMethods={paymentMethods}
                                        cashSessions={cashSessions}
                                        branchId={rental.branch_id}
                                        paymentMethodId={
                                            form.data.payment_method_id
                                        }
                                        value={form.data.cash_session_id}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'cash_session_id',
                                                value,
                                            )
                                        }
                                        error={form.errors.cash_session_id}
                                    />
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
                                            message={
                                                form.errors.payment_reference
                                            }
                                        />
                                    </div>
                                </>
                            )}
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
                            {!correctionMode && (
                                <>
                                    <Summary
                                        label="Biaya pengembalian"
                                        value={finalCharge}
                                    />
                                    <Summary
                                        label="Pembayaran"
                                        value={-form.data.payment_amount}
                                    />
                                </>
                            )}
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
                            finalReturnHasBalance ||
                            finalReturnHasHeldCollateral
                        }
                    >
                        <PackageCheck />
                        {form.processing
                            ? 'Memproses...'
                            : correctionMode
                              ? 'Finalisasi koreksi operasional'
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
