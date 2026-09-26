import { router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Stage4Text, stage4Translate } from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

type TransferActionDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    submitLabel: string;
    noteLabel: string;
    url: string;
    noteField: 'notes' | 'reason';
    payload: Record<string, string | number>;
    initialNotes?: string;
    warning?: string;
    destructive?: boolean;
};

/**
 * Shared confirmation form for transfer decisions previously using window.prompt.
 * Parent mounts it only while an action is selected, resetting notes on close.
 * Validation and permissions remain enforced by the existing Laravel endpoints.
 */
export function TransferActionDialog({
    open,
    onOpenChange,
    title,
    description,
    submitLabel,
    noteLabel,
    url,
    noteField,
    payload,
    initialNotes = '',
    warning,
    destructive = false,
}: TransferActionDialogProps) {
    const { locale: stage4Locale } = useAppLocale();

    const noteId = useId();
    const helpId = useId();
    const errorId = useId();
    const [notes, setNotes] = useState(initialNotes);
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState(false);
    const pending = useRef(false);
    const noteLength = notes.trim().length;
    const valid = noteLength >= 5 && noteLength <= 3000;

    const handleOpenChange = (nextOpen: boolean) => {
        if (pending.current) {
            return;
        }

        onOpenChange(nextOpen);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (pending.current) {
            return;
        }

        if (!valid) {
            setError('Catatan wajib berisi 5–3.000 karakter setelah spasi dihapus.');

            return;
        }

        pending.current = true;
        setProcessing(true);
        setError('');

        router.post(
            url,
            { ...payload, [noteField]: notes.trim() },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: (errors) => {
                    setError(
                        errors[noteField] ??
                            errors.item ??
                            Object.values(errors)[0] ??
                            'Permintaan gagal. Silakan periksa kembali.',
                    );
                },
                onFinish: () => {
                    pending.current = false;
                    setProcessing(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                {warning && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle><Stage4Text k="stage4.ui.80b41ba77977" /></AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                )}

                <form className="space-y-4" onSubmit={submit}>
                    <div className="space-y-2">
                        <Label htmlFor={noteId}>{noteLabel}</Label>
                        <textarea
                            id={noteId}
                            autoFocus
                            required
                            minLength={5}
                            maxLength={3000}
                            value={notes}
                            aria-invalid={error !== ''}
                            aria-describedby={error ? errorId : helpId}
                            onChange={(event) => {
                                setNotes(event.target.value);

                                if (error) {
                                    setError('');
                                }
                            }}
                            className="min-h-28 w-full resize-y rounded-md border bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            placeholder={stage4Translate("stage4.ui.b14019ed164c", stage4Locale)}
                            disabled={processing}
                        />
                        <div id={helpId} className="flex justify-between gap-2 text-xs text-muted-foreground">
                            <span><Stage4Text k="stage4.ui.6cedc1c67dd8" /></span>
                            <span className="tabular-nums">{noteLength}/3000</span>
                        </div>
                        {error && (
                            <p id={errorId} role="alert" className="text-sm text-destructive">
                                {error}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => handleOpenChange(false)}
                        ><Stage4Text k="stage4.ui.1433539c3b8f" />
                        </Button>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={!valid || processing}
                        >
                            {processing ? stage4Translate("stage4.ui.3da705bdb58e", stage4Locale) : submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
