<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('rentals.create');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'checked_out_at' => ['required', 'date'],
            'checkout_notes' => ['nullable', 'string', 'max:3000'],
            'checkout_condition' => ['nullable', Rule::in(['excellent', 'good', 'fair'])],
            'assets' => ['nullable', 'array'],
            'assets.*.asset_id' => ['required', 'integer', 'distinct'],
            'assets.*.condition' => ['required', Rule::in(['excellent', 'good', 'fair'])],
            'assets.*.notes' => ['nullable', 'string', 'max:1000'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $booking = $this->route('booking');

                if (! $booking instanceof Booking
                    || ! $this->user()->accessibleBranches()->whereKey($booking->branch_id)->exists()) {
                    $validator->errors()->add('booking', 'Booking tidak ditemukan pada cabang yang dapat diakses.');
                }

                $hasPayment = $this->float('payment_amount') > 0 || $this->float('deposit_paid') > 0;

                if ($hasPayment && ! $this->filled('payment_method_id')) {
                    $validator->errors()->add('payment_method_id', 'Metode pembayaran wajib dipilih.');
                }

                if ($this->filled('payment_method_id')) {
                    $method = PaymentMethod::query()
                        ->where('company_id', $this->user()->company_id)
                        ->where('is_active', true)
                        ->find($this->integer('payment_method_id'));

                    if ($method === null) {
                        $validator->errors()->add('payment_method_id', 'Metode pembayaran tidak tersedia.');
                    } elseif ($method->requires_reference && ! $this->filled('payment_reference')) {
                        $validator->errors()->add('payment_reference', 'Referensi pembayaran wajib diisi.');
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'checkout_condition' => $this->input('checkout_condition', 'good'),
            'payment_amount' => $this->input('payment_amount', 0),
            'deposit_paid' => $this->input('deposit_paid', 0),
        ]);
    }
}
