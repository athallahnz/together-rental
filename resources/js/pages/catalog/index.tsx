import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Boxes,
    BoxIcon,
    CircleDollarSign,
    CircleOff,
    Globe2,
    Layers3,
    PackageCheck,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Tags,
    Timer,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { BrandMark } from '@/components/catalog/brand-mark';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import {
    CategoryFormDialog,
    PackageFormDialog,
    ProductFormDialog,
    RatePlanFormDialog,
} from '@/components/catalog/catalog-dialogs';
import { PaginationLinks } from '@/components/pagination-links';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AccessBranch,
    CatalogBrand,
    CatalogInventorySummary,
    CatalogModel,
    CatalogPermissions,
    Pagination,
    Product,
    ProductCategory,
    RatePlan,
    RentalPackage,
} from '@/types';

type Section = 'products' | 'categories' | 'packages' | 'rate-plans';

type Props = {
    products: Pagination<Product>;
    categories: ProductCategory[];
    brands: CatalogBrand[];
    models: CatalogModel[];
    ratePlans: RatePlan[];
    packages: RentalPackage[];
    summary: {
        products: number;
        rentable: number;
        serialized: number;
        packages: number;
        withoutRate: number;
    };
    inventorySummary: CatalogInventorySummary;
    filters: {
        search: string;
        category_id: number | null;
        catalog_brand_id: number | null;
        catalog_model_id: number | null;
        tracking_type: string;
        status: string;
        section: Section;
        branch_id: number | null;
    };
    branches: AccessBranch[];
    permissions: CatalogPermissions;
};

export default function CatalogIndex({
    products,
    categories,
    brands,
    models,
    ratePlans,
    packages,
    summary,
    inventorySummary,
    filters,
    branches,
    permissions,
}: Props) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const [productDialog, setProductDialog] = useState(false);
    const [categoryDialog, setCategoryDialog] = useState(false);
    const [ratePlanDialog, setRatePlanDialog] = useState(false);
    const [packageDialog, setPackageDialog] = useState(false);
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [editingCategory, setEditingCategory] =
        useState<ProductCategory | null>(null);
    const [editingRatePlan, setEditingRatePlan] = useState<RatePlan | null>(
        null,
    );
    const [editingPackage, setEditingPackage] = useState<RentalPackage | null>(
        null,
    );

    const navigate = (
        section: Section,
        next: Partial<{
            category_id: number | null;
            catalog_brand_id: number | null;
            catalog_model_id: number | null;
            tracking_type: string;
            status: string;
            branch_id: number | null;
        }> = {},
    ) => {
        const categoryId =
            next.category_id === null
                ? undefined
                : (next.category_id ?? filters.category_id ?? undefined);
        const tracking = next.tracking_type ?? filters.tracking_type;
        const status = next.status ?? filters.status;
        const catalogBrandId =
            next.catalog_brand_id === null
                ? undefined
                : (next.catalog_brand_id ??
                  filters.catalog_brand_id ??
                  undefined);
        const catalogModelId =
            next.catalog_model_id === null
                ? undefined
                : (next.catalog_model_id ??
                  filters.catalog_model_id ??
                  undefined);
        const branchId =
            next.branch_id === null
                ? undefined
                : (next.branch_id ?? filters.branch_id ?? undefined);

        router.get(
            '/catalog',
            {
                section,
                search: search || undefined,
                category_id: categoryId,
                catalog_brand_id: catalogBrandId,
                catalog_model_id: catalogModelId,
                tracking_type:
                    tracking === 'all' ? undefined : tracking || undefined,
                status: status === 'all' ? undefined : status || undefined,
                branch_id: branchId,
            },
            { preserveState: true, replace: true },
        );
    };

    const openProduct = (product: Product | null) => {
        setEditingProduct(product);
        setProductDialog(true);
    };
    const openCategory = (category: ProductCategory | null) => {
        setEditingCategory(category);
        setCategoryDialog(true);
    };
    const openRatePlan = (ratePlan: RatePlan | null) => {
        setEditingRatePlan(ratePlan);
        setRatePlanDialog(true);
    };
    const openPackage = (rentalPackage: RentalPackage | null) => {
        setEditingPackage(rentalPackage);
        setPackageDialog(true);
    };
    const selectedBranch =
        branches.find((branch) => branch.id === filters.branch_id) ?? null;
    const branchScopeLabel = selectedBranch
        ? `${selectedBranch.code} · ${selectedBranch.name}`
        : 'Semua cabang yang dapat diakses';

    return (
        <>
            <Head title="Katalog & Harga" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Master Catalog & Pricing
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Katalog & Harga
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kelola produk, kategori, paket rental, rate plan,
                            dan harga global maupun khusus cabang dalam satu
                            sumber data.
                        </p>
                    </div>
                    {permissions.manage && (
                        <div className="flex flex-wrap gap-2">
                            {permissions.manageGlobal && (
                                <Button asChild variant="outline">
                                    <Link href="/catalog/intelligence">
                                        <Sparkles />
                                        Catalog Intelligence
                                    </Link>
                                </Button>
                            )}
                            <Button asChild variant="outline">
                                <Link href="/catalog/public-content">
                                    <Globe2 />
                                    Konten Publik
                                </Link>
                            </Button>
                            <Button
                                onClick={() => {
                                    if (filters.section === 'categories') {
                                        openCategory(null);
                                    } else if (filters.section === 'packages') {
                                        openPackage(null);
                                    } else if (
                                        filters.section === 'rate-plans'
                                    ) {
                                        openRatePlan(null);
                                    } else {
                                        openProduct(null);
                                    }
                                }}
                            >
                                <Plus />
                                Tambah{' '}
                                {filters.section === 'categories'
                                    ? 'kategori'
                                    : filters.section === 'packages'
                                      ? 'paket'
                                      : filters.section === 'rate-plans'
                                        ? 'rate plan'
                                        : 'produk'}
                            </Button>
                        </div>
                    )}
                </header>

                <CatalogErrors errors={errors} />

                <BranchScopeFilter
                    branches={branches}
                    branchId={filters.branch_id}
                    scopeLabel={branchScopeLabel}
                    onChange={(branchId) =>
                        navigate(filters.section, { branch_id: branchId })
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        {
                            label: 'Master produk',
                            value: summary.products,
                            icon: Boxes,
                        },
                        {
                            label: 'Siap disewakan',
                            value: summary.rentable,
                            icon: PackageCheck,
                        },
                        {
                            label: 'Per unit / serial',
                            value: summary.serialized,
                            icon: BoxIcon,
                        },
                        {
                            label: 'Paket rental',
                            value: summary.packages,
                            icon: Layers3,
                        },
                        {
                            label: 'Belum punya harga',
                            value: summary.withoutRate,
                            icon: CircleDollarSign,
                        },
                    ].map(({ label, value, icon: Icon }) => (
                        <Card key={label}>
                            <CardContent className="flex items-center justify-between p-5">
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {label}
                                    </p>
                                    <p className="mt-2 text-3xl font-semibold">
                                        {value}
                                    </p>
                                </div>
                                <div className="flex size-11 items-center justify-center rounded-xl bg-muted">
                                    <Icon className="size-5" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <InventoryScopeSummary
                    summary={inventorySummary}
                    scopeLabel={branchScopeLabel}
                />

                <nav className="flex flex-wrap gap-2 rounded-xl border bg-card p-2">
                    {[
                        ['products', 'Produk', Boxes],
                        ['categories', 'Kategori', Tags],
                        ['packages', 'Paket Rental', Layers3],
                        ['rate-plans', 'Rate Plan', Timer],
                    ].map(([section, label, Icon]) => (
                        <Button
                            key={section as string}
                            variant={
                                filters.section === section
                                    ? 'default'
                                    : 'ghost'
                            }
                            onClick={() => navigate(section as Section)}
                        >
                            <Icon />
                            {label as string}
                        </Button>
                    ))}
                </nav>

                {filters.section === 'products' && (
                    <ProductsSection
                        products={products}
                        categories={categories}
                        brands={brands}
                        models={models}
                        filters={filters}
                        search={search}
                        setSearch={setSearch}
                        navigate={navigate}
                        permissions={permissions}
                        branchId={filters.branch_id}
                        branchScopeLabel={branchScopeLabel}
                        onEdit={openProduct}
                    />
                )}
                {filters.section === 'categories' && (
                    <CategoriesSection
                        categories={categories}
                        permissions={permissions}
                        onEdit={openCategory}
                    />
                )}
                {filters.section === 'packages' && (
                    <PackagesSection
                        packages={packages}
                        permissions={permissions}
                        onEdit={openPackage}
                    />
                )}
                {filters.section === 'rate-plans' && (
                    <RatePlansSection
                        ratePlans={ratePlans}
                        permissions={permissions}
                        onEdit={openRatePlan}
                    />
                )}
            </div>

            <ProductFormDialog
                open={productDialog}
                onOpenChange={setProductDialog}
                product={editingProduct}
                categories={categories.filter((category) => category.is_active)}
            />
            <CategoryFormDialog
                open={categoryDialog}
                onOpenChange={setCategoryDialog}
                category={editingCategory}
                categories={categories}
            />
            <RatePlanFormDialog
                open={ratePlanDialog}
                onOpenChange={setRatePlanDialog}
                ratePlan={editingRatePlan}
                branches={branches}
                manageGlobal={permissions.manageGlobal}
            />
            <PackageFormDialog
                open={packageDialog}
                onOpenChange={setPackageDialog}
                rentalPackage={editingPackage}
                branches={branches}
                manageGlobal={permissions.manageGlobal}
            />
        </>
    );
}

function BranchScopeFilter({
    branches,
    branchId,
    scopeLabel,
    onChange,
}: {
    branches: AccessBranch[];
    branchId: number | null;
    scopeLabel: string;
    onChange: (branchId: number | null) => void;
}) {
    return (
        <Card className="border-primary/20 bg-primary/[0.02]">
            <CardContent className="grid gap-4 p-4 md:grid-cols-[minmax(0,1fr)_minmax(260px,360px)] md:items-center md:p-5">
                <div>
                    <p className="text-sm font-semibold">
                        Lingkup cabang katalog
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Master produk dan kategori tetap global. Filter ini
                        mengubah ringkasan aset, stok, harga, paket, dan rate
                        plan sesuai cabang yang dipilih.
                    </p>
                    <p className="mt-2 text-xs font-medium text-primary">
                        Aktif: {scopeLabel}
                    </p>
                </div>
                <Select
                    value={branchId?.toString() ?? 'all'}
                    onValueChange={(value) =>
                        onChange(value === 'all' ? null : Number(value))
                    }
                >
                    <SelectTrigger aria-label="Filter cabang katalog">
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
    );
}

function InventoryScopeSummary({
    summary,
    scopeLabel,
}: {
    summary: CatalogInventorySummary;
    scopeLabel: string;
}) {
    const metrics = [
        { label: 'Aset serialized', value: summary.assets, icon: Boxes },
        {
            label: 'Aset tersedia',
            value: summary.availableAssets,
            icon: PackageCheck,
        },
        { label: 'Aset disewa', value: summary.rentedAssets, icon: BoxIcon },
        {
            label: 'Maintenance / transit',
            value: summary.maintenanceAssets + summary.inTransitAssets,
            icon: CircleOff,
        },
        {
            label: 'Stok bulk',
            value: summary.quantityOnHand,
            icon: Layers3,
        },
    ];

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-base">
                    Ringkasan inventaris per cabang
                </CardTitle>
                <CardDescription>{scopeLabel}</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                {metrics.map(({ label, value, icon: Icon }) => (
                    <div key={label} className="rounded-lg border p-3">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-xs font-medium text-muted-foreground">
                                {label}
                            </p>
                            <Icon className="size-4 text-muted-foreground" />
                        </div>
                        <p className="mt-2 text-2xl font-semibold">{value}</p>
                    </div>
                ))}
                <div className="sm:col-span-2 xl:col-span-5">
                    <p className="text-xs text-muted-foreground">
                        Bulk: reservasi {summary.quantityReserved} · disewa{' '}
                        {summary.quantityRented} · maintenance{' '}
                        {summary.quantityMaintenance} · dalam transfer{' '}
                        {summary.quantityInTransfer}. Serialized: maintenance{' '}
                        {summary.maintenanceAssets} · in transit{' '}
                        {summary.inTransitAssets}.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}

function ProductsSection({
    products,
    categories,
    brands,
    models,
    filters,
    search,
    setSearch,
    navigate,
    permissions,
    branchId,
    branchScopeLabel,
    onEdit,
}: {
    products: Pagination<Product>;
    categories: ProductCategory[];
    brands: CatalogBrand[];
    models: CatalogModel[];
    filters: Props['filters'];
    search: string;
    setSearch: (value: string) => void;
    navigate: (
        section: Section,
        next?: Partial<{
            category_id: number | null;
            catalog_brand_id: number | null;
            catalog_model_id: number | null;
            tracking_type: string;
            status: string;
            branch_id: number | null;
        }>,
    ) => void;
    permissions: CatalogPermissions;
    branchId: number | null;
    branchScopeLabel: string;
    onEdit: (product: Product) => void;
}) {
    return (
        <Card>
            <CardHeader className="gap-2 px-5 pb-0 sm:px-6">
                <div>
                    <CardTitle>Master produk rental</CardTitle>
                    <CardDescription>
                        Master produk bersifat global. Angka aset, stok, dan
                        harga di bawah mengikuti lingkup {branchScopeLabel}.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent
                className={`grid gap-5 px-5 pb-6 sm:px-6 lg:items-start ${
                    brands.length > 0
                        ? 'lg:grid-cols-[15rem_minmax(0,1fr)] xl:grid-cols-[17rem_minmax(0,1fr)]'
                        : ''
                }`}
            >
                {brands.length > 0 && (
                    <BrandFilterNavigation
                        brands={brands}
                        activeBrandId={filters.catalog_brand_id}
                        onChange={(catalogBrandId) =>
                            navigate('products', {
                                catalog_brand_id: catalogBrandId,
                                catalog_model_id: null,
                            })
                        }
                    />
                )}
                <div className="grid min-w-0 gap-4">
                    <ProductFilterControls
                        categories={categories}
                        models={models}
                        filters={filters}
                        search={search}
                        setSearch={setSearch}
                        navigate={navigate}
                    />
                    {products.data.length === 0 ? (
                        <EmptyState
                            icon={Boxes}
                            title="Produk tidak ditemukan"
                            description="Ubah filter atau tambahkan produk baru."
                        />
                    ) : (
                        <div className="grid gap-3">
                            {products.data.map((product) => (
                                <article
                                    key={product.id}
                                    className="grid gap-4 rounded-xl border p-4 sm:p-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto] xl:items-center"
                                >
                                    <div className="flex min-w-0 items-start gap-3">
                                        <BrandMark
                                            name={
                                                product.catalog_brand?.name ??
                                                product.brand ??
                                                'Tanpa brand'
                                            }
                                            logoUrl={
                                                product.catalog_brand?.logo_url
                                            }
                                        />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={`/catalog/products/${product.id}${branchId ? `?branch_id=${branchId}` : ''}`}
                                                    className="truncate font-semibold hover:underline"
                                                >
                                                    {product.name}
                                                </Link>
                                                <Badge
                                                    variant={
                                                        product.is_active
                                                            ? 'outline'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {product.is_active
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </Badge>
                                                {!product.is_rentable && (
                                                    <Badge variant="secondary">
                                                        Tidak disewakan
                                                    </Badge>
                                                )}
                                                {product.enrichment_status ===
                                                    'enriched' && (
                                                    <Badge variant="default">
                                                        Canonical
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                {product.sku}
                                            </p>
                                            <p className="mt-2 truncate text-sm text-muted-foreground">
                                                {[
                                                    product.catalog_brand
                                                        ?.name ?? product.brand,
                                                    product.catalog_model
                                                        ?.name ?? product.model,
                                                    product.variant,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') ||
                                                    'Brand dan model belum diisi'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="grid gap-2 text-sm">
                                        <p>
                                            {product.category
                                                ? `${product.category.code} · ${product.category.name}`
                                                : 'Tanpa kategori'}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {product.tracking_type ===
                                            'serialized'
                                                ? `Per unit / serial · ${product.assets_count ?? 0} aset`
                                                : `Kuantitas / bulk · stok ${Number(product.quantity_on_hand ?? 0)}`}
                                        </p>
                                        {product.tracking_type ===
                                        'serialized' ? (
                                            <p className="text-xs text-muted-foreground">
                                                tersedia{' '}
                                                {product.available_assets_count ??
                                                    0}{' '}
                                                · disewa{' '}
                                                {product.rented_assets_count ??
                                                    0}{' '}
                                                · maintenance{' '}
                                                {product.maintenance_assets_count ??
                                                    0}{' '}
                                                · in transit{' '}
                                                {product.in_transit_assets_count ??
                                                    0}
                                            </p>
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                reservasi{' '}
                                                {Number(
                                                    product.quantity_reserved ??
                                                        0,
                                                )}{' '}
                                                · disewa{' '}
                                                {Number(
                                                    product.quantity_rented ??
                                                        0,
                                                )}{' '}
                                                · maintenance{' '}
                                                {Number(
                                                    product.quantity_maintenance ??
                                                        0,
                                                )}{' '}
                                                · transfer{' '}
                                                {Number(
                                                    product.quantity_in_transfer ??
                                                        0,
                                                )}
                                            </p>
                                        )}
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {product.rates_count ?? 0} harga ·{' '}
                                            {branchScopeLabel}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2 xl:justify-end">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={`/catalog/products/${product.id}${branchId ? `?branch_id=${branchId}` : ''}`}
                                            >
                                                Detail & harga
                                            </Link>
                                        </Button>
                                        {permissions.manage && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => onEdit(product)}
                                            >
                                                <Pencil />
                                                Edit
                                            </Button>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                    <PaginationLinks
                        links={products.links}
                        from={products.from}
                        to={products.to}
                        total={products.total}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function ProductFilterControls({
    categories,
    models,
    filters,
    search,
    setSearch,
    navigate,
}: {
    categories: ProductCategory[];
    models: CatalogModel[];
    filters: Props['filters'];
    search: string;
    setSearch: (value: string) => void;
    navigate: (
        section: Section,
        next?: Partial<{
            category_id: number | null;
            catalog_brand_id: number | null;
            catalog_model_id: number | null;
            tracking_type: string;
            status: string;
            branch_id: number | null;
        }>,
    ) => void;
}) {
    return (
        <div className="grid gap-3 rounded-xl border bg-muted/15 p-3 sm:p-4 md:grid-cols-2 2xl:grid-cols-[minmax(260px,1fr)_repeat(4,minmax(140px,auto))]">
            <form
                className="flex gap-2 md:col-span-2 2xl:col-span-1"
                onSubmit={(event) => {
                    event.preventDefault();
                    navigate('products');
                }}
            >
                <div className="relative flex-1">
                    <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="pl-9"
                        placeholder="Cari SKU, produk, brand, model"
                    />
                </div>
                <Button
                    type="submit"
                    size="icon"
                    variant="outline"
                    aria-label="Cari produk"
                >
                    <Search />
                </Button>
            </form>
            <FilterSelect
                value={filters.catalog_model_id?.toString() ?? 'all'}
                placeholder={
                    filters.catalog_brand_id
                        ? 'Semua model'
                        : 'Pilih brand dahulu'
                }
                disabled={!filters.catalog_brand_id}
                onValueChange={(value) =>
                    navigate('products', {
                        catalog_model_id:
                            value === 'all' ? null : Number(value),
                    })
                }
                options={models.map((model) => ({
                    value: model.id.toString(),
                    label: `${model.name} (${model.products_count ?? 0})`,
                }))}
            />
            <FilterSelect
                value={filters.category_id?.toString() ?? 'all'}
                placeholder="Semua kategori"
                onValueChange={(value) =>
                    navigate('products', {
                        category_id: value === 'all' ? null : Number(value),
                    })
                }
                options={categories.map((category) => ({
                    value: category.id.toString(),
                    label: category.name,
                }))}
            />
            <FilterSelect
                value={filters.tracking_type || 'all'}
                placeholder="Semua tracking"
                onValueChange={(tracking_type) =>
                    navigate('products', { tracking_type })
                }
                options={[
                    {
                        value: 'serialized',
                        label: 'Per unit / serial',
                    },
                    { value: 'bulk', label: 'Kuantitas / bulk' },
                ]}
            />
            <FilterSelect
                value={filters.status || 'all'}
                placeholder="Semua status"
                onValueChange={(status) => navigate('products', { status })}
                options={[
                    { value: 'active', label: 'Aktif' },
                    { value: 'inactive', label: 'Nonaktif' },
                    { value: 'rentable', label: 'Dapat disewa' },
                    {
                        value: 'not-rentable',
                        label: 'Tidak disewakan',
                    },
                ]}
            />
        </div>
    );
}

function CategoriesSection({
    categories,
    permissions,
    onEdit,
}: {
    categories: ProductCategory[];
    permissions: CatalogPermissions;
    onEdit: (category: ProductCategory) => void;
}) {
    const confirm = useConfirmDialog();

    return (
        <Card>
            <CardHeader>
                <CardTitle>Hierarki kategori</CardTitle>
                <CardDescription>
                    Kategori yang masih memiliki produk atau subkategori tidak
                    dapat diarsipkan.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {categories.length === 0 ? (
                    <EmptyState
                        icon={Tags}
                        title="Belum ada kategori"
                        description="Tambahkan kategori pertama untuk menyusun produk."
                    />
                ) : (
                    categories.map((category) => (
                        <article
                            key={category.id}
                            className="flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-center md:justify-between"
                        >
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-semibold">
                                        {category.name}
                                    </p>
                                    <Badge variant="outline">
                                        {category.code}
                                    </Badge>
                                    <Badge
                                        variant={
                                            category.is_active
                                                ? 'outline'
                                                : 'secondary'
                                        }
                                    >
                                        {category.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}
                                    </Badge>
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {category.parent
                                        ? `Induk: ${category.parent.name}`
                                        : 'Kategori utama'}{' '}
                                    · {category.products_count ?? 0} produk ·{' '}
                                    {category.children_count ?? 0} subkategori
                                </p>
                            </div>
                            {permissions.manage && (
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => onEdit(category)}
                                    >
                                        <Pencil />
                                        Edit
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        disabled={
                                            (category.products_count ?? 0) >
                                                0 ||
                                            (category.children_count ?? 0) > 0
                                        }
                                        onClick={async () => {
                                            const confirmed = await confirm({
                                                title: 'Arsipkan kategori?',
                                                description: `${category.name} tidak akan tersedia untuk pengelompokan produk baru.`,
                                                confirmLabel:
                                                    'Arsipkan kategori',
                                                variant: 'destructive',
                                            });

                                            if (!confirmed) {
                                                return;
                                            }

                                            router.delete(
                                                `/catalog/categories/${category.id}`,
                                                {
                                                    preserveScroll: true,
                                                },
                                            );
                                        }}
                                    >
                                        <Trash2 />
                                        Arsipkan
                                    </Button>
                                </div>
                            )}
                        </article>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function PackagesSection({
    packages,
    permissions,
    onEdit,
}: {
    packages: RentalPackage[];
    permissions: CatalogPermissions;
    onEdit: (rentalPackage: RentalPackage) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Paket rental</CardTitle>
                <CardDescription>
                    Kombinasi produk dengan harga paket global atau per cabang.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {packages.length === 0 ? (
                    <EmptyState
                        icon={Layers3}
                        title="Belum ada paket"
                        description="Buat paket untuk menjual kombinasi beberapa produk."
                    />
                ) : (
                    packages.map((rentalPackage) => {
                        const canManage =
                            permissions.manage &&
                            (rentalPackage.branch_id !== null ||
                                permissions.manageGlobal);

                        return (
                            <article
                                key={rentalPackage.id}
                                className="grid gap-4 rounded-xl border p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                            >
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link
                                            href={`/catalog/packages/${rentalPackage.id}`}
                                            className="font-semibold hover:underline"
                                        >
                                            {rentalPackage.name}
                                        </Link>
                                        <Badge variant="outline">
                                            {rentalPackage.code}
                                        </Badge>
                                        <Badge
                                            variant={
                                                rentalPackage.is_active
                                                    ? 'outline'
                                                    : 'secondary'
                                            }
                                        >
                                            {rentalPackage.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {rentalPackage.branch
                                            ? `${rentalPackage.branch.code} · ${rentalPackage.branch.name}`
                                            : 'Global seluruh cabang'}{' '}
                                        · {rentalPackage.items_count ?? 0} item
                                        · {rentalPackage.rates_count ?? 0} harga
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={`/catalog/packages/${rentalPackage.id}`}
                                        >
                                            Detail paket
                                        </Link>
                                    </Button>
                                    {canManage && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                onEdit(rentalPackage)
                                            }
                                        >
                                            <Pencil />
                                            Edit
                                        </Button>
                                    )}
                                </div>
                            </article>
                        );
                    })
                )}
            </CardContent>
        </Card>
    );
}

function RatePlansSection({
    ratePlans,
    permissions,
    onEdit,
}: {
    ratePlans: RatePlan[];
    permissions: CatalogPermissions;
    onEdit: (ratePlan: RatePlan) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Rate plan</CardTitle>
                <CardDescription>
                    Standar durasi seperti 6 jam, 12 jam, satu hari, atau skema
                    cabang khusus.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {ratePlans.map((ratePlan) => {
                    const canManage =
                        permissions.manage &&
                        (ratePlan.branch_id !== null ||
                            permissions.manageGlobal);

                    return (
                        <article
                            key={ratePlan.id}
                            className="grid gap-4 rounded-xl border p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                        >
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-semibold">
                                        {ratePlan.name}
                                    </p>
                                    <Badge variant="outline">
                                        {ratePlan.code}
                                    </Badge>
                                    <Badge
                                        variant={
                                            ratePlan.is_active
                                                ? 'outline'
                                                : 'secondary'
                                        }
                                    >
                                        {ratePlan.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}
                                    </Badge>
                                </div>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {ratePlan.duration_value}{' '}
                                    {durationLabel(ratePlan.duration_unit)} ·
                                    grace {ratePlan.grace_period_minutes} menit
                                    ·{' '}
                                    {ratePlan.branch
                                        ? ratePlan.branch.code
                                        : 'Global'}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {ratePlan.product_rates_count ?? 0} harga
                                    produk · {ratePlan.package_rates_count ?? 0}{' '}
                                    harga paket
                                </p>
                            </div>
                            {canManage && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => onEdit(ratePlan)}
                                >
                                    <Pencil />
                                    Edit
                                </Button>
                            )}
                        </article>
                    );
                })}
            </CardContent>
        </Card>
    );
}

function FilterSelect({
    value,
    placeholder,
    options,
    onValueChange,
    disabled = false,
}: {
    value: string;
    placeholder: string;
    options: { value: string; label: string }[];
    onValueChange: (value: string) => void;
    disabled?: boolean;
}) {
    return (
        <Select value={value} onValueChange={onValueChange} disabled={disabled}>
            <SelectTrigger>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{placeholder}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function BrandFilterNavigation({
    brands,
    activeBrandId,
    onChange,
}: {
    brands: CatalogBrand[];
    activeBrandId: number | null;
    onChange: (brandId: number | null) => void;
}) {
    const totalProducts = brands.reduce(
        (total, brand) => total + (brand.products_count ?? 0),
        0,
    );

    return (
        <aside className="min-w-0 lg:sticky lg:top-4">
            {/* <p className="mb-2 px-1 text-xs font-medium tracking-wide text-muted-foreground uppercase lg:px-0">
                Filter brand
            </p> */}
            <nav
                aria-label="Filter produk berdasarkan brand"
                className="flex gap-3 overflow-x-auto overscroll-contain rounded-xl border bg-muted/15 p-3 pb-4 lg:max-h-[calc(100vh-13rem)] lg:flex-col lg:gap-2 lg:overflow-x-hidden lg:overflow-y-auto lg:p-3"
            >
                <button
                    type="button"
                    onClick={() => onChange(null)}
                    aria-pressed={activeBrandId === null}
                    className={`flex min-w-[10rem] shrink-0 items-center gap-3 rounded-xl border px-4 py-3 text-left transition hover:bg-muted/60 lg:w-full lg:min-w-0 ${
                        activeBrandId === null
                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                            : 'bg-background'
                    }`}
                >
                    <BrandMark name="Semua brand" className="size-10" />
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-medium">
                            Semua brand
                        </span>
                        <span className="block text-xs text-muted-foreground">
                            {totalProducts} produk
                        </span>
                    </span>
                </button>
                {brands.map((brand) => (
                    <button
                        key={brand.id}
                        type="button"
                        onClick={() => onChange(brand.id)}
                        aria-pressed={activeBrandId === brand.id}
                        className={`flex min-w-[10rem] shrink-0 items-center gap-3 rounded-xl border px-4 py-3 text-left transition hover:bg-muted/60 lg:w-full lg:min-w-0 ${
                            activeBrandId === brand.id
                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                : 'bg-background'
                        }`}
                    >
                        <BrandMark
                            name={brand.name}
                            logoUrl={brand.logo_url}
                            className="size-10"
                        />
                        <span className="min-w-0">
                            <span className="block truncate text-sm font-medium">
                                {brand.name}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                {brand.products_count ?? 0} produk
                            </span>
                        </span>
                    </button>
                ))}
            </nav>
        </aside>
    );
}

function CatalogErrors({ errors }: { errors: Record<string, string> }) {
    const message =
        errors.product ?? errors.category ?? errors.package ?? errors.branch_id;

    if (typeof message !== 'string') {
        return null;
    }

    return (
        <Alert variant="destructive">
            <CircleOff />
            <AlertTitle>Perubahan katalog ditolak</AlertTitle>
            <AlertDescription>{message}</AlertDescription>
        </Alert>
    );
}

function EmptyState({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof Boxes;
    title: string;
    description: string;
}) {
    return (
        <div className="py-16 text-center">
            <Icon className="mx-auto size-9 text-muted-foreground" />
            <p className="mt-4 font-medium">{title}</p>
            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

function durationLabel(unit: RatePlan['duration_unit']) {
    return {
        minute: 'menit',
        hour: 'jam',
        day: 'hari',
        week: 'minggu',
        month: 'bulan',
    }[unit];
}
