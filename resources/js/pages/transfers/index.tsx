import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftRight, Plus, Settings2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PaginationLinks } from '@/components/pagination-links';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    TransferBranch,
    TransferPagination,
    TransferPermissions,
    TransferStatus,
} from '@/types';

type Props = {
    transfers: TransferPagination;
    summary: {
        draft: number;
        pending_approval: number;
        approved: number;
        in_transit: number;
        discrepancy: number;
    };
    branches: TransferBranch[];
    filters: {
        search: string;
        status: string;
        branchId: number | null;
    };
    permissions: TransferPermissions;
};

const labels: Record<TransferStatus, string> = {
    draft: 'Draft',
    pending_approval: 'Menunggu Approval',
    approved: 'Disetujui / Hold',
    dispatched: 'In Transit',
    receiving: 'Penerimaan Parsial',
    discrepancy: 'Discrepancy',
    completed: 'Selesai',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const date = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function TransferIndex({
    transfers,
    summary,
    branches,
    filters,
    permissions,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (changes: Record<string, string | number>) => {
        router.get(
            '/transfers',
            {
                search,
                status: filters.status,
                branch_id: filters.branchId ?? '',
                ...changes,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Transfer Aset Antar-Cabang" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Transfer Aset Antar-Cabang
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Dual approval, pengiriman, penerimaan, bukti
                            kondisi, dan biaya dalam satu timeline.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {permissions.settings && (
                            <Button variant="outline" asChild>
                                <Link href="/transfers/settings">
                                    <Settings2 className="size-4" />
                                    Pengaturan
                                </Link>
                            </Button>
                        )}
                        {permissions.create && (
                            <Button asChild>
                                <Link href="/transfers/create">
                                    <Plus className="size-4" />
                                    Buat Transfer
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <Summary label="Draft" value={summary.draft} />
                    <Summary
                        label="Menunggu approval"
                        value={summary.pending_approval}
                    />
                    <Summary label="Approved" value={summary.approved} />
                    <Summary label="In Transit" value={summary.in_transit} />
                    <Summary label="Discrepancy" value={summary.discrepancy} />
                </div>

                <Card>
                    <CardContent className="grid gap-3 pt-6 md:grid-cols-4">
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    applyFilters({});
                                }
                            }}
                            placeholder="Nomor transfer, aset, produk..."
                        />
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                applyFilters({
                                    status: value === 'all' ? '' : value,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                                {Object.entries(labels).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <Select
                            value={
                                filters.branchId === null
                                    ? 'all'
                                    : String(filters.branchId)
                            }
                            onValueChange={(value) =>
                                applyFilters({
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
                                        value={String(branch.id)}
                                    >
                                        {branch.code} — {branch.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            variant="secondary"
                            onClick={() => applyFilters({})}
                        >
                            Cari
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3">Transfer</th>
                                        <th className="px-4 py-3">Rute</th>
                                        <th className="px-4 py-3">Jadwal</th>
                                        <th className="px-4 py-3">Item</th>
                                        <th className="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transfers.data.map((transfer) => (
                                        <tr
                                            key={transfer.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    className="font-medium hover:underline"
                                                    href={`/transfers/${transfer.id}`}
                                                >
                                                    {transfer.transfer_number}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    Revisi{' '}
                                                    {transfer.revision_number}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span>
                                                        {
                                                            transfer
                                                                .origin_branch
                                                                .code
                                                        }
                                                    </span>
                                                    <ArrowLeftRight className="size-3.5 text-muted-foreground" />
                                                    <span>
                                                        {
                                                            transfer
                                                                .destination_branch
                                                                .code
                                                        }
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {transfer.planned_dispatch_at
                                                    ? date.format(
                                                          new Date(
                                                              transfer.planned_dispatch_at,
                                                          ),
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {transfer.items_count ?? 0} item
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant="secondary">
                                                    {labels[transfer.status]}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                    {transfers.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-4 py-12 text-center text-muted-foreground"
                                            >
                                                Belum ada transfer yang sesuai
                                                filter.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="p-4">
                            <PaginationLinks
                                links={transfers.links}
                                from={transfers.from}
                                to={transfers.to}
                                total={transfers.total}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Summary({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardContent className="pt-6">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="mt-1 text-2xl font-semibold">{value}</p>
            </CardContent>
        </Card>
    );
}
