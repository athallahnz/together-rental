<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPaymentInput;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\RatePlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveBookingRequest extends FormRequest
{
    use ValidatesPaymentInput;

    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            ? Gate::allows('bookings.update')
            : Gate::allows('bookings.create');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)->where('status', 'active')->whereNull('deleted_at')],
            'rate_plan_id' => ['required', 'integer', Rule::exists('rate_plans', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'source' => ['required', Rule::in(['counter', 'phone', 'whatsapp', 'website', 'other'])],
            'starts_at' => ['required', 'date', 'after_or_equal:today'],
            'duration_units' => ['required', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.type' => ['required', Rule::in(['product', 'package'])],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer'],
            'cash_session_id' => ['nullable', 'integer'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $branch = Branch::query()->find($this->integer('branch_id'));

                if ($branch !== null && ! $this->user()->canAccessBranch($branch)) {
                    $validator->errors()->add('branch_id', 'Anda tidak memiliki akses ke cabang tersebut.');
                }

                $booking = $this->route('booking');

                if ($booking instanceof Booking && $booking->branch_id !== $this->integer('branch_id')) {
                    $validator->errors()->add('branch_id', 'Cabang booking tidak dapat diubah.');
                }

                if (! RatePlan::query()
                    ->where('company_id', $this->user()->company_id)
                    ->where('is_active', true)
                    ->whereKey($this->integer('rate_plan_id'))
                    ->where(fn (Builder $query) => $query
                        ->whereNull('branch_id')
                        ->orWhere('branch_id', $this->integer('branch_id')))
                    ->exists()) {
                    $validator->errors()->add('rate_plan_id', 'Rate plan tidak tersedia untuk cabang terpilih.');
                }

                $paymentAmount = $this->float('payment_amount');
                $depositPaid = $this->float('deposit_paid');
                $hasPayment = $paymentAmount > 0 || $depositPaid > 0;

                if ($booking instanceof Booking && $hasPayment) {
                    $validator->errors()->add(
                        'payment_amount',
                        'Pembayaran baru dicatat dari detail booking, bukan saat mengubah booking.',
                    );
                }

                $this->validatePaymentInput(
                    $validator,
                    $paymentAmount + $depositPaid,
                    $this->integer('branch_id'),
                );
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source' => $this->input('source', 'counter'),
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'payment_amount' => $this->input('payment_amount', 0),
            'deposit_paid' => $this->input('deposit_paid', 0),
        ]);
    }
}
