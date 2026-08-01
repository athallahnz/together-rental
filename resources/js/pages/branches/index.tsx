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
            title: activating ? 'Aktifkan cabang?' : 'Nonaktifkan cabang?',
            description: `${branch.code} · ${branch.name} akan ${activating ? 'diaktifkan kembali' : 'dinonaktifkan dari operasional aktif'}.`,
            confirmLabel: activating ? 'Aktifkan' : 'Nonaktifkan',
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
            <Head title="Manajemen Cabang" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Database Rental Management V2
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Manajemen Cabang
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kelola identitas, status operasional, akses, dan
                            konteks kerja setiap cabang dari satu database
                            terpusat.
                        </p>
                    </div>
                    {permissions.manage && (
                        <Button onClick={openCreate}>
                            <Plus />
                            Tambah cabang
                        </Button>
                    )}
                </header>

                {typeof pageErrors.branch === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>Perubahan cabang ditolak</AlertTitle>
                        <AlertDescription>{pageErrors.branch}</AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        {
                            label: 'Total cabang',
                            value: summary.total,
                            icon: Building2,
                        },
                        {
                            label: 'Cabang aktif',
                            value: summary.active,
                            icon: CheckCircle2,
                        },
                        {
                            label: 'Nonaktif',
                            value: summary.inactive,
                            icon: CircleOff,
                        },
                        {
                            label: 'Tampil publik',
                            value: summary.public,
                            icon: Globe2,
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

                <Card>
                    <CardHeader className="gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Daftar cabang</CardTitle>
                            <CardDescription>
                                Cabang aktif dapat dipilih sebagai konteks kerja
                                pada sidebar.
                            </CardDescription>
                        </div>
                        <div className="flex flex-col gap-2 sm:flex-row">
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
                                        className="w-full pl-9 sm:w-64"
                                        placeholder="Kode, nama, atau kota"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="icon"
                                    aria-label="Cari cabang"
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
                                <SelectTrigger className="w-full sm:w-36">
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
                        </div>
                    </CardHeader>
                    <CardContent>
                        {branches.length === 0 ? (
                            <div className="py-16 text-center">
                                <Building2 className="mx-auto size-9 text-muted-foreground" />
                                <p className="mt-4 font-medium">
                                    Cabang tidak ditemukan
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Ubah filter pencarian atau tambahkan cabang
                                    baru.
                                </p>
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
                                                                    Aktif
                                                                    sekarang
                                                                </Badge>
                                                            )}
                                                            <Badge
                                                                variant={
                                                                    branch.is_active
                                                                        ? 'outline'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {branch.is_active
                                                                    ? 'Operasional'
                                                                    : 'Nonaktif'}
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
                                                                    ? 'Tampil publik'
                                                                    : 'Tidak publik'}
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
                                                        'Lokasi belum diisi'}
                                                </p>
                                                <p className="flex items-center gap-2">
                                                    <CalendarDays className="size-4" />
                                                    {branch.opened_at
                                                        ? `Buka ${new Intl.DateTimeFormat(
                                                              'id-ID',
                                                          ).format(
                                                              new Date(
                                                                  branch.opened_at,
                                                              ),
                                                          )}`
                                                        : 'Tanggal buka belum diisi'}
                                                </p>
                                            </div>

                                            <div className="mt-5 grid grid-cols-3 gap-2 rounded-lg bg-muted/50 p-3 text-center">
                                                <div>
                                                    <p className="text-lg font-semibold">
                                                        {branch.customers_count}
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        Pelanggan
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-lg font-semibold">
                                                        {branch.assets_count}
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        Aset
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-lg font-semibold">
                                                        {
                                                            branch.active_rentals_count
                                                        }
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        Rental aktif
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                                                <Users className="size-4" />
                                                {
                                                    branch.active_employees_count
                                                }{' '}
                                                karyawan ·{' '}
                                                {branch.active_users_count} user
                                                aktif
                                            </div>

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
                                                            Gunakan cabang
                                                        </Button>
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
                                                                Katalog publik
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                openEdit(branch)
                                                            }
                                                        >
                                                            <Pencil />
                                                            Edit
                                                        </Button>
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
                                                                ? 'Nonaktifkan'
                                                                : 'Aktifkan'}
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
                                ? `Edit cabang ${editingBranch.code}`
                                : 'Tambah cabang baru'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingBranch
                                ? 'Perbarui identitas dan lokasi cabang.'
                                : 'Setting, kas utama, dan nomor dokumen akan dibuat otomatis.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Kode cabang"
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
                                label="Nama cabang"
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
                                label="Telepon"
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
                                label="Desa/Kelurahan"
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
                                label="Kecamatan"
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
                                label="Kota/Kabupaten"
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
                                label="Provinsi"
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
                                label="Kode pos"
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
                                label="Tanggal buka"
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
                                label="Zona waktu"
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
                            label="Alamat lengkap"
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
                                        Aktifkan cabang setelah dibuat
                                    </Label>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Cabang aktif langsung tersedia pada
                                        branch switcher.
                                    </p>
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
                                Batal
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Menyimpan…'
                                    : editingBranch
                                      ? 'Simpan perubahan'
                                      : 'Buat cabang'}
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
