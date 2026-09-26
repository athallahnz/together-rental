import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleDollarSign,
    CircleOff,
    Layers3,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { useGlobalLocale } from '@/lib/locale-store';
import { Stage3Text, stage3Translate } from '@/components/stage3-text';
import {
    PackageFormDialog,
    PackageItemFormDialog,
    PackageRateFormDialog,
} from '@/components/catalog/catalog-dialogs';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
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
import { MetricCard } from '@/components/ui/metric-card';
import type {
    AccessBranch,
    CatalogPermissions,
    PackageRate,
    Product,
    RatePlan,
    RentalPackage,
} from '@/types';

type Props = {
    package: RentalPackage;
    products: Product[];
    ratePlans: RatePlan[];
    branches: AccessBranch[];
    permissions: CatalogPermissions & { manageResource: boolean };
};

export default function PackageShow({
    package: rentalPackage,
    products,
    ratePlans,
    branches,
    permissions,
}: Props) {
    const stage3Locale = useGlobalLocale();
    const { errors } = usePage().props;
    const confirm = useConfirmDialog();
    const [packageDialog, setPackageDialog] = useState(false);
    const [itemDialog, setItemDialog] = useState(false);
    const [rateDialog, setRateDialog] = useState(false);
    const [editingRate, setEditingRate] = useState<PackageRate | null>(null);

    const openRate = (rate: PackageRate | null) => {
        setEditingRate(rate);
        setRateDialog(true);
    };

    return (
        <>
            <Head title={rentalPackage.name} />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4">
                    <Button asChild variant="ghost" className="w-fit">
                        <Link href="/catalog?section=packages">
                            <ArrowLeft />
                            <Stage3Text k="stage3.ui.semua.paket.a1b91" /></Link>
                    </Button>
                    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
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
                                <Badge variant="secondary">
                                    {rentalPackage.branch
                                        ? rentalPackage.branch.code
                                        : stage3Translate('stage3.ui.correction.global.5f118', stage3Locale)}
                                </Badge>
                            </div>
                            <h1 className="mt-3 text-2xl font-semibold tracking-tight">
                                {rentalPackage.name}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                                {rentalPackage.description ||
                                    'Belum ada deskripsi paket.'}
                            </p>
                        </div>
                        {permissions.manageResource && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => setPackageDialog(true)}
                                >
                                    <Pencil />
                                    <Stage3Text k="stage3.ui.edit.paket.2354f" /></Button>
                                <Button onClick={() => setItemDialog(true)}>
                                    <Plus />
                                    <Stage3Text k="stage3.ui.tambah.item.9a69c" /></Button>
                            </div>
                        )}
                    </div>
                </header>

                <PackageErrors errors={errors} />

                <section className="grid gap-4 sm:grid-cols-3">
                    <Metric
                        label={stage3Translate('stage3.ui.produk.dalam.paket.9827c', stage3Locale)}
                        value={(rentalPackage.items ?? []).length.toString()}
                        icon={Layers3}
                    />
                    <Metric
                        label={stage3Translate('stage3.ui.total.unit.8fce6', stage3Locale)}
                        value={(rentalPackage.items ?? [])
                            .reduce((sum, item) => sum + item.quantity, 0)
                            .toString()}
                        icon={Layers3}
                    />
                    <Metric
                        label={stage3Translate('stage3.ui.harga.aktif.8a98d', stage3Locale)}
                        value={(rentalPackage.rates ?? [])
                            .filter((rate) => rate.is_active)
                            .length.toString()}
                        icon={CircleDollarSign}
                    />
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle><Stage3Text k="stage3.ui.isi.paket.cc244" /></CardTitle>
                                <CardDescription>
                                    <Stage3Text k="stage3.ui.produk.wajib.dan.opsional.yang.disertakan.a450e" /></CardDescription>
                            </div>
                            {permissions.manageResource && (
                                <Button
                                    size="sm"
                                    onClick={() => setItemDialog(true)}
                                >
                                    <Plus />
                                    <Stage3Text k="stage3.ui.item.ecdda" /></Button>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {(rentalPackage.items ?? []).length === 0 ? (
                                <Empty
                                    icon={Layers3}
                                    title={stage3Translate('stage3.ui.paket.belum.memiliki.item.5ff10', stage3Locale)}
                                    description={stage3Translate('stage3.ui.tambahkan.produk.yang.akan.disertakan.dalam.pak.e26f3', stage3Locale)}
                                />
                            ) : (
                                rentalPackage.items?.map((item) => (
                                    <article
                                        key={item.id}
                                        className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={`/catalog/products/${item.product_id}`}
                                                    className="font-semibold hover:underline"
                                                >
                                                    {item.product?.name ??
                                                        'Produk'}
                                                </Link>
                                                {item.is_optional && (
                                                    <Badge variant="secondary">
                                                        <Stage3Text k="stage3.ui.opsional.cf048" /></Badge>
                                                )}
                                                {!item.product?.is_active && (
                                                    <Badge variant="destructive">
                                                        <Stage3Text k="stage3.ui.produk.nonaktif.15a44" /></Badge>
                                                )}
                                            </div>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                {item.product?.sku} ·{' '}
                                                {item.quantity} <Stage3Text k="stage3.ui.unit.0df9e" /></p>
                                        </div>
                                        {permissions.manageResource && (
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label={stage3Translate('stage3.ui.hapus.item.paket.dda4a', stage3Locale)}
                                                onClick={async () => {
                                                    const confirmed =
                                                        await confirm({
                                                            title: 'Keluarkan item paket?',
                                                            description: `${item.product?.name ?? 'Produk'} akan dihapus dari komposisi paket ini.`,
                                                            confirmLabel:
                                                                'Keluarkan item',
                                                            variant:
                                                                'destructive',
                                                        });

                                                    if (!confirmed) {
                                                        return;
                                                    }

                                                    router.delete(
                                                        `/catalog/package-items/${item.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }}
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </article>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle><Stage3Text k="stage3.ui.harga.paket.12c54" /></CardTitle>
                                <CardDescription>
                                    <Stage3Text k="stage3.ui.harga.berdasarkan.rate.plan.dan.cabang.c3d8d" /></CardDescription>
                            </div>
                            {permissions.manageResource && (
                                <Button
                                    size="sm"
                                    onClick={() => openRate(null)}
                                >
                                    <Plus />
                                    <Stage3Text k="stage3.ui.harga.059e7" /></Button>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {(rentalPackage.rates ?? []).length === 0 ? (
                                <Empty
                                    icon={CircleDollarSign}
                                    title={stage3Translate('stage3.ui.paket.belum.memiliki.harga.72c29', stage3Locale)}
                                    description={stage3Translate('stage3.ui.tambahkan.harga.paket.berdasarkan.rate.plan.4a6bd', stage3Locale)}
                                />
                            ) : (
                                rentalPackage.rates?.map((rate) => {
                                    const canManage =
                                        permissions.manageResource &&
                                        (rate.branch_id !== null ||
                                            permissions.manageGlobal);

                                    return (
                                        <article
                                            key={rate.id}
                                            className="grid gap-3 rounded-xl border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
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
                                                            : stage3Translate('stage3.ui.correction.global.5f118', stage3Locale)}
                                                    </Badge>
                                                    {!rate.is_active && (
                                                        <Badge variant="secondary">
                                                            <Stage3Text k="stage3.ui.nonaktif.60944" /></Badge>
                                                    )}
                                                </div>
                                                <p className="mt-2 text-lg font-semibold">
                                                    {formatCurrency(
                                                        rate.amount,
                                                    )}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    <Stage3Text k="stage3.ui.deposit.e7b0b" />{' '}
                                                    {formatCurrency(
                                                        rate.deposit_amount,
                                                    )}
                                                </p>
                                            </div>
                                            {canManage && (
                                                <div className="flex gap-2">
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label={stage3Translate('stage3.ui.edit.harga.9cb6d', stage3Locale)}
                                                        onClick={() =>
                                                            openRate(rate)
                                                        }
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={stage3Translate('stage3.ui.hapus.harga.be63c', stage3Locale)}
                                                        onClick={async () => {
                                                            const confirmed =
                                                                await confirm({
                                                                    title: 'Hapus harga paket?',
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
                                                                `/catalog/package-rates/${rate.id}`,
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
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle><Stage3Text k="stage3.ui.periode.dan.scope.1fc55" /></CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5 text-sm sm:grid-cols-3">
                        <Info
                            label={stage3Translate('stage3.ui.scope.4651a', stage3Locale)}
                            value={
                                rentalPackage.branch
                                    ? `${rentalPackage.branch.code} · ${rentalPackage.branch.name}`
                                    : stage3Translate('stage3.ui.correction.global.seluruh.cabang.b98dd', stage3Locale)
                            }
                        />
                        <Info
                            label={stage3Translate('stage3.ui.berlaku.mulai.41c81', stage3Locale)}
                            value={
                                rentalPackage.valid_from
                                    ? formatDate(rentalPackage.valid_from)
                                    : 'Tanpa batas awal'
                            }
                        />
                        <Info
                            label={stage3Translate('stage3.ui.berlaku.sampai.8b9f4', stage3Locale)}
                            value={
                                rentalPackage.valid_until
                                    ? formatDate(rentalPackage.valid_until)
                                    : 'Tanpa batas akhir'
                            }
                        />
                    </CardContent>
                </Card>

                {permissions.manageResource && (
                    <Card className="border-destructive/30">
                        <CardHeader>
                            <CardTitle className="text-base">
                                <Stage3Text k="stage3.ui.arsipkan.paket.aca0f" /></CardTitle>
                            <CardDescription>
                                <Stage3Text k="stage3.ui.paket.yang.masih.digunakan.booking.aktif.akan.d.bc987" /></CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button
                                variant="destructive"
                                onClick={async () => {
                                    const confirmed = await confirm({
                                        title: 'Arsipkan paket?',
                                        description: `${rentalPackage.name} tidak akan tersedia untuk transaksi baru. Histori tetap disimpan.`,
                                        confirmLabel: 'Arsipkan paket',
                                        variant: 'destructive',
                                    });

                                    if (!confirmed) {
                                        return;
                                    }

                                    router.delete(
                                        `/catalog/packages/${rentalPackage.id}`,
                                    );
                                }}
                            >
                                <Trash2 />
                                <Stage3Text k="stage3.ui.arsipkan.paket.aca0f" /></Button>
                        </CardContent>
                    </Card>
                )}
            </div>

            <PackageFormDialog
                open={packageDialog}
                onOpenChange={setPackageDialog}
                rentalPackage={rentalPackage}
                branches={branches}
                manageGlobal={permissions.manageGlobal}
            />
            <PackageItemFormDialog
                open={itemDialog}
                onOpenChange={setItemDialog}
                rentalPackage={rentalPackage}
                products={products}
            />
            <PackageRateFormDialog
                open={rateDialog}
                onOpenChange={setRateDialog}
                rentalPackage={rentalPackage}
                rate={editingRate}
                ratePlans={ratePlans}
                branches={branches}
                manageGlobal={permissions.manageGlobal}
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
    value: string;
    icon: typeof Layers3;
}) {
    return <MetricCard label={label} value={value} icon={Icon} />;
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2">{value}</p>
        </div>
    );
}

function Empty({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof Layers3;
    title: string;
    description: string;
}) {
    return (
        <div className="py-12 text-center">
            <Icon className="mx-auto size-9 text-muted-foreground" />
            <p className="mt-4 font-medium">{title}</p>
            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

function PackageErrors({ errors }: { errors: Record<string, string> }) {
    const message =
        errors.package ??
        errors.branch_id ??
        errors.product_id ??
        errors.rate_plan_id;

    if (typeof message !== 'string') {
        return null;
    }

    return (
        <Alert variant="destructive">
            <CircleOff />
            <AlertTitle><Stage3Text k="stage3.ui.perubahan.paket.ditolak.95495" /></AlertTitle>
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
