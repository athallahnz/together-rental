import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RupiahInput } from '@/components/ui/rupiah-input';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Branch = { id: number; code: string; name: string };
type Asset = {
    id: number;
    asset_code: string;
    serial_number: string | null;
    status: string;
    condition: string;
    product: { id: number; sku: string; name: string };
    current_branch: Branch;
};
type Order = {
    id: number;
    maintenance_number: string;
    type: string;
    status: string;
    vendor_name: string | null;
    estimated_cost: string;
    actual_cost: string;
    reported_at: string;
    branch: Branch;
    asset: Asset;
};
type Props = {
    maintenanceOrders: {
        data: Order[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    summary: {
        reported: number;
        in_progress: number;
        completed: number;
        actual_cost: number;
    };
    assets: Asset[];
    branches: Branch[];
    filters: { search: string; status: string; branchId: number | null };
    permissions: { manage: boolean };
};

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});
const statusLabel: Record<string, string> = {
    reported: 'Dilaporkan',
    in_progress: 'Dikerjakan',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

export default function MaintenanceIndex({
    maintenanceOrders,
    summary,
    assets,
    branches,
    filters,
    permissions,
}: Props) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState(filters.search);
    const form = useForm({
        asset_id: '',
        type: 'repair',
        problem_description: '',
        vendor_name: '',
        estimated_cost: 0,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/maintenance', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    const filter = (values: Record<string, string>) => {
        router.get(
            '/maintenance',
            {
                search,
                status: filters.status,
                branch_id: filters.branchId ?? '',
                ...values,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Maintenance Aset" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Maintenance Aset
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kendalikan perbaikan, biaya, downtime, dan kesiapan
                            unit.
                        </p>
                    </div>
                    {permissions.manage && (
                        <Button onClick={() => setOpen(true)}>
                            Buat Maintenance
                        </Button>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Summary title="Dilaporkan" value={summary.reported} />
                    <Summary title="Dikerjakan" value={summary.in_progress} />
                    <Summary title="Selesai" value={summary.completed} />
                    <Summary
                        title="Biaya terealisasi"
                        value={money.format(summary.actual_cost)}
                    />
                </div>

                <Card>
                    <CardContent className="grid gap-3 pt-6 md:grid-cols-4">
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) =>
                                event.key === 'Enter' && filter({})
                            }
                            placeholder="Nomor, aset, produk, vendor..."
                        />
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                filter({ status: value === 'all' ? '' : value })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                                {Object.entries(statusLabel).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.branchId?.toString() ?? 'all'}
                            onValueChange={(value) =>
                                filter({
                                    branch_id: value === 'all' ? '' : value,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua cabang" />
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
                                        {branch.code} — {branch.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button variant="outline" onClick={() => filter({})}>
                            Cari
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Work Order</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-sm">
                            <thead className="border-b text-left text-muted-foreground">
                                <tr>
                                    <th className="py-3">Nomor</th>
                                    <th>Aset</th>
                                    <th>Cabang</th>
                                    <th>Status</th>
                                    <th>Vendor</th>
                                    <th className="text-right">Estimasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {maintenanceOrders.data.map((order) => (
                                    <tr key={order.id} className="border-b">
                                        <td className="py-3 font-medium">
                                            <Link
                                                className="hover:underline"
                                                href={`/maintenance/${order.id}`}
                                            >
                                                {order.maintenance_number}
                                            </Link>
                                        </td>
                                        <td>
                                            {order.asset.asset_code} ·{' '}
                                            {order.asset.product.name}
                                        </td>
                                        <td>{order.branch.code}</td>
                                        <td>
                                            {statusLabel[order.status] ??
                                                order.status}
                                        </td>
                                        <td>{order.vendor_name || '—'}</td>
                                        <td className="text-right">
                                            {money.format(
                                                Number(order.estimated_cost),
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {maintenanceOrders.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            Belum ada maintenance sesuai filter.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {maintenanceOrders.links.map((link, index) => (
                                <Button
                                    key={index}
                                    size="sm"
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    disabled={!link.url}
                                    asChild={Boolean(link.url)}
                                >
                                    {link.url ? (
                                        <Link
                                            href={link.url}
                                            preserveScroll
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ) : (
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    )}
                                </Button>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Buat Maintenance Aset</DialogTitle>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submit}>
                        <Field label="Aset" error={form.errors.asset_id}>
                            <Select
                                value={form.data.asset_id}
                                onValueChange={(value) =>
                                    form.setData('asset_id', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih aset" />
                                </SelectTrigger>
                                <SelectContent>
                                    {assets.map((asset) => (
                                        <SelectItem
                                            key={asset.id}
                                            value={asset.id.toString()}
                                        >
                                            {asset.asset_code} —{' '}
                                            {asset.product.name} (
                                            {asset.current_branch.code})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Jenis" error={form.errors.type}>
                            <Select
                                value={form.data.type}
                                onValueChange={(value) =>
                                    form.setData('type', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="repair">
                                        Perbaikan
                                    </SelectItem>
                                    <SelectItem value="service">
                                        Servis berkala
                                    </SelectItem>
                                    <SelectItem value="inspection">
                                        Inspeksi
                                    </SelectItem>
                                    <SelectItem value="cleaning">
                                        Cleaning
                                    </SelectItem>
                                    <SelectItem value="other">
                                        Lainnya
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label="Masalah"
                            error={form.errors.problem_description}
                        >
                            <textarea
                                className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={form.data.problem_description}
                                onChange={(event) =>
                                    form.setData(
                                        'problem_description',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Vendor" error={form.errors.vendor_name}>
                            <Input
                                value={form.data.vendor_name}
                                onChange={(event) =>
                                    form.setData(
                                        'vendor_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Estimasi biaya"
                            error={form.errors.estimated_cost}
                        >
                            <RupiahInput
                                value={form.data.estimated_cost}
                                min={0}
                                onValueChange={(value) =>
                                    form.setData('estimated_cost', value)
                                }
                            />
                        </Field>
                        <DialogFooter>
                            <Button disabled={form.processing}>
                                Simpan Maintenance
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Summary({ title, value }: { title: string; value: string | number }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold">
                {value}
            </CardContent>
        </Card>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
