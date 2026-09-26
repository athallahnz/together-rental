import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ArchiveRestore, PackagePlus, Search } from 'lucide-react';
import { useGlobalLocale } from '@/lib/locale-store';
import { formatStage3Date, stage3AssetStatus, stage3DisposalMethod } from '@/lib/stage3-display';
import { Stage3Text, stage3Translate } from '@/components/stage3-text';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FilterBar } from '@/components/ui/filter-bar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MetricCard } from '@/components/ui/metric-card';
import { RupiahInput } from '@/components/ui/rupiah-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Branch = { id: number; code: string; name: string };
type Product = {
    id: number;
    sku: string;
    name: string;
    replacement_value: string;
};
type Asset = {
    id: number;
    asset_code: string;
    serial_number: string | null;
    status: string;
    condition: string;
    purchase_date: string | null;
    purchase_price: string;
    product: Product;
    current_branch: Branch;
};
type Acquisition = {
    id: number;
    acquisition_number: string;
    acquisition_date: string;
    vendor_name: string | null;
    reference_number: string | null;
    total_amount: string;
    items_count: number;
    branch: Branch;
    creator: { id: number; name: string } | null;
    items: {
        id: number;
        purchase_price: string;
        asset: {
            id: number;
            asset_code: string;
            serial_number: string | null;
            status: string;
            is_active: boolean;
        };
        product: { id: number; sku: string; name: string };
    }[];
};
type Disposal = {
    id: number;
    disposal_number: string;
    disposal_date: string;
    method: string;
    sale_amount: string;
    reason: string;
    branch: Branch;
    disposer: { id: number; name: string } | null;
    asset: {
        id: number;
        asset_code: string;
        serial_number: string | null;
        status: string;
        condition: string;
        purchase_price: string;
        product: { id: number; sku: string; name: string };
    };
};
type Props = {
    summary: {
        active_assets: number;
        acquisition_count: number;
        acquisition_value: number;
        disposal_count: number;
        sale_proceeds: number;
    };
    acquisitions: Acquisition[];
    disposals: Disposal[];
    disposableAssets: Asset[];
    products: Product[];
    branches: Branch[];
    filters: { search: string; branch_id: number | null };
    permissions: { manage: boolean; inspect: boolean };
    defaultBranchId: number | null;
};

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});
const today = new Date().toISOString().slice(0, 10);

export default function AssetLifecycle({
    summary,
    acquisitions,
    disposals,
    disposableAssets,
    products,
    branches,
    filters,
    permissions,
    defaultBranchId,
}: Props) {
    const stage3Locale = useGlobalLocale();
    const [search, setSearch] = useState(filters.search);
    const [acquisitionOpen, setAcquisitionOpen] = useState(false);
    const [disposalAsset, setDisposalAsset] = useState<Asset | null>(null);
    const acquisitionForm = useForm({
        branch_id: (filters.branch_id ?? defaultBranchId ?? '').toString(),
        product_id: '',
        quantity: 1,
        acquisition_date: today,
        vendor_name: '',
        reference_number: '',
        unit_cost: 0,
        replacement_value: 0,
        warranty_until: '',
        serial_numbers: '',
        notes: '',
    });
    const disposalForm = useForm({
        asset: '',
        disposal_date: today,
        method: 'sold',
        sale_amount: 0,
        reason: '',
        notes: '',
    });
    const selectedProduct = useMemo(
        () =>
            products.find(
                (product) =>
                    product.id.toString() === acquisitionForm.data.product_id,
            ),
        [acquisitionForm.data.product_id, products],
    );

    const applyFilter = (values: Record<string, string>) => {
        router.get(
            '/assets/lifecycle',
            {
                search,
                branch_id: filters.branch_id ?? '',
                ...values,
            },
            { preserveState: true, replace: true },
        );
    };

    const submitAcquisition = (event: FormEvent) => {
        event.preventDefault();
        acquisitionForm.post('/assets/acquisitions', {
            preserveScroll: true,
            onSuccess: () => {
                acquisitionForm.reset();
                acquisitionForm.setData('acquisition_date', today);
                setAcquisitionOpen(false);
            },
        });
    };

    const openDisposal = (asset: Asset) => {
        disposalForm.reset();
        disposalForm.setData({
            asset: '',
            disposal_date: today,
            method: asset.status === 'lost' ? 'write_off' : 'sold',
            sale_amount: 0,
            reason: '',
            notes: '',
        });
        setDisposalAsset(asset);
    };

    const submitDisposal = (event: FormEvent) => {
        event.preventDefault();

        if (!disposalAsset) {
            return;
        }

        disposalForm.post(`/assets/${disposalAsset.id}/dispose`, {
            preserveScroll: true,
            onSuccess: () => setDisposalAsset(null),
        });
    };

    return (
        <>
            <Head title={stage3Translate('stage3.ui.siklus.aset.f537b', stage3Locale)} />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold"><Stage3Text k="stage3.ui.siklus.aset.f537b" /></h1>
                        <p className="text-sm text-muted-foreground">
                            <Stage3Text k="stage3.ui.catat.perolehan.unit.serialized.dan.tutup.aset.81ea6" /></p>
                    </div>
                    {permissions.manage && (
                        <Button onClick={() => setAcquisitionOpen(true)}>
                            <PackagePlus />
                            <Stage3Text k="stage3.ui.catat.acquisition.bc362" /></Button>
                    )}
                </header>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <MetricCard
                        label={stage3Translate('stage3.ui.aset.aktif.6041a', stage3Locale)}
                        value={summary.active_assets.toString()}
                    />
                    <MetricCard
                        label={stage3Translate('stage3.ui.acquisition.e85b4', stage3Locale)}
                        value={summary.acquisition_count.toString()}
                    />
                    <MetricCard
                        label={stage3Translate('stage3.ui.nilai.perolehan.3e353', stage3Locale)}
                        value={money.format(summary.acquisition_value)}
                    />
                    <MetricCard
                        label={stage3Translate('stage3.ui.disposal.5fad6', stage3Locale)}
                        value={summary.disposal_count.toString()}
                    />
                    <MetricCard
                        label={stage3Translate('stage3.ui.hasil.penjualan.550dc', stage3Locale)}
                        value={money.format(summary.sale_proceeds)}
                    />
                </div>

                <FilterBar
                    title={stage3Translate('stage3.ui.filter.siklus.aset.a4555', stage3Locale)}
                    description={stage3Translate('stage3.ui.cari.nomor.acquisition.disposal.vendor.aset.ser.3e419', stage3Locale)}
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) =>
                            event.key === 'Enter' && applyFilter({})
                        }
                        placeholder={stage3Translate('stage3.ui.cari.aset.atau.dokumen.lifecycle.c4800', stage3Locale)}
                    />
                    <Select
                        value={filters.branch_id?.toString() ?? 'all'}
                        onValueChange={(value) =>
                            applyFilter({
                                branch_id: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder={stage3Translate('stage3.ui.semua.cabang.27d30', stage3Locale)} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all"><Stage3Text k="stage3.ui.semua.cabang.27d30" /></SelectItem>
                            {branches.map((branch) => (
                                <SelectItem
                                    key={branch.id}
                                    value={branch.id.toString()}
                                >
                                    {branch.code} — {branch.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button variant="outline" onClick={() => applyFilter({})}>
                        <Search />
                        <Stage3Text k="stage3.ui.cari.3f227" /></Button>
                </FilterBar>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle><Stage3Text k="stage3.ui.acquisition.terbaru.f8f12" /></CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead className="border-b text-left text-muted-foreground">
                                    <tr>
                                        <th className="py-3"><Stage3Text k="stage3.ui.nomor.8d334" /></th>
                                        <th><Stage3Text k="stage3.ui.tanggal.80715" /></th>
                                        <th><Stage3Text k="stage3.ui.cabang.13874" /></th>
                                        <th><Stage3Text k="stage3.ui.vendor.d9615" /></th>
                                        <th><Stage3Text k="stage3.ui.unit.f6b93" /></th>
                                        <th className="text-right"><Stage3Text k="stage3.ui.nilai.74c54" /></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {acquisitions.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b align-top"
                                        >
                                            <td className="py-3 font-medium">
                                                {row.acquisition_number}
                                                <p className="mt-1 text-xs font-normal text-muted-foreground">
                                                    {row.items
                                                        .map(
                                                            (item) =>
                                                                item.asset
                                                                    .asset_code,
                                                        )
                                                        .join(', ')}
                                                </p>
                                            </td>
                                            <td>{formatStage3Date(row.acquisition_date, stage3Locale)}</td>
                                            <td>{row.branch.code}</td>
                                            <td>{row.vendor_name || '—'}</td>
                                            <td>{row.items_count}</td>
                                            <td className="text-right">
                                                {money.format(
                                                    Number(row.total_amount),
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {acquisitions.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                <Stage3Text k="stage3.ui.belum.ada.acquisition.sesuai.filter.eb0e5" /></td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle><Stage3Text k="stage3.ui.disposal.terbaru.7e271" /></CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead className="border-b text-left text-muted-foreground">
                                    <tr>
                                        <th className="py-3"><Stage3Text k="stage3.ui.nomor.8d334" /></th>
                                        <th><Stage3Text k="stage3.ui.aset.a2eed" /></th>
                                        <th><Stage3Text k="stage3.ui.metode.5ac33" /></th>
                                        <th><Stage3Text k="stage3.ui.cabang.13874" /></th>
                                        <th className="text-right"><Stage3Text k="stage3.ui.hasil.c123e" /></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {disposals.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b align-top"
                                        >
                                            <td className="py-3 font-medium">
                                                {row.disposal_number}
                                                <p className="mt-1 text-xs font-normal text-muted-foreground">
                                                    {formatStage3Date(row.disposal_date, stage3Locale)}
                                                </p>
                                            </td>
                                            <td>
                                                {row.asset.asset_code}
                                                <p className="text-xs text-muted-foreground">
                                                    {row.asset.product.name}
                                                </p>
                                            </td>
                                            <td>
                                                {stage3DisposalMethod(row.method, stage3Locale)}
                                            </td>
                                            <td>{row.branch.code}</td>
                                            <td className="text-right">
                                                {money.format(
                                                    Number(row.sale_amount),
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {disposals.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                <Stage3Text k="stage3.ui.belum.ada.disposal.sesuai.filter.629cb" /></td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage3Text k="stage3.ui.unit.siap.disposal.40e34" /></CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <p className="mb-4 text-sm text-muted-foreground">
                            <Stage3Text k="stage3.ui.hanya.unit.aktif.berstatus.available.atau.lost.c7489" /></p>
                        <table className="w-full min-w-[820px] text-sm">
                            <thead className="border-b text-left text-muted-foreground">
                                <tr>
                                    <th className="py-3"><Stage3Text k="stage3.ui.aset.a2eed" /></th>
                                    <th><Stage3Text k="stage3.ui.produk.869eb" /></th>
                                    <th><Stage3Text k="stage3.ui.cabang.13874" /></th>
                                    <th><Stage3Text k="stage3.ui.status.bae7d" /></th>
                                    <th><Stage3Text k="stage3.ui.harga.beli.19e23" /></th>
                                    <th className="text-right"><Stage3Text k="stage3.ui.aksi.60ad4" /></th>
                                </tr>
                            </thead>
                            <tbody>
                                {disposableAssets.map((asset) => (
                                    <tr key={asset.id} className="border-b">
                                        <td className="py-3 font-medium">
                                            {asset.asset_code}
                                            <p className="text-xs font-normal text-muted-foreground">
                                                {asset.serial_number ||
                                                    'Tanpa serial'}
                                            </p>
                                        </td>
                                        <td>{asset.product.name}</td>
                                        <td>{asset.current_branch.code}</td>
                                        <td>{stage3AssetStatus(asset.status, stage3Locale)}</td>
                                        <td>
                                            {money.format(
                                                Number(asset.purchase_price),
                                            )}
                                        </td>
                                        <td className="text-right">
                                            {permissions.manage ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        openDisposal(asset)
                                                    }
                                                >
                                                    <ArchiveRestore />
                                                    <Stage3Text k="stage3.ui.dispose.87876" /></Button>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {disposableAssets.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            <Stage3Text k="stage3.ui.tidak.ada.unit.yang.siap.disposal.pada.scope.in.55369" /></td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={acquisitionOpen} onOpenChange={setAcquisitionOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle><Stage3Text k="stage3.ui.catat.acquisition.aset.fab0c" /></DialogTitle>
                    </DialogHeader>
                    <form className="grid gap-4" onSubmit={submitAcquisition}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={stage3Translate('stage3.ui.cabang.13874', stage3Locale)}>
                                <Select
                                    value={acquisitionForm.data.branch_id}
                                    onValueChange={(value) =>
                                        acquisitionForm.setData(
                                            'branch_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={stage3Translate('stage3.ui.pilih.cabang.f5340', stage3Locale)} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {branches.map((branch) => (
                                            <SelectItem
                                                key={branch.id}
                                                value={branch.id.toString()}
                                            >
                                                {branch.code} — {branch.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <ErrorText
                                    value={acquisitionForm.errors.branch_id}
                                />
                            </Field>
                            <Field label={stage3Translate('stage3.ui.produk.serialized.dd056', stage3Locale)}>
                                <Select
                                    value={acquisitionForm.data.product_id}
                                    onValueChange={(value) => {
                                        acquisitionForm.setData(
                                            'product_id',
                                            value,
                                        );
                                        const product = products.find(
                                            (item) =>
                                                item.id.toString() === value,
                                        );

                                        if (product) {
                                            acquisitionForm.setData(
                                                'replacement_value',
                                                Number(
                                                    product.replacement_value,
                                                ),
                                            );
                                        }
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={stage3Translate('stage3.ui.pilih.produk.75abb', stage3Locale)} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {products.map((product) => (
                                            <SelectItem
                                                key={product.id}
                                                value={product.id.toString()}
                                            >
                                                {product.sku} — {product.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <ErrorText
                                    value={acquisitionForm.errors.product_id}
                                />
                            </Field>
                            <Field label={stage3Translate('stage3.ui.jumlah.unit.39fc5', stage3Locale)}>
                                <Input
                                    type="number"
                                    min={1}
                                    max={25}
                                    value={acquisitionForm.data.quantity}
                                    onChange={(event) =>
                                        acquisitionForm.setData(
                                            'quantity',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <ErrorText
                                    value={acquisitionForm.errors.quantity}
                                />
                            </Field>
                            <Field label={stage3Translate('stage3.ui.tanggal.acquisition.25fed', stage3Locale)}>
                                <Input
                                    type="date"
                                    max={today}
                                    value={
                                        acquisitionForm.data.acquisition_date
                                    }
                                    onChange={(event) =>
                                        acquisitionForm.setData(
                                            'acquisition_date',
                                            event.target.value,
                                        )
                                    }
                                />
                                <ErrorText
                                    value={
                                        acquisitionForm.errors.acquisition_date
                                    }
                                />
                            </Field>
                            <Field label={stage3Translate('stage3.ui.harga.beli.unit.5c6fa', stage3Locale)}>
                                <RupiahInput
                                    value={acquisitionForm.data.unit_cost}
                                    onValueChange={(value) =>
                                        acquisitionForm.setData(
                                            'unit_cost',
                                            value,
                                        )
                                    }
                                />
                                <ErrorText
                                    value={acquisitionForm.errors.unit_cost}
                                />
                            </Field>
                            <Field label={stage3Translate('stage3.ui.nilai.penggantian.unit.ae79a', stage3Locale)}>
                                <RupiahInput
                                    value={
                                        acquisitionForm.data.replacement_value
                                    }
                                    onValueChange={(value) =>
                                        acquisitionForm.setData(
                                            'replacement_value',
                                            value,
                                        )
                                    }
                                />
                                {selectedProduct && (
                                    <p className="text-xs text-muted-foreground">
                                        <Stage3Text k="stage3.ui.default.produk.b3d60" />{' '}
                                        {money.format(
                                            Number(
                                                selectedProduct.replacement_value,
                                            ),
                                        )}
                                    </p>
                                )}
                                <ErrorText
                                    value={
                                        acquisitionForm.errors.replacement_value
                                    }
                                />
                            </Field>
                            <Field label={stage3Translate('stage3.ui.vendor.d9615', stage3Locale)}>
                                <Input
                                    value={acquisitionForm.data.vendor_name}
                                    onChange={(event) =>
                                        acquisitionForm.setData(
                                            'vendor_name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={stage3Translate('stage3.ui.opsional.cf048', stage3Locale)}
                                />
                                <ErrorText
                                    value={acquisitionForm.errors.vendor_name}
                                />
                            </Field>
                            <Field label={stage3Translate('stage3.ui.referensi.pembelian.5ed00', stage3Locale)}>
                                <Input
                                    value={
                                        acquisitionForm.data.reference_number
                                    }
                                    onChange={(event) =>
                                        acquisitionForm.setData(
                                            'reference_number',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={stage3Translate('stage3.ui.invoice.po.nota.78aa2', stage3Locale)}
                                />
                                <ErrorText
                                    value={
                                        acquisitionForm.errors.reference_number
                                    }
                                />
                            </Field>
                            <Field label={stage3Translate('stage3.ui.garansi.sampai.fa65c', stage3Locale)}>
                                <Input
                                    type="date"
                                    value={acquisitionForm.data.warranty_until}
                                    onChange={(event) =>
                                        acquisitionForm.setData(
                                            'warranty_until',
                                            event.target.value,
                                        )
                                    }
                                />
                                <ErrorText
                                    value={
                                        acquisitionForm.errors.warranty_until
                                    }
                                />
                            </Field>
                        </div>
                        <Field label={stage3Translate('stage3.ui.serial.number.satu.baris.per.unit.dbf36', stage3Locale)}>
                            <textarea
                                className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={acquisitionForm.data.serial_numbers}
                                onChange={(event) =>
                                    acquisitionForm.setData(
                                        'serial_numbers',
                                        event.target.value,
                                    )
                                }
                                placeholder={'SN001\nSN002\nSN003'}
                            />
                            <ErrorText
                                value={acquisitionForm.errors.serial_numbers}
                            />
                        </Field>
                        <Field label={stage3Translate('stage3.ui.catatan.9f09a', stage3Locale)}>
                            <textarea
                                className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={acquisitionForm.data.notes}
                                onChange={(event) =>
                                    acquisitionForm.setData(
                                        'notes',
                                        event.target.value,
                                    )
                                }
                            />
                            <ErrorText value={acquisitionForm.errors.notes} />
                        </Field>
                        <p className="text-xs text-muted-foreground">
                            <Stage3Text k="stage3.ui.asset.code.dibuat.otomatis.dari.nomor.acquisiti.07bfd" /></p>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAcquisitionOpen(false)}
                            >
                                <Stage3Text k="stage3.ui.batal.14335" /></Button>
                            <Button disabled={acquisitionForm.processing}>
                                <Stage3Text k="stage3.ui.simpan.acquisition.e09fa" /></Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={Boolean(disposalAsset)}
                onOpenChange={(open) => !open && setDisposalAsset(null)}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            <Stage3Text k="stage3.ui.dispose.87876" />{disposalAsset?.asset_code}
                        </DialogTitle>
                    </DialogHeader>
                    <form className="grid gap-4" onSubmit={submitDisposal}>
                        <ErrorText value={disposalForm.errors.asset} />
                        <Field label={stage3Translate('stage3.ui.tanggal.disposal.4adcd', stage3Locale)}>
                            <Input
                                type="date"
                                max={today}
                                value={disposalForm.data.disposal_date}
                                onChange={(event) =>
                                    disposalForm.setData(
                                        'disposal_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <ErrorText
                                value={disposalForm.errors.disposal_date}
                            />
                        </Field>
                        <Field label={stage3Translate('stage3.ui.metode.5ac33', stage3Locale)}>
                            <Select
                                value={disposalForm.data.method}
                                onValueChange={(value) =>
                                    disposalForm.setData('method', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {disposalAsset?.status !== 'lost' && (
                                        <SelectItem value="sold">
                                            <Stage3Text k="stage3.ui.dijual.76dbb" /></SelectItem>
                                    )}
                                    <SelectItem value="write_off">
                                        <Stage3Text k="stage3.ui.write.off.95181" /></SelectItem>
                                    {disposalAsset?.status !== 'lost' && (
                                        <SelectItem value="donated">
                                            <Stage3Text k="stage3.ui.donasi.d1891" /></SelectItem>
                                    )}
                                </SelectContent>
                            </Select>
                            <ErrorText value={disposalForm.errors.method} />
                        </Field>
                        {disposalForm.data.method === 'sold' && (
                            <Field label={stage3Translate('stage3.ui.nilai.penjualan.ad434', stage3Locale)}>
                                <RupiahInput
                                    value={disposalForm.data.sale_amount}
                                    onValueChange={(value) =>
                                        disposalForm.setData(
                                            'sale_amount',
                                            value,
                                        )
                                    }
                                />
                                <ErrorText
                                    value={disposalForm.errors.sale_amount}
                                />
                            </Field>
                        )}
                        <Field label={stage3Translate('stage3.ui.alasan.3faa8', stage3Locale)}>
                            <textarea
                                className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={disposalForm.data.reason}
                                onChange={(event) =>
                                    disposalForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                placeholder={stage3Translate('stage3.ui.jelaskan.alasan.disposal.9858a', stage3Locale)}
                            />
                            <ErrorText value={disposalForm.errors.reason} />
                        </Field>
                        <Field label={stage3Translate('stage3.ui.catatan.tambahan.e2149', stage3Locale)}>
                            <textarea
                                className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={disposalForm.data.notes}
                                onChange={(event) =>
                                    disposalForm.setData(
                                        'notes',
                                        event.target.value,
                                    )
                                }
                            />
                            <ErrorText value={disposalForm.errors.notes} />
                        </Field>
                        <p className="text-xs text-muted-foreground">
                            <Stage3Text k="stage3.ui.disposal.mengubah.aset.menjadi.retired.dan.nona.c0394" /></p>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDisposalAsset(null)}
                            >
                                <Stage3Text k="stage3.ui.batal.14335" /></Button>
                            <Button disabled={disposalForm.processing}>
                                <Stage3Text k="stage3.ui.konfirmasi.disposal.29197" /></Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function ErrorText({ value }: { value?: string }) {
    return value ? <p className="text-xs text-destructive">{value}</p> : null;
}
