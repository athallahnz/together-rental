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

export type CollateralInput = {
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
    type: 'KTP',
    number: '',
    holder_name: '',
    notes: '',
    document: null,
});

export function CollateralFields({
    value,
    onChange,
    errors = {},
}: {
    value: CollateralInput[];
    onChange: (value: CollateralInput[]) => void;
    errors?: Record<string, string>;
}) {
    const update = (index: number, patch: Partial<CollateralInput>) =>
        onChange(
            value.map((item, position) =>
                position === index ? { ...item, ...patch } : item,
            ),
        );

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
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <Label>Jenis</Label>
                            <Select
                                value={collateral.type}
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
