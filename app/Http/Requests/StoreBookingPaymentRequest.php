<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('payments.create');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['required', 'integer'],
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

                if (! $booking instanceof Booking || ! in_array($booking->status, Booking::ACTIVE_STATUSES, true)) {
                    $validator->errors()->add('booking', 'Pembayaran hanya dapat dicatat pada booking aktif.');
                }

                if ($this->float('payment_amount') <= 0 && $this->float('deposit_paid') <= 0) {
                    $validator->errors()->add('payment_amount', 'Isi DP sewa atau deposit jaminan.');
                }

                $method = PaymentMethod::query()
                    ->where('company_id', $this->user()->company_id)
                    ->where('is_active', true)
                    ->find($this->integer('payment_method_id'));

                if ($method === null) {
                    $validator->errors()->add('payment_method_id', 'Metode pembayaran tidak tersedia.');
                } elseif ($method->requires_reference && ! $this->filled('payment_reference')) {
                    $validator->errors()->add('payment_reference', 'Referensi pembayaran wajib diisi.');
                }
            },
        ];
    }
}
