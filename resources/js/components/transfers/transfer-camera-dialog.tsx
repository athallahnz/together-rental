import { useCallback, useEffect, useRef, useState } from 'react';
import { Camera, RefreshCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCapture: (file: File) => void;
    filenamePrefix?: string;
};

export function TransferCameraDialog({
    open,
    onOpenChange,
    onCapture,
    filenamePrefix = 'transfer-photo',
}: Props) {
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [facingMode, setFacingMode] = useState<'environment' | 'user'>(
        'environment',
    );

    const stopStream = useCallback(() => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
    }, []);

    useEffect(() => {
        if (!open) {
            stopStream();

            return;
        }

        let cancelled = false;

        const mediaDevices = navigator.mediaDevices;

        if (!mediaDevices?.getUserMedia) {
            queueMicrotask(() => {
                if (!cancelled) {
                    setError(
                        'Browser ini tidak mendukung akses kamera realtime.',
                    );
                }
            });

            return () => {
                cancelled = true;
                stopStream();
            };
        }

        mediaDevices
            .getUserMedia({
                video: {
                    facingMode: { ideal: facingMode },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
                audio: false,
            })
            .then((stream) => {
                if (cancelled) {
                    stream.getTracks().forEach((track) => track.stop());

                    return;
                }

                setError(null);
                streamRef.current = stream;

                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    void videoRef.current.play();
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(
                        'Kamera tidak dapat dibuka. Periksa izin kamera dan pastikan halaman menggunakan HTTPS atau localhost.',
                    );
                }
            });

        return () => {
            cancelled = true;
            stopStream();
        };
    }, [facingMode, open, stopStream]);

    const switchCamera = () => {
        stopStream();
        setError(null);
        setFacingMode((current) =>
            current === 'environment' ? 'user' : 'environment',
        );
    };

    const capture = () => {
        const video = videoRef.current;

        if (!video || video.videoWidth === 0 || video.videoHeight === 0) {
            setError(
                'Preview kamera belum siap. Tunggu sebentar lalu coba lagi.',
            );

            return;
        }

        const canvas = document.createElement('canvas');

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const context = canvas.getContext('2d');

        if (!context) {
            setError('Browser tidak dapat memproses gambar kamera.');

            return;
        }

        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    setError('Foto gagal diproses.');

                    return;
                }

                const timestamp = new Date()
                    .toISOString()
                    .replaceAll(':', '-')
                    .replaceAll('.', '-');
                onCapture(
                    new File([blob], `${filenamePrefix}-${timestamp}.jpg`, {
                        type: 'image/jpeg',
                        lastModified: Date.now(),
                    }),
                );
                onOpenChange(false);
            },
            'image/jpeg',
            0.88,
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Ambil Foto Realtime</DialogTitle>
                </DialogHeader>
                <div className="overflow-hidden rounded-lg border bg-black">
                    <video
                        ref={videoRef}
                        className="aspect-video w-full object-cover"
                        playsInline
                        muted
                    />
                </div>
                {error && (
                    <p className="text-sm text-destructive" role="alert">
                        {error}
                    </p>
                )}
                <DialogFooter className="gap-2 sm:justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={switchCamera}
                    >
                        <RefreshCcw className="size-4" />
                        Ganti Kamera
                    </Button>
                    <Button type="button" onClick={capture} disabled={!!error}>
                        <Camera className="size-4" />
                        Ambil Foto
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
