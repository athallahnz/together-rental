import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { RupiahInput } from '@/components/ui/rupiah-input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AccessBranch,
    CategoryFormData,
    PackageRate,
    PackageRateFormData,
    Product,
    ProductCategory,
    ProductFormData,
    ProductRate,
    ProductRateFormData,
    RatePlan,
    RatePlanFormData,
    RentalPackage,
    RentalPackageFormData,
} from '@/types';

type DialogBaseProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const textareaClass =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

export function ProductFormDialog({
    open,
    onOpenChange,
    product,
    categories,
}: DialogBaseProps & {
    product: Product | null;
    categories: ProductCategory[];
}) {
    const data = productData(product);
    const form = useForm<ProductFormData>(data);
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (product) {
            form.put(`/catalog/products/${product.id}`, options);
        } else {
            form.post('/catalog/products', options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    form.setData(productData(product));
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {product ? `Edit ${product.name}` : 'Tambah produk'}
                    </DialogTitle>
                    <DialogDescription>
                        Produk adalah master jenis barang. Unit fisik dan serial
                        number dikelola pada modul inventaris.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="SKU" name="sku" error={form.errors.sku}>
                            <Input
                                id="sku"
                                value={form.data.sku}
                                onChange={(event) =>
                                    form.setData('sku', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Nama produk"
                            name="name"
                            error={form.errors.name}
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Kategori"
                            name="category_id"
                            error={form.errors.category_id}
                        >
                            <Select
                                value={
                                    form.data.category_id?.toString() ?? 'none'
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'category_id',
                                        value === 'none' ? null : Number(value),
                                    )
                                }
                            >
                                <SelectTrigger id="category_id">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Tanpa kategori
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={category.id.toString()}
                                        >
                                            {category.code} · {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label="Tracking inventaris"
                            name="tracking_type"
                            error={form.errors.tracking_type}
                        >
                            <Select
                                value={form.data.tracking_type}
                                onValueChange={(value) =>
                                    form.setData(
                                        'tracking_type',
                                        value as Product['tracking_type'],
                                    )
                                }
                            >
                                <SelectTrigger id="tracking_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="serialized">
                                        Per unit / serial
                                    </SelectItem>
                                    <SelectItem value="bulk">
                                        Kuantitas / bulk
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label="Brand"
                            name="brand"
                            error={form.errors.brand}
                        >
                            <Input
                                id="brand"
                                value={form.data.brand}
                                onChange={(event) =>
                                    form.setData('brand', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Model"
                            name="model"
                            error={form.errors.model}
                        >
                            <Input
                                id="model"
                                value={form.data.model}
                                onChange={(event) =>
                                    form.setData('model', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Nilai penggantian"
                            name="replacement_value"
                            error={form.errors.replacement_value}
                        >
                            <RupiahInput
                                id="replacement_value"
                                value={form.data.replacement_value}
                                onValueChange={(value) =>
                                    form.setData(
                                        'replacement_value',
                                        String(value),
                                    )
                                }
                                required
                            />
                        </Field>
                    </div>
                    <Field
                        label="Deskripsi"
                        name="description"
                        error={form.errors.description}
                    >
                        <textarea
                            id="description"
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </Field>
                    <div className="flex flex-wrap gap-6">
                        <CheckField
                            id="is_rentable"
                            label="Dapat disewakan"
                            checked={form.data.is_rentable}
                            onCheckedChange={(checked) =>
                                form.setData('is_rentable', checked)
                            }
                        />
                        <CheckField
                            id="is_active"
                            label="Produk aktif"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {product ? 'Simpan perubahan' : 'Buat produk'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function CategoryFormDialog({
    open,
    onOpenChange,
    category,
    categories,
}: DialogBaseProps & {
    category: ProductCategory | null;
    categories: ProductCategory[];
}) {
    const form = useForm<CategoryFormData>(categoryData(category));
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (category) {
            form.put(`/catalog/categories/${category.id}`, options);
        } else {
            form.post('/catalog/categories', options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    form.setData(categoryData(category));
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {category ? `Edit ${category.name}` : 'Tambah kategori'}
                    </DialogTitle>
                    <DialogDescription>
                        Susun kategori bertingkat agar katalog mudah dicari.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Kode"
                            name="category_code"
                            error={form.errors.code}
                        >
                            <Input
                                id="category_code"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Urutan"
                            name="sort_order"
                            error={form.errors.sort_order}
                        >
                            <Input
                                id="sort_order"
                                type="number"
                                min="0"
                                value={form.data.sort_order}
                                onChange={(event) =>
                                    form.setData(
                                        'sort_order',
                                        Number(event.target.value),
                                    )
                                }
                                required
                            />
                        </Field>
                    </div>
                    <Field
                        label="Nama kategori"
                        name="category_name"
                        error={form.errors.name}
                    >
                        <Input
                            id="category_name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field
                        label="Kategori induk"
                        name="parent_id"
                        error={form.errors.parent_id}
                    >
                        <Select
                            value={form.data.parent_id?.toString() ?? 'none'}
                            onValueChange={(value) =>
                                form.setData(
                                    'parent_id',
                                    value === 'none' ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger id="parent_id">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Kategori utama
                                </SelectItem>
                                {categories
                                    .filter((item) => item.id !== category?.id)
                                    .map((item) => (
                                        <SelectItem
                                            key={item.id}
                                            value={item.id.toString()}
                                        >
                                            {item.code} · {item.name}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field
                        label="Deskripsi"
                        name="category_description"
                        error={form.errors.description}
                    >
                        <textarea
                            id="category_description"
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </Field>
                    <CheckField
                        id="category_active"
                        label="Kategori aktif"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked)
                        }
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Simpan kategori
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function RatePlanFormDialog({
    open,
    onOpenChange,
    ratePlan,
    branches,
    manageGlobal,
}: DialogBaseProps & {
    ratePlan: RatePlan | null;
    branches: AccessBranch[];
    manageGlobal: boolean;
}) {
    const form = useForm<RatePlanFormData>(
        ratePlanData(ratePlan, branches, manageGlobal),
    );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (ratePlan) {
            form.put(`/catalog/rate-plans/${ratePlan.id}`, options);
        } else {
            form.post('/catalog/rate-plans', options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    form.setData(
                        ratePlanData(ratePlan, branches, manageGlobal),
                    );
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {ratePlan
                            ? `Edit ${ratePlan.name}`
                            : 'Tambah rate plan'}
                    </DialogTitle>
                    <DialogDescription>
                        Tentukan satuan durasi dan grace period yang dipakai
                        harga produk maupun paket.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <ScopeField
                        value={form.data.branch_id}
                        onValueChange={(branchId) =>
                            form.setData('branch_id', branchId)
                        }
                        branches={branches}
                        manageGlobal={manageGlobal}
                        error={form.errors.branch_id}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Kode"
                            name="rate_code"
                            error={form.errors.code}
                        >
                            <Input
                                id="rate_code"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Nama rate plan"
                            name="rate_name"
                            error={form.errors.name}
                        >
                            <Input
                                id="rate_name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Satuan"
                            name="duration_unit"
                            error={form.errors.duration_unit}
                        >
                            <Select
                                value={form.data.duration_unit}
                                onValueChange={(value) =>
                                    form.setData(
                                        'duration_unit',
                                        value as RatePlan['duration_unit'],
                                    )
                                }
                            >
                                <SelectTrigger id="duration_unit">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="minute">
                                        Menit
                                    </SelectItem>
                                    <SelectItem value="hour">Jam</SelectItem>
                                    <SelectItem value="day">Hari</SelectItem>
                                    <SelectItem value="week">Minggu</SelectItem>
                                    <SelectItem value="month">Bulan</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label="Nilai durasi"
                            name="duration_value"
                            error={form.errors.duration_value}
                        >
                            <Input
                                id="duration_value"
                                type="number"
                                min="1"
                                value={form.data.duration_value}
                                onChange={(event) =>
                                    form.setData(
                                        'duration_value',
                                        Number(event.target.value),
                                    )
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Grace period (menit)"
                            name="grace_period_minutes"
                            error={form.errors.grace_period_minutes}
                        >
                            <Input
                                id="grace_period_minutes"
                                type="number"
                                min="0"
                                value={form.data.grace_period_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'grace_period_minutes',
                                        Number(event.target.value),
                                    )
                                }
                                required
                            />
                        </Field>
                    </div>
                    <CheckField
                        id="rate_active"
                        label="Rate plan aktif"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked)
                        }
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Simpan rate plan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function PackageFormDialog({
    open,
    onOpenChange,
    rentalPackage,
    branches,
    manageGlobal,
}: DialogBaseProps & {
    rentalPackage: RentalPackage | null;
    branches: AccessBranch[];
    manageGlobal: boolean;
}) {
    const form = useForm<RentalPackageFormData>(
        packageData(rentalPackage, branches, manageGlobal),
    );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (rentalPackage) {
            form.put(`/catalog/packages/${rentalPackage.id}`, options);
        } else {
            form.post('/catalog/packages', options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    form.setData(
                        packageData(rentalPackage, branches, manageGlobal),
                    );
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {rentalPackage
                            ? `Edit ${rentalPackage.name}`
                            : 'Tambah paket rental'}
                    </DialogTitle>
                    <DialogDescription>
                        Paket menggabungkan beberapa produk dengan harga
                        sendiri.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <ScopeField
                        value={form.data.branch_id}
                        onValueChange={(branchId) =>
                            form.setData('branch_id', branchId)
                        }
                        branches={branches}
                        manageGlobal={manageGlobal}
                        error={form.errors.branch_id}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Kode paket"
                            name="package_code"
                            error={form.errors.code}
                        >
                            <Input
                                id="package_code"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Nama paket"
                            name="package_name"
                            error={form.errors.name}
                        >
                            <Input
                                id="package_name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Berlaku mulai"
                            name="valid_from"
                            error={form.errors.valid_from}
                        >
                            <Input
                                id="valid_from"
                                type="date"
                                value={form.data.valid_from}
                                onChange={(event) =>
                                    form.setData(
                                        'valid_from',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Berlaku sampai"
                            name="valid_until"
                            error={form.errors.valid_until}
                        >
                            <Input
                                id="valid_until"
                                type="date"
                                value={form.data.valid_until}
                                onChange={(event) =>
                                    form.setData(
                                        'valid_until',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <Field
                        label="Deskripsi"
                        name="package_description"
                        error={form.errors.description}
                    >
                        <textarea
                            id="package_description"
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </Field>
                    <CheckField
                        id="package_active"
                        label="Paket aktif"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked)
                        }
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Simpan paket
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ProductRateFormDialog({
    open,
    onOpenChange,
    product,
    rate,
    ratePlans,
    branches,
    manageGlobal,
}: DialogBaseProps & {
    product: Product;
    rate: ProductRate | null;
    ratePlans: RatePlan[];
    branches: AccessBranch[];
    manageGlobal: boolean;
}) {
    const form = useForm<ProductRateFormData>(
        productRateData(rate, branches, manageGlobal),
    );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (rate) {
            form.put(`/catalog/product-rates/${rate.id}`, options);
        } else {
            form.post(`/catalog/products/${product.id}/rates`, options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    form.setData(productRateData(rate, branches, manageGlobal));
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {rate
                            ? 'Edit harga produk'
                            : `Tambah harga ${product.name}`}
                    </DialogTitle>
                    <DialogDescription>
                        Harga dapat berlaku global atau khusus satu cabang.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <ScopeField
                        value={form.data.branch_id}
                        onValueChange={(branchId) =>
                            form.setData('branch_id', branchId)
                        }
                        branches={branches}
                        manageGlobal={manageGlobal}
                        error={form.errors.branch_id}
                    />
                    <Field
                        label="Rate plan"
                        name="product_rate_plan"
                        error={form.errors.rate_plan_id}
                    >
                        <RatePlanSelect
                            id="product_rate_plan"
                            value={form.data.rate_plan_id}
                            onValueChange={(value) =>
                                form.setData('rate_plan_id', value)
                            }
                            ratePlans={ratePlans}
                            branchId={form.data.branch_id}
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <MoneyField
                            id="product_amount"
                            label="Harga sewa"
                            value={form.data.amount}
                            error={form.errors.amount}
                            onChange={(value) => form.setData('amount', value)}
                        />
                        <MoneyField
                            id="product_deposit"
                            label="Deposit"
                            value={form.data.deposit_amount}
                            error={form.errors.deposit_amount}
                            onChange={(value) =>
                                form.setData('deposit_amount', value)
                            }
                        />
                        <MoneyField
                            id="additional_hour"
                            label="Tambahan per jam"
                            value={form.data.additional_hour_amount}
                            error={form.errors.additional_hour_amount}
                            onChange={(value) =>
                                form.setData('additional_hour_amount', value)
                            }
                        />
                        <MoneyField
                            id="late_fee"
                            label="Denda keterlambatan"
                            value={form.data.late_fee_amount}
                            error={form.errors.late_fee_amount}
                            onChange={(value) =>
                                form.setData('late_fee_amount', value)
                            }
                        />
                        <Field
                            label="Berlaku mulai"
                            name="product_valid_from"
                            error={form.errors.valid_from}
                        >
                            <Input
                                id="product_valid_from"
                                type="date"
                                value={form.data.valid_from}
                                onChange={(event) =>
                                    form.setData(
                                        'valid_from',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Berlaku sampai"
                            name="product_valid_until"
                            error={form.errors.valid_until}
                        >
                            <Input
                                id="product_valid_until"
                                type="date"
                                value={form.data.valid_until}
                                onChange={(event) =>
                                    form.setData(
                                        'valid_until',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <CheckField
                        id="product_rate_active"
                        label="Harga aktif"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked)
                        }
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Simpan harga
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function PackageItemFormDialog({
    open,
    onOpenChange,
    rentalPackage,
    products,
}: DialogBaseProps & {
    rentalPackage: RentalPackage;
    products: Product[];
}) {
    const form = useForm({
        product_id: null as number | null,
        quantity: 1,
        is_optional: false,
        sort_order: rentalPackage.items?.length ?? 0,
    });

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    form.reset();
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Tambah produk ke paket</DialogTitle>
                    <DialogDescription>
                        Pilih produk aktif dan jumlah unit yang termasuk.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            `/catalog/packages/${rentalPackage.id}/items`,
                            {
                                preserveScroll: true,
                                onSuccess: () => onOpenChange(false),
                            },
                        );
                    }}
                >
                    <Field
                        label="Produk"
                        name="package_product"
                        error={form.errors.product_id}
                    >
                        <Select
                            value={form.data.product_id?.toString() ?? ''}
                            onValueChange={(value) =>
                                form.setData('product_id', Number(value))
                            }
                        >
                            <SelectTrigger id="package_product">
                                <SelectValue placeholder="Pilih produk" />
                            </SelectTrigger>
                            <SelectContent>
                                {products.map((product) => (
                                    <SelectItem
                                        key={product.id}
                                        value={product.id.toString()}
                                    >
                                        {product.sku} · {product.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Jumlah"
                            name="package_quantity"
                            error={form.errors.quantity}
                        >
                            <Input
                                id="package_quantity"
                                type="number"
                                min="1"
                                value={form.data.quantity}
                                onChange={(event) =>
                                    form.setData(
                                        'quantity',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Urutan"
                            name="package_sort_order"
                            error={form.errors.sort_order}
                        >
                            <Input
                                id="package_sort_order"
                                type="number"
                                min="0"
                                value={form.data.sort_order}
                                onChange={(event) =>
                                    form.setData(
                                        'sort_order',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <CheckField
                        id="package_optional"
                        label="Item opsional"
                        checked={form.data.is_optional}
                        onCheckedChange={(checked) =>
                            form.setData('is_optional', checked)
                        }
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Tambahkan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function PackageRateFormDialog({
    open,
    onOpenChange,
    rentalPackage,
    rate,
    ratePlans,
    branches,
    manageGlobal,
}: DialogBaseProps & {
    rentalPackage: RentalPackage;
    rate: PackageRate | null;
    ratePlans: RatePlan[];
    branches: AccessBranch[];
    manageGlobal: boolean;
}) {
    const form = useForm<PackageRateFormData>(
        packageRateData(rate, branches, manageGlobal),
    );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (rate) {
            form.put(`/catalog/package-rates/${rate.id}`, options);
        } else {
            form.post(`/catalog/packages/${rentalPackage.id}/rates`, options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    form.setData(packageRateData(rate, branches, manageGlobal));
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {rate ? 'Edit harga paket' : 'Tambah harga paket'}
                    </DialogTitle>
                    <DialogDescription>
                        Tetapkan harga paket berdasarkan rate plan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <ScopeField
                        value={form.data.branch_id}
                        onValueChange={(branchId) =>
                            form.setData('branch_id', branchId)
                        }
                        branches={branches}
                        manageGlobal={manageGlobal}
                        error={form.errors.branch_id}
                    />
                    <Field
                        label="Rate plan"
                        name="package_rate_plan"
                        error={form.errors.rate_plan_id}
                    >
                        <RatePlanSelect
                            id="package_rate_plan"
                            value={form.data.rate_plan_id}
                            onValueChange={(value) =>
                                form.setData('rate_plan_id', value)
                            }
                            ratePlans={ratePlans}
                            branchId={form.data.branch_id}
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <MoneyField
                            id="package_amount"
                            label="Harga paket"
                            value={form.data.amount}
                            error={form.errors.amount}
                            onChange={(value) => form.setData('amount', value)}
                        />
                        <MoneyField
                            id="package_deposit"
                            label="Deposit"
                            value={form.data.deposit_amount}
                            error={form.errors.deposit_amount}
                            onChange={(value) =>
                                form.setData('deposit_amount', value)
                            }
                        />
                    </div>
                    <CheckField
                        id="package_rate_active"
                        label="Harga aktif"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked)
                        }
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Simpan harga
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    name,
    error,
    children,
}: {
    label: string;
    name: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function CheckField({
    id,
    label,
    checked,
    onCheckedChange,
}: {
    id: string;
    label: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center gap-2">
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(value === true)}
            />
            <Label htmlFor={id}>{label}</Label>
        </div>
    );
}

function ScopeField({
    value,
    onValueChange,
    branches,
    manageGlobal,
    error,
}: {
    value: number | null;
    onValueChange: (value: number | null) => void;
    branches: AccessBranch[];
    manageGlobal: boolean;
    error?: string;
}) {
    return (
        <Field label="Scope harga" name="branch_id" error={error}>
            <Select
                value={value?.toString() ?? 'global'}
                onValueChange={(next) =>
                    onValueChange(next === 'global' ? null : Number(next))
                }
            >
                <SelectTrigger id="branch_id">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {manageGlobal && (
                        <SelectItem value="global">
                            Global · seluruh cabang
                        </SelectItem>
                    )}
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
        </Field>
    );
}

function RatePlanSelect({
    id,
    value,
    onValueChange,
    ratePlans,
    branchId,
}: {
    id: string;
    value: number | null;
    onValueChange: (value: number) => void;
    ratePlans: RatePlan[];
    branchId: number | null;
}) {
    const options = ratePlans.filter(
        (plan) => plan.branch_id === null || plan.branch_id === branchId,
    );

    return (
        <Select
            value={value?.toString() ?? ''}
            onValueChange={(next) => onValueChange(Number(next))}
        >
            <SelectTrigger id={id}>
                <SelectValue placeholder="Pilih rate plan" />
            </SelectTrigger>
            <SelectContent>
                {options.map((plan) => (
                    <SelectItem key={plan.id} value={plan.id.toString()}>
                        {plan.code} · {plan.name}
                        {plan.branch ? ` · ${plan.branch.code}` : ' · Global'}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function MoneyField({
    id,
    label,
    value,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <Field label={label} name={id} error={error}>
            <RupiahInput
                id={id}
                value={value}
                onValueChange={(nextValue) => onChange(String(nextValue))}
                required
            />
        </Field>
    );
}

function productData(product: Product | null): ProductFormData {
    return {
        category_id: product?.category_id ?? null,
        sku: product?.sku ?? '',
        name: product?.name ?? '',
        brand: product?.brand ?? '',
        model: product?.model ?? '',
        tracking_type: product?.tracking_type ?? 'serialized',
        description: product?.description ?? '',
        replacement_value: product?.replacement_value ?? '0',
        is_rentable: product?.is_rentable ?? true,
        is_active: product?.is_active ?? true,
    };
}

function categoryData(category: ProductCategory | null): CategoryFormData {
    return {
        parent_id: category?.parent_id ?? null,
        code: category?.code ?? '',
        name: category?.name ?? '',
        description: category?.description ?? '',
        is_active: category?.is_active ?? true,
        sort_order: category?.sort_order ?? 0,
    };
}

function ratePlanData(
    ratePlan: RatePlan | null,
    branches: AccessBranch[],
    manageGlobal: boolean,
): RatePlanFormData {
    return {
        branch_id:
            ratePlan?.branch_id ??
            (manageGlobal ? null : (branches[0]?.id ?? null)),
        code: ratePlan?.code ?? '',
        name: ratePlan?.name ?? '',
        duration_unit: ratePlan?.duration_unit ?? 'hour',
        duration_value: ratePlan?.duration_value ?? 1,
        grace_period_minutes: ratePlan?.grace_period_minutes ?? 0,
        is_active: ratePlan?.is_active ?? true,
    };
}

function packageData(
    rentalPackage: RentalPackage | null,
    branches: AccessBranch[],
    manageGlobal: boolean,
): RentalPackageFormData {
    return {
        branch_id:
            rentalPackage?.branch_id ??
            (manageGlobal ? null : (branches[0]?.id ?? null)),
        code: rentalPackage?.code ?? '',
        name: rentalPackage?.name ?? '',
        description: rentalPackage?.description ?? '',
        valid_from: rentalPackage?.valid_from?.slice(0, 10) ?? '',
        valid_until: rentalPackage?.valid_until?.slice(0, 10) ?? '',
        is_active: rentalPackage?.is_active ?? true,
    };
}

function productRateData(
    rate: ProductRate | null,
    branches: AccessBranch[],
    manageGlobal: boolean,
): ProductRateFormData {
    return {
        branch_id:
            rate?.branch_id ??
            (manageGlobal ? null : (branches[0]?.id ?? null)),
        rate_plan_id: rate?.rate_plan_id ?? null,
        amount: rate?.amount ?? '0',
        deposit_amount: rate?.deposit_amount ?? '0',
        additional_hour_amount: rate?.additional_hour_amount ?? '0',
        late_fee_amount: rate?.late_fee_amount ?? '0',
        valid_from: rate?.valid_from?.slice(0, 10) ?? '',
        valid_until: rate?.valid_until?.slice(0, 10) ?? '',
        is_active: rate?.is_active ?? true,
    };
}

function packageRateData(
    rate: PackageRate | null,
    branches: AccessBranch[],
    manageGlobal: boolean,
): PackageRateFormData {
    return {
        branch_id:
            rate?.branch_id ??
            (manageGlobal ? null : (branches[0]?.id ?? null)),
        rate_plan_id: rate?.rate_plan_id ?? null,
        amount: rate?.amount ?? '0',
        deposit_amount: rate?.deposit_amount ?? '0',
        is_active: rate?.is_active ?? true,
    };
}
