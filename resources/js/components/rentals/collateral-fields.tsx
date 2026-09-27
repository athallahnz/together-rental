import { Plus, Trash2 } from 'lucide-react';
import { Stage4Text, stage4Translate } from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type CollateralIdentityOption = {
    id: number;
    type: string;
    collateral_type: string;
    number: string;
    name_on_identity: string | null;
    expires_at: string | null;
    is_primary: boolean;
    verified_at: string | null;
    document_present: boolean;
    is_expired: boolean;
    is_default: boolean;
};

export type CollateralInput = {
    customer_identity_id: number | null;
    type: string;
    number: string;
    holder_name: string;
    notes: string;
    document: File | null;
};

const TYPES = [
    'KTP',
    'SIM',
    'Kartu Mahasiswa',
    'Kartu Pelajar',
    'Kartu Pegawai',
    'Paspor',
    'Lainnya',
];

export const emptyCollateral = (): CollateralInput => ({
    customer_identity_id: null,
    type: 'KTP',
    number: '',
    holder_name: '',
    notes: '',
    document: null,
});

export const collateralFromIdentity = (
    identity: CollateralIdentityOption,
): CollateralInput => ({
    customer_identity_id: identity.id,
    type: identity.collateral_type,
    number: identity.number,
    holder_name: identity.name_on_identity ?? '',
    notes: '',
    document: null,
});

export function CollateralFields({
    value,
    onChange,
    errors = {},
    identityOptions = [],
}: {
    value: CollateralInput[];
    onChange: (value: CollateralInput[]) => void;
    errors?: Record<string, string>;
    identityOptions?: CollateralIdentityOption[];
}) {
    const { locale: stage4Locale } = useAppLocale();

    const update = (index: number, patch: Partial<CollateralInput>) =>
        onChange(
            value.map((item, position) =>
                position === index ? { ...item, ...patch } : item,
            ),
        );

    const linkedIdentity = (collateral: CollateralInput) =>
        identityOptions.find(
            (identity) => identity.id === collateral.customer_identity_id,
        );
    const chooseSource = (index: number, source: string) => {
        if (source === 'manual') {
            const current = value[index];

            // Switching away from Customer360 must not silently reuse its
            // canonical ID/number as an unconfirmed manual receipt.
            update(
                index,
                current?.customer_identity_id
                    ? {
                          ...emptyCollateral(),
                          notes: current.notes,
                          document: current.document,
                      }
                    : { customer_identity_id: null },
            );

            return;
        }

        const identity = identityOptions.find(
            (option) => option.id === Number(source),
        );

        if (!identity || identity.is_expired) {
            return;
        }

        const current = value[index];
        update(index, {
            ...collateralFromIdentity(identity),
            notes: current?.notes ?? '',
            document: current?.document ?? null,
        });
    };

    return (
        <div className="space-y-4">
            {value.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    <Stage4Text k="stage4.ui.9de540e7b8d2" />
                </p>
            )}
            {value.map((collateral, index) => (
                <div key={index} className="rounded-lg border p-4">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <p className="font-medium">
                                <Stage4Text k="stage4.ui.39e0c3e56754" />
                                {index + 1}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                <Stage4Text k="stage4.ui.d9849f2d0b86" />
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() =>
                                onChange(
                                    value.filter(
                                        (_, position) => position !== index,
                                    ),
                                )
                            }
                        >
                            <Trash2 />
                        </Button>
                    </div>
                    {identityOptions.length > 0 && (
                        <div className="mb-4 rounded-md border bg-muted/30 p-3">
                            <Label>
                                <Stage4Text k="stage4.ui.ac060d458d63" />
                            </Label>
                            <Select
                                value={
                                    collateral.customer_identity_id
                                        ? String(
                                              collateral.customer_identity_id,
                                          )
                                        : 'manual'
                                }
                                onValueChange={(source) =>
                                    chooseSource(index, source)
                                }
                            >
                                <SelectTrigger className="mt-2">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="manual">
                                        <Stage4Text k="stage4.ui.e495a54aa028" />
                                    </SelectItem>
                                    {identityOptions.map((identity) => (
                                        <SelectItem
                                            key={identity.id}
                                            value={String(identity.id)}
                                            disabled={identity.is_expired}
                                        >
                                            {identity.collateral_type} ·{' '}
                                            {identity.number}
                                            {identity.is_primary
                                                ? stage4Translate(
                                                      'stage4.ui.c73f51f254b4',
                                                      stage4Locale,
                                                  )
                                                : ''}
                                            {identity.verified_at
                                                ? stage4Translate(
                                                      'stage4.ui.973da3a900f7',
                                                      stage4Locale,
                                                  )
                                                : stage4Translate(
                                                      'stage4.ui.d948d2d59c8e',
                                                      stage4Locale,
                                                  )}
                                            {identity.is_expired
                                                ? stage4Translate(
                                                      'stage4.ui.2bb8fb66b3ac',
                                                      stage4Locale,
                                                  )
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {linkedIdentity(collateral) && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    <Stage4Text k="stage4.ui.bcbdb1df10ca" />
                                </p>
                            )}
                            {errors[
                                `collaterals.${index}.customer_identity_id`
                            ] && (
                                <p className="mt-1 text-sm text-destructive">
                                    {
                                        errors[
                                            `collaterals.${index}.customer_identity_id`
                                        ]
                                    }
                                </p>
                            )}
                        </div>
                    )}
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <Label>
                                <Stage4Text k="stage4.ui.fabb2b5c779a" />
                            </Label>
                            <Select
                                value={collateral.type}
                                disabled={
                                    linkedIdentity(collateral) !== undefined
                                }
                                onValueChange={(type) =>
                                    update(index, { type })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {TYPES.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors[`collaterals.${index}.type`] && (
                                <p className="mt-1 text-sm text-destructive">
                                    {errors[`collaterals.${index}.type`]}
                                </p>
                            )}
                        </div>
                        <div>
                            <Label>
                                <Stage4Text k="stage4.ui.4cc7e7c2b541" />
                            </Label>
                            <Input
                                value={collateral.number}
                                readOnly={
                                    linkedIdentity(collateral) !== undefined
                                }
                                onChange={(event) =>
                                    update(index, {
                                        number: event.target.value,
                                    })
                                }
                                placeholder={stage4Translate(
                                    'stage4.ui.78c2ce2fb4eb',
                                    stage4Locale,
                                )}
                            />
                            {errors[`collaterals.${index}.number`] && (
                                <p className="mt-1 text-sm text-destructive">
                                    {errors[`collaterals.${index}.number`]}
                                </p>
                            )}
                        </div>
                        <div>
                            <Label>
                                <Stage4Text k="stage4.ui.b9f45b6b55d5" />
                            </Label>
                            <Input
                                value={collateral.holder_name}
                                readOnly={
                                    linkedIdentity(collateral) !== undefined
                                }
                                onChange={(event) =>
                                    update(index, {
                                        holder_name: event.target.value,
                                    })
                                }
                                placeholder={stage4Translate(
                                    'stage4.ui.f02c0e4b0131',
                                    stage4Locale,
                                )}
                            />
                        </div>
                        <div>
                            <Label>
                                <Stage4Text k="stage4.ui.7431175db613" />
                            </Label>
                            <Input
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                onChange={(event) =>
                                    update(index, {
                                        document:
                                            event.target.files?.[0] ?? null,
                                    })
                                }
                            />
                            {errors[`collaterals.${index}.document`] && (
                                <p className="mt-1 text-sm text-destructive">
                                    {errors[`collaterals.${index}.document`]}
                                </p>
                            )}
                        </div>
                        <div className="md:col-span-2 xl:col-span-4">
                            <Label>
                                <Stage4Text k="stage4.ui.9f09aefd0dd4" />
                            </Label>
                            <Input
                                value={collateral.notes}
                                onChange={(event) =>
                                    update(index, { notes: event.target.value })
                                }
                                placeholder={stage4Translate(
                                    'stage4.ui.520abfec3a84',
                                    stage4Locale,
                                )}
                            />
                        </div>
                    </div>
                </div>
            ))}
            {value.length < 5 && (
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => onChange([...value, emptyCollateral()])}
                >
                    <Plus />
                    <Stage4Text k="stage4.ui.f36d005ae4a4" />
                </Button>
            )}
        </div>
    );
}
