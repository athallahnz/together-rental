import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Boxes,
    CalendarDays,
    CircleDollarSign,
    CircleOff,
    PackageCheck,
    Pencil,
    Plus,
    Trash2,
    Warehouse,
} from 'lucide-react';
import { useState } from 'react';
import {
    ProductFormDialog,
    ProductRateFormDialog,
} from '@/components/catalog/catalog-dialogs';
import { AssetSmartCalendarDialog } from '@/components/catalog/asset-smart-calendar';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    AccessBranch,
    CatalogBranchStock,
    CatalogPermissions,
    Product,
    ProductCategory,
    ProductRate,
    RatePlan,
} from '@/types';

type AssetUnit = {
    id: number;
    product_id: number;
    current_branch_id: number | null;
    asset_code: string;
    serial_number: string | null;
    status: string;
    condition: string;
    is_active: boolean;
    current_branch?: { id: number; code: string; name: string } | null;
};

type Props = {
    product: Product;
    assetSummary: Record<string, number>;
    branchStock: CatalogBranchStock[];
    assetUnits: AssetUnit[];
    filters: {
        branch_id: number | null;
    };
    categories: ProductCategory[];
    ratePlans: RatePlan[];
    branches: AccessBranch[];
    permissions: CatalogPermissions;
};

export default function ProductShow({
    product,
    assetSummary,
    branchStock,
    assetUnits,
    filters,
    categories,
    ratePlans,
    branches,
    permissions,
}: Props) {
    const { errors } = usePage().props;
    const confirm = useConfirmDialog();
    const [productDialog, setProductDialog] = useState(false);
    const [rateDialog, setRateDialog] = useState(false);
    const [editingRate, setEditingRate] = useState<ProductRate | null>(null);
    const [calendarAsset, setCalendarAsset] = useState<AssetUnit | null>(null);
    const totalAssets = Object.values(assetSummary).reduce(
        (sum, value) => sum + Number(value),
        0,
    );
    const selectedBranch =
        branches.find((branch) => branch.id === filters.branch_id) ?? null;
    const scopeLabel = selectedBranch
        ? `${selectedBranch.code} · ${selectedBranch.name}`
        : 'Semua cabang yang dapat diakses';
    const catalogHref = filters.branch_id
        ? `/catalog?branch_id=${filters.branch_id}`
        : '/catalog';

    const openRate = (rate: ProductRate | null) => {
        setEditingRate(rate);
        setRateDialog(true);
    };

    return (
        <>
            <Head title={product.name} />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4">
                    <Button asChild variant="ghost" className="w-fit">
                        <Link href={catalogHref}>
                            <ArrowLeft />
                            Semua produk
                        </Link>
                    </Button>
                    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">{product.sku}</Badge>
                                <Badge
                                    variant={
                                        product.is_active
                                            ? 'outline'
                                            : 'secondary'
                                    }
                                >
                                    {product.is_active ? 'Aktif' : 'Nonaktif'}
                                </Badge>
                                <Badge variant="secondary">
                                    {product.tracking_type === 'serialized'
                                        ? 'Per unit / serial'
                                        : 'Kuantitas / bulk'}
                                </Badge>
                                <Badge
                                    variant={
                                        product.enrichment_status === 'enriched'
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {product.enrichment_status === 'enriched'
                                        ? 'Canonical mapped'
                                        : 'Belum dipetakan'}
                                </Badge>
                            </div>
                            <h1 className="mt-3 text-2xl font-semibold tracking-tight">
                                {product.name}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                                {[product.brand, product.model, product.variant]
                                    .filter(Boolean)
                                    .join(' · ') ||
                                    'Brand, model, dan varian belum diisi'}
                                {product.category
                                    ? ` · ${product.category.name}`
                                    : ''}
                            </p>
                        </div>
                        {permissions.manage && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => setProductDialog(true)}
                                >
                                    <Pencil />
                                    Edit produk
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => openRate(null)}
                                >
                                    <Plus />
                                    Tambah harga
                                </Button>
                            </div>
                        )}
                    </div>
                </header>

                <ProductErrors errors={errors} />

                <Card className="border-primary/20 bg-primary/[0.02]">
                    <CardContent className="grid gap-4 p-4 md:grid-cols-[minmax(0,1fr)_minmax(260px,360px)] md:items-center md:p-5">
                        <div>
                            <p className="text-sm font-semibold">
                                Lingkup inventaris produk
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Aset, stok, dan daftar harga mengikuti cabang
                                yang dipilih. Master produk tetap global.
                            </p>
                            <p className="mt-2 text-xs font-medium text-primary">
                                Aktif: {scopeLabel}
                            </p>
                        </div>
                        <Select
                            value={filters.branch_id?.toString() ?? 'all'}
                            onValueChange={(value) =>
                                router.get(
                                    `/catalog/products/${product.id}`,
                                    {
                                        branch_id:
                                            value === 'all'
                                                ? undefined
                                                : Number(value),
                                    },
                                    {
                                        preserveState: true,
                                        replace: true,
                                    },
                                )
                            }
                        >
                            <SelectTrigger aria-label="Filter cabang produk">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua cabang yang dapat diakses
                                </SelectItem>
                                {branches.map((branch) => (
                                    <SelectItem
                                        key={branch.id}
                                        value={branch.id.toString()}
                                    >
                                        {branch.code} · {branch.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        label="Nilai penggantian"
                        value={formatCurrency(product.replacement_value)}
                        icon={CircleDollarSign}
                    />
                    <Metric
                        label={`Total aset · ${scopeLabel}`}
                        value={totalAssets.toString()}
                        icon={Boxes}
                    />
                    <Metric
                        label={`Aset tersedia · ${scopeLabel}`}
                        value={Number(assetSummary.available ?? 0).toString()}
                        icon={PackageCheck}
                    />
                    <Metric
                        label={`Harga aktif · ${scopeLabel}`}
                        value={(product.rates ?? [])
                            .filter((rate) => rate.is_active)
                            .length.toString()}
                        icon={CircleDollarSign}
                    />
                </section>

                {product.tracking_type === 'serialized' && (
                    <Card className="border-primary/15">
                        <CardHeader>
                            <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <CalendarDays className="size-5" />
                                        Smart Calendar per aset
                                    </CardTitle>
                                    <CardDescription>
                                        Setiap unit memiliki kalender sendiri
                                        untuk booking, rental, maintenance, dan
                                        transfer.
                                    </CardDescription>
                                </div>
                                <Badge variant="outline">
                                    {assetUnits.length} unit pada scope ini
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {assetUnits.map((asset) => (
                                    <div
                                        key={asset.id}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-semibold">
                                                    {asset.asset_code}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {asset.serial_number ??
                                                        'Tanpa serial'}{' '}
                                                    ·{' '}
                                                    {asset.current_branch
                                                        ?.code ?? '-'}
                                                </p>
                                            </div>
                                            <Badge
                                                variant={
                                                    asset.status === 'available'
                                                        ? 'outline'
                                                        : 'secondary'
                                                }
                                            >
                                                {assetStatusLabel(asset.status)}
                                            </Badge>
                                        </div>
                                        <p className="mt-3 text-xs text-muted-foreground">
                                            Kondisi {asset.condition} ·{' '}
                                            {asset.is_active
                                                ? 'aktif'
                                                : 'nonaktif'}
                                        </p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mt-3 w-full"
                                            onClick={() =>
                                                setCalendarAsset(asset)
                                            }
                                        >
                                            <CalendarDays />
                                            Lihat kalender unit
                                        </Button>
                                    </div>
                                ))}
                                {assetUnits.length === 0 && (
                                    <div className="col-span-full rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                        Belum ada aset serialized pada cabang
                                        yang dipilih.
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.6fr)]">
                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>Daftar harga</CardTitle>
                                <CardDescription>
                                    Harga global menjadi fallback; harga cabang
                                    dipakai untuk override operasional lokal.
                                </CardDescription>
                            </div>
                            {permissions.manage && (
                                <Button
                                    size="sm"
                                    onClick={() => openRate(null)}
                                >
                                    <Plus />
                                    Harga
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {(product.rates ?? []).length === 0 ? (
                                <EmptyRates />
                            ) : (
                                product.rates?.map((rate) => {
                                    const canManage =
                                        permissions.manage &&
                                        (rate.branch_id !== null ||
                                            permissions.manageGlobal);

                                    return (
                                        <article
                                            key={rate.id}
                                            className="grid gap-4 rounded-xl border p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                                        >
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-semibold">
                                                        {rate.rate_plan?.name ??
                                                            'Rate plan'}
                                                    </p>
                                                    <Badge variant="outline">
                                                        {rate.branch
                                                            ? rate.branch.code
                                                            : 'Global'}
                                                    </Badge>
                                                    <Badge
                                                        variant={
                                                            rate.is_active
                                                                ? 'outline'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {rate.is_active
                                                            ? 'Aktif'
                                                            : 'Nonaktif'}
                                                    </Badge>
                                                </div>
                                                <p className="mt-2 text-lg font-semibold">
                                                    {formatCurrency(
                                                        rate.amount,
                                                    )}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Deposit{' '}
                                                    {formatCurrency(
                                                        rate.deposit_amount,
                                                    )}{' '}
                                                    · tambahan/jam{' '}
                                                    {formatCurrency(
                                                        rate.additional_hour_amount,
                                                    )}{' '}
                                                    · denda{' '}
                                                    {formatCurrency(
                                                        rate.late_fee_amount,
                                                    )}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Berlaku{' '}
                                                    {rate.valid_from
                                                        ? formatDate(
                                                              rate.valid_from,
                                                          )
                                                        : 'tanpa batas awal'}{' '}
                                                    —{' '}
                                                    {rate.valid_until
                                                        ? formatDate(
                                                              rate.valid_until,
                                                          )
                                                        : 'tanpa batas akhir'}
                                                </p>
                                            </div>
                                            {canManage && (
                                                <div className="flex gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            openRate(rate)
                                                        }
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label="Hapus harga"
                                                        onClick={async () => {
                                                            const confirmed =
                                                                await confirm({
                                                                    title: 'Hapus harga produk?',
                                                                    description:
                                                                        'Harga ini akan dihapus dan tidak lagi tersedia untuk transaksi baru.',
                                                                    confirmLabel:
                                                                        'Hapus harga',
                                                                    variant:
                                                                        'destructive',
                                                                });

                                                            if (!confirmed) {
                                                                return;
                                                            }

                                                            router.delete(
                                                                `/catalog/product-rates/${rate.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </div>
                                            )}
                                        </article>
                                    );
                                })
                            )}
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Profil produk</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm">
                                <Info
                                    label="Kategori"
                                    value={
                                        product.category
                                            ? `${product.category.code} · ${product.category.name}`
                                            : 'Tanpa kategori'
                                    }
                                />
                                <Info
                                    label="Dapat disewakan"
                                    value={product.is_rentable ? 'Ya' : 'Tidak'}
                                />
                                <Info
                                    label="Deskripsi"
                                    value={
                                        product.description ||
                                        'Belum ada deskripsi.'
                                    }
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Distribusi per cabang</CardTitle>
                                <CardDescription>
                                    Ringkasan aset serialized dan stok bulk
                                    untuk seluruh cabang yang dapat Anda akses.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {branchStock.map((item) => {
                                    const isSelected =
                                        filters.branch_id === item.branch.id;

                                    return (
                                        <div
                                            key={item.branch.id}
                                            className={`rounded-lg border p-3 ${
                                                isSelected
                                                    ? 'border-primary/40 bg-primary/[0.03]'
                                                    : ''
                                            }`}
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <div>
                                                    <p className="font-medium">
                                                        {item.branch.code}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {item.branch.name}
                                                    </p>
                                                </div>
                                                <Warehouse className="size-4 text-muted-foreground" />
                                            </div>
                                            {product.tracking_type ===
                                            'serialized' ? (
                                                <>
                                                    <p className="mt-2 text-sm">
                                                        {item.assets.total} aset
                                                        ·{' '}
                                                        {item.assets.available}{' '}
                                                        tersedia ·{' '}
                                                        {item.assets.rented}{' '}
                                                        disewa
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Reservasi{' '}
                                                        {item.assets.reserved} ·
                                                        maintenance{' '}
                                                        {
                                                            item.assets
                                                                .maintenance
                                                        }{' '}
                                                        · transit{' '}
                                                        {item.assets.in_transit}{' '}
                                                        · hilang{' '}
                                                        {item.assets.lost}
                                                    </p>
                                                </>
                                            ) : (
                                                <>
                                                    <p className="mt-2 text-sm">
                                                        Stok{' '}
                                                        {
                                                            item.inventory
                                                                .quantity_on_hand
                                                        }{' '}
                                                        · disewa{' '}
                                                        {
                                                            item.inventory
                                                                .quantity_rented
                                                        }
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Reservasi{' '}
                                                        {
                                                            item.inventory
                                                                .quantity_reserved
                                                        }{' '}
                                                        · maintenance{' '}
                                                        {
                                                            item.inventory
                                                                .quantity_maintenance
                                                        }{' '}
                                                        · transfer{' '}
                                                        {
                                                            item.inventory
                                                                .quantity_in_transfer
                                                        }
                                                    </p>
                                                </>
                                            )}
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                        {permissions.manage && (
                            <Card className="border-destructive/30">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Arsipkan produk
                                    </CardTitle>
                                    <CardDescription>
                                        Ditolak otomatis bila masih dipakai
                                        aset, paket, booking, atau rental aktif.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Button
                                        variant="destructive"
                                        onClick={async () => {
                                            const confirmed = await confirm({
                                                title: 'Arsipkan produk?',
                                                description: `${product.name} tidak akan tersedia untuk transaksi baru. Histori tetap disimpan.`,
                                                confirmLabel: 'Arsipkan produk',
                                                variant: 'destructive',
                                            });

                                            if (!confirmed) {
                                                return;
                                            }

                                            router.delete(
                                                `/catalog/products/${product.id}`,
                                            );
                                        }}
                                    >
                                        <Trash2 />
                                        Arsipkan produk
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            <AssetSmartCalendarDialog
                key={calendarAsset?.id ?? 'asset-calendar'}
                open={calendarAsset !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCalendarAsset(null);
                    }
                }}
                asset={calendarAsset}
            />

            <ProductFormDialog
                open={productDialog}
                onOpenChange={setProductDialog}
                product={product}
                categories={categories}
            />
            <ProductRateFormDialog
                open={rateDialog}
                onOpenChange={setRateDialog}
                product={product}
                rate={editingRate}
                ratePlans={ratePlans}
                branches={branches}
                manageGlobal={permissions.manageGlobal}
            />
        </>
    );
}

function assetStatusLabel(status: string) {
    return (
        {
            available: 'Tersedia',
            reserved: 'Terbooking',
            rented: 'Tersewa',
            maintenance: 'Maintenance',
            in_transit: 'Dalam transfer',
            lost: 'Hilang',
        }[status] ?? status
    );
}

function Metric({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: string;
    icon: typeof Boxes;
}) {
    return (
        <Card>
            <CardContent className="flex items-center justify-between p-5">
                <div>
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {label}
                    </p>
                    <p className="mt-2 text-2xl font-semibold">{value}</p>
                </div>
                <div className="flex size-11 items-center justify-center rounded-xl bg-muted">
                    <Icon className="size-5" />
                </div>
            </CardContent>
        </Card>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 leading-6">{value}</p>
        </div>
    );
}

function EmptyRates() {
    return (
        <div className="py-14 text-center">
            <CircleDollarSign className="mx-auto size-9 text-muted-foreground" />
            <p className="mt-4 font-medium">Produk belum memiliki harga</p>
            <p className="mt-1 text-sm text-muted-foreground">
                Tambahkan harga berdasarkan rate plan dan scope cabang.
            </p>
        </div>
    );
}

function ProductErrors({ errors }: { errors: Record<string, string> }) {
    const message = errors.product ?? errors.branch_id ?? errors.rate_plan_id;

    if (typeof message !== 'string') {
        return null;
    }

    return (
        <Alert variant="destructive">
            <CircleOff />
            <AlertTitle>Perubahan produk ditolak</AlertTitle>
            <AlertDescription>{message}</AlertDescription>
        </Alert>
    );
}

function formatCurrency(value: string | number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
    }).format(new Date(value));
}
