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
            title: activating ? 'Aktifkan karyawan?' : 'Nonaktifkan karyawan?',
            description: `${employee.name} akan ${activating ? 'diaktifkan kembali' : 'dinonaktifkan dari operasional aktif'}.`,
            confirmLabel: activating ? 'Aktifkan' : 'Nonaktifkan',
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
            title: activating ? 'Aktifkan jabatan?' : 'Nonaktifkan jabatan?',
            description: `Jabatan ${position.name} akan ${activating ? 'diaktifkan kembali' : 'dinonaktifkan untuk penugasan baru'}.`,
            confirmLabel: activating ? 'Aktifkan' : 'Nonaktifkan',
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
            <Head title="Karyawan & Jabatan" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Modul Pengguna & Akses
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Karyawan & jabatan
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kelola identitas staf, penempatan cabang, jabatan,
                            status kepegawaian, dan hubungan ke akun login.
                        </p>
                    </div>
                    {permissions.manage && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={openCreatePosition}
                            >
                                <BriefcaseBusiness />
                                Tambah jabatan
                            </Button>
                            <Button onClick={openCreateEmployee}>
                                <Plus />
                                Tambah karyawan
                            </Button>
                        </div>
                    )}
                </header>

                <AccessNav current="/employees" />

                {typeof pageErrors.employee === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>Perubahan karyawan ditolak</AlertTitle>
                        <AlertDescription>
                            {pageErrors.employee}
                        </AlertDescription>
                    </Alert>
                )}
                {typeof pageErrors.position === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>Perubahan jabatan ditolak</AlertTitle>
                        <AlertDescription>
                            {pageErrors.position}
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        {
                            label: 'Total karyawan',
                            value: summary.total,
                            icon: ContactRound,
                        },
                        {
                            label: 'Karyawan aktif',
                            value: summary.active,
                            icon: CheckCircle2,
                        },
                        {
                            label: 'Belum punya akun',
                            value: summary.withoutAccount,
                            icon: Link2,
                        },
                        {
                            label: 'Sedang cuti',
                            value: summary.onLeave,
                            icon: CircleOff,
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
                    <CardHeader className="gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <CardTitle>Daftar karyawan</CardTitle>
                            <CardDescription>
                                Satu karyawan hanya dapat dihubungkan ke satu
                                akun login.
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
                                        placeholder="Nama, nomor, telepon"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="icon"
                                    variant="outline"
                                    aria-label="Cari karyawan"
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
                                <SelectTrigger className="w-full sm:w-48">
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
                                <SelectTrigger className="w-full sm:w-40">
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
                                    <SelectItem value="leave">Cuti</SelectItem>
                                    <SelectItem value="terminated">
                                        Berakhir
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
                                    Karyawan tidak ditemukan
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Ubah filter atau tambahkan karyawan baru.
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
                                                    : 'Jabatan belum ditentukan'}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {employee.primary_branch
                                                    ? `${employee.primary_branch.code} · ${employee.primary_branch.name}`
                                                    : 'Cabang utama belum ditentukan'}
                                            </p>
                                            <p className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <UserRoundCheck className="size-4" />
                                                {employee.user
                                                    ? employee.user.email
                                                    : 'Belum terhubung ke akun'}
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
                                                    Edit
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
                                                        ? 'Nonaktifkan'
                                                        : 'Aktifkan'}
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
                        <CardTitle>Master jabatan</CardTitle>
                        <CardDescription>
                            Jabatan nonaktif tetap dipertahankan untuk menjaga
                            histori karyawan lama.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {positions.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                Belum ada data jabatan.
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
                                                            ? 'Aktif'
                                                            : 'Nonaktif'}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                    {position.code}
                                                </p>
                                            </div>
                                            <Badge variant="secondary">
                                                {position.employees_count}{' '}
                                                karyawan
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
                                                    Edit
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
                                                        ? 'Nonaktifkan'
                                                        : 'Aktifkan'}
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
                                ? `Edit ${editingEmployee.name}`
                                : 'Tambah karyawan'}
                        </DialogTitle>
                        <DialogDescription>
                            Akun login bersifat opsional dan dapat dihubungkan
                            kemudian.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitEmployee} className="grid gap-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Nomor karyawan"
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
                                label="Nama lengkap"
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
                                label="Cabang utama"
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
                            <FormField
                                label="Jabatan"
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
                                            Belum ditetapkan
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
                                label="Status"
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
                                            Aktif
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            Nonaktif
                                        </SelectItem>
                                        <SelectItem value="leave">
                                            Cuti
                                        </SelectItem>
                                        <SelectItem value="terminated">
                                            Berakhir
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label="Akun login"
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
                                            Tidak dihubungkan
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
                                label="Nomor identitas"
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
                                label="Jenis kelamin"
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
                                            Tidak diisi
                                        </SelectItem>
                                        <SelectItem value="male">
                                            Laki-laki
                                        </SelectItem>
                                        <SelectItem value="female">
                                            Perempuan
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label="Telepon"
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
                                label="Tempat lahir"
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
                                label="Tanggal lahir"
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
                                label="Tanggal bergabung"
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
                                label="Tanggal berakhir"
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
                            label="Alamat"
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
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={employeeForm.processing}
                            >
                                {employeeForm.processing
                                    ? 'Menyimpan…'
                                    : editingEmployee
                                      ? 'Simpan perubahan'
                                      : 'Buat karyawan'}
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
                                ? `Edit ${editingPosition.name}`
                                : 'Tambah jabatan'}
                        </DialogTitle>
                        <DialogDescription>
                            Kode jabatan unik dalam perusahaan dan dipakai
                            sebagai referensi kepegawaian.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitPosition} className="grid gap-4">
                        <FormField
                            label="Kode jabatan"
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
                            label="Nama jabatan"
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
                            label="Deskripsi"
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
                                    Jabatan aktif
                                </span>
                                <span className="mt-1 block text-xs text-muted-foreground">
                                    Dapat dipilih untuk karyawan baru.
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
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={positionForm.processing}
                            >
                                {positionForm.processing
                                    ? 'Menyimpan…'
                                    : editingPosition
                                      ? 'Simpan perubahan'
                                      : 'Buat jabatan'}
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
