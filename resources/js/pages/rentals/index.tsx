import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Eye, Plus, Search, ShoppingBag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { AccessBranch, Pagination } from '@/types';

type RentalRow = {
    id: number;
    rental_number: string;
    status: string;
    checked_out_at: string;
    due_at: string;
    total_amount: string;
    balance_due: string;
    items_count: number;
    branch: AccessBranch;
    customer: { name: string; phone: string | null };
    booking?: { booking_number: string; source: string } | null;
};
type Props = {
    rentals: Pagination<RentalRow>;
    summary: { active: number; overdue: number; today: number };
    filters: { search: string; status: string; branchId: number | null };
    permissions: { create: boolean };
};
const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function RentalIndex({
    rentals,
    summary,
    filters,
    permissions,
}: Props) {
    return (
        <>
            <Head title="Rental aktif" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Rental aktif</h1>
                        <p className="text-sm text-muted-foreground">
                            Checkout booking dan transaksi walk-in dalam satu
                            daftar.
                        </p>
                    </div>
                    {permissions.create && (
                        <Button asChild>
                            <Link href="/rentals/direct/create">
                                <Plus />
                                Rental langsung
                            </Link>
                        </Button>
                    )}
                </header>
                <section className="grid gap-4 sm:grid-cols-3">
                    <Summary
                        label="Sedang disewa"
                        value={summary.active}
                        icon={ShoppingBag}
                    />
                    <Summary
                        label="Jatuh tempo"
                        value={summary.overdue}
                        icon={AlertTriangle}
                    />
                    <Summary
                        label="Checkout hari ini"
                        value={summary.today}
                        icon={ShoppingBag}
                    />
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar transaksi</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="mb-4 flex gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const data = new FormData(event.currentTarget);

                                router.get('/rentals', {
                                    search: String(data.get('search') ?? ''),
                                });
                            }}
                        >
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Nomor rental atau pelanggan"
                            />
                            <Button variant="outline">
                                <Search />
                                Cari
                            </Button>
                        </form>
                        <div className="space-y-3">
                            {rentals.data.map((rental) => (
                                <div
                                    key={rental.id}
                                    className="flex flex-col gap-3 rounded-lg border p-4 md:flex-row md:items-center md:justify-between"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {rental.rental_number}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {rental.customer.name} ·{' '}
                                            {rental.branch.name} ·{' '}
                                            {rental.items_count} item
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Kembali{' '}
                                            {new Date(
                                                rental.due_at,
                                            ).toLocaleString('id-ID')}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <div className="text-right text-sm">
                                            <p className="font-medium">
                                                {money.format(
                                                    Number(rental.total_amount),
                                                )}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {rental.status}
                                            </p>
                                        </div>
                                        <Button
                                            size="icon"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={`/rentals/${rental.id}`}
                                            >
                                                <Eye />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {rentals.data.length === 0 && (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Belum ada rental pada filter ini.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Summary({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number;
    icon: typeof ShoppingBag;
}) {
    return (
        <Card>
            <CardContent className="flex items-center justify-between p-5">
                <div>
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="text-2xl font-semibold">{value}</p>
                </div>
                <Icon className="size-5 text-muted-foreground" />
            </CardContent>
        </Card>
    );
}
