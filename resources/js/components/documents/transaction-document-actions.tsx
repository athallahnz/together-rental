import { Download, FileCheck2, Printer, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type SourceType = 'booking' | 'rental';
type DocumentType = 'invoice' | 'agreement';
type ActionMode = 'print' | 'download';

type Props = {
    sourceType: SourceType;
    sourceReference: string;
    className?: string;
};

type QuickIssueResponse = {
    data: {
        id: number;
        document_number: string;
        document_type: DocumentType;
        version: number;
        created: boolean;
        pdf_url: string;
        preview_url: string;
    };
};

const documentLabels: Record<DocumentType, string> = {
    invoice: 'Invoice',
    agreement: 'Agreement Rental',
};

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export function TransactionDocumentActions({
    sourceType,
    sourceReference,
    className,
}: Props) {
    const [authorized, setAuthorized] = useState<boolean | null>(null);
    const [dismissed, setDismissed] = useState(false);
    const [busy, setBusy] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const documentTypes = useMemo<DocumentType[]>(
        () => (sourceType === 'booking' ? ['invoice'] : ['invoice', 'agreement']),
        [sourceType],
    );

    useEffect(() => {
        const controller = new AbortController();
        const params = new URLSearchParams({
            document_type: documentTypes[0],
            source_type: sourceType,
            q: sourceReference,
        });

        fetch(`/documents/source-options?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (response.status === 403) {
                    setAuthorized(false);

                    return;
                }

                if (!response.ok) {
                    setAuthorized(false);

                    return;
                }

                const payload = (await response.json()) as {
                    data?: Array<{ reference?: string }>;
                };
                const exists = (payload.data ?? []).some(
                    (option) => option.reference === sourceReference,
                );
                setAuthorized(exists);
            })
            .catch((reason: unknown) => {
                if (reason instanceof DOMException && reason.name === 'AbortError') {
                    return;
                }

                setAuthorized(false);
            });

        return () => controller.abort();
    }, [documentTypes, sourceReference, sourceType]);

    const issue = async (documentType: DocumentType, mode: ActionMode) => {
        const key = `${documentType}:${mode}`;
        setBusy(key);
        setError(null);
        setMessage(null);

        const previewWindow =
            mode === 'print' ? window.open('about:blank', '_blank') : null;

        try {
            const response = await fetch('/documents/quick-issue', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    document_type: documentType,
                    source_type: sourceType,
                    source_reference: sourceReference,
                }),
            });

            const payload = (await response.json()) as
                | QuickIssueResponse
                | { message?: string; errors?: Record<string, string[]> };

            if (!response.ok || !('data' in payload)) {
                const firstError =
                    'errors' in payload && payload.errors
                        ? Object.values(payload.errors).flat()[0]
                        : null;
                const errorMessage =
                    'message' in payload && typeof payload.message === 'string'
                        ? payload.message
                        : null;

                throw new Error(
                    firstError ?? errorMessage ?? 'Dokumen gagal diterbitkan.',
                );
            }

            const data = payload.data;
            setMessage(
                `${documentLabels[documentType]} ${data.document_number} Â· V${data.version}${
                    data.created ? ' diterbitkan' : ' digunakan kembali'
                }`,
            );

            if (mode === 'print') {
                if (previewWindow) {
                    previewWindow.location.href = data.preview_url;
                } else {
                    window.open(data.preview_url, '_blank', 'noopener,noreferrer');
                }
            } else {
                const link = document.createElement('a');
                link.href = data.pdf_url;
                link.rel = 'noopener';
                document.body.appendChild(link);
                link.click();
                link.remove();
            }
        } catch (reason) {
            previewWindow?.close();
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Dokumen gagal diterbitkan.',
            );
        } finally {
            setBusy(null);
        }
    };

    if (authorized !== true || dismissed) {
        return null;
    }

    return (
        <Card className={className}>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileCheck2 className="size-4" />
                            Dokumen transaksi
                        </CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Cetak atau download langsung tanpa pindah ke Document Center.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setDismissed(true)}
                    >
                        <X /> Lewati
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                {documentTypes.map((documentType) => (
                    <div
                        key={documentType}
                        className="flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p className="font-medium">
                                {documentLabels[documentType]}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Snapshot/versioning tetap memakai Document Center yang sama.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={busy !== null}
                                onClick={() => issue(documentType, 'print')}
                            >
                                <Printer />
                                {busy === `${documentType}:print`
                                    ? 'Menyiapkan...'
                                    : 'Cetak'}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                disabled={busy !== null}
                                onClick={() => issue(documentType, 'download')}
                            >
                                <Download />
                                {busy === `${documentType}:download`
                                    ? 'Menyiapkan...'
                                    : 'Download'}
                            </Button>
                        </div>
                    </div>
                ))}

                {message && (
                    <Alert>
                        <FileCheck2 />
                        <AlertTitle>Dokumen siap</AlertTitle>
                        <AlertDescription>{message}</AlertDescription>
                    </Alert>
                )}
                {error && (
                    <Alert variant="destructive">
                        <AlertTitle>Dokumen gagal diproses</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}
            </CardContent>
        </Card>
    );
}
