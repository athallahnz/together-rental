import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppLocale } from '@/lib/i18n';
import type { PaymentMethodOption } from './cash-session-select';

export function PaymentEvidenceFields({
    methods,
    methodId,
    amount,
    reference,
    onReferenceChange,
    onProofChange,
    referenceError,
    proofError,
}: {
    methods: PaymentMethodOption[];
    methodId: number | string | null;
    amount: number;
    reference: string;
    onReferenceChange: (value: string) => void;
    onProofChange: (file: File | null) => void;
    referenceError?: string;
    proofError?: string;
}) {
    const { locale } = useAppLocale();
    const method = methods.find((item) => item.id === Number(methodId));

    if (!method || amount <= 0) {
        return null;
    }

    const needsProof = ['bank_transfer', 'qris'].includes(method.type);

    return (
        <div className="space-y-3">
            {needsProof && (
                <div className="space-y-2">
                    <Label htmlFor="payment_proof">
                        {locale === 'en'
                            ? 'Payment proof (required)'
                            : 'Bukti pembayaran (wajib)'}
                    </Label>
                    <Input
                        key={method.id}
                        id="payment_proof"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                        onChange={(event) =>
                            onProofChange(event.target.files?.[0] ?? null)
                        }
                    />
                    <p className="text-xs text-muted-foreground">
                        {locale === 'en'
                            ? 'JPG, PNG, WebP, or PDF; maximum 5 MB. Stored privately.'
                            : 'JPG, PNG, WebP, atau PDF; maksimal 5 MB. Disimpan secara privat.'}
                    </p>
                    <InputError message={proofError} />
                </div>
            )}
            {(needsProof || method.requires_reference) && (
                <div className="space-y-2">
                    <Label htmlFor="payment_reference">
                        {needsProof
                            ? locale === 'en'
                                ? 'Reference code (optional)'
                                : 'Kode referensi (opsional)'
                            : locale === 'en'
                              ? 'Reference code (required)'
                              : 'Kode referensi (wajib)'}
                    </Label>
                    <Input
                        id="payment_reference"
                        value={reference}
                        onChange={(event) =>
                            onReferenceChange(event.target.value)
                        }
                    />
                    <InputError message={referenceError} />
                </div>
            )}
        </div>
    );
}
