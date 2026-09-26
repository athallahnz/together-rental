import { CalendarDays, LoaderCircle, ShieldCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { translateKey, useAppLocale } from '@/lib/i18n';
import type { AppLocale } from '@/lib/i18n';
import { publicAvailabilityLabel } from '@/lib/public-i18n';
import {
    CalendarMonth,
    CalendarToolbar,
    shiftMonth,
} from '@/components/catalog/asset-smart-calendar';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PublicAssetCalendarPayload, PublicBranch } from '@/types';

type Props = {
    branch: PublicBranch;
    slug: string;
    productName: string;
};

function currentMonth() {
    const date = new Date();

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export default function PublicAssetCalendar({
    branch,
    slug,
    productName,
}: Props) {
    const { locale, tr } = useAppLocale();

    const [month, setMonth] = useState(currentMonth());
    const [data, setData] = useState<PublicAssetCalendarPayload | null>(null);
    const [unitKey, setUnitKey] = useState<string>('unit-1');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        const query = new URLSearchParams({
            branch: branch.code,
            month,
            unit: unitKey.replace('unit-', '') || '1',
        });

        void fetch(
            `/rental/products/${encodeURIComponent(slug)}/asset-calendar?${query.toString()}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(translateKey('public.detail.calendar.failure', locale));
                }

                const payload = (await response.json()) as {
                    data: PublicAssetCalendarPayload;
                };

                if (controller.signal.aborted) {
                    return;
                }

                setData(payload.data);

                if (
                    payload.data.selected_unit?.key &&
                    payload.data.selected_unit.key !== unitKey
                ) {
                    setUnitKey(payload.data.selected_unit.key);
                }
            })
            .catch((reason: unknown) => {
                if (controller.signal.aborted) {
                    return;
                }

                setError(
                    reason instanceof Error
                        ? reason.message
                        : translateKey('public.detail.calendar.failure', locale),
                );
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [branch.code, locale, month, slug, unitKey]);

    const selectedUnit = data?.selected_unit ?? null;

    return (
        <section
            id="smart-calendar"
            className="rounded-[2rem] border border-black/7 bg-white p-5 shadow-sm sm:p-7"
        >
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div className="flex items-center gap-2 text-xs font-semibold tracking-[0.14em] text-neutral-400 uppercase">
                        <CalendarDays className="size-4" /> {tr('public.detail.calendar.eyebrow')}
                    </div>
                    <h2 className="mt-2 text-2xl font-semibold tracking-tight">
                        {tr('public.detail.calendar.title', { name: productName })}
                    </h2>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-neutral-500">
                        {tr('public.detail.calendar.description')}
                    </p>
                </div>
                <div className="w-full max-w-xs">
                    <Select
                        value={unitKey}
                        onValueChange={(value) => {
                            setLoading(true);
                            setError(null);
                            setUnitKey(value);
                        }}
                    >
                        <SelectTrigger className="border-neutral-300 bg-white text-neutral-900">
                            <SelectValue placeholder={tr('public.detail.calendar.choose')} />
                        </SelectTrigger>
                        <SelectContent>
                            {data?.units.map((unit) => (
                                <SelectItem key={unit.key} value={unit.key}>
                                    {unit.label} ·{' '}
                                    {statusLabel(unit.current_status, locale)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="mt-5">
                <CalendarToolbar
                    month={month}
                    appearance="public"
                    locale={locale}
                    onPrevious={() => {
                        setLoading(true);
                        setError(null);
                        setMonth(shiftMonth(month, -1));
                    }}
                    onNext={() => {
                        setLoading(true);
                        setError(null);
                        setMonth(shiftMonth(month, 1));
                    }}
                />
            </div>

            {loading && (
                <div className="flex min-h-72 items-center justify-center text-sm text-neutral-500">
                    <LoaderCircle className="mr-2 size-5 animate-spin" />
                    {tr('public.detail.calendar.load')}
                </div>
            )}

            {!loading && error && (
                <Alert className="mt-5">
                    <AlertTitle>{tr('public.detail.calendar.error')}</AlertTitle>
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {!loading && !error && data?.units.length === 0 && (
                <div className="mt-5 rounded-2xl border border-dashed p-8 text-center text-sm text-neutral-500">
                    {tr('public.detail.calendar.empty')}
                </div>
            )}

            {!loading && selectedUnit && (
                <div className="mt-5 space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant="outline"
                            className="border-neutral-300 bg-white text-neutral-700"
                        >
                            {selectedUnit.label}
                        </Badge>
                        <Badge
                            variant="outline"
                            className="border-emerald-200 bg-emerald-50 text-emerald-700"
                        >
                            {tr('public.detail.calendar.current')}{' '}
                            {statusLabel(selectedUnit.current_status, locale)}
                        </Badge>
                        <Badge
                            variant="outline"
                            className="border-neutral-300 bg-neutral-50 text-neutral-700"
                        >
                            {tr('public.detail.calendar.condition')} {conditionLabel(selectedUnit.condition, locale)}
                        </Badge>
                    </div>
                    <CalendarMonth
                        month={month}
                        events={selectedUnit.events}
                        appearance="public"
                        locale={locale}
                    />
                </div>
            )}

            {data?.privacy_note && (
                <div className="mt-5 flex items-start gap-3 rounded-2xl bg-neutral-50 p-4 text-sm text-neutral-600">
                    <ShieldCheck className="mt-0.5 size-5 shrink-0" />
                    <p className="leading-6">{tr('public.detail.calendar.privacy')}</p>
                </div>
            )}
        </section>
    );
}

function statusLabel(status: string, locale: 'id' | 'en') {
    const keys = {
        reserved: 'public.detail.calendar.reserved',
        rented: 'public.detail.calendar.rented',
        maintenance: 'public.detail.calendar.maintenance',
        lost: 'public.detail.calendar.lost',
    } as const;

    if (status === 'available' || status === 'limited' || status === 'unavailable' || status === 'in_transit') {
        return publicAvailabilityLabel(status, locale);
    }

    const key = keys[status as keyof typeof keys];

    return key ? translateKey(key, locale) : status;
}

/** Known asset conditions are system values; preserve unknown custom codes as supplied. */
function conditionLabel(condition: string, locale: AppLocale): string {
    const keys = {
        excellent: 'public.detail.calendar.condition.excellent',
        good: 'public.detail.calendar.condition.good',
        fair: 'public.detail.calendar.condition.fair',
        poor: 'public.detail.calendar.condition.poor',
        damaged: 'public.detail.calendar.condition.damaged',
        critical: 'public.detail.calendar.condition.critical',
        lost: 'public.detail.calendar.condition.lost',
        retired: 'public.detail.calendar.condition.retired',
    } as const;
    const key = keys[condition.toLowerCase() as keyof typeof keys];

    return key ? translateKey(key, locale) : condition;
}
