import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Camera, FileText, Settings2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { TransferBranch } from '@/types';

type CaptureMode = 'camera_required' | 'camera_preferred' | 'gallery_allowed';

type SettingsForm = {
    branch_id: number;
    dispatch_capture_mode: CaptureMode;
    receiving_capture_mode: CaptureMode;
    dispatch_min_photos: number;
    receiving_min_photos: number;
    require_waybill: boolean;
    allow_gallery_override: boolean;
};

type Props = {
    branches: TransferBranch[];
    selectedBranchId: number;
    settings: Omit<SettingsForm, 'branch_id'>;
};

const captureOptions: Array<{
    value: CaptureMode;
    label: string;
    description: string;
}> = [
    {
        value: 'camera_required',
        label: 'Kamera wajib',
        description: 'Bukti harus diambil langsung melalui kamera perangkat.',
    },
    {
        value: 'camera_preferred',
        label: 'Kamera diutamakan',
        description:
            'Kamera menjadi alur utama, galeri dapat dipakai sesuai izin.',
    },
    {
        value: 'gallery_allowed',
        label: 'Galeri diperbolehkan',
        description:
            'Operator dapat mengambil foto atau memilih file dari perangkat.',
    },
];

export default function TransferSettings({
    branches,
    selectedBranchId,
    settings,
}: Props) {
    const form = useForm<SettingsForm>({
        branch_id: selectedBranchId,
        ...settings,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put('/transfers/settings', { preserveScroll: true });
    };

    const switchBranch = (branchId: string) => {
        router.get(
            '/transfers/settings',
            { branch_id: Number(branchId) },
            { preserveState: false, replace: true },
        );
    };

    return (
        <>
            <Head title="Pengaturan Transfer Aset" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="mb-2 -ml-3"
                        >
                            <Link href="/transfers">
                                <ArrowLeft />
                                Kembali ke transfer
                            </Link>
                        </Button>
                        <p className="text-sm font-medium text-primary">
                            Modul Transfer Aset Antar-Cabang
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Kebijakan bukti dan penerimaan
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Atur sumber foto realtime, jumlah bukti minimum, dan
                            kewajiban surat jalan untuk masing-masing cabang.
                        </p>
                    </div>

                    <div className="w-full max-w-sm space-y-2">
                        <Label htmlFor="branch-filter">
                            Cabang yang diatur
                        </Label>
                        <Select
                            value={String(selectedBranchId)}
                            onValueChange={switchBranch}
                        >
                            <SelectTrigger id="branch-filter">
                                <SelectValue />
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
                    </div>
                </header>

                <Alert>
                    <Settings2 />
                    <AlertTitle>Pengaturan berlaku per tahap</AlertTitle>
                    <AlertDescription>
                        Dispatch menggunakan kebijakan cabang asal, sedangkan
                        receiving menggunakan kebijakan cabang tujuan. Semua
                        override tetap mencatat pelaku, alasan, waktu, dan
                        checksum.
                    </AlertDescription>
                </Alert>

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <CapturePolicyCard
                            icon={<Camera className="size-5" />}
                            title="Pemeriksaan keberangkatan"
                            description="Digunakan saat aset dilepas dari cabang asal."
                            mode={form.data.dispatch_capture_mode}
                            minPhotos={form.data.dispatch_min_photos}
                            modeError={form.errors.dispatch_capture_mode}
                            minPhotosError={form.errors.dispatch_min_photos}
                            onModeChange={(value) =>
                                form.setData('dispatch_capture_mode', value)
                            }
                            onMinPhotosChange={(value) =>
                                form.setData('dispatch_min_photos', value)
                            }
                        />

                        <CapturePolicyCard
                            icon={<Camera className="size-5" />}
                            title="Pemeriksaan penerimaan"
                            description="Digunakan saat cabang tujuan memeriksa item."
                            mode={form.data.receiving_capture_mode}
                            minPhotos={form.data.receiving_min_photos}
                            modeError={form.errors.receiving_capture_mode}
                            minPhotosError={form.errors.receiving_min_photos}
                            onModeChange={(value) =>
                                form.setData('receiving_capture_mode', value)
                            }
                            onMinPhotosChange={(value) =>
                                form.setData('receiving_min_photos', value)
                            }
                        />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="size-5" />
                                Dokumen dan fallback
                            </CardTitle>
                            <CardDescription>
                                Guardrail tambahan untuk menjaga bukti
                                pengiriman tetap lengkap dan dapat diaudit.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <BooleanSetting
                                id="require_waybill"
                                checked={form.data.require_waybill}
                                title="Surat jalan wajib saat dispatch"
                                description="Tombol Kirim diblokir sampai nomor atau dokumen surat jalan dilengkapi."
                                onCheckedChange={(checked) =>
                                    form.setData('require_waybill', checked)
                                }
                            />
                            <BooleanSetting
                                id="allow_gallery_override"
                                checked={form.data.allow_gallery_override}
                                title="Izinkan override galeri"
                                description="Hanya pengguna dengan permission override dan alasan resmi yang dapat memakainya."
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'allow_gallery_override',
                                        checked,
                                    )
                                }
                            />
                        </CardContent>
                    </Card>

                    <InputError message={form.errors.branch_id} />

                    <div className="flex justify-end gap-3">
                        <Button asChild type="button" variant="outline">
                            <Link href="/transfers">Batal</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Menyimpan...'
                                : 'Simpan pengaturan'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function CapturePolicyCard({
    icon,
    title,
    description,
    mode,
    minPhotos,
    modeError,
    minPhotosError,
    onModeChange,
    onMinPhotosChange,
}: {
    icon: ReactNode;
    title: string;
    description: string;
    mode: CaptureMode;
    minPhotos: number;
    modeError?: string;
    minPhotosError?: string;
    onModeChange: (value: CaptureMode) => void;
    onMinPhotosChange: (value: number) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    {icon}
                    {title}
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="space-y-2">
                    <Label>Mode sumber foto</Label>
                    <Select
                        value={mode}
                        onValueChange={(value) =>
                            onModeChange(value as CaptureMode)
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {captureOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-xs leading-5 text-muted-foreground">
                        {
                            captureOptions.find(
                                (option) => option.value === mode,
                            )?.description
                        }
                    </p>
                    <InputError message={modeError} />
                </div>

                <div className="space-y-2">
                    <Label>Foto minimum per item</Label>
                    <Input
                        type="number"
                        min={1}
                        max={10}
                        value={minPhotos}
                        onChange={(event) =>
                            onMinPhotosChange(Number(event.target.value))
                        }
                    />
                    <p className="text-xs leading-5 text-muted-foreground">
                        Sistem memvalidasi jumlah bukti untuk setiap unit atau
                        baris pooled inventory sebelum tahap dapat difinalisasi.
                    </p>
                    <InputError message={minPhotosError} />
                </div>
            </CardContent>
        </Card>
    );
}

function BooleanSetting({
    id,
    checked,
    title,
    description,
    onCheckedChange,
}: {
    id: string;
    checked: boolean;
    title: string;
    description: string;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-start gap-3 rounded-xl border p-4">
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(value === true)}
            />
            <div className="min-w-0">
                <Label htmlFor={id}>{title}</Label>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    {description}
                </p>
            </div>
        </div>
    );
}
