<?php

namespace App\Domain\Rentals;

use App\Domain\Catalog\AssetScheduleService;
use App\Domain\Finance\PaymentManager;
use App\Domain\Pricing\RentalPricingEngine;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\RentalItem;
use App\Models\RentalItemAsset;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalExtensionManager
{
    public function __construct(
        private readonly RentalNumberGenerator $numbers,
        private readonly AssetScheduleService $schedule,
        private readonly PaymentManager $payments,
        private readonly RentalPricingEngine $pricing,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     description: string,
     *     product_name: string,
     *     out_quantity: int,
     *     current_due_at: string,
     *     unit_rate: float,
     *     assets: list<array{id: int, asset_code: string, serial_number: string|null}>
     * }>
     */
    public function quoteOptions(Rental $rental): array
    {
        $rental->loadMissing([
            'booking:id,starts_at,ends_at',
            'ratePlan:id,name,duration_unit,duration_value',
            'items.product:id,name',
            'items.assets' => fn ($query) => $query->where('status', 'out'),
            'items.assets.asset:id,asset_code,serial_number',
        ]);

        $originalUnits = $this->originalDurationUnits($rental);

        $options = $rental->items
            ->filter(fn (RentalItem $item): bool => $item->status === 'out' && $item->assets->isNotEmpty())
            ->map(function (RentalItem $item) use ($rental, $originalUnits): array {
                $currentDueAt = $item->due_at ?? $rental->due_at;

                return [
                    'id' => (int) $item->id,
                    'description' => (string) $item->description,
                    'product_name' => (string) ($item->product->name ?: $item->description),
                    'out_quantity' => $item->assets->count(),
                    'current_due_at' => CarbonImmutable::parse((string) $currentDueAt)->toIso8601String(),
                    'unit_rate' => round((float) $item->unit_rate / $originalUnits, 2),
                    'assets' => array_values($item->assets->map(fn (RentalItemAsset $assignment): array => [
                        'id' => (int) $assignment->asset_id,
                        'asset_code' => (string) $assignment->asset->asset_code,
                        'serial_number' => is_string($assignment->asset->serial_number)
                            ? $assignment->asset->serial_number
                            : null,
                    ])->all()),
                ];
            })
            ->all();

        return array_values($options);
    }

    /** @param array<string, mixed> $data */
    public function extend(Rental $rental, array $data, User $actor): RentalExtension
    {
        return DB::transaction(function () use ($rental, $data, $actor): RentalExtension {
            $locked = Rental::query()
                ->with([
                    'branch',
                    'booking:id,starts_at,ends_at',
                    'ratePlan:id,code,name,duration_unit,duration_value',
                    'customer:id,is_member,member_since',
                ])
                ->lockForUpdate()
                ->findOrFail($rental->id);

            if (! in_array($locked->status, ['active', 'partial_return'], true)) {
                throw ValidationException::withMessages([
                    'rental' => 'Perpanjangan hanya dapat dilakukan pada rental aktif atau partial return.',
                ]);
            }

            if ($actor->company_id !== $locked->branch->company_id) {
                abort(404);
            }
            if ($actor->current_branch_id !== $locked->branch_id && ! $actor->hasCompanyScopedRole()) {
                abort(403, 'Aktifkan cabang rental sebelum memproses perpanjangan.');
            }

            if ($locked->ratePlan === null) {
                throw ValidationException::withMessages([
                    'rental' => 'Rental tidak memiliki rate plan yang dapat digunakan untuk menghitung perpanjangan.',
                ]);
            }
            if ($locked->customer === null) {
                throw ValidationException::withMessages([
                    'rental' => 'Pelanggan rental tidak tersedia untuk kalkulasi harga.',
                ]);
            }

            $promotion = $this->pricing->resolvePromotion(
                $data['promotion_code'] ?? null,
                $locked->branch,
                $locked->customer,
            );
            $durationUnits = max(1, (int) ($data['duration_units'] ?? 1));
            $effectiveDurationUnits = $durationUnits + $this->pricing->bonusDurationUnits($promotion);
            $rawSelectedIds = $data['item_ids'] ?? [];
            if (! is_array($rawSelectedIds)) {
                $rawSelectedIds = [];
            }

            /** @var list<int> $selectedIds */
            $selectedIds = array_values(array_unique(array_filter(
                array_map(static fn (mixed $id): int => (int) $id, $rawSelectedIds),
                static fn (int $id): bool => $id > 0,
            )));

            $items = RentalItem::query()
                ->where('rental_id', $locked->id)
                ->whereIn('id', $selectedIds)
                ->with([
                    'product:id,name',
                    'assets' => fn ($query) => $query->where('status', 'out'),
                    'assets.asset:id,asset_code,serial_number',
                ])
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count($selectedIds) || $items->isEmpty()) {
                throw ValidationException::withMessages([
                    'item_ids' => 'Pilih minimal satu item rental yang masih berada pada pelanggan.',
                ]);
            }

            $originalUnits = $this->originalDurationUnits($locked);
            /** @var list<array{
             *     item: RentalItem,
             *     quantity: int,
             *     previous_due_at: CarbonImmutable,
             *     extended_due_at: CarbonImmutable,
             *     unit_rate: float,
             *     total_amount: float
             * }> $lines
             */
            $lines = [];
            $subtotal = 0.0;
            $extensionDueAt = null;

            foreach ($items as $item) {
                if ($item->status !== 'out' || $item->assets->count() !== (int) $item->quantity) {
                    throw ValidationException::withMessages([
                        'item_ids' => "Item {$item->description} sudah dikembalikan sebagian/seluruhnya dan tidak dapat diperpanjang sebagai satu baris. Pilih item lain yang seluruh unitnya masih keluar.",
                    ]);
                }

                $previousDueAt = CarbonImmutable::parse((string) ($item->due_at ?? $locked->due_at));
                $extendedDueAt = $this->addDuration($previousDueAt, $locked, $effectiveDurationUnits);
                $this->assertNoScheduleConflict($locked, $item, $previousDueAt, $extendedDueAt);

                $unitRate = round((float) $item->unit_rate / $originalUnits, 2);
                $quantity = $item->assets->count();
                $lineTotal = round($unitRate * $durationUnits * $quantity, 2);
                $subtotal += $lineTotal;
                if ($extensionDueAt === null || $extendedDueAt->isAfter($extensionDueAt)) {
                    $extensionDueAt = $extendedDueAt;
                }
                $lines[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'previous_due_at' => $previousDueAt,
                    'extended_due_at' => $extendedDueAt,
                    'unit_rate' => $unitRate,
                    'total_amount' => $lineTotal,
                ];
            }

            $pricing = $this->pricing->price(
                $subtotal,
                $durationUnits,
                $locked->customer,
                $locked->ratePlan,
                $promotion,
            );
            $paymentAmount = (float) ($data['payment_amount'] ?? 0);
            if ($paymentAmount > $pricing['total_amount'] + 0.009) {
                throw ValidationException::withMessages([
                    'payment_amount' => 'Pembayaran perpanjangan tidak boleh melebihi total biaya perpanjangan.',
                ]);
            }

            if (! $extensionDueAt instanceof CarbonImmutable) {
                throw ValidationException::withMessages([
                    'item_ids' => 'Tidak ada item rental yang dapat diperpanjang.',
                ]);
            }

            $extension = RentalExtension::query()->create([
                'branch_id' => $locked->branch_id,
                'rental_id' => $locked->id,
                'promotion_id' => $promotion?->id,
                'extension_number' => $this->numbers->nextExtension($locked->branch),
                'previous_due_at' => $locked->due_at,
                'extended_due_at' => $extensionDueAt,
                'status' => 'approved',
                'subtotal' => $pricing['subtotal'],
                'discount_amount' => $pricing['discount_amount'],
                'total_amount' => $pricing['total_amount'],
                'paid_amount' => $paymentAmount,
                'pricing_snapshot' => $pricing['snapshot'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            foreach ($lines as $line) {
                /** @var RentalItem $item */
                $item = $line['item'];
                $extension->items()->create([
                    'rental_item_id' => $item->id,
                    'quantity' => $line['quantity'],
                    'previous_due_at' => $line['previous_due_at'],
                    'extended_due_at' => $line['extended_due_at'],
                    'unit_rate' => $line['unit_rate'],
                    'additional_amount' => 0,
                    'total_amount' => $line['total_amount'],
                ]);
                $item->update(['due_at' => $line['extended_due_at']]);
            }

            if ($paymentAmount > 0) {
                $this->payments->record($locked->branch, [
                    'customer_id' => $locked->customer_id,
                    'booking_id' => $locked->booking_id,
                    'rental_id' => $locked->id,
                    'rental_extension_id' => $extension->id,
                    'payment_method_id' => $data['payment_method_id'] ?? null,
                    'financial_category_code' => 'RENTAL',
                    'cash_session_id' => $data['cash_session_id'] ?? null,
                    'direction' => 'in',
                    'type' => 'rental',
                    'source_context' => 'rental_extension',
                    'amount' => $paymentAmount,
                    'paid_at' => now(),
                    'external_reference' => $data['payment_reference'] ?? null,
                    'notes' => $data['payment_notes']
                        ?? "Pembayaran perpanjangan {$extension->extension_number}.",
                ], $actor);
            }

            $nextDueAt = RentalItem::query()
                ->where('rental_id', $locked->id)
                ->whereHas('assets', fn ($query) => $query->where('status', 'out'))
                ->min('due_at');

            $locked->update([
                'due_at' => $nextDueAt ?? $locked->due_at,
                'subtotal' => (float) $locked->subtotal + $pricing['subtotal'],
                'discount_amount' => (float) $locked->discount_amount + $pricing['discount_amount'],
                'total_amount' => (float) $locked->total_amount + $pricing['total_amount'],
                'paid_amount' => (float) $locked->paid_amount + $paymentAmount,
                'balance_due' => (float) $locked->balance_due + $pricing['total_amount'] - $paymentAmount,
                'updated_by' => $actor->id,
            ]);

            return $extension->fresh([
                'items.rentalItem.product',
                'payments.paymentMethod',
                'creator:id,name',
                'approver:id,name',
                'promotion:id,code,name,type',
            ]);
        }, 3);
    }

    private function assertNoScheduleConflict(
        Rental $rental,
        RentalItem $item,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): void {
        foreach ($item->assets as $assignment) {
            $calendar = $this->schedule->calendar(
                $assignment->asset,
                $startsAt,
                $endsAt,
                false,
            );
            $conflict = collect($calendar['events'])->first(
                fn (array $event): bool => ! (
                    $event['type'] === 'rental'
                    && ($event['href'] ?? null) === "/rentals/{$rental->id}"
                ),
            );

            if ($conflict !== null) {
                throw ValidationException::withMessages([
                    'item_ids' => sprintf(
                        'Unit %s tidak dapat diperpanjang sampai %s karena bentrok dengan %s.',
                        $assignment->asset->asset_code,
                        $endsAt->format('d/m/Y H:i'),
                        (string) ($conflict['label'] ?? 'jadwal operasional lain'),
                    ),
                ]);
            }
        }
    }

    private function originalDurationUnits(Rental $rental): int
    {
        if ($rental->booking === null || $rental->ratePlan === null) {
            return 1;
        }

        $startsAt = CarbonImmutable::parse((string) $rental->booking->starts_at);
        $endsAt = CarbonImmutable::parse((string) $rental->booking->ends_at);
        $planMinutes = $this->planMinutes($rental);
        $durationMinutes = max(1, (int) round($startsAt->diffInMinutes($endsAt)));

        return max(1, (int) round($durationMinutes / $planMinutes));
    }

    private function addDuration(
        CarbonImmutable $dueAt,
        Rental $rental,
        int $durationUnits,
    ): CarbonImmutable {
        return $dueAt->addMinutes($this->planMinutes($rental) * $durationUnits);
    }

    private function planMinutes(Rental $rental): int
    {
        $plan = $rental->ratePlan;
        if ($plan === null) {
            return 1440;
        }

        $unitMinutes = match ($plan->duration_unit) {
            'hour' => 60,
            'week' => 10_080,
            'month' => 43_200,
            default => 1_440,
        };

        return max(1, (int) $plan->duration_value * $unitMinutes);
    }
}
