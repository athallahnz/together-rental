import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AccessBranch, Customer, CustomerFormData } from '@/types';

function initialData(
    branches: AccessBranch[],
    customer: Customer | null,
): CustomerFormData {
    return customer
        ? {
              registered_branch_id: customer.registered_branch_id,
              name: customer.name,
              gender: customer.gender ?? '',
              phone: customer.phone ?? '',
              email: customer.email ?? '',
              birth_place: customer.birth_place ?? '',
              birth_date: customer.birth_date?.slice(0, 10) ?? '',
              institution: customer.institution ?? '',
              is_member: customer.is_member,
              member_number: customer.member_number ?? '',
              member_since: customer.member_since?.slice(0, 10) ?? '',
              status: customer.status,
              risk_level: customer.risk_level,
              notes: customer.notes ?? '',
          }
        : {
              registered_branch_id: branches[0]?.id ?? null,
              name: '',
              gender: '',
              phone: '',
              email: '',
              birth_place: '',
              birth_date: '',
              institution: '',
              is_member: false,
              member_number: '',
              member_since: '',
              status: 'active',
              risk_level: 'normal',
              notes: '',
          };
}

export function CustomerFormDialog({
    open,
    onOpenChange,
    customer,
    branches,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customer: Customer | null;
    branches: AccessBranch[];
}) {
    const form = useForm<CustomerFormData>(initialData(branches, customer));

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (customer) {
            form.put(`/customers/${customer.id}`, options);

            return;
        }

        form.post('/customers', options);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (nextOpen) {
                    form.setData(initialData(branches, customer));
                    form.clearErrors();
                }

                onOpenChange(nextOpen);
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {customer
                            ? `Edit ${customer.name}`
                            : 'Tambah pelanggan'}
                    </DialogTitle>
                    <DialogDescription>
                        Nomor pelanggan dibuat otomatis berdasarkan cabang
                        pendaftaran. Nomor `LEG-PNG-*` dari sistem lama tetap
                        dipertahankan.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Cabang pendaftaran"
                            name="registered_branch_id"
                            error={form.errors.registered_branch_id}
                        >
                            <Select
                                value={
                                    form.data.registered_branch_id?.toString() ??
                                    ''
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'registered_branch_id',
                                        Number(value),
                                    )
                                }
                            >
                                <SelectTrigger id="registered_branch_id">
                                    <SelectValue placeholder="Pilih cabang" />
                                </SelectTrigger>
                                <SelectContent>
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
                            label="Nama lengkap"
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
                            label="Telepon"
                            name="phone"
                            error={form.errors.phone}
                        >
                            <Input
                                id="phone"
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData('phone', event.target.value)
                                }
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
                                    form.setData('email', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Jenis kelamin"
                            name="gender"
                            error={form.errors.gender}
                        >
                            <Select
                                value={form.data.gender || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
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
                            label="Institusi"
                            name="institution"
                            error={form.errors.institution}
                        >
                            <Input
                                id="institution"
                                value={form.data.institution}
                                onChange={(event) =>
                                    form.setData(
                                        'institution',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Tempat lahir"
                            name="birth_place"
                            error={form.errors.birth_place}
                        >
                            <Input
                                id="birth_place"
                                value={form.data.birth_place}
                                onChange={(event) =>
                                    form.setData(
                                        'birth_place',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Tanggal lahir"
                            name="birth_date"
                            error={form.errors.birth_date}
                        >
                            <Input
                                id="birth_date"
                                type="date"
                                value={form.data.birth_date}
                                onChange={(event) =>
                                    form.setData(
                                        'birth_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Status pelanggan"
                            name="status"
                            error={form.errors.status}
                        >
                            <Select
                                value={form.data.status}
                                onValueChange={(value) =>
                                    form.setData(
                                        'status',
                                        value as Customer['status'],
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
                                    <SelectItem value="blocked">
                                        Diblokir
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            label="Risk profile"
                            name="risk_level"
                            error={form.errors.risk_level}
                        >
                            <Select
                                value={form.data.risk_level}
                                onValueChange={(value) =>
                                    form.setData(
                                        'risk_level',
                                        value as Customer['risk_level'],
                                    )
                                }
                            >
                                <SelectTrigger id="risk_level">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Rendah</SelectItem>
                                    <SelectItem value="normal">
                                        Normal
                                    </SelectItem>
                                    <SelectItem value="high">Tinggi</SelectItem>
                                    <SelectItem value="critical">
                                        Kritis
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>

                    <div className="grid gap-4 rounded-xl border p-4">
                        <label className="flex items-start gap-3">
                            <Checkbox
                                checked={form.data.is_member}
                                onCheckedChange={(checked) =>
                                    form.setData('is_member', checked === true)
                                }
                            />
                            <span>
                                <span className="block text-sm font-medium">
                                    Pelanggan member
                                </span>
                                <span className="mt-1 block text-xs text-muted-foreground">
                                    Nomor member dibuat otomatis jika
                                    dikosongkan.
                                </span>
                            </span>
                        </label>
                        {form.data.is_member && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="Nomor member"
                                    name="member_number"
                                    error={form.errors.member_number}
                                >
                                    <Input
                                        id="member_number"
                                        value={form.data.member_number}
                                        onChange={(event) =>
                                            form.setData(
                                                'member_number',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Otomatis"
                                    />
                                </FormField>
                                <FormField
                                    label="Member sejak"
                                    name="member_since"
                                    error={form.errors.member_since}
                                >
                                    <Input
                                        id="member_since"
                                        type="date"
                                        value={form.data.member_since}
                                        onChange={(event) =>
                                            form.setData(
                                                'member_since',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                        )}
                    </div>

                    <FormField
                        label="Catatan internal"
                        name="notes"
                        error={form.errors.notes}
                    >
                        <textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            rows={3}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </FormField>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Menyimpan…'
                                : customer
                                  ? 'Simpan perubahan'
                                  : 'Buat pelanggan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
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
