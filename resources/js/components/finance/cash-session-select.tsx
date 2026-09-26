import { Stage5Text, stage5Translate } from '@/components/stage5-text';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type PaymentMethodOption = {
    id: number;
    code: string;
    name: string;
    type: string;
    requires_reference: boolean;
};

export type CashSessionOption = {
    id: number;
    branch_id: number;
    register_name: string;
    opened_at: string;
};

export function CashSessionSelect({
    paymentMethods,
    cashSessions,
    branchId,
    paymentMethodId,
    value,
    onValueChange,
    error,
}: {
    paymentMethods: PaymentMethodOption[];
    cashSessions: CashSessionOption[];
    branchId: number;
    paymentMethodId: number | string | null;
    value: number | null;
    onValueChange: (value: number | null) => void;
    error?: string;
}) {
    const selectedMethod = paymentMethods.find(
        (method) => method.id === Number(paymentMethodId),
    );

    if (selectedMethod?.type !== 'cash') {
        return null;
    }

    const availableSessions = cashSessions.filter(
        (session) => session.branch_id === branchId,
    );

    return (
        <div className="space-y-2">
            <Label><Stage5Text k="stage5.ui.bdf75fb65c7f" /></Label>
            <Select
                value={value === null ? '' : String(value)}
                onValueChange={(nextValue) =>
                    onValueChange(Number(nextValue) || null)
                }
            >
                <SelectTrigger>
                    <SelectValue placeholder={stage5Translate("stage5.ui.cfde4a0267a8")} />
                </SelectTrigger>
                <SelectContent>
                    {availableSessions.map((session) => (
                        <SelectItem key={session.id} value={String(session.id)}>
                            {session.register_name} <Stage5Text k="stage5.ui.020948e75705" />{' '}
                            {new Date(session.opened_at).toLocaleString(
                                'id-ID',
                            )}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {availableSessions.length === 0 && (
                <p className="text-sm text-amber-700">
                    <Stage5Text k="stage5.ui.bc90bcc78a07" />
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
