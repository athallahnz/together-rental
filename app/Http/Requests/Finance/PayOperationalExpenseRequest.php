<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ValidatesPaymentInput;
use App\Models\OperationalExpense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PayOperationalExpenseRequest extends FormRequest
{
    use ValidatesPaymentInput;

    public function authorize(): bool
    {
        return $this->user()?->can('expenses.pay') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'cash_session_id' => ['nullable', 'integer', Rule::exists('cash_sessions', 'id')->where('status', 'open')],
            'paid_at' => ['required', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $expense = $this->route('expense');
                if ($expense instanceof OperationalExpense) {
                    $this->validatePaymentInput(
                        $validator,
                        (float) $expense->amount,
                        $expense->branch_id,
                    );
                }
            },
        ];
    }
}
