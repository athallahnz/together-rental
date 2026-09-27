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
import { getEffectiveLocale, useGlobalLocale } from '@/lib/locale-store';
import { formatStage3Date } from '@/lib/stage3-display';
import {
    stage3CustomerHistoryStatus,
    stage3LoyaltyTier,
} from '@/lib/stage3-customer-history';
import type { AppLocale } from '@/lib/i18n';
import { Stage3Text, stage3Translate } from '@/components/stage3-text';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
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
import { MetricCard } from '@/components/ui/metric-card';
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

type Confirm = ReturnType<typeof useConfirmDialog>;

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
    const stage3Locale = useGlobalLocale();
    const { errors: pageErrors } = usePage().props;
    const confirm = useConfirmDialog();
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

    const archiveCustomer = async () => {
        const confirmed = await confirm({
            title: stage3Translate(
                'stage3.ui.correction.confirm.customer.archive.title',
                stage3Locale,
            ),
            description: stage3Translate(
                'stage3.ui.correction.confirm.customer.archive',
                stage3Locale,
                { name: customer.name },
            ),
            confirmLabel: stage3Translate(
                'stage3.ui.correction.confirm.customer.archive.action',
                stage3Locale,
            ),
            variant: 'destructive',
        });

        if (!confirmed) {
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
                            <Stage3Text k="stage3.ui.semua.pelanggan.50b93" />
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
                            <Stage3Text k="stage3.ui.customer.360.terdaftar.di.c9bc5" />{' '}
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
                                <Stage3Text k="stage3.ui.edit.profil.190e4" />
                            </Button>
                        )}
                        {permissions.archive && (
                            <Button
                                variant="destructive"
                                onClick={archiveCustomer}
                            >
                                <Trash2 />
                                <Stage3Text k="stage3.ui.arsipkan.5d7c1" />
                            </Button>
                        )}
                    </div>
                </header>

                {(typeof pageErrors.customer === 'string' ||
                    typeof pageErrors.loyalty === 'string') && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>
                            <Stage3Text k="stage3.ui.perubahan.ditolak.1750a" />
                        </AlertTitle>
                        <AlertDescription>
                            {String(pageErrors.customer ?? pageErrors.loyalty)}
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatisticCard
                        label={stage3Translate(
                            'stage3.ui.total.rental.27c39',
                            stage3Locale,
                        )}
                        value={statistics.rentals.toString()}
                        icon={ReceiptText}
                    />
                    <StatisticCard
                        label={stage3Translate(
                            'stage3.ui.rental.aktif.de680',
                            stage3Locale,
                        )}
                        value={statistics.activeRentals.toString()}
                        icon={Clock3}
                    />
                    <StatisticCard
                        label={stage3Translate(
                            'stage3.ui.nilai.rental.ef458',
                            stage3Locale,
                        )}
                        value={currency(statistics.rentalValue)}
                        icon={Banknote}
                    />
                    <StatisticCard
                        label={stage3Translate(
                            'stage3.ui.terbayar.78327',
                            stage3Locale,
                        )}
                        value={currency(statistics.paidAmount)}
                        icon={ShieldCheck}
                    />
                    <StatisticCard
                        label={stage3Translate(
                            'stage3.ui.outstanding.f8ee5',
                            stage3Locale,
                        )}
                        value={currency(statistics.outstanding)}
                        icon={AlertTriangle}
                    />
                </section>

                <section className="grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage3Text k="stage3.ui.profil.pelanggan.7301c" />
                            </CardTitle>
                            <CardDescription>
                                <Stage3Text k="stage3.ui.informasi.utama.untuk.pelayanan.dan.penilaian.r.8ff19" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Info
                                icon={Phone}
                                label={stage3Translate(
                                    'stage3.ui.telepon.396dc',
                                    stage3Locale,
                                )}
                                value={customer.phone}
                            />
                            <Info
                                icon={Mail}
                                label="Email"
                                value={customer.email}
                            />
                            <Info
                                icon={UserRound}
                                label={stage3Translate(
                                    'stage3.ui.jenis.kelamin.64cd3',
                                    stage3Locale,
                                )}
                                value={
                                    customer.gender === 'male'
                                        ? stage3Translate(
                                              'stage3.ui.correction.laki.laki.afdcb',
                                              stage3Locale,
                                          )
                                        : customer.gender === 'female'
                                          ? stage3Translate(
                                                'stage3.ui.correction.perempuan.bc797',
                                                stage3Locale,
                                            )
                                          : null
                                }
                            />
                            <Info
                                icon={CalendarDays}
                                label={stage3Translate(
                                    'stage3.ui.tempat.tanggal.lahir.c7ef3',
                                    stage3Locale,
                                )}
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
                                label={stage3Translate(
                                    'stage3.ui.institusi.305f2',
                                    stage3Locale,
                                )}
                                value={customer.institution}
                            />
                            <Info
                                icon={ReceiptText}
                                label={stage3Translate(
                                    'stage3.ui.rental.terakhir.f6a5c',
                                    stage3Locale,
                                )}
                                value={shortDate(statistics.lastRentalAt)}
                            />
                            <div className="sm:col-span-2">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <Stage3Text k="stage3.ui.catatan.internal.1ae31" />
                                </p>
                                <p className="mt-2 text-sm leading-6 whitespace-pre-wrap">
                                    {customer.notes ||
                                        stage3Translate(
                                            'stage3.ui.correction.belum.ada.catatan.be505',
                                            stage3Locale,
                                        )}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between">
                            <div>
                                <CardTitle>
                                    <Stage3Text k="stage3.ui.loyalty.e2b31" />
                                </CardTitle>
                                <CardDescription>
                                    <Stage3Text k="stage3.ui.saldo.tier.dan.lifetime.points.27a7b" />
                                </CardDescription>
                            </div>
                            {permissions.loyalty && (
                                <Button
                                    size="sm"
                                    onClick={() => setLoyaltyOpen(true)}
                                >
                                    <Coins />
                                    <Stage3Text k="stage3.ui.transaksi.poin.fd386" />
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
                                        <Stage3Text k="stage3.ui.saldo.8b0fc" />
                                    </p>
                                </div>
                                <div>
                                    <p className="text-2xl font-semibold">
                                        {number(loyalty?.lifetime_points ?? 0)}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        <Stage3Text k="stage3.ui.lifetime.8d33f" />
                                    </p>
                                </div>
                                <div>
                                    <p className="text-lg font-semibold capitalize">
                                        {stage3LoyaltyTier(
                                            loyalty?.tier ?? 'regular',
                                            stage3Locale,
                                        )}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        <Stage3Text k="stage3.ui.tier.5bd44" />
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
                                        <Stage3Text k="stage3.ui.belum.ada.transaksi.loyalty.c3804" />
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
                                <CardTitle>
                                    <Stage3Text k="stage3.ui.identitas.bac26" />
                                </CardTitle>
                                <CardDescription>
                                    <Stage3Text k="stage3.ui.dokumen.identitas.dan.status.verifikasi.cebfb" />
                                </CardDescription>
                            </div>
                            {permissions.update && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => openIdentity(null)}
                                >
                                    <Plus />
                                    <Stage3Text k="stage3.ui.tambah.a44eb" />
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
                                                    <Badge>
                                                        <Stage3Text k="stage3.ui.utama.de8e2" />
                                                    </Badge>
                                                )}
                                                <Badge
                                                    variant={
                                                        identity.verified_at
                                                            ? 'outline'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {identity.verified_at
                                                        ? stage3Translate(
                                                              'stage3.ui.correction.terverifikasi.b76a0',
                                                              stage3Locale,
                                                          )
                                                        : stage3Translate(
                                                              'stage3.ui.correction.belum.diverifikasi.53109',
                                                              stage3Locale,
                                                          )}
                                                </Badge>
                                            </div>
                                            <p className="mt-2 font-mono text-sm">
                                                {identity.number}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {identity.name_on_identity ||
                                                    customer.name}
                                                {identity.expires_at &&
                                                    ` · ${stage3Translate('stage3.ui.r2.customer.valid.until', stage3Locale, { date: shortDate(identity.expires_at) ?? '—' })}`}
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
                                                        <Stage3Text k="stage3.ui.verifikasi.84a23" />
                                                    </Button>
                                                )}
                                            {permissions.update && (
                                                <>
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label={stage3Translate(
                                                            'stage3.ui.edit.identitas.40a46',
                                                            stage3Locale,
                                                        )}
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
                                                        aria-label={stage3Translate(
                                                            'stage3.ui.hapus.identitas.24b65',
                                                            stage3Locale,
                                                        )}
                                                        onClick={() =>
                                                            void destroyIdentity(
                                                                identity,
                                                                confirm,
                                                                stage3Locale,
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
                                    text={stage3Translate(
                                        'stage3.ui.r2.customer.empty.identities',
                                        stage3Locale,
                                    )}
                                />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between">
                            <div>
                                <CardTitle>
                                    <Stage3Text k="stage3.ui.alamat.85b6e" />
                                </CardTitle>
                                <CardDescription>
                                    <Stage3Text k="stage3.ui.alamat.identitas.domisili.atau.tempat.kerja.85021" />
                                </CardDescription>
                            </div>
                            {permissions.update && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => openAddress(null)}
                                >
                                    <Plus />
                                    <Stage3Text k="stage3.ui.tambah.a44eb" />
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
                                                    <Badge>
                                                        <Stage3Text k="stage3.ui.utama.de8e2" />
                                                    </Badge>
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
                                                    aria-label={stage3Translate(
                                                        'stage3.ui.edit.alamat.3354d',
                                                        stage3Locale,
                                                    )}
                                                    onClick={() =>
                                                        openAddress(address)
                                                    }
                                                >
                                                    <Pencil />
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={stage3Translate(
                                                        'stage3.ui.hapus.alamat.97df4',
                                                        stage3Locale,
                                                    )}
                                                    onClick={() =>
                                                        void destroyAddress(
                                                            address,
                                                            confirm,
                                                            stage3Locale,
                                                        )
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
                                    text={stage3Translate(
                                        'stage3.ui.r2.customer.empty.addresses',
                                        stage3Locale,
                                    )}
                                />
                            )}
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 xl:grid-cols-2">
                    <HistoryCard
                        title={stage3Translate(
                            'stage3.ui.rental.terakhir.f6a5c',
                            stage3Locale,
                        )}
                        description={stage3Translate(
                            'stage3.ui.sepuluh.transaksi.rental.terbaru.pelanggan.66a01',
                            stage3Locale,
                        )}
                        empty={stage3Translate(
                            'stage3.ui.r2.customer.empty.rentals',
                            stage3Locale,
                        )}
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
                                        {dateTime(rental.checked_out_at)}{' '}
                                        <Stage3Text k="stage3.ui.jatuh.tempo.e1a6d" />
                                        {dateTime(rental.due_at)}
                                    </p>
                                </div>
                                <div className="sm:text-right">
                                    <Badge variant="outline">
                                        {stage3CustomerHistoryStatus(
                                            rental.status,
                                            'rental',
                                            stage3Locale,
                                        )}
                                    </Badge>
                                    <p className="mt-1 text-sm font-medium">
                                        {currency(rental.total_amount)}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </HistoryCard>

                    <HistoryCard
                        title={stage3Translate(
                            'stage3.ui.booking.terakhir.54fed',
                            stage3Locale,
                        )}
                        description={stage3Translate(
                            'stage3.ui.sepuluh.reservasi.terbaru.pelanggan.b07bb',
                            stage3Locale,
                        )}
                        empty={stage3Translate(
                            'stage3.ui.r2.customer.empty.bookings',
                            stage3Locale,
                        )}
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
                                        {booking.branch?.code ?? '—'}{' '}
                                        <Stage3Text k="stage3.ui.mulai.65f56" />{' '}
                                        {dateTime(booking.starts_at)}
                                    </p>
                                </div>
                                <div className="sm:text-right">
                                    <Badge variant="outline">
                                        {stage3CustomerHistoryStatus(
                                            booking.status,
                                            'booking',
                                            stage3Locale,
                                        )}
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
    const stage3Locale = useGlobalLocale();
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
                        {identity
                            ? stage3Translate(
                                  'stage3.ui.correction.edit.identitas.40a46',
                                  stage3Locale,
                              )
                            : stage3Translate(
                                  'stage3.ui.correction.tambah.identitas.dcb13',
                                  stage3Locale,
                              )}
                    </DialogTitle>
                    <DialogDescription>
                        <Stage3Text k="stage3.ui.dokumen.yang.diverifikasi.dapat.digunakan.sebag.6551e" />
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        label={stage3Translate(
                            'stage3.ui.jenis.identitas.f891f',
                            stage3Locale,
                        )}
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
                                <SelectItem value="ktp">
                                    <Stage3Text k="stage3.ui.ktp.101c2" />
                                </SelectItem>
                                <SelectItem value="sim">
                                    <Stage3Text k="stage3.ui.sim.9563e" />
                                </SelectItem>
                                <SelectItem value="passport">
                                    <Stage3Text k="stage3.ui.paspor.31953" />
                                </SelectItem>
                                <SelectItem value="student_card">
                                    <Stage3Text k="stage3.ui.kartu.pelajar.868f2" />
                                </SelectItem>
                                <SelectItem value="employee_card">
                                    <Stage3Text k="stage3.ui.kartu.karyawan.5a740" />
                                </SelectItem>
                                <SelectItem value="other">
                                    <Stage3Text k="stage3.ui.lainnya.844f8" />
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label={stage3Translate(
                            'stage3.ui.nomor.identitas.54fdf',
                            stage3Locale,
                        )}
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
                        label={stage3Translate(
                            'stage3.ui.nama.pada.identitas.a8a50',
                            stage3Locale,
                        )}
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
                        label={stage3Translate(
                            'stage3.ui.masa.berlaku.2d469',
                            stage3Locale,
                        )}
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
                            <Stage3Text k="stage3.ui.jadikan.identitas.utama.10074" />
                        </span>
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            <Stage3Text k="stage3.ui.batal.14335" />
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? stage3Translate(
                                      'stage3.ui.correction.menyimpan.92e24',
                                      stage3Locale,
                                  )
                                : stage3Translate(
                                      'stage3.ui.correction.simpan.identitas.807e3',
                                      stage3Locale,
                                  )}
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
    const stage3Locale = useGlobalLocale();
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
                        {address
                            ? stage3Translate(
                                  'stage3.ui.correction.edit.alamat.3354d',
                                  stage3Locale,
                              )
                            : stage3Translate(
                                  'stage3.ui.correction.tambah.alamat.83ad0',
                                  stage3Locale,
                              )}
                    </DialogTitle>
                    <DialogDescription>
                        <Stage3Text k="stage3.ui.simpan.alamat.secara.terstruktur.untuk.verifika.f1ca6" />
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        label={stage3Translate(
                            'stage3.ui.jenis.alamat.37325',
                            stage3Locale,
                        )}
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
                                    <Stage3Text k="stage3.ui.identitas.bac26" />
                                </SelectItem>
                                <SelectItem value="domicile">
                                    <Stage3Text k="stage3.ui.domisili.89bfd" />
                                </SelectItem>
                                <SelectItem value="work">
                                    <Stage3Text k="stage3.ui.tempat.kerja.32b42" />
                                </SelectItem>
                                <SelectItem value="other">
                                    <Stage3Text k="stage3.ui.lainnya.844f8" />
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label={stage3Translate(
                            'stage3.ui.alamat.lengkap.328e8',
                            stage3Locale,
                        )}
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
                            <Stage3Text k="stage3.ui.jadikan.alamat.utama.06332" />
                        </span>
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            <Stage3Text k="stage3.ui.batal.14335" />
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? stage3Translate(
                                      'stage3.ui.correction.menyimpan.92e24',
                                      stage3Locale,
                                  )
                                : stage3Translate(
                                      'stage3.ui.correction.simpan.alamat.78405',
                                      stage3Locale,
                                  )}
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
    const stage3Locale = useGlobalLocale();
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
                    <DialogTitle>
                        <Stage3Text k="stage3.ui.transaksi.loyalty.6331c" />
                    </DialogTitle>
                    <DialogDescription>
                        <Stage3Text k="stage3.ui.saldo.sekarang.f98ee" />
                        {number(balance)}{' '}
                        <Stage3Text k="stage3.ui.poin.redeem.tidak.dapat.membuat.saldo.menjadi.n.1bd42" />
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        label={stage3Translate(
                            'stage3.ui.jenis.transaksi.4d19f',
                            stage3Locale,
                        )}
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
                                    <Stage3Text k="stage3.ui.tambah.poin.cb670" />
                                </SelectItem>
                                <SelectItem value="redeem">
                                    <Stage3Text k="stage3.ui.tukar.poin.a1cb4" />
                                </SelectItem>
                                <SelectItem value="adjustment">
                                    <Stage3Text k="stage3.ui.koreksi.manual.fc56d" />
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label={stage3Translate(
                            'stage3.ui.jumlah.poin.2fc65',
                            stage3Locale,
                        )}
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
                        label={stage3Translate(
                            'stage3.ui.keterangan.557f5',
                            stage3Locale,
                        )}
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
                            <Stage3Text k="stage3.ui.batal.14335" />
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? stage3Translate(
                                      'stage3.ui.correction.menyimpan.92e24',
                                      stage3Locale,
                                  )
                                : stage3Translate(
                                      'stage3.ui.correction.simpan.transaksi.40d7d',
                                      stage3Locale,
                                  )}
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
    return <MetricCard label={label} value={value} icon={Icon} compact />;
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
    const stage3Locale = useGlobalLocale();

    return (
        <div className="flex gap-3 rounded-lg border p-3">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-1 truncate text-sm font-medium">
                    {value ||
                        stage3Translate(
                            'stage3.ui.correction.belum.diisi.098d4',
                            stage3Locale,
                        )}
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
    const stage3Locale = useGlobalLocale();

    return (
        <Badge variant={status === 'active' ? 'outline' : 'secondary'}>
            {status === 'active'
                ? stage3Translate(
                      'stage3.ui.correction.aktif.89f29',
                      stage3Locale,
                  )
                : status === 'blocked'
                  ? stage3Translate(
                        'stage3.ui.correction.diblokir.ae752',
                        stage3Locale,
                    )
                  : stage3Translate(
                        'stage3.ui.correction.nonaktif.60944',
                        stage3Locale,
                    )}
        </Badge>
    );
}

function RiskBadge({ risk }: { risk: Customer['risk_level'] }) {
    const stage3Locale = useGlobalLocale();
    const riskLabels = {
        low: stage3Locale === 'en' ? 'low' : 'rendah',
        normal: stage3Locale === 'en' ? 'normal' : 'normal',
        high: stage3Locale === 'en' ? 'high' : 'tinggi',
        critical: stage3Locale === 'en' ? 'critical' : 'kritis',
    };

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
            <Stage3Text k="stage3.ui.risiko.25055" /> {riskLabels[risk]}
        </Badge>
    );
}

async function destroyIdentity(
    identity: CustomerIdentity,
    confirm: Confirm,
    stage3Locale: AppLocale,
) {
    const confirmed = await confirm({
        title: stage3Translate(
            'stage3.ui.correction.confirm.identity.delete.title',
            stage3Locale,
        ),
        description: stage3Translate(
            'stage3.ui.correction.confirm.identity.delete',
            stage3Locale,
            { number: identity.number },
        ),
        confirmLabel: stage3Translate(
            'stage3.ui.correction.confirm.identity.delete.action',
            stage3Locale,
        ),
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    router.delete(`/customer-identities/${identity.id}`, {
        preserveScroll: true,
    });
}

async function destroyAddress(
    address: CustomerAddress,
    confirm: Confirm,
    stage3Locale: AppLocale,
) {
    const confirmed = await confirm({
        title: stage3Translate(
            'stage3.ui.correction.confirm.address.delete.title',
            stage3Locale,
        ),
        description: stage3Translate(
            'stage3.ui.correction.confirm.address.delete',
            stage3Locale,
        ),
        confirmLabel: stage3Translate(
            'stage3.ui.correction.confirm.address.delete.action',
            stage3Locale,
        ),
        variant: 'destructive',
    });

    if (!confirmed) {
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
    return value ? formatStage3Date(value, getEffectiveLocale()) : null;
}

function dateTime(value: string | null | undefined) {
    if (!value) {
        return getEffectiveLocale() === 'en'
            ? 'Time unavailable'
            : 'Waktu tidak tersedia';
    }

    return new Intl.DateTimeFormat(
        getEffectiveLocale() === 'en' ? 'en-GB' : 'id-ID',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            timeZone: 'Asia/Jakarta',
            hour: '2-digit',
            minute: '2-digit',
        },
    ).format(new Date(value));
}
