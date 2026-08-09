<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResetOperationalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'string'],
            'confirmation_phrase' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'current_password'],
            'normalize_condition' => ['required', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();
            if ($user === null || $user->company_id === null) {
                $validator->errors()->add('scope', 'Perusahaan pengguna tidak tersedia.');

                return;
            }

            $scope = trim((string) $this->input('scope'));
            if ($scope === 'all') {
                $expected = 'RESET SEMUA CABANG';
            } elseif (ctype_digit($scope)) {
                $branch = Branch::query()
                    ->where('company_id', $user->company_id)
                    ->whereKey((int) $scope)
                    ->first();
                if ($branch === null) {
                    $validator->errors()->add('scope', 'Cabang reset tidak valid.');

                    return;
                }
                $expected = 'RESET '.mb_strtoupper($branch->code);
            } else {
                $validator->errors()->add('scope', 'Lingkup reset tidak valid.');

                return;
            }

            if (mb_strtoupper(trim((string) $this->input('confirmation_phrase'))) !== $expected) {
                $validator->errors()->add(
                    'confirmation_phrase',
                    "Ketik {$expected} untuk mengonfirmasi reset.",
                );
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'scope' => trim((string) $this->input('scope')),
            'confirmation_phrase' => mb_strtoupper(trim((string) $this->input('confirmation_phrase'))),
            'normalize_condition' => $this->boolean('normalize_condition'),
        ]);
    }
}
