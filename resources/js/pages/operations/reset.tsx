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
    return (
        <MetricCard
            label={label}
            value={value.toLocaleString('id-ID')}
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
            title: 'Reset data operasional sekarang?',
            description: `Booking, rental, transaksi keuangan terkait, return, maintenance, stock opname, dan transfer pada ${scopeLabel} akan dihapus permanen dari environment ${environment}. Master pelanggan, katalog, aset, harga, cabang, pengguna, dan role tetap dipertahankan.`,
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
        summary.payments + summary.refunds + summary.financial_adjustments;

    return (
        <>
            <Head title="Reset Data Operasional" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p className="text-sm font-medium text-destructive">
                            Utilitas UAT / Development
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Reset Data Operasional
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kembalikan data transaksi ke baseline kosong tanpa
                            menghapus master pelanggan, katalog, aset, harga,
                            cabang, pengguna, atau role.
                        </p>
                    </div>

                    <div className="w-full max-w-sm space-y-2">
                        <Label htmlFor="reset-scope">Lingkup reset</Label>
                        <Select
                            value={selectedScope}
                            onValueChange={switchScope}
                        >
                            <SelectTrigger id="reset-scope">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua cabang perusahaan
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
                        Aksi destruktif khusus non-production
                    </AlertTitle>
                    <AlertDescription>
                        Halaman ini hanya tersedia untuk Super Admin pada
                        environment local, testing, atau staging. Reset tidak
                        dapat dibatalkan. Gunakan checkpoint Git/database backup
                        sebelum menjalankannya pada data UAT yang penting.
                    </AlertDescription>
                </Alert>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Booking"
                        value={summary.bookings}
                        detail={`${summary.reservations.toLocaleString('id-ID')} reservasi aset ikut dilepas`}
                        icon={CalendarDays}
                    />
                    <SummaryCard
                        label="Rental"
                        value={summary.rentals}
                        detail={`${summary.returns.toLocaleString('id-ID')} pengembalian ikut dibersihkan`}
                        icon={ShoppingBag}
                    />
                    <SummaryCard
                        label="Keuangan transaksi"
                        value={totalFinance}
                        detail={`${summary.payments.toLocaleString('id-ID')} payment · ${summary.refunds.toLocaleString('id-ID')} refund`}
                        icon={CircleDollarSign}
                    />
                    <SummaryCard
                        label="Maintenance"
                        value={summary.maintenance}
                        detail={`${summary.inspections.toLocaleString('id-ID')} inspection pada lingkup reset`}
                        icon={Wrench}
                    />
                    <SummaryCard
                        label="Stock opname"
                        value={summary.inventory_audits}
                        detail={
                            summary.notifications.toLocaleString('id-ID') +
                            ' reminder operasional terkait ikut dibersihkan'
                        }
                        icon={ClipboardCheck}
                    />
                    <SummaryCard
                        label="Transfer aset"
                        value={summary.transfers}
                        detail={`${summary.transfer_expenses.toLocaleString('id-ID')} biaya transfer terkait`}
                        icon={ArrowLeftRight}
                    />
                    <SummaryCard
                        label="Aset serialized"
                        value={summary.serialized_assets}
                        detail="Aset aktif akan dikembalikan ke status available"
                        icon={PackageCheck}
                    />
                    <SummaryCard
                        label="Stok bulk"
                        value={summary.bulk_inventory_rows}
                        detail="Counter reserved, rented, maintenance, dan transfer → 0"
                        icon={Database}
                    />
                    <SummaryCard
                        label="Lingkup"
                        value={selectedScope === 'all' ? branches.length : 1}
                        detail={scopeLabel}
                        icon={ShieldCheck}
                    />
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Data yang dibersihkan</CardTitle>
                            <CardDescription>
                                Semua data berikut diperlakukan sebagai data
                                transaksi/operasional UAT.
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
                            <CardTitle>Master data tetap aman</CardTitle>
                            <CardDescription>
                                Reset tidak mengubah fondasi bisnis dan
                                identitas aset.
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
                            <CardTitle>Pemulihan inventaris</CardTitle>
                            <CardDescription>
                                Setelah transaksi dihapus, seluruh aset aktif
                                pada lingkup reset dibuat available dan counter
                                stok operasional dikembalikan ke nol.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="rounded-xl border bg-muted/30 p-4 text-sm leading-6 text-muted-foreground">
                                <p>
                                    <strong className="text-foreground">
                                        Lokasi aset tidak dipindahkan.
                                    </strong>{' '}
                                    current branch dan owning branch tetap
                                    seperti kondisi terakhir. Quantity on hand
                                    stok bulk juga tetap dipertahankan.
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
                                        Normalisasi kondisi aset menjadi good
                                    </span>
                                    <span className="mt-1 block text-sm leading-6 text-muted-foreground">
                                        Aktifkan hanya untuk membuat baseline
                                        UAT benar-benar bersih. Jika tidak
                                        dicentang, status menjadi available
                                        tetapi catatan kondisi fisik terakhir
                                        tetap dipertahankan.
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
                                Konfirmasi reset
                            </CardTitle>
                            <CardDescription>
                                Untuk mencegah reset tidak disengaja, ketik
                                frasa konfirmasi dan masukkan password akun
                                Super Admin.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="grid gap-5 lg:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="confirmation_phrase">
                                        Ketik{' '}
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
                                        Password Super Admin
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
                                        placeholder="Masukkan password saat ini"
                                    />
                                    <InputError
                                        message={form.errors.password}
                                    />
                                </div>
                            </div>

                            <InputError message={form.errors.scope} />

                            <div className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                                <p className="max-w-2xl text-xs leading-5 text-muted-foreground">
                                    Target: <strong>{scopeLabel}</strong> ·
                                    Environment: <strong>{environment}</strong>.
                                    Transfer yang melibatkan cabang target ikut
                                    dibersihkan karena satu transfer tidak boleh
                                    tersisa hanya pada salah satu sisi.
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
