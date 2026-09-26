import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    AlertTriangle,
    CalendarPlus,
    FileText,
    PackageCheck,
    RotateCcw,
    ShieldCheck,
} from 'lucide-react';
import type { FormEvent } from 'react';
import {
    Stage4Text,
    stage4Translate,
    stage4TranslateDynamic,
    stage4FormatDateTime,
} from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import { TransactionDocumentActions } from '@/components/documents/transaction-document-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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

type Rental = {
    id: number;
    rental_number: string;
    status: string;
    checked_out_at: string;
    due_at: string;
    is_overdue: boolean;
    subtotal: string;
    discount_amount: string;
    total_amount: string;
    promotion?: { code: string; name: string; type: string } | null;
    paid_amount: string;
    deposit_amount: string;
    balance_due: string;
    notes: string | null;
    extensions: Array<{
        id: number;
        extension_number: string;
        previous_due_at: string;
        extended_due_at: string;
        status: string;
        discount_amount: string;
        total_amount: string;
        paid_amount: string;
        promotion?: { code: string; name: string; type: string } | null;
        notes: string | null;
        approved_at: string;
        creator?: { name: string } | null;
        approver?: { name: string } | null;
        items: Array<{
            id: number;
            quantity: number;
            previous_due_at: string;
            extended_due_at: string;
            total_amount: string;
            rental_item: {
                description: string;
                product?: { name: string } | null;
            };
        }>;
    }>;
    collaterals: Array<{
        id: number;
        customer_identity_id: number | null;
        source_type: 'manual' | 'customer_identity' | string;
        identity_snapshot: {
            verified_at?: string | null;
            expires_at?: string | null;
        } | null;
        type: string;
        number: string;
        holder_name: string | null;
        status: 'held' | 'returned' | string;
        received_at: string | null;
        returned_at: string | null;
        document_path: string | null;
        notes: string | null;
        receiver?: { name: string } | null;
        returner?: { name: string } | null;
    }>;
    returns: Array<{
        id: number;
        return_number: string;
        type: string;
        status: string;
        returned_at: string;
        total_charge_amount: string;
    }>;
    branch: { name: string };
    customer: {
        name: string;
        customer_number: string;
        phone: string | null;
        is_member?: boolean;
        member_number?: string | null;
    };
    booking?: { booking_number: string; source: string } | null;
    rate_plan?: { name: string } | null;
    items: Array<{
        id: number;
        description: string;
        quantity: number;
        returned_quantity: number;
        is_bulk: boolean;
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
type CustomerIdentityOption = {
    id: number;
    type: string;
    collateral_type: string;
    number: string;
    name_on_identity: string | null;
    expires_at: string | null;
    is_primary: boolean;
    verified_at: string | null;
    document_present: boolean;
    is_expired: boolean;
    is_default: boolean;
};

type Props = {
    rental: Rental;
    customerIdentities: CustomerIdentityOption[];
    permissions: {
        update: boolean;
        extend: boolean;
        return: boolean;
        correctCompleted: boolean;
        reopenReturn: boolean;
        updateCustomer: boolean;
    };
};
const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function RentalShow({
    rental,
    customerIdentities,
    permissions,
}: Props) {
    const { locale: stage4Locale } = useAppLocale();

    const eligibleIdentities = customerIdentities.filter(
        (identity) => !identity.is_expired,
    );
    const defaultIdentity =
        eligibleIdentities.find((identity) => identity.is_default) ??
        eligibleIdentities[0] ??
        null;
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
    const collateral = useForm<{
        source_mode: 'existing' | 'new' | 'manual';
        customer_identity_id: number | null;
        identity_type: string;
        identity_number: string;
        identity_name_on_identity: string;
        identity_expires_at: string;
        identity_is_primary: boolean;
        save_to_customer360: boolean;
        type: string;
        number: string;
        holder_name: string;
        notes: string;
        physical_received: boolean;
        document: File | null;
    }>({
        source_mode: defaultIdentity ? 'existing' : 'manual',
        customer_identity_id: defaultIdentity?.id ?? null,
        identity_type: 'ktp',
        identity_number: '',
        identity_name_on_identity: rental.customer.name,
        identity_expires_at: '',
        identity_is_primary: customerIdentities.length === 0,
        save_to_customer360: permissions.updateCustomer,
        type: 'KTP',
        number: '',
        holder_name: rental.customer.name,
        notes: '',
        physical_received: false,
        document: null,
    });
    const collateralReturn = useForm<{ returned_at: string }>({
        returned_at: '',
    });
    const submitCollateral = (event: FormEvent) => {
        event.preventDefault();
        collateral.post(`/rentals/${rental.id}/collaterals`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                collateral.reset(
                    'identity_number',
                    'identity_expires_at',
                    'number',
                    'notes',
                    'physical_received',
                    'document',
                );
            },
        });
    };
    const returnCollateral = (collateralId: number) => {
        collateralReturn.post(
            `/rentals/${rental.id}/collaterals/${collateralId}/return`,
            { preserveScroll: true },
        );
    };
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
    const overdue = rental.is_overdue;

    return (
        <>
            <Head title={rental.rental_number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/rentals">
                            <ArrowLeft />
                            <Stage4Text k="stage4.ui.e40979f5591c" />
                        </Link>
                    </Button>
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">
                                {rental.rental_number}
                            </h1>
                            <Badge>
                                {stage4TranslateDynamic(
                                    rental.status,
                                    stage4Locale,
                                )}
                            </Badge>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {permissions.extend &&
                                ['active', 'partial_return'].includes(
                                    rental.status,
                                ) && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={`/rentals/${rental.id}/extend`}
                                        >
                                            <CalendarPlus />
                                            <Stage4Text k="stage4.ui.cd1752d1c785" />
                                        </Link>
                                    </Button>
                                )}
                            {permissions.return &&
                                [
                                    'active',
                                    'partial_return',
                                    'correction_pending',
                                ].includes(rental.status) && (
                                    <Button asChild>
                                        <Link
                                            href={`/rentals/${rental.id}/return`}
                                        >
                                            <PackageCheck />
                                            <Stage4Text k="stage4.ui.f59b32920284" />
                                        </Link>
                                    </Button>
                                )}
                        </div>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {rental.customer.name}
                        <Stage4Text k="stage4.ui.e21a079b3a50" />{' '}
                        {rental.branch.name}
                    </p>
                </header>

                <TransactionDocumentActions
                    sourceType="rental"
                    sourceReference={rental.rental_number}
                />
                {overdue && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>
                            <Stage4Text k="stage4.ui.2bef74ec5e5c" />
                        </AlertTitle>
                        <AlertDescription>
                            <Stage4Text k="stage4.ui.ab3a886750dd" />
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
                                    <Stage4Text k="stage4.ui.1d7cb6dac73c" />
                                </b>{' '}
                                {stage4FormatDateTime(
                                    rental.checked_out_at,
                                    stage4Locale,
                                )}
                            </p>
                            <p>
                                <b>
                                    <Stage4Text k="stage4.ui.e9854f380b7f" />
                                </b>{' '}
                                {stage4FormatDateTime(
                                    rental.due_at,
                                    stage4Locale,
                                )}
                            </p>
                            <p>
                                <b>
                                    <Stage4Text k="stage4.ui.cf6b5fa84600" />
                                </b>{' '}
                                {rental.rate_plan?.name ?? '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage4Text k="stage4.ui.dccc0aa1d2b6" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p>
                                {rental.booking?.source === 'direct'
                                    ? stage4Translate(
                                          'stage4.ui.fd25629b8a39',
                                          stage4Locale,
                                      )
                                    : stage4Translate(
                                          'stage4.ui.e52caf59c035',
                                          stage4Locale,
                                      )}
                            </p>
                            <p>{rental.booking?.booking_number}</p>
                            <p>
                                {rental.customer.customer_number}
                                <Stage4Text k="stage4.ui.e21a079b3a50" />{' '}
                                {rental.customer.phone ?? '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage4Text k="stage4.ui.f0874594eb78" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="flex justify-between">
                                <span>
                                    <Stage4Text k="stage4.ui.b25928c69902" />
                                </span>
                                <b>
                                    {money.format(Number(rental.total_amount))}
                                </b>
                            </p>
                            {Number(rental.discount_amount) > 0 && (
                                <p className="flex justify-between text-emerald-600">
                                    <span>
                                        <Stage4Text k="stage4.ui.cc2aac6758fd" />
                                    </span>
                                    <b>
                                        -
                                        {money.format(
                                            Number(rental.discount_amount),
                                        )}
                                    </b>
                                </p>
                            )}
                            {rental.promotion && (
                                <p className="flex justify-between text-muted-foreground">
                                    <span>
                                        <Stage4Text k="stage4.ui.618ff494a852" />
                                    </span>
                                    <b>{rental.promotion.code}</b>
                                </p>
                            )}
                            <p className="flex justify-between">
                                <span>
                                    <Stage4Text k="stage4.ui.42b86f7c1b0f" />
                                </span>
                                <b>
                                    {money.format(Number(rental.paid_amount))}
                                </b>
                            </p>
                            <p className="flex justify-between">
                                <span>
                                    <Stage4Text k="stage4.ui.e7b0b317a6e8" />
                                </span>
                                <b>
                                    {money.format(
                                        Number(rental.deposit_amount),
                                    )}
                                </b>
                            </p>
                            <p className="flex justify-between border-t pt-2">
                                <span>
                                    {refundDue
                                        ? stage4Translate(
                                              'stage4.ui.d9e768aee9fe',
                                              stage4Locale,
                                          )
                                        : stage4Translate(
                                              'stage4.ui.861b9e39506d',
                                              stage4Locale,
                                          )}
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
                        <CardTitle>
                            <Stage4Text k="stage4.ui.7550abf6fc2d" />
                        </CardTitle>
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
                                    {item.is_bulk && (
                                        <p>
                                            <Stage4Text k="stage4.ui.9a9df57c80e0" />{' '}
                                            {item.quantity -
                                                item.returned_quantity}
                                            <Stage4Text k="stage4.ui.40fb092e21fb" />{' '}
                                            {item.returned_quantity}
                                            <Stage4Text k="stage4.ui.0df9eea0bad5" />
                                        </p>
                                    )}
                                    {item.assets.map((line) => (
                                        <div
                                            key={line.id}
                                            className="rounded-md bg-muted p-3 text-sm"
                                        >
                                            <p className="font-medium">
                                                {line.asset.asset_code}
                                            </p>
                                            <p className="text-muted-foreground">
                                                <Stage4Text k="stage4.ui.b723bb628009" />{' '}
                                                {stage4TranslateDynamic(
                                                    line.checkout_condition,
                                                    stage4Locale,
                                                )}
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
                <Card>
                    <CardHeader>
                        <CardTitle>
                            <Stage4Text k="stage4.ui.bc6bef81de10" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {rental.collaterals.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                <Stage4Text k="stage4.ui.7c12a821f792" />
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {rental.collaterals.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex flex-col justify-between gap-3 rounded-lg border p-4 md:flex-row"
                                    >
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium">
                                                    {item.type}
                                                    <Stage4Text k="stage4.ui.e21a079b3a50" />{' '}
                                                    {item.number}
                                                </p>
                                                <Badge
                                                    variant={
                                                        item.status === 'held'
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {item.status === 'held'
                                                        ? stage4Translate(
                                                              'stage4.ui.6178dcdddcd7',
                                                              stage4Locale,
                                                          )
                                                        : stage4Translate(
                                                              'stage4.ui.573315eb2d15',
                                                              stage4Locale,
                                                          )}
                                                </Badge>
                                                {item.source_type ===
                                                    'customer_identity' && (
                                                    <Badge variant="outline">
                                                        <Stage4Text k="stage4.ui.ef653211a5b4" />
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                <Stage4Text k="stage4.ui.b9f45b6b55d5" />{' '}
                                                {item.holder_name ??
                                                    rental.customer.name}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                <Stage4Text k="stage4.ui.64643bb547ae" />{' '}
                                                {item.received_at
                                                    ? new Date(
                                                          item.received_at,
                                                      ).toLocaleString(
                                                          stage4Locale === 'en'
                                                              ? 'en-GB'
                                                              : 'id-ID',
                                                      )
                                                    : '-'}
                                                {item.receiver?.name
                                                    ? ` · ${item.receiver.name}`
                                                    : ''}
                                                {item.returned_at
                                                    ? ` · Dikembalikan ${new Date(
                                                          item.returned_at,
                                                      ).toLocaleString(
                                                          'id-ID',
                                                      )}`
                                                    : ''}
                                                {item.returner?.name
                                                    ? ` oleh ${item.returner.name}`
                                                    : ''}
                                            </p>
                                            {item.notes && (
                                                <p className="mt-2 text-sm">
                                                    {item.notes}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            {item.document_path && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={`/rentals/${rental.id}/collaterals/${item.id}/document`}
                                                    >
                                                        <FileText />
                                                        <Stage4Text k="stage4.ui.a809e9504f2d" />
                                                    </a>
                                                </Button>
                                            )}
                                            {permissions.return &&
                                                item.status === 'held' && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() =>
                                                            returnCollateral(
                                                                item.id,
                                                            )
                                                        }
                                                        disabled={
                                                            collateralReturn.processing
                                                        }
                                                    >
                                                        <ShieldCheck />
                                                        <Stage4Text k="stage4.ui.0b189c8327e9" />
                                                    </Button>
                                                )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {permissions.update &&
                            ['active', 'partial_return'].includes(
                                rental.status,
                            ) && (
                                <form
                                    className="grid gap-4 rounded-lg border border-dashed p-4 md:grid-cols-2 xl:grid-cols-4"
                                    onSubmit={submitCollateral}
                                >
                                    <div className="md:col-span-2 xl:col-span-4">
                                        <Label>
                                            <Stage4Text k="stage4.ui.ac060d458d63" />
                                        </Label>
                                        <Select
                                            value={collateral.data.source_mode}
                                            onValueChange={(value) => {
                                                const mode = value as
                                                    | 'existing'
                                                    | 'new'
                                                    | 'manual';

                                                collateral.setData(
                                                    'source_mode',
                                                    mode,
                                                );

                                                if (
                                                    mode === 'existing' &&
                                                    collateral.data
                                                        .customer_identity_id ===
                                                        null
                                                ) {
                                                    collateral.setData(
                                                        'customer_identity_id',
                                                        defaultIdentity?.id ??
                                                            eligibleIdentities[0]
                                                                ?.id ??
                                                            null,
                                                    );
                                                }
                                            }}
                                        >
                                            <SelectTrigger className="mt-2">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {eligibleIdentities.length >
                                                    0 && (
                                                    <SelectItem value="existing">
                                                        <Stage4Text k="stage4.ui.a63a2b27dffa" />
                                                    </SelectItem>
                                                )}
                                                <SelectItem value="new">
                                                    <Stage4Text k="stage4.ui.8575a7da4327" />
                                                </SelectItem>
                                                <SelectItem value="manual">
                                                    <Stage4Text k="stage4.ui.1d561d36b3f6" />
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            <Stage4Text k="stage4.ui.ee4fb51bc3a6" />
                                        </p>
                                    </div>

                                    {collateral.data.source_mode ===
                                        'existing' && (
                                        <>
                                            <div className="md:col-span-2 xl:col-span-4">
                                                <Label>
                                                    <Stage4Text k="stage4.ui.b5a3c6e91461" />
                                                </Label>
                                                <Select
                                                    value={
                                                        collateral.data
                                                            .customer_identity_id
                                                            ? String(
                                                                  collateral
                                                                      .data
                                                                      .customer_identity_id,
                                                              )
                                                            : ''
                                                    }
                                                    onValueChange={(value) =>
                                                        collateral.setData(
                                                            'customer_identity_id',
                                                            Number(value),
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="mt-2">
                                                        <SelectValue
                                                            placeholder={stage4Translate(
                                                                'stage4.ui.5a32acf1b0ad',
                                                                stage4Locale,
                                                            )}
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {customerIdentities.map(
                                                            (identity) => (
                                                                <SelectItem
                                                                    key={
                                                                        identity.id
                                                                    }
                                                                    value={String(
                                                                        identity.id,
                                                                    )}
                                                                    disabled={
                                                                        identity.is_expired
                                                                    }
                                                                >
                                                                    {
                                                                        identity.collateral_type
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {
                                                                        identity.number
                                                                    }
                                                                    {identity.is_primary
                                                                        ? stage4Translate(
                                                                              'stage4.ui.c73f51f254b4',
                                                                              stage4Locale,
                                                                          )
                                                                        : ''}
                                                                    {identity.verified_at
                                                                        ? stage4Translate(
                                                                              'stage4.ui.973da3a900f7',
                                                                              stage4Locale,
                                                                          )
                                                                        : stage4Translate(
                                                                              'stage4.ui.d948d2d59c8e',
                                                                              stage4Locale,
                                                                          )}
                                                                    {identity.is_expired
                                                                        ? stage4Translate(
                                                                              'stage4.ui.2bb8fb66b3ac',
                                                                              stage4Locale,
                                                                          )
                                                                        : ''}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                {collateral.errors
                                                    .customer_identity_id && (
                                                    <p className="mt-1 text-sm text-destructive">
                                                        {
                                                            collateral.errors
                                                                .customer_identity_id
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                            {customerIdentities.length ===
                                                0 && (
                                                <p className="text-sm text-muted-foreground md:col-span-2 xl:col-span-4">
                                                    <Stage4Text k="stage4.ui.81de49daef26" />
                                                </p>
                                            )}
                                        </>
                                    )}

                                    {collateral.data.source_mode === 'new' && (
                                        <>
                                            <div>
                                                <Label>
                                                    <Stage4Text k="stage4.ui.f891f72ff159" />
                                                </Label>
                                                <Select
                                                    value={
                                                        collateral.data
                                                            .identity_type
                                                    }
                                                    onValueChange={(value) =>
                                                        collateral.setData(
                                                            'identity_type',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="mt-2">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="ktp">
                                                            <Stage4Text k="stage4.ui.101c22b89e15" />
                                                        </SelectItem>
                                                        <SelectItem value="sim">
                                                            <Stage4Text k="stage4.ui.9563e7496df3" />
                                                        </SelectItem>
                                                        <SelectItem value="passport">
                                                            <Stage4Text k="stage4.ui.319536cdb788" />
                                                        </SelectItem>
                                                        <SelectItem value="student_card">
                                                            <Stage4Text k="stage4.ui.16d3bb60398b" />
                                                        </SelectItem>
                                                        <SelectItem value="employee_card">
                                                            <Stage4Text k="stage4.ui.1ef7d61f48d1" />
                                                        </SelectItem>
                                                        <SelectItem value="other">
                                                            <Stage4Text k="stage4.ui.844f8a723473" />
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                {collateral.errors
                                                    .identity_type && (
                                                    <p className="mt-1 text-sm text-destructive">
                                                        {
                                                            collateral.errors
                                                                .identity_type
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                            <div>
                                                <Label>
                                                    <Stage4Text k="stage4.ui.54fdf9054ea7" />
                                                </Label>
                                                <Input
                                                    value={
                                                        collateral.data
                                                            .identity_number
                                                    }
                                                    onChange={(event) =>
                                                        collateral.setData(
                                                            'identity_number',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder={stage4Translate(
                                                        'stage4.ui.54fdf9054ea7',
                                                        stage4Locale,
                                                    )}
                                                />
                                                {collateral.errors
                                                    .identity_number && (
                                                    <p className="mt-1 text-sm text-destructive">
                                                        {
                                                            collateral.errors
                                                                .identity_number
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                            <div>
                                                <Label>
                                                    <Stage4Text k="stage4.ui.a8a50b3df19b" />
                                                </Label>
                                                <Input
                                                    value={
                                                        collateral.data
                                                            .identity_name_on_identity
                                                    }
                                                    onChange={(event) =>
                                                        collateral.setData(
                                                            'identity_name_on_identity',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label>
                                                    <Stage4Text k="stage4.ui.8b9f470f992b" />
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        <Stage4Text k="stage4.ui.95099252d186" />
                                                    </span>
                                                </Label>
                                                <Input
                                                    type="date"
                                                    value={
                                                        collateral.data
                                                            .identity_expires_at
                                                    }
                                                    onChange={(event) =>
                                                        collateral.setData(
                                                            'identity_expires_at',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                {collateral.errors
                                                    .identity_expires_at && (
                                                    <p className="mt-1 text-sm text-destructive">
                                                        {
                                                            collateral.errors
                                                                .identity_expires_at
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex items-start gap-3 md:col-span-2 xl:col-span-4">
                                                <Checkbox
                                                    id="save_to_customer360"
                                                    checked={
                                                        collateral.data
                                                            .save_to_customer360
                                                    }
                                                    disabled={
                                                        !permissions.updateCustomer
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        collateral.setData(
                                                            'save_to_customer360',
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                <div>
                                                    <Label htmlFor="save_to_customer360">
                                                        <Stage4Text k="stage4.ui.190f73e6be77" />
                                                    </Label>
                                                    <p className="text-xs text-muted-foreground">
                                                        <Stage4Text k="stage4.ui.c0988ac9eb98" />
                                                    </p>
                                                    {!permissions.updateCustomer && (
                                                        <p className="mt-1 text-xs text-amber-600">
                                                            <Stage4Text k="stage4.ui.10fff5ae16a7" />
                                                        </p>
                                                    )}
                                                    {collateral.errors
                                                        .save_to_customer360 && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                collateral
                                                                    .errors
                                                                    .save_to_customer360
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            {collateral.data
                                                .save_to_customer360 &&
                                                permissions.updateCustomer && (
                                                    <div className="flex items-center gap-3 md:col-span-2 xl:col-span-4">
                                                        <Checkbox
                                                            id="identity_is_primary"
                                                            checked={
                                                                collateral.data
                                                                    .identity_is_primary
                                                            }
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                collateral.setData(
                                                                    'identity_is_primary',
                                                                    checked ===
                                                                        true,
                                                                )
                                                            }
                                                        />
                                                        <Label htmlFor="identity_is_primary">
                                                            <Stage4Text k="stage4.ui.10074363ced4" />
                                                        </Label>
                                                    </div>
                                                )}
                                        </>
                                    )}

                                    {collateral.data.source_mode ===
                                        'manual' && (
                                        <>
                                            <div>
                                                <Label>
                                                    <Stage4Text k="stage4.ui.fabb2b5c779a" />
                                                </Label>
                                                <Input
                                                    value={collateral.data.type}
                                                    onChange={(event) =>
                                                        collateral.setData(
                                                            'type',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder={stage4Translate(
                                                        'stage4.ui.5ddd4a1ac042',
                                                        stage4Locale,
                                                    )}
                                                />
                                                {collateral.errors.type && (
                                                    <p className="mt-1 text-sm text-destructive">
                                                        {collateral.errors.type}
                                                    </p>
                                                )}
                                            </div>
                                            <div>
                                                <Label>
                                                    <Stage4Text k="stage4.ui.eddbb21dd281" />
                                                </Label>
                                                <Input
                                                    value={
                                                        collateral.data.number
                                                    }
                                                    onChange={(event) =>
                                                        collateral.setData(
                                                            'number',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder={stage4Translate(
                                                        'stage4.ui.cefdf68cccee',
                                                        stage4Locale,
                                                    )}
                                                />
                                                {collateral.errors.number && (
                                                    <p className="mt-1 text-sm text-destructive">
                                                        {
                                                            collateral.errors
                                                                .number
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label>
                                                    <Stage4Text k="stage4.ui.b9f45b6b55d5" />
                                                </Label>
                                                <Input
                                                    value={
                                                        collateral.data
                                                            .holder_name
                                                    }
                                                    onChange={(event) =>
                                                        collateral.setData(
                                                            'holder_name',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </>
                                    )}

                                    <div className="md:col-span-2">
                                        <Label>
                                            <Stage4Text k="stage4.ui.5b17c14f39b6" />
                                        </Label>
                                        <Input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp,application/pdf"
                                            onChange={(event) =>
                                                collateral.setData(
                                                    'document',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="md:col-span-2">
                                        <Label>
                                            <Stage4Text k="stage4.ui.9f09aefd0dd4" />
                                        </Label>
                                        <Input
                                            value={collateral.data.notes}
                                            onChange={(event) =>
                                                collateral.setData(
                                                    'notes',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder={stage4Translate(
                                                'stage4.ui.33401e0a5903',
                                                stage4Locale,
                                            )}
                                        />
                                    </div>

                                    <div className="flex items-start gap-3 md:col-span-2 xl:col-span-4">
                                        <Checkbox
                                            id="physical_received"
                                            checked={
                                                collateral.data
                                                    .physical_received
                                            }
                                            onCheckedChange={(checked) =>
                                                collateral.setData(
                                                    'physical_received',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <div>
                                            <Label htmlFor="physical_received">
                                                <Stage4Text k="stage4.ui.611656185a41" />
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                <Stage4Text k="stage4.ui.51a1a65ee7cb" />
                                            </p>
                                            {collateral.errors
                                                .physical_received && (
                                                <p className="mt-1 text-sm text-destructive">
                                                    {
                                                        collateral.errors
                                                            .physical_received
                                                    }
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {(
                                        collateral.errors as Record<
                                            string,
                                            string
                                        >
                                    ).collaterals && (
                                        <p className="text-sm text-destructive md:col-span-2 xl:col-span-4">
                                            {
                                                (
                                                    collateral.errors as Record<
                                                        string,
                                                        string
                                                    >
                                                ).collaterals
                                            }
                                        </p>
                                    )}

                                    <div className="flex items-end md:col-span-2 xl:col-span-4">
                                        <Button
                                            type="submit"
                                            disabled={collateral.processing}
                                        >
                                            <ShieldCheck />
                                            <Stage4Text k="stage4.ui.00aeed7b2308" />
                                        </Button>
                                    </div>
                                </form>
                            )}
                    </CardContent>
                </Card>
                {rental.extensions.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage4Text k="stage4.ui.995f0683f4d8" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {rental.extensions.map((extension) => (
                                <div
                                    key={extension.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-medium">
                                                {extension.extension_number}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {new Date(
                                                    extension.previous_due_at,
                                                ).toLocaleString(
                                                    stage4Locale === 'en'
                                                        ? 'en-GB'
                                                        : 'id-ID',
                                                )}
                                                {stage4Translate(
                                                    'stage4.ui.84fb07a3c1c3',
                                                    stage4Locale,
                                                )}
                                                {new Date(
                                                    extension.extended_due_at,
                                                ).toLocaleString(
                                                    stage4Locale === 'en'
                                                        ? 'en-GB'
                                                        : 'id-ID',
                                                )}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <b>
                                                {money.format(
                                                    Number(
                                                        extension.total_amount,
                                                    ),
                                                )}
                                            </b>
                                            {Number(extension.discount_amount) >
                                                0 && (
                                                <p className="text-xs text-emerald-600">
                                                    <Stage4Text k="stage4.ui.6cc10ed56760" />{' '}
                                                    {money.format(
                                                        Number(
                                                            extension.discount_amount,
                                                        ),
                                                    )}
                                                    {extension.promotion
                                                        ? ` · ${extension.promotion.code}`
                                                        : ''}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                <Stage4Text k="stage4.ui.42b86f7c1b0f" />{' '}
                                                {money.format(
                                                    Number(
                                                        extension.paid_amount,
                                                    ),
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-3 space-y-2">
                                        {extension.items.map((item) => (
                                            <div
                                                key={item.id}
                                                className="flex flex-wrap justify-between gap-2 rounded-md bg-muted p-3 text-sm"
                                            >
                                                <span>
                                                    {
                                                        item.rental_item
                                                            .description
                                                    }{' '}
                                                    <Stage4Text k="stage4.ui.e21a079b3a50" />{' '}
                                                    {item.quantity}
                                                    <Stage4Text k="stage4.ui.0df9eea0bad5" />
                                                </span>
                                                <span>
                                                    {new Date(
                                                        item.extended_due_at,
                                                    ).toLocaleString(
                                                        stage4Locale === 'en'
                                                            ? 'en-GB'
                                                            : 'id-ID',
                                                    )}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        <Stage4Text k="stage4.ui.59d6b5d8e131" />{' '}
                                        {extension.approver?.name ?? '-'}
                                        {extension.notes
                                            ? ` · ${extension.notes}`
                                            : ''}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
                {rental.returns.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage4Text k="stage4.ui.86fb4e3d48c3" />
                            </CardTitle>
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
                                            {stage4TranslateDynamic(
                                                item.type === 'partial'
                                                    ? 'partial_return'
                                                    : item.type === 'full'
                                                      ? 'full_return'
                                                      : item.type,
                                                stage4Locale,
                                            )}{' '}
                                            {' · '}{' '}
                                            {stage4FormatDateTime(
                                                item.returned_at,
                                                stage4Locale,
                                            )}
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
                                            {stage4TranslateDynamic(
                                                item.status,
                                                stage4Locale,
                                            )}
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
                            <CardTitle>
                                <Stage4Text k="stage4.ui.c1ddf40384e3" />
                            </CardTitle>
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
                                                    : stage4Translate(
                                                          'stage4.ui.c5979fb31b35',
                                                          stage4Locale,
                                                      )}
                                            </p>
                                        </div>
                                        <Badge>
                                            {stage4TranslateDynamic(
                                                item.status,
                                                stage4Locale,
                                            )}
                                        </Badge>
                                    </div>
                                    <p className="mt-3 text-sm">
                                        {item.reason}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        <Stage4Text k="stage4.ui.31e0a5724f12" />{' '}
                                        {item.opener.name}
                                        {item.finalizer
                                            ? ` · ${stage4Translate('stage4.ui.5b4f6e96b640', stage4Locale)} ${item.finalizer.name}`
                                            : ''}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
                {permissions.reopenReturn &&
                    !rental.items.some((item) => item.is_bulk) &&
                    ['returned', 'completed'].includes(rental.status) && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <RotateCcw className="size-5" />
                                    <Stage4Text k="stage4.ui.e426bee464e1" />
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Alert className="mb-5">
                                    <AlertTriangle />
                                    <AlertTitle>
                                        <Stage4Text k="stage4.ui.24f2edd041bb" />
                                    </AlertTitle>
                                    <AlertDescription>
                                        <Stage4Text k="stage4.ui.47a91fa95bf8" />
                                    </AlertDescription>
                                </Alert>
                                <form
                                    className="grid gap-4"
                                    onSubmit={submitOperationalCorrection}
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="rental_return_id">
                                            <Stage4Text k="stage4.ui.2486202a6a02" />
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
                                            <Stage4Text k="stage4.ui.520de3bd0bd8" />
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
                                            placeholder={stage4Translate(
                                                'stage4.ui.84d5bd4e5ee6',
                                                stage4Locale,
                                            )}
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
                                            <Stage4Text k="stage4.ui.b93aba98f10e" />
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}
                {rental.financial_adjustments.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage4Text k="stage4.ui.f02ebef19432" />
                            </CardTitle>
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
                                                {item.component}
                                                <Stage4Text k="stage4.ui.e21a079b3a50" />{' '}
                                                {item.direction}
                                                <Stage4Text k="stage4.ui.e21a079b3a50" />{' '}
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
                                        <Stage4Text k="stage4.ui.8b0fcd0c1f89" />{' '}
                                        {money.format(
                                            Number(item.balance_before),
                                        )}
                                        {stage4Translate(
                                            'stage4.ui.84fb07a3c1c3',
                                            stage4Locale,
                                        )}
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
                                    <Stage4Text k="stage4.ui.b88f17cd7aea" />
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Alert className="mb-5">
                                    <AlertTitle>
                                        <Stage4Text k="stage4.ui.b23942884e46" />
                                    </AlertTitle>
                                    <AlertDescription>
                                        <Stage4Text k="stage4.ui.0516d0a6c25f" />
                                    </AlertDescription>
                                </Alert>
                                <form
                                    className="grid gap-4 md:grid-cols-2"
                                    onSubmit={submitCorrection}
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="component">
                                            <Stage4Text k="stage4.ui.0750e291bd60" />
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
                                                <Stage4Text k="stage4.ui.0129b86a0496" />
                                            </option>
                                            <option value="payment">
                                                <Stage4Text k="stage4.ui.f0874594eb78" />
                                            </option>
                                            <option value="deposit">
                                                <Stage4Text k="stage4.ui.e7b0b317a6e8" />
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
                                            <Stage4Text k="stage4.ui.9083748eb607" />
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
                                                <Stage4Text k="stage4.ui.a44eb3d1808f" />
                                            </option>
                                            <option value="decrease">
                                                <Stage4Text k="stage4.ui.5589ac4ca19e" />
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
                                            <Stage4Text k="stage4.ui.013340da8633" />
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
                                            <Stage4Text k="stage4.ui.0fd21bfb8d44" />
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
                                            placeholder={stage4Translate(
                                                'stage4.ui.84d5bd4e5ee6',
                                                stage4Locale,
                                            )}
                                        />
                                        {correction.errors.reason && (
                                            <p className="text-sm text-destructive">
                                                {correction.errors.reason}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <Label htmlFor="notes">
                                            <Stage4Text k="stage4.ui.6ea7f8153fe1" />
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
                                            <Stage4Text k="stage4.ui.d4ead4b78da1" />
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
