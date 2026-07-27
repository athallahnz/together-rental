import { Head, useForm, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    CircleOff,
    LockKeyhole,
    Pencil,
    Plus,
    ShieldCheck,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { AccessNav } from '@/components/access-nav';
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

type Permission = {
    id: number;
    name: string;
    slug: string;
    module: string;
    description: string | null;
};

type Role = {
    id: number;
    name: string;
    slug: string;
    scope: 'company' | 'branch';
    is_system: boolean;
    users_count: number;
    permissions: Permission[];
};

type RoleForm = {
    name: string;
    slug: string;
    scope: Role['scope'];
    permission_ids: number[];
};

type Props = {
    roles: Role[];
    permissionGroups: Record<string, Permission[]>;
    permissions: { manage: boolean; manageUsers: boolean };
};

const emptyForm: RoleForm = {
    name: '',
    slug: '',
    scope: 'branch',
    permission_ids: [],
};

const moduleLabels: Record<string, string> = {
    branches: 'Cabang',
    users: 'Pengguna',
    roles: 'Role & permission',
    customers: 'Pelanggan',
    catalog: 'Katalog',
    inventory: 'Inventaris',
    bookings: 'Booking',
    rentals: 'Rental',
    finance: 'Keuangan',
    reports: 'Laporan',
    settings: 'Pengaturan',
    legacy_imports: 'Legacy import',
};

export default function RoleIndex({
    roles,
    permissionGroups,
    permissions,
}: Props) {
    const { errors: pageErrors } = usePage().props;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const form = useForm<RoleForm>(emptyForm);
    const allPermissions = useMemo(
        () => Object.values(permissionGroups).flat(),
        [permissionGroups],
    );
    const switchPermission = allPermissions.find(
        (permission) => permission.slug === 'branches.switch',
    );

    const openCreate = () => {
        setEditingRole(null);
        form.setData({
            ...emptyForm,
            permission_ids: switchPermission ? [switchPermission.id] : [],
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (role: Role) => {
        setEditingRole(role);
        form.setData({
            name: role.name,
            slug: role.slug,
            scope: role.scope,
            permission_ids: role.permissions.map((permission) => permission.id),
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
        };

        if (editingRole) {
            form.put(`/roles/${editingRole.id}`, options);

            return;
        }

        form.post('/roles', options);
    };

    const setScope = (scope: Role['scope']) => {
        form.setData((current) => ({
            ...current,
            scope,
            permission_ids:
                scope === 'branch' && switchPermission
                    ? [
                          ...new Set([
                              ...current.permission_ids,
                              switchPermission.id,
                          ]),
                      ]
                    : current.permission_ids,
        }));
    };

    const togglePermission = (permission: Permission, enabled: boolean) => {
        if (
            form.data.scope === 'branch' &&
            permission.slug === 'branches.switch'
        ) {
            return;
        }

        form.setData(
            'permission_ids',
            enabled
                ? [...new Set([...form.data.permission_ids, permission.id])]
                : form.data.permission_ids.filter(
                      (permissionId) => permissionId !== permission.id,
                  ),
        );
    };

    return (
        <>
            <Head title="Role & Permission" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Modul Pengguna & Akses
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Role & permission
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Role perusahaan berlaku lintas cabang, sedangkan
                            role cabang membatasi kemampuan pengguna pada lokasi
                            yang ditugaskan.
                        </p>
                    </div>
                    {permissions.manage && (
                        <Button onClick={openCreate}>
                            <Plus />
                            Buat role khusus
                        </Button>
                    )}
                </header>

                <AccessNav current="/roles" />

                {typeof pageErrors.role === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>Perubahan role ditolak</AlertTitle>
                        <AlertDescription>{pageErrors.role}</AlertDescription>
                    </Alert>
                )}

                <Alert>
                    <ShieldCheck />
                    <AlertTitle>Guardrail akses aktif</AlertTitle>
                    <AlertDescription>
                        Role sistem dikunci. Role scope cabang selalu membawa
                        izin berpindah ke cabang yang memang ditugaskan.
                    </AlertDescription>
                </Alert>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {roles.map((role) => (
                        <Card key={role.id}>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <CardTitle>{role.name}</CardTitle>
                                            {role.is_system && (
                                                <Badge>
                                                    <LockKeyhole />
                                                    Sistem
                                                </Badge>
                                            )}
                                        </div>
                                        <CardDescription className="mt-2 font-mono">
                                            {role.slug}
                                        </CardDescription>
                                    </div>
                                    <Badge variant="outline">
                                        {role.scope === 'company' ? (
                                            <Building2 />
                                        ) : (
                                            <BadgeCheck />
                                        )}
                                        {role.scope === 'company'
                                            ? 'Perusahaan'
                                            : 'Cabang'}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid grid-cols-2 gap-3 rounded-lg bg-muted/50 p-3 text-center">
                                    <div>
                                        <p className="text-xl font-semibold">
                                            {role.users_count}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Penugasan user
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xl font-semibold">
                                            {role.permissions.length}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Permission
                                        </p>
                                    </div>
                                </div>

                                <div className="flex min-h-16 flex-wrap content-start gap-1.5">
                                    {role.permissions.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Belum memiliki permission.
                                        </p>
                                    ) : (
                                        role.permissions
                                            .slice(0, 8)
                                            .map((permission) => (
                                                <Badge
                                                    key={permission.id}
                                                    variant="secondary"
                                                >
                                                    {permission.slug}
                                                </Badge>
                                            ))
                                    )}
                                    {role.permissions.length > 8 && (
                                        <Badge variant="outline">
                                            +{role.permissions.length - 8}
                                        </Badge>
                                    )}
                                </div>

                                {permissions.manage && !role.is_system && (
                                    <Button
                                        variant="outline"
                                        onClick={() => openEdit(role)}
                                    >
                                        <Pencil />
                                        Edit role
                                    </Button>
                                )}
                                {role.is_system && (
                                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <LockKeyhole className="size-4" />
                                        Dikelola oleh foundation seeder.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </section>

                {roles.length === 0 && (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <Users className="mx-auto size-9 text-muted-foreground" />
                            <p className="mt-4 font-medium">
                                Role belum tersedia
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Jalankan foundation seeder atau buat role
                                khusus.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingRole
                                ? `Edit ${editingRole.name}`
                                : 'Buat role khusus'}
                        </DialogTitle>
                        <DialogDescription>
                            Berikan permission minimum yang diperlukan sesuai
                            tanggung jawab pengguna.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="grid gap-6">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <FormField
                                label="Nama role"
                                name="role_name"
                                error={form.errors.name}
                            >
                                <Input
                                    id="role_name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Slug"
                                name="role_slug"
                                error={form.errors.slug}
                            >
                                <Input
                                    id="role_slug"
                                    value={form.data.slug}
                                    onChange={(event) =>
                                        form.setData('slug', event.target.value)
                                    }
                                    placeholder="otomatis-dari-nama"
                                />
                            </FormField>
                            <FormField
                                label="Scope akses"
                                name="role_scope"
                                error={form.errors.scope}
                            >
                                <Select
                                    value={form.data.scope}
                                    onValueChange={(value) =>
                                        setScope(value as Role['scope'])
                                    }
                                    disabled={
                                        Boolean(editingRole?.users_count) &&
                                        editingRole?.scope !== undefined
                                    }
                                >
                                    <SelectTrigger id="role_scope">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="branch">
                                            Per cabang
                                        </SelectItem>
                                        <SelectItem value="company">
                                            Seluruh perusahaan
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        <div>
                            <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <h3 className="font-medium">
                                        Matriks permission
                                    </h3>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Permission dikelompokkan berdasarkan
                                        modul bisnis.
                                    </p>
                                </div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    {form.data.permission_ids.length} dipilih
                                </p>
                            </div>
                            <InputError
                                message={form.errors.permission_ids}
                                className="mt-2"
                            />
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                {Object.entries(permissionGroups).map(
                                    ([module, modulePermissions]) => (
                                        <section
                                            key={module}
                                            className="rounded-xl border p-4"
                                        >
                                            <h4 className="font-medium">
                                                {moduleLabels[module] ?? module}
                                            </h4>
                                            <div className="mt-3 grid gap-3">
                                                {modulePermissions.map(
                                                    (permission) => {
                                                        const required =
                                                            form.data.scope ===
                                                                'branch' &&
                                                            permission.slug ===
                                                                'branches.switch';

                                                        return (
                                                            <label
                                                                key={
                                                                    permission.id
                                                                }
                                                                className="flex items-start gap-3 rounded-lg p-2 hover:bg-muted/60"
                                                            >
                                                                <Checkbox
                                                                    checked={form.data.permission_ids.includes(
                                                                        permission.id,
                                                                    )}
                                                                    disabled={
                                                                        required
                                                                    }
                                                                    onCheckedChange={(
                                                                        checked,
                                                                    ) =>
                                                                        togglePermission(
                                                                            permission,
                                                                            checked ===
                                                                                true,
                                                                        )
                                                                    }
                                                                />
                                                                <span className="min-w-0">
                                                                    <span className="block text-sm font-medium">
                                                                        {
                                                                            permission.name
                                                                        }
                                                                        {required &&
                                                                            ' · wajib'}
                                                                    </span>
                                                                    <span className="mt-0.5 block font-mono text-[11px] text-muted-foreground">
                                                                        {
                                                                            permission.slug
                                                                        }
                                                                    </span>
                                                                    {permission.description && (
                                                                        <span className="mt-1 block text-xs text-muted-foreground">
                                                                            {
                                                                                permission.description
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </span>
                                                            </label>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        </section>
                                    ),
                                )}
                            </div>
                        </div>

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
                                    : editingRole
                                      ? 'Simpan perubahan'
                                      : 'Buat role'}
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
