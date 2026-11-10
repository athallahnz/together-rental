import { Download, FileCheck2, Printer, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    Stage4Text,
    stage4Translate,
    stage4TranslateDynamic,
} from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
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

type DocumentErrorResponse = {
    message?: string;
    errors?: Record<string, string[]>;
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
    const { locale: stage4Locale } = useAppLocale();

    const [authorized, setAuthorized] = useState<boolean | null>(null);
    const [dismissed, setDismissed] = useState(false);
    const [busy, setBusy] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const documentTypes = useMemo<DocumentType[]>(
        () =>
            sourceType === 'booking' ? ['invoice'] : ['invoice', 'agreement'],
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
                if (
                    reason instanceof DOMException &&
                    reason.name === 'AbortError'
                ) {
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
            const pdfResponse = await fetch(
                mode === 'print' ? data.preview_url : data.pdf_url,
                { headers: { Accept: 'application/json' } },
            );

            if (!pdfResponse.ok) {
                let detail =
                    stage4Locale === 'en'
                        ? 'The PDF could not be generated. Please try again.'
                        : 'PDF gagal dibuat. Silakan coba kembali.';

                if (
                    pdfResponse.headers
                        .get('content-type')
                        ?.includes('application/json')
                ) {
                    const failure =
                        (await pdfResponse.json()) as DocumentErrorResponse;
                    detail =
                        Object.values(failure.errors ?? {}).flat()[0] ??
                        failure.message ??
                        detail;
                }

                throw new Error(detail);
            }

            if (
                !pdfResponse.headers
                    .get('content-type')
                    ?.includes('application/pdf')
            ) {
                throw new Error(
                    stage4Locale === 'en'
                        ? 'The server did not return a PDF. Refresh the page and sign in again if needed.'
                        : 'Server belum mengembalikan PDF. Muat ulang halaman dan login kembali bila diperlukan.',
                );
            }

            const pdfUrl = URL.createObjectURL(await pdfResponse.blob());
            // Keep the URL alive while the browser PDF viewer loads and prints.
            window.setTimeout(() => URL.revokeObjectURL(pdfUrl), 300_000);

            if (mode === 'print') {
                if (previewWindow) {
                    previewWindow.location.href = pdfUrl;
                } else {
                    window.open(pdfUrl, '_blank', 'noopener,noreferrer');
                }
            } else {
                const link = document.createElement('a');
                link.href = pdfUrl;
                link.download =
                    data.document_number.toLowerCase().replace(/[\\/]/g, '-') +
                    '.pdf';
                link.rel = 'noopener';
                document.body.appendChild(link);
                link.click();
                link.remove();
            }

            setMessage(
                `${documentLabels[documentType]} ${data.document_number} · V${data.version}${
                    data.created ? ' diterbitkan' : ' digunakan kembali'
                }`,
            );
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
                            <Stage4Text k="stage4.ui.e0d3e769c653" />
                        </CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            <Stage4Text k="stage4.ui.274a60e86740" />
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setDismissed(true)}
                    >
                        <X />
                        <Stage4Text k="stage4.ui.8bd638497107" />
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
                                {stage4TranslateDynamic(
                                    documentLabels[documentType],
                                    stage4Locale,
                                )}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                <Stage4Text k="stage4.ui.90a079715238" />
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
                                    ? stage4Translate(
                                          'stage4.ui.9683d9f785f4',
                                          stage4Locale,
                                      )
                                    : stage4Translate(
                                          'stage4.ui.e96378dc0a9d',
                                          stage4Locale,
                                      )}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                disabled={busy !== null}
                                onClick={() => issue(documentType, 'download')}
                            >
                                <Download />
                                {busy === `${documentType}:download`
                                    ? stage4Translate(
                                          'stage4.ui.9683d9f785f4',
                                          stage4Locale,
                                      )
                                    : stage4Translate(
                                          'stage4.ui.a479c9c34e87',
                                          stage4Locale,
                                      )}
                            </Button>
                        </div>
                    </div>
                ))}

                {message && (
                    <Alert>
                        <FileCheck2 />
                        <AlertTitle>
                            <Stage4Text k="stage4.ui.4928cf59e856" />
                        </AlertTitle>
                        <AlertDescription>{message}</AlertDescription>
                    </Alert>
                )}
                {error && (
                    <Alert variant="destructive">
                        <AlertTitle>
                            <Stage4Text k="stage4.ui.e1b9d99c661b" />
                        </AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}
            </CardContent>
        </Card>
    );
}
