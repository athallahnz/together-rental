import { Head, router, useForm } from '@inertiajs/react';
import { BadgePercent, Plus, Save, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { PaginationLinks } from '@/components/pagination-links';
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
import type {
    AccessBranch,
    Promotion,
    PromotionPagination,
    PromotionType,
} from '@/types';

type Props = {
    promotions: PromotionPagination;
    branches: AccessBranch[];
    filters: {
        search: string;
        status: string;
        branch_id: number | null;
    };
    canManage: boolean;
    canCreateGlobal: boolean;
    defaultBranchId: number | null;
};

type PromotionForm = {
    branch_id: number | null;
    code: string;
    name: string;
    type: PromotionType;
    value: number;
    maximum_discount: number | null;
    minimum_transaction: number;
    bonus_duration: number;
    usage_limit: number | null;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    member_only: boolean;
    non_member_only: boolean;
    allow_member_stack: boolean;
};

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const localDate = (value: string | null) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
};

const emptyForm = (branchId: number | null): PromotionForm => ({
    branch_id: branchId,
    code: '',
    name: '',
    type: 'percentage',
    value: 10,
    maximum_discount: null,
    minimum_transaction: 0,
    bonus_duration: 0,
    usage_limit: null,
    starts_at: '',
    ends_at: '',
    is_active: true,
    member_only: false,
    non_member_only: false,
    allow_member_stack: false,
});

export default function PromotionsPage({
    promotions,
    branches,
    filters,
    canManage,
    canCreateGlobal,
    defaultBranchId,
}: Props) {
    const [editing, setEditing] = useState<Promotion | null>(null);
    const initialBranchId = filters.branch_id ?? defaultBranchId;
    const form = useForm<PromotionForm>(emptyForm(initialBranchId));
    const usage = useMemo(
        () =>
            promotions.data.reduce(
                (total, item) =>
                    total +
                    (item.booking_usage_count ?? 0) +
                    (item.extension_usage_count ?? 0),
                0,
            ),
        [promotions.data],
    );

    const beginEdit = (promotion: Promotion) => {
        setEditing(promotion);
        form.setData({
            branch_id: promotion.branch_id,
            code: promotion.code,
            name: promotion.name,
            type: promotion.type,
            value: Number(promotion.value),
            maximum_discount:
                promotion.maximum_discount === null
                    ? null
                    : Number(promotion.maximum_discount),
            minimum_transaction: Number(promotion.minimum_transaction),
            bonus_duration: promotion.bonus_duration,
            usage_limit: promotion.usage_limit,
            starts_at: localDate(promotion.starts_at),
            ends_at: localDate(promotion.ends_at),
            is_active: promotion.is_active,
            member_only: Boolean(promotion.rules?.member_only),
            non_member_only: Boolean(promotion.rules?.non_member_only),
            allow_member_stack: Boolean(promotion.rules?.allow_member_stack),
        });
        form.clearErrors();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const reset = () => {
        setEditing(null);
        form.setData(emptyForm(filters.branch_id ?? defaultBranchId));
        form.clearErrors();
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: reset,
        };

        if (editing) {
            form.put(`/catalog/promotions/${editing.id}`, options);
        } else {
            form.post('/catalog/promotions', options);
        }
    };

    const applyFilters = (patch: Partial<Props['filters']>) => {
        router.get(
            '/catalog/promotions',
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Promosi & Diskon" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <BadgePercent className="size-6" />
                            Promosi & Diskon
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Pricing terpusat untuk Booking, Rental In Store, dan
                            Perpanjangan. Member aktif mendapat diskon 10% flat
                            per transaksi.
                        </p>
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {promotions.total} promo · {usage} pemakaian pada
                        halaman ini
                    </div>
                </header>

                {canManage && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {editing
                                    ? `Edit ${editing.code}`
                                    : 'Buat promo'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                                onSubmit={submit}
                            >
                                <Field
                                    label="Scope cabang"
                                    error={form.errors.branch_id}
                                >
                                    <Select
                                        value={
                                            form.data.branch_id === null
                                                ? 'global'
                                                : String(form.data.branch_id)
                                        }
                                        onValueChange={(value) =>
                                            form.setData(
                                                'branch_id',
                                                value === 'global'
                                                    ? null
                                                    : Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {canCreateGlobal && (
                                                <SelectItem value="global">
                                                    Global semua cabang
                                                </SelectItem>
                                            )}
                                            {branches.map((branch) => (
                                                <SelectItem
                                                    key={branch.id}
                                                    value={String(branch.id)}
                                                >
                                                    {branch.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field label="Kode" error={form.errors.code}>
                                    <Input
                                        value={form.data.code}
                                        onChange={(event) =>
                                            form.setData(
                                                'code',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="WEEKEND10"
                                    />
                                </Field>
                                <Field
                                    label="Nama promo"
                                    error={form.errors.name}
                                >
                                    <Input
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Tipe" error={form.errors.type}>
                                    <Select
                                        value={form.data.type}
                                        onValueChange={(value: PromotionType) =>
                                            form.setData('type', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="percentage">
                                                Persentase
                                            </SelectItem>
                                            <SelectItem value="fixed">
                                                Nominal tetap
                                            </SelectItem>
                                            <SelectItem value="bonus_duration">
                                                Bonus durasi
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                {form.data.type === 'percentage' && (
                                    <Field
                                        label="Diskon (%)"
                                        error={form.errors.value}
                                    >
                                        <Input
                                            type="number"
                                            min={0}
                                            max={100}
                                            value={form.data.value}
                                            onChange={(event) =>
                                                form.setData(
                                                    'value',
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                                {form.data.type === 'fixed' && (
                                    <Field
                                        label="Diskon nominal"
                                        error={form.errors.value}
                                    >
                                        <RupiahInput
                                            value={form.data.value}
                                            onValueChange={(value) =>
                                                form.setData('value', value)
                                            }
                                        />
                                    </Field>
                                )}
                                {form.data.type === 'percentage' && (
                                    <Field
                                        label="Maksimum diskon"
                                        error={form.errors.maximum_discount}
                                    >
                                        <RupiahInput
                                            value={
                                                form.data.maximum_discount ?? 0
                                            }
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'maximum_discount',
                                                    value > 0 ? value : null,
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                                {form.data.type === 'bonus_duration' && (
                                    <Field
                                        label="Bonus unit durasi"
                                        error={form.errors.bonus_duration}
                                    >
                                        <Input
                                            type="number"
                                            min={1}
                                            max={365}
                                            value={form.data.bonus_duration}
                                            onChange={(event) =>
                                                form.setData(
                                                    'bonus_duration',
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                                <Field
                                    label="Minimum transaksi"
                                    error={form.errors.minimum_transaction}
                                >
                                    <RupiahInput
                                        value={form.data.minimum_transaction}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'minimum_transaction',
                                                value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Batas penggunaan"
                                    error={form.errors.usage_limit}
                                >
                                    <Input
                                        type="number"
                                        min={1}
                                        value={form.data.usage_limit ?? ''}
                                        placeholder="Kosong = tanpa batas"
                                        onChange={(event) =>
                                            form.setData(
                                                'usage_limit',
                                                event.target.value
                                                    ? Number(event.target.value)
                                                    : null,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Mulai"
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
                                    label="Berakhir"
                                    error={form.errors.ends_at}
                                >
                                    <Input
                                        type="datetime-local"
                                        value={form.data.ends_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'ends_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <div className="space-y-2 md:col-span-2 xl:col-span-4">
                                    <Label>Aturan pelanggan</Label>
                                    <div className="flex flex-wrap gap-4 rounded-md border p-3 text-sm">
                                        <Check
                                            checked={form.data.member_only}
                                            label="Hanya member"
                                            onChange={(checked) => {
                                                form.setData(
                                                    'member_only',
                                                    checked,
                                                );

                                                if (checked) {
                                                    form.setData(
                                                        'non_member_only',
                                                        false,
                                                    );
                                                }
                                            }}
                                        />
                                        <Check
                                            checked={form.data.non_member_only}
                                            label="Hanya non-member"
                                            onChange={(checked) => {
                                                form.setData(
                                                    'non_member_only',
                                                    checked,
                                                );

                                                if (checked) {
                                                    form.setData(
                                                        'member_only',
                                                        false,
                                                    );
                                                }
                                            }}
                                        />
                                        <Check
                                            checked={
                                                form.data.allow_member_stack
                                            }
                                            label="Izinkan diskon promo + member 10%"
                                            onChange={(checked) =>
                                                form.setData(
                                                    'allow_member_stack',
                                                    checked,
                                                )
                                            }
                                        />
                                        <Check
                                            checked={form.data.is_active}
                                            label="Promo aktif"
                                            onChange={(checked) =>
                                                form.setData(
                                                    'is_active',
                                                    checked,
                                                )
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="flex gap-2 md:col-span-2 xl:col-span-4">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {editing ? <Save /> : <Plus />}
                                        {editing
                                            ? 'Simpan perubahan'
                                            : 'Buat promo'}
                                    </Button>
                                    {editing && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={reset}
                                        >
                                            <X /> Batal edit
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar promo</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 md:grid-cols-3">
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    defaultValue={filters.search}
                                    className="pl-9"
                                    placeholder="Cari kode atau nama..."
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters({
                                                search: event.currentTarget
                                                    .value,
                                            });
                                        }
                                    }}
                                />
                            </div>
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        status: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua status
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Aktif
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Nonaktif
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={
                                    filters.branch_id
                                        ? String(filters.branch_id)
                                        : 'all'
                                }
                                onValueChange={(value) =>
                                    applyFilters({
                                        branch_id:
                                            value === 'all'
                                                ? null
                                                : Number(value),
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Global + semua cabang
                                    </SelectItem>
                                    {branches.map((branch) => (
                                        <SelectItem
                                            key={branch.id}
                                            value={String(branch.id)}
                                        >
                                            {branch.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full min-w-[980px] text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3">Promo</th>
                                        <th className="p-3">Scope</th>
                                        <th className="p-3">Benefit</th>
                                        <th className="p-3">Periode</th>
                                        <th className="p-3">Pemakaian</th>
                                        <th className="p-3">Status</th>
                                        {canManage && (
                                            <th className="p-3 text-right">
                                                Aksi
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {promotions.data.map((promotion) => {
                                        const used =
                                            (promotion.booking_usage_count ??
                                                0) +
                                            (promotion.extension_usage_count ??
                                                0);

                                        return (
                                            <tr
                                                key={promotion.id}
                                                className="border-t align-top"
                                            >
                                                <td className="p-3">
                                                    <div className="font-semibold">
                                                        {promotion.code}
                                                    </div>
                                                    <div className="text-muted-foreground">
                                                        {promotion.name}
                                                    </div>
                                                </td>
                                                <td className="p-3">
                                                    {promotion.branch?.name ??
                                                        'Global'}
                                                </td>
                                                <td className="p-3">
                                                    {promotion.type ===
                                                        'percentage' &&
                                                        `${Number(promotion.value)}%`}
                                                    {promotion.type ===
                                                        'fixed' &&
                                                        money.format(
                                                            Number(
                                                                promotion.value,
                                                            ),
                                                        )}
                                                    {promotion.type ===
                                                        'bonus_duration' &&
                                                        `+${promotion.bonus_duration} unit durasi`}
                                                    {promotion.rules
                                                        ?.allow_member_stack && (
                                                        <div className="text-xs text-muted-foreground">
                                                            Stack member aktif
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="p-3 text-xs">
                                                    <div>
                                                        {promotion.starts_at
                                                            ? new Date(
                                                                  promotion.starts_at,
                                                              ).toLocaleString(
                                                                  'id-ID',
                                                              )
                                                            : 'Tanpa batas awal'}
                                                    </div>
                                                    <div>
                                                        {promotion.ends_at
                                                            ? new Date(
                                                                  promotion.ends_at,
                                                              ).toLocaleString(
                                                                  'id-ID',
                                                              )
                                                            : 'Tanpa batas akhir'}
                                                    </div>
                                                </td>
                                                <td className="p-3">
                                                    {used}
                                                    {promotion.usage_limit
                                                        ? ` / ${promotion.usage_limit}`
                                                        : ''}
                                                </td>
                                                <td className="p-3">
                                                    <span
                                                        className={
                                                            promotion.is_active
                                                                ? 'text-emerald-600'
                                                                : 'text-muted-foreground'
                                                        }
                                                    >
                                                        {promotion.is_active
                                                            ? 'Aktif'
                                                            : 'Nonaktif'}
                                                    </span>
                                                </td>
                                                {canManage && (
                                                    <td className="p-3 text-right">
                                                        {promotion.can_manage && (
                                                            <div className="flex justify-end gap-2">
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        beginEdit(
                                                                            promotion,
                                                                        )
                                                                    }
                                                                >
                                                                    Edit
                                                                </Button>
                                                                {promotion.is_active && (
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="destructive"
                                                                        onClick={() =>
                                                                            router.delete(
                                                                                `/catalog/promotions/${promotion.id}`,
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        Nonaktifkan
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        )}
                                                    </td>
                                                )}
                                            </tr>
                                        );
                                    })}
                                    {promotions.data.length === 0 && (
                                        <tr>
                                            <td
                                                className="p-6 text-center text-muted-foreground"
                                                colSpan={canManage ? 7 : 6}
                                            >
                                                Belum ada promo pada filter ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <PaginationLinks
                            links={promotions.links}
                            from={promotions.from}
                            to={promotions.to}
                            total={promotions.total}
                        />
                    </CardContent>
                </Card>
            </div>
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

function Check({
    checked,
    label,
    onChange,
}: {
    checked: boolean;
    label: string;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label className="flex cursor-pointer items-center gap-2">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
            />
            <span>{label}</span>
        </label>
    );
}
