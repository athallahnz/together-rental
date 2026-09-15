import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ArchiveRestore, PackagePlus, Search } from 'lucide-react';
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
const disposalLabels: Record<string, string> = {
    sold: 'Dijual',
    write_off: 'Write-off',
    donated: 'Donasi',
};

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
            <Head title="Siklus Aset" />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Siklus Aset</h1>
                        <p className="text-sm text-muted-foreground">
                            Catat perolehan unit serialized dan tutup aset
                            melalui penjualan, write-off, atau donasi tanpa
                            menghapus histori.
                        </p>
                    </div>
                    {permissions.manage && (
                        <Button onClick={() => setAcquisitionOpen(true)}>
                            <PackagePlus />
                            Catat Acquisition
                        </Button>
                    )}
                </header>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <MetricCard
                        label="Aset aktif"
                        value={summary.active_assets.toString()}
                    />
                    <MetricCard
                        label="Acquisition"
                        value={summary.acquisition_count.toString()}
                    />
                    <MetricCard
                        label="Nilai perolehan"
                        value={money.format(summary.acquisition_value)}
                    />
                    <MetricCard
                        label="Disposal"
                        value={summary.disposal_count.toString()}
                    />
                    <MetricCard
                        label="Hasil penjualan"
                        value={money.format(summary.sale_proceeds)}
                    />
                </div>

                <FilterBar
                    title="Filter siklus aset"
                    description="Cari nomor acquisition/disposal, vendor, aset, serial, SKU, atau produk."
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) =>
                            event.key === 'Enter' && applyFilter({})
                        }
                        placeholder="Cari aset atau dokumen lifecycle..."
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
                            <SelectValue placeholder="Semua cabang" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua cabang</SelectItem>
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
                        Cari
                    </Button>
                </FilterBar>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Acquisition Terbaru</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead className="border-b text-left text-muted-foreground">
                                    <tr>
                                        <th className="py-3">Nomor</th>
                                        <th>Tanggal</th>
                                        <th>Cabang</th>
                                        <th>Vendor</th>
                                        <th>Unit</th>
                                        <th className="text-right">Nilai</th>
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
                                            <td>{row.acquisition_date}</td>
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
                                                Belum ada acquisition sesuai
                                                filter.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Disposal Terbaru</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead className="border-b text-left text-muted-foreground">
                                    <tr>
                                        <th className="py-3">Nomor</th>
                                        <th>Aset</th>
                                        <th>Metode</th>
                                        <th>Cabang</th>
                                        <th className="text-right">Hasil</th>
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
                                                    {row.disposal_date}
                                                </p>
                                            </td>
                                            <td>
                                                {row.asset.asset_code}
                                                <p className="text-xs text-muted-foreground">
                                                    {row.asset.product.name}
                                                </p>
                                            </td>
                                            <td>
                                                {disposalLabels[row.method] ??
                                                    row.method}
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
                                                Belum ada disposal sesuai
                                                filter.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Unit Siap Disposal</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <p className="mb-4 text-sm text-muted-foreground">
                            Hanya unit aktif berstatus available atau lost yang
                            tampil. Sistem tetap melakukan guard reservasi,
                            rental, maintenance, dan branch scope saat submit.
                        </p>
                        <table className="w-full min-w-[820px] text-sm">
                            <thead className="border-b text-left text-muted-foreground">
                                <tr>
                                    <th className="py-3">Aset</th>
                                    <th>Produk</th>
                                    <th>Cabang</th>
                                    <th>Status</th>
                                    <th>Harga Beli</th>
                                    <th className="text-right">Aksi</th>
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
                                        <td>{asset.status}</td>
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
                                                    Dispose
                                                </Button>
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
                                            Tidak ada unit yang siap disposal
                                            pada scope ini.
                                        </td>
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
                        <DialogTitle>Catat Acquisition Aset</DialogTitle>
                    </DialogHeader>
                    <form className="grid gap-4" onSubmit={submitAcquisition}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Cabang">
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
                                        <SelectValue placeholder="Pilih cabang" />
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
                            <Field label="Produk serialized">
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
                                        <SelectValue placeholder="Pilih produk" />
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
                            <Field label="Jumlah unit">
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
                            <Field label="Tanggal acquisition">
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
                            <Field label="Harga beli / unit">
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
                            <Field label="Nilai penggantian / unit">
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
                                        Default produk{' '}
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
                            <Field label="Vendor">
                                <Input
                                    value={acquisitionForm.data.vendor_name}
                                    onChange={(event) =>
                                        acquisitionForm.setData(
                                            'vendor_name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Opsional"
                                />
                                <ErrorText
                                    value={acquisitionForm.errors.vendor_name}
                                />
                            </Field>
                            <Field label="Referensi pembelian">
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
                                    placeholder="Invoice / PO / nota"
                                />
                                <ErrorText
                                    value={
                                        acquisitionForm.errors.reference_number
                                    }
                                />
                            </Field>
                            <Field label="Garansi sampai">
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
                        <Field label="Serial number — satu baris per unit">
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
                        <Field label="Catatan">
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
                            Asset code dibuat otomatis dari nomor acquisition.
                            Pencatatan ini tidak membuat pembayaran atau cash
                            transaction otomatis.
                        </p>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAcquisitionOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button disabled={acquisitionForm.processing}>
                                Simpan Acquisition
                            </Button>
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
                            Dispose {disposalAsset?.asset_code}
                        </DialogTitle>
                    </DialogHeader>
                    <form className="grid gap-4" onSubmit={submitDisposal}>
                        <ErrorText value={disposalForm.errors.asset} />
                        <Field label="Tanggal disposal">
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
                        <Field label="Metode">
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
                                            Dijual
                                        </SelectItem>
                                    )}
                                    <SelectItem value="write_off">
                                        Write-off
                                    </SelectItem>
                                    {disposalAsset?.status !== 'lost' && (
                                        <SelectItem value="donated">
                                            Donasi
                                        </SelectItem>
                                    )}
                                </SelectContent>
                            </Select>
                            <ErrorText value={disposalForm.errors.method} />
                        </Field>
                        {disposalForm.data.method === 'sold' && (
                            <Field label="Nilai penjualan">
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
                        <Field label="Alasan">
                            <textarea
                                className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={disposalForm.data.reason}
                                onChange={(event) =>
                                    disposalForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                placeholder="Jelaskan alasan disposal..."
                            />
                            <ErrorText value={disposalForm.errors.reason} />
                        </Field>
                        <Field label="Catatan tambahan">
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
                            Disposal mengubah aset menjadi retired dan nonaktif.
                            Nilai penjualan hanya dicatat sebagai histori
                            lifecycle; tidak membuat cash-in otomatis.
                        </p>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDisposalAsset(null)}
                            >
                                Batal
                            </Button>
                            <Button disabled={disposalForm.processing}>
                                Konfirmasi Disposal
                            </Button>
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
