import { AlertCircleIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export default function AlertError({
    errors,
    title,
}: {
    errors: string[];
    title?: string;
}) {
    return (
        <Alert variant="destructive" role="alert" aria-live="polite">
            <AlertCircleIcon />
            <AlertTitle>{title || 'Tindakan belum dapat diproses'}</AlertTitle>
            <AlertDescription>
                <ul className="list-inside list-disc text-sm">
                    {Array.from(new Set(errors)).map((error) => (
                        <li key={error}>{error}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
