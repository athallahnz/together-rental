import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    BadgeCheck,
    CheckCircle2,
    CircleOff,
    ContactRound,
    Eye,
    Gem,
    IdCard,
    Pencil,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import { useGlobalLocale } from '@/lib/locale-store';
import { Stage3Text, stage3Translate } from '@/components/stage3-text';
import { CustomerFormDialog } from '@/components/customers/customer-form-dialog';
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
import { Input } from '@/components/ui/input';
import { MetricCard } from '@/components/ui/metric-card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AccessBranch, Customer, Pagination } from '@/types';

type Props = {
    customers: Pagination<Customer>;
    summary: {
        total: number;
        active: number;
        members: number;
        highRisk: number;
        unverified: number;
    };
    filters: {
        search: string;
        status: string;
        membership: string;
        risk: string;
        branch_id: number | null;
    };
    branches: AccessBranch[];
    permissions: {
        create: boolean;
        update: boolean;
        archive: boolean;
        verify: boolean;
        loyalty: boolean;
    };
};

export default function CustomerIndex({
    customers,
    summary,
    filters,
    branches,
    permissions,
}: Props) {
    const stage3Locale = useGlobalLocale();
    const { errors: pageErrors } = usePage().props;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<Customer | null>(
        null,
    );
    const [search, setSearch] = useState(filters.search);

    const openCreate = () => {
        setEditingCustomer(null);
        setDialogOpen(true);
    };

    const openEdit = (customer: Customer) => {
        setEditingCustomer(customer);
        setDialogOpen(true);
    };

    const applyFilters = (
        next: Partial<{
            status: string;
            membership: string;
            risk: string;
            branch_id: number | null;
        }> = {},
    ) => {
        const status = next.status ?? filters.status;
        const membership = next.membership ?? filters.membership;
        const risk = next.risk ?? filters.risk;
        const params = {
            search: search || undefined,
            status: status === 'all' ? undefined : status || undefined,
            membership:
                membership === 'all' ? undefined : membership || undefined,
            risk: risk === 'all' ? undefined : risk || undefined,
            branch_id:
                next.branch_id === null
                    ? undefined
                    : (next.branch_id ?? filters.branch_id ?? undefined),
        };

        router.get('/customers', params, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title={stage3Translate('stage3.ui.pelanggan.af0ab', stage3Locale)} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            <Stage3Text k="stage3.ui.customer.relationship.management.7f42d" /></p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            <Stage3Text k="stage3.ui.pelanggan.af0ab" /></h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            <Stage3Text k="stage3.ui.satu.database.pelanggan.lintas.cabang.dengan.id.033ce" /></p>
                    </div>
                    {permissions.create && (
                        <Button onClick={openCreate}>
                            <Plus />
                            <Stage3Text k="stage3.ui.tambah.pelanggan.bc83e" /></Button>
                    )}
                </header>

                {typeof pageErrors.customer === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle><Stage3Text k="stage3.ui.perubahan.pelanggan.ditolak.f95ec" /></AlertTitle>
                        <AlertDescription>
                            {pageErrors.customer}
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        {
                            label: stage3Translate('stage3.ui.correction.total.pelanggan.7797c', stage3Locale),
                            value: summary.total,
                            icon: ContactRound,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.pelanggan.aktif.a9b25', stage3Locale),
                            value: summary.active,
                            icon: CheckCircle2,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.member.6853c', stage3Locale),
                            value: summary.members,
                            icon: Gem,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.risiko.tinggi.035bf', stage3Locale),
                            value: summary.highRisk,
                            icon: AlertTriangle,
                        },
                        {
                            label: stage3Translate('stage3.ui.correction.identitas.belum.valid.43262', stage3Locale),
                            value: summary.unverified,
                            icon: IdCard,
                        },
                    ].map(({ label, value, icon: Icon }) => (
                        <MetricCard
                            key={label}
                            label={label}
                            value={value}
                            icon={Icon}
                            tone={
                                label === 'Risiko tinggi' && Number(value) > 0
                                    ? 'danger'
                                    : 'neutral'
                            }
                        />
                    ))}
                </section>

                <Card>
                    <CardHeader className="gap-4">
                        <div>
                            <CardTitle><Stage3Text k="stage3.ui.database.pelanggan.5adf4" /></CardTitle>
                            <CardDescription>
                                <Stage3Text k="stage3.ui.pencarian.mencakup.nama.telepon.nomor.pelanggan.faab5" /></CardDescription>
                        </div>
                        <div
                            data-slot="filter-grid"
                            className="grid items-end gap-3 rounded-xl border bg-muted/25 p-4 sm:grid-cols-2 xl:grid-cols-[minmax(260px,1fr)_repeat(4,minmax(140px,auto))]"
                        >
                            <form
                                className="flex gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    applyFilters();
                                }}
                            >
                                <div className="relative flex-1">
                                    <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        className="pl-9"
                                        placeholder={stage3Translate('stage3.ui.cari.pelanggan.0e369', stage3Locale)}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="icon"
                                    variant="outline"
                                    aria-label={stage3Translate('stage3.ui.cari.pelanggan.0e369', stage3Locale)}
                                >
                                    <Search />
                                </Button>
                            </form>
                            <FilterSelect
                                value={filters.branch_id?.toString() ?? 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        branch_id:
                                            value === 'all'
                                                ? null
                                                : Number(value),
                                    })
                                }
                                placeholder={stage3Translate('stage3.ui.semua.cabang.27d30', stage3Locale)}
                                options={branches.map((branch) => ({
                                    value: branch.id.toString(),
                                    label: `${branch.code} · ${branch.name}`,
                                }))}
                            />
                            <FilterSelect
                                value={filters.status || 'all'}
                                onValueChange={(status) =>
                                    applyFilters({ status })
                                }
                                placeholder={stage3Translate('stage3.ui.semua.status.baa2a', stage3Locale)}
                                options={[
                                    { value: 'active', label: stage3Translate('stage3.ui.correction.aktif.89f29', stage3Locale) },
                                    { value: 'inactive', label: stage3Translate('stage3.ui.correction.nonaktif.60944', stage3Locale) },
                                    { value: 'blocked', label: stage3Translate('stage3.ui.correction.diblokir.ae752', stage3Locale) },
                                ]}
                            />
                            <FilterSelect
                                value={filters.membership || 'all'}
                                onValueChange={(membership) =>
                                    applyFilters({ membership })
                                }
                                placeholder={stage3Translate('stage3.ui.semua.pelanggan.50b93', stage3Locale)}
                                options={[
                                    { value: 'member', label: stage3Translate('stage3.ui.correction.member.6853c', stage3Locale) },
                                    { value: 'regular', label: stage3Translate('stage3.ui.correction.non.member.b65c4', stage3Locale) },
                                ]}
                            />
                            <FilterSelect
                                value={filters.risk || 'all'}
                                onValueChange={(risk) => applyFilters({ risk })}
                                placeholder={stage3Translate('stage3.ui.semua.risiko.d8f13', stage3Locale)}
                                options={[
                                    { value: 'low', label: stage3Translate('stage3.ui.correction.rendah.afc56', stage3Locale) },
                                    { value: 'normal', label: stage3Translate('stage3.ui.correction.normal.45e11', stage3Locale) },
                                    { value: 'high', label: stage3Translate('stage3.ui.correction.tinggi.dc1b9', stage3Locale) },
                                    { value: 'critical', label: stage3Translate('stage3.ui.correction.kritis.f690d', stage3Locale) },
                                ]}
                            />
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {customers.data.length === 0 ? (
                            <div className="py-16 text-center">
                                <ContactRound className="mx-auto size-9 text-muted-foreground" />
                                <p className="mt-4 font-medium">
                                    <Stage3Text k="stage3.ui.pelanggan.tidak.ditemukan.b95f6" /></p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    <Stage3Text k="stage3.ui.ubah.filter.atau.tambahkan.pelanggan.baru.e58c5" /></p>
                            </div>
                        ) : (
                            <div className="grid gap-3">
                                {customers.data.map((customer) => (
                                    <article
                                        key={customer.id}
                                        className="grid gap-4 rounded-xl border p-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto] lg:items-center"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={`/customers/${customer.id}`}
                                                    className="truncate font-semibold hover:underline"
                                                >
                                                    {customer.name}
                                                </Link>
                                                <CustomerStatusBadge
                                                    status={customer.status}
                                                />
                                                <RiskBadge
                                                    risk={customer.risk_level}
                                                />
                                                {customer.is_member && (
                                                    <Badge>
                                                        <Gem />
                                                        <Stage3Text k="stage3.ui.member.6853c" /></Badge>
                                                )}
                                            </div>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                {customer.customer_number}
                                                {customer.member_number &&
                                                    ` · ${customer.member_number}`}
                                            </p>
                                            <p className="mt-2 truncate text-sm text-muted-foreground">
                                                {customer.phone ||
                                                    customer.email ||
                                                    stage3Translate('stage3.ui.correction.kontak.belum.diisi.c944e', stage3Locale)}
                                            </p>
                                        </div>

                                        <div className="grid gap-2 text-sm">
                                            <p>
                                                {customer.registered_branch
                                                    ? `${customer.registered_branch.code} · ${customer.registered_branch.name}`
                                                    : stage3Translate('stage3.ui.correction.cabang.pendaftaran.tidak.tersedia.ab83f', stage3Locale)}
                                            </p>
                                            <p className="flex items-center gap-2 text-muted-foreground">
                                                <IdCard className="size-4" />
                                                {customer.primary_identity
                                                    ? `${customer.primary_identity.type.toUpperCase()} · ${customer.primary_identity.number}`
                                                    : stage3Translate('stage3.ui.correction.identitas.belum.ditambahkan.85c89', stage3Locale)}
                                                {customer.primary_identity
                                                    ?.verified_at && (
                                                    <BadgeCheck className="size-4 text-emerald-600" />
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {customer.rentals_count ?? 0}{' '}
                                                <Stage3Text k="stage3.ui.rental.1442b" />{' '}
                                                {customer.loyalty_account
                                                    ?.points_balance ?? 0}{' '}
                                                <Stage3Text k="stage3.ui.poin.07d36" /></p>
                                        </div>

                                        <div className="flex flex-wrap gap-2 lg:justify-end">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={`/customers/${customer.id}`}
                                                >
                                                    <Eye />
                                                    <Stage3Text k="stage3.ui.customer.360.c4f8b" /></Link>
                                            </Button>
                                            {permissions.update && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        openEdit(customer)
                                                    }
                                                >
                                                    <Pencil />
                                                    <Stage3Text k="stage3.ui.edit.53016" /></Button>
                                            )}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}

                        <PaginationLinks
                            links={customers.links}
                            from={customers.from}
                            to={customers.to}
                            total={customers.total}
                        />
                    </CardContent>
                </Card>
            </div>

            <CustomerFormDialog
                key={editingCustomer?.id ?? 'new-customer'}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                customer={editingCustomer}
                branches={branches}
            />
        </>
    );
}

function FilterSelect({
    value,
    onValueChange,
    placeholder,
    options,
}: {
    value: string;
    onValueChange: (value: string) => void;
    placeholder: string;
    options: Array<{ value: string; label: string }>;
}) {
    return (
        <Select value={value} onValueChange={onValueChange}>
            <SelectTrigger>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{placeholder}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function CustomerStatusBadge({ status }: { status: Customer['status'] }) {
    const stage3Locale = useGlobalLocale();

    return (
        <Badge variant={status === 'active' ? 'outline' : 'secondary'}>
            {status === 'active'
                ? stage3Translate('stage3.ui.correction.aktif.89f29', stage3Locale)
                : status === 'blocked'
                  ? stage3Translate('stage3.ui.correction.diblokir.ae752', stage3Locale)
                  : stage3Translate('stage3.ui.correction.nonaktif.60944', stage3Locale)}
        </Badge>
    );
}

function RiskBadge({ risk }: { risk: Customer['risk_level'] }) {
    const stage3Locale = useGlobalLocale();
    const label = {
        low: stage3Locale === 'en' ? 'Low risk' : 'Risiko rendah',
        normal: stage3Locale === 'en' ? 'Normal risk' : 'Risiko normal',
        high: stage3Locale === 'en' ? 'High risk' : 'Risiko tinggi',
        critical: stage3Locale === 'en' ? 'Critical risk' : 'Risiko kritis',
    }[risk];

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
            {label}
        </Badge>
    );
}
