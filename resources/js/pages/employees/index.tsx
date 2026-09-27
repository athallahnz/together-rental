import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CheckCircle2,
    CircleOff,
    ContactRound,
    Link2,
    Pencil,
    Plus,
    Search,
    UserRoundCheck,
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
import type { AccessBranch, Pagination } from '@/types';

type Position = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    employees_count: number;
};

type UserOption = {
    id: number;
    name: string;
    email: string;
    status: string;
};

type Employee = {
    id: number;
    employee_number: string;
    name: string;
    identity_number: string | null;
    gender: 'male' | 'female' | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    birth_place: string | null;
    birth_date: string | null;
    joined_at: string | null;
    ended_at: string | null;
    status: 'active' | 'inactive' | 'leave' | 'terminated';
    primary_branch_id: number | null;
    position_id: number | null;
    user_id: number | null;
    primary_branch: AccessBranch | null;
    position: Pick<Position, 'id' | 'code' | 'name'> | null;
    user: UserOption | null;
};

type EmployeeForm = {
    employee_number: string;
    name: string;
    identity_number: string;
    gender: 'male' | 'female' | '';
    phone: string;
    email: string;
    address: string;
    birth_place: string;
    birth_date: string;
    joined_at: string;
    ended_at: string;
    status: Employee['status'];
    primary_branch_id: number | null;
    position_id: number | null;
    user_id: number | null;
};

type PositionForm = {
    code: string;
    name: string;
    description: string;
    is_active: boolean;
};

type Props = {
    employees: Pagination<Employee>;
    summary: {
        total: number;
        active: number;
        withoutAccount: number;
        onLeave: number;
    };
    filters: { search: string; status: string; branch_id: number | null };
    branches: AccessBranch[];
    positions: Position[];
    availableUsers: UserOption[];
    permissions: { manage: boolean; viewRoles: boolean };
};

const emptyPosition: PositionForm = {
    code: '',
    name: '',
    description: '',
    is_active: true,
};

function emptyEmployee(branches: AccessBranch[]): EmployeeForm {
    return {
        employee_number: '',
        name: '',
        identity_number: '',
        gender: '',
        phone: '',
        email: '',
        address: '',
        birth_place: '',
        birth_date: '',
        joined_at: '',
        ended_at: '',
        status: 'active',
        primary_branch_id: branches[0]?.id ?? null,
        position_id: null,
        user_id: null,
    };
}

function employeeToForm(employee: Employee): EmployeeForm {
    return {
        employee_number: employee.employee_number,
        name: employee.name,
        identity_number: employee.identity_number ?? '',
        gender: employee.gender ?? '',
        phone: employee.phone ?? '',
        email: employee.email ?? '',
        address: employee.address ?? '',
        birth_place: employee.birth_place ?? '',
        birth_date: employee.birth_date?.slice(0, 10) ?? '',
        joined_at: employee.joined_at?.slice(0, 10) ?? '',
        ended_at: employee.ended_at?.slice(0, 10) ?? '',
        status: employee.status,
        primary_branch_id: employee.primary_branch_id,
        position_id: employee.position_id,
        user_id: employee.user_id,
    };
}

export default function EmployeeIndex({
    employees,
    summary,
    filters,
    branches,
    positions,
    availableUsers,
    permissions,
}: Props) {
    const stage3Locale = useGlobalLocale();
    const { errors: pageErrors } = usePage().props;
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState(filters.search);
    const [employeeDialogOpen, setEmployeeDialogOpen] = useState(false);
    const [positionDialogOpen, setPositionDialogOpen] = useState(false);
    const [editingEmployee, setEditingEmployee] = useState<Employee | null>(
        null,
    );
    const [editingPosition, setEditingPosition] = useState<Position | null>(
        null,
    );
    const employeeForm = useForm<EmployeeForm>(emptyEmployee(branches));
    const positionForm = useForm<PositionForm>(emptyPosition);

    const userOptions = useMemo(() => {
        if (
            !editingEmployee?.user ||
            availableUsers.some((user) => user.id === editingEmployee.user?.id)
        ) {
            return availableUsers;
        }

        return [editingEmployee.user, ...availableUsers];
    }, [availableUsers, editingEmployee]);

    const openCreateEmployee = () => {
        setEditingEmployee(null);
        employeeForm.setData(emptyEmployee(branches));
        employeeForm.clearErrors();
        setEmployeeDialogOpen(true);
    };

    const openEditEmployee = (employee: Employee) => {
        setEditingEmployee(employee);
        employeeForm.setData(employeeToForm(employee));
        employeeForm.clearErrors();
        setEmployeeDialogOpen(true);
    };

    const openCreatePosition = () => {
        setEditingPosition(null);
        positionForm.setData(emptyPosition);
        positionForm.clearErrors();
        setPositionDialogOpen(true);
    };

    const openEditPosition = (position: Position) => {
        setEditingPosition(position);
        positionForm.setData({
            code: position.code,
            name: position.name,
            description: position.description ?? '',
            is_active: position.is_active,
        });
        positionForm.clearErrors();
        setPositionDialogOpen(true);
    };

    const submitEmployee = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setEmployeeDialogOpen(false),
        };

        if (editingEmployee) {
            employeeForm.put(`/employees/${editingEmployee.id}`, options);

            return;
        }

        employeeForm.post('/employees', options);
    };

    const submitPosition = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setPositionDialogOpen(false),
        };

        if (editingPosition) {
            positionForm.put(`/positions/${editingPosition.id}`, options);

            return;
        }

        positionForm.post('/positions', options);
    };

    const applyFilters = (
        status = filters.status,
        branchId = filters.branch_id,
    ) => {
        router.get(
            '/employees',
            {
                search: search || undefined,
                status: status || undefined,
                branch_id: branchId || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const toggleEmployeeStatus = async (employee: Employee) => {
        const activating = employee.status !== 'active';
        const confirmed = await confirm({
            title: activating
                ? stage3Translate(
                      'stage3.ui.correction.aktifkan.karyawan.a2548',
                      stage3Locale,
                  )
                : stage3Translate(
                      'stage3.ui.correction.nonaktifkan.karyawan.77bdf',
                      stage3Locale,
                  ),
            description: stage3Translate(
                activating
                    ? 'stage3.ui.correction.confirm.employee.activate'
                    : 'stage3.ui.correction.confirm.employee.deactivate',
                stage3Locale,
                { name: employee.name },
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

        router.patch(
            `/employees/${employee.id}/status`,
            {},
            { preserveScroll: true },
        );
    };

    const togglePositionStatus = async (position: Position) => {
        const activating = !position.is_active;
        const confirmed = await confirm({
            title: activating
                ? stage3Translate(
                      'stage3.ui.correction.aktifkan.jabatan.15cb2',
                      stage3Locale,
                  )
                : stage3Translate(
                      'stage3.ui.correction.nonaktifkan.jabatan.4df18',
                      stage3Locale,
                  ),
            description: stage3Translate(
                activating
                    ? 'stage3.ui.correction.confirm.position.activate'
                    : 'stage3.ui.correction.confirm.position.deactivate',
                stage3Locale,
                { name: position.name },
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

        router.patch(
            `/positions/${position.id}/status`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head
                title={stage3Translate(
                    'stage3.ui.karyawan.jabatan.1ff53',
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
                            <Stage3Text k="stage3.ui.karyawan.jabatan.1c11a" />
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            <Stage3Text k="stage3.ui.kelola.identitas.staf.penempatan.cabang.jabatan.e3940" />
                        </p>
                    </div>
                    {permissions.manage && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={openCreatePosition}
                            >
                                <BriefcaseBusiness />
                                <Stage3Text k="stage3.ui.tambah.jabatan.508e4" />
                            </Button>
                            <Button onClick={openCreateEmployee}>
                                <Plus />
                                <Stage3Text k="stage3.ui.tambah.karyawan.03c69" />
                            </Button>
                        </div>
                    )}
                </header>

                <AccessNav current="/employees" />

                {typeof pageErrors.employee === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>
                            <Stage3Text k="stage3.ui.perubahan.karyawan.ditolak.c3af9" />
                        </AlertTitle>
                        <AlertDescription>
                            {pageErrors.employee}
                        </AlertDescription>
                    </Alert>
                )}
                {typeof pageErrors.position === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>
                            <Stage3Text k="stage3.ui.perubahan.jabatan.ditolak.9fee6" />
                        </AlertTitle>
                        <AlertDescription>
                            {pageErrors.position}
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        {
                            label: stage3Translate(
                                'stage3.ui.correction.total.karyawan.4ebc7',
                                stage3Locale,
                            ),
                            value: summary.total,
                            icon: ContactRound,
                        },
                        {
                            label: stage3Translate(
                                'stage3.ui.correction.karyawan.aktif.acfc1',
                                stage3Locale,
                            ),
                            value: summary.active,
                            icon: CheckCircle2,
                        },
                        {
                            label: stage3Translate(
                                'stage3.ui.correction.belum.punya.akun.c465c',
                                stage3Locale,
                            ),
                            value: summary.withoutAccount,
                            icon: Link2,
                        },
                        {
                            label: stage3Translate(
                                'stage3.ui.correction.sedang.cuti.04b97',
                                stage3Locale,
                            ),
                            value: summary.onLeave,
                            icon: CircleOff,
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
                                <Stage3Text k="stage3.ui.daftar.karyawan.4c6c1" />
                            </CardTitle>
                            <CardDescription>
                                <Stage3Text k="stage3.ui.satu.karyawan.hanya.dapat.dihubungkan.ke.satu.a.f4c1e" />
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
                                            'stage3.ui.nama.nomor.telepon.2167f',
                                            stage3Locale,
                                        )}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="icon"
                                    variant="outline"
                                    aria-label={stage3Translate(
                                        'stage3.ui.cari.karyawan.d36b4',
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
                                    <SelectItem value="leave">
                                        <Stage3Text k="stage3.ui.cuti.7d69c" />
                                    </SelectItem>
                                    <SelectItem value="terminated">
                                        <Stage3Text k="stage3.ui.berakhir.6e493" />
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {employees.data.length === 0 ? (
                            <div className="py-16 text-center">
                                <ContactRound className="mx-auto size-9 text-muted-foreground" />
                                <p className="mt-4 font-medium">
                                    <Stage3Text k="stage3.ui.karyawan.tidak.ditemukan.59cd5" />
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    <Stage3Text k="stage3.ui.ubah.filter.atau.tambahkan.karyawan.baru.d39ce" />
                                </p>
                            </div>
                        ) : (
                            <div className="grid gap-3">
                                {employees.data.map((employee) => (
                                    <article
                                        key={employee.id}
                                        className="grid gap-4 rounded-xl border p-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto] lg:items-center"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="truncate font-semibold">
                                                    {employee.name}
                                                </h2>
                                                <EmployeeStatusBadge
                                                    status={employee.status}
                                                />
                                            </div>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                {employee.employee_number}
                                            </p>
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                {employee.phone ||
                                                    employee.email ||
                                                    'Kontak belum diisi'}
                                            </p>
                                        </div>
                                        <div className="grid gap-2 text-sm">
                                            <p>
                                                {employee.position
                                                    ? `${employee.position.code} · ${employee.position.name}`
                                                    : stage3Translate(
                                                          'stage3.ui.correction.jabatan.belum.ditentukan.6c7d8',
                                                          stage3Locale,
                                                      )}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {employee.primary_branch
                                                    ? `${employee.primary_branch.code} · ${employee.primary_branch.name}`
                                                    : stage3Translate(
                                                          'stage3.ui.correction.cabang.utama.belum.ditentukan.ecbfe',
                                                          stage3Locale,
                                                      )}
                                            </p>
                                            <p className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <UserRoundCheck className="size-4" />
                                                {employee.user
                                                    ? employee.user.email
                                                    : stage3Translate(
                                                          'stage3.ui.correction.belum.terhubung.ke.akun.2dce5',
                                                          stage3Locale,
                                                      )}
                                            </p>
                                        </div>
                                        {permissions.manage && (
                                            <div className="flex flex-wrap gap-2 lg:justify-end">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        openEditEmployee(
                                                            employee,
                                                        )
                                                    }
                                                >
                                                    <Pencil />
                                                    <Stage3Text k="stage3.ui.edit.53016" />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant={
                                                        employee.status ===
                                                        'active'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                    onClick={() =>
                                                        toggleEmployeeStatus(
                                                            employee,
                                                        )
                                                    }
                                                >
                                                    {employee.status ===
                                                    'active' ? (
                                                        <CircleOff />
                                                    ) : (
                                                        <CheckCircle2 />
                                                    )}
                                                    {employee.status ===
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
                                ))}
                            </div>
                        )}

                        <PaginationLinks
                            links={employees.links}
                            from={employees.from}
                            to={employees.to}
                            total={employees.total}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            <Stage3Text k="stage3.ui.master.jabatan.07b9a" />
                        </CardTitle>
                        <CardDescription>
                            <Stage3Text k="stage3.ui.jabatan.nonaktif.tetap.dipertahankan.untuk.menj.f73c6" />
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {positions.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                <Stage3Text k="stage3.ui.belum.ada.data.jabatan.73c48" />
                            </p>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {positions.map((position) => (
                                    <article
                                        key={position.id}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="font-semibold">
                                                        {position.name}
                                                    </h3>
                                                    <Badge
                                                        variant={
                                                            position.is_active
                                                                ? 'outline'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {position.is_active
                                                            ? stage3Translate(
                                                                  'stage3.ui.correction.aktif.89f29',
                                                                  stage3Locale,
                                                              )
                                                            : stage3Translate(
                                                                  'stage3.ui.correction.nonaktif.60944',
                                                                  stage3Locale,
                                                              )}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                    {position.code}
                                                </p>
                                            </div>
                                            <Badge variant="secondary">
                                                {position.employees_count}{' '}
                                                <Stage3Text k="stage3.ui.karyawan.87c78" />
                                            </Badge>
                                        </div>
                                        <p className="mt-3 min-h-10 text-sm text-muted-foreground">
                                            {position.description ||
                                                'Tanpa deskripsi.'}
                                        </p>
                                        {permissions.manage && (
                                            <div className="mt-4 flex gap-2 border-t pt-3">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        openEditPosition(
                                                            position,
                                                        )
                                                    }
                                                >
                                                    <Pencil />
                                                    <Stage3Text k="stage3.ui.edit.53016" />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        togglePositionStatus(
                                                            position,
                                                        )
                                                    }
                                                >
                                                    {position.is_active
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
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={employeeDialogOpen}
                onOpenChange={setEmployeeDialogOpen}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingEmployee
                                ? stage3Translate(
                                      'stage3.ui.correction.edit.53016',
                                      stage3Locale,
                                  ) +
                                  ' ' +
                                  editingEmployee.name
                                : stage3Translate(
                                      'stage3.ui.correction.tambah.karyawan.03c69',
                                      stage3Locale,
                                  )}
                        </DialogTitle>
                        <DialogDescription>
                            <Stage3Text k="stage3.ui.akun.login.bersifat.opsional.dan.dapat.dihubung.07f1f" />
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitEmployee} className="grid gap-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.nomor.karyawan.752b5',
                                    stage3Locale,
                                )}
                                name="employee_number"
                                error={employeeForm.errors.employee_number}
                            >
                                <Input
                                    id="employee_number"
                                    value={employeeForm.data.employee_number}
                                    onChange={(event) =>
                                        employeeForm.setData(
                                            'employee_number',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.nama.lengkap.c3587',
                                    stage3Locale,
                                )}
                                name="name"
                                error={employeeForm.errors.name}
                            >
                                <Input
                                    id="name"
                                    value={employeeForm.data.name}
                                    onChange={(event) =>
                                        employeeForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.cabang.utama.f13ea',
                                    stage3Locale,
                                )}
                                name="primary_branch_id"
                                error={employeeForm.errors.primary_branch_id}
                            >
                                <Select
                                    value={
                                        employeeForm.data.primary_branch_id?.toString() ??
                                        'none'
                                    }
                                    onValueChange={(value) =>
                                        employeeForm.setData(
                                            'primary_branch_id',
                                            value === 'none'
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger id="primary_branch_id">
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
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.jabatan.6cc74',
                                    stage3Locale,
                                )}
                                name="position_id"
                                error={employeeForm.errors.position_id}
                            >
                                <Select
                                    value={
                                        employeeForm.data.position_id?.toString() ??
                                        'none'
                                    }
                                    onValueChange={(value) =>
                                        employeeForm.setData(
                                            'position_id',
                                            value === 'none'
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger id="position_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            <Stage3Text k="stage3.ui.belum.ditetapkan.d4955" />
                                        </SelectItem>
                                        {positions.map((position) => (
                                            <SelectItem
                                                key={position.id}
                                                value={position.id.toString()}
                                            >
                                                {position.code} ·{' '}
                                                {position.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.status.bae7d',
                                    stage3Locale,
                                )}
                                name="status"
                                error={employeeForm.errors.status}
                            >
                                <Select
                                    value={employeeForm.data.status}
                                    onValueChange={(value) =>
                                        employeeForm.setData(
                                            'status',
                                            value as EmployeeForm['status'],
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
                                        <SelectItem value="leave">
                                            <Stage3Text k="stage3.ui.cuti.7d69c" />
                                        </SelectItem>
                                        <SelectItem value="terminated">
                                            <Stage3Text k="stage3.ui.berakhir.6e493" />
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.akun.login.94023',
                                    stage3Locale,
                                )}
                                name="user_id"
                                error={employeeForm.errors.user_id}
                            >
                                <Select
                                    value={
                                        employeeForm.data.user_id?.toString() ??
                                        'none'
                                    }
                                    onValueChange={(value) =>
                                        employeeForm.setData(
                                            'user_id',
                                            value === 'none'
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger id="user_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            <Stage3Text k="stage3.ui.tidak.dihubungkan.867c6" />
                                        </SelectItem>
                                        {userOptions.map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={user.id.toString()}
                                            >
                                                {user.name} · {user.email}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.nomor.identitas.54fdf',
                                    stage3Locale,
                                )}
                                name="identity_number"
                                error={employeeForm.errors.identity_number}
                            >
                                <Input
                                    id="identity_number"
                                    value={employeeForm.data.identity_number}
                                    onChange={(event) =>
                                        employeeForm.setData(
                                            'identity_number',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.jenis.kelamin.64cd3',
                                    stage3Locale,
                                )}
                                name="gender"
                                error={employeeForm.errors.gender}
                            >
                                <Select
                                    value={employeeForm.data.gender || 'none'}
                                    onValueChange={(value) =>
                                        employeeForm.setData(
                                            'gender',
                                            value === 'none'
                                                ? ''
                                                : (value as 'male' | 'female'),
                                        )
                                    }
                                >
                                    <SelectTrigger id="gender">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            <Stage3Text k="stage3.ui.tidak.diisi.46951" />
                                        </SelectItem>
                                        <SelectItem value="male">
                                            <Stage3Text k="stage3.ui.laki.laki.afdcb" />
                                        </SelectItem>
                                        <SelectItem value="female">
                                            <Stage3Text k="stage3.ui.perempuan.bc797" />
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.telepon.396dc',
                                    stage3Locale,
                                )}
                                name="phone"
                                error={employeeForm.errors.phone}
                            >
                                <Input
                                    id="phone"
                                    value={employeeForm.data.phone}
                                    onChange={(event) =>
                                        employeeForm.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Email"
                                name="email"
                                error={employeeForm.errors.email}
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    value={employeeForm.data.email}
                                    onChange={(event) =>
                                        employeeForm.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.tempat.lahir.c76be',
                                    stage3Locale,
                                )}
                                name="birth_place"
                                error={employeeForm.errors.birth_place}
                            >
                                <Input
                                    id="birth_place"
                                    value={employeeForm.data.birth_place}
                                    onChange={(event) =>
                                        employeeForm.setData(
                                            'birth_place',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.tanggal.lahir.c7642',
                                    stage3Locale,
                                )}
                                name="birth_date"
                                error={employeeForm.errors.birth_date}
                            >
                                <Input
                                    id="birth_date"
                                    type="date"
                                    value={employeeForm.data.birth_date}
                                    onChange={(event) =>
                                        employeeForm.setData(
                                            'birth_date',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.tanggal.bergabung.85a22',
                                    stage3Locale,
                                )}
                                name="joined_at"
                                error={employeeForm.errors.joined_at}
                            >
                                <Input
                                    id="joined_at"
                                    type="date"
                                    value={employeeForm.data.joined_at}
                                    onChange={(event) =>
                                        employeeForm.setData(
                                            'joined_at',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label={stage3Translate(
                                    'stage3.ui.tanggal.berakhir.99021',
                                    stage3Locale,
                                )}
                                name="ended_at"
                                error={employeeForm.errors.ended_at}
                            >
                                <Input
                                    id="ended_at"
                                    type="date"
                                    value={employeeForm.data.ended_at}
                                    onChange={(event) =>
                                        employeeForm.setData(
                                            'ended_at',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>
                        <FormField
                            label={stage3Translate(
                                'stage3.ui.alamat.85b6e',
                                stage3Locale,
                            )}
                            name="address"
                            error={employeeForm.errors.address}
                        >
                            <textarea
                                id="address"
                                value={employeeForm.data.address}
                                onChange={(event) =>
                                    employeeForm.setData(
                                        'address',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </FormField>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEmployeeDialogOpen(false)}
                                disabled={employeeForm.processing}
                            >
                                <Stage3Text k="stage3.ui.batal.14335" />
                            </Button>
                            <Button
                                type="submit"
                                disabled={employeeForm.processing}
                            >
                                {employeeForm.processing
                                    ? stage3Translate(
                                          'stage3.ui.correction.menyimpan.92e24',
                                          stage3Locale,
                                      )
                                    : editingEmployee
                                      ? stage3Translate(
                                            'stage3.ui.correction.simpan.perubahan.099b3',
                                            stage3Locale,
                                        )
                                      : stage3Translate(
                                            'stage3.ui.correction.buat.karyawan.aee0c',
                                            stage3Locale,
                                        )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={positionDialogOpen}
                onOpenChange={setPositionDialogOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingPosition
                                ? stage3Translate(
                                      'stage3.ui.correction.edit.53016',
                                      stage3Locale,
                                  ) +
                                  ' ' +
                                  editingPosition.name
                                : stage3Translate(
                                      'stage3.ui.correction.tambah.jabatan.508e4',
                                      stage3Locale,
                                  )}
                        </DialogTitle>
                        <DialogDescription>
                            <Stage3Text k="stage3.ui.kode.jabatan.unik.dalam.perusahaan.dan.dipakai.081ca" />
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitPosition} className="grid gap-4">
                        <FormField
                            label={stage3Translate(
                                'stage3.ui.kode.jabatan.0e67b',
                                stage3Locale,
                            )}
                            name="position_code"
                            error={positionForm.errors.code}
                        >
                            <Input
                                id="position_code"
                                value={positionForm.data.code}
                                onChange={(event) =>
                                    positionForm.setData(
                                        'code',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label={stage3Translate(
                                'stage3.ui.nama.jabatan.be836',
                                stage3Locale,
                            )}
                            name="position_name"
                            error={positionForm.errors.name}
                        >
                            <Input
                                id="position_name"
                                value={positionForm.data.name}
                                onChange={(event) =>
                                    positionForm.setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label={stage3Translate(
                                'stage3.ui.deskripsi.7e9fd',
                                stage3Locale,
                            )}
                            name="position_description"
                            error={positionForm.errors.description}
                        >
                            <textarea
                                id="position_description"
                                value={positionForm.data.description}
                                onChange={(event) =>
                                    positionForm.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </FormField>
                        <label className="flex items-start gap-3 rounded-lg border p-4">
                            <Checkbox
                                checked={positionForm.data.is_active}
                                onCheckedChange={(checked) =>
                                    positionForm.setData(
                                        'is_active',
                                        checked === true,
                                    )
                                }
                            />
                            <span>
                                <span className="block text-sm font-medium">
                                    <Stage3Text k="stage3.ui.jabatan.aktif.f91b7" />
                                </span>
                                <span className="mt-1 block text-xs text-muted-foreground">
                                    <Stage3Text k="stage3.ui.dapat.dipilih.untuk.karyawan.baru.4800a" />
                                </span>
                            </span>
                        </label>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setPositionDialogOpen(false)}
                                disabled={positionForm.processing}
                            >
                                <Stage3Text k="stage3.ui.batal.14335" />
                            </Button>
                            <Button
                                type="submit"
                                disabled={positionForm.processing}
                            >
                                {positionForm.processing
                                    ? stage3Translate(
                                          'stage3.ui.correction.menyimpan.92e24',
                                          stage3Locale,
                                      )
                                    : editingPosition
                                      ? stage3Translate(
                                            'stage3.ui.correction.simpan.perubahan.099b3',
                                            stage3Locale,
                                        )
                                      : stage3Translate(
                                            'stage3.ui.correction.buat.jabatan.5df05',
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

function EmployeeStatusBadge({ status }: { status: Employee['status'] }) {
    const label = {
        active: 'Aktif',
        inactive: 'Nonaktif',
        leave: 'Cuti',
        terminated: 'Berakhir',
    }[status];

    return (
        <Badge variant={status === 'active' ? 'outline' : 'secondary'}>
            {label}
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
