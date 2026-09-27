import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    CalendarDays,
    CircleDollarSign,
    ClipboardCheck,
    Database,
    PackageCheck,
    RotateCcw,
    ShieldCheck,
    ShoppingBag,
    Wrench,
} from 'lucide-react';
import type { FormEvent } from 'react';
import {
    stage5Choice,
    Stage5Text,
    stage5Translate,
    stage5IntlLocale,
} from '@/components/stage5-text';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MetricCard } from '@/components/ui/metric-card';
import { useAppLocale } from '@/lib/i18n';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type BranchOption = {
    id: number;
    code: string;
    name: string;
    city: string | null;
    is_active: boolean;
};

type ResetSummary = {
    bookings: number;
    reservations: number;
    rentals: number;
    returns: number;
    payments: number;
    refunds: number;
    financial_adjustments: number;
    maintenance: number;
    inventory_audits: number;
    notifications: number;
    transfers: number;
    transfer_expenses: number;
    operational_expenses: number;
    transaction_documents: number;
    inspections: number;
    serialized_assets: number;
    bulk_inventory_rows: number;
};

type Props = {
    branches: BranchOption[];
    selectedScope: string;
    scopeLabel: string;
    confirmationPhrase: string;
    summary: ResetSummary;
    environment: string;
};

type ResetForm = {
    scope: string;
    confirmation_phrase: string;
    password: string;
    normalize_condition: boolean;
};

function SummaryCard({
    label,
    value,
    detail,
    icon: Icon,
}: {
    label: string;
    value: number;
    detail: string;
    icon: typeof CalendarDays;
}) {
    const { locale: stage5Locale } = useAppLocale();

    return (
        <MetricCard
            label={label}
            value={value.toLocaleString(stage5IntlLocale(stage5Locale))}
            detail={detail}
            icon={Icon}
            tone="primary"
        />
    );
}

export default function OperationalDataReset({
    branches,
    selectedScope,
    scopeLabel,
    confirmationPhrase,
    summary,
    environment,
}: Props) {
    const { locale: stage5Locale } = useAppLocale();
    const confirm = useConfirmDialog();
    const form = useForm<ResetForm>({
        scope: selectedScope,
        confirmation_phrase: '',
        password: '',
        normalize_condition: false,
    });

    const switchScope = (scope: string) => {
        router.get(
            '/operations/reset',
            { scope },
            {
                preserveState: false,
                replace: true,
            },
        );
    };

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const approved = await confirm({
            title: stage5Translate('stage5.ui.c71a7063881f', stage5Locale),
            description: stage5Choice(
                `Pemesanan, rental, transaksi keuangan terkait, dokumen, pengembalian, perawatan, pemeriksaan stok, dan transfer pada ${scopeLabel} akan dihapus permanen dari lingkungan ${environment}. Data induk pelanggan, katalog, aset, harga, cabang, pengguna, dan peran tetap dipertahankan.`,
                `Bookings, rentals, associated financial transactions, documents, returns, maintenance, stocktaking, and transfers in ${scopeLabel} will be permanently deleted from ${environment}. Customer, catalog, asset, pricing, branch, user and role master data will be preserved.`,
                stage5Locale,
            ),
            confirmLabel: 'Ya, reset operasional',
            variant: 'destructive',
        });

        if (!approved) {
            return;
        }

        form.post('/operations/reset', {
            preserveScroll: true,
        });
    };

    const totalFinance =
        summary.payments +
        summary.refunds +
        summary.financial_adjustments +
        summary.operational_expenses +
        summary.transaction_documents;

    return (
        <>
            <Head
                title={stage5Translate('stage5.ui.4341da9b183f', stage5Locale)}
            />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p className="text-sm font-medium text-destructive">
                            <Stage5Text k="stage5.ui.95107c3bc6c6" />
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            <Stage5Text k="stage5.ui.4341da9b183f" />
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            <Stage5Text k="stage5.ui.d34e792396dc" />
                        </p>
                    </div>

                    <div className="w-full max-w-sm space-y-2">
                        <Label htmlFor="reset-scope">
                            <Stage5Text k="stage5.ui.a5da1c3778e0" />
                        </Label>
                        <Select
                            value={selectedScope}
                            onValueChange={switchScope}
                        >
                            <SelectTrigger id="reset-scope">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    <Stage5Text k="stage5.ui.ce3a190738ab" />
                                </SelectItem>
                                {branches.map((branch) => (
                                    <SelectItem
                                        key={branch.id}
                                        value={String(branch.id)}
                                    >
                                        {branch.code} · {branch.name}
                                        {!branch.is_active ? ' · nonaktif' : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </header>

                <Alert variant="destructive">
                    <AlertTriangle />
                    <AlertTitle>
                        <Stage5Text k="stage5.ui.3e731aed27d2" />
                    </AlertTitle>
                    <AlertDescription>
                        <Stage5Text k="stage5.ui.a7627d12ce43" />
                    </AlertDescription>
                </Alert>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.e38ea8eebe78',
                            stage5Locale,
                        )}
                        value={summary.bookings}
                        detail={`${summary.reservations.toLocaleString(stage5IntlLocale(stage5Locale))} reservasi aset ikut dilepas`}
                        icon={CalendarDays}
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.e703935c66bf',
                            stage5Locale,
                        )}
                        value={summary.rentals}
                        detail={`${summary.returns.toLocaleString(stage5IntlLocale(stage5Locale))} pengembalian ikut dibersihkan`}
                        icon={ShoppingBag}
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.15f141a61e02',
                            stage5Locale,
                        )}
                        value={totalFinance}
                        detail={`${summary.payments.toLocaleString(stage5IntlLocale(stage5Locale))} payment · ${summary.refunds.toLocaleString(stage5IntlLocale(stage5Locale))} refund · ${summary.operational_expenses.toLocaleString(stage5IntlLocale(stage5Locale))} expense · ${summary.transaction_documents.toLocaleString(stage5IntlLocale(stage5Locale))} dokumen`}
                        icon={CircleDollarSign}
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.94de303bbef8',
                            stage5Locale,
                        )}
                        value={summary.maintenance}
                        detail={`${summary.inspections.toLocaleString(stage5IntlLocale(stage5Locale))} inspection pada lingkup reset`}
                        icon={Wrench}
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.5b358c94674d',
                            stage5Locale,
                        )}
                        value={summary.inventory_audits}
                        detail={
                            summary.notifications.toLocaleString(
                                stage5IntlLocale(stage5Locale),
                            ) + ' reminder operasional terkait ikut dibersihkan'
                        }
                        icon={ClipboardCheck}
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.b7480f1ffbe7',
                            stage5Locale,
                        )}
                        value={summary.transfers}
                        detail={`${summary.transfer_expenses.toLocaleString(stage5IntlLocale(stage5Locale))} biaya transfer terkait`}
                        icon={ArrowLeftRight}
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.7dba4563705a',
                            stage5Locale,
                        )}
                        value={summary.serialized_assets}
                        detail="Aset aktif akan dikembalikan ke status available"
                        icon={PackageCheck}
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.3722564cb316',
                            stage5Locale,
                        )}
                        value={summary.bulk_inventory_rows}
                        detail="Counter reserved, rented, maintenance, dan transfer → 0"
                        icon={Database}
                    />
                    <SummaryCard
                        label={stage5Translate(
                            'stage5.ui.7f0e0b23bf61',
                            stage5Locale,
                        )}
                        value={selectedScope === 'all' ? branches.length : 1}
                        detail={scopeLabel}
                        icon={ShieldCheck}
                    />
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage5Text k="stage5.ui.ac0c8b0bcc50" />
                            </CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.ee66f28d4a35" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-3 text-sm leading-6 text-muted-foreground sm:grid-cols-2">
                                {[
                                    'Booking dan reservasi aset',
                                    'Rental dan unit checkout',
                                    'Payment, refund, dan koreksi finansial terkait',
                                    'Return, inspection, dan koreksi operasional',
                                    'Maintenance order',
                                    'Transfer aset, approval, dokumen, dan biaya',
                                ].map((item) => (
                                    <li
                                        key={item}
                                        className="flex items-start gap-2"
                                    >
                                        <RotateCcw className="mt-1 size-4 shrink-0 text-destructive" />
                                        <span>{item}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage5Text k="stage5.ui.5d456a91ba3e" />
                            </CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.f3c2b6a5bd4d" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-3 text-sm leading-6 text-muted-foreground sm:grid-cols-2">
                                {[
                                    'Pelanggan dan identitas',
                                    'Kategori, brand, produk, dan paket',
                                    'Aset fisik dan kode/serial',
                                    'Harga dan rate plan',
                                    'Cabang dan konfigurasi',
                                    'User, karyawan, role, dan permission',
                                ].map((item) => (
                                    <li
                                        key={item}
                                        className="flex items-start gap-2"
                                    >
                                        <ClipboardCheck className="mt-1 size-4 shrink-0 text-emerald-600" />
                                        <span>{item}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Stage5Text k="stage5.ui.ae1618687629" />
                            </CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.14adff6c5281" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="rounded-xl border bg-muted/30 p-4 text-sm leading-6 text-muted-foreground">
                                <p>
                                    <strong className="text-foreground">
                                        <Stage5Text k="stage5.ui.2b3da7cf2b3a" />
                                    </strong>{' '}
                                    <Stage5Text k="stage5.ui.9d9fbfcff77c" />
                                </p>
                            </div>

                            <label className="flex cursor-pointer items-start gap-3 rounded-xl border p-4">
                                <Checkbox
                                    checked={form.data.normalize_condition}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'normalize_condition',
                                            checked === true,
                                        )
                                    }
                                />
                                <span>
                                    <span className="block text-sm font-medium">
                                        <Stage5Text k="stage5.ui.62c169432423" />
                                    </span>
                                    <span className="mt-1 block text-sm leading-6 text-muted-foreground">
                                        <Stage5Text k="stage5.ui.8e14745d02e7" />
                                    </span>
                                </span>
                            </label>
                            <InputError
                                message={form.errors.normalize_condition}
                            />
                        </CardContent>
                    </Card>

                    <Card className="border-destructive/40">
                        <CardHeader>
                            <CardTitle className="text-destructive">
                                <Stage5Text k="stage5.ui.1d606a7ebb5a" />
                            </CardTitle>
                            <CardDescription>
                                <Stage5Text k="stage5.ui.0e0c73e8c562" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="grid gap-5 lg:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="confirmation_phrase">
                                        <Stage5Text k="stage5.ui.e0a37a7a2bdd" />{' '}
                                        <span className="font-mono font-semibold text-destructive">
                                            {confirmationPhrase}
                                        </span>
                                    </Label>
                                    <Input
                                        id="confirmation_phrase"
                                        value={form.data.confirmation_phrase}
                                        onChange={(event) =>
                                            form.setData(
                                                'confirmation_phrase',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                        autoComplete="off"
                                        placeholder={confirmationPhrase}
                                    />
                                    <InputError
                                        message={
                                            form.errors.confirmation_phrase
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="password">
                                        <Stage5Text k="stage5.ui.83d7665e0004" />
                                    </Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={form.data.password}
                                        onChange={(event) =>
                                            form.setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="current-password"
                                        placeholder={stage5Translate(
                                            'stage5.ui.5bbfdb51d1c0',
                                            stage5Locale,
                                        )}
                                    />
                                    <InputError
                                        message={form.errors.password}
                                    />
                                </div>
                            </div>

                            <InputError message={form.errors.scope} />

                            <div className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                                <p className="max-w-2xl text-xs leading-5 text-muted-foreground">
                                    <Stage5Text k="stage5.ui.652ac2cbbafc" />{' '}
                                    <strong>{scopeLabel}</strong>{' '}
                                    <Stage5Text k="stage5.ui.17568ffbc7ac" />{' '}
                                    <strong>{environment}</strong>
                                    <Stage5Text k="stage5.ui.75e1f0b8a405" />
                                </p>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={
                                        form.processing ||
                                        form.data.confirmation_phrase.trim() !==
                                            confirmationPhrase ||
                                        form.data.password === ''
                                    }
                                >
                                    <RotateCcw />
                                    {form.processing
                                        ? 'Mereset...'
                                        : 'Reset Data Operasional'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}
