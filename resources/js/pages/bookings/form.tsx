import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, RefreshCw, Save, Trash2 } from 'lucide-react';
import { Stage4Text, stage4Translate, stage4TranslateDynamic } from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import { SearchPickerDialog } from '@/components/bookings/search-picker-dialog';
import type { BookingSearchOption } from '@/components/bookings/search-picker-dialog';
import { CashSessionSelect } from '@/components/finance/cash-session-select';
import {
    CollateralFields,
    collateralFromIdentity,
    emptyCollateral,
} from '@/components/rentals/collateral-fields';
import type {
    CollateralIdentityOption,
    CollateralInput,
} from '@/components/rentals/collateral-fields';
import type {
    CashSessionOption,
    PaymentMethodOption,
} from '@/components/finance/cash-session-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    customer360_received_confirmed: boolean;
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

const durationLabel = (plan?: BookingRatePlan, locale: "id" | "en" = "id") => {
    if (!plan) {
        return 'unit';
    }

    const labels = locale === 'en'
        ? { hour: 'hour', day: 'day', week: 'week', month: 'month' }
        : { hour: 'jam', day: 'hari', week: 'minggu', month: 'bulan' };

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
    const { locale: stage4Locale } = useAppLocale();

    const direct = mode === 'direct';
    const [identityOptions, setIdentityOptions] =
        useState<CollateralIdentityOption[]>([]);
    const [identityLookup, setIdentityLookup] = useState<
        'idle' | 'loading' | 'ready' | 'error'
    >('idle');
    const activeIdentityRequest = useRef<AbortController | null>(null);
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
        collaterals: direct ? [emptyCollateral()] : [],
        customer360_received_confirmed: false,
    });
    // GET is read-only: no physical collateral is held until direct checkout.
    // Abort and replace any older lookup if the customer or branch changes.
    const loadCustomer360 = async (customerId: number, branchId: number) => {
        activeIdentityRequest.current?.abort();
        setIdentityOptions([]);
        form.setData((current) => ({
            ...current,
            collaterals: [emptyCollateral()],
            customer360_received_confirmed: false,
        }));

        if (!direct || !customerId || !branchId) {
            setIdentityLookup('idle');

            return;
        }

        const controller = new AbortController();
        activeIdentityRequest.current = controller;
        setIdentityLookup('loading');
        const params = new URLSearchParams({
            customer_id: String(customerId),
            branch_id: String(branchId),
        });

        try {
            const response = await fetch(
                `/rentals/direct/customer-identities?${params}`,
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            );

            if (!response.ok) {
                throw new Error('Customer360 lookup failed.');
            }

            const payload = (await response.json()) as {
                data: CollateralIdentityOption[];
            };

            if (controller.signal.aborted || activeIdentityRequest.current !== controller) {
                return;
            }

            const preferred = payload.data.find(
                (identity) => identity.is_default && !identity.is_expired,
            );

            setIdentityOptions(payload.data);
            form.setData((current) => ({
                ...current,
                collaterals: preferred
                    ? [collateralFromIdentity(preferred)]
                    : [emptyCollateral()],
                customer360_received_confirmed: false,
            }));
            setIdentityLookup('ready');
        } catch {
            if (controller.signal.aborted || activeIdentityRequest.current !== controller) {
                return;
            }

            // Do not reuse stale Customer360 data after a failed request.
            // A genuinely received manual document remains allowed.
            setIdentityOptions([]);
            setIdentityLookup('error');
        }
    };

    useEffect(() => {
        const requestRef = activeIdentityRequest;

        return () => requestRef.current?.abort();
    }, []);

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

        return endsAt.toLocaleString(stage4Locale === 'en' ? 'en-GB' : 'id-ID', {
            dateStyle: 'long',
            timeStyle: 'short',
        });
    }, [form.data.duration_units, form.data.starts_at, selectedPlan, stage4Locale]);

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
            if (identityLookup === 'loading') {
                form.setError('collaterals', 'Tunggu hingga identitas Customer360 selesai dimuat.');

                return;
            }

            if (
                form.data.collaterals.some((item) => item.customer_identity_id !== null) &&
                !form.data.customer360_received_confirmed
            ) {
                form.setError(
                    'customer360_received_confirmed',
                    'Konfirmasikan dokumen fisik Customer360 sudah diterima.',
                );

                return;
            }

            if (form.data.collaterals.length === 0) {
                form.setError(
                    'collaterals',
                    'Minimal satu jaminan fisik/dokumen wajib diterima untuk Rental In Store.',
                );

                return;
            }

            form.clearErrors('collaterals');
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
                        ? stage4Translate("stage4.ui.fd25629b8a39", stage4Locale)
                        : booking
                          ? `Edit ${booking.booking_number}`
                          : stage4Translate("stage4.ui.eae4f64d5567", stage4Locale)
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
                                <ArrowLeft /><Stage4Text k="stage4.ui.c43a6e25b712" />
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-2xl font-semibold">
                            {direct
                                ? stage4Translate("stage4.ui.fd25629b8a39", stage4Locale)
                                : booking
                                  ? stage4Translate("stage4.ui.2e3b81a60136", stage4Locale)
                                  : stage4Translate("stage4.ui.eae4f64d5567", stage4Locale)}
                        </h1>
                    </div>
                    <Button
                        type="submit"
                        disabled={form.processing || (direct && identityLookup === 'loading')}
                    >
                        <Save />
                        {direct ? stage4Translate("stage4.ui.b9339faf8954", stage4Locale) : stage4Translate("stage4.ui.bdc335b94616", stage4Locale)}
                    </Button>
                </header>
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {direct
                                ? stage4Translate("stage4.ui.820f5c238143", stage4Locale)
                                : stage4Translate("stage4.ui.5bd6fca1a763", stage4Locale)}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <Field label={stage4Translate("stage4.ui.1387475bd674", stage4Locale)} error={form.errors.branch_id}>
                            <Select
                                value={String(form.data.branch_id || '')}
                                disabled={Boolean(booking)}
                                onValueChange={(value) => {
                                    form.setData('branch_id', Number(value));

                                    if (direct) {
                                        void loadCustomer360(form.data.customer_id, Number(value));
                                    }

                                    resetItemSelections();
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={stage4Translate("stage4.ui.f53404d2ddcf", stage4Locale)} />
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
                            label={stage4Translate("stage4.ui.af0ab4433946", stage4Locale)}
                            error={form.errors.customer_id}
                        >
                            <SearchPickerDialog
                                type="customer"
                                value={selectedCustomer}
                                onSelect={(option) => {
                                    if (direct && option.id !== form.data.customer_id) {
                                        void loadCustomer360(option.id, form.data.branch_id);
                                    }

                                    setSelectedCustomer(option);
                                    form.setData('customer_id', option.id);
                                }}
                            />
                        </Field>
                        <Field
                            label={stage4Translate("stage4.ui.3d00cf0d363b", stage4Locale)}
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
                                placeholder={stage4Translate("stage4.ui.092902ea5dff", stage4Locale)}
                            />
                            <p className="text-xs text-muted-foreground">
                                {selectedCustomer?.is_member
                                    ? stage4Translate("stage4.ui.575164e1b40d", stage4Locale)
                                    : stage4Translate("stage4.ui.d61a19094b66", stage4Locale)}
                            </p>
                        </Field>
                        <Field
                            label={stage4Translate("stage4.ui.31b1ce48655c", stage4Locale)}
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
                                    <SelectValue placeholder={stage4Translate("stage4.ui.cc50ae030139", stage4Locale)} />
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
                                            }, stage4Locale)}
                                            )
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label={stage4Translate("stage4.ui.fceb2d8f5369", stage4Locale)}
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
                            label={`${stage4Translate("stage4.ui.0a31c6125219", stage4Locale)} (${durationLabel(selectedPlan, stage4Locale)})`}
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
                        <Field label={stage4Translate("stage4.ui.2a6388bc0137", stage4Locale)}>
                            <Input
                                readOnly
                                value={calculatedEndsAt}
                                placeholder={stage4Translate("stage4.ui.ee29932c3e6c", stage4Locale)}
                                className="bg-muted"
                            />
                            <p className="text-xs text-muted-foreground"><Stage4Text k="stage4.ui.fa1324477477" />
                            </p>
                        </Field>
                        <Field label={stage4Translate("stage4.ui.ff648afc53ef", stage4Locale)} error={form.errors.source}>
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
                                            {stage4TranslateDynamic(item, stage4Locale)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle><Stage4Text k="stage4.ui.db7bf83172f0" /></CardTitle>
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
                            <Plus /><Stage4Text k="stage4.ui.9a69cafa8d15" />
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
                                            <SelectItem value="product"><Stage4Text k="stage4.ui.869eb84eb3dc" />
                                            </SelectItem>
                                            <SelectItem value="package"><Stage4Text k="stage4.ui.3c97ce060ce2" />
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
                                ? stage4Translate("stage4.ui.bf47c3bc9bf5", stage4Locale)
                                : stage4Translate("stage4.ui.c474ac0f2d71", stage4Locale)}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {direct && (
                            <>
                                <Field
                                    label={stage4Translate("stage4.ui.c7d851c67548", stage4Locale)}
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
                                    label={stage4Translate("stage4.ui.93621ca8fc63", stage4Locale)}
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
                                            <SelectItem value="excellent"><Stage4Text k="stage4.ui.e90dcd5d96b8" />
                                            </SelectItem>
                                            <SelectItem value="good"><Stage4Text k="stage4.ui.04f5b5ce0518" />
                                            </SelectItem>
                                            <SelectItem value="fair"><Stage4Text k="stage4.ui.e776a0660b3d" />
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>
                            </>
                        )}
                        <Field
                            label={stage4Translate("stage4.ui.53eb1a623ade", stage4Locale)}
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
                                    <SelectValue placeholder={stage4Translate("stage4.ui.22cd7e4b4a0a", stage4Locale)} />
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
                            label={stage4Translate("stage4.ui.7afede4d7a6c", stage4Locale)}
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
                            label={stage4Translate("stage4.ui.6a6462650207", stage4Locale)}
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
                            label={stage4Translate("stage4.ui.7f2cc58cb31e", stage4Locale)}
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
                                placeholder={stage4Translate("stage4.ui.942ab3e55971", stage4Locale)}
                            />
                        </Field>
                        {direct && (
                            <div className="md:col-span-2 xl:col-span-3">
                                <Label><Stage4Text k="stage4.ui.28d33595e915" /></Label>
                                <textarea
                                    className="mt-2 min-h-24 w-full rounded-md border bg-transparent p-3 text-sm"
                                    value={form.data.checkout_notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'checkout_notes',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={stage4Translate("stage4.ui.5664a2e9edee", stage4Locale)}
                                />
                            </div>
                        )}
                    </CardContent>
                </Card>
                {direct && (
                    <Card>
                        <CardHeader>
                            <CardTitle><Stage4Text k="stage4.ui.404889fd0b06" /></CardTitle>
                            <p className="text-sm text-muted-foreground"><Stage4Text k="stage4.ui.62becb5ce129" />
                            </p>
                            {form.data.customer_id > 0 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="w-fit"
                                    disabled={identityLookup === 'loading'}
                                    onClick={() => void loadCustomer360(form.data.customer_id, form.data.branch_id)}
                                >
                                    <RefreshCw /><Stage4Text k="stage4.ui.dd4425e113b9" />
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent>
                            {identityLookup === 'loading' && (
                                <p role="status" className="mb-3 text-sm text-muted-foreground"><Stage4Text k="stage4.ui.889ec8f312f4" />
                                </p>
                            )}
                            {identityLookup === 'error' && (
                                <div className="mb-3 flex flex-wrap items-center gap-2 rounded-md border border-amber-400/50 bg-amber-50 p-3 text-sm text-amber-950 dark:bg-amber-950/20 dark:text-amber-100"><Stage4Text k="stage4.ui.190425aa06f4" />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => void loadCustomer360(form.data.customer_id, form.data.branch_id)}
                                    ><Stage4Text k="stage4.ui.2b8b41244705" />
                                    </Button>
                                </div>
                            )}
                            {identityLookup === 'ready' && identityOptions.length === 0 && (
                                <p className="mb-3 text-sm text-muted-foreground"><Stage4Text k="stage4.ui.2a7195d234b9" />
                                </p>
                            )}
                            {identityLookup === 'ready' &&
                                identityOptions.length > 0 &&
                                !identityOptions.some((item) => !item.is_expired) && (
                                    <p className="mb-3 text-sm text-amber-700 dark:text-amber-300"><Stage4Text k="stage4.ui.650fac4c51cc" />
                                    </p>
                                )}
                            {identityLookup !== 'loading' && (
                                <CollateralFields
                                    value={form.data.collaterals}
                                    onChange={(collaterals) => {
                                        const before = form.data.collaterals
                                            .map((item) => item.customer_identity_id)
                                            .join(',');
                                        const after = collaterals
                                            .map((item) => item.customer_identity_id)
                                            .join(',');
                                        form.setData('collaterals', collaterals);

                                        if (before !== after) {
                                            form.setData('customer360_received_confirmed', false);
                                        }
                                    }}
                                    errors={form.errors as Record<string, string>}
                                    identityOptions={identityOptions}
                                />
                            )}
                            {form.data.collaterals.some(
                                (item) => item.customer_identity_id !== null,
                            ) && (
                                <div className="mt-4 flex items-start gap-3 rounded-md border p-3">
                                    <Checkbox
                                        id="direct-customer360-received"
                                        checked={form.data.customer360_received_confirmed}
                                        onCheckedChange={(checked) => {
                                            form.setData('customer360_received_confirmed', checked === true);
                                            form.clearErrors('customer360_received_confirmed');
                                        }}
                                    />
                                    <div className="space-y-1">
                                        <Label htmlFor="direct-customer360-received"><Stage4Text k="stage4.ui.e275e2573e3c" />
                                        </Label>
                                        <p className="text-xs text-muted-foreground"><Stage4Text k="stage4.ui.e71bf64ea2f3" />
                                        </p>
                                        {form.errors.customer360_received_confirmed && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.customer360_received_confirmed}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}
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
                        <CardTitle><Stage4Text k="stage4.ui.9f09aefd0dd4" /></CardTitle>
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
