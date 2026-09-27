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
import { useGlobalLocale } from '@/lib/locale-store';
import { Stage3Text, stage3Translate } from '@/components/stage3-text';
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
    const stage3Locale = useGlobalLocale();
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
            title: activating
                ? stage3Translate(
                      'stage3.ui.correction.aktifkan.akun.2711c',
                      stage3Locale,
                  )
                : stage3Translate(
                      'stage3.ui.correction.nonaktifkan.akun.53c2b',
                      stage3Locale,
                  ),
            description: stage3Translate(
                activating
                    ? 'stage3.ui.correction.confirm.user.activate'
                    : 'stage3.ui.correction.confirm.user.deactivate',
                stage3Locale,
                { name: user.name },
            ),
            confirmLabel: activating
                ? stage3Translate(
                      'stage3.ui.correction.aktifkan.b2fe9',
                      stage3Locale,
                  )
                : stage3Translate(
                      'stage3.ui.correction.nonaktifkan.42191',
                      stage3Locale,
                  ),
            variant: activating ? 'default' : 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.patch(`/users/${user.id}/status`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head
                title={stage3Translate(
                    'stage3.ui.akun.pengguna.22ab5',
                    stage3Locale,
                )}
            />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            <Stage3Text k="stage3.ui.administrasi.580b7" />
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            <Stage3Text k="stage3.ui.akun.pengguna.433cd" />
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            <Stage3Text k="stage3.ui.kelola.akun.status.login.cabang.kerja.serta.rol.97d53" />
                        </p>
                    </div>
                    {permissions.manage && (
                        <Button onClick={openCreate}>
                            <Plus />
                            <Stage3Text k="stage3.ui.tambah.pengguna.e505d" />
                        </Button>
                    )}
                </header>

                <AccessNav current="/users" />

                {typeof pageErrors.user === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>
                            <Stage3Text k="stage3.ui.perubahan.pengguna.ditolak.a7601" />
                        </AlertTitle>
                        <AlertDescription>{pageErrors.user}</AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        {
                            label: stage3Translate(
                                'stage3.ui.correction.total.akun.d3aba',
                                stage3Locale,
                            ),
                            value: summary.total,
                            icon: Users,
                        },
                        {
                            label: stage3Translate(
                                'stage3.ui.correction.akun.aktif.87263',
                                stage3Locale,
                            ),
                            value: summary.active,
                            icon: CheckCircle2,
                        },
                        {
                            label: stage3Translate(
                                'stage3.ui.correction.nonaktif.suspend.3a748',
                                stage3Locale,
                            ),
                            value: summary.inactive,
                            icon: CircleOff,
                        },
                        {
                            label: stage3Translate(
                                'stage3.ui.correction.2fa.aktif.815b3',
                                stage3Locale,
                            ),
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
                            <CardTitle>
                                <Stage3Text k="stage3.ui.daftar.akun.9dcb1" />
                            </CardTitle>
                            <CardDescription>
                                <Stage3Text k="stage3.ui.akun.sendiri.dikelola.melalui.pengaturan.profil.503e7" />
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
                                        placeholder={stage3Translate(
                                            'stage3.ui.nama.email.atau.nik.b3023',
                                            stage3Locale,
                                        )}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="icon"
                                    variant="outline"
                                    aria-label={stage3Translate(
                                        'stage3.ui.cari.pengguna.02f2f',
                                        stage3Locale,
                                    )}
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
                                        <Stage3Text k="stage3.ui.semua.cabang.27d30" />
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
                                        <Stage3Text k="stage3.ui.semua.status.baa2a" />
                                    </SelectItem>
                                    <SelectItem value="active">
                                        <Stage3Text k="stage3.ui.aktif.89f29" />
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        <Stage3Text k="stage3.ui.nonaktif.60944" />
                                    </SelectItem>
                                    <SelectItem value="suspended">
                                        <Stage3Text k="stage3.ui.suspend.b2424" />
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
                                    <Stage3Text k="stage3.ui.pengguna.tidak.ditemukan.b228a" />
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    <Stage3Text k="stage3.ui.ubah.filter.atau.tambahkan.akun.baru.ec194" />
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
                                                            <Stage3Text k="stage3.ui.sesi.aktif.a5fbb" />
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
                                                        : stage3Translate(
                                                              'stage3.ui.correction.belum.terhubung.ke.data.karyawan.20903',
                                                              stage3Locale,
                                                          )}
                                                </p>
                                            </div>

                                            <div className="grid gap-2 text-sm">
                                                <p className="flex items-center gap-2">
                                                    <BadgeCheck className="size-4 text-muted-foreground" />
                                                    {companyRole?.name ??
                                                        stage3Translate(
                                                            'stage3.ui.correction.akses.berbasis.cabang.a7198',
                                                            stage3Locale,
                                                        )}
                                                </p>
                                                <p className="flex items-center gap-2 text-muted-foreground">
                                                    <Building2 className="size-4" />
                                                    {user.current_branch
                                                        ? `${user.current_branch.code} · ${user.current_branch.name}`
                                                        : stage3Translate(
                                                              'stage3.ui.correction.cabang.kerja.belum.ditetapkan.3d0ed',
                                                              stage3Locale,
                                                          )}
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
                                                        <Stage3Text k="stage3.ui.edit.53016" />
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
                                                            ? stage3Translate(
                                                                  'stage3.ui.correction.nonaktifkan.42191',
                                                                  stage3Locale,
                                                              )
                                                            : stage3Translate(
                                                                  'stage3.ui.correction.aktifkan.b2fe9',
                                                                  stage3Locale,
                                                              )}
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
                                ? stage3Translate(
                                      'stage3.ui.correction.edit.53016',
                                      stage3Locale,
                                  ) +
                                  ' ' +
                                  editingUser.name
                                : stage3Translate(
                                      'stage3.ui.correction.tambah.pengguna.e505d',
                                      stage3Locale,
                                  )}
                        </DialogTitle>
                        <DialogDescription>
                            <Stage3Text k="stage3.ui.role.perusahaan.berlaku.ke.semua.cabang.role.ca.3adf4" />
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="grid gap-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.nama.pengguna.7f64a',
                                    stage3Locale,
                                )}
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
                                label={stage3Translate(
                                    'stage3.ui.email.login.d8f0f',
                                    stage3Locale,
                                )}
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
                                        ? stage3Translate(
                                              'stage3.ui.correction.password.baru.opsional.72cfc',
                                              stage3Locale,
                                          )
                                        : stage3Translate(
                                              'stage3.ui.correction.password.8be3c',
                                              stage3Locale,
                                          )
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
                                label={stage3Translate(
                                    'stage3.ui.konfirmasi.password.8acb9',
                                    stage3Locale,
                                )}
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
                                label={stage3Translate(
                                    'stage3.ui.status.akun.baccb',
                                    stage3Locale,
                                )}
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
                                            <Stage3Text k="stage3.ui.aktif.89f29" />
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            <Stage3Text k="stage3.ui.nonaktif.60944" />
                                        </SelectItem>
                                        <SelectItem value="suspended">
                                            <Stage3Text k="stage3.ui.suspend.b2424" />
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.data.karyawan.1a14c',
                                    stage3Locale,
                                )}
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
                                            <Stage3Text k="stage3.ui.tidak.dihubungkan.867c6" />
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
                                <p className="font-medium">
                                    <Stage3Text k="stage3.ui.akses.perusahaan.e9af8" />
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    <Stage3Text k="stage3.ui.pilih.jika.pengguna.perlu.akses.lintas.cabang.k.c32dc" />
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
                                        <Stage3Text k="stage3.ui.tanpa.role.perusahaan.a184d" />
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
                                <p className="font-medium">
                                    <Stage3Text k="stage3.ui.akses.cabang.7595d" />
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    <Stage3Text k="stage3.ui.centang.cabang.dan.tentukan.role.operasional.un.fb364" />
                                </p>
                            </div>
                            {branches.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    <Stage3Text k="stage3.ui.belum.ada.cabang.aktif.5dfa7" />
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
                            label={stage3Translate(
                                'stage3.ui.cabang.kerja.default.97237',
                                stage3Locale,
                            )}
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
                                        <Stage3Text k="stage3.ui.belum.ditetapkan.d4955" />
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
                                <Stage3Text k="stage3.ui.batal.14335" />
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? stage3Translate(
                                          'stage3.ui.correction.menyimpan.92e24',
                                          stage3Locale,
                                      )
                                    : editingUser
                                      ? stage3Translate(
                                            'stage3.ui.correction.simpan.perubahan.099b3',
                                            stage3Locale,
                                        )
                                      : stage3Translate(
                                            'stage3.ui.correction.buat.pengguna.8c72f',
                                            stage3Locale,
                                        )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function StatusBadge({ status }: { status: ManagedUser['status'] }) {
    const stage3Locale = useGlobalLocale();

    return (
        <Badge variant={status === 'active' ? 'outline' : 'secondary'}>
            {status === 'active'
                ? stage3Translate(
                      'stage3.ui.correction.aktif.89f29',
                      stage3Locale,
                  )
                : status === 'suspended'
                  ? stage3Translate(
                        'stage3.ui.correction.suspend.b2424',
                        stage3Locale,
                    )
                  : stage3Translate(
                        'stage3.ui.correction.nonaktif.60944',
                        stage3Locale,
                    )}
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
