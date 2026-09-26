import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, LogOut } from 'lucide-react';
import { Stage4Text, stage4Translate, stage4FormatDateTime } from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import { CashSessionSelect } from '@/components/finance/cash-session-select';
import {
    CollateralFields,
    collateralFromIdentity,
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

type AssetInput = {
    asset_id: number;
    condition: 'excellent' | 'good' | 'fair';
    notes: string;
};
type Props = {
    booking: Booking;
    paymentMethods: PaymentMethodOption[];
    cashSessions: CashSessionOption[];
    customerIdentities: CollateralIdentityOption[];
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
    cashSessions,
    customerIdentities,
    financialSummary,
}: Props) {
    const { locale: stage4Locale } = useAppLocale();

    const reservedAssets = booking.items.flatMap((item) =>
        (item.reservations ?? []).map((reservation) => ({
            reservation,
            item,
        })),
    );
    const defaultIdentity = customerIdentities.find(
        (identity) => identity.is_default && !identity.is_expired,
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
        cash_session_id: null as number | null,
        payment_reference: '',
        payment_notes: '',
        collaterals: defaultIdentity
            ? [collateralFromIdentity(defaultIdentity)]
            : ([] as CollateralInput[]),
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
                    form.post(`/rentals/checkout/${booking.id}`, {
                        forceFormData: true,
                    });
                }}
            >
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/bookings/${booking.id}`}>
                                <ArrowLeft /><Stage4Text k="stage4.ui.679ff8e9555a" />
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-2xl font-semibold"><Stage4Text k="stage4.ui.e52caf59c035" />
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {booking.booking_number} · {booking.customer?.name}{' '}
                            · {booking.branch?.name}
                        </p>
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        <LogOut /><Stage4Text k="stage4.ui.b659cd7bfebf" />
                    </Button>
                </header>

                <section className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle><Stage4Text k="stage4.ui.40f9759982fe" /></CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Label><Stage4Text k="stage4.ui.e46ab2d83de2" /></Label>
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
                            <p className="text-sm text-muted-foreground"><Stage4Text k="stage4.ui.e9854f380b7f" />{' '}
                                {stage4FormatDateTime(booking.ends_at, stage4Locale)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle><Stage4Text k="stage4.ui.ef458a133154" /></CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="flex justify-between">
                                <span><Stage4Text k="stage4.ui.b25928c69902" /></span>
                                <b>
                                    {money.format(Number(booking.total_amount))}
                                </b>
                            </p>
                            <p className="flex justify-between">
                                <span><Stage4Text k="stage4.ui.4d3eb469ae4a" /></span>
                                <b>
                                    {money.format(
                                        Number(booking.deposit_required),
                                    )}
                                </b>
                            </p>
                            <p className="flex justify-between text-emerald-600">
                                <span><Stage4Text k="stage4.ui.8b1cf6e31c9f" /></span>
                                <b>
                                    {money.format(financialSummary.rental_paid)}
                                </b>
                            </p>
                            <p className="flex justify-between font-semibold">
                                <span><Stage4Text k="stage4.ui.c0fd40e41b59" /></span>
                                <b>
                                    {money.format(financialSummary.balance_due)}
                                </b>
                            </p>
                            <p className="flex justify-between text-muted-foreground">
                                <span><Stage4Text k="stage4.ui.0c875d087fb6" /></span>
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
                            <CardTitle><Stage4Text k="stage4.ui.eacbf2923e7f" /></CardTitle>
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
                                onValueChange={(value) => {
                                    form.setData(
                                        'payment_method_id',
                                        Number(value),
                                    );
                                    form.setData('cash_session_id', null);
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={stage4Translate("stage4.ui.53eb1a623ade", stage4Locale)} />
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
                            <CashSessionSelect
                                paymentMethods={paymentMethods}
                                cashSessions={cashSessions}
                                branchId={booking.branch_id}
                                paymentMethodId={form.data.payment_method_id}
                                value={form.data.cash_session_id}
                                onValueChange={(value) =>
                                    form.setData('cash_session_id', value)
                                }
                                error={form.errors.cash_session_id}
                            />
                            <Input
                                placeholder={stage4Translate("stage4.ui.e657fd4a7334", stage4Locale)}
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
                                    <p className="text-sm text-amber-700"><Stage4Text k="stage4.ui.d73620845bab" />
                                    </p>
                                )}
                            {remainingBalance > 0 && (
                                <div className="flex gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                    <p><Stage4Text k="stage4.ui.c012c9af8b8f" />{' '}
                                        <b>{money.format(remainingBalance)}</b><Stage4Text k="stage4.ui.64f2789c8682" />
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage4Text k="stage4.ui.c9635da859ac" /></CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {booking.items.flatMap((item) =>
                            (item.bulk_reservations ?? []).map((reservation) => (
                                <div key={`bulk-${reservation.id}`} className="rounded-lg border p-4">
                                    <p className="font-medium">{reservation.product.name}<Stage4Text k="stage4.ui.7ed3a04cc02c" /> {reservation.quantity}<Stage4Text k="stage4.ui.0df9eea0bad5" /></p>
                                    <p className="text-sm text-muted-foreground">{item.description}<Stage4Text k="stage4.ui.86562b32c51f" /></p>
                                </div>
                            )),
                        )}
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
                                        <SelectItem value="excellent"><Stage4Text k="stage4.ui.e90dcd5d96b8" />
                                        </SelectItem>
                                        <SelectItem value="good"><Stage4Text k="stage4.ui.04f5b5ce0518" />
                                        </SelectItem>
                                        <SelectItem value="fair"><Stage4Text k="stage4.ui.e776a0660b3d" />
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    placeholder={stage4Translate("stage4.ui.79886597b7e9", stage4Locale)}
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
                        <CardTitle><Stage4Text k="stage4.ui.bc6bef81de10" /></CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CollateralFields
                            value={form.data.collaterals}
                            onChange={(collaterals) =>
                                form.setData('collaterals', collaterals)
                            }
                            errors={form.errors as Record<string, string>}
                            identityOptions={customerIdentities}
                        />
                        {form.errors.collaterals && (
                            <p className="mt-2 text-sm text-destructive">
                                {form.errors.collaterals}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage4Text k="stage4.ui.85b064124057" /></CardTitle>
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
                            placeholder={stage4Translate("stage4.ui.3932e431286b", stage4Locale)}
                        />
                    </CardContent>
                </Card>
            </form>
        </>
    );
}
