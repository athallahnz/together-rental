<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPaymentInput;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRentalReturnRequest extends FormRequest
{
    use ValidatesPaymentInput;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_method_id' => $this->integer('payment_method_id') ?: null,
            'payment_amount' => $this->input('payment_amount', 0) ?: 0,
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'returned_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer'],
            'cash_session_id' => ['nullable', 'integer'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'items' => ['required_without:bulk_items', 'array'],
            'items.*.rental_item_asset_id' => ['required', 'integer', 'distinct'],
            'items.*.replacement_asset_id' => ['nullable', 'integer', 'distinct', 'exists:assets,id'],
            'items.*.condition' => [
                'required',
                Rule::in(['excellent', 'good', 'fair', 'damaged', 'lost']),
            ],
            'items.*.damage_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.cleaning_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'bulk_items' => ['required_without:items', 'array'],
            'bulk_items.*.rental_item_id' => ['required', 'integer'],
            'bulk_items.*.quantity' => ['required', 'integer', 'min:1'],
            'bulk_items.*.condition' => ['required', Rule::in(['excellent', 'good', 'fair', 'damaged', 'lost'])],
            'bulk_items.*.damage_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'bulk_items.*.cleaning_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'bulk_items.*.notes' => ['nullable', 'string', 'max:2000'],
            'returned_collateral_ids' => ['nullable', 'array'],
            'returned_collateral_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $payment = (float) $this->input('payment_amount', 0);
                $rental = $this->route('rental');
                if ($rental instanceof Rental) {
                    $this->validatePaymentInput(
                        $validator,
                        $payment,
                        $rental->branch_id,
                    );

                    $submitted = $this->input('returned_at');
                    if (
                        $submitted !== null
                        && $submitted !== ''
                        && ! $validator->errors()->has('returned_at')
                    ) {
                        $returnedAt = CarbonImmutable::parse((string) $submitted);
                        $checkedOutAt = $rental->checked_out_at === null
                            ? null
                            : CarbonImmutable::parse((string) $rental->checked_out_at);

                        if ($checkedOutAt !== null && $returnedAt->isBefore($checkedOutAt)) {
                            $validator->errors()->add(
                                'returned_at',
                                'Waktu pengembalian tidak boleh sebelum waktu checkout.',
                            );
                        }

                        if ($returnedAt->isAfter(now()->addMinutes(2))) {
                            $validator->errors()->add(
                                'returned_at',
                                'Waktu pengembalian tidak boleh lebih dari 2 menit di masa depan.',
                            );
                        }
                    }
                }
            },
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('rentals.return') === true;
    }
}
