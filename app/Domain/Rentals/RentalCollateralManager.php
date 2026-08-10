<?php

namespace App\Domain\Rentals;

use App\Models\Rental;
use App\Models\RentalCollateral;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RentalCollateralManager
{
    /**
     * @param  list<array<string, mixed>>  $inputs
     * @return Collection<int, RentalCollateral>
     */
    public function receiveManyLocked(
        Rental $rental,
        array $inputs,
        User $actor,
        mixed $receivedAt = null,
    ): Collection {
        /** @var Collection<int, RentalCollateral> $received */
        $received = new Collection;

        foreach ($inputs as $input) {
            $received->push($this->createLocked($rental, $input, $actor, $receivedAt));
        }

        return $received;
    }

    /** @param array<string, mixed> $data */
    public function receive(Rental $rental, array $data, User $actor): RentalCollateral
    {
        return DB::transaction(function () use ($rental, $data, $actor): RentalCollateral {
            $locked = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! in_array($locked->status, ['active', 'partial_return'], true)) {
                throw new ConflictHttpException(
                    'Jaminan baru hanya dapat diterima untuk rental yang masih aktif.',
                );
            }

            return $this->createLocked($locked, $data, $actor, now());
        }, 3);
    }

    /**
     * @param  list<int>  $collateralIds
     * @return Collection<int, RentalCollateral>
     */
    public function returnSelectedLocked(
        Rental $rental,
        array $collateralIds,
        User $actor,
        mixed $returnedAt,
        bool $requireAllHeld,
    ): Collection {
        $ids = array_values(array_unique(array_map(
            static fn (int $id): int => $id,
            $collateralIds,
        )));

        $held = RentalCollateral::query()
            ->where('rental_id', $rental->id)
            ->where('status', 'held')
            ->lockForUpdate()
            ->get();

        $selected = $held->whereIn('id', $ids);

        if ($selected->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'returned_collateral_ids' => 'Ada jaminan yang tidak termasuk rental ini atau sudah dikembalikan.',
            ]);
        }

        if ($requireAllHeld && $selected->count() !== $held->count()) {
            throw ValidationException::withMessages([
                'returned_collateral_ids' => 'Pengembalian final wajib mengonfirmasi seluruh jaminan fisik yang masih ditahan.',
            ]);
        }

        foreach ($selected as $collateral) {
            $collateral->forceFill([
                'status' => 'returned',
                'returned_at' => $returnedAt,
                'returned_by' => $actor->id,
            ])->saveOrFail();
        }

        return $selected->values();
    }

    public function returnOne(
        Rental $rental,
        RentalCollateral $collateral,
        User $actor,
        mixed $returnedAt = null,
    ): RentalCollateral {
        return DB::transaction(function () use (
            $rental,
            $collateral,
            $actor,
            $returnedAt,
        ): RentalCollateral {
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);
            $lockedCollateral = RentalCollateral::query()
                ->where('rental_id', $lockedRental->id)
                ->lockForUpdate()
                ->findOrFail($collateral->id);

            if ($lockedCollateral->status !== 'held') {
                throw new ConflictHttpException('Jaminan ini sudah dikembalikan.');
            }

            $lockedCollateral->forceFill([
                'status' => 'returned',
                'returned_at' => $returnedAt ?? now(),
                'returned_by' => $actor->id,
            ])->saveOrFail();

            return $lockedCollateral->fresh(['receiver:id,name', 'returner:id,name']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function createLocked(
        Rental $rental,
        array $data,
        User $actor,
        mixed $receivedAt,
    ): RentalCollateral {
        $type = trim((string) ($data['type'] ?? ''));
        $number = trim((string) ($data['number'] ?? ''));

        if ($type === '' || $number === '') {
            throw ValidationException::withMessages([
                'collaterals' => 'Jenis dan nomor jaminan wajib diisi.',
            ]);
        }

        $duplicate = RentalCollateral::query()
            ->where('rental_id', $rental->id)
            ->where('status', 'held')
            ->where('type', $type)
            ->where('number', $number)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'collaterals' => 'Jaminan dengan jenis dan nomor yang sama masih ditahan pada rental ini.',
            ]);
        }

        return RentalCollateral::query()->create([
            'rental_id' => $rental->id,
            'customer_id' => $rental->customer_id,
            'type' => $type,
            'number' => $number,
            'holder_name' => $this->nullableString($data['holder_name'] ?? null),
            'status' => 'held',
            'received_at' => $receivedAt ?? now(),
            'received_by' => $actor->id,
            'document_path' => $this->nullableString($data['document_path'] ?? null),
            'document_original_name' => $this->nullableString($data['document_original_name'] ?? null),
            'document_mime_type' => $this->nullableString($data['document_mime_type'] ?? null),
            'document_size' => isset($data['document_size']) ? (int) $data['document_size'] : null,
            'notes' => $this->nullableString($data['notes'] ?? null),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
