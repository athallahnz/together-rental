import { AlertTriangle, CircleHelp } from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useMemo,
    useRef,
    useState,
} from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ConfirmDialogOptions = {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'default' | 'destructive';
};

type ConfirmDialogContextValue = {
    confirm: (options: ConfirmDialogOptions) => Promise<boolean>;
};

const ConfirmDialogContext = createContext<ConfirmDialogContextValue | null>(
    null,
);

export function ConfirmDialogProvider({
    children,
}: {
    children: React.ReactNode;
}) {
    const [options, setOptions] = useState<ConfirmDialogOptions | null>(null);
    const resolverRef = useRef<((confirmed: boolean) => void) | null>(null);

    const finish = useCallback((confirmed: boolean) => {
        resolverRef.current?.(confirmed);
        resolverRef.current = null;
        setOptions(null);
    }, []);

    const confirm = useCallback((nextOptions: ConfirmDialogOptions) => {
        resolverRef.current?.(false);

        return new Promise<boolean>((resolve) => {
            resolverRef.current = resolve;
            setOptions(nextOptions);
        });
    }, []);

    const contextValue = useMemo(() => ({ confirm }), [confirm]);
    const destructive = options?.variant === 'destructive';
    const Icon = destructive ? AlertTriangle : CircleHelp;

    return (
        <ConfirmDialogContext.Provider value={contextValue}>
            {children}

            <Dialog
                open={options !== null}
                onOpenChange={(open) => {
                    if (!open && options !== null) {
                        finish(false);
                    }
                }}
            >
                <DialogContent
                    className="sm:max-w-md"
                    onEscapeKeyDown={() => finish(false)}
                    onPointerDownOutside={() => finish(false)}
                >
                    <DialogHeader>
                        <div className="flex items-start gap-3 text-left">
                            <div
                                className={
                                    destructive
                                        ? 'flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive'
                                        : 'flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary'
                                }
                            >
                                <Icon className="size-5" />
                            </div>
                            <div className="min-w-0">
                                <DialogTitle>{options?.title}</DialogTitle>
                                {options?.description && (
                                    <DialogDescription className="mt-2 leading-relaxed">
                                        {options.description}
                                    </DialogDescription>
                                )}
                            </div>
                        </div>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => finish(false)}
                        >
                            {options?.cancelLabel ?? 'Batal'}
                        </Button>
                        <Button
                            type="button"
                            variant={destructive ? 'destructive' : 'default'}
                            onClick={() => finish(true)}
                        >
                            {options?.confirmLabel ?? 'Lanjutkan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </ConfirmDialogContext.Provider>
    );
}

export function useConfirmDialog(): ConfirmDialogContextValue['confirm'] {
    const context = useContext(ConfirmDialogContext);

    if (!context) {
        throw new Error(
            'useConfirmDialog harus digunakan di dalam ConfirmDialogProvider.',
        );
    }

    return context.confirm;
}
