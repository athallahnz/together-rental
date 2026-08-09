<?php

namespace App\Http\Requests\Transfers;

use App\Http\Requests\Concerns\ValidatesPaymentInput;
use App\Models\BranchTransferExpense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PayBranchTransferExpenseRequest extends FormRequest
{
    use ValidatesPaymentInput;

    public function authorize(): bool
    {
        return $this->user()?->can('transfers.expense') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'actual_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'cash_session_id' => ['nullable', 'integer', Rule::exists('cash_sessions', 'id')->where('status', 'open')],
            'paid_at' => ['required', 'date'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $expense = $this->route('expense');
                if ($expense instanceof BranchTransferExpense) {
                    $this->validatePaymentInput(
                        $validator,
                        $this->float('actual_amount'),
                        $expense->expense_branch_id,
                    );
                }
            },
        ];
    }
}
