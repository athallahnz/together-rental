import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftRight, Plus, Settings2 } from 'lucide-react';
import { useState } from 'react';
import {
    Stage4Text,
    stage4Translate,
    stage4TranslateDynamic,
    stage4FormatDateTime,
    stage4ItemCount,
} from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { FilterBar } from '@/components/ui/filter-bar';
import { MetricCard } from '@/components/ui/metric-card';
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

export default function TransferIndex({
    transfers,
    summary,
    branches,
    filters,
    permissions,
}: Props) {
    const { locale: stage4Locale } = useAppLocale();

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
            <Head
                title={stage4Translate('stage4.ui.519c5796eec3', stage4Locale)}
            />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            <Stage4Text k="stage4.ui.519c5796eec3" />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            <Stage4Text k="stage4.ui.49319f6fcb47" />
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {permissions.settings && (
                            <Button variant="outline" asChild>
                                <Link href="/transfers/settings">
                                    <Settings2 className="size-4" />
                                    <Stage4Text k="stage4.ui.3fcdc1c4886d" />
                                </Link>
                            </Button>
                        )}
                        {permissions.create && (
                            <Button asChild>
                                <Link href="/transfers/create">
                                    <Plus className="size-4" />
                                    <Stage4Text k="stage4.ui.13363d3d0063" />
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <Summary
                        label={stage4Translate(
                            'stage4.ui.23d33e22acfc',
                            stage4Locale,
                        )}
                        value={summary.draft}
                    />
                    <Summary
                        label={stage4Translate(
                            'stage4.ui.6618acf15388',
                            stage4Locale,
                        )}
                        value={summary.pending_approval}
                    />
                    <Summary
                        label={stage4Translate(
                            'stage4.ui.41b81eb8db1b',
                            stage4Locale,
                        )}
                        value={summary.approved}
                    />
                    <Summary
                        label={stage4Translate(
                            'stage4.ui.046e5cee819c',
                            stage4Locale,
                        )}
                        value={summary.in_transit}
                    />
                    <Summary
                        label={stage4Translate(
                            'stage4.ui.001d34d78ee8',
                            stage4Locale,
                        )}
                        value={summary.discrepancy}
                    />
                </div>

                <FilterBar
                    title={stage4Translate(
                        'stage4.ui.d3ce1a14ad57',
                        stage4Locale,
                    )}
                    description={stage4Translate(
                        'stage4.ui.496463625e8f',
                        stage4Locale,
                    )}
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters({});
                            }
                        }}
                        placeholder={stage4Translate(
                            'stage4.ui.b9efc6d6975e',
                            stage4Locale,
                        )}
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
                            <SelectValue
                                placeholder={stage4Translate(
                                    'stage4.ui.baa2adda4148',
                                    stage4Locale,
                                )}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                <Stage4Text k="stage4.ui.baa2adda4148" />
                            </SelectItem>
                            {Object.entries(labels).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {stage4TranslateDynamic(
                                        label,
                                        stage4Locale,
                                    )}
                                </SelectItem>
                            ))}
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
                            <SelectValue
                                placeholder={stage4Translate(
                                    'stage4.ui.27d30aba48a4',
                                    stage4Locale,
                                )}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                <Stage4Text k="stage4.ui.27d30aba48a4" />
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
                        <Stage4Text k="stage4.ui.3f2275d79afb" />
                    </Button>
                </FilterBar>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3">
                                            <Stage4Text k="stage4.ui.cbb4cc49824b" />
                                        </th>
                                        <th className="px-4 py-3">
                                            <Stage4Text k="stage4.ui.607e80f9c0c7" />
                                        </th>
                                        <th className="px-4 py-3">
                                            <Stage4Text k="stage4.ui.92d937165b09" />
                                        </th>
                                        <th className="px-4 py-3">
                                            <Stage4Text k="stage4.ui.ecdda59aea5e" />
                                        </th>
                                        <th className="px-4 py-3">
                                            <Stage4Text k="stage4.ui.bae7d5be7082" />
                                        </th>
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
                                                    <Stage4Text k="stage4.ui.ede67d8c2d61" />{' '}
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
                                                    ? stage4FormatDateTime(
                                                          new Date(
                                                              transfer.planned_dispatch_at,
                                                          ),
                                                          stage4Locale,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {stage4ItemCount(
                                                    transfer.items_count ?? 0,
                                                    stage4Locale,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant="secondary">
                                                    {stage4TranslateDynamic(
                                                        labels[transfer.status],
                                                        stage4Locale,
                                                    )}
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
                                                <Stage4Text k="stage4.ui.b2df2d0f9976" />
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
    return <MetricCard label={label} value={value} icon={ArrowLeftRight} />;
}
