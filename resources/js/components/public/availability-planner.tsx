import {
    AlertCircle,
    CalendarClock,
    CheckCircle2,
    Clock3,
    LoaderCircle,
    MessageCircle,
    PackageSearch,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ChangeEvent } from 'react';
import type {
    PublicAvailability,
    PublicAvailabilityResult,
    PublicBranch,
    PublicRate,
} from '@/types';

const currency = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function localDateTimeValue(date: Date): string {
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60_000);

    return local.toISOString().slice(0, 16);
}

function defaultPeriod(durationMinutes: number): {
    startsAt: string;
    endsAt: string;
} {
    const startsAt = new Date();
    startsAt.setDate(startsAt.getDate() + 1);
    startsAt.setHours(9, 0, 0, 0);

    const endsAt = new Date(startsAt.getTime() + durationMinutes * 60_000);

    return {
        startsAt: localDateTimeValue(startsAt),
        endsAt: localDateTimeValue(endsAt),
    };
}

type Props = {
    branch: PublicBranch;
    itemType: 'product' | 'package';
    slug: string;
    itemName: string;
    rates: PublicRate[];
    currentAvailability: PublicAvailability;
    fallbackInquiryUrl?: string | null;
};

type LaravelErrorResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

export default function AvailabilityPlanner({
    branch,
    itemType,
    slug,
    itemName,
    rates,
    currentAvailability,
    fallbackInquiryUrl,
}: Props) {
    const initialPeriod = useMemo(
        () => defaultPeriod(rates[0]?.duration_minutes ?? 1440),
        [rates],
    );
    const [startsAt, setStartsAt] = useState(initialPeriod.startsAt);
    const [endsAt, setEndsAt] = useState(initialPeriod.endsAt);
    const [quantity, setQuantity] = useState(1);
    const [rateId, setRateId] = useState<number | null>(rates[0]?.id ?? null);
    const [result, setResult] = useState<PublicAvailabilityResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const checkAvailability = async () => {
        setLoading(true);
        setError(null);
        setResult(null);

        const parameters = new URLSearchParams({
            branch: branch.code,
            type: itemType,
            slug,
            starts_at: startsAt,
            ends_at: endsAt,
            quantity: String(quantity),
        });

        if (rateId !== null) {
            parameters.set('rate_id', String(rateId));
        }

        try {
            const response = await fetch(
                `/rental/availability?${parameters.toString()}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );
            const payload = (await response.json()) as
                { data: PublicAvailabilityResult } | LaravelErrorResponse;

            if (!response.ok) {
                const errorPayload = payload as LaravelErrorResponse;
                const firstError = Object.values(
                    errorPayload.errors ?? {},
                ).flat()[0];

                throw new Error(
                    firstError ??
                        errorPayload.message ??
                        'Ketersediaan belum dapat diperiksa.',
                );
            }

            if (!('data' in payload)) {
                throw new Error('Respons ketersediaan tidak valid.');
            }

            setResult(payload.data);
        } catch (requestError) {
            setError(
                requestError instanceof Error
                    ? requestError.message
                    : 'Ketersediaan belum dapat diperiksa.',
            );
        } finally {
            setLoading(false);
        }
    };

    const statusClass = result
        ? result.availability.status === 'available'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
            : result.availability.status === 'limited'
              ? 'border-amber-200 bg-amber-50 text-amber-900'
              : 'border-rose-200 bg-rose-50 text-rose-900'
        : 'border-black/7 bg-[#f7f7f3] text-neutral-700';

    return (
        <section className="mt-8 overflow-hidden rounded-[1.75rem] border border-black/7 bg-white shadow-sm">
            <div className="border-b border-black/5 px-5 py-5 sm:px-6">
                <div className="flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-neutral-950 text-white">
                        <CalendarClock className="size-5" />
                    </div>
                    <div>
                        <p className="font-semibold">
                            Cek ketersediaan berdasarkan tanggal
                        </p>
                        <p className="mt-1 text-sm leading-6 text-neutral-500">
                            Sistem memeriksa booking dan rental aktif di{' '}
                            {branch.name} sebelum Anda menghubungi admin.
                        </p>
                    </div>
                </div>
            </div>

            <div className="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                <label className="grid gap-2 text-sm font-medium">
                    Mulai rental
                    <input
                        type="datetime-local"
                        value={startsAt}
                        onChange={(event: ChangeEvent<HTMLInputElement>) =>
                            setStartsAt(event.target.value)
                        }
                        className="h-11 min-w-0 rounded-xl border border-black/10 bg-[#fafaf8] px-3 text-sm transition outline-none focus:border-black/30"
                    />
                </label>
                <label className="grid gap-2 text-sm font-medium">
                    Selesai rental
                    <input
                        type="datetime-local"
                        value={endsAt}
                        onChange={(event: ChangeEvent<HTMLInputElement>) =>
                            setEndsAt(event.target.value)
                        }
                        className="h-11 min-w-0 rounded-xl border border-black/10 bg-[#fafaf8] px-3 text-sm transition outline-none focus:border-black/30"
                    />
                </label>
                <label className="grid gap-2 text-sm font-medium">
                    Jumlah
                    <select
                        value={quantity}
                        onChange={(event: ChangeEvent<HTMLSelectElement>) =>
                            setQuantity(Number(event.target.value))
                        }
                        className="h-11 rounded-xl border border-black/10 bg-[#fafaf8] px-3 text-sm transition outline-none focus:border-black/30"
                    >
                        {Array.from(
                            { length: 10 },
                            (_, index) => index + 1,
                        ).map((value) => (
                            <option key={value} value={value}>
                                {value}{' '}
                                {itemType === 'package' ? 'paket' : 'unit'}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="grid gap-2 text-sm font-medium">
                    Pilihan tarif
                    <select
                        value={rateId ?? ''}
                        onChange={(event: ChangeEvent<HTMLSelectElement>) =>
                            setRateId(
                                event.target.value === ''
                                    ? null
                                    : Number(event.target.value),
                            )
                        }
                        className="h-11 rounded-xl border border-black/10 bg-[#fafaf8] px-3 text-sm transition outline-none focus:border-black/30"
                    >
                        {rates.length === 0 && (
                            <option value="">Hubungi admin</option>
                        )}
                        {rates.map((rate) => (
                            <option key={rate.id} value={rate.id}>
                                {rate.duration_label} ·{' '}
                                {currency.format(rate.amount)}
                            </option>
                        ))}
                    </select>
                </label>

                <button
                    type="button"
                    onClick={checkAvailability}
                    disabled={loading || startsAt === '' || endsAt === ''}
                    className="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-neutral-950 px-6 text-sm font-semibold text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50 sm:col-span-2"
                >
                    {loading ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <PackageSearch className="size-4" />
                    )}
                    {loading ? 'Memeriksa jadwal...' : `Cek ${itemName}`}
                </button>
            </div>

            {error && (
                <div className="mx-5 mb-5 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 sm:mx-6 sm:mb-6">
                    <AlertCircle className="mt-0.5 size-5 shrink-0" />
                    <p>{error}</p>
                </div>
            )}

            <div
                className={`mx-5 mb-5 rounded-2xl border p-4 sm:mx-6 sm:mb-6 ${statusClass}`}
            >
                {result ? (
                    <div>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex items-start gap-3">
                                {result.availability.status ===
                                'unavailable' ? (
                                    <AlertCircle className="mt-0.5 size-5 shrink-0" />
                                ) : (
                                    <CheckCircle2 className="mt-0.5 size-5 shrink-0" />
                                )}
                                <div>
                                    <p className="font-semibold">
                                        {result.availability.label}
                                    </p>
                                    <p className="mt-1 text-sm opacity-75">
                                        {result.period.starts_label} –{' '}
                                        {result.period.ends_label} WIB ·{' '}
                                        {result.period.duration_label} ·{' '}
                                        {result.period.timezone_label}
                                    </p>
                                </div>
                            </div>
                            <div className="rounded-xl bg-white/65 px-4 py-3 text-left sm:text-right">
                                <p className="text-xs opacity-65">
                                    Kapasitas periode
                                </p>
                                <p className="mt-1 font-semibold">
                                    {result.availability.available_units} dari{' '}
                                    {result.availability.total_units} tersedia
                                </p>
                            </div>
                        </div>

                        {result.estimate && (
                            <div className="mt-4 grid gap-3 border-t border-current/10 pt-4 sm:grid-cols-3">
                                <div>
                                    <p className="text-xs opacity-65">
                                        Estimasi rental
                                    </p>
                                    <p className="mt-1 font-semibold">
                                        {currency.format(
                                            result.estimate.rental_amount,
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs opacity-65">
                                        Estimasi deposit
                                    </p>
                                    <p className="mt-1 font-semibold">
                                        {currency.format(
                                            result.estimate.deposit_amount,
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs opacity-65">
                                        Siklus tarif
                                    </p>
                                    <p className="mt-1 font-semibold">
                                        {result.estimate.billing_units} ×{' '}
                                        {result.rate?.duration_label ?? 'tarif'}
                                    </p>
                                </div>
                            </div>
                        )}

                        {result.items.length > 0 && (
                            <div className="mt-4 border-t border-current/10 pt-4">
                                <p className="text-xs font-semibold tracking-wide uppercase opacity-65">
                                    Kesiapan isi paket
                                </p>
                                <div className="mt-3 grid gap-2">
                                    {result.items.map((item) => (
                                        <div
                                            key={`${item.slug}-${item.quantity_per_package}`}
                                            className="flex items-center justify-between gap-4 rounded-xl bg-white/65 px-3 py-2.5 text-sm"
                                        >
                                            <span className="min-w-0 truncate">
                                                {item.name} ·{' '}
                                                {item.quantity_per_package}×
                                                {item.is_optional
                                                    ? ' · opsional'
                                                    : ''}
                                            </span>
                                            <span className="shrink-0 font-semibold">
                                                {item.available_units} tersedia
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="mt-5 flex flex-col gap-3 border-t border-current/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="flex items-start gap-2 text-xs leading-5 opacity-70">
                                <Clock3 className="mt-0.5 size-3.5 shrink-0" />
                                Hasil merupakan estimasi real-time. Admin tetap
                                melakukan konfirmasi final sebelum booking.
                            </p>
                            {result.inquiry_url && (
                                <a
                                    href={result.inquiry_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-full bg-neutral-950 px-5 text-sm font-semibold text-white transition hover:bg-neutral-800"
                                >
                                    <MessageCircle className="size-4" /> Kirim
                                    inquiry terstruktur
                                </a>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="flex items-start gap-3">
                        <Clock3 className="mt-0.5 size-5 shrink-0" />
                        <div>
                            <p className="font-semibold">
                                Kondisi saat ini: {currentAvailability.label}
                            </p>
                            <p className="mt-1 text-sm opacity-70">
                                Pilih periode untuk memperoleh hasil yang
                                mempertimbangkan bentrok jadwal.
                            </p>
                            {fallbackInquiryUrl && (
                                <a
                                    href={fallbackInquiryUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-3 inline-flex items-center gap-2 text-sm font-semibold underline underline-offset-4"
                                >
                                    <MessageCircle className="size-4" /> Tanya
                                    admin tanpa memilih periode
                                </a>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}
