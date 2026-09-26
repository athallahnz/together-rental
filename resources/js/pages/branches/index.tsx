import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRightLeft,
    Building2,
    CalendarDays,
    CheckCircle2,
    CircleOff,
    Globe2,
    MapPin,
    Pencil,
    Plus,
    Search,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { useGlobalLocale } from '@/lib/locale-store';
import { formatStage3Date } from '@/lib/stage3-display';
import { Stage3Text, stage3Translate } from '@/components/stage3-text';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
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
import { MetricCard } from '@/components/ui/metric-card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Branch = {
    id: number;
    code: string;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    village: string | null;
    district: string | null;
    city: string | null;
    province: string | null;
    postal_code: string | null;
    timezone: string;
    opened_at: string | null;
    is_active: boolean;
    public_catalog_enabled: boolean;
    active_users_count: number;
    active_employees_count: number;
    customers_count: number;
    assets_count: number;
    rentals_count: number;
    active_rentals_count: number;
};

type BranchForm = {
    code: string;
    name: string;
    phone: string;
    email: string;
    address: string;
    village: string;
    district: string;
    city: string;
    province: string;
    postal_code: string;
    timezone: string;
    opened_at: string;
    is_active: boolean;
};

type Props = {
    branches: Branch[];
    summary: {
        total: number;
        active: number;
        inactive: number;
        public: number;
    };
    filters: {
        search: string;
        status: string;
    };
    permissions: {
        manage: boolean;
        switch: boolean;
    };
};

const emptyForm: BranchForm = {
    code: '',
    name: '',
    phone: '',
    email: '',
    address: '',
    village: '',
    district: '',
    city: '',
    province: '',
    postal_code: '',
    timezone: 'Asia/Jakarta',
    opened_at: '',
    is_active: true,
};

function branchToForm(branch: Branch): BranchForm {
    return {
        code: branch.code,
        name: branch.name,
        phone: branch.phone ?? '',
        email: branch.email ?? '',
        address: branch.address ?? '',
        village: branch.village ?? '',
        district: branch.district ?? '',
        city: branch.city ?? '',
        province: branch.province ?? '',
        postal_code: branch.postal_code ?? '',
        timezone: branch.timezone,
        opened_at: branch.opened_at?.slice(0, 10) ?? '',
        is_active: branch.is_active,
    };
}

export default function BranchIndex({
    branches,
    summary,
    filters,
    permissions,
}: Props) {
    const stage3Locale = useGlobalLocale();
    const { auth, errors: pageErrors } = usePage().props;
    const confirm = useConfirmDialog();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingBranch, setEditingBranch] = useState<Branch | null>(null);
    const [search, setSearch] = useState(filters.search);
    const form = useForm<BranchForm>(emptyForm);
    const currentBranchId = auth.currentBranch?.id ?? null;

    const openCreate = () => {
        setEditingBranch(null);
        form.setData(emptyForm);
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (branch: Branch) => {
        setEditingBranch(branch);
        form.setData(branchToForm(branch));
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
        };

        if (editingBranch) {
            form.put(`/branches/${editingBranch.id}`, options);

            return;
        }

        form.post('/branches', options);
    };

    const applyFilters = (status = filters.status) => {
        router.get(
            '/branches',
            {
                search: search || undefined,
                status: status || undefined,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const toggleStatus = async (branch: Branch) => {
        const activating = !branch.is_active;
        const confirmed = await confirm({
            title: activating ? stage3Translate('stage3.ui.correction.aktifkan.cabang.82c5f', stage3Locale) : stage3Translate('stage3.ui.correction.nonaktifkan.cabang.2a14d', stage3Locale),
            description: stage3Translate(
                activating ? 'stage3.ui.correction.confirm.branch.activate' : 'stage3.ui.correction.confirm.branch.deactivate',
                stage3Locale,
                { code: branch.code, name: branch.name },
            ),
            confirmLabel: activating ? stage3Translate('stage3.ui.correction.aktifkan.b2fe9', stage3Locale) : stage3Translate('stage3.ui.correction.nonaktifkan.42191', stage3Locale),
            variant: activating ? 'default' : 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.patch(
            `/branches/${branch.id}/status`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={stage3Translate('stage3.ui.manajemen.cabang.301b3', stage3Locale)} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            <Stage3Text k="stage3.ui.administrasi.580b7" /></p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            <Stage3Text k="stage3.ui.manajemen.cabang.301b3" /></h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            <Stage3Text k="stage3.ui.kelola.identitas.status.operasional.akses.dan.k.c4bed" /></p>
                    </div>
                    {permissions.manage && (
                        <Button onClick={openCreate}>
                            <Plus />
                            <Stage3Text k="stage3.ui.tambah.cabang.aff17" /></Button>
                    )}
                </header>

                {typeof pageErrors.branch === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle><Stage3Text k="stage3.ui.perubahan.cabang.ditolak.50130" /></AlertTitle>
                        <AlertDescription>{pageErrors.branch}</AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        {
                            label: stage3Translate('stage3.ui.correction.total.cabang.de76d', stage3Locale),
                            value: summary.total,
                            icon: Building2,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.cabang.aktif.a0bd6', stage3Locale),
                            value: summary.active,
                            icon: CheckCircle2,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.nonaktif.60944', stage3Locale),
                            value: summary.inactive,
                            icon: CircleOff,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.tampil.publik.db39a', stage3Locale),
                            value: summary.public,
                            icon: Globe2,
                        },
                    ].map(({ label, value, icon: Icon }) => (
                        <MetricCard
                            key={label}
                            label={label}
                            value={value}
                            icon={Icon}
                        />
                    ))}
                </section>

                <Card>
                    <CardHeader className="gap-4">
                        <div>
                            <CardTitle><Stage3Text k="stage3.ui.daftar.cabang.3636f" /></CardTitle>
                            <CardDescription>
                                <Stage3Text k="stage3.ui.cabang.aktif.dapat.dipilih.sebagai.konteks.kerj.647bf" /></CardDescription>
                        </div>
                        <div
                            data-slot="filter-grid"
                            className="grid items-end gap-3 rounded-xl border bg-muted/25 p-4 sm:grid-cols-[minmax(260px,1fr)_minmax(160px,0.35fr)]"
                        >
                            <form
                                className="flex gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    applyFilters();
                                }}
                            >
                                <div className="relative">
                                    <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        className="w-full pl-9"
                                        placeholder={stage3Translate('stage3.ui.kode.nama.atau.kota.a838a', stage3Locale)}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="icon"
                                    aria-label={stage3Translate('stage3.ui.cari.cabang.22305', stage3Locale)}
                                >
                                    <Search />
                                </Button>
                            </form>
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) =>
                                    applyFilters(value === 'all' ? '' : value)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        <Stage3Text k="stage3.ui.semua.status.baa2a" /></SelectItem>
                                    <SelectItem value="active">
                                        <Stage3Text k="stage3.ui.aktif.89f29" /></SelectItem>
                                    <SelectItem value="inactive">
                                        <Stage3Text k="stage3.ui.nonaktif.60944" /></SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {branches.length === 0 ? (
                            <div className="py-16 text-center">
                                <Building2 className="mx-auto size-9 text-muted-foreground" />
                                <p className="mt-4 font-medium">
                                    <Stage3Text k="stage3.ui.cabang.tidak.ditemukan.e29b1" /></p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    <Stage3Text k="stage3.ui.ubah.filter.pencarian.atau.tambahkan.cabang.bar.ad2fd" /></p>
                            </div>
                        ) : (
                            <div className="grid gap-4 xl:grid-cols-2">
                                {branches.map((branch) => {
                                    const isCurrent =
                                        branch.id === currentBranchId;

                                    return (
                                        <article
                                            key={branch.id}
                                            className="rounded-xl border p-5"
                                        >
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="flex min-w-0 gap-3">
                                                    <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary font-mono text-xs font-semibold text-primary-foreground">
                                                        {branch.code.slice(
                                                            0,
                                                            4,
                                                        )}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <h2 className="truncate font-semibold">
                                                                {branch.name}
                                                            </h2>
                                                            {isCurrent && (
                                                                <Badge>
                                                                    <Stage3Text k="stage3.ui.aktif.sekarang.7f10a" /></Badge>
                                                            )}
                                                            <Badge
                                                                variant={
                                                                    branch.is_active
                                                                        ? 'outline'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {branch.is_active
                                                                    ? stage3Translate('stage3.ui.correction.operasional.425f3', stage3Locale)
                                                                    : stage3Translate('stage3.ui.correction.nonaktif.60944', stage3Locale)}
                                                            </Badge>
                                                            <Badge
                                                                variant={
                                                                    branch.is_active &&
                                                                    branch.public_catalog_enabled
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {branch.is_active &&
                                                                branch.public_catalog_enabled
                                                                    ? stage3Translate('stage3.ui.correction.tampil.publik.db39a', stage3Locale)
                                                                    : stage3Translate('stage3.ui.correction.tidak.publik.f9352', stage3Locale)}
                                                            </Badge>
                                                        </div>
                                                        <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                            {branch.code}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="mt-4 grid gap-2 text-sm text-muted-foreground sm:grid-cols-2">
                                                <p className="flex items-center gap-2">
                                                    <MapPin className="size-4" />
                                                    {[
                                                        branch.city,
                                                        branch.province,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(', ') ||
                                                        stage3Translate('stage3.ui.correction.lokasi.belum.diisi.fef9c', stage3Locale)}
                                                </p>
                                                <p className="flex items-center gap-2">
                                                    <CalendarDays className="size-4" />
                                                    {branch.opened_at
                                                        ? stage3Translate('stage3.ui.correction.branch.opened', stage3Locale) + ' ' + formatStage3Date(branch.opened_at, stage3Locale)
                                                        : stage3Translate('stage3.ui.correction.tanggal.buka.belum.diisi.b1b69', stage3Locale)}
                                                </p>
                                            </div>

                                            <div className="mt-5 grid grid-cols-3 gap-2 rounded-lg bg-muted/50 p-3 text-center">
                                                <div>
                                                    <p className="text-lg font-semibold">
                                                        {branch.customers_count}
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        <Stage3Text k="stage3.ui.pelanggan.af0ab" /></p>
                                                </div>
                                                <div>
                                                    <p className="text-lg font-semibold">
                                                        {branch.assets_count}
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        <Stage3Text k="stage3.ui.aset.a2eed" /></p>
                                                </div>
                                                <div>
                                                    <p className="text-lg font-semibold">
                                                        {
                                                            branch.active_rentals_count
                                                        }
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        <Stage3Text k="stage3.ui.rental.aktif.de680" /></p>
                                                </div>
                                            </div>

                                            <div className="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                                                <Users className="size-4" />
                                                {
                                                    branch.active_employees_count
                                                }{' '}
                                                <Stage3Text k="stage3.ui.karyawan.69edd" />{' '}
                                                {branch.active_users_count} <Stage3Text k="stage3.ui.user.aktif.42bc0" /></div>

                                            <div className="mt-5 flex flex-wrap gap-2 border-t pt-4">
                                                {permissions.switch &&
                                                    branch.is_active &&
                                                    !isCurrent && (
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/branches/${branch.id}/switch`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <ArrowRightLeft />
                                                            <Stage3Text k="stage3.ui.gunakan.cabang.204cc" /></Button>
                                                    )}
                                                {permissions.manage && (
                                                    <>
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <Link
                                                                href={`/branches/${branch.id}/public-profile`}
                                                            >
                                                                <Globe2 />
                                                                <Stage3Text k="stage3.ui.katalog.publik.60e66" /></Link>
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                openEdit(branch)
                                                            }
                                                        >
                                                            <Pencil />
                                                            <Stage3Text k="stage3.ui.edit.53016" /></Button>
                                                        <Button
                                                            size="sm"
                                                            variant={
                                                                branch.is_active
                                                                    ? 'destructive'
                                                                    : 'secondary'
                                                            }
                                                            onClick={() =>
                                                                toggleStatus(
                                                                    branch,
                                                                )
                                                            }
                                                            disabled={isCurrent}
                                                        >
                                                            {branch.is_active ? (
                                                                <CircleOff />
                                                            ) : (
                                                                <CheckCircle2 />
                                                            )}
                                                            {branch.is_active
                                                                ? stage3Translate('stage3.ui.correction.nonaktifkan.42191', stage3Locale)
                                                                : stage3Translate('stage3.ui.correction.aktifkan.b2fe9', stage3Locale)}
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </article>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={dialogOpen}
                onOpenChange={(open) => {
                    if (!form.processing) {
                        setDialogOpen(open);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingBranch
                                ? stage3Translate('stage3.ui.correction.edit.53016', stage3Locale) + ' ' + editingBranch.code
                                : stage3Translate('stage3.ui.correction.tambah.cabang.baru.1145d', stage3Locale)}
                        </DialogTitle>
                        <DialogDescription>
                            {editingBranch
                                ? stage3Translate('stage3.ui.correction.perbarui.identitas.dan.lokasi.cabang.2e140', stage3Locale)
                                : stage3Translate('stage3.ui.correction.setting.kas.utama.dan.nomor.dokumen.akan.di.07983', stage3Locale)}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label={stage3Translate('stage3.ui.kode.cabang.20779', stage3Locale)}
                                name="code"
                                error={form.errors.code}
                            >
                                <Input
                                    id="code"
                                    value={form.data.code}
                                    onChange={(event) =>
                                        form.setData(
                                            'code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    maxLength={20}
                                    placeholder="MDO"
                                    required
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.nama.cabang.08a87', stage3Locale)}
                                name="name"
                                error={form.errors.name}
                            >
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    placeholder="Together Kamera Madiun"
                                    required
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.telepon.396dc', stage3Locale)}
                                name="phone"
                                error={form.errors.phone}
                            >
                                <Input
                                    id="phone"
                                    value={form.data.phone}
                                    onChange={(event) =>
                                        form.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="08xxxxxxxxxx"
                                />
                            </FormField>
                            <FormField
                                label="Email"
                                name="email"
                                error={form.errors.email}
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="cabang@togetherkamera.com"
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.desa.kelurahan.0e36d', stage3Locale)}
                                name="village"
                                error={form.errors.village}
                            >
                                <Input
                                    id="village"
                                    value={form.data.village}
                                    onChange={(event) =>
                                        form.setData(
                                            'village',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.kecamatan.b1d85', stage3Locale)}
                                name="district"
                                error={form.errors.district}
                            >
                                <Input
                                    id="district"
                                    value={form.data.district}
                                    onChange={(event) =>
                                        form.setData(
                                            'district',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.kota.kabupaten.47f65', stage3Locale)}
                                name="city"
                                error={form.errors.city}
                            >
                                <Input
                                    id="city"
                                    value={form.data.city}
                                    onChange={(event) =>
                                        form.setData('city', event.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.provinsi.7a5b1', stage3Locale)}
                                name="province"
                                error={form.errors.province}
                            >
                                <Input
                                    id="province"
                                    value={form.data.province}
                                    onChange={(event) =>
                                        form.setData(
                                            'province',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.kode.pos.0344a', stage3Locale)}
                                name="postal_code"
                                error={form.errors.postal_code}
                            >
                                <Input
                                    id="postal_code"
                                    value={form.data.postal_code}
                                    onChange={(event) =>
                                        form.setData(
                                            'postal_code',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={10}
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.tanggal.buka.fd1ce', stage3Locale)}
                                name="opened_at"
                                error={form.errors.opened_at}
                            >
                                <Input
                                    id="opened_at"
                                    type="date"
                                    value={form.data.opened_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'opened_at',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.zona.waktu.27df0', stage3Locale)}
                                name="timezone"
                                error={form.errors.timezone}
                            >
                                <Select
                                    value={form.data.timezone}
                                    onValueChange={(value) =>
                                        form.setData('timezone', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="timezone"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Asia/Jakarta">
                                            WIB · Asia/Jakarta
                                        </SelectItem>
                                        <SelectItem value="Asia/Makassar">
                                            WITA · Asia/Makassar
                                        </SelectItem>
                                        <SelectItem value="Asia/Jayapura">
                                            WIT · Asia/Jayapura
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        <FormField
                            label={stage3Translate('stage3.ui.alamat.lengkap.328e8', stage3Locale)}
                            name="address"
                            error={form.errors.address}
                        >
                            <textarea
                                id="address"
                                value={form.data.address}
                                onChange={(event) =>
                                    form.setData('address', event.target.value)
                                }
                                rows={3}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </FormField>

                        {!editingBranch && (
                            <div className="flex items-start gap-3 rounded-lg border p-4">
                                <Checkbox
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            checked === true,
                                        )
                                    }
                                />
                                <div>
                                    <Label htmlFor="is_active">
                                        <Stage3Text k="stage3.ui.aktifkan.cabang.setelah.dibuat.00c43" /></Label>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        <Stage3Text k="stage3.ui.cabang.aktif.langsung.tersedia.pada.branch.swit.4f61b" /></p>
                                </div>
                            </div>
                        )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialogOpen(false)}
                                disabled={form.processing}
                            >
                                <Stage3Text k="stage3.ui.batal.14335" /></Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? stage3Translate('stage3.ui.correction.menyimpan.92e24', stage3Locale)
                                    : editingBranch
                                      ? stage3Translate('stage3.ui.correction.simpan.perubahan.099b3', stage3Locale)
                                      : stage3Translate('stage3.ui.correction.buat.cabang.3491c', stage3Locale)}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function FormField({
    label,
    name,
    error,
    children,
}: {
    label: string;
    name: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
