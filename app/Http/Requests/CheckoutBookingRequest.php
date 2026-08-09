<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPaymentInput;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutBookingRequest extends FormRequest
{
    use ValidatesPaymentInput;

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
            'payment_method_id' => ['nullable', 'integer', 'min:1'],
            'cash_session_id' => ['nullable', 'integer'],
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

                if ($booking instanceof Booking) {
                    $this->validatePaymentInput(
                        $validator,
                        $this->float('payment_amount') + $this->float('deposit_paid'),
                        $booking->branch_id,
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $paymentMethodId = $this->integer('payment_method_id');

        $this->merge([
            'checkout_condition' => $this->input('checkout_condition', 'good'),
            'payment_amount' => $this->input('payment_amount', 0),
            'deposit_paid' => $this->input('deposit_paid', 0),
            'payment_method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
        ]);
    }
}
