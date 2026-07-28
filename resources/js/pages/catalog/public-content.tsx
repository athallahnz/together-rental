import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Eye,
    FileImage,
    Globe2,
    PackageCheck,
    Pencil,
    Search,
    Sparkles,
    Tags,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { PaginationLinks } from '@/components/pagination-links';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Pagination } from '@/types';

type Readiness = {
    score: number;
    ready: boolean;
    checks: Record<string, boolean>;
};

type PublicProductContent = {
    id: number;
    sku: string;
    name: string;
    slug: string;
    is_active: boolean;
    is_rentable: boolean;
    is_public: boolean;
    is_featured: boolean;
    public_sort_order: number;
    short_description: string | null;
    primary_image_path: string | null;
    image_url: string | null;
    gallery: string[];
    seo_title: string | null;
    seo_description: string | null;
    category: string | null;
    brand: string | null;
    active_rates_count: number;
    active_assets_count: number;
    readiness: Readiness;
};

type PublicPackageContent = {
    id: number;
    code: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    is_public: boolean;
    is_featured: boolean;
    public_sort_order: number;
    primary_image_path: string | null;
    image_url: string | null;
    seo_title: string | null;
    seo_description: string | null;
    branch: { code: string; name: string } | null;
    items_count: number;
    active_rates_count: number;
    readiness: Readiness;
};

type PublicCategoryContent = {
    id: number;
    name: string;
    code: string;
    slug: string;
    description: string | null;
    icon: string | null;
    image_path: string | null;
    image_url: string | null;
    is_active: boolean;
    is_public: boolean;
    sort_order: number;
    products_count: number;
};

type PublicBrandContent = {
    id: number;
    name: string;
    slug: string;
    logo_path: string | null;
    logo_url: string | null;
    is_active: boolean;
    is_public: boolean;
    is_featured: boolean;
    sort_order: number;
    products_count: number;
};

type Section = 'products' | 'packages' | 'categories' | 'brands';

type Props = {
    products: Pagination<PublicProductContent>;
    packages: Pagination<PublicPackageContent>;
    categories: Pagination<PublicCategoryContent>;
    brands: Pagination<PublicBrandContent>;
    filters: { section: Section; search: string; status: string };
    summary: {
        products: number;
        publicProducts: number;
        readyProducts: number;
        featuredProducts: number;
        publicPackages: number;
    };
};

const textareaClass =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]';

export default function PublicCatalogContent({
    products,
    packages,
    categories,
    brands,
    filters,
    summary,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [product, setProduct] = useState<PublicProductContent | null>(null);
    const [rentalPackage, setRentalPackage] =
        useState<PublicPackageContent | null>(null);
    const [category, setCategory] = useState<PublicCategoryContent | null>(
        null,
    );
    const [brand, setBrand] = useState<PublicBrandContent | null>(null);

    const navigate = (section: Section, status = filters.status) => {
        router.get(
            '/catalog/public-content',
            { section, search: search || undefined, status },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Konten Katalog Publik" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Modul 8 · Public Content Management
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Konten Katalog Publik
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kurasi produk, paket, kategori, brand, media,
                            urutan, dan metadata SEO tanpa mengubah data
                            operasional.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/catalog">Kembali ke katalog</Link>
                        </Button>
                        <Button asChild>
                            <a href="/rental" target="_blank" rel="noreferrer">
                                <Globe2 /> Lihat website publik
                            </a>
                        </Button>
                    </div>
                </header>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Metric
                        label="Total produk"
                        value={summary.products}
                        icon={Tags}
                    />
                    <Metric
                        label="Tampil publik"
                        value={summary.publicProducts}
                        icon={Eye}
                    />
                    <Metric
                        label="Siap tayang"
                        value={summary.readyProducts}
                        icon={CheckCircle2}
                    />
                    <Metric
                        label="Produk unggulan"
                        value={summary.featuredProducts}
                        icon={Sparkles}
                    />
                    <Metric
                        label="Paket publik"
                        value={summary.publicPackages}
                        icon={PackageCheck}
                    />
                </section>

                <Card>
                    <CardContent className="grid gap-3 p-4 lg:grid-cols-[minmax(0,1fr)_220px_auto]">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        navigate(filters.section);
                                    }
                                }}
                                placeholder="Cari produk, paket, SKU, atau kode"
                                className="pl-9"
                            />
                        </div>
                        <Select
                            value={filters.status}
                            onValueChange={(value) =>
                                navigate(filters.section, value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                                <SelectItem value="public">
                                    Tampil publik
                                </SelectItem>
                                <SelectItem value="draft">
                                    Disembunyikan
                                </SelectItem>
                                <SelectItem value="incomplete">
                                    Konten belum lengkap
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button onClick={() => navigate(filters.section)}>
                            Terapkan
                        </Button>
                    </CardContent>
                </Card>

                <nav className="flex flex-wrap gap-2 rounded-xl border bg-card p-2">
                    {(
                        [
                            ['products', 'Produk'],
                            ['packages', 'Paket Rental'],
                            ['categories', 'Kategori'],
                            ['brands', 'Brand'],
                        ] as const
                    ).map(([section, label]) => (
                        <Button
                            key={section}
                            variant={
                                filters.section === section
                                    ? 'default'
                                    : 'ghost'
                            }
                            onClick={() => navigate(section)}
                        >
                            {label}
                        </Button>
                    ))}
                </nav>

                {filters.section === 'products' && (
                    <PaginatedSection pagination={products}>
                        <ContentGrid>
                            {products.data.map((item) => (
                                <ContentCard
                                    key={item.id}
                                    title={item.name}
                                    subtitle={`${item.sku} · ${item.category ?? 'Tanpa kategori'}`}
                                    image={item.image_url}
                                    isPublic={item.is_public}
                                    featured={item.is_featured}
                                    readiness={item.readiness}
                                    details={`${item.active_rates_count} harga aktif · ${item.active_assets_count} unit tersedia`}
                                    previewHref={`/rental/products/${item.slug}`}
                                    onEdit={() => setProduct(item)}
                                />
                            ))}
                        </ContentGrid>
                    </PaginatedSection>
                )}

                {filters.section === 'packages' && (
                    <PaginatedSection pagination={packages}>
                        <ContentGrid>
                            {packages.data.map((item) => (
                                <ContentCard
                                    key={item.id}
                                    title={item.name}
                                    subtitle={`${item.code} · ${item.branch?.code ?? 'Global'}`}
                                    image={item.image_url}
                                    isPublic={item.is_public}
                                    featured={item.is_featured}
                                    readiness={item.readiness}
                                    details={`${item.items_count} item · ${item.active_rates_count} harga aktif`}
                                    previewHref={`/rental/packages/${item.slug}`}
                                    onEdit={() => setRentalPackage(item)}
                                />
                            ))}
                        </ContentGrid>
                    </PaginatedSection>
                )}

                {filters.section === 'categories' && (
                    <PaginatedSection pagination={categories}>
                        <ContentGrid>
                            {categories.data.map((item) => (
                                <SimpleContentCard
                                    key={item.id}
                                    title={item.name}
                                    subtitle={`${item.code} · ${item.products_count} produk`}
                                    image={item.image_url}
                                    isPublic={item.is_public}
                                    onEdit={() => setCategory(item)}
                                />
                            ))}
                        </ContentGrid>
                    </PaginatedSection>
                )}

                {filters.section === 'brands' && (
                    <PaginatedSection pagination={brands}>
                        <ContentGrid>
                            {brands.data.map((item) => (
                                <SimpleContentCard
                                    key={item.id}
                                    title={item.name}
                                    subtitle={`${item.products_count} produk${item.is_featured ? ' · unggulan' : ''}`}
                                    image={item.logo_url}
                                    isPublic={item.is_public}
                                    onEdit={() => setBrand(item)}
                                />
                            ))}
                        </ContentGrid>
                    </PaginatedSection>
                )}
            </div>

            <ProductContentDialog
                key={product?.id ?? 'product-none'}
                product={product}
                onClose={() => setProduct(null)}
            />
            <PackageContentDialog
                key={rentalPackage?.id ?? 'package-none'}
                rentalPackage={rentalPackage}
                onClose={() => setRentalPackage(null)}
            />
            <CategoryContentDialog
                key={category?.id ?? 'category-none'}
                category={category}
                onClose={() => setCategory(null)}
            />
            <BrandContentDialog
                key={brand?.id ?? 'brand-none'}
                brand={brand}
                onClose={() => setBrand(null)}
            />
        </>
    );
}

function Metric({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number;
    icon: typeof Tags;
}) {
    return (
        <Card>
            <CardContent className="flex items-center justify-between p-5">
                <div>
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="mt-2 text-3xl font-semibold">{value}</p>
                </div>
                <div className="flex size-11 items-center justify-center rounded-xl bg-muted">
                    <Icon className="size-5" />
                </div>
            </CardContent>
        </Card>
    );
}

function PaginatedSection<T>({
    pagination,
    children,
}: {
    pagination: Pagination<T>;
    children: ReactNode;
}) {
    if (pagination.total === 0) {
        return (
            <Card>
                <CardContent className="flex min-h-40 flex-col items-center justify-center gap-2 p-6 text-center">
                    <FileImage className="size-8 text-muted-foreground/40" />
                    <p className="font-medium">Belum ada konten yang sesuai</p>
                    <p className="text-sm text-muted-foreground">
                        Ubah pencarian atau filter status untuk menampilkan data
                        lain.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <section className="grid gap-5">
            {children}
            <PaginationLinks
                links={pagination.links}
                from={pagination.from}
                to={pagination.to}
                total={pagination.total}
            />
        </section>
    );
}

function ContentGrid({ children }: { children: ReactNode }) {
    return (
        <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            {children}
        </section>
    );
}

function ContentCard({
    title,
    subtitle,
    image,
    isPublic,
    featured,
    readiness,
    details,
    previewHref,
    onEdit,
}: {
    title: string;
    subtitle: string;
    image: string | null;
    isPublic: boolean;
    featured: boolean;
    readiness: Readiness;
    details: string;
    previewHref: string;
    onEdit: () => void;
}) {
    return (
        <Card className="overflow-hidden">
            <div className="flex h-28 items-center justify-center bg-muted/60 sm:h-32">
                {image ? (
                    <img
                        src={image}
                        alt=""
                        loading="lazy"
                        className="size-full object-contain p-3"
                    />
                ) : (
                    <FileImage className="size-9 text-muted-foreground/40" />
                )}
            </div>
            <CardHeader className="p-4 pb-2">
                <div className="flex flex-wrap gap-2">
                    <Badge variant={isPublic ? 'outline' : 'secondary'}>
                        {isPublic ? 'Tampil publik' : 'Disembunyikan'}
                    </Badge>
                    {featured && <Badge>Unggulan</Badge>}
                    <Badge variant={readiness.ready ? 'outline' : 'secondary'}>
                        Kelengkapan {readiness.score}%
                    </Badge>
                </div>
                <CardTitle className="text-base">{title}</CardTitle>
                <CardDescription>{subtitle}</CardDescription>
            </CardHeader>
            <CardContent className="p-4 pt-1">
                <p className="text-xs text-muted-foreground">{details}</p>
                <div className="mt-3 grid grid-cols-2 gap-2">
                    <Button size="sm" onClick={onEdit}>
                        <Pencil /> Kelola
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <a href={previewHref} target="_blank" rel="noreferrer">
                            <Eye /> Preview
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function SimpleContentCard({
    title,
    subtitle,
    image,
    isPublic,
    onEdit,
}: {
    title: string;
    subtitle: string;
    image: string | null;
    isPublic: boolean;
    onEdit: () => void;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 p-4">
                <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted">
                    {image ? (
                        <img
                            src={image}
                            alt=""
                            loading="lazy"
                            className="size-full object-contain p-1.5"
                        />
                    ) : (
                        <FileImage className="size-6 text-muted-foreground/40" />
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex gap-2">
                        <Badge variant={isPublic ? 'outline' : 'secondary'}>
                            {isPublic ? 'Publik' : 'Draft'}
                        </Badge>
                    </div>
                    <p className="mt-2 truncate font-semibold">{title}</p>
                    <p className="text-xs text-muted-foreground">{subtitle}</p>
                </div>
                <Button size="icon" variant="outline" onClick={onEdit}>
                    <Pencil />
                </Button>
            </CardContent>
        </Card>
    );
}

function ToggleField({
    id,
    label,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center gap-3">
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(value) => onChange(value === true)}
            />
            <Label htmlFor={id}>{label}</Label>
        </div>
    );
}

function ProductContentDialog({
    product,
    onClose,
}: {
    product: PublicProductContent | null;
    onClose: () => void;
}) {
    const initial = useMemo(
        () => ({
            is_public: product?.is_public ?? false,
            is_featured: product?.is_featured ?? false,
            public_sort_order: product?.public_sort_order ?? 0,
            short_description: product?.short_description ?? '',
            seo_title: product?.seo_title ?? '',
            seo_description: product?.seo_description ?? '',
            primary_image: null as File | null,
            gallery_images: [] as File[],
            remove_primary_image: false,
            clear_gallery: false,
            _method: 'put' as const,
        }),
        [product],
    );
    const form = useForm(initial);
    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!product) {
            return;
        }

        form.post(`/catalog/public-content/products/${product.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog
            open={product !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Konten publik {product?.name}</DialogTitle>
                    <DialogDescription>
                        Atur media, penempatan landing page, dan metadata mesin
                        pencari.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="flex flex-wrap gap-6">
                        <ToggleField
                            id="product_public"
                            label="Tampilkan ke publik"
                            checked={form.data.is_public}
                            onChange={(v) => form.setData('is_public', v)}
                        />
                        <ToggleField
                            id="product_featured"
                            label="Produk unggulan"
                            checked={form.data.is_featured}
                            onChange={(v) => form.setData('is_featured', v)}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Urutan tampil</Label>
                            <Input
                                type="number"
                                min="0"
                                value={form.data.public_sort_order}
                                onChange={(e) =>
                                    form.setData(
                                        'public_sort_order',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Foto utama</Label>
                            <Input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(e) =>
                                    form.setData(
                                        'primary_image',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Deskripsi singkat</Label>
                        <textarea
                            className={textareaClass}
                            value={form.data.short_description}
                            onChange={(e) =>
                                form.setData(
                                    'short_description',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.short_description} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Galeri tambahan (maks. 8)</Label>
                        <Input
                            type="file"
                            multiple
                            accept="image/png,image/jpeg,image/webp"
                            onChange={(e) =>
                                form.setData(
                                    'gallery_images',
                                    Array.from(e.target.files ?? []),
                                )
                            }
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <ToggleField
                            id="remove_primary"
                            label="Hapus foto utama lama"
                            checked={form.data.remove_primary_image}
                            onChange={(v) =>
                                form.setData('remove_primary_image', v)
                            }
                        />
                        <ToggleField
                            id="clear_gallery"
                            label="Kosongkan galeri lama"
                            checked={form.data.clear_gallery}
                            onChange={(v) => form.setData('clear_gallery', v)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>SEO title</Label>
                        <Input
                            value={form.data.seo_title}
                            onChange={(e) =>
                                form.setData('seo_title', e.target.value)
                            }
                            maxLength={180}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>SEO description</Label>
                        <textarea
                            className={textareaClass}
                            value={form.data.seo_description}
                            onChange={(e) =>
                                form.setData('seo_description', e.target.value)
                            }
                            maxLength={320}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                        <Button disabled={form.processing}>
                            Simpan konten
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PackageContentDialog({
    rentalPackage,
    onClose,
}: {
    rentalPackage: PublicPackageContent | null;
    onClose: () => void;
}) {
    const form = useForm({
        is_public: rentalPackage?.is_public ?? false,
        is_featured: rentalPackage?.is_featured ?? false,
        public_sort_order: rentalPackage?.public_sort_order ?? 0,
        seo_title: rentalPackage?.seo_title ?? '',
        seo_description: rentalPackage?.seo_description ?? '',
        primary_image: null as File | null,
        remove_primary_image: false,
        _method: 'put' as const,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!rentalPackage) {
            return;
        }

        form.post(`/catalog/public-content/packages/${rentalPackage.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog
            open={rentalPackage !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Konten paket {rentalPackage?.name}
                    </DialogTitle>
                    <DialogDescription>
                        Atur status publik, visual, urutan, dan SEO paket.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="flex flex-wrap gap-6">
                        <ToggleField
                            id="package_public"
                            label="Tampilkan ke publik"
                            checked={form.data.is_public}
                            onChange={(v) => form.setData('is_public', v)}
                        />
                        <ToggleField
                            id="package_featured"
                            label="Paket unggulan"
                            checked={form.data.is_featured}
                            onChange={(v) => form.setData('is_featured', v)}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Urutan</Label>
                            <Input
                                type="number"
                                min="0"
                                value={form.data.public_sort_order}
                                onChange={(e) =>
                                    form.setData(
                                        'public_sort_order',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Foto utama</Label>
                            <Input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(e) =>
                                    form.setData(
                                        'primary_image',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <ToggleField
                        id="package_remove"
                        label="Hapus foto lama"
                        checked={form.data.remove_primary_image}
                        onChange={(v) =>
                            form.setData('remove_primary_image', v)
                        }
                    />
                    <div className="grid gap-2">
                        <Label>SEO title</Label>
                        <Input
                            value={form.data.seo_title}
                            onChange={(e) =>
                                form.setData('seo_title', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>SEO description</Label>
                        <textarea
                            className={textareaClass}
                            value={form.data.seo_description}
                            onChange={(e) =>
                                form.setData('seo_description', e.target.value)
                            }
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                        <Button disabled={form.processing}>Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CategoryContentDialog({
    category,
    onClose,
}: {
    category: PublicCategoryContent | null;
    onClose: () => void;
}) {
    const form = useForm({
        is_public: category?.is_public ?? false,
        sort_order: category?.sort_order ?? 0,
        icon: category?.icon ?? '',
        image: null as File | null,
        remove_image: false,
        _method: 'put' as const,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!category) {
            return;
        }

        form.post(`/catalog/public-content/categories/${category.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog
            open={category !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Kategori {category?.name}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <ToggleField
                        id="category_public"
                        label="Tampilkan ke publik"
                        checked={form.data.is_public}
                        onChange={(v) => form.setData('is_public', v)}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Urutan</Label>
                            <Input
                                type="number"
                                min="0"
                                value={form.data.sort_order}
                                onChange={(e) =>
                                    form.setData(
                                        'sort_order',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Nama ikon</Label>
                            <Input
                                value={form.data.icon}
                                onChange={(e) =>
                                    form.setData('icon', e.target.value)
                                }
                                placeholder="camera"
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Foto kategori</Label>
                        <Input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            onChange={(e) =>
                                form.setData(
                                    'image',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </div>
                    <ToggleField
                        id="category_remove"
                        label="Hapus foto lama"
                        checked={form.data.remove_image}
                        onChange={(v) => form.setData('remove_image', v)}
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                        <Button disabled={form.processing}>Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function BrandContentDialog({
    brand,
    onClose,
}: {
    brand: PublicBrandContent | null;
    onClose: () => void;
}) {
    const form = useForm({
        is_public: brand?.is_public ?? false,
        is_featured: brand?.is_featured ?? false,
        sort_order: brand?.sort_order ?? 0,
        logo: null as File | null,
        remove_logo: false,
        _method: 'put' as const,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!brand) {
            return;
        }

        form.post(`/catalog/public-content/brands/${brand.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog
            open={brand !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Brand {brand?.name}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="flex flex-wrap gap-6">
                        <ToggleField
                            id="brand_public"
                            label="Tampilkan ke publik"
                            checked={form.data.is_public}
                            onChange={(v) => form.setData('is_public', v)}
                        />
                        <ToggleField
                            id="brand_featured"
                            label="Brand unggulan"
                            checked={form.data.is_featured}
                            onChange={(v) => form.setData('is_featured', v)}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Urutan</Label>
                            <Input
                                type="number"
                                min="0"
                                value={form.data.sort_order}
                                onChange={(e) =>
                                    form.setData(
                                        'sort_order',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Logo</Label>
                            <Input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(e) =>
                                    form.setData(
                                        'logo',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <ToggleField
                        id="brand_remove"
                        label="Hapus logo lama"
                        checked={form.data.remove_logo}
                        onChange={(v) => form.setData('remove_logo', v)}
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                        <Button disabled={form.processing}>Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
