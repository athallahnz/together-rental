import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    Banknote,
    Building2,
    CheckCircle2,
    CircleOff,
    Clock3,
    CreditCard,
    Landmark,
    Pencil,
    Plus,
    ReceiptText,
    ShieldCheck,
    SlidersHorizontal,
    WalletCards,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RupiahInput } from '@/components/ui/rupiah-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type PaymentMethodType = 'cash' | 'bank_transfer' | 'qris' | 'card' | 'other';
type CategoryType = 'income' | 'expense' | 'liability';
type MasterTab = 'methods' | 'categories' | 'registers';

type PaymentMethod = {
    id: number;
    code: string;
    name: string;
    type: PaymentMethodType;
    requires_reference: boolean;
    is_active: boolean;
    sort_order: number;
    payments_count: number;
    refunds_count: number;
};

type FinancialCategory = {
    id: number;
    code: string;
    name: string;
    type: CategoryType;
    is_active: boolean;
    payments_count: number;
    cash_transactions_count: number;
    transfer_expenses_count: number;
};

type Branch = {
    id: number;
    code: string;
    name: string;
    is_active?: boolean;
};

type CashSession = {
    id: number;
    status: 'open' | 'closed';
    opened_at: string;
    closed_at: string | null;
    opening_balance: string | number;
    expected_closing_balance: string | number;
    actual_closing_balance: string | number | null;
    difference_amount: string | number;
    expected_balance?: string | number;
    incoming_total?: string | number;
    outgoing_total?: string | number;
    opener?: { id: number; name: string } | null;
    closer?: { id: number; name: string } | null;
};

type CashRegister = {
    id: number;
    branch_id: number;
    code: string;
    name: string;
    is_active: boolean;
    sessions_count: number;
    branch: Branch;
    open_session: CashSession | null;
    latest_session: CashSession | null;
};

type Props = {
    paymentMethods: PaymentMethod[];
    financialCategories: FinancialCategory[];
    cashRegisters: CashRegister[];
    branches: Branch[];
    summary: {
        payment_methods: number;
        active_payment_methods: number;
        financial_categories: number;
        active_financial_categories: number;
        cash_registers: number;
        active_cash_registers: number;
        open_cash_sessions: number;
    };
    permissions: {
        managePaymentMethods: boolean;
        manageCategories: boolean;
        manageCashRegisters: boolean;
        manageCashSessions: boolean;
    };
};

type PaymentMethodForm = {
    code: string;
    name: string;
    type: PaymentMethodType;
    requires_reference: boolean;
    sort_order: number;
};

type CategoryForm = {
    code: string;
    name: string;
    type: CategoryType;
};

type RegisterForm = {
    branch_id: number;
    code: string;
    name: string;
};

const emptyPaymentMethod: PaymentMethodForm = {
    code: '',
    name: '',
    type: 'bank_transfer',
    requires_reference: true,
    sort_order: 0,
};

const emptyCategory: CategoryForm = {
    code: '',
    name: '',
    type: 'income',
};

const coreCategoryCodes = new Set([
    'RENTAL',
    'DEPOSIT',
    'LATE-FEE',
    'DAMAGE',
    'REFUND',
    'OPERATING',
    'TRANSFER-SHIPPING',
]);

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const methodTypeLabels: Record<PaymentMethodType, string> = {
    cash: 'Tunai',
    bank_transfer: 'Transfer bank',
    qris: 'QRIS',
    card: 'Kartu debit/kredit',
    other: 'Lainnya',
};

const categoryTypeLabels: Record<CategoryType, string> = {
    income: 'Pendapatan',
    expense: 'Pengeluaran',
    liability: 'Liabilitas',
};

function formatMoney(value: string | number | null | undefined): string {
    return money.format(Number(value ?? 0));
}

function formatDateTime(value: string | null | undefined): string {
    return value ? dateTime.format(new Date(value)) : '—';
}

export default function FinanceMasterIndex({
    paymentMethods,
    financialCategories,
    cashRegisters,
    branches,
    summary,
    permissions,
}: Props) {
    const { errors: pageErrors } = usePage().props;
    const confirm = useConfirmDialog();
    const [activeTab, setActiveTab] = useState<MasterTab>('methods');
    const [paymentDialogOpen, setPaymentDialogOpen] = useState(false);
    const [categoryDialogOpen, setCategoryDialogOpen] = useState(false);
    const [registerDialogOpen, setRegisterDialogOpen] = useState(false);
    const [sessionDialogOpen, setSessionDialogOpen] = useState(false);
    const [editingPaymentMethod, setEditingPaymentMethod] =
        useState<PaymentMethod | null>(null);
    const [editingCategory, setEditingCategory] =
        useState<FinancialCategory | null>(null);
    const [editingRegister, setEditingRegister] = useState<CashRegister | null>(
        null,
    );
    const [sessionRegister, setSessionRegister] = useState<CashRegister | null>(
        null,
    );
    const [sessionAction, setSessionAction] = useState<'open' | 'close'>(
        'open',
    );
    const paymentForm = useForm<PaymentMethodForm>(emptyPaymentMethod);
    const categoryForm = useForm<CategoryForm>(emptyCategory);
    const registerForm = useForm<RegisterForm>({
        branch_id: branches[0]?.id ?? 0,
        code: '',
        name: '',
    });
    const sessionForm = useForm({
        opening_balance: 0,
        opening_notes: '',
        actual_closing_balance: 0,
        closing_notes: '',
    });

    const openCreatePayment = () => {
        setEditingPaymentMethod(null);
        paymentForm.setData(emptyPaymentMethod);
        paymentForm.clearErrors();
        setPaymentDialogOpen(true);
    };

    const openEditPayment = (method: PaymentMethod) => {
        setEditingPaymentMethod(method);
        paymentForm.setData({
            code: method.code,
            name: method.name,
            type: method.type,
            requires_reference: method.requires_reference,
            sort_order: method.sort_order,
        });
        paymentForm.clearErrors();
        setPaymentDialogOpen(true);
    };

    const submitPayment = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setPaymentDialogOpen(false),
        };

        if (editingPaymentMethod) {
            paymentForm.put(
                `/finance/payment-methods/${editingPaymentMethod.id}`,
                options,
            );

            return;
        }

        paymentForm.post('/finance/payment-methods', options);
    };

    const openCreateCategory = () => {
        setEditingCategory(null);
        categoryForm.setData(emptyCategory);
        categoryForm.clearErrors();
        setCategoryDialogOpen(true);
    };

    const openEditCategory = (category: FinancialCategory) => {
        setEditingCategory(category);
        categoryForm.setData({
            code: category.code,
            name: category.name,
            type: category.type,
        });
        categoryForm.clearErrors();
        setCategoryDialogOpen(true);
    };

    const submitCategory = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setCategoryDialogOpen(false),
        };

        if (editingCategory) {
            categoryForm.put(
                `/finance/financial-categories/${editingCategory.id}`,
                options,
            );

            return;
        }

        categoryForm.post('/finance/financial-categories', options);
    };

    const openCreateRegister = () => {
        setEditingRegister(null);
        registerForm.setData({
            branch_id: branches[0]?.id ?? 0,
            code: '',
            name: '',
        });
        registerForm.clearErrors();
        setRegisterDialogOpen(true);
    };

    const openEditRegister = (register: CashRegister) => {
        setEditingRegister(register);
        registerForm.setData({
            branch_id: register.branch_id,
            code: register.code,
            name: register.name,
        });
        registerForm.clearErrors();
        setRegisterDialogOpen(true);
    };

    const submitRegister = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setRegisterDialogOpen(false),
        };

        if (editingRegister) {
            registerForm.put(
                `/finance/cash-registers/${editingRegister.id}`,
                options,
            );

            return;
        }

        registerForm.post('/finance/cash-registers', options);
    };

    const togglePaymentStatus = async (method: PaymentMethod) => {
        const activate = !method.is_active;
        const approved = await confirm({
            title: activate
                ? 'Aktifkan metode pembayaran?'
                : 'Nonaktifkan metode pembayaran?',
            description: activate
                ? `${method.code} akan tersedia kembali untuk transaksi baru.`
                : `${method.code} tidak lagi dapat dipilih untuk transaksi baru. Histori lama tetap utuh.`,
            confirmLabel: activate ? 'Aktifkan' : 'Nonaktifkan',
            variant: activate ? 'default' : 'destructive',
        });

        if (!approved) {
            return;
        }

        router.patch(
            `/finance/payment-methods/${method.id}/status`,
            {},
            { preserveScroll: true },
        );
    };

    const toggleCategoryStatus = async (category: FinancialCategory) => {
        const activate = !category.is_active;
        const approved = await confirm({
            title: activate ? 'Aktifkan kategori?' : 'Nonaktifkan kategori?',
            description: activate
                ? `${category.code} akan tersedia kembali untuk transaksi baru.`
                : `${category.code} tidak lagi dapat dipilih. Histori kategorisasi lama tidak berubah.`,
            confirmLabel: activate ? 'Aktifkan' : 'Nonaktifkan',
            variant: activate ? 'default' : 'destructive',
        });

        if (!approved) {
            return;
        }

        router.patch(
            `/finance/financial-categories/${category.id}/status`,
            {},
            { preserveScroll: true },
        );
    };

    const toggleRegisterStatus = async (register: CashRegister) => {
        const activate = !register.is_active;
        const approved = await confirm({
            title: activate ? 'Aktifkan kasir?' : 'Nonaktifkan kasir?',
            description: activate
                ? `${register.code} akan dapat membuka sesi kas kembali.`
                : `${register.code} tidak dapat menerima sesi baru. Histori sesi tetap tersimpan.`,
            confirmLabel: activate ? 'Aktifkan' : 'Nonaktifkan',
            variant: activate ? 'default' : 'destructive',
        });

        if (!approved) {
            return;
        }

        router.patch(
            `/finance/cash-registers/${register.id}/status`,
            {},
            { preserveScroll: true },
        );
    };

    const openSessionDialog = (register: CashRegister) => {
        setSessionRegister(register);
        setSessionAction('open');
        sessionForm.setData({
            opening_balance: 0,
            opening_notes: '',
            actual_closing_balance: 0,
            closing_notes: '',
        });
        sessionForm.clearErrors();
        setSessionDialogOpen(true);
    };

    const closeSessionDialog = (register: CashRegister) => {
        setSessionRegister(register);
        setSessionAction('close');
        sessionForm.setData({
            opening_balance: 0,
            opening_notes: '',
            actual_closing_balance: Number(
                register.open_session?.expected_balance ?? 0,
            ),
            closing_notes: '',
        });
        sessionForm.clearErrors();
        setSessionDialogOpen(true);
    };

    const submitSession = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!sessionRegister) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => setSessionDialogOpen(false),
        };

        if (sessionAction === 'open') {
            sessionForm.post(
                `/finance/cash-registers/${sessionRegister.id}/sessions`,
                options,
            );

            return;
        }

        if (!sessionRegister.open_session) {
            return;
        }

        sessionForm.post(
            `/finance/cash-sessions/${sessionRegister.open_session.id}/close`,
            options,
        );
    };

    const tabActions: Record<MasterTab, (() => void) | null> = {
        methods: permissions.managePaymentMethods ? openCreatePayment : null,
        categories: permissions.manageCategories ? openCreateCategory : null,
        registers: permissions.manageCashRegisters ? openCreateRegister : null,
    };

    return (
        <>
            <Head title="Master Finance & Kasir" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Finance Control Center
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Master Finance & Kasir
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kelola metode pembayaran, kategori pencatatan,
                            perangkat kasir cabang, dan sesi kas tanpa mengubah
                            histori transaksi yang sudah terbentuk.
                        </p>
                    </div>
                    {tabActions[activeTab] && (
                        <Button onClick={tabActions[activeTab] ?? undefined}>
                            <Plus />
                            Tambah data
                        </Button>
                    )}
                </header>

                {[
                    pageErrors.payment_method,
                    pageErrors.financial_category,
                    pageErrors.cash_register,
                    pageErrors.cash_session,
                ].some((error) => typeof error === 'string') && (
                    <Alert variant="destructive">
                        <CircleOff />
                        <AlertTitle>Perubahan master ditolak</AlertTitle>
                        <AlertDescription>
                            {
                                [
                                    pageErrors.payment_method,
                                    pageErrors.financial_category,
                                    pageErrors.cash_register,
                                    pageErrors.cash_session,
                                ].find(
                                    (error) => typeof error === 'string',
                                ) as string
                            }
                        </AlertDescription>
                    </Alert>
                )}

                <Alert>
                    <ShieldCheck />
                    <AlertTitle>Histori finansial tetap dilindungi</AlertTitle>
                    <AlertDescription>
                        Master yang tidak dipakai cukup dinonaktifkan. Kode dan
                        tipe yang sudah terhubung transaksi dikunci, kategori
                        inti sistem tetap aktif, dan kasir dengan sesi terbuka
                        tidak dapat dinonaktifkan.
                    </AlertDescription>
                </Alert>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        {
                            label: 'Metode aktif',
                            value: `${summary.active_payment_methods}/${summary.payment_methods}`,
                            icon: CreditCard,
                        },
                        {
                            label: 'Kategori aktif',
                            value: `${summary.active_financial_categories}/${summary.financial_categories}`,
                            icon: ReceiptText,
                        },
                        {
                            label: 'Kasir aktif',
                            value: `${summary.active_cash_registers}/${summary.cash_registers}`,
                            icon: WalletCards,
                        },
                        {
                            label: 'Sesi terbuka',
                            value: summary.open_cash_sessions,
                            icon: Clock3,
                        },
                    ].map(({ label, value, icon: Icon }) => (
                        <Card key={label}>
                            <CardContent className="flex items-center justify-between p-5">
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {label}
                                    </p>
                                    <p className="mt-2 text-3xl font-semibold">
                                        {value}
                                    </p>
                                </div>
                                <div className="flex size-11 items-center justify-center rounded-xl bg-muted">
                                    <Icon className="size-5" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <nav className="flex flex-wrap gap-2">
                    {[
                        {
                            key: 'methods' as const,
                            label: 'Metode pembayaran',
                            icon: CreditCard,
                        },
                        {
                            key: 'categories' as const,
                            label: 'Kategori keuangan',
                            icon: SlidersHorizontal,
                        },
                        {
                            key: 'registers' as const,
                            label: 'Cash register & sesi',
                            icon: WalletCards,
                        },
                    ].map(({ key, label, icon: Icon }) => (
                        <Button
                            key={key}
                            type="button"
                            size="sm"
                            variant={activeTab === key ? 'default' : 'outline'}
                            onClick={() => setActiveTab(key)}
                        >
                            <Icon />
                            {label}
                        </Button>
                    ))}
                </nav>

                {activeTab === 'methods' && (
                    <MasterSection
                        title="Metode pembayaran"
                        description="Urutan menentukan susunan pilihan pada form transaksi. Metode nonaktif tidak muncul pada transaksi baru."
                        empty={paymentMethods.length === 0}
                        emptyLabel="Belum ada metode pembayaran."
                    >
                        {paymentMethods.map((method) => (
                            <Card
                                key={method.id}
                                className={cn(
                                    !method.is_active && 'opacity-70',
                                )}
                            >
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <CardTitle>
                                                    {method.name}
                                                </CardTitle>
                                                <StatusBadge
                                                    active={method.is_active}
                                                />
                                            </div>
                                            <CardDescription className="mt-2 font-mono">
                                                {method.code} · urutan{' '}
                                                {method.sort_order}
                                            </CardDescription>
                                        </div>
                                        <MethodIcon type={method.type} />
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="outline">
                                            {methodTypeLabels[method.type]}
                                        </Badge>
                                        <Badge variant="outline">
                                            {method.requires_reference
                                                ? 'Referensi wajib'
                                                : 'Referensi opsional'}
                                        </Badge>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {method.payments_count.toLocaleString(
                                            'id-ID',
                                        )}{' '}
                                        payment ·{' '}
                                        {method.refunds_count.toLocaleString(
                                            'id-ID',
                                        )}{' '}
                                        refund
                                    </p>
                                    {permissions.managePaymentMethods && (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    openEditPayment(method)
                                                }
                                            >
                                                <Pencil />
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant={
                                                    method.is_active
                                                        ? 'destructive'
                                                        : 'default'
                                                }
                                                onClick={() =>
                                                    void togglePaymentStatus(
                                                        method,
                                                    )
                                                }
                                            >
                                                {method.is_active ? (
                                                    <CircleOff />
                                                ) : (
                                                    <CheckCircle2 />
                                                )}
                                                {method.is_active
                                                    ? 'Nonaktifkan'
                                                    : 'Aktifkan'}
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </MasterSection>
                )}

                {activeTab === 'categories' && (
                    <MasterSection
                        title="Kategori keuangan"
                        description="Kategori mengklasifikasikan pendapatan, pengeluaran, dan liabilitas pada payment serta ledger kas."
                        empty={financialCategories.length === 0}
                        emptyLabel="Belum ada kategori keuangan."
                    >
                        {financialCategories.map((category) => (
                            <Card
                                key={category.id}
                                className={cn(
                                    !category.is_active && 'opacity-70',
                                )}
                            >
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <CardTitle>
                                                    {category.name}
                                                </CardTitle>
                                                <StatusBadge
                                                    active={category.is_active}
                                                />
                                                {coreCategoryCodes.has(
                                                    category.code,
                                                ) && (
                                                    <Badge variant="outline">
                                                        Inti sistem
                                                    </Badge>
                                                )}
                                            </div>
                                            <CardDescription className="mt-2 font-mono">
                                                {category.code}
                                            </CardDescription>
                                        </div>
                                        <CategoryIcon type={category.type} />
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <Badge variant="outline">
                                        {categoryTypeLabels[category.type]}
                                    </Badge>
                                    <p className="text-sm text-muted-foreground">
                                        {category.payments_count.toLocaleString(
                                            'id-ID',
                                        )}{' '}
                                        payment ·{' '}
                                        {category.cash_transactions_count.toLocaleString(
                                            'id-ID',
                                        )}{' '}
                                        ledger ·{' '}
                                        {category.transfer_expenses_count.toLocaleString(
                                            'id-ID',
                                        )}{' '}
                                        biaya transfer
                                    </p>
                                    {permissions.manageCategories && (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    openEditCategory(category)
                                                }
                                            >
                                                <Pencil />
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant={
                                                    category.is_active
                                                        ? 'destructive'
                                                        : 'default'
                                                }
                                                disabled={
                                                    category.is_active &&
                                                    coreCategoryCodes.has(
                                                        category.code,
                                                    )
                                                }
                                                onClick={() =>
                                                    void toggleCategoryStatus(
                                                        category,
                                                    )
                                                }
                                            >
                                                {category.is_active ? (
                                                    <CircleOff />
                                                ) : (
                                                    <CheckCircle2 />
                                                )}
                                                {category.is_active
                                                    ? 'Nonaktifkan'
                                                    : 'Aktifkan'}
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </MasterSection>
                )}

                {activeTab === 'registers' && (
                    <MasterSection
                        title="Cash register & sesi kas"
                        description="Setiap kasir hanya boleh memiliki satu sesi terbuka. Saldo ekspektasi dihitung dari ledger append-only."
                        empty={cashRegisters.length === 0}
                        emptyLabel="Belum ada cash register pada lingkup cabang ini."
                    >
                        {cashRegisters.map((register) => (
                            <Card
                                key={register.id}
                                className={cn(
                                    !register.is_active && 'opacity-70',
                                    register.open_session &&
                                        'border-emerald-500/40',
                                )}
                            >
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <CardTitle>
                                                    {register.name}
                                                </CardTitle>
                                                <StatusBadge
                                                    active={register.is_active}
                                                />
                                                {register.open_session && (
                                                    <Badge className="bg-emerald-600 hover:bg-emerald-600">
                                                        Sesi terbuka
                                                    </Badge>
                                                )}
                                            </div>
                                            <CardDescription className="mt-2">
                                                <span className="font-mono">
                                                    {register.code}
                                                </span>{' '}
                                                · {register.branch.code} ·{' '}
                                                {register.branch.name}
                                            </CardDescription>
                                        </div>
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                                            <WalletCards className="size-5" />
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {register.open_session ? (
                                        <div className="rounded-lg border bg-emerald-500/5 p-4">
                                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                Saldo ekspektasi sekarang
                                            </p>
                                            <p className="mt-1 text-2xl font-semibold tabular-nums">
                                                {formatMoney(
                                                    register.open_session
                                                        .expected_balance,
                                                )}
                                            </p>
                                            <div className="mt-3 grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                                                <span>
                                                    Masuk{' '}
                                                    {formatMoney(
                                                        register.open_session
                                                            .incoming_total,
                                                    )}
                                                </span>
                                                <span>
                                                    Keluar{' '}
                                                    {formatMoney(
                                                        register.open_session
                                                            .outgoing_total,
                                                    )}
                                                </span>
                                                <span>
                                                    Dibuka{' '}
                                                    {formatDateTime(
                                                        register.open_session
                                                            .opened_at,
                                                    )}
                                                </span>
                                                <span>
                                                    Oleh{' '}
                                                    {register.open_session
                                                        .opener?.name ?? '—'}
                                                </span>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                            Tidak ada sesi aktif. Sesi terakhir:{' '}
                                            {register.latest_session
                                                ? `${formatDateTime(register.latest_session.closed_at ?? register.latest_session.opened_at)}`
                                                : 'belum pernah dibuka'}
                                        </div>
                                    )}

                                    <p className="text-sm text-muted-foreground">
                                        {register.sessions_count.toLocaleString(
                                            'id-ID',
                                        )}{' '}
                                        histori sesi
                                    </p>

                                    <div className="flex flex-wrap gap-2">
                                        {permissions.manageCashSessions &&
                                            register.is_active &&
                                            (register.open_session ? (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        closeSessionDialog(
                                                            register,
                                                        )
                                                    }
                                                >
                                                    <Clock3 />
                                                    Tutup sesi
                                                </Button>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        openSessionDialog(
                                                            register,
                                                        )
                                                    }
                                                >
                                                    <Banknote />
                                                    Buka sesi
                                                </Button>
                                            ))}
                                        {permissions.manageCashRegisters && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        openEditRegister(
                                                            register,
                                                        )
                                                    }
                                                >
                                                    <Pencil />
                                                    Edit
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant={
                                                        register.is_active
                                                            ? 'destructive'
                                                            : 'default'
                                                    }
                                                    disabled={
                                                        register.open_session !==
                                                        null
                                                    }
                                                    onClick={() =>
                                                        void toggleRegisterStatus(
                                                            register,
                                                        )
                                                    }
                                                >
                                                    {register.is_active ? (
                                                        <CircleOff />
                                                    ) : (
                                                        <CheckCircle2 />
                                                    )}
                                                    {register.is_active
                                                        ? 'Nonaktifkan'
                                                        : 'Aktifkan'}
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </MasterSection>
                )}
            </div>

            <Dialog
                open={paymentDialogOpen}
                onOpenChange={setPaymentDialogOpen}
            >
                <DialogContent>
                    <form onSubmit={submitPayment}>
                        <DialogHeader>
                            <DialogTitle>
                                {editingPaymentMethod
                                    ? 'Edit metode pembayaran'
                                    : 'Tambah metode pembayaran'}
                            </DialogTitle>
                            <DialogDescription>
                                Kode dan tipe akan dikunci setelah metode
                                dipakai transaksi.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-5">
                            <div className="grid gap-2 sm:grid-cols-2">
                                <FormField
                                    label="Kode"
                                    error={paymentForm.errors.code}
                                >
                                    <Input
                                        value={paymentForm.data.code}
                                        onChange={(event) =>
                                            paymentForm.setData(
                                                'code',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="E-WALLET"
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Urutan"
                                    error={paymentForm.errors.sort_order}
                                >
                                    <Input
                                        type="number"
                                        min={0}
                                        max={9999}
                                        value={paymentForm.data.sort_order}
                                        onChange={(event) =>
                                            paymentForm.setData(
                                                'sort_order',
                                                Number(event.target.value),
                                            )
                                        }
                                        required
                                    />
                                </FormField>
                            </div>
                            <FormField
                                label="Nama metode"
                                error={paymentForm.errors.name}
                            >
                                <Input
                                    value={paymentForm.data.name}
                                    onChange={(event) =>
                                        paymentForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Dompet Digital"
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Tipe"
                                error={paymentForm.errors.type}
                            >
                                <Select
                                    value={paymentForm.data.type}
                                    onValueChange={(value: PaymentMethodType) =>
                                        paymentForm.setData('type', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(methodTypeLabels).map(
                                            ([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <label className="flex items-start gap-3 rounded-lg border p-3">
                                <Checkbox
                                    checked={
                                        paymentForm.data.requires_reference
                                    }
                                    onCheckedChange={(checked) =>
                                        paymentForm.setData(
                                            'requires_reference',
                                            checked === true,
                                        )
                                    }
                                />
                                <span>
                                    <span className="block text-sm font-medium">
                                        Wajib referensi transaksi
                                    </span>
                                    <span className="mt-1 block text-xs text-muted-foreground">
                                        Cocok untuk transfer, QRIS, kartu, dan
                                        kanal non-tunai lainnya.
                                    </span>
                                </span>
                            </label>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setPaymentDialogOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={paymentForm.processing}
                            >
                                Simpan metode
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={categoryDialogOpen}
                onOpenChange={setCategoryDialogOpen}
            >
                <DialogContent>
                    <form onSubmit={submitCategory}>
                        <DialogHeader>
                            <DialogTitle>
                                {editingCategory
                                    ? 'Edit kategori keuangan'
                                    : 'Tambah kategori keuangan'}
                            </DialogTitle>
                            <DialogDescription>
                                Kode dan tipe dikunci setelah kategori tercatat
                                pada transaksi.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-5">
                            <FormField
                                label="Kode"
                                error={categoryForm.errors.code}
                            >
                                <Input
                                    value={categoryForm.data.code}
                                    onChange={(event) =>
                                        categoryForm.setData(
                                            'code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="MARKETING"
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Nama kategori"
                                error={categoryForm.errors.name}
                            >
                                <Input
                                    value={categoryForm.data.name}
                                    onChange={(event) =>
                                        categoryForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Biaya Marketing"
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Tipe"
                                error={categoryForm.errors.type}
                            >
                                <Select
                                    value={categoryForm.data.type}
                                    onValueChange={(value: CategoryType) =>
                                        categoryForm.setData('type', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(categoryTypeLabels).map(
                                            ([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCategoryDialogOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={categoryForm.processing}
                            >
                                Simpan kategori
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={registerDialogOpen}
                onOpenChange={setRegisterDialogOpen}
            >
                <DialogContent>
                    <form onSubmit={submitRegister}>
                        <DialogHeader>
                            <DialogTitle>
                                {editingRegister
                                    ? 'Edit cash register'
                                    : 'Tambah cash register'}
                            </DialogTitle>
                            <DialogDescription>
                                Register mengikuti satu cabang dan tidak dapat
                                dipindahkan setelah dibuat.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-5">
                            <FormField
                                label="Cabang"
                                error={registerForm.errors.branch_id}
                            >
                                <Select
                                    value={String(registerForm.data.branch_id)}
                                    disabled={editingRegister !== null}
                                    onValueChange={(value) =>
                                        registerForm.setData(
                                            'branch_id',
                                            Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih cabang" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {branches.map((branch) => (
                                            <SelectItem
                                                key={branch.id}
                                                value={String(branch.id)}
                                            >
                                                {branch.code} · {branch.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label="Kode register"
                                error={registerForm.errors.code}
                            >
                                <Input
                                    value={registerForm.data.code}
                                    onChange={(event) =>
                                        registerForm.setData(
                                            'code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="FRONT-DESK"
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Nama register"
                                error={registerForm.errors.name}
                            >
                                <Input
                                    value={registerForm.data.name}
                                    onChange={(event) =>
                                        registerForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Kasir Front Desk"
                                    required
                                />
                            </FormField>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRegisterDialogOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={registerForm.processing}
                            >
                                Simpan register
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={sessionDialogOpen}
                onOpenChange={setSessionDialogOpen}
            >
                <DialogContent>
                    <form onSubmit={submitSession}>
                        <DialogHeader>
                            <DialogTitle>
                                {sessionAction === 'open'
                                    ? 'Buka sesi kas'
                                    : 'Tutup sesi kas'}
                            </DialogTitle>
                            <DialogDescription>
                                {sessionRegister?.branch.code} ·{' '}
                                {sessionRegister?.name}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-5">
                            {sessionAction === 'open' ? (
                                <>
                                    <FormField
                                        label="Modal awal"
                                        error={
                                            sessionForm.errors.opening_balance
                                        }
                                    >
                                        <RupiahInput
                                            value={
                                                sessionForm.data.opening_balance
                                            }
                                            onValueChange={(value) =>
                                                sessionForm.setData(
                                                    'opening_balance',
                                                    value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        label="Catatan pembukaan"
                                        error={sessionForm.errors.opening_notes}
                                    >
                                        <Input
                                            value={
                                                sessionForm.data.opening_notes
                                            }
                                            onChange={(event) =>
                                                sessionForm.setData(
                                                    'opening_notes',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Contoh: Shift pagi"
                                        />
                                    </FormField>
                                </>
                            ) : (
                                <>
                                    <div className="grid gap-3 rounded-lg border bg-muted/40 p-4 sm:grid-cols-3">
                                        <SessionMetric
                                            label="Modal"
                                            value={formatMoney(
                                                sessionRegister?.open_session
                                                    ?.opening_balance,
                                            )}
                                        />
                                        <SessionMetric
                                            label="Masuk"
                                            value={formatMoney(
                                                sessionRegister?.open_session
                                                    ?.incoming_total,
                                            )}
                                        />
                                        <SessionMetric
                                            label="Ekspektasi"
                                            value={formatMoney(
                                                sessionRegister?.open_session
                                                    ?.expected_balance,
                                            )}
                                        />
                                    </div>
                                    <FormField
                                        label="Saldo aktual"
                                        error={
                                            sessionForm.errors
                                                .actual_closing_balance
                                        }
                                    >
                                        <RupiahInput
                                            value={
                                                sessionForm.data
                                                    .actual_closing_balance
                                            }
                                            onValueChange={(value) =>
                                                sessionForm.setData(
                                                    'actual_closing_balance',
                                                    value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        label="Catatan penutupan"
                                        error={sessionForm.errors.closing_notes}
                                    >
                                        <Input
                                            value={
                                                sessionForm.data.closing_notes
                                            }
                                            onChange={(event) =>
                                                sessionForm.setData(
                                                    'closing_notes',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Jelaskan bila ada selisih"
                                        />
                                    </FormField>
                                </>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSessionDialogOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={sessionForm.processing}
                            >
                                {sessionAction === 'open'
                                    ? 'Buka sesi'
                                    : 'Tutup sesi'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function MasterSection({
    title,
    description,
    empty,
    emptyLabel,
    children,
}: {
    title: string;
    description: string;
    empty: boolean;
    emptyLabel: string;
    children: React.ReactNode;
}) {
    return (
        <section>
            <div className="mb-4">
                <h2 className="text-lg font-semibold">{title}</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            {empty ? (
                <Card>
                    <CardContent className="py-14 text-center text-sm text-muted-foreground">
                        {emptyLabel}
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                    {children}
                </div>
            )}
        </section>
    );
}

function FormField({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return active ? (
        <Badge className="bg-emerald-600 hover:bg-emerald-600">
            <CheckCircle2 />
            Aktif
        </Badge>
    ) : (
        <Badge variant="secondary">
            <CircleOff />
            Nonaktif
        </Badge>
    );
}

function MethodIcon({ type }: { type: PaymentMethodType }) {
    const Icon =
        type === 'cash'
            ? Banknote
            : type === 'bank_transfer'
              ? Landmark
              : CreditCard;

    return (
        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted">
            <Icon className="size-5" />
        </div>
    );
}

function CategoryIcon({ type }: { type: CategoryType }) {
    const Icon =
        type === 'income'
            ? ArrowDownToLine
            : type === 'expense'
              ? ArrowUpFromLine
              : Building2;

    return (
        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted">
            <Icon className="size-5" />
        </div>
    );
}

function SessionMetric({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-semibold tabular-nums">{value}</p>
        </div>
    );
}
