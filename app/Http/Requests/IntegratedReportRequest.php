<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IntegratedReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reports.view') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
            'report' => [
                'nullable',
                'string',
                Rule::in(['operational', 'finance', 'receivables', 'cash', 'assets', 'transfers', 'inventory-audits']),
            ],
            'status' => ['nullable', 'string', 'max:30'],
            'payment_method_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'string', Rule::in(['excel', 'pdf'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('from') || ! $this->filled('to')) {
                    return;
                }

                $from = CarbonImmutable::parse($this->string('from')->toString());
                $to = CarbonImmutable::parse($this->string('to')->toString());

                if ($from->diffInDays($to) > 366) {
                    $validator->errors()->add('to', 'Rentang laporan maksimal 367 hari kalender.');
                }
            },
        ];
    }
}
