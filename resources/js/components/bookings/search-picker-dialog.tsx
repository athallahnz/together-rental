import { useEffect, useRef, useState } from 'react';
import {
    Check,
    ChevronsUpDown,
    ImageIcon,
    LoaderCircle,
    Search,
} from 'lucide-react';
import { Stage4Text, stage4Translate } from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type BookingSearchOption = {
    id: number;
    name: string;
    code?: string;
    sku?: string;
    customer_number?: string;
    phone?: string | null;
    is_member?: boolean;
    member_number?: string | null;
    branch_id?: number | null;
    brand?: string | null;
    model?: string | null;
    variant?: string | null;
    tracking_type?: string;
    image_url?: string | null;
    brand_logo_url?: string | null;
};

type SearchType = 'customer' | 'product' | 'package';

type Props = {
    type: SearchType;
    value: BookingSearchOption | null;
    onSelect: (option: BookingSearchOption) => void;
    branchId?: number;
    ratePlanId?: number;
    disabled?: boolean;
};

const labels: Record<SearchType, string> = {
    customer: 'pelanggan',
    product: 'produk',
    package: 'paket',
};

const optionMeta = (option: BookingSearchOption) =>
    [
        option.customer_number ?? option.sku ?? option.code ?? '',
        option.tracking_type === 'bulk' || option.tracking_type === 'quantity'
            ? 'Bulk'
            : '',
    ]
        .filter(Boolean)
        .join(' · ');

export function SearchPickerDialog({
    type,
    value,
    onSelect,
    branchId,
    ratePlanId,
    disabled = false,
}: Props) {
    const { locale: stage4Locale } = useAppLocale();

    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<BookingSearchOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const label =
        stage4Locale === 'en'
            ? { customer: 'customer', product: 'product', package: 'package' }[
                  type
              ]
            : labels[type];
    const missingContext = type !== 'customer' && (!branchId || !ratePlanId);

    useEffect(() => {
        if (!open) {
            return;
        }

        const timeout = window.setTimeout(() => inputRef.current?.focus(), 50);

        return () => window.clearTimeout(timeout);
    }, [open]);

    useEffect(() => {
        const normalized = query.trim();

        if (!open || normalized.length < 2 || missingContext) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setLoading(true);
            setFailed(false);

            const parameters = new URLSearchParams({
                type,
                q: normalized,
            });

            if (branchId) {
                parameters.set('branch_id', String(branchId));
            }

            if (ratePlanId) {
                parameters.set('rate_plan_id', String(ratePlanId));
            }

            try {
                const response = await fetch(
                    `/bookings/options?${parameters}`,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Search request failed.');
                }

                const payload = (await response.json()) as {
                    data: BookingSearchOption[];
                };

                setResults(payload.data);
            } catch (error) {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }

                setFailed(true);
                setResults([]);
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }, 300);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [branchId, missingContext, open, query, ratePlanId, type]);

    const choose = (option: BookingSearchOption) => {
        onSelect(option);
        setOpen(false);
    };

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (!nextOpen) {
            setQuery('');
            setResults([]);
            setLoading(false);
            setFailed(false);
        }
    };

    const handleQueryChange = (nextQuery: string) => {
        setQuery(nextQuery);
        setResults([]);
        setLoading(false);
        setFailed(false);
    };

    // const choose = (option: BookingSearchOption) => {
    //     onSelect(option);
    //     handleOpenChange(false);
    // };

    return (
        <>
            <Button
                type="button"
                variant="outline"
                className="h-10 w-full justify-between px-3 font-normal"
                disabled={disabled}
                onClick={() => handleOpenChange(true)}
            >
                <span
                    className={cn(
                        'truncate',
                        !value && 'text-muted-foreground',
                    )}
                >
                    {value
                        ? `${value.name}${optionMeta(value) ? ` · ${optionMeta(value)}` : ''}`
                        : `${stage4Translate('stage4.ui.e137919915b5', stage4Locale)} ${label}`}
                </span>
                <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
            </Button>

            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogContent className="flex max-h-[88vh] flex-col sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            <Stage4Text k="stage4.ui.e137919915b5" /> {label}
                        </DialogTitle>
                        <DialogDescription>
                            <Stage4Text k="stage4.ui.72b7034fe2b9" />
                        </DialogDescription>
                    </DialogHeader>

                    {missingContext ? (
                        <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            <Stage4Text k="stage4.ui.601ffccbb716" />
                        </p>
                    ) : (
                        <>
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    ref={inputRef}
                                    value={query}
                                    className="pr-9 pl-9"
                                    placeholder={`${stage4Translate('stage4.ui.560e3c2f18d3', stage4Locale)} ${label}...`}
                                    onChange={(event) =>
                                        handleQueryChange(event.target.value)
                                    }
                                />
                                {loading && (
                                    <LoaderCircle className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                                )}
                            </div>

                            <div className="min-h-52 flex-1 overflow-y-auto rounded-md border">
                                {query.trim().length < 2 && (
                                    <p className="p-6 text-center text-sm text-muted-foreground">
                                        <Stage4Text k="stage4.ui.8fdea1923869" />{' '}
                                        {label}.
                                    </p>
                                )}
                                {failed && (
                                    <p className="p-6 text-center text-sm text-destructive">
                                        <Stage4Text k="stage4.ui.8a6bc6513c46" />
                                    </p>
                                )}
                                {!loading &&
                                    !failed &&
                                    query.trim().length >= 2 &&
                                    results.length === 0 && (
                                        <p className="p-6 text-center text-sm text-muted-foreground">
                                            {label}{' '}
                                            {stage4Translate(
                                                'stage4.ui.a26e90671794',
                                                stage4Locale,
                                            )}
                                        </p>
                                    )}
                                {results.map((option) => (
                                    <button
                                        key={option.id}
                                        type="button"
                                        className="group flex w-full items-center gap-4 border-b px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-muted/70"
                                        onClick={() => choose(option)}
                                    >
                                        {type !== 'customer' && (
                                            <div className="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted sm:size-24">
                                                {option.image_url ? (
                                                    <img
                                                        src={option.image_url}
                                                        alt={option.name}
                                                        className="size-full object-cover transition-transform group-hover:scale-105"
                                                    />
                                                ) : (
                                                    <ImageIcon className="size-7 text-muted-foreground/50" />
                                                )}
                                                {option.brand_logo_url && (
                                                    <div className="absolute right-1.5 bottom-1.5 flex h-7 max-w-16 items-center justify-center rounded bg-background/95 px-1.5 shadow-sm">
                                                        <img
                                                            src={
                                                                option.brand_logo_url
                                                            }
                                                            alt={
                                                                option.brand ??
                                                                stage4Translate(
                                                                    'stage4.ui.62b4aa574f70',
                                                                    stage4Locale,
                                                                )
                                                            }
                                                            className="max-h-5 max-w-full object-contain"
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold">
                                                {option.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {[
                                                    optionMeta(option),
                                                    option.phone,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </p>
                                            {type === 'product' && (
                                                <p className="mt-1 truncate text-xs text-muted-foreground">
                                                    {[
                                                        option.brand,
                                                        option.model,
                                                        option.variant,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') ||
                                                        'Brand dan model belum diisi'}
                                                </p>
                                            )}
                                        </div>
                                        {value?.id === option.id && (
                                            <Check className="size-4 shrink-0 text-primary" />
                                        )}
                                    </button>
                                ))}
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
