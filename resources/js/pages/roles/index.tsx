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
import { useGlobalLocale } from '@/lib/locale-store';
import { stage3PermissionModule, stage3PermissionName } from '@/lib/stage3-display';
import { Stage3Text, stage3Translate } from '@/components/stage3-text';
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



export default function RoleIndex({
    roles,
    permissionGroups,
    permissions,
}: Props) {
    const stage3Locale = useGlobalLocale();
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
            <Head title={stage3Translate('stage3.ui.role.permission.b2491', stage3Locale)} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            <Stage3Text k="stage3.ui.administrasi.580b7" /></p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            <Stage3Text k="stage3.ui.role.permission.fb006" /></h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            <Stage3Text k="stage3.ui.role.perusahaan.berlaku.lintas.cabang.sedangkan.d6454" /></p>
                    </div>
                    {permissions.manage && (
                        <Button onClick={openCreate}>
                            <Plus />
                            <Stage3Text k="stage3.ui.buat.role.khusus.0740c" /></Button>
                    )}
                </header>

                <AccessNav current="/roles" />

                {typeof pageErrors.role === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle><Stage3Text k="stage3.ui.perubahan.role.ditolak.df6e2" /></AlertTitle>
                        <AlertDescription>{pageErrors.role}</AlertDescription>
                    </Alert>
                )}

                <Alert>
                    <ShieldCheck />
                    <AlertTitle><Stage3Text k="stage3.ui.guardrail.akses.aktif.8718c" /></AlertTitle>
                    <AlertDescription>
                        <Stage3Text k="stage3.ui.identitas.role.sistem.dikunci.tetapi.permission.1701c" /></AlertDescription>
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
                                                    <Stage3Text k="stage3.ui.sistem.991f3" /></Badge>
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
                                            ? stage3Translate('stage3.ui.correction.perusahaan.8de8b', stage3Locale)
                                            : stage3Translate('stage3.ui.correction.cabang.13874', stage3Locale)}
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
                                            <Stage3Text k="stage3.ui.penugasan.user.9393c" /></p>
                                    </div>
                                    <div>
                                        <p className="text-xl font-semibold">
                                            {role.permissions.length}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            <Stage3Text k="stage3.ui.permission.17857" /></p>
                                    </div>
                                </div>

                                <div className="flex min-h-16 flex-wrap content-start gap-1.5">
                                    {role.permissions.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            <Stage3Text k="stage3.ui.belum.memiliki.permission.06eff" /></p>
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

                                {permissions.manage && (
                                    <Button
                                        variant="outline"
                                        onClick={() => openEdit(role)}
                                    >
                                        {role.is_system ? (
                                            <ShieldCheck />
                                        ) : (
                                            <Pencil />
                                        )}
                                        {role.is_system
                                            ? stage3Translate('stage3.ui.correction.atur.permission.d5a5c', stage3Locale)
                                            : 'Edit role'}
                                    </Button>
                                )}
                                {role.is_system && (
                                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <LockKeyhole className="size-4" />
                                        <Stage3Text k="stage3.ui.identitas.role.dikunci.permission.dapat.disesua.4e87e" /></p>
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
                                <Stage3Text k="stage3.ui.role.belum.tersedia.72b26" /></p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                <Stage3Text k="stage3.ui.jalankan.foundation.seeder.atau.buat.role.khusu.af636" /></p>
                        </CardContent>
                    </Card>
                )}
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingRole?.is_system
                                ? stage3Translate('stage3.ui.correction.atur.permission.d5a5c', stage3Locale) + ' ' + editingRole.name
                                : editingRole
                                  ? stage3Translate('stage3.ui.correction.edit.53016', stage3Locale) + ' ' + editingRole.name
                                  : stage3Translate('stage3.ui.correction.buat.role.khusus.0740c', stage3Locale)}
                        </DialogTitle>
                        <DialogDescription>
                            {editingRole?.is_system
                                ? stage3Translate('stage3.ui.correction.nama.slug.dan.scope.role.sistem.tetap.dikun.3ca25', stage3Locale)
                                : stage3Translate('stage3.ui.correction.berikan.permission.minimum.yang.diperlukan..304a0', stage3Locale)}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="grid gap-6">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <FormField
                                label={stage3Translate('stage3.ui.nama.role.dfe12', stage3Locale)}
                                name="role_name"
                                error={form.errors.name}
                            >
                                <Input
                                    id="role_name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    disabled={editingRole?.is_system}
                                    required
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.slug.094da', stage3Locale)}
                                name="role_slug"
                                error={form.errors.slug}
                            >
                                <Input
                                    id="role_slug"
                                    value={form.data.slug}
                                    onChange={(event) =>
                                        form.setData('slug', event.target.value)
                                    }
                                    placeholder={stage3Translate('stage3.ui.correction.otomatis.dari.nama.14543', stage3Locale)}
                                    disabled={editingRole?.is_system}
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate('stage3.ui.scope.akses.a9959', stage3Locale)}
                                name="role_scope"
                                error={form.errors.scope}
                            >
                                <Select
                                    value={form.data.scope}
                                    onValueChange={(value) =>
                                        setScope(value as Role['scope'])
                                    }
                                    disabled={
                                        editingRole?.is_system ||
                                        (Boolean(editingRole?.users_count) &&
                                            editingRole?.scope !== undefined)
                                    }
                                >
                                    <SelectTrigger id="role_scope">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="branch">
                                            <Stage3Text k="stage3.ui.per.cabang.ed167" /></SelectItem>
                                        <SelectItem value="company">
                                            <Stage3Text k="stage3.ui.seluruh.perusahaan.fac1d" /></SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        <div>
                            <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <h3 className="font-medium">
                                        <Stage3Text k="stage3.ui.matriks.permission.134f1" /></h3>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        <Stage3Text k="stage3.ui.permission.dikelompokkan.berdasarkan.modul.bisn.10000" /></p>
                                </div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    {form.data.permission_ids.length} <Stage3Text k="stage3.ui.dipilih.3edb9" /></p>
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
                                                {stage3PermissionModule(module, stage3Locale)}
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
                                                                            stage3PermissionName(permission.slug, permission.name, stage3Locale)
                                                                        }
                                                                        {required &&
                                                                            stage3Translate('stage3.ui.correction.confirm.required', stage3Locale)}
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
                                <Stage3Text k="stage3.ui.batal.14335" /></Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? stage3Translate('stage3.ui.correction.menyimpan.92e24', stage3Locale)
                                    : editingRole?.is_system
                                      ? 'Simpan permission'
                                      : editingRole
                                        ? stage3Translate('stage3.ui.correction.simpan.perubahan.099b3', stage3Locale)
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
