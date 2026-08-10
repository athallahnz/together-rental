<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPaymentInput;
use App\Models\Rental;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreRentalExtensionRequest extends FormRequest
{
    use ValidatesPaymentInput;

    public function authorize(): bool
    {
        return Gate::allows('rentals.extend');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'duration_units' => ['required', 'integer', 'min:1', 'max:365'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'distinct', 'exists:rental_items,id'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer'],
            'cash_session_id' => ['nullable', 'integer'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
            'promotion_code' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rental = $this->route('rental');

                if (! $rental instanceof Rental
                    || ! $this->user()->accessibleBranches()->whereKey($rental->branch_id)->exists()) {
                    $validator->errors()->add('rental', 'Rental tidak tersedia pada cabang yang dapat diakses.');

                    return;
                }

                $rawItemIds = $this->input('item_ids', []);
                if (! is_array($rawItemIds)) {
                    $rawItemIds = [];
                }

                /** @var list<int> $itemIds */
                $itemIds = array_values(array_unique(array_filter(
                    array_map(static fn (mixed $id): int => (int) $id, $rawItemIds),
                    static fn (int $id): bool => $id > 0,
                )));
                $ownedCount = $rental->items()->whereKey($itemIds)->count();

                if ($ownedCount !== count($itemIds)) {
                    $validator->errors()->add('item_ids', 'Ada item perpanjangan yang bukan milik rental ini.');
                }

                $this->validatePaymentInput(
                    $validator,
                    $this->float('payment_amount'),
                    $rental->branch_id,
                );
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $paymentMethodId = $this->integer('payment_method_id');

        $this->merge([
            'payment_amount' => $this->input('payment_amount', 0),
            'payment_method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
            'cash_session_id' => $this->integer('cash_session_id') ?: null,
            'promotion_code' => $this->filled('promotion_code')
                ? mb_strtoupper(trim((string) $this->input('promotion_code')))
                : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'payment_notes' => $this->filled('payment_notes')
                ? trim((string) $this->input('payment_notes'))
                : null,
        ]);
    }
}
