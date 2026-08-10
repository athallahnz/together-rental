import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    CheckCircle2,
    CircleOff,
    KeyRound,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    UserRoundCog,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { AccessNav } from '@/components/access-nav';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
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
import type { AccessBranch, AccessRole, Pagination } from '@/types';

type BranchMembership = AccessBranch & {
    pivot?: { is_default: boolean; is_active: boolean };
};

type RoleAssignment = AccessRole & {
    pivot?: { branch_id: number | null };
};

type EmployeeSummary = {
    id: number;
    employee_number: string;
    name: string;
    status: string;
    primary_branch?: AccessBranch | null;
    position?: { id: number; code: string; name: string } | null;
};

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'inactive' | 'suspended';
    two_factor_confirmed_at: string | null;
    created_at: string;
    current_branch: AccessBranch | null;
    branches: BranchMembership[];
    roles: RoleAssignment[];
    employee: EmployeeSummary | null;
};

type EmployeeOption = {
    id: number;
    user_id: number | null;
    employee_number: string;
    name: string;
    status: string;
    user?: { id: number; name: string; email: string } | null;
};

type BranchAccess = { branch_id: number; role_id: number };

type UserForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    status: 'active' | 'inactive' | 'suspended';
    employee_id: number | null;
    company_role_id: number | null;
    default_branch_id: number | null;
    branch_access: BranchAccess[];
};

type Props = {
    users: Pagination<ManagedUser>;
    summary: {
        total: number;
        active: number;
        inactive: number;
        twoFactor: number;
    };
    filters: { search: string; status: string; branch_id: number | null };
    branches: AccessBranch[];
    roles: AccessRole[];
    availableEmployees: EmployeeOption[];
    permissions: { manage: boolean; viewRoles: boolean };
    currentUserId: number;
};

function emptyForm(branches: AccessBranch[]): UserForm {
    return {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        status: 'active',
        employee_id: null,
        company_role_id: null,
        default_branch_id: branches[0]?.id ?? null,
        branch_access: [],
    };
}

function userToForm(user: ManagedUser): UserForm {
    return {
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        status: user.status,
        employee_id: user.employee?.id ?? null,
        company_role_id:
            user.roles.find(
                (role) => role.scope === 'company' && !role.pivot?.branch_id,
            )?.id ?? null,
        default_branch_id:
            user.current_branch?.id ?? user.branches[0]?.id ?? null,
        branch_access: user.roles
            .filter(
                (role) =>
                    role.scope === 'branch' && role.pivot?.branch_id != null,
            )
            .map((role) => ({
                branch_id: Number(role.pivot?.branch_id),
                role_id: role.id,
            })),
    };
}

export default function UserIndex({
    users,
    summary,
    filters,
    branches,
    roles,
    availableEmployees,
    permissions,
    currentUserId,
}: Props) {
    const { errors: pageErrors } = usePage().props;
    const confirm = useConfirmDialog();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<ManagedUser | null>(null);
    const [search, setSearch] = useState(filters.search);
    const form = useForm<UserForm>(emptyForm(branches));
    const companyRoles = roles.filter((role) => role.scope === 'company');
    const branchRoles = roles.filter((role) => role.scope === 'branch');

    const employeeOptions = useMemo(
        () =>
            availableEmployees.filter(
                (employee) =>
                    employee.user_id === null ||
                    employee.user_id === editingUser?.id,
            ),
        [availableEmployees, editingUser],
    );

    const openCreate = () => {
        setEditingUser(null);
        form.setData(emptyForm(branches));
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (user: ManagedUser) => {
        setEditingUser(user);
        form.setData(userToForm(user));
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
        };

        if (editingUser) {
            form.put(`/users/${editingUser.id}`, options);

            return;
        }

        form.post('/users', options);
    };

    const applyFilters = (
        status = filters.status,
        branchId = filters.branch_id,
    ) => {
        router.get(
            '/users',
            {
                search: search || undefined,
                status: status || undefined,
                branch_id: branchId || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const toggleBranch = (branchId: number, enabled: boolean) => {
        const current = form.data.branch_access.filter(
            (item) => item.branch_id !== branchId,
        );

        if (!enabled) {
            form.setData('branch_access', current);

            return;
        }

        if (!branchRoles[0]) {
            return;
        }

        form.setData('branch_access', [
            ...current,
            { branch_id: branchId, role_id: branchRoles[0].id },
        ]);
    };

    const updateBranchRole = (branchId: number, roleId: number) => {
        form.setData(
            'branch_access',
            form.data.branch_access.map((item) =>
                item.branch_id === branchId
                    ? { ...item, role_id: roleId }
                    : item,
            ),
        );
    };

    const toggleStatus = async (user: ManagedUser) => {
        const activating = user.status !== 'active';
        const confirmed = await confirm({
            title: activating ? 'Aktifkan akun?' : 'Nonaktifkan akun?',
            description: `Akun ${user.name} akan ${activating ? 'diaktifkan kembali' : 'dinonaktifkan dan tidak dapat masuk ke sistem'}.`,
            confirmLabel: activating ? 'Aktifkan' : 'Nonaktifkan',
            variant: activating ? 'default' : 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.patch(`/users/${user.id}/status`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Akun Pengguna" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Modul Pengguna & Akses
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Akun pengguna
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kelola akun, status login, cabang kerja, serta role
                            perusahaan dan role per cabang secara terpusat.
                        </p>
                    </div>
                    {permissions.manage && (
                        <Button onClick={openCreate}>
                            <Plus />
                            Tambah pengguna
                        </Button>
                    )}
                </header>

                <AccessNav current="/users" />

                {typeof pageErrors.user === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>Perubahan pengguna ditolak</AlertTitle>
                        <AlertDescription>{pageErrors.user}</AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        {
                            label: 'Total akun',
                            value: summary.total,
                            icon: Users,
                        },
                        {
                            label: 'Akun aktif',
                            value: summary.active,
                            icon: CheckCircle2,
                        },
                        {
                            label: 'Nonaktif / suspend',
                            value: summary.inactive,
                            icon: CircleOff,
                        },
                        {
                            label: '2FA aktif',
                            value: summary.twoFactor,
                            icon: ShieldCheck,
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
                            <CardTitle>Daftar akun</CardTitle>
                            <CardDescription>
                                Akun sendiri dikelola melalui Pengaturan Profil
                                agar hak akses sesi aktif tetap aman.
                            </CardDescription>
                        </div>
                        <div
                            data-slot="filter-grid"
                            className="grid items-end gap-3 rounded-xl border bg-muted/25 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(260px,1fr)_minmax(180px,0.45fr)_minmax(160px,0.4fr)]"
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
                                        placeholder="Nama, email, atau NIK"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="icon"
                                    variant="outline"
                                    aria-label="Cari pengguna"
                                >
                                    <Search />
                                </Button>
                            </form>
                            <Select
                                value={filters.branch_id?.toString() ?? 'all'}
                                onValueChange={(value) =>
                                    applyFilters(
                                        filters.status,
                                        value === 'all' ? null : Number(value),
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua cabang
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
                                        Semua status
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Aktif
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Nonaktif
                                    </SelectItem>
                                    <SelectItem value="suspended">
                                        Suspend
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {users.data.length === 0 ? (
                            <div className="py-16 text-center">
                                <UserRoundCog className="mx-auto size-9 text-muted-foreground" />
                                <p className="mt-4 font-medium">
                                    Pengguna tidak ditemukan
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Ubah filter atau tambahkan akun baru.
                                </p>
                            </div>
                        ) : (
                            <div className="grid gap-3">
                                {users.data.map((user) => {
                                    const isSelf = user.id === currentUserId;
                                    const companyRole = user.roles.find(
                                        (role) =>
                                            role.scope === 'company' &&
                                            !role.pivot?.branch_id,
                                    );

                                    return (
                                        <article
                                            key={user.id}
                                            className="grid gap-4 rounded-xl border p-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto] lg:items-center"
                                        >
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h2 className="truncate font-semibold">
                                                        {user.name}
                                                    </h2>
                                                    {isSelf && (
                                                        <Badge>
                                                            Sesi aktif
                                                        </Badge>
                                                    )}
                                                    <StatusBadge
                                                        status={user.status}
                                                    />
                                                    {user.two_factor_confirmed_at && (
                                                        <Badge variant="outline">
                                                            <KeyRound />
                                                            2FA
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="mt-1 truncate text-sm text-muted-foreground">
                                                    {user.email}
                                                </p>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {user.employee
                                                        ? `${user.employee.employee_number} · ${user.employee.name}`
                                                        : 'Belum terhubung ke data karyawan'}
                                                </p>
                                            </div>

                                            <div className="grid gap-2 text-sm">
                                                <p className="flex items-center gap-2">
                                                    <BadgeCheck className="size-4 text-muted-foreground" />
                                                    {companyRole?.name ??
                                                        'Akses berbasis cabang'}
                                                </p>
                                                <p className="flex items-center gap-2 text-muted-foreground">
                                                    <Building2 className="size-4" />
                                                    {user.current_branch
                                                        ? `${user.current_branch.code} · ${user.current_branch.name}`
                                                        : 'Cabang kerja belum ditetapkan'}
                                                    {user.branches.length > 1 &&
                                                        ` +${user.branches.length - 1}`}
                                                </p>
                                            </div>

                                            {permissions.manage && (
                                                <div className="flex flex-wrap gap-2 lg:justify-end">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            openEdit(user)
                                                        }
                                                        disabled={isSelf}
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant={
                                                            user.status ===
                                                            'active'
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                        onClick={() =>
                                                            toggleStatus(user)
                                                        }
                                                        disabled={isSelf}
                                                    >
                                                        {user.status ===
                                                        'active' ? (
                                                            <CircleOff />
                                                        ) : (
                                                            <CheckCircle2 />
                                                        )}
                                                        {user.status ===
                                                        'active'
                                                            ? 'Nonaktifkan'
                                                            : 'Aktifkan'}
                                                    </Button>
                                                </div>
                                            )}
                                        </article>
                                    );
                                })}
                            </div>
                        )}

                        <PaginationLinks
                            links={users.links}
                            from={users.from}
                            to={users.to}
                            total={users.total}
                        />
                    </CardContent>
                </Card>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingUser
                                ? `Edit ${editingUser.name}`
                                : 'Tambah pengguna'}
                        </DialogTitle>
                        <DialogDescription>
                            Role perusahaan berlaku ke semua cabang. Role cabang
                            dapat dibedakan untuk setiap lokasi.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="grid gap-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Nama pengguna"
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
                            </FormField>
                            <FormField
                                label="Email login"
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
                                    required
                                />
                            </FormField>
                            <FormField
                                label={
                                    editingUser
                                        ? 'Password baru (opsional)'
                                        : 'Password'
                                }
                                name="password"
                                error={form.errors.password}
                            >
                                <Input
                                    id="password"
                                    type="password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                    required={!editingUser}
                                />
                            </FormField>
                            <FormField
                                label="Konfirmasi password"
                                name="password_confirmation"
                            >
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(event) =>
                                        form.setData(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                    required={
                                        !editingUser ||
                                        form.data.password.length > 0
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Status akun"
                                name="status"
                                error={form.errors.status}
                            >
                                <Select
                                    value={form.data.status}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'status',
                                            value as UserForm['status'],
                                        )
                                    }
                                >
                                    <SelectTrigger id="status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">
                                            Aktif
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            Nonaktif
                                        </SelectItem>
                                        <SelectItem value="suspended">
                                            Suspend
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label="Data karyawan"
                                name="employee_id"
                                error={form.errors.employee_id}
                            >
                                <Select
                                    value={
                                        form.data.employee_id?.toString() ??
                                        'none'
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'employee_id',
                                            value === 'none'
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger id="employee_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Tidak dihubungkan
                                        </SelectItem>
                                        {employeeOptions.map((employee) => (
                                            <SelectItem
                                                key={employee.id}
                                                value={employee.id.toString()}
                                            >
                                                {employee.employee_number} ·{' '}
                                                {employee.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        <div className="grid gap-4 rounded-xl border p-4">
                            <div>
                                <p className="font-medium">Akses perusahaan</p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Pilih jika pengguna perlu akses lintas
                                    cabang. Kosongkan untuk akses per cabang.
                                </p>
                            </div>
                            <Select
                                value={
                                    form.data.company_role_id?.toString() ??
                                    'none'
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'company_role_id',
                                        value === 'none' ? null : Number(value),
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Tanpa role perusahaan
                                    </SelectItem>
                                    {companyRoles.map((role) => (
                                        <SelectItem
                                            key={role.id}
                                            value={role.id.toString()}
                                        >
                                            {role.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.company_role_id} />
                        </div>

                        <div className="grid gap-4 rounded-xl border p-4">
                            <div>
                                <p className="font-medium">Akses cabang</p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Centang cabang dan tentukan role operasional
                                    untuk masing-masing cabang.
                                </p>
                            </div>
                            {branches.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada cabang aktif.
                                </p>
                            ) : (
                                <div className="grid gap-3">
                                    {branches.map((branch) => {
                                        const assignment =
                                            form.data.branch_access.find(
                                                (item) =>
                                                    item.branch_id ===
                                                    branch.id,
                                            );

                                        return (
                                            <div
                                                key={branch.id}
                                                className="grid gap-3 rounded-lg bg-muted/40 p-3 sm:grid-cols-[1fr_220px] sm:items-center"
                                            >
                                                <label className="flex items-center gap-3 text-sm font-medium">
                                                    <Checkbox
                                                        checked={Boolean(
                                                            assignment,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleBranch(
                                                                branch.id,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    <span>
                                                        {branch.code} ·{' '}
                                                        {branch.name}
                                                    </span>
                                                </label>
                                                {assignment && (
                                                    <Select
                                                        value={assignment.role_id.toString()}
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updateBranchRole(
                                                                branch.id,
                                                                Number(value),
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {branchRoles.map(
                                                                (role) => (
                                                                    <SelectItem
                                                                        key={
                                                                            role.id
                                                                        }
                                                                        value={role.id.toString()}
                                                                    >
                                                                        {
                                                                            role.name
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                            <InputError message={form.errors.branch_access} />
                        </div>

                        <FormField
                            label="Cabang kerja default"
                            name="default_branch_id"
                            error={form.errors.default_branch_id}
                        >
                            <Select
                                value={
                                    form.data.default_branch_id?.toString() ??
                                    'none'
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'default_branch_id',
                                        value === 'none' ? null : Number(value),
                                    )
                                }
                            >
                                <SelectTrigger id="default_branch_id">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Belum ditetapkan
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
                        </FormField>

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
                                    : editingUser
                                      ? 'Simpan perubahan'
                                      : 'Buat pengguna'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function StatusBadge({ status }: { status: ManagedUser['status'] }) {
    return (
        <Badge variant={status === 'active' ? 'outline' : 'secondary'}>
            {status === 'active'
                ? 'Aktif'
                : status === 'suspended'
                  ? 'Suspend'
                  : 'Nonaktif'}
        </Badge>
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
