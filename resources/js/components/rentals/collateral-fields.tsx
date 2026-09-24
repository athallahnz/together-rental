import { Plus, Trash2 } from 'lucide-react';
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
            update(index, { customer_identity_id: null });

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
                    Tidak ada jaminan fisik. Deposit uang tetap dicatat terpisah
                    pada bagian pembayaran.
                </p>
            )}
            {value.map((collateral, index) => (
                <div key={index} className="rounded-lg border p-4">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <p className="font-medium">Jaminan #{index + 1}</p>
                            <p className="text-xs text-muted-foreground">
                                Barang/dokumen yang benar-benar diterima dan
                                ditahan oleh petugas.
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
                            <Label>Sumber jaminan</Label>
                            <Select
                                value={
                                    collateral.customer_identity_id
                                        ? String(collateral.customer_identity_id)
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
                                        Input manual
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
                                                ? ' · Utama'
                                                : ''}
                                            {identity.verified_at
                                                ? ' · Terverifikasi'
                                                : ' · Belum diverifikasi'}
                                            {identity.is_expired
                                                ? ' · Kedaluwarsa'
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {linkedIdentity(collateral) && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Diambil dari Customer360 dan disalin ke
                                    snapshot rental saat checkout. Hapus atau
                                    pilih input manual bila dokumen ini tidak
                                    benar-benar ditahan.
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
                            <Label>Jenis</Label>
                            <Select
                                value={collateral.type}
                                disabled={linkedIdentity(collateral) !== undefined}
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
                            <Label>Nomor / identitas barang</Label>
                            <Input
                                value={collateral.number}
                                readOnly={linkedIdentity(collateral) !== undefined}
                                onChange={(event) =>
                                    update(index, {
                                        number: event.target.value,
                                    })
                                }
                                placeholder="NIK / nomor SIM / nomor kartu"
                            />
                            {errors[`collaterals.${index}.number`] && (
                                <p className="mt-1 text-sm text-destructive">
                                    {errors[`collaterals.${index}.number`]}
                                </p>
                            )}
                        </div>
                        <div>
                            <Label>Atas nama</Label>
                            <Input
                                value={collateral.holder_name}
                                readOnly={linkedIdentity(collateral) !== undefined}
                                onChange={(event) =>
                                    update(index, {
                                        holder_name: event.target.value,
                                    })
                                }
                                placeholder="Nama pemilik jaminan"
                            />
                        </div>
                        <div>
                            <Label>Dokumen/foto (opsional)</Label>
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
                            <Label>Catatan</Label>
                            <Input
                                value={collateral.notes}
                                onChange={(event) =>
                                    update(index, { notes: event.target.value })
                                }
                                placeholder="Kondisi fisik, tempat penyimpanan, atau catatan lain"
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
                    Tambah jaminan fisik
                </Button>
            )}
        </div>
    );
}
