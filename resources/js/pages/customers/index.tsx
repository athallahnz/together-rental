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
            <Head title="Pelanggan" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Customer Relationship Management
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Pelanggan
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Satu database pelanggan lintas cabang dengan
                            identitas, membership, loyalty, risk profile, dan
                            histori transaksi yang terhubung.
                        </p>
                    </div>
                    {permissions.create && (
                        <Button onClick={openCreate}>
                            <Plus />
                            Tambah pelanggan
                        </Button>
                    )}
                </header>

                {typeof pageErrors.customer === 'string' && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>Perubahan pelanggan ditolak</AlertTitle>
                        <AlertDescription>
                            {pageErrors.customer}
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        {
                            label: 'Total pelanggan',
                            value: summary.total,
                            icon: ContactRound,
                        },
                        {
                            label: 'Pelanggan aktif',
                            value: summary.active,
                            icon: CheckCircle2,
                        },
                        {
                            label: 'Member',
                            value: summary.members,
                            icon: Gem,
                        },
                        {
                            label: 'Risiko tinggi',
                            value: summary.highRisk,
                            icon: AlertTriangle,
                        },
                        {
                            label: 'Identitas belum valid',
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
                            <CardTitle>Database pelanggan</CardTitle>
                            <CardDescription>
                                Pencarian mencakup nama, telepon, nomor
                                pelanggan, nomor member, dan nomor identitas.
                            </CardDescription>
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
                                        placeholder="Cari pelanggan"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="icon"
                                    variant="outline"
                                    aria-label="Cari pelanggan"
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
                                placeholder="Semua cabang"
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
                                placeholder="Semua status"
                                options={[
                                    { value: 'active', label: 'Aktif' },
                                    { value: 'inactive', label: 'Nonaktif' },
                                    { value: 'blocked', label: 'Diblokir' },
                                ]}
                            />
                            <FilterSelect
                                value={filters.membership || 'all'}
                                onValueChange={(membership) =>
                                    applyFilters({ membership })
                                }
                                placeholder="Semua pelanggan"
                                options={[
                                    { value: 'member', label: 'Member' },
                                    { value: 'regular', label: 'Non-member' },
                                ]}
                            />
                            <FilterSelect
                                value={filters.risk || 'all'}
                                onValueChange={(risk) => applyFilters({ risk })}
                                placeholder="Semua risiko"
                                options={[
                                    { value: 'low', label: 'Rendah' },
                                    { value: 'normal', label: 'Normal' },
                                    { value: 'high', label: 'Tinggi' },
                                    { value: 'critical', label: 'Kritis' },
                                ]}
                            />
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {customers.data.length === 0 ? (
                            <div className="py-16 text-center">
                                <ContactRound className="mx-auto size-9 text-muted-foreground" />
                                <p className="mt-4 font-medium">
                                    Pelanggan tidak ditemukan
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Ubah filter atau tambahkan pelanggan baru.
                                </p>
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
                                                        Member
                                                    </Badge>
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
                                                    'Kontak belum diisi'}
                                            </p>
                                        </div>

                                        <div className="grid gap-2 text-sm">
                                            <p>
                                                {customer.registered_branch
                                                    ? `${customer.registered_branch.code} · ${customer.registered_branch.name}`
                                                    : 'Cabang pendaftaran tidak tersedia'}
                                            </p>
                                            <p className="flex items-center gap-2 text-muted-foreground">
                                                <IdCard className="size-4" />
                                                {customer.primary_identity
                                                    ? `${customer.primary_identity.type.toUpperCase()} · ${customer.primary_identity.number}`
                                                    : 'Identitas belum ditambahkan'}
                                                {customer.primary_identity
                                                    ?.verified_at && (
                                                    <BadgeCheck className="size-4 text-emerald-600" />
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {customer.rentals_count ?? 0}{' '}
                                                rental ·{' '}
                                                {customer.loyalty_account
                                                    ?.points_balance ?? 0}{' '}
                                                poin
                                            </p>
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
                                                    Customer 360°
                                                </Link>
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
                                                    Edit
                                                </Button>
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
    const label = {
        low: 'Risiko rendah',
        normal: 'Risiko normal',
        high: 'Risiko tinggi',
        critical: 'Risiko kritis',
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
