import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

type ValidationErrorValue = string | string[] | null | undefined;
type ValidationErrors = Record<string, ValidationErrorValue>;

const validationToastId = 'global-validation-errors';
const httpExceptionToastId = 'global-http-exception';
const networkErrorToastId = 'global-network-error';
const nativeValidationToastId = 'global-native-validation';

function uniqueMessages(errors: ValidationErrors): string[] {
    return Array.from(
        new Set(
            Object.values(errors)
                .flatMap((value) =>
                    Array.isArray(value) ? value : value ? [value] : [],
                )
                .filter((message): message is string => message.trim() !== ''),
        ),
    );
}

function validationDescription(messages: string[]): string {
    const visible = messages.slice(0, 3);
    const remaining = messages.length - visible.length;
    const description = visible.join(' • ');

    return remaining > 0
        ? `${description} • ${remaining} kesalahan lainnya.`
        : description;
}

function fieldLabel(
    element: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement,
): string {
    const explicitLabel = element.getAttribute('aria-label');

    if (explicitLabel) {
        return explicitLabel;
    }

    const label = element.labels?.[0];

    if (label?.textContent?.trim()) {
        return label.textContent.trim();
    }

    return element.name || 'Kolom ini';
}

export function useFlashToast(): void {
    useEffect(() => {
        const unregisterFlash = router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message, {
                description: data.description,
                duration: data.duration,
                id: data.id,
            });
        });

        const unregisterError = router.on('error', (event) => {
            const detail = (event as CustomEvent<{ errors?: ValidationErrors }>)
                .detail;
            const messages = uniqueMessages(detail?.errors ?? {});

            if (messages.length === 0) {
                return;
            }

            toast.error('Periksa kembali formulir', {
                id: validationToastId,
                description: validationDescription(messages),
                duration: 7000,
            });
        });

        const unregisterHttpException = router.on('httpException', () => {
            toast.error('Respons server tidak dapat diproses', {
                id: httpExceptionToastId,
                description:
                    'Muat ulang halaman lalu ulangi tindakan. Hubungi administrator bila masalah tetap terjadi.',
                duration: 7000,
            });
        });

        const unregisterNetworkError = router.on('networkError', () => {
            toast.error('Terjadi kesalahan aplikasi', {
                id: networkErrorToastId,
                description:
                    'Tindakan belum dapat diselesaikan. Coba kembali beberapa saat lagi.',
                duration: 7000,
            });
        });

        const handleNativeInvalid = (event: Event) => {
            const target = event.target;

            if (
                !(target instanceof HTMLInputElement) &&
                !(target instanceof HTMLSelectElement) &&
                !(target instanceof HTMLTextAreaElement)
            ) {
                return;
            }

            toast.warning('Lengkapi data yang wajib diisi', {
                id: nativeValidationToastId,
                description: `${fieldLabel(target)}: ${target.validationMessage}`,
                duration: 5000,
            });
        };

        document.addEventListener('invalid', handleNativeInvalid, true);

        return () => {
            unregisterFlash();
            unregisterError();
            unregisterHttpException();
            unregisterNetworkError();
            document.removeEventListener('invalid', handleNativeInvalid, true);
        };
    }, []);
}
