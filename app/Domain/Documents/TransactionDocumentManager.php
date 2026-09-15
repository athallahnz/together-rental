<?php

namespace App\Domain\Documents;

use App\Domain\Rentals\RentalNumberGenerator;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalCollateral;
use App\Models\RentalExtension;
use App\Models\RentalItem;
use App\Models\RentalItemAsset;
use App\Models\TransactionDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionDocumentManager
{
    public function __construct(private readonly RentalNumberGenerator $numbers) {}

    public function issue(
        string $documentType,
        string $sourceType,
        string $sourceReference,
        User $actor,
    ): TransactionDocument {
        return DB::transaction(function () use (
            $documentType,
            $sourceType,
            $sourceReference,
            $actor,
        ): TransactionDocument {
            $reference = mb_strtoupper(trim($sourceReference));
            [$sourceId, $branch, $snapshot] = match ($sourceType) {
                'booking' => $this->bookingSnapshot($reference, $actor),
                'rental' => $this->rentalSnapshot($reference, $actor),
                'payment' => $this->paymentSnapshot($reference, $actor),
                default => throw ValidationException::withMessages([
                    'source_type' => 'Jenis sumber dokumen tidak dikenali.',
                ]),
            };

            $this->assertCombination($documentType, $sourceType, $snapshot);
            $snapshot['document'] = [
                'type' => $documentType,
                'source_type' => $sourceType,
                'schema_version' => 1,
            ];
            $encoded = json_encode(
                $snapshot,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            $hash = hash('sha256', $encoded);

            $latest = TransactionDocument::query()
                ->where('document_type', $documentType)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->latest('version')
                ->first();

            if ($latest !== null && hash_equals((string) $latest->content_hash, $hash)) {
                return $latest;
            }

            $version = $latest === null ? 1 : ((int) $latest->version) + 1;

            return TransactionDocument::query()->create([
                'branch_id' => $branch->id,
                'document_number' => $this->numbers->nextTransactionDocument($branch, $documentType),
                'document_type' => $documentType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_reference' => (string) ($snapshot['source']['reference'] ?? $reference),
                'version' => $version,
                'content_hash' => $hash,
                'snapshot' => $snapshot,
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);
        }, 3);
    }

    /**
     * @return array{0: int, 1: Branch, 2: array<string, mixed>}
     */
    private function bookingSnapshot(string $reference, User $actor): array
    {
        $booking = Booking::query()
            ->whereIn('branch_id', $this->allowedBranchIds($actor))
            ->where('booking_number', $reference)
            ->with([
                'branch:id,company_id,code,name,phone,email,address,city,province,postal_code',
                'customer:id,customer_number,name,phone,email,institution,is_member,member_number',
                'customer.primaryAddress:id,customer_id,address,village,district,city,province,postal_code',
                'ratePlan:id,code,name,duration_unit,duration_value',
                'promotion:id,code,name,type,value,bonus_duration',
                'items:id,booking_id,product_id,package_id,description,quantity,unit_rate,additional_amount,discount_amount,total_amount',
                'items.product:id,sku,name',
                'items.package:id,code,name',
                'payments:id,booking_id,payment_method_id,payment_number,type,direction,status,amount,paid_at,external_reference',
                'payments.paymentMethod:id,code,name,type',
            ])
            ->first();

        if ($booking === null) {
            throw ValidationException::withMessages([
                'source_reference' => 'Booking tidak ditemukan dalam cakupan cabang Anda.',
            ]);
        }
        $this->guardBranch($booking->branch, $actor);

        $rentalPaid = (float) $booking->payments
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->where('type', 'rental')
            ->sum('amount');
        $depositPaid = (float) $booking->payments
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->where('type', 'deposit')
            ->sum('amount');

        return [
            (int) $booking->id,
            $booking->branch,
            [
                'source' => [
                    'reference' => $booking->booking_number,
                    'status' => $booking->status,
                    'booked_at' => $this->isoDateTime($booking->booked_at),
                    'starts_at' => $this->isoDateTime($booking->starts_at),
                    'ends_at' => $this->isoDateTime($booking->ends_at),
                ],
                'branch' => $this->branchData($booking->branch),
                'customer' => $this->customerData($booking->customer),
                'rate_plan' => $booking->ratePlan?->only(['code', 'name', 'duration_unit', 'duration_value']),
                'promotion' => $booking->promotion?->only(['code', 'name', 'type', 'value', 'bonus_duration']),
                'items' => $booking->items->map(static fn (BookingItem $item): array => [
                    'description' => $item->description,
                    'sku' => $item->product?->sku,
                    'package_code' => $item->package?->code,
                    'quantity' => (int) $item->quantity,
                    'unit_rate' => (float) $item->unit_rate,
                    'additional_amount' => (float) $item->additional_amount,
                    'discount_amount' => (float) $item->discount_amount,
                    'total_amount' => (float) $item->total_amount,
                ])->values()->all(),
                'financial' => [
                    'subtotal' => (float) $booking->subtotal,
                    'discount_amount' => (float) $booking->discount_amount,
                    'tax_amount' => (float) $booking->tax_amount,
                    'total_amount' => (float) $booking->total_amount,
                    'rental_paid' => $rentalPaid,
                    'balance_due' => max(0, (float) $booking->total_amount - $rentalPaid),
                    'deposit_required' => (float) $booking->deposit_required,
                    'deposit_paid' => $depositPaid,
                ],
                'pricing_snapshot' => $booking->pricing_snapshot,
                'payments' => $this->paymentLines($booking->payments),
                'notes' => $booking->notes,
            ],
        ];
    }

    /**
     * @return array{0: int, 1: Branch, 2: array<string, mixed>}
     */
    private function rentalSnapshot(string $reference, User $actor): array
    {
        $rental = Rental::query()
            ->whereIn('branch_id', $this->allowedBranchIds($actor))
            ->where('rental_number', $reference)
            ->with([
                'branch:id,company_id,code,name,phone,email,address,city,province,postal_code',
                'customer:id,customer_number,name,phone,email,institution,is_member,member_number',
                'customer.primaryAddress:id,customer_id,address,village,district,city,province,postal_code',
                'booking:id,booking_number,source',
                'ratePlan:id,code,name,duration_unit,duration_value',
                'promotion:id,code,name,type,value,bonus_duration',
                'items:id,rental_id,product_id,description,quantity,returned_quantity,unit_rate,additional_amount,discount_amount,total_amount,due_at,status',
                'items.product:id,sku,name',
                'items.assets:id,rental_item_id,asset_id,checkout_condition,return_condition,status',
                'items.assets.asset:id,asset_code,serial_number',
                'payments:id,rental_id,payment_method_id,payment_number,type,direction,status,amount,paid_at,external_reference',
                'payments.paymentMethod:id,code,name,type',
                'extensions:id,rental_id,extension_number,status,previous_due_at,extended_due_at,subtotal,discount_amount,total_amount,paid_amount,approved_at',
                'collaterals:id,rental_id,type,number,holder_name,status,received_at,returned_at,notes',
            ])
            ->first();

        if ($rental === null) {
            throw ValidationException::withMessages([
                'source_reference' => 'Rental tidak ditemukan dalam cakupan cabang Anda.',
            ]);
        }
        $this->guardBranch($rental->branch, $actor);

        return [
            (int) $rental->id,
            $rental->branch,
            [
                'source' => [
                    'reference' => $rental->rental_number,
                    'status' => $rental->status,
                    'booking_reference' => $rental->booking?->booking_number,
                    'checked_out_at' => $this->isoDateTime($rental->checked_out_at),
                    'due_at' => $this->isoDateTime($rental->due_at),
                    'returned_at' => $this->isoDateTime($rental->returned_at),
                ],
                'branch' => $this->branchData($rental->branch),
                'customer' => $this->customerData($rental->customer),
                'rate_plan' => $rental->ratePlan?->only(['code', 'name', 'duration_unit', 'duration_value']),
                'promotion' => $rental->promotion?->only(['code', 'name', 'type', 'value', 'bonus_duration']),
                'items' => $rental->items->map(fn (RentalItem $item): array => [
                    'description' => $item->description,
                    'sku' => $item->product?->sku,
                    'quantity' => (int) $item->quantity,
                    'returned_quantity' => (int) $item->returned_quantity,
                    'unit_rate' => (float) $item->unit_rate,
                    'additional_amount' => (float) $item->additional_amount,
                    'discount_amount' => (float) $item->discount_amount,
                    'total_amount' => (float) $item->total_amount,
                    'due_at' => $this->isoDateTime($item->due_at),
                    'status' => $item->status,
                    'assets' => $item->assets->map(static fn (RentalItemAsset $line): array => [
                        'asset_code' => $line->asset?->asset_code,
                        'serial_number' => $line->asset?->serial_number,
                        'checkout_condition' => $line->checkout_condition,
                        'return_condition' => $line->return_condition,
                        'status' => $line->status,
                    ])->values()->all(),
                ])->values()->all(),
                'financial' => [
                    'subtotal' => (float) $rental->subtotal,
                    'discount_amount' => (float) $rental->discount_amount,
                    'tax_amount' => (float) $rental->tax_amount,
                    'total_amount' => (float) $rental->total_amount,
                    'paid_amount' => (float) $rental->paid_amount,
                    'balance_due' => (float) $rental->balance_due,
                    'deposit_amount' => (float) $rental->deposit_amount,
                    'late_fee_amount' => (float) $rental->late_fee_amount,
                    'damage_fee_amount' => (float) $rental->damage_fee_amount,
                ],
                'pricing_snapshot' => $rental->pricing_snapshot,
                'payments' => $this->paymentLines($rental->payments),
                'extensions' => $rental->extensions->map(fn (RentalExtension $extension): array => [
                    'extension_number' => $extension->extension_number,
                    'status' => $extension->status,
                    'previous_due_at' => $this->isoDateTime($extension->previous_due_at),
                    'extended_due_at' => $this->isoDateTime($extension->extended_due_at),
                    'total_amount' => (float) $extension->total_amount,
                    'approved_at' => $this->isoDateTime($extension->approved_at),
                ])->values()->all(),
                'collaterals' => $rental->collaterals->map(fn (RentalCollateral $collateral): array => [
                    'type' => $collateral->type,
                    'number' => $collateral->number,
                    'holder_name' => $collateral->holder_name,
                    'status' => $collateral->status,
                    'received_at' => $this->isoDateTime($collateral->received_at),
                    'returned_at' => $this->isoDateTime($collateral->returned_at),
                    'notes' => $collateral->notes,
                ])->values()->all(),
                'agreement_terms' => $this->agreementTerms(),
                'notes' => $rental->notes,
            ],
        ];
    }

    /**
     * @return array{0: int, 1: Branch, 2: array<string, mixed>}
     */
    private function paymentSnapshot(string $reference, User $actor): array
    {
        $payment = Payment::query()
            ->whereIn('branch_id', $this->allowedBranchIds($actor))
            ->where('payment_number', $reference)
            ->with([
                'branch:id,company_id,code,name,phone,email,address,city,province,postal_code',
                'customer:id,customer_number,name,phone,email,institution,is_member,member_number',
                'customer.primaryAddress:id,customer_id,address,village,district,city,province,postal_code',
                'booking:id,booking_number,status',
                'rental:id,rental_number,status',
                'rentalExtension:id,rental_id,extension_number,status',
                'paymentMethod:id,code,name,type',
                'receiver:id,name',
            ])
            ->first();

        if ($payment === null) {
            throw ValidationException::withMessages([
                'source_reference' => 'Payment tidak ditemukan dalam cakupan cabang Anda.',
            ]);
        }
        $this->guardBranch($payment->branch, $actor);

        return [
            (int) $payment->id,
            $payment->branch,
            [
                'source' => [
                    'reference' => $payment->payment_number,
                    'status' => $payment->status,
                    'paid_at' => $this->isoDateTime($payment->paid_at),
                ],
                'branch' => $this->branchData($payment->branch),
                'customer' => $payment->customer === null ? null : $this->customerData($payment->customer),
                'payment' => [
                    'payment_number' => $payment->payment_number,
                    'direction' => $payment->direction,
                    'type' => $payment->type,
                    'source_context' => $payment->source_context,
                    'status' => $payment->status,
                    'amount' => (float) $payment->amount,
                    'paid_at' => $this->isoDateTime($payment->paid_at),
                    'external_reference' => $payment->external_reference,
                    'method' => $payment->paymentMethod?->only(['code', 'name', 'type']),
                    'receiver' => $payment->receiver?->only(['id', 'name']),
                ],
                'related' => [
                    'booking_reference' => $payment->booking?->booking_number,
                    'rental_reference' => $payment->rental?->rental_number,
                    'extension_reference' => $payment->rentalExtension?->extension_number,
                ],
                'notes' => $payment->notes,
            ],
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function assertCombination(string $documentType, string $sourceType, array $snapshot): void
    {
        $valid = match ($documentType) {
            'invoice' => in_array($sourceType, ['booking', 'rental'], true),
            'receipt' => $sourceType === 'payment',
            'agreement' => $sourceType === 'rental',
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                'source_type' => 'Sumber dokumen tidak sesuai dengan jenis dokumen.',
            ]);
        }

        if ($documentType === 'invoice' && $sourceType === 'booking') {
            $source = is_array($snapshot['source'] ?? null) ? $snapshot['source'] : [];
            $status = (string) ($source['status'] ?? '');
            if (! in_array($status, ['confirmed', 'converted', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'source_reference' => 'Invoice booking hanya dapat diterbitkan untuk booking yang sudah dikonfirmasi.',
                ]);
            }
        }

        if ($documentType === 'receipt') {
            $payment = is_array($snapshot['payment'] ?? null) ? $snapshot['payment'] : [];
            if (($payment['status'] ?? null) !== 'completed' || ($payment['direction'] ?? null) !== 'in') {
                throw ValidationException::withMessages([
                    'source_reference' => 'Nota hanya dapat diterbitkan untuk payment masuk yang masih valid.',
                ]);
            }
        }
    }

    /** @return list<int> */
    private function allowedBranchIds(User $actor): array
    {
        $ids = $actor->accessibleBranches()
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if (! $actor->hasCompanyScopedRole()) {
            $current = (int) ($actor->current_branch_id ?? 0);

            return in_array($current, $ids, true) ? [$current] : [];
        }

        return array_values($ids);
    }

    private function guardBranch(Branch $branch, User $actor): void
    {
        if ((int) $branch->company_id !== (int) $actor->company_id) {
            abort(404);
        }

        $allowed = $this->allowedBranchIds($actor);
        abort_unless(in_array((int) $branch->id, $allowed, true), 404);
    }

    /** @return array<string, mixed> */
    private function branchData(Branch $branch): array
    {
        return $branch->only([
            'id', 'code', 'name', 'phone', 'email', 'address', 'city', 'province', 'postal_code',
        ]);
    }

    /** @return array<string, mixed> */
    private function customerData(Customer $customer): array
    {
        $address = $customer->primaryAddress;

        return [
            'customer_number' => $customer->customer_number,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'institution' => $customer->institution,
            'is_member' => (bool) $customer->is_member,
            'member_number' => $customer->member_number,
            'address' => $address?->only([
                'address', 'village', 'district', 'city', 'province', 'postal_code',
            ]),
        ];
    }

    /**
     * @param  EloquentCollection<int, Payment>  $payments
     * @return list<array<string, mixed>>
     */
    private function paymentLines(EloquentCollection $payments): array
    {
        $lines = [];

        foreach ($payments as $payment) {
            $lines[] = [
                'payment_number' => $payment->payment_number,
                'type' => $payment->type,
                'direction' => $payment->direction,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'paid_at' => $this->isoDateTime($payment->paid_at),
                'external_reference' => $payment->external_reference,
                'method' => $payment->paymentMethod?->name,
            ];
        }

        return $lines;
    }

    private function isoDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->toIso8601String();
    }

    /** @return list<string> */
    private function agreementTerms(): array
    {
        return [
            'Penyewa bertanggung jawab menjaga seluruh unit selama masa rental.',
            'Perpanjangan wajib diproses melalui sistem sebelum jatuh tempo dan tunduk pada ketersediaan unit.',
            'Keterlambatan, kerusakan, kehilangan, dan kebutuhan cleaning dapat menimbulkan biaya tambahan sesuai hasil pemeriksaan.',
            'Jaminan fisik dicatat terpisah dari deposit uang dan dikembalikan setelah kewajiban yang terkait dinyatakan selesai.',
            'Setiap pembayaran, koreksi, dan pengembalian dicatat dalam histori sistem dan tidak menghapus transaksi sebelumnya.',
            'Dokumen ini merekam kondisi transaksi pada saat diterbitkan; versi baru dapat diterbitkan bila transaksi berubah.',
        ];
    }
}
