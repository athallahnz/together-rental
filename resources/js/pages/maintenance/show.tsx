import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { RupiahInput } from '@/components/ui/rupiah-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Props = {
    maintenance: {
        id: number;
        maintenance_number: string;
        type: string;
        status: string;
        problem_description: string;
        resolution: string | null;
        vendor_name: string | null;
        estimated_cost: string;
        actual_cost: string;
        reported_at: string;
        started_at: string | null;
        completed_at: string | null;
        branch: { code: string; name: string };
        asset: {
            asset_code: string;
            serial_number: string | null;
            status: string;
            condition: string;
            product: { sku: string; name: string };
        };
        creator: { name: string } | null;
        completer: { name: string } | null;
    };
    histories: {
        id: number;
        from_status: string;
        to_status: string;
        from_condition: string;
        to_condition: string;
        reason: string;
        changed_at: string;
        changer: { name: string } | null;
    }[];
    permissions: { manage: boolean };
};
const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});
const label: Record<string, string> = {
    reported: 'Dilaporkan',
    in_progress: 'Dikerjakan',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

export default function MaintenanceShow({
    maintenance,
    histories,
    permissions,
}: Props) {
    const complete = useForm({
        resolution: '',
        actual_cost: Number(maintenance.estimated_cost),
        asset_condition: 'good',
        asset_disposition: 'available',
    });
    const cancel = useForm({ reason: '' });
    const start = useForm({});
    const active = ['reported', 'in_progress'].includes(maintenance.status);
    const submitComplete = (event: FormEvent) => {
        event.preventDefault();
        complete.post(`/maintenance/${maintenance.id}/complete`, {
            preserveScroll: true,
        });
    };
    const submitCancel = (event: FormEvent) => {
        event.preventDefault();
        cancel.post(`/maintenance/${maintenance.id}/cancel`, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Maintenance', href: '/maintenance' },
                {
                    title: maintenance.maintenance_number,
                    href: `/maintenance/${maintenance.id}`,
                },
            ]}
        >
            <Head title={maintenance.maintenance_number} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Link
                            href="/maintenance"
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            ← Kembali
                        </Link>
                        <h1 className="text-2xl font-semibold">
                            {maintenance.maintenance_number}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {label[maintenance.status] ?? maintenance.status} ·{' '}
                            {maintenance.branch.name}
                        </p>
                    </div>
                    {permissions.manage &&
                        maintenance.status === 'reported' && (
                            <Button
                                disabled={start.processing}
                                onClick={() =>
                                    start.post(
                                        `/maintenance/${maintenance.id}/start`,
                                        {
                                            preserveScroll: true,
                                        },
                                    )
                                }
                            >
                                Mulai Pengerjaan
                            </Button>
                        )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Detail Work Order</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Info
                                title="Aset"
                                value={`${maintenance.asset.asset_code} — ${maintenance.asset.product.name}`}
                            />
                            <Info
                                title="Serial number"
                                value={maintenance.asset.serial_number || '—'}
                            />
                            <Info title="Jenis" value={maintenance.type} />
                            <Info
                                title="Vendor"
                                value={maintenance.vendor_name || '—'}
                            />
                            <Info
                                title="Estimasi"
                                value={money.format(
                                    Number(maintenance.estimated_cost),
                                )}
                            />
                            <Info
                                title="Biaya aktual"
                                value={money.format(
                                    Number(maintenance.actual_cost),
                                )}
                            />
                            <div className="sm:col-span-2">
                                <Info
                                    title="Masalah"
                                    value={maintenance.problem_description}
                                />
                            </div>
                            {maintenance.resolution && (
                                <div className="sm:col-span-2">
                                    <Info
                                        title="Resolusi"
                                        value={maintenance.resolution}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Status Aset</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Info
                                title="Status"
                                value={maintenance.asset.status}
                            />
                            <Info
                                title="Kondisi"
                                value={maintenance.asset.condition}
                            />
                            <Info
                                title="Dibuat oleh"
                                value={maintenance.creator?.name || 'Sistem'}
                            />
                            <Info
                                title="Diselesaikan oleh"
                                value={maintenance.completer?.name || '—'}
                            />
                        </CardContent>
                    </Card>
                </div>

                {permissions.manage && active && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Selesaikan Maintenance</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form
                                    className="space-y-4"
                                    onSubmit={submitComplete}
                                >
                                    <Field
                                        label="Resolusi"
                                        error={complete.errors.resolution}
                                    >
                                        <textarea
                                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            value={complete.data.resolution}
                                            onChange={(event) =>
                                                complete.setData(
                                                    'resolution',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Biaya aktual"
                                        error={complete.errors.actual_cost}
                                    >
                                        <RupiahInput
                                            min={0}
                                            value={complete.data.actual_cost}
                                            onValueChange={(value) =>
                                                complete.setData(
                                                    'actual_cost',
                                                    value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Kondisi akhir"
                                        error={complete.errors.asset_condition}
                                    >
                                        <Select
                                            value={
                                                complete.data.asset_condition
                                            }
                                            onValueChange={(value) =>
                                                complete.setData(
                                                    'asset_condition',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="good">
                                                    Baik
                                                </SelectItem>
                                                <SelectItem value="damaged">
                                                    Masih rusak
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    <Field
                                        label="Tindak lanjut aset"
                                        error={
                                            complete.errors.asset_disposition
                                        }
                                    >
                                        <Select
                                            value={
                                                complete.data.asset_disposition
                                            }
                                            onValueChange={(value) =>
                                                complete.setData(
                                                    'asset_disposition',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="available">
                                                    Kembali tersedia
                                                </SelectItem>
                                                <SelectItem value="maintenance">
                                                    Tetap maintenance
                                                </SelectItem>
                                                <SelectItem value="retired">
                                                    Pensiunkan aset
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    <Button disabled={complete.processing}>
                                        Selesaikan
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Batalkan Maintenance</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form
                                    className="space-y-4"
                                    onSubmit={submitCancel}
                                >
                                    <Field
                                        label="Alasan wajib"
                                        error={cancel.errors.reason}
                                    >
                                        <textarea
                                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            value={cancel.data.reason}
                                            onChange={(event) =>
                                                cancel.setData(
                                                    'reason',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Button
                                        variant="destructive"
                                        disabled={cancel.processing}
                                    >
                                        Batalkan Work Order
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Histori Status Aset</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {histories.map((history) => (
                            <div
                                key={history.id}
                                className="rounded-lg border p-3 text-sm"
                            >
                                <div className="font-medium">
                                    {history.from_status} → {history.to_status}
                                </div>
                                <div className="text-muted-foreground">
                                    {history.reason} ·{' '}
                                    {history.changer?.name || 'Sistem'}
                                </div>
                            </div>
                        ))}
                        {histories.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Belum ada histori.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function Info({ title, value }: { title: string; value: string }) {
    return (
        <div>
            <div className="text-xs tracking-wide text-muted-foreground uppercase">
                {title}
            </div>
            <div className="mt-1 font-medium">{value}</div>
        </div>
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
