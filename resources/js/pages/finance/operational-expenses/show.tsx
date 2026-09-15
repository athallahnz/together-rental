import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Banknote,
    Download,
    ExternalLink,
    Save,
    WalletCards,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import type {
    FinanceBranch,
    FinancePaymentMethod,
    OperationalExpense,
    OperationalExpenseStatus,
} from '@/types';

type Category = {
    id: number;
    code: string;
    name: string;
    type: string;
    is_active: boolean;
};

type CashSession = {
    id: number;
    cash_register_id: number;
    status: string;
    opened_at: string;
    opening_balance: string;
    register: {
        id: number;
        branch_id: number;
        code: string;
        name: string;
        branch: FinanceBranch;
    };
};

type Activity = {
    id: number;
    event: string;
    description: string | null;
    old_values: string | null;
    new_values: string | null;
    created_at: string;
    actor_name: string | null;
};

type Props = {
    expense: OperationalExpense;
    hasProof: boolean;
    activities: Activity[];
    categories: Category[];
    paymentMethods: FinancePaymentMethod[];
    openCashSessions: CashSession[];
    permissions: {
        manage: boolean;
        pay: boolean;
        void: boolean;
    };
};

type EditForm = {
    branch_id: number;
    financial_category_id: number;
    amount: number;
    incurred_at: string;
    vendor_name: string;
    external_reference: string;
    notes: string;
    proof: File | null;
};

type PayForm = {
    payment_method_id: number;
    cash_session_id: number | null;
    paid_at: string;
    payment_reference: string;
    notes: string;
};

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});
const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'long',
    timeStyle: 'short',
});

function localDateTime(value: string | Date): string {
    const date = value instanceof Date ? value : new Date(value);
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

export default function OperationalExpenseShow({
    expense,
    hasProof,
    activities,
    categories,
    paymentMethods,
    openCashSessions,
    permissions,
}: Props) {
    const [payOpen, setPayOpen] = useState(false);
    const [voidOpen, setVoidOpen] = useState(false);
    const activeCategories = categories.filter(
        (category) => category.is_active,
    );
    const editForm = useForm<EditForm>({
        branch_id: expense.branch_id,
        financial_category_id: expense.financial_category_id,
        amount: Number(expense.amount),
        incurred_at: localDateTime(expense.incurred_at),
        vendor_name: expense.vendor_name ?? '',
        external_reference: expense.external_reference ?? '',
        notes: expense.notes ?? '',
        proof: null,
    });
    const payForm = useForm<PayForm>({
        payment_method_id: paymentMethods[0]?.id ?? 0,
        cash_session_id: null,
        paid_at: localDateTime(new Date()),
        payment_reference: expense.external_reference ?? '',
        notes: `Pengeluaran operasional ${expense.expense_number}`,
    });
    const voidForm = useForm({ reason: '' });
    const selectedMethod = useMemo(
        () =>
            paymentMethods.find(
                (method) => method.id === payForm.data.payment_method_id,
            ),
        [payForm.data.payment_method_id, paymentMethods],
    );
    const availableCashSessions = openCashSessions.filter(
        (session) => session.register.branch_id === expense.branch_id,
    );

    const updateExpense = (event: FormEvent) => {
        event.preventDefault();

        editForm.transform((data) => ({ ...data, _method: 'put' }));
        editForm.post(`/finance/expenses/${expense.id}`, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const payExpense = (event: FormEvent) => {
        event.preventDefault();
        payForm.post(`/finance/expenses/${expense.id}/pay`, {
            preserveScroll: true,
            onSuccess: () => setPayOpen(false),
        });
    };

    const voidExpense = (event: FormEvent) => {
        event.preventDefault();
        voidForm.post(`/finance/expenses/${expense.id}/void`, {
            preserveScroll: true,
            onSuccess: () => setVoidOpen(false),
        });
    };

    return (
        <>
            <Head title={`Expense ${expense.expense_number}`} />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <Button variant="ghost" asChild className="mb-2 -ml-3">
                            <Link href="/finance/expenses">
                                <ArrowLeft /> Expense & Cash Center
                            </Link>
                        </Button>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <Banknote className="size-6 text-primary" />
                            {expense.expense_number}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {expense.branch.code} ·{' '}
                            {expense.financial_category.name} ·{' '}
                            {dateTime.format(new Date(expense.incurred_at))}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {expense.status === 'recorded' && permissions.pay && (
                            <Button
                                type="button"
                                onClick={() => setPayOpen(true)}
                            >
                                <WalletCards /> Bayar expense
                            </Button>
                        )}
                        {expense.status !== 'void' && permissions.void && (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => setVoidOpen(true)}
                            >
                                <Ban /> Void
                            </Button>
                        )}
                    </div>
                </header>

                {expense.status === 'recorded' && (
                    <Alert>
                        <WalletCards />
                        <AlertTitle>Belum memengaruhi kas</AlertTitle>
                        <AlertDescription>
                            Expense masih recorded. Saldo kas dan Finance
                            Dashboard baru berubah saat payment berhasil dibuat.
                        </AlertDescription>
                    </Alert>
                )}
                {expense.status === 'void' && (
                    <Alert variant="destructive">
                        <Ban />
                        <AlertTitle>Expense telah di-void</AlertTitle>
                        <AlertDescription>
                            Histori tidak dihapus.{' '}
                            {expense.void_reason
                                ? `Alasan: ${expense.void_reason}`
                                : ''}
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Detail pengeluaran</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Info label="Status">
                                <StatusBadge status={expense.status} />
                            </Info>
                            <Info label="Nominal">
                                <strong>
                                    {money.format(Number(expense.amount))}
                                </strong>
                            </Info>
                            <Info label="Vendor / penerima">
                                {expense.vendor_name ?? '—'}
                            </Info>
                            <Info label="Referensi">
                                {expense.external_reference ?? '—'}
                            </Info>
                            <Info label="Kategori">
                                {expense.financial_category.code} ·{' '}
                                {expense.financial_category.name}
                            </Info>
                            <Info label="Cabang">
                                {expense.branch.code} · {expense.branch.name}
                            </Info>
                            <Info label="Dibuat oleh">
                                {expense.creator?.name ?? '—'}
                            </Info>
                            <Info label="Dibayar oleh">
                                {expense.payer?.name ?? '—'}
                            </Info>
                            <Info label="Tanggal bayar">
                                {expense.paid_at
                                    ? dateTime.format(new Date(expense.paid_at))
                                    : '—'}
                            </Info>
                            <Info label="Metode bayar">
                                {expense.payment_method?.name ?? '—'}
                            </Info>
                            <Info label="Catatan" className="sm:col-span-2">
                                {expense.notes ?? '—'}
                            </Info>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Dokumen & payment</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {hasProof ? (
                                <Button
                                    variant="outline"
                                    asChild
                                    className="w-full justify-start"
                                >
                                    <a
                                        href={`/finance/expenses/${expense.id}/proof`}
                                    >
                                        <Download />{' '}
                                        {expense.proof_original_name ??
                                            'Unduh bukti'}
                                    </a>
                                </Button>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Tidak ada bukti terlampir.
                                </p>
                            )}
                            {expense.payment ? (
                                <Button
                                    variant="outline"
                                    asChild
                                    className="w-full justify-start"
                                >
                                    <Link
                                        href={`/finance/payments/${expense.payment.id}`}
                                    >
                                        <ExternalLink />{' '}
                                        {expense.payment.payment_number}
                                    </Link>
                                </Button>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada payment.
                                </p>
                            )}
                            {expense.cash_session?.register && (
                                <p className="rounded-md border p-3 text-xs text-muted-foreground">
                                    Sesi kas #{expense.cash_session.id} ·{' '}
                                    {expense.cash_session.register.code}{' '}
                                    {expense.cash_session.register.name}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </section>

                {expense.status === 'recorded' && permissions.manage && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Edit expense recorded</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                                onSubmit={updateExpense}
                            >
                                <Field
                                    label="Kategori"
                                    error={
                                        editForm.errors.financial_category_id
                                    }
                                >
                                    <Select
                                        value={String(
                                            editForm.data.financial_category_id,
                                        )}
                                        onValueChange={(value) =>
                                            editForm.setData(
                                                'financial_category_id',
                                                Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {activeCategories.map(
                                                (category) => (
                                                    <SelectItem
                                                        key={category.id}
                                                        value={String(
                                                            category.id,
                                                        )}
                                                    >
                                                        {category.code} ·{' '}
                                                        {category.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field
                                    label="Nominal"
                                    error={editForm.errors.amount}
                                >
                                    <RupiahInput
                                        value={editForm.data.amount}
                                        onValueChange={(value) =>
                                            editForm.setData('amount', value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Tanggal kejadian"
                                    error={editForm.errors.incurred_at}
                                >
                                    <Input
                                        type="datetime-local"
                                        value={editForm.data.incurred_at}
                                        onChange={(event) =>
                                            editForm.setData(
                                                'incurred_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Vendor / penerima"
                                    error={editForm.errors.vendor_name}
                                >
                                    <Input
                                        value={editForm.data.vendor_name}
                                        onChange={(event) =>
                                            editForm.setData(
                                                'vendor_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Referensi"
                                    error={editForm.errors.external_reference}
                                >
                                    <Input
                                        value={editForm.data.external_reference}
                                        onChange={(event) =>
                                            editForm.setData(
                                                'external_reference',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Ganti bukti"
                                    error={editForm.errors.proof}
                                >
                                    <Input
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        onChange={(event) =>
                                            editForm.setData(
                                                'proof',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Catatan"
                                    error={editForm.errors.notes}
                                >
                                    <Input
                                        value={editForm.data.notes}
                                        onChange={(event) =>
                                            editForm.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <div className="flex items-end">
                                    <Button
                                        type="submit"
                                        disabled={editForm.processing}
                                    >
                                        <Save /> Simpan perubahan
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Audit timeline</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {activities.map((activity) => (
                            <div
                                key={activity.id}
                                className="rounded-md border p-3"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <strong className="text-sm">
                                        {activity.event}
                                    </strong>
                                    <span className="text-xs text-muted-foreground">
                                        {dateTime.format(
                                            new Date(activity.created_at),
                                        )}
                                    </span>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {activity.actor_name ?? 'System'}
                                    {activity.description
                                        ? ` · ${activity.description}`
                                        : ''}
                                </p>
                            </div>
                        ))}
                        {activities.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Belum ada activity log.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={payOpen} onOpenChange={setPayOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Bayar {expense.expense_number}
                        </DialogTitle>
                        <DialogDescription>
                            Payment OUT sebesar{' '}
                            {money.format(Number(expense.amount))} akan dibuat.
                            Metode CASH juga menulis satu baris cash ledger OUT.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={payExpense}>
                        <Field
                            label="Metode pembayaran"
                            error={payForm.errors.payment_method_id}
                        >
                            <Select
                                value={String(
                                    payForm.data.payment_method_id || '',
                                )}
                                onValueChange={(value) => {
                                    payForm.setData(
                                        'payment_method_id',
                                        Number(value),
                                    );

                                    if (
                                        paymentMethods.find(
                                            (method) =>
                                                method.id === Number(value),
                                        )?.type !== 'cash'
                                    ) {
                                        payForm.setData(
                                            'cash_session_id',
                                            null,
                                        );
                                    }
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih metode" />
                                </SelectTrigger>
                                <SelectContent>
                                    {paymentMethods.map((method) => (
                                        <SelectItem
                                            key={method.id}
                                            value={String(method.id)}
                                        >
                                            {method.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        {selectedMethod?.type === 'cash' && (
                            <Field
                                label="Sesi kas aktif"
                                error={payForm.errors.cash_session_id}
                            >
                                <Select
                                    value={
                                        payForm.data.cash_session_id
                                            ? String(
                                                  payForm.data.cash_session_id,
                                              )
                                            : ''
                                    }
                                    onValueChange={(value) =>
                                        payForm.setData(
                                            'cash_session_id',
                                            Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih sesi kas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableCashSessions.map(
                                            (session) => (
                                                <SelectItem
                                                    key={session.id}
                                                    value={String(session.id)}
                                                >
                                                    #{session.id} ·{' '}
                                                    {session.register.code}{' '}
                                                    {session.register.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                {availableCashSessions.length === 0 && (
                                    <p className="text-xs text-destructive">
                                        Belum ada sesi kas aktif untuk cabang
                                        ini.
                                    </p>
                                )}
                            </Field>
                        )}
                        <Field
                            label="Tanggal bayar"
                            error={payForm.errors.paid_at}
                        >
                            <Input
                                type="datetime-local"
                                value={payForm.data.paid_at}
                                onChange={(event) =>
                                    payForm.setData(
                                        'paid_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Referensi pembayaran"
                            error={payForm.errors.payment_reference}
                        >
                            <Input
                                value={payForm.data.payment_reference}
                                placeholder={
                                    selectedMethod?.requires_reference
                                        ? 'Wajib untuk metode ini'
                                        : 'Opsional'
                                }
                                onChange={(event) =>
                                    payForm.setData(
                                        'payment_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Catatan payment"
                            error={payForm.errors.notes}
                        >
                            <Input
                                value={payForm.data.notes}
                                onChange={(event) =>
                                    payForm.setData('notes', event.target.value)
                                }
                            />
                        </Field>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setPayOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={payForm.processing}>
                                Bayar expense
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={voidOpen} onOpenChange={setVoidOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Void {expense.expense_number}</DialogTitle>
                        <DialogDescription>
                            Histori tidak akan dihapus. Expense paid akan
                            me-void Payment; jika tunai, ledger membuat reversal
                            append-only pada sesi kas yang masih open.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={voidExpense}>
                        <Field
                            label="Alasan void"
                            error={voidForm.errors.reason}
                        >
                            <Input
                                value={voidForm.data.reason}
                                onChange={(event) =>
                                    voidForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                placeholder="Minimal 10 karakter"
                            />
                        </Field>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setVoidOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={voidForm.processing}
                            >
                                <Ban /> Void expense
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function StatusBadge({ status }: { status: OperationalExpenseStatus }) {
    return (
        <Badge
            variant={
                status === 'void'
                    ? 'destructive'
                    : status === 'paid'
                      ? 'secondary'
                      : 'outline'
            }
        >
            {status === 'recorded'
                ? 'Recorded'
                : status === 'paid'
                  ? 'Paid'
                  : 'Void'}
        </Badge>
    );
}

function Info({
    label,
    children,
    className = '',
}: {
    label: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={className}>
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <div className="mt-1 text-sm">{children}</div>
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
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
