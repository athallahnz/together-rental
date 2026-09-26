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
import { Stage5Text, stage5Translate, stage5Date, stage5Money, stage5Display } from '@/components/stage5-text';
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
import { useAppLocale } from '@/lib/i18n';
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

const money = { format: stage5Money };


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
    const { locale: stage5Locale } = useAppLocale();
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
            <Head title={`${stage5Display('Expense', stage5Locale)} ${expense.expense_number}`} />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <Button variant="ghost" asChild className="mb-2 -ml-3">
                            <Link href="/finance/expenses">
                                <ArrowLeft /> <Stage5Text k="stage5.ui.18927b067117" />
                            </Link>
                        </Button>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <Banknote className="size-6 text-primary" />
                            {expense.expense_number}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {expense.branch.code} ·{' '}
                            {expense.financial_category.name} ·{' '}
                            {stage5Date(new Date(expense.incurred_at), stage5Locale)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {expense.status === 'recorded' && permissions.pay && (
                            <Button
                                type="button"
                                onClick={() => setPayOpen(true)}
                            >
                                <WalletCards /> <Stage5Text k="stage5.ui.c2493c0bae46" />
                            </Button>
                        )}
                        {expense.status !== 'void' && permissions.void && (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => setVoidOpen(true)}
                            >
                                <Ban /> <Stage5Text k="stage5.ui.207c7c00630b" />
                            </Button>
                        )}
                    </div>
                </header>

                {expense.status === 'recorded' && (
                    <Alert>
                        <WalletCards />
                        <AlertTitle><Stage5Text k="stage5.ui.55a29d40d62c" /></AlertTitle>
                        <AlertDescription>
                            <Stage5Text k="stage5.ui.c9d1efd3a450" />
                        </AlertDescription>
                    </Alert>
                )}
                {expense.status === 'void' && (
                    <Alert variant="destructive">
                        <Ban />
                        <AlertTitle><Stage5Text k="stage5.ui.da0bf9ad8769" /></AlertTitle>
                        <AlertDescription>
                            <Stage5Text k="stage5.ui.5d34a26816f9" />{' '}
                            {expense.void_reason
                                ? `Alasan: ${expense.void_reason}`
                                : ''}
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle><Stage5Text k="stage5.ui.0660664934cc" /></CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Info label={stage5Translate("stage5.ui.bae7d5be7082", stage5Locale)}>
                                <StatusBadge status={expense.status} />
                            </Info>
                            <Info label={stage5Translate("stage5.ui.1795d163388f", stage5Locale)}>
                                <strong>
                                    {money.format(Number(expense.amount))}
                                </strong>
                            </Info>
                            <Info label={stage5Translate("stage5.ui.e3f18544463f", stage5Locale)}>
                                {expense.vendor_name ?? '—'}
                            </Info>
                            <Info label={stage5Translate("stage5.ui.3166201d7baf", stage5Locale)}>
                                {expense.external_reference ?? '—'}
                            </Info>
                            <Info label={stage5Translate("stage5.ui.b7964404a785", stage5Locale)}>
                                {expense.financial_category.code} ·{' '}
                                {expense.financial_category.name}
                            </Info>
                            <Info label={stage5Translate("stage5.ui.1387475bd674", stage5Locale)}>
                                {expense.branch.code} · {expense.branch.name}
                            </Info>
                            <Info label={stage5Translate("stage5.ui.1216355f2efb", stage5Locale)}>
                                {expense.creator?.name ?? '—'}
                            </Info>
                            <Info label={stage5Translate("stage5.ui.e74fdd23a4cc", stage5Locale)}>
                                {expense.payer?.name ?? '—'}
                            </Info>
                            <Info label={stage5Translate("stage5.ui.33da3f80c25b", stage5Locale)}>
                                {expense.paid_at
                                    ? stage5Date(new Date(expense.paid_at), stage5Locale)
                                    : '—'}
                            </Info>
                            <Info label={stage5Translate("stage5.ui.85de16445a90", stage5Locale)}>
                                {expense.payment_method?.name ?? '—'}
                            </Info>
                            <Info label={stage5Translate("stage5.ui.9f09aefd0dd4", stage5Locale)} className="sm:col-span-2">
                                {expense.notes ?? '—'}
                            </Info>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle><Stage5Text k="stage5.ui.80e39e6fbaa8" /></CardTitle>
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
                                    <Stage5Text k="stage5.ui.3c96cc261314" />
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
                                    <Stage5Text k="stage5.ui.4cfb8063343c" />
                                </p>
                            )}
                            {expense.cash_session?.register && (
                                <p className="rounded-md border p-3 text-xs text-muted-foreground">
                                    <Stage5Text k="stage5.ui.ad2a6ab5762c" />{expense.cash_session.id} ·{' '}
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
                            <CardTitle><Stage5Text k="stage5.ui.de87a51461ff" /></CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                                onSubmit={updateExpense}
                            >
                                <Field
                                    label={stage5Translate("stage5.ui.b7964404a785", stage5Locale)}
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
                                    label={stage5Translate("stage5.ui.1795d163388f", stage5Locale)}
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
                                    label={stage5Translate("stage5.ui.08206fcf8a87", stage5Locale)}
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
                                    label={stage5Translate("stage5.ui.e3f18544463f", stage5Locale)}
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
                                    label={stage5Translate("stage5.ui.3166201d7baf", stage5Locale)}
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
                                    label={stage5Translate("stage5.ui.f5af564bd34a", stage5Locale)}
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
                                    label={stage5Translate("stage5.ui.9f09aefd0dd4", stage5Locale)}
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
                                        <Save /> <Stage5Text k="stage5.ui.099b36f6ccbb" />
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle><Stage5Text k="stage5.ui.e2a0860210b2" /></CardTitle>
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
                                        {stage5Date(
                                            new Date(activity.created_at),
                                            stage5Locale,
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
                                <Stage5Text k="stage5.ui.e40a3353a009" />
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={payOpen} onOpenChange={setPayOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            <Stage5Text k="stage5.ui.6290c54f33d8" /> {expense.expense_number}
                        </DialogTitle>
                        <DialogDescription>
                            <Stage5Text k="stage5.ui.ad2e4eed4cbd" />{' '}
                            {money.format(Number(expense.amount))} <Stage5Text k="stage5.ui.3e46a43dcfbb" />
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={payExpense}>
                        <Field
                            label={stage5Translate("stage5.ui.53eb1a623ade", stage5Locale)}
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
                                    <SelectValue placeholder={stage5Translate("stage5.ui.cfabec6a6763", stage5Locale)} />
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
                                label={stage5Translate("stage5.ui.bdf75fb65c7f", stage5Locale)}
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
                                        <SelectValue placeholder={stage5Translate("stage5.ui.cfde4a0267a8", stage5Locale)} />
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
                                        <Stage5Text k="stage5.ui.cb53e0d48f57" />
                                    </p>
                                )}
                            </Field>
                        )}
                        <Field
                            label={stage5Translate("stage5.ui.33da3f80c25b", stage5Locale)}
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
                            label={stage5Translate("stage5.ui.7f2cc58cb31e", stage5Locale)}
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
                            label={stage5Translate("stage5.ui.3be039f9f735", stage5Locale)}
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
                                <Stage5Text k="stage5.ui.1433539c3b8f" />
                            </Button>
                            <Button type="submit" disabled={payForm.processing}>
                                <Stage5Text k="stage5.ui.c2493c0bae46" />
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={voidOpen} onOpenChange={setVoidOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle><Stage5Text k="stage5.ui.207c7c00630b" /> {expense.expense_number}</DialogTitle>
                        <DialogDescription>
                            <Stage5Text k="stage5.ui.169b524a288c" />
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={voidExpense}>
                        <Field
                            label={stage5Translate("stage5.ui.c2a53eee88e3", stage5Locale)}
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
                                placeholder={stage5Translate("stage5.ui.24e468e21005", stage5Locale)}
                            />
                        </Field>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setVoidOpen(false)}
                            >
                                <Stage5Text k="stage5.ui.1433539c3b8f" />
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={voidForm.processing}
                            >
                                <Ban /> <Stage5Text k="stage5.ui.92afd25a7c3b" />
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
