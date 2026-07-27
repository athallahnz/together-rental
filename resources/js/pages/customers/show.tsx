import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BadgeCheck,
    Banknote,
    CalendarDays,
    CircleOff,
    Clock3,
    Coins,
    Gem,
    IdCard,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    ReceiptText,
    ShieldCheck,
    Trash2,
    UserRound,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { CustomerFormDialog } from '@/components/customers/customer-form-dialog';
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
import type {
    AccessBranch,
    Customer,
    CustomerAddress,
    CustomerIdentity,
} from '@/types';

type RentalSummary = {
    id: number;
    branch_id: number;
    rental_number: string;
    legacy_number: string | null;
    status: string;
    checked_out_at: string | null;
    due_at: string;
    returned_at: string | null;
    total_amount: string;
    paid_amount: string;
    balance_due: string;
    branch: AccessBranch | null;
};

type BookingSummary = {
    id: number;
    branch_id: number;
    booking_number: string;
    legacy_number: string | null;
    status: string;
    booked_at: string;
    starts_at: string;
    ends_at: string;
    total_amount: string;
    branch: AccessBranch | null;
};

type Props = {
    customer: Customer;
    statistics: {
        rentals: number;
        activeRentals: number;
        rentalValue: string;
        paidAmount: string;
        outstanding: string;
        lastRentalAt: string | null;
    };
    recentRentals: RentalSummary[];
    recentBookings: BookingSummary[];
    branches: AccessBranch[];
    permissions: {
        create: boolean;
        update: boolean;
        archive: boolean;
        verify: boolean;
        loyalty: boolean;
    };
};

export default function CustomerShow({
    customer,
    statistics,
    recentRentals,
    recentBookings,
    branches,
    permissions,
}: Props) {
    const { errors: pageErrors } = usePage().props;
    const [editOpen, setEditOpen] = useState(false);
    const [identityOpen, setIdentityOpen] = useState(false);
    const [addressOpen, setAddressOpen] = useState(false);
    const [loyaltyOpen, setLoyaltyOpen] = useState(false);
    const [editingIdentity, setEditingIdentity] =
        useState<CustomerIdentity | null>(null);
    const [editingAddress, setEditingAddress] =
        useState<CustomerAddress | null>(null);
    const identities = customer.identities ?? [];
    const addresses = customer.addresses ?? [];
    const loyalty = customer.loyalty_account;

    const archiveCustomer = () => {
        if (
            !window.confirm(
                `Arsipkan ${customer.name}? Data histori tetap disimpan, tetapi pelanggan tidak tampil pada transaksi baru.`,
            )
        ) {
            return;
        }

        router.delete(`/customers/${customer.id}`);
    };

    const openIdentity = (identity: CustomerIdentity | null) => {
        setEditingIdentity(identity);
        setIdentityOpen(true);
    };

    const openAddress = (address: CustomerAddress | null) => {
        setEditingAddress(address);
        setAddressOpen(true);
    };

    return (
        <>
            <Head title={customer.name} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/customers">
                            <ArrowLeft />
                            Semua pelanggan
                        </Link>
                    </Button>
                </div>

                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {customer.customer_number}
                            </Badge>
                            <CustomerStatusBadge status={customer.status} />
                            <RiskBadge risk={customer.risk_level} />
                            {customer.is_member && (
                                <Badge>
                                    <Gem />
                                    {customer.member_number ?? 'Member'}
                                </Badge>
                            )}
                        </div>
                        <h1 className="mt-3 text-2xl font-semibold tracking-tight">
                            {customer.name}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Customer 360° · Terdaftar di{' '}
                            {customer.registered_branch
                                ? `${customer.registered_branch.code} · ${customer.registered_branch.name}`
                                : 'cabang yang sudah tidak tersedia'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.update && (
                            <Button
                                variant="outline"
                                onClick={() => setEditOpen(true)}
                            >
                                <Pencil />
                                Edit profil
                            </Button>
                        )}
                        {permissions.archive && (
                            <Button
                                variant="destructive"
                                onClick={archiveCustomer}
                            >
                                <Trash2 />
                                Arsipkan
                            </Button>
                        )}
                    </div>
                </header>

                {(typeof pageErrors.customer === 'string' ||
                    typeof pageErrors.loyalty === 'string') && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>Perubahan ditolak</AlertTitle>
                        <AlertDescription>
                            {String(pageErrors.customer ?? pageErrors.loyalty)}
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatisticCard
                        label="Total rental"
                        value={statistics.rentals.toString()}
                        icon={ReceiptText}
                    />
                    <StatisticCard
                        label="Rental aktif"
                        value={statistics.activeRentals.toString()}
                        icon={Clock3}
                    />
                    <StatisticCard
                        label="Nilai rental"
                        value={currency(statistics.rentalValue)}
                        icon={Banknote}
                    />
                    <StatisticCard
                        label="Terbayar"
                        value={currency(statistics.paidAmount)}
                        icon={ShieldCheck}
                    />
                    <StatisticCard
                        label="Outstanding"
                        value={currency(statistics.outstanding)}
                        icon={AlertTriangle}
                    />
                </section>

                <section className="grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Profil pelanggan</CardTitle>
                            <CardDescription>
                                Informasi utama untuk pelayanan dan penilaian
                                risiko.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Info
                                icon={Phone}
                                label="Telepon"
                                value={customer.phone}
                            />
                            <Info
                                icon={Mail}
                                label="Email"
                                value={customer.email}
                            />
                            <Info
                                icon={UserRound}
                                label="Jenis kelamin"
                                value={
                                    customer.gender === 'male'
                                        ? 'Laki-laki'
                                        : customer.gender === 'female'
                                          ? 'Perempuan'
                                          : null
                                }
                            />
                            <Info
                                icon={CalendarDays}
                                label="Tempat, tanggal lahir"
                                value={
                                    [
                                        customer.birth_place,
                                        shortDate(customer.birth_date),
                                    ]
                                        .filter(Boolean)
                                        .join(', ') || null
                                }
                            />
                            <Info
                                icon={BadgeCheck}
                                label="Institusi"
                                value={customer.institution}
                            />
                            <Info
                                icon={ReceiptText}
                                label="Rental terakhir"
                                value={shortDate(statistics.lastRentalAt)}
                            />
                            <div className="sm:col-span-2">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Catatan internal
                                </p>
                                <p className="mt-2 text-sm leading-6 whitespace-pre-wrap">
                                    {customer.notes || 'Belum ada catatan.'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between">
                            <div>
                                <CardTitle>Loyalty</CardTitle>
                                <CardDescription>
                                    Saldo, tier, dan lifetime points.
                                </CardDescription>
                            </div>
                            {permissions.loyalty && (
                                <Button
                                    size="sm"
                                    onClick={() => setLoyaltyOpen(true)}
                                >
                                    <Coins />
                                    Transaksi poin
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-3 gap-3 rounded-xl bg-muted/50 p-5 text-center">
                                <div>
                                    <p className="text-2xl font-semibold">
                                        {number(loyalty?.points_balance ?? 0)}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Saldo
                                    </p>
                                </div>
                                <div>
                                    <p className="text-2xl font-semibold">
                                        {number(loyalty?.lifetime_points ?? 0)}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Lifetime
                                    </p>
                                </div>
                                <div>
                                    <p className="text-lg font-semibold capitalize">
                                        {loyalty?.tier ?? 'regular'}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Tier
                                    </p>
                                </div>
                            </div>

                            <div className="mt-5 grid gap-3">
                                {(loyalty?.transactions ?? [])
                                    .slice(0, 5)
                                    .map((transaction) => (
                                        <div
                                            key={transaction.id}
                                            className="flex items-start justify-between gap-4 border-b pb-3 last:border-0"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {transaction.description ||
                                                        transaction.type}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {dateTime(
                                                        transaction.occurred_at,
                                                    )}
                                                    {transaction.branch &&
                                                        ` · ${transaction.branch.code}`}
                                                </p>
                                            </div>
                                            <p
                                                className={
                                                    transaction.points >= 0
                                                        ? 'font-semibold text-emerald-600'
                                                        : 'font-semibold text-red-600'
                                                }
                                            >
                                                {transaction.points >= 0
                                                    ? '+'
                                                    : ''}
                                                {number(transaction.points)}
                                            </p>
                                        </div>
                                    ))}
                                {(loyalty?.transactions ?? []).length === 0 && (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        Belum ada transaksi loyalty.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader className="flex-row items-start justify-between">
                            <div>
                                <CardTitle>Identitas</CardTitle>
                                <CardDescription>
                                    Dokumen identitas dan status verifikasi.
                                </CardDescription>
                            </div>
                            {permissions.update && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => openIdentity(null)}
                                >
                                    <Plus />
                                    Tambah
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {identities.map((identity) => (
                                <article
                                    key={identity.id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium uppercase">
                                                    {identity.type.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </p>
                                                {identity.is_primary && (
                                                    <Badge>Utama</Badge>
                                                )}
                                                <Badge
                                                    variant={
                                                        identity.verified_at
                                                            ? 'outline'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {identity.verified_at
                                                        ? 'Terverifikasi'
                                                        : 'Belum diverifikasi'}
                                                </Badge>
                                            </div>
                                            <p className="mt-2 font-mono text-sm">
                                                {identity.number}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {identity.name_on_identity ||
                                                    customer.name}
                                                {identity.expires_at &&
                                                    ` · Berlaku sampai ${shortDate(identity.expires_at)}`}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {permissions.verify &&
                                                !identity.verified_at && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            router.patch(
                                                                `/customer-identities/${identity.id}/verify`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <BadgeCheck />
                                                        Verifikasi
                                                    </Button>
                                                )}
                                            {permissions.update && (
                                                <>
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label="Edit identitas"
                                                        onClick={() =>
                                                            openIdentity(
                                                                identity,
                                                            )
                                                        }
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label="Hapus identitas"
                                                        onClick={() =>
                                                            destroyIdentity(
                                                                identity,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </article>
                            ))}
                            {identities.length === 0 && (
                                <EmptyState
                                    icon={IdCard}
                                    text="Identitas belum ditambahkan."
                                />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between">
                            <div>
                                <CardTitle>Alamat</CardTitle>
                                <CardDescription>
                                    Alamat identitas, domisili, atau tempat
                                    kerja.
                                </CardDescription>
                            </div>
                            {permissions.update && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => openAddress(null)}
                                >
                                    <Plus />
                                    Tambah
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {addresses.map((address) => (
                                <article
                                    key={address.id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium capitalize">
                                                    {address.type}
                                                </p>
                                                {address.is_primary && (
                                                    <Badge>Utama</Badge>
                                                )}
                                            </div>
                                            <p className="mt-2 text-sm leading-6">
                                                {address.address}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {[
                                                    address.village,
                                                    address.district,
                                                    address.city,
                                                    address.province,
                                                    address.postal_code,
                                                ]
                                                    .filter(Boolean)
                                                    .join(', ')}
                                            </p>
                                        </div>
                                        {permissions.update && (
                                            <div className="flex gap-1">
                                                <Button
                                                    size="icon"
                                                    variant="outline"
                                                    aria-label="Edit alamat"
                                                    onClick={() =>
                                                        openAddress(address)
                                                    }
                                                >
                                                    <Pencil />
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label="Hapus alamat"
                                                    onClick={() =>
                                                        destroyAddress(address)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </article>
                            ))}
                            {addresses.length === 0 && (
                                <EmptyState
                                    icon={MapPin}
                                    text="Alamat belum ditambahkan."
                                />
                            )}
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 xl:grid-cols-2">
                    <HistoryCard
                        title="Rental terakhir"
                        description="Sepuluh transaksi rental terbaru pelanggan."
                        empty="Belum ada histori rental."
                    >
                        {recentRentals.map((rental) => (
                            <div
                                key={rental.id}
                                className="grid gap-2 border-b py-3 first:pt-0 last:border-0 sm:grid-cols-[1fr_auto] sm:items-center"
                            >
                                <div>
                                    <p className="font-medium">
                                        {rental.rental_number}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {rental.branch?.code ?? '—'} ·{' '}
                                        {dateTime(rental.checked_out_at)} ·
                                        Jatuh tempo {dateTime(rental.due_at)}
                                    </p>
                                </div>
                                <div className="sm:text-right">
                                    <Badge variant="outline">
                                        {rental.status}
                                    </Badge>
                                    <p className="mt-1 text-sm font-medium">
                                        {currency(rental.total_amount)}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </HistoryCard>

                    <HistoryCard
                        title="Booking terakhir"
                        description="Sepuluh reservasi terbaru pelanggan."
                        empty="Belum ada histori booking."
                    >
                        {recentBookings.map((booking) => (
                            <div
                                key={booking.id}
                                className="grid gap-2 border-b py-3 first:pt-0 last:border-0 sm:grid-cols-[1fr_auto] sm:items-center"
                            >
                                <div>
                                    <p className="font-medium">
                                        {booking.booking_number}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {booking.branch?.code ?? '—'} · Mulai{' '}
                                        {dateTime(booking.starts_at)}
                                    </p>
                                </div>
                                <div className="sm:text-right">
                                    <Badge variant="outline">
                                        {booking.status}
                                    </Badge>
                                    <p className="mt-1 text-sm font-medium">
                                        {currency(booking.total_amount)}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </HistoryCard>
                </section>
            </div>

            <CustomerFormDialog
                key={`edit-${customer.id}`}
                open={editOpen}
                onOpenChange={setEditOpen}
                customer={customer}
                branches={branches}
            />
            <IdentityDialog
                key={editingIdentity?.id ?? 'new-identity'}
                open={identityOpen}
                onOpenChange={setIdentityOpen}
                customerId={customer.id}
                identity={editingIdentity}
                customerName={customer.name}
            />
            <AddressDialog
                key={editingAddress?.id ?? 'new-address'}
                open={addressOpen}
                onOpenChange={setAddressOpen}
                customerId={customer.id}
                address={editingAddress}
            />
            <LoyaltyDialog
                open={loyaltyOpen}
                onOpenChange={setLoyaltyOpen}
                customerId={customer.id}
                balance={loyalty?.points_balance ?? 0}
            />
        </>
    );
}

function IdentityDialog({
    open,
    onOpenChange,
    customerId,
    identity,
    customerName,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customerId: number;
    identity: CustomerIdentity | null;
    customerName: string;
}) {
    const form = useForm({
        type: identity?.type ?? ('ktp' as CustomerIdentity['type']),
        number: identity?.number ?? '',
        name_on_identity: identity?.name_on_identity ?? customerName,
        expires_at: identity?.expires_at?.slice(0, 10) ?? '',
        is_primary: identity?.is_primary ?? false,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (identity) {
            form.put(`/customer-identities/${identity.id}`, options);

            return;
        }

        form.post(`/customers/${customerId}/identities`, options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {identity ? 'Edit identitas' : 'Tambah identitas'}
                    </DialogTitle>
                    <DialogDescription>
                        Dokumen yang diverifikasi dapat digunakan sebagai
                        referensi proses rental.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        label="Jenis identitas"
                        name="identity_type"
                        error={form.errors.type}
                    >
                        <Select
                            value={form.data.type}
                            onValueChange={(value) =>
                                form.setData(
                                    'type',
                                    value as CustomerIdentity['type'],
                                )
                            }
                        >
                            <SelectTrigger id="identity_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="ktp">KTP</SelectItem>
                                <SelectItem value="sim">SIM</SelectItem>
                                <SelectItem value="passport">Paspor</SelectItem>
                                <SelectItem value="student_card">
                                    Kartu pelajar
                                </SelectItem>
                                <SelectItem value="employee_card">
                                    Kartu karyawan
                                </SelectItem>
                                <SelectItem value="other">Lainnya</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label="Nomor identitas"
                        name="identity_number"
                        error={form.errors.number}
                    >
                        <Input
                            id="identity_number"
                            value={form.data.number}
                            onChange={(event) =>
                                form.setData('number', event.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        label="Nama pada identitas"
                        name="name_on_identity"
                        error={form.errors.name_on_identity}
                    >
                        <Input
                            id="name_on_identity"
                            value={form.data.name_on_identity}
                            onChange={(event) =>
                                form.setData(
                                    'name_on_identity',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>
                    <FormField
                        label="Masa berlaku"
                        name="identity_expires_at"
                        error={form.errors.expires_at}
                    >
                        <Input
                            id="identity_expires_at"
                            type="date"
                            value={form.data.expires_at}
                            onChange={(event) =>
                                form.setData('expires_at', event.target.value)
                            }
                        />
                    </FormField>
                    <label className="flex items-center gap-3 rounded-lg border p-4">
                        <Checkbox
                            checked={form.data.is_primary}
                            onCheckedChange={(checked) =>
                                form.setData('is_primary', checked === true)
                            }
                        />
                        <span className="text-sm font-medium">
                            Jadikan identitas utama
                        </span>
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Menyimpan…'
                                : 'Simpan identitas'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddressDialog({
    open,
    onOpenChange,
    customerId,
    address,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customerId: number;
    address: CustomerAddress | null;
}) {
    const form = useForm({
        type: address?.type ?? ('domicile' as CustomerAddress['type']),
        address: address?.address ?? '',
        village: address?.village ?? '',
        district: address?.district ?? '',
        city: address?.city ?? '',
        province: address?.province ?? '',
        postal_code: address?.postal_code ?? '',
        is_primary: address?.is_primary ?? false,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (address) {
            form.put(`/customer-addresses/${address.id}`, options);

            return;
        }

        form.post(`/customers/${customerId}/addresses`, options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {address ? 'Edit alamat' : 'Tambah alamat'}
                    </DialogTitle>
                    <DialogDescription>
                        Simpan alamat secara terstruktur untuk verifikasi dan
                        operasional rental.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        label="Jenis alamat"
                        name="address_type"
                        error={form.errors.type}
                    >
                        <Select
                            value={form.data.type}
                            onValueChange={(value) =>
                                form.setData(
                                    'type',
                                    value as CustomerAddress['type'],
                                )
                            }
                        >
                            <SelectTrigger id="address_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="identity">
                                    Identitas
                                </SelectItem>
                                <SelectItem value="domicile">
                                    Domisili
                                </SelectItem>
                                <SelectItem value="work">
                                    Tempat kerja
                                </SelectItem>
                                <SelectItem value="other">Lainnya</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label="Alamat lengkap"
                        name="address_text"
                        error={form.errors.address}
                    >
                        <textarea
                            id="address_text"
                            value={form.data.address}
                            onChange={(event) =>
                                form.setData('address', event.target.value)
                            }
                            rows={3}
                            required
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {[
                            ['village', 'Desa / kelurahan'],
                            ['district', 'Kecamatan'],
                            ['city', 'Kota / kabupaten'],
                            ['province', 'Provinsi'],
                            ['postal_code', 'Kode pos'],
                        ].map(([field, label]) => (
                            <FormField
                                key={field}
                                label={label}
                                name={`address_${field}`}
                                error={
                                    form.errors[
                                        field as keyof typeof form.errors
                                    ]
                                }
                            >
                                <Input
                                    id={`address_${field}`}
                                    value={
                                        form.data[
                                            field as keyof typeof form.data
                                        ] as string
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            field as
                                                | 'village'
                                                | 'district'
                                                | 'city'
                                                | 'province'
                                                | 'postal_code',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        ))}
                    </div>
                    <label className="flex items-center gap-3 rounded-lg border p-4">
                        <Checkbox
                            checked={form.data.is_primary}
                            onCheckedChange={(checked) =>
                                form.setData('is_primary', checked === true)
                            }
                        />
                        <span className="text-sm font-medium">
                            Jadikan alamat utama
                        </span>
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Menyimpan…' : 'Simpan alamat'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function LoyaltyDialog({
    open,
    onOpenChange,
    customerId,
    balance,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customerId: number;
    balance: number;
}) {
    const form = useForm({
        type: 'earn' as 'earn' | 'redeem' | 'adjustment',
        points: 0,
        description: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/customers/${customerId}/loyalty-adjustments`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Transaksi loyalty</DialogTitle>
                    <DialogDescription>
                        Saldo sekarang {number(balance)} poin. Redeem tidak
                        dapat membuat saldo menjadi negatif.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        label="Jenis transaksi"
                        name="loyalty_type"
                        error={form.errors.type}
                    >
                        <Select
                            value={form.data.type}
                            onValueChange={(value) =>
                                form.setData(
                                    'type',
                                    value as typeof form.data.type,
                                )
                            }
                        >
                            <SelectTrigger id="loyalty_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="earn">
                                    Tambah poin
                                </SelectItem>
                                <SelectItem value="redeem">
                                    Tukar poin
                                </SelectItem>
                                <SelectItem value="adjustment">
                                    Koreksi manual
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label="Jumlah poin"
                        name="loyalty_points"
                        error={form.errors.points}
                    >
                        <Input
                            id="loyalty_points"
                            type="number"
                            value={form.data.points}
                            onChange={(event) =>
                                form.setData(
                                    'points',
                                    Number(event.target.value),
                                )
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        label="Keterangan"
                        name="loyalty_description"
                        error={form.errors.description}
                    >
                        <Input
                            id="loyalty_description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            required
                        />
                    </FormField>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Menyimpan…'
                                : 'Simpan transaksi'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function StatisticCard({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: string;
    icon: typeof ReceiptText;
}) {
    return (
        <Card>
            <CardContent className="flex items-center justify-between p-5">
                <div className="min-w-0">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {label}
                    </p>
                    <p className="mt-2 truncate text-xl font-semibold">
                        {value}
                    </p>
                </div>
                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                    <Icon className="size-5" />
                </div>
            </CardContent>
        </Card>
    );
}

function Info({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Phone;
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="flex gap-3 rounded-lg border p-3">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-1 truncate text-sm font-medium">
                    {value || 'Belum diisi'}
                </p>
            </div>
        </div>
    );
}

function HistoryCard({
    title,
    description,
    empty,
    children,
}: {
    title: string;
    description: string;
    empty: string;
    children: React.ReactNode;
}) {
    const hasChildren = Array.isArray(children)
        ? children.length > 0
        : Boolean(children);

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                {hasChildren ? (
                    children
                ) : (
                    <p className="py-10 text-center text-sm text-muted-foreground">
                        {empty}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function EmptyState({
    icon: Icon,
    text,
}: {
    icon: typeof IdCard;
    text: string;
}) {
    return (
        <div className="py-10 text-center text-sm text-muted-foreground">
            <Icon className="mx-auto mb-3 size-7" />
            {text}
        </div>
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

function CustomerStatusBadge({ status }: { status: Customer['status'] }) {
    return (
        <Badge variant={status === 'active' ? 'outline' : 'secondary'}>
            {status === 'active'
                ? 'Aktif'
                : status === 'blocked'
                  ? 'Diblokir'
                  : 'Nonaktif'}
        </Badge>
    );
}

function RiskBadge({ risk }: { risk: Customer['risk_level'] }) {
    return (
        <Badge
            variant={
                risk === 'critical'
                    ? 'destructive'
                    : risk === 'high'
                      ? 'secondary'
                      : 'outline'
            }
        >
            Risiko{' '}
            {
                {
                    low: 'rendah',
                    normal: 'normal',
                    high: 'tinggi',
                    critical: 'kritis',
                }[risk]
            }
        </Badge>
    );
}

function destroyIdentity(identity: CustomerIdentity) {
    if (!window.confirm(`Hapus identitas ${identity.number}?`)) {
        return;
    }

    router.delete(`/customer-identities/${identity.id}`, {
        preserveScroll: true,
    });
}

function destroyAddress(address: CustomerAddress) {
    if (!window.confirm('Hapus alamat pelanggan ini?')) {
        return;
    }

    router.delete(`/customer-addresses/${address.id}`, {
        preserveScroll: true,
    });
}

function currency(value: string | number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function number(value: number) {
    return new Intl.NumberFormat('id-ID').format(value);
}

function shortDate(value: string | null | undefined) {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function dateTime(value: string | null | undefined) {
    if (!value) {
        return 'Waktu tidak tersedia';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
