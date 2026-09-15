<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IssueTransactionDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.issue') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['invoice', 'receipt', 'agreement'])],
            'source_type' => ['required', Rule::in(['booking', 'rental', 'payment'])],
            'source_reference' => ['required', 'string', 'max:80'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = (string) $this->input('document_type');
                $source = (string) $this->input('source_type');
                $valid = match ($type) {
                    'invoice' => in_array($source, ['booking', 'rental'], true),
                    'receipt' => $source === 'payment',
                    'agreement' => $source === 'rental',
                    default => false,
                };

                if (! $valid) {
                    $validator->errors()->add(
                        'source_type',
                        'Sumber dokumen tidak sesuai dengan jenis dokumen yang dipilih.',
                    );
                }
            },
        ];
    }
}
