<?php

namespace App\Domain\Rentals;

use App\Models\CustomerIdentity;
use App\Models\Rental;
use App\Models\RentalCollateral;
use App\Models\User;
use Illuminate\Support\Carbon;
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

            $receivedAt = now();
            $prepared = $this->prepareActiveCollateralInput(
                $locked,
                $data,
                $actor,
                $receivedAt,
            );

            return $this->createLocked($locked, $prepared, $actor, $receivedAt);
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareActiveCollateralInput(
        Rental $rental,
        array $data,
        User $actor,
        mixed $receivedAt,
    ): array {
        $mode = (string) ($data['source_mode'] ?? '');

        if ($mode === '') {
            $mode = ((int) ($data['customer_identity_id'] ?? 0)) > 0
                ? 'existing'
                : 'manual';
        }

        if ($mode === 'existing') {
            $data['customer_identity_id'] = (int) ($data['customer_identity_id'] ?? 0);

            return $data;
        }

        if ($mode !== 'new') {
            $data['customer_identity_id'] = null;

            return $data;
        }

        $identityType = mb_strtolower(trim((string) ($data['identity_type'] ?? '')));
        $identityNumber = mb_strtoupper(trim((string) ($data['identity_number'] ?? '')));
        $identityName = $this->nullableString($data['identity_name_on_identity'] ?? null);
        $expiresAt = $this->nullableString($data['identity_expires_at'] ?? null);
        $saveToCustomer360 = (bool) ($data['save_to_customer360'] ?? false);

        if ($identityType === '' || $identityNumber === '') {
            throw ValidationException::withMessages([
                'identity_number' => 'Jenis dan nomor identitas baru wajib diisi.',
            ]);
        }

        if ($saveToCustomer360) {
            if (! $actor->can('customers.update')) {
                throw ValidationException::withMessages([
                    'save_to_customer360' => 'Akun ini tidak memiliki izin untuk menyimpan identitas ke Customer360.',
                ]);
            }

            $identity = $this->createCustomerIdentityLocked(
                $rental,
                [
                    'type' => $identityType,
                    'number' => $identityNumber,
                    'name_on_identity' => $identityName,
                    'expires_at' => $expiresAt,
                    'is_primary' => (bool) ($data['identity_is_primary'] ?? false),
                ],
                $receivedAt,
            );

            $data['customer_identity_id'] = $identity->id;

            return $data;
        }

        $transientIdentity = new CustomerIdentity([
            'type' => $identityType,
            'number' => $identityNumber,
            'name_on_identity' => $identityName,
            'expires_at' => $expiresAt,
        ]);

        $data['customer_identity_id'] = null;
        $data['type'] = $transientIdentity->collateralType();
        $data['number'] = $identityNumber;
        $data['holder_name'] = $identityName;

        return $data;
    }

    /**
     * @param array{type:string, number:string, name_on_identity:?string, expires_at:?string, is_primary:bool} $data
     */
    private function createCustomerIdentityLocked(
        Rental $rental,
        array $data,
        mixed $receivedAt,
    ): CustomerIdentity {
        $customer = $rental->customer()->lockForUpdate()->firstOrFail();

        $duplicate = CustomerIdentity::query()
            ->where('type', $data['type'])
            ->where('number', $data['number'])
            ->whereHas(
                'customer',
                fn ($query) => $query->where('company_id', $customer->company_id),
            )
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'identity_number' => 'Nomor identitas tersebut sudah terdaftar. Pilih identitas Customer360 yang sudah ada.',
            ]);
        }

        if (
            $data['expires_at'] !== null
            && Carbon::parse($data['expires_at'])->startOfDay()->lt(
                Carbon::parse((string) $receivedAt)->startOfDay(),
            )
        ) {
            throw ValidationException::withMessages([
                'identity_expires_at' => 'Identitas yang sudah kedaluwarsa tidak dapat diterima sebagai jaminan.',
            ]);
        }

        $hasIdentity = $customer->identities()->exists();
        $isPrimary = $data['is_primary'] || ! $hasIdentity;

        if ($isPrimary) {
            $customer->identities()->update(['is_primary' => false]);
        }

        return $customer->identities()->create([
            'type' => $data['type'],
            'number' => $data['number'],
            'name_on_identity' => $data['name_on_identity'],
            'expires_at' => $data['expires_at'],
            'is_primary' => $isPrimary,
            'verified_at' => null,
            'verified_by' => null,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function createLocked(
        Rental $rental,
        array $data,
        User $actor,
        mixed $receivedAt,
    ): RentalCollateral {
        $identity = null;
        $identityId = (int) ($data['customer_identity_id'] ?? 0);
        if ($identityId > 0) {
            $identity = CustomerIdentity::query()
                ->where('customer_id', $rental->customer_id)
                ->find($identityId);

            if ($identity === null) {
                throw ValidationException::withMessages([
                    'collaterals' => 'Identitas Customer360 tidak milik pelanggan rental ini.',
                ]);
            }

            if ($identity->isExpiredAt($receivedAt ?? now())) {
                throw ValidationException::withMessages([
                    'collaterals' => 'Identitas Customer360 sudah kedaluwarsa pada waktu penerimaan.',
                ]);
            }
        }

        $type = $identity?->collateralType() ?? trim((string) ($data['type'] ?? ''));
        $number = $identity?->number ?? trim((string) ($data['number'] ?? ''));
        $holderName = $identity?->name_on_identity
            ?? $this->nullableString($data['holder_name'] ?? null);

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
            'customer_identity_id' => $identity?->id,
            'source_type' => $identity === null ? 'manual' : 'customer_identity',
            'type' => $type,
            'number' => $number,
            'holder_name' => $holderName,
            'identity_snapshot' => $identity?->collateralSnapshot(),
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
