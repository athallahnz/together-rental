import { Camera, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { TransferCameraDialog } from '@/components/transfers/transfer-camera-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useGlobalLocale } from '@/lib/locale-store';

type Props = {
    id: string;
    label: string;
    files: File[];
    onFilesChange: (files: File[]) => void;
    filenamePrefix: string;
    maxFiles?: number;
    maxBytes?: number;
    error?: string;
};

const allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];

export function CatalogImageField({
    id,
    label,
    files,
    onFilesChange,
    filenamePrefix,
    maxFiles = 1,
    maxBytes = 4 * 1024 * 1024,
    error,
}: Props) {
    const locale = useGlobalLocale();
    const english = locale === 'en';
    const [cameraOpen, setCameraOpen] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const addFiles = (incoming: File[]) => {
        if (incoming.length === 0) {
            return;
        }

        const next =
            maxFiles === 1 ? incoming.slice(0, 1) : [...files, ...incoming];

        if (next.length > maxFiles) {
            setLocalError(
                english
                    ? `Only ${maxFiles} more photo(s) can be added.`
                    : `Hanya ${maxFiles} foto lagi yang dapat ditambahkan.`,
            );

            return;
        }

        if (next.some((file) => !allowedTypes.includes(file.type))) {
            setLocalError(
                english
                    ? 'Use a JPG, PNG, or WebP image.'
                    : 'Gunakan gambar JPG, PNG, atau WebP.',
            );

            return;
        }

        if (next.some((file) => file.size > maxBytes)) {
            const limit = maxBytes / 1024 / 1024;

            setLocalError(
                english
                    ? `Each image must be no larger than ${limit} MB.`
                    : `Setiap gambar maksimal ${limit} MB.`,
            );

            return;
        }

        setLocalError(null);
        onFilesChange(next);
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="file"
                multiple={maxFiles > 1}
                accept="image/png,image/jpeg,image/webp"
                disabled={maxFiles === 0}
                onChange={(event) => {
                    addFiles(Array.from(event.currentTarget.files ?? []));
                    event.currentTarget.value = '';
                }}
            />
            <Button
                type="button"
                variant="outline"
                className="w-fit"
                disabled={maxFiles === 0}
                onClick={() => setCameraOpen(true)}
            >
                <Camera className="size-4" />
                {english ? 'Open camera' : 'Buka kamera'}
            </Button>
            {maxFiles === 0 && (
                <p className="text-xs text-muted-foreground">
                    {english
                        ? 'The gallery is full. Clear the existing gallery to add photos.'
                        : 'Galeri penuh. Kosongkan galeri lama untuk menambah foto.'}
                </p>
            )}
            {files.length > 0 && (
                <ul
                    className="space-y-1"
                    aria-label={english ? 'Selected photos' : 'Foto terpilih'}
                >
                    {files.map((file, index) => (
                        <li
                            key={`${file.name}-${file.lastModified}-${index}`}
                            className="flex items-center justify-between gap-2 text-sm"
                        >
                            <span className="min-w-0 truncate">
                                {file.name}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`${english ? 'Remove' : 'Hapus'} ${file.name}`}
                                onClick={() => {
                                    onFilesChange(
                                        files.filter(
                                            (_, fileIndex) =>
                                                fileIndex !== index,
                                        ),
                                    );
                                    setLocalError(null);
                                }}
                            >
                                <X className="size-4" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            <InputError message={localError ?? error} />
            <TransferCameraDialog
                open={cameraOpen}
                onOpenChange={setCameraOpen}
                onCapture={(file) => addFiles([file])}
                filenamePrefix={filenamePrefix}
            />
        </div>
    );
}
