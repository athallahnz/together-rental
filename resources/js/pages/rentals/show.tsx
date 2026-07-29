import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Rental = {
    id: number;
    rental_number: string;
    status: string;
    checked_out_at: string;
    due_at: string;
    subtotal: string;
    total_amount: string;
    paid_amount: string;
    deposit_amount: string;
    balance_due: string;
    notes: string | null;
    branch: { name: string };
    customer: { name: string; customer_number: string; phone: string | null };
    booking?: { booking_number: string; source: string } | null;
    rate_plan?: { name: string } | null;
    items: Array<{
        id: number;
        description: string;
        quantity: number;
        total_amount: string;
        assets: Array<{
            id: number;
            checkout_condition: string;
            notes: string | null;
            asset: {
                asset_code: string;
                serial_number: string | null;
                status: string;
            };
        }>;
    }>;
    status_histories: Array<{
        id: number;
        from_status: string | null;
        to_status: string;
        reason: string | null;
        changer?: { name: string } | null;
    }>;
};
type Props = { rental: Rental; permissions: { return: boolean } };
const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function RentalShow({ rental }: Props) {
    return (
        <>
            <Head title={rental.rental_number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/rentals">
                            <ArrowLeft />
                            Daftar rental
                        </Link>
                    </Button>
                    <div className="mt-3 flex items-center gap-3">
                        <h1 className="text-2xl font-semibold">
                            {rental.rental_number}
                        </h1>
                        <Badge>{rental.status}</Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {rental.customer.name} · {rental.branch.name}
                    </p>
                </header>
                <section className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Jadwal</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p>
                                <b>Checkout:</b>{' '}
                                {new Date(rental.checked_out_at).toLocaleString(
                                    'id-ID',
                                )}
                            </p>
                            <p>
                                <b>Batas kembali:</b>{' '}
                                {new Date(rental.due_at).toLocaleString(
                                    'id-ID',
                                )}
                            </p>
                            <p>
                                <b>Rate:</b> {rental.rate_plan?.name ?? '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Asal transaksi</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p>
                                {rental.booking?.source === 'direct'
                                    ? 'Rental langsung'
                                    : 'Checkout booking'}
                            </p>
                            <p>{rental.booking?.booking_number}</p>
                            <p>
                                {rental.customer.customer_number} ·{' '}
                                {rental.customer.phone ?? '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Pembayaran</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="flex justify-between">
                                <span>Total</span>
                                <b>
                                    {money.format(Number(rental.total_amount))}
                                </b>
                            </p>
                            <p className="flex justify-between">
                                <span>Dibayar</span>
                                <b>
                                    {money.format(Number(rental.paid_amount))}
                                </b>
                            </p>
                            <p className="flex justify-between">
                                <span>Deposit</span>
                                <b>
                                    {money.format(
                                        Number(rental.deposit_amount),
                                    )}
                                </b>
                            </p>
                            <p className="flex justify-between border-t pt-2">
                                <span>Sisa</span>
                                <b>
                                    {money.format(Number(rental.balance_due))}
                                </b>
                            </p>
                        </CardContent>
                    </Card>
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle>Unit yang dibawa</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {rental.items.map((item) => (
                            <div
                                key={item.id}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex justify-between">
                                    <p className="font-medium">
                                        {item.description}
                                    </p>
                                    <b>
                                        {money.format(
                                            Number(item.total_amount),
                                        )}
                                    </b>
                                </div>
                                <div className="mt-3 grid gap-2 md:grid-cols-2">
                                    {item.assets.map((line) => (
                                        <div
                                            key={line.id}
                                            className="rounded-md bg-muted p-3 text-sm"
                                        >
                                            <p className="font-medium">
                                                {line.asset.asset_code}
                                            </p>
                                            <p className="text-muted-foreground">
                                                Kondisi{' '}
                                                {line.checkout_condition}
                                                {line.asset.serial_number
                                                    ? ` · SN ${line.asset.serial_number}`
                                                    : ''}
                                            </p>
                                            {line.notes && <p>{line.notes}</p>}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
