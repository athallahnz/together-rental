import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Plus, Search, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Stage4Text, stage4Translate, stage4TranslateDynamic } from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    Transfer,
    TransferAsset,
    TransferBranch,
    TransferProduct,
} from '@/types';

type ProductOption = TransferProduct & {
    assets: TransferAsset[];
    branch_inventories: Array<{
        quantity_on_hand: number;
        quantity_reserved: number;
        quantity_rented: number;
        quantity_maintenance: number;
        quantity_in_transfer: number;
    }>;
};

type FormItem = {
    product_id: number;
    asset_id: number | null;
    quantity: number;
    condition_before: string;
    notes: string;
    product: TransferProduct;
    asset: TransferAsset | null;
};

type FormData = {
    from_branch_id: number;
    to_branch_id: number;
    reason: string;
    planned_dispatch_at: string;
    expected_arrival_at: string;
    shipping_method: string;
    courier_name: string;
    courier_phone: string;
    vehicle_number: string;
    tracking_number: string;
    waybill_number: string;
    seal_number: string;
    shipping_notes: string;
    items: FormItem[];
    submit: boolean;
    approval_notes: string;
    lock_version: number;
};

type Blocker = {
    code: string;
    message: string;
    source_number?: string;
};

type Props = {
    transfer: Transfer | null;
    branches: TransferBranch[];
    currentBranchId: number;
};

function localDateTime(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

export default function TransferForm({
    transfer,
    branches,
    currentBranchId,
}: Props) {
    const { locale: stage4Locale } = useAppLocale();

    const editing = transfer !== null;
    const [optionSearch, setOptionSearch] = useState('');
    const [loadingOptions, setLoadingOptions] = useState(false);
    const [options, setOptions] = useState<ProductOption[]>([]);
    const [blockers, setBlockers] = useState<Blocker[]>([]);
    const [preflightPassed, setPreflightPassed] = useState<boolean | null>(
        null,
    );
    const form = useForm<FormData>({
        from_branch_id: transfer?.from_branch_id ?? currentBranchId,
        to_branch_id:
            transfer?.to_branch_id ??
            branches.find((branch) => branch.id !== currentBranchId)?.id ??
            currentBranchId,
        reason: transfer?.reason ?? '',
        planned_dispatch_at: localDateTime(transfer?.planned_dispatch_at),
        expected_arrival_at: localDateTime(transfer?.expected_arrival_at),
        shipping_method: transfer?.shipping_method ?? 'internal',
        courier_name: transfer?.courier_name ?? '',
        courier_phone: transfer?.courier_phone ?? '',
        vehicle_number: transfer?.vehicle_number ?? '',
        tracking_number: transfer?.tracking_number ?? '',
        waybill_number: transfer?.waybill_number ?? '',
        seal_number: transfer?.seal_number ?? '',
        shipping_notes: transfer?.shipping_notes ?? '',
        items:
            transfer?.items.map((item) => ({
                product_id: item.product_id,
                asset_id: item.asset_id,
                quantity: item.quantity,
                condition_before: item.condition_before ?? 'good',
                notes: item.notes ?? '',
                product: item.product,
                asset: item.asset,
            })) ?? [],
        submit: false,
        approval_notes: '',
        lock_version: transfer?.lock_version ?? 0,
    });

    const selectedAssetIds = useMemo(
        () =>
            new Set(
                form.data.items.map((item) => item.asset_id).filter(Boolean),
            ),
        [form.data.items],
    );

    const searchOptions = async () => {
        if (!form.data.from_branch_id) {
            return;
        }

        setLoadingOptions(true);

        try {
            const params = new URLSearchParams({
                from_branch_id: String(form.data.from_branch_id),
                search: optionSearch,
            });

            const response = await fetch(`/transfers/options?${params}`);
            const payload = (await response.json()) as {
                products: ProductOption[];
            };
            setOptions(payload.products);
        } finally {
            setLoadingOptions(false);
        }
    };

    const addSerialized = (product: ProductOption, asset: TransferAsset) => {
        if (selectedAssetIds.has(asset.id)) {
            return;
        }

        form.setData('items', [
            ...form.data.items,
            {
                product_id: product.id,
                asset_id: asset.id,
                quantity: 1,
                condition_before: asset.condition,
                notes: '',
                product,
                asset,
            },
        ]);
        setPreflightPassed(null);
    };

    const addQuantity = (product: ProductOption) => {
        const existing = form.data.items.findIndex(
            (item) => item.product_id === product.id && item.asset_id === null,
        );

        if (existing >= 0) {
            const next = [...form.data.items];
            next[existing] = {
                ...next[existing],
                quantity: next[existing].quantity + 1,
            };

            form.setData('items', next);
        } else {
            form.setData('items', [
                ...form.data.items,
                {
                    product_id: product.id,
                    asset_id: null,
                    quantity: 1,
                    condition_before: 'good',
                    notes: '',
                    product,
                    asset: null,
                },
            ]);
        }

        setPreflightPassed(null);
    };

    const removeItem = (index: number) => {
        form.setData(
            'items',
            form.data.items.filter((_, itemIndex) => itemIndex !== index),
        );
        setPreflightPassed(null);
    };

    const updateItem = <K extends keyof FormItem>(
        index: number,
        key: K,
        value: FormItem[K],
    ) => {
        const next = [...form.data.items];
        next[index] = { ...next[index], [key]: value };
        form.setData('items', next);
        setPreflightPassed(null);
    };

    const preflight = async () => {
        const token = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');
        const response = await fetch('/transfers/preflight', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify({
                ...form.data,
                submit: false,
                context_transfer_id: transfer?.id ?? null,
            }),
        });
        const payload = (await response.json()) as {
            eligible?: boolean;
            blockers?: Blocker[];
            errors?: Record<string, string[]>;
        };
        const nextBlockers =
            payload.blockers ??
            Object.entries(payload.errors ?? {}).flatMap(([code, messages]) =>
                messages.map((message) => ({ code, message })),
            );
        setBlockers(nextBlockers);
        setPreflightPassed(
            payload.eligible === true && nextBlockers.length === 0,
        );
    };

    const submit = (event: FormEvent, submitNow: boolean) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, submit: submitNow }));
        const options = {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        };

        if (editing) {
            form.put(`/transfers/${transfer.id}`, options);
        } else {
            form.post('/transfers', options);
        }
    };

    return (
        <>
            <Head
                title={editing ? stage4Translate("stage4.ui.269ad002de85", stage4Locale) : stage4Translate("stage4.ui.ed6335dd7674", stage4Locale)}
            />
            <form className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {editing
                                ? stage4Translate("stage4.ui.269ad002de85", stage4Locale)
                                : stage4Translate("stage4.ui.ed6335dd7674", stage4Locale)}
                        </h1>
                        <p className="text-sm text-muted-foreground"><Stage4Text k="stage4.ui.4015fd0eb2cc" />
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link
                            href={
                                editing
                                    ? `/transfers/${transfer.id}`
                                    : '/transfers'
                            }
                        ><Stage4Text k="stage4.ui.c43a6e25b712" />
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage4Text k="stage4.ui.fdc6f773ee92" /></CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <Field
                            label={stage4Translate("stage4.ui.e964366f3e5e", stage4Locale)}
                            error={form.errors.from_branch_id}
                        >
                            <Select
                                value={String(form.data.from_branch_id)}
                                onValueChange={(value) => {
                                    form.setData(
                                        'from_branch_id',
                                        Number(value),
                                    );
                                    form.setData('items', []);
                                    setOptions([]);
                                    setPreflightPassed(null);
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {branches.map((branch) => (
                                        <SelectItem
                                            key={branch.id}
                                            value={String(branch.id)}
                                        >
                                            {branch.code} — {branch.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label={stage4Translate("stage4.ui.33344a5c5d9e", stage4Locale)}
                            error={form.errors.to_branch_id}
                        >
                            <Select
                                value={String(form.data.to_branch_id)}
                                onValueChange={(value) => {
                                    form.setData('to_branch_id', Number(value));
                                    setPreflightPassed(null);
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {branches
                                        .filter(
                                            (branch) =>
                                                branch.id !==
                                                form.data.from_branch_id,
                                        )
                                        .map((branch) => (
                                            <SelectItem
                                                key={branch.id}
                                                value={String(branch.id)}
                                            >
                                                {branch.code} — {branch.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label={stage4Translate("stage4.ui.d8780dff23b7", stage4Locale)}
                            error={form.errors.planned_dispatch_at}
                        >
                            <Input
                                type="datetime-local"
                                value={form.data.planned_dispatch_at}
                                onChange={(event) => {
                                    form.setData(
                                        'planned_dispatch_at',
                                        event.target.value,
                                    );
                                    setPreflightPassed(null);
                                }}
                            />
                        </Field>
                        <Field
                            label={stage4Translate("stage4.ui.60f3f737fe53", stage4Locale)}
                            error={form.errors.expected_arrival_at}
                        >
                            <Input
                                type="datetime-local"
                                value={form.data.expected_arrival_at}
                                onChange={(event) =>
                                    form.setData(
                                        'expected_arrival_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <div className="md:col-span-2">
                            <Field
                                label={stage4Translate("stage4.ui.9b4ff1a5d22a", stage4Locale)}
                                error={form.errors.reason}
                            >
                                <textarea
                                    className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage4Text k="stage4.ui.700069a38682" /></CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex gap-2">
                            <Input
                                value={optionSearch}
                                onChange={(event) =>
                                    setOptionSearch(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        void searchOptions();
                                    }
                                }}
                                placeholder={stage4Translate("stage4.ui.5dd5ca53bb71", stage4Locale)}
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => void searchOptions()}
                                disabled={loadingOptions}
                            >
                                <Search className="size-4" /><Stage4Text k="stage4.ui.3f2275d79afb" />
                            </Button>
                        </div>
                        <div className="grid gap-3 lg:grid-cols-2">
                            {options.map((product) => (
                                <div
                                    key={product.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-medium">
                                                {product.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {product.sku} •{' '}
                                                {product.tracking_type}
                                            </p>
                                        </div>
                                        {product.tracking_type !==
                                            'serialized' && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() =>
                                                    addQuantity(product)
                                                }
                                            >
                                                <Plus className="size-4" /><Stage4Text k="stage4.ui.a44eb3d1808f" />
                                            </Button>
                                        )}
                                    </div>
                                    {product.tracking_type === 'serialized' && (
                                        <div className="mt-3 space-y-2">
                                            {product.assets.map((asset) => (
                                                <div
                                                    key={asset.id}
                                                    className="flex items-center justify-between gap-2 rounded-md bg-muted/40 p-2"
                                                >
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {asset.asset_code}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {asset.serial_number ??
                                                                stage4Translate("stage4.ui.6a596a22a716", stage4Locale)}{' '}
                                                            • {stage4TranslateDynamic(asset.status, stage4Locale)} •{' '}
                                                            {stage4TranslateDynamic(asset.condition, stage4Locale)}
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={selectedAssetIds.has(
                                                            asset.id,
                                                        )}
                                                        onClick={() =>
                                                            addSerialized(
                                                                product,
                                                                asset,
                                                            )
                                                        }
                                                    ><Stage4Text k="stage4.ui.a44eb3d1808f" />
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage4Text k="stage4.ui.2004f0edd7fc" /></CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {form.data.items.map((item, index) => (
                            <div
                                key={`${item.product_id}-${item.asset_id ?? 'qty'}`}
                                className="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_130px_170px_auto]"
                            >
                                <div>
                                    <p className="font-medium">
                                        {item.product.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {item.asset
                                            ? `${item.asset.asset_code} • ${item.asset.serial_number ?? 'Tanpa serial'}`
                                            : stage4Translate("stage4.ui.2047ad7f2175", stage4Locale)}
                                    </p>
                                </div>
                                <div>
                                    <Label><Stage4Text k="stage4.ui.755c97873b6a" /></Label>
                                    <Input
                                        className="mt-1"
                                        type="number"
                                        min={1}
                                        disabled={item.asset !== null}
                                        value={item.quantity}
                                        onChange={(event) =>
                                            updateItem(
                                                index,
                                                'quantity',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div>
                                    <Label><Stage4Text k="stage4.ui.2045e4eb3d41" /></Label>
                                    <Select
                                        value={item.condition_before}
                                        onValueChange={(value) =>
                                            updateItem(
                                                index,
                                                'condition_before',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger className="mt-1">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="good"><Stage4Text k="stage4.ui.04f5b5ce0518" />
                                            </SelectItem>
                                            <SelectItem value="fair"><Stage4Text k="stage4.ui.e776a0660b3d" />
                                            </SelectItem>
                                            <SelectItem value="damaged"><Stage4Text k="stage4.ui.f1238819f6ca" />
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() => removeItem(index)}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))}
                        {form.data.items.length === 0 && (
                            <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"><Stage4Text k="stage4.ui.830960e318c5" />
                            </p>
                        )}
                        <InputError message={form.errors.items} />
                    </CardContent>
                </Card>

                {preflightPassed !== null && (
                    <Alert
                        variant={preflightPassed ? 'default' : 'destructive'}
                    >
                        <AlertTriangle className="size-4" />
                        <AlertTitle>
                            {preflightPassed
                                ? stage4Translate("stage4.ui.1e76b646dcfd", stage4Locale)
                                : stage4Translate("stage4.ui.c7566b3c19fd", stage4Locale)}
                        </AlertTitle>
                        <AlertDescription>
                            {preflightPassed ? (
                                stage4Translate("stage4.ui.679c68f17230", stage4Locale)
                            ) : (
                                <ul className="list-disc pl-5">
                                    {blockers.map((blocker, index) => (
                                        <li key={`${blocker.code}-${index}`}>
                                            {blocker.message}
                                            {blocker.source_number
                                                ? ` (${blocker.source_number})`
                                                : ''}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardContent className="flex flex-wrap items-end justify-between gap-4 pt-6">
                        <div className="min-w-64 flex-1">
                            <Label><Stage4Text k="stage4.ui.9005fb18d95a" /></Label>
                            <Input
                                className="mt-1"
                                value={form.data.approval_notes}
                                onChange={(event) =>
                                    form.setData(
                                        'approval_notes',
                                        event.target.value,
                                    )
                                }
                                placeholder={stage4Translate("stage4.ui.cf048762964b", stage4Locale)}
                            />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void preflight()}
                                disabled={form.processing}
                            ><Stage4Text k="stage4.ui.cc88975dfdff" />
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={(event) => submit(event, false)}
                                disabled={form.processing}
                            ><Stage4Text k="stage4.ui.5591ae665d57" />
                            </Button>
                            <Button
                                type="button"
                                onClick={(event) => submit(event, true)}
                                disabled={form.processing}
                            ><Stage4Text k="stage4.ui.324c41f02cf2" />
                            </Button>
                        </div>
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
    children: ReactNode;
}) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
