import { useMemo, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-react';
import { SearchPickerDialog } from '@/components/bookings/search-picker-dialog';
import type { BookingSearchOption } from '@/components/bookings/search-picker-dialog';
import { CashSessionSelect } from '@/components/finance/cash-session-select';
import { CollateralFields } from '@/components/rentals/collateral-fields';
import type { CollateralInput } from '@/components/rentals/collateral-fields';
import type {
    CashSessionOption,
    PaymentMethodOption,
} from '@/components/finance/cash-session-select';
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
import type { AccessBranch, Booking, BookingRatePlan } from '@/types';

type Option = BookingSearchOption;
type Line = { type: 'product' | 'package'; id: number; quantity: number };
type Props = {
    booking: Booking | null;
    mode?: 'booking' | 'direct';
    branches: AccessBranch[];
    customers: Option[];
    ratePlans: BookingRatePlan[];
    products: Option[];
    packages: Option[];
    paymentMethods?: PaymentMethodOption[];
    cashSessions?: CashSessionOption[];
};
type FormData = {
    branch_id: number;
    customer_id: number;
    rate_plan_id: number;
    source: string;
    starts_at: string;
    duration_units: number;
    promotion_code: string;
    notes: string;
    items: Line[];
    checked_out_at: string;
    checkout_condition: 'excellent' | 'good' | 'fair';
    checkout_notes: string;
    payment_amount: number;
    deposit_paid: number;
    payment_method_id: number;
    cash_session_id: number | null;
    payment_reference: string;
    collaterals: CollateralInput[];
};

const localDate = (value?: string) =>
    value ? new Date(value).toISOString().slice(0, 16) : '';

const planMinutes = (plan?: BookingRatePlan) => {
    if (!plan) {
        return 0;
    }

    const multipliers = {
        hour: 60,
        day: 1_440,
        week: 10_080,
        month: 43_200,
    };

    return plan.duration_value * multipliers[plan.duration_unit];
};

const initialDurationUnits = (
    booking: Booking | null,
    ratePlans: BookingRatePlan[],
) => {
    if (!booking) {
        return 1;
    }

    const plan = ratePlans.find((item) => item.id === booking.rate_plan_id);
    const unitMinutes = planMinutes(plan);

    if (unitMinutes === 0) {
        return 1;
    }

    const bookingMinutes =
        (new Date(booking.ends_at).getTime() -
            new Date(booking.starts_at).getTime()) /
        60_000;

    return Math.max(1, Math.round(bookingMinutes / unitMinutes));
};

const durationLabel = (plan?: BookingRatePlan) => {
    if (!plan) {
        return 'unit';
    }

    const labels = {
        hour: 'jam',
        day: 'hari',
        week: 'minggu',
        month: 'bulan',
    };

    return plan.duration_value === 1
        ? labels[plan.duration_unit]
        : `x ${plan.duration_value} ${labels[plan.duration_unit]}`;
};

export default function BookingForm({
    booking,
    mode = 'booking',
    branches,
    customers,
    ratePlans,
    products,
    packages,
    paymentMethods = [],
    cashSessions = [],
}: Props) {
    const direct = mode === 'direct';
    const [selectedCustomer, setSelectedCustomer] = useState<Option | null>(
        booking
            ? (customers.find((option) => option.id === booking.customer_id) ??
                  null)
            : null,
    );
    const [selectedItems, setSelectedItems] = useState<Record<string, Option>>(
        () => ({
            ...Object.fromEntries(
                products.map((option) => [`product:${option.id}`, option]),
            ),
            ...Object.fromEntries(
                packages.map((option) => [`package:${option.id}`, option]),
            ),
        }),
    );
    const form = useForm<FormData>({
        branch_id: booking?.branch_id ?? branches[0]?.id ?? 0,
        customer_id: booking?.customer_id ?? 0,
        rate_plan_id: booking?.rate_plan_id ?? 0,
        source: booking?.source ?? 'counter',
        starts_at: localDate(booking?.starts_at),
        duration_units: initialDurationUnits(booking, ratePlans),
        promotion_code: booking?.promotion?.code ?? '',
        notes: booking?.notes ?? '',
        items: booking?.items?.map((item) => ({
            type: item.product_id ? 'product' : 'package',
            id: item.product_id ?? item.package_id ?? 0,
            quantity: item.quantity,
        })) ?? [{ type: 'product', id: 0, quantity: 1 }],
        checked_out_at: localDate(
            booking?.starts_at ?? new Date().toISOString(),
        ),
        checkout_condition: 'good',
        checkout_notes: '',
        payment_amount: 0,
        deposit_paid: 0,
        payment_method_id: 0,
        cash_session_id: null,
        payment_reference: '',
        collaterals: [],
    });
    const updateLine = (index: number, patch: Partial<Line>) =>
        form.setData(
            'items',
            form.data.items.map((line, position) =>
                position === index ? { ...line, ...patch } : line,
            ),
        );
    const selectedByLine = useMemo(
        () =>
            form.data.items.map(
                (line) => selectedItems[`${line.type}:${line.id}`] ?? null,
            ),
        [form.data.items, selectedItems],
    );
    const selectedPlan = useMemo(
        () => ratePlans.find((item) => item.id === form.data.rate_plan_id),
        [form.data.rate_plan_id, ratePlans],
    );
    const calculatedEndsAt = useMemo(() => {
        const minutes = planMinutes(selectedPlan) * form.data.duration_units;

        if (!form.data.starts_at || minutes <= 0) {
            return '';
        }

        const endsAt = new Date(form.data.starts_at);

        endsAt.setMinutes(endsAt.getMinutes() + minutes);

        return endsAt.toLocaleString('id-ID', {
            dateStyle: 'long',
            timeStyle: 'short',
        });
    }, [form.data.duration_units, form.data.starts_at, selectedPlan]);

    const selectItem = (index: number, option: Option) => {
        const line = form.data.items[index];

        setSelectedItems((current) => ({
            ...current,
            [`${line.type}:${option.id}`]: option,
        }));
        updateLine(index, { id: option.id });
    };

    const resetItemSelections = () => {
        setSelectedItems({});
        form.setData(
            'items',
            form.data.items.map((line) => ({ ...line, id: 0 })),
        );
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (direct) {
            form.post('/rentals/direct', { forceFormData: true });
        } else if (booking) {
            form.put(`/bookings/${booking.id}`);
        } else {
            form.post('/bookings');
        }
    };

    return (
        <>
            <Head
                title={
                    direct
                        ? 'Rental In Store'
                        : booking
                          ? `Edit ${booking.booking_number}`
                          : 'Booking baru'
                }
            />
            <form
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={submit}
            >
                <header className="flex items-center justify-between">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={
                                    direct
                                        ? '/rentals'
                                        : booking
                                          ? `/bookings/${booking.id}`
                                          : '/bookings'
                                }
                            >
                                <ArrowLeft />
                                Kembali
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-2xl font-semibold">
                            {direct
                                ? 'Rental In Store'
                                : booking
                                  ? 'Edit booking draft'
                                  : 'Booking baru'}
                        </h1>
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        <Save />
                        {direct ? 'Checkout sekarang' : 'Simpan booking'}
                    </Button>
                </header>
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {direct
                                ? 'Informasi rental walk-in'
                                : 'Informasi booking'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <Field label="Cabang" error={form.errors.branch_id}>
                            <Select
                                value={String(form.data.branch_id || '')}
                                disabled={Boolean(booking)}
                                onValueChange={(value) => {
                                    form.setData('branch_id', Number(value));
                                    resetItemSelections();
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih cabang" />
                                </SelectTrigger>
                                <SelectContent>
                                    {branches.map((item) => (
                                        <SelectItem
                                            key={item.id}
                                            value={String(item.id)}
                                        >
                                            {item.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label="Pelanggan"
                            error={form.errors.customer_id}
                        >
                            <SearchPickerDialog
                                type="customer"
                                value={selectedCustomer}
                                onSelect={(option) => {
                                    setSelectedCustomer(option);
                                    form.setData('customer_id', option.id);
                                }}
                            />
                        </Field>
                        <Field
                            label="Kode promo"
                            error={form.errors.promotion_code}
                        >
                            <Input
                                value={form.data.promotion_code}
                                onChange={(event) =>
                                    form.setData(
                                        'promotion_code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="Opsional, contoh: MEMBERDAY"
                            />
                            <p className="text-xs text-muted-foreground">
                                {selectedCustomer?.is_member
                                    ? 'Member aktif: diskon 10% otomatis. Promo diskon memakai nilai terbaik kecuali promo mengizinkan stacking.'
                                    : 'Promo divalidasi server berdasarkan cabang, periode, minimum transaksi, dan kuota.'}
                            </p>
                        </Field>
                        <Field
                            label="Rate plan"
                            error={form.errors.rate_plan_id}
                        >
                            <Select
                                value={String(form.data.rate_plan_id || '')}
                                onValueChange={(value) => {
                                    form.setData('rate_plan_id', Number(value));
                                    form.setData('duration_units', 1);
                                    resetItemSelections();
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih rate plan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ratePlans.map((item) => (
                                        <SelectItem
                                            key={item.id}
                                            value={String(item.id)}
                                        >
                                            {item.name} ({item.duration_value}{' '}
                                            {durationLabel({
                                                ...item,
                                                duration_value: 1,
                                            })}
                                            )
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label="Waktu pengambilan"
                            error={form.errors.starts_at}
                        >
                            <Input
                                type="datetime-local"
                                value={form.data.starts_at}
                                onChange={(event) =>
                                    form.setData(
                                        'starts_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label={`Jumlah durasi (${durationLabel(selectedPlan)})`}
                            error={form.errors.duration_units}
                        >
                            <Input
                                type="number"
                                min={1}
                                max={365}
                                value={form.data.duration_units}
                                onChange={(event) =>
                                    form.setData(
                                        'duration_units',
                                        Math.max(1, Number(event.target.value)),
                                    )
                                }
                            />
                        </Field>
                        <Field label="Batas pengembalian otomatis">
                            <Input
                                readOnly
                                value={calculatedEndsAt}
                                placeholder="Pilih waktu dan rate plan"
                                className="bg-muted"
                            />
                            <p className="text-xs text-muted-foreground">
                                Dihitung otomatis dari rate plan dan jumlah
                                durasi. Promo bonus durasi akan ditambahkan
                                server setelah kode promo tervalidasi.
                            </p>
                        </Field>
                        <Field label="Sumber" error={form.errors.source}>
                            <Select
                                value={form.data.source}
                                onValueChange={(value) =>
                                    form.setData('source', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {[
                                        'counter',
                                        'phone',
                                        'whatsapp',
                                        'website',
                                        'other',
                                    ].map((item) => (
                                        <SelectItem key={item} value={item}>
                                            {item}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Item booking</CardTitle>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                form.setData('items', [
                                    ...form.data.items,
                                    { type: 'product', id: 0, quantity: 1 },
                                ])
                            }
                        >
                            <Plus />
                            Tambah item
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {form.data.items.map((line, index) => {
                            return (
                                <div
                                    key={index}
                                    className="grid gap-3 rounded-lg border p-3 md:grid-cols-[160px_1fr_120px_auto]"
                                >
                                    <Select
                                        value={line.type}
                                        onValueChange={(
                                            value: 'product' | 'package',
                                        ) =>
                                            updateLine(index, {
                                                type: value,
                                                id: 0,
                                            })
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="product">
                                                Produk
                                            </SelectItem>
                                            <SelectItem value="package">
                                                Paket
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <SearchPickerDialog
                                        type={line.type}
                                        value={selectedByLine[index]}
                                        branchId={form.data.branch_id}
                                        ratePlanId={form.data.rate_plan_id}
                                        disabled={
                                            !form.data.branch_id ||
                                            !form.data.rate_plan_id
                                        }
                                        onSelect={(option) =>
                                            selectItem(index, option)
                                        }
                                    />
                                    <Input
                                        type="number"
                                        min={1}
                                        value={line.quantity}
                                        onChange={(event) =>
                                            updateLine(index, {
                                                quantity: Number(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                    />
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        disabled={form.data.items.length === 1}
                                        onClick={() =>
                                            form.setData(
                                                'items',
                                                form.data.items.filter(
                                                    (_, position) =>
                                                        position !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                    {form.errors[
                                        `items.${index}.quantity` as keyof typeof form.errors
                                    ] && (
                                        <p className="text-sm text-destructive md:col-span-4">
                                            {
                                                form.errors[
                                                    `items.${index}.quantity` as keyof typeof form.errors
                                                ]
                                            }
                                        </p>
                                    )}
                                    {form.errors[
                                        `items.${index}.id` as keyof typeof form.errors
                                    ] && (
                                        <p className="text-sm text-destructive md:col-span-4">
                                            {
                                                form.errors[
                                                    `items.${index}.id` as keyof typeof form.errors
                                                ]
                                            }
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                        {typeof form.errors.items === 'string' && (
                            <p className="text-sm text-destructive">
                                {form.errors.items}
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {direct
                                ? 'Checkout dan pembayaran awal'
                                : 'Pembayaran awal booking'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {direct && (
                            <>
                                <Field
                                    label="Waktu checkout aktual"
                                    error={form.errors.checked_out_at}
                                >
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
                                </Field>
                                <Field
                                    label="Kondisi awal seluruh unit"
                                    error={form.errors.checkout_condition}
                                >
                                    <Select
                                        value={form.data.checkout_condition}
                                        onValueChange={(
                                            value:
                                                'excellent' | 'good' | 'fair',
                                        ) =>
                                            form.setData(
                                                'checkout_condition',
                                                value,
                                            )
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
                                </Field>
                            </>
                        )}
                        <Field
                            label="Metode pembayaran"
                            error={form.errors.payment_method_id}
                        >
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
                                    <SelectValue placeholder="Pilih bila ada pembayaran" />
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
                        </Field>
                        <CashSessionSelect
                            paymentMethods={paymentMethods}
                            cashSessions={cashSessions}
                            branchId={form.data.branch_id}
                            paymentMethodId={form.data.payment_method_id}
                            value={form.data.cash_session_id}
                            onValueChange={(value) =>
                                form.setData('cash_session_id', value)
                            }
                            error={form.errors.cash_session_id}
                        />
                        <Field
                            label="Pembayaran rental"
                            error={form.errors.payment_amount}
                        >
                            <RupiahInput
                                value={form.data.payment_amount}
                                onValueChange={(value) =>
                                    form.setData('payment_amount', value)
                                }
                            />
                        </Field>
                        <Field
                            label="Deposit diterima"
                            error={form.errors.deposit_paid}
                        >
                            <RupiahInput
                                value={form.data.deposit_paid}
                                onValueChange={(value) =>
                                    form.setData('deposit_paid', value)
                                }
                            />
                        </Field>
                        <Field
                            label="Referensi pembayaran"
                            error={form.errors.payment_reference}
                        >
                            <Input
                                value={form.data.payment_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'payment_reference',
                                        event.target.value,
                                    )
                                }
                                placeholder="Nomor transfer/QRIS"
                            />
                        </Field>
                        {direct && (
                            <div className="md:col-span-2 xl:col-span-3">
                                <Label>Catatan checkout dan kelengkapan</Label>
                                <textarea
                                    className="mt-2 min-h-24 w-full rounded-md border bg-transparent p-3 text-sm"
                                    value={form.data.checkout_notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'checkout_notes',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Tas, baterai, charger, memory card, dan catatan kondisi."
                                />
                            </div>
                        )}
                    </CardContent>
                </Card>
                {direct && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Jaminan fisik / dokumen</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CollateralFields
                                value={form.data.collaterals}
                                onChange={(collaterals) =>
                                    form.setData('collaterals', collaterals)
                                }
                                errors={form.errors as Record<string, string>}
                            />
                            {form.errors.collaterals && (
                                <p className="mt-2 text-sm text-destructive">
                                    {form.errors.collaterals}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Catatan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <textarea
                            className="min-h-28 w-full rounded-md border bg-transparent p-3 text-sm"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
