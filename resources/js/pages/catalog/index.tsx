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
import { useGlobalLocale } from '@/lib/locale-store';
import { Stage3Text, stage3Translate } from '@/components/stage3-text';
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
import { FilterBar, FilterField } from '@/components/ui/filter-bar';
import { MetricCard } from '@/components/ui/metric-card';
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
    const stage3Locale = useGlobalLocale();
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
        : stage3Translate('stage3.ui.semua.cabang.yang.dapat.diakses.1b890', stage3Locale);

    return (
        <>
            <Head title={stage3Translate('stage3.ui.katalog.harga.3e128', stage3Locale)} />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            <Stage3Text k="stage3.ui.master.catalog.pricing.864e9" /></p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            <Stage3Text k="stage3.ui.katalog.harga.3e128" /></h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            <Stage3Text k="stage3.ui.kelola.produk.kategori.paket.rental.rate.plan.d.a85f0" /></p>
                    </div>
                    {permissions.manage && (
                        <div className="flex flex-wrap gap-2">
                            {permissions.manageGlobal && (
                                <Button asChild variant="outline">
                                    <Link href="/catalog/intelligence">
                                        <Sparkles />
                                        <Stage3Text k="stage3.ui.correction.catalog.intelligence.bbba2" /></Link>
                                </Button>
                            )}
                            <Button asChild variant="outline">
                                <Link href="/catalog/public-content">
                                    <Globe2 />
                                    <Stage3Text k="stage3.ui.konten.publik.cef46" /></Link>
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
                                <Stage3Text k="stage3.ui.tambah.a44eb" />{' '}
                                {filters.section === 'categories'
                                    ? stage3Translate('stage3.ui.correction.kategori.94487', stage3Locale)
                                    : filters.section === 'packages'
                                      ? stage3Translate('stage3.ui.correction.paket.04585', stage3Locale)
                                      : filters.section === 'rate-plans'
                                        ? stage3Translate('stage3.ui.correction.rate.plan.a81e7', stage3Locale)
                                        : stage3Translate('stage3.ui.correction.produk.0a511', stage3Locale)}
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
                            label: stage3Translate('stage3.ui.correction.master.produk.f35f0', stage3Locale),
                            value: summary.products,
                            icon: Boxes,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.siap.disewakan.c9572', stage3Locale),
                            value: summary.rentable,
                            icon: PackageCheck,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.per.unit.serial.cc205', stage3Locale),
                            value: summary.serialized,
                            icon: BoxIcon,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.paket.rental.bbc25', stage3Locale),
                            value: summary.packages,
                            icon: Layers3,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.belum.punya.harga.d0d52', stage3Locale),
                            value: summary.withoutRate,
                            icon: CircleDollarSign,
                        },
                    ].map(({ label, value, icon: Icon }) => (
                        <MetricCard
                            key={label}
                            label={label}
                            value={value}
                            icon={Icon}
                            tone={
                                label === stage3Translate('stage3.ui.correction.belum.punya.harga.d0d52', stage3Locale) &&
                                Number(value) > 0
                                    ? 'warning'
                                    : 'neutral'
                            }
                        />
                    ))}
                </section>

                <InventoryScopeSummary
                    summary={inventorySummary}
                    scopeLabel={branchScopeLabel}
                />

                <nav className="flex flex-wrap gap-2 rounded-xl border bg-card p-2">
                    {[
                        ['products', stage3Translate('stage3.ui.correction.produk.869eb', stage3Locale), Boxes],
                        ['categories', stage3Translate('stage3.ui.correction.kategori.b7964', stage3Locale), Tags],
                        ['packages', stage3Translate('stage3.ui.correction.paket.rental.11f40', stage3Locale), Layers3],
                        ['rate-plans', stage3Translate('stage3.ui.correction.rate.plan.3e998', stage3Locale), Timer],
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
    const stage3Locale = useGlobalLocale();

    return (
        <FilterBar
            title={stage3Translate('stage3.ui.lingkup.cabang.katalog.30074', stage3Locale)}
            description={stage3Translate('stage3.ui.master.produk.dan.kategori.tetap.global.pilihan.26c64', stage3Locale)}
            context={<Badge variant="outline"><Stage3Text k="stage3.ui.aktif.62c8b" />{scopeLabel}</Badge>}
            contentClassName="grid-cols-1 md:grid-cols-[minmax(260px,360px)]"
        >
            <FilterField label={stage3Translate('stage3.ui.cabang.operasional.2c54a', stage3Locale)}>
                <Select
                    value={branchId?.toString() ?? 'all'}
                    onValueChange={(value) =>
                        onChange(value === 'all' ? null : Number(value))
                    }
                >
                    <SelectTrigger aria-label={stage3Translate('stage3.ui.filter.cabang.katalog.72a03', stage3Locale)}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">
                            <Stage3Text k="stage3.ui.semua.cabang.yang.dapat.diakses.1b890" /></SelectItem>
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
            </FilterField>
        </FilterBar>
    );
}

function InventoryScopeSummary({
    summary,
    scopeLabel,
}: {
    summary: CatalogInventorySummary;
    scopeLabel: string;
}) {
    const stage3Locale = useGlobalLocale();
    const metrics = [
        { label: stage3Translate('stage3.ui.correction.aset.serialized.7dba4', stage3Locale), value: summary.assets, icon: Boxes },
        {
            label: stage3Translate('stage3.ui.correction.aset.tersedia.0418d', stage3Locale),
            value: summary.availableAssets,
            icon: PackageCheck,
        },
        { label: stage3Translate('stage3.ui.correction.aset.disewa.06754', stage3Locale), value: summary.rentedAssets, icon: BoxIcon },
        {
            label: stage3Translate('stage3.ui.correction.maintenance.transit.43451', stage3Locale),
            value: summary.maintenanceAssets + summary.inTransitAssets,
            icon: CircleOff,
        },
        {
            label: stage3Translate('stage3.ui.correction.stok.bulk.37225', stage3Locale),
            value: summary.quantityOnHand,
            icon: Layers3,
        },
    ];

    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-base font-semibold">
                    <Stage3Text k="stage3.ui.ringkasan.inventaris.per.cabang.3d9b6" /></h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {scopeLabel}
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                {metrics.map(({ label, value, icon: Icon }) => (
                    <MetricCard
                        key={label}
                        label={label}
                        value={value}
                        icon={Icon}
                    />
                ))}
            </div>
            <p className="text-xs leading-5 text-muted-foreground">
                <Stage3Text k="stage3.ui.bulk.reservasi.24b8c" />{summary.quantityReserved} <Stage3Text k="stage3.ui.disewa.1a778" />{' '}
                {summary.quantityRented} <Stage3Text k="stage3.ui.maintenance.7fb37" />{' '}
                {summary.quantityMaintenance} <Stage3Text k="stage3.ui.dalam.transfer.e021a" />{' '}
                {summary.quantityInTransfer}<Stage3Text k="stage3.ui.serialized.maintenance.d18c5" />{' '}
                {summary.maintenanceAssets} <Stage3Text k="stage3.ui.in.transit.b5077" />{' '}
                {summary.inTransitAssets}.
            </p>
        </section>
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
    const stage3Locale = useGlobalLocale();

    return (
        <Card>
            <CardHeader className="gap-2 px-5 pb-0 sm:px-6">
                <div>
                    <CardTitle><Stage3Text k="stage3.ui.master.produk.rental.fd03a" /></CardTitle>
                    <CardDescription>
                        <Stage3Text k="stage3.ui.master.produk.bersifat.global.angka.aset.stok.d.81c8b" />{branchScopeLabel}.
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
                            title={stage3Translate('stage3.ui.produk.tidak.ditemukan.ce695', stage3Locale)}
                            description={stage3Translate('stage3.ui.ubah.filter.atau.tambahkan.produk.baru.1004c', stage3Locale)}
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
                                                stage3Translate('stage3.ui.correction.tanpa.brand.61d4f', stage3Locale)
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
                                                        ? stage3Translate('stage3.ui.correction.aktif.89f29', stage3Locale)
                                                        : stage3Translate('stage3.ui.correction.nonaktif.60944', stage3Locale)}
                                                </Badge>
                                                {!product.is_rentable && (
                                                    <Badge variant="secondary">
                                                        <Stage3Text k="stage3.ui.tidak.disewakan.13719" /></Badge>
                                                )}
                                                {product.enrichment_status ===
                                                    'enriched' && (
                                                    <Badge variant="default">
                                                        <Stage3Text k="stage3.ui.correction.canonical.52e10" /></Badge>
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
                                                : stage3Translate('stage3.ui.correction.tanpa.kategori.a3fdc', stage3Locale)}
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
                                                <Stage3Text k="stage3.ui.tersedia.3c0c7" />{' '}
                                                {product.available_assets_count ??
                                                    0}{' '}
                                                <Stage3Text k="stage3.ui.disewa.1a778" />{' '}
                                                {product.rented_assets_count ??
                                                    0}{' '}
                                                <Stage3Text k="stage3.ui.maintenance.7fb37" />{' '}
                                                {product.maintenance_assets_count ??
                                                    0}{' '}
                                                <Stage3Text k="stage3.ui.in.transit.b5077" />{' '}
                                                {product.in_transit_assets_count ??
                                                    0}
                                            </p>
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                <Stage3Text k="stage3.ui.reservasi.4c764" />{' '}
                                                {Number(
                                                    product.quantity_reserved ??
                                                        0,
                                                )}{' '}
                                                <Stage3Text k="stage3.ui.disewa.1a778" />{' '}
                                                {Number(
                                                    product.quantity_rented ??
                                                        0,
                                                )}{' '}
                                                <Stage3Text k="stage3.ui.maintenance.7fb37" />{' '}
                                                {Number(
                                                    product.quantity_maintenance ??
                                                        0,
                                                )}{' '}
                                                <Stage3Text k="stage3.ui.transfer.1fd07" />{' '}
                                                {Number(
                                                    product.quantity_in_transfer ??
                                                        0,
                                                )}
                                            </p>
                                        )}
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {product.rates_count ?? 0} <Stage3Text k="stage3.ui.harga.09403" />{' '}
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
                                                <Stage3Text k="stage3.ui.detail.harga.1a7dd" /></Link>
                                        </Button>
                                        {permissions.manage && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => onEdit(product)}
                                            >
                                                <Pencil />
                                                <Stage3Text k="stage3.ui.edit.53016" /></Button>
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
    const stage3Locale = useGlobalLocale();

    return (
        <div
            data-slot="filter-grid"
            className="grid items-end gap-3 rounded-xl border bg-muted/25 p-4 md:grid-cols-2 2xl:grid-cols-[minmax(260px,1fr)_repeat(4,minmax(140px,auto))]"
        >
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
                        placeholder={stage3Translate('stage3.ui.cari.sku.produk.brand.model.dff08', stage3Locale)}
                    />
                </div>
                <Button
                    type="submit"
                    size="icon"
                    variant="outline"
                    aria-label={stage3Translate('stage3.ui.cari.produk.56310', stage3Locale)}
                >
                    <Search />
                </Button>
            </form>
            <FilterSelect
                value={filters.catalog_model_id?.toString() ?? 'all'}
                placeholder={
                    filters.catalog_brand_id
                        ? stage3Translate('stage3.ui.correction.semua.model.ef378', stage3Locale)
                        : stage3Translate('stage3.ui.correction.pilih.brand.dahulu.fa1b5', stage3Locale)
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
                placeholder={stage3Translate('stage3.ui.semua.kategori.3ee43', stage3Locale)}
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
                placeholder={stage3Translate('stage3.ui.semua.tracking.d8966', stage3Locale)}
                onValueChange={(tracking_type) =>
                    navigate('products', { tracking_type })
                }
                options={[
                    {
                        value: 'serialized',
                        label: stage3Translate('stage3.ui.correction.per.unit.serial.cc205', stage3Locale),
                    },
                    { value: 'bulk', label: 'Kuantitas / bulk' },
                ]}
            />
            <FilterSelect
                value={filters.status || 'all'}
                placeholder={stage3Translate('stage3.ui.semua.status.baa2a', stage3Locale)}
                onValueChange={(status) => navigate('products', { status })}
                options={[
                    { value: 'active', label: stage3Translate('stage3.ui.correction.aktif.89f29', stage3Locale) },
                    { value: 'inactive', label: stage3Translate('stage3.ui.correction.nonaktif.60944', stage3Locale) },
                    { value: 'rentable', label: stage3Translate('stage3.ui.correction.dapat.disewa.18f2a', stage3Locale) },
                    {
                        value: 'not-rentable',
                        label: stage3Translate('stage3.ui.correction.tidak.disewakan.13719', stage3Locale),
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
    const stage3Locale = useGlobalLocale();
    const confirm = useConfirmDialog();

    return (
        <Card>
            <CardHeader>
                <CardTitle><Stage3Text k="stage3.ui.hierarki.kategori.33bf2" /></CardTitle>
                <CardDescription>
                    <Stage3Text k="stage3.ui.kategori.yang.masih.memiliki.produk.atau.subkat.70506" /></CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {categories.length === 0 ? (
                    <EmptyState
                        icon={Tags}
                        title={stage3Translate('stage3.ui.belum.ada.kategori.6aca7', stage3Locale)}
                        description={stage3Translate('stage3.ui.tambahkan.kategori.pertama.untuk.menyusun.produ.1a645', stage3Locale)}
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
                                            ? stage3Translate('stage3.ui.correction.aktif.89f29', stage3Locale)
                                            : stage3Translate('stage3.ui.correction.nonaktif.60944', stage3Locale)}
                                    </Badge>
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {category.parent
                                        ? `Induk: ${category.parent.name}`
                                        : stage3Translate('stage3.ui.correction.kategori.utama.eda08', stage3Locale)}{' '}
                                    · {category.products_count ?? 0} <Stage3Text k="stage3.ui.produk.a5b80" />{' '}
                                    {category.children_count ?? 0} <Stage3Text k="stage3.ui.subkategori.dff75" /></p>
                            </div>
                            {permissions.manage && (
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => onEdit(category)}
                                    >
                                        <Pencil />
                                        <Stage3Text k="stage3.ui.edit.53016" /></Button>
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
                                                title: stage3Translate('stage3.ui.correction.arsipkan.kategori.d4a17', stage3Locale),
                                                description: stage3Translate('stage3.ui.correction.confirm.category.archive', stage3Locale, { name: category.name }),
                                                confirmLabel:
                                                    stage3Translate('stage3.ui.correction.confirm.category.archive.action', stage3Locale),
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
                                        <Stage3Text k="stage3.ui.arsipkan.5d7c1" /></Button>
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
    const stage3Locale = useGlobalLocale();

    return (
        <Card>
            <CardHeader>
                <CardTitle><Stage3Text k="stage3.ui.paket.rental.bbc25" /></CardTitle>
                <CardDescription>
                    <Stage3Text k="stage3.ui.kombinasi.produk.dengan.harga.paket.global.atau.2231b" /></CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {packages.length === 0 ? (
                    <EmptyState
                        icon={Layers3}
                        title={stage3Translate('stage3.ui.belum.ada.paket.3ef6e', stage3Locale)}
                        description={stage3Translate('stage3.ui.buat.paket.untuk.menjual.kombinasi.beberapa.pro.4c9f3', stage3Locale)}
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
                                                ? stage3Translate('stage3.ui.correction.aktif.89f29', stage3Locale)
                                                : stage3Translate('stage3.ui.correction.nonaktif.60944', stage3Locale)}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {rentalPackage.branch
                                            ? `${rentalPackage.branch.code} · ${rentalPackage.branch.name}`
                                            : stage3Translate('stage3.ui.correction.global.seluruh.cabang.b98dd', stage3Locale)}{' '}
                                        · {rentalPackage.items_count ?? 0} <Stage3Text k="stage3.ui.item.d7e17" />{rentalPackage.rates_count ?? 0} <Stage3Text k="stage3.ui.harga.619d7" /></p>
                                </div>
                                <div className="flex gap-2">
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={`/catalog/packages/${rentalPackage.id}`}
                                        >
                                            <Stage3Text k="stage3.ui.detail.paket.028b3" /></Link>
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
                                            <Stage3Text k="stage3.ui.edit.53016" /></Button>
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
    const stage3Locale = useGlobalLocale();

    return (
        <Card>
            <CardHeader>
                <CardTitle><Stage3Text k="stage3.ui.rate.plan.31b1c" /></CardTitle>
                <CardDescription>
                    <Stage3Text k="stage3.ui.standar.durasi.seperti.6.jam.12.jam.satu.hari.a.a8b49" /></CardDescription>
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
                                            ? stage3Translate('stage3.ui.correction.aktif.89f29', stage3Locale)
                                            : stage3Translate('stage3.ui.correction.nonaktif.60944', stage3Locale)}
                                    </Badge>
                                </div>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {ratePlan.duration_value}{' '}
                                    {durationLabel(ratePlan.duration_unit, stage3Locale)} <Stage3Text k="stage3.ui.grace.0b30e" />{ratePlan.grace_period_minutes} <Stage3Text k="stage3.ui.menit.3ac0d" />{' '}
                                    {ratePlan.branch
                                        ? ratePlan.branch.code
                                        : stage3Translate('stage3.ui.correction.global.5f118', stage3Locale)}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {ratePlan.product_rates_count ?? 0} <Stage3Text k="stage3.ui.harga.produk.bd0da" />{ratePlan.package_rates_count ?? 0}{' '}
                                    <Stage3Text k="stage3.ui.harga.paket.9ca88" /></p>
                            </div>
                            {canManage && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => onEdit(ratePlan)}
                                >
                                    <Pencil />
                                    <Stage3Text k="stage3.ui.edit.53016" /></Button>
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
    const stage3Locale = useGlobalLocale();
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
                aria-label={stage3Translate('stage3.ui.filter.produk.berdasarkan.brand.84491', stage3Locale)}
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
                            <Stage3Text k="stage3.ui.semua.brand.a34bf" /></span>
                        <span className="block text-xs text-muted-foreground">
                            {totalProducts} <Stage3Text k="stage3.ui.produk.0a511" /></span>
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
                                {brand.products_count ?? 0} <Stage3Text k="stage3.ui.produk.0a511" /></span>
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
            <AlertTitle><Stage3Text k="stage3.ui.perubahan.katalog.ditolak.90a29" /></AlertTitle>
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

function durationLabel(unit: RatePlan['duration_unit'], locale: 'id' | 'en') {
    const id = { minute: 'menit', hour: 'jam', day: 'hari', week: 'minggu', month: 'bulan' };
    const en = { minute: 'minutes', hour: 'hours', day: 'days', week: 'weeks', month: 'months' };

    return (locale === 'en' ? en : id)[unit];
}
