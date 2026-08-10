<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('products.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(Promotion::TYPES)],
            'value' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'minimum_transaction' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'bonus_duration' => ['nullable', 'integer', 'min:0', 'max:365'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['required', 'boolean'],
            'member_only' => ['nullable', 'boolean'],
            'non_member_only' => ['nullable', 'boolean'],
            'allow_member_stack' => ['nullable', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $branchId = $this->integer('branch_id') ?: null;
                $promotion = $this->route('promotion');
                $promotion = $promotion instanceof Promotion ? $promotion : null;

                if ($branchId === null && ! $user->hasCompanyScopedRole()) {
                    $validator->errors()->add('branch_id', 'Hanya role company yang dapat membuat promo global.');
                }

                if ($branchId !== null) {
                    $branch = Branch::query()
                        ->where('company_id', $user->company_id)
                        ->whereKey($branchId)
                        ->first();
                    if ($branch === null || ! $user->canAccessBranch($branch)) {
                        $validator->errors()->add('branch_id', 'Cabang promo tidak dapat diakses.');
                    }
                    if (! $user->hasCompanyScopedRole() && $branchId !== $user->current_branch_id) {
                        $validator->errors()->add('branch_id', 'Aktifkan cabang yang akan dikelola terlebih dahulu.');
                    }
                }

                $duplicate = Promotion::query()
                    ->where('company_id', $user->company_id)
                    ->where('code', $this->string('code')->toString())
                    ->when(
                        $branchId === null,
                        fn ($query) => $query->whereNull('branch_id'),
                        fn ($query) => $query->where('branch_id', $branchId),
                    )
                    ->when(
                        $promotion !== null,
                        fn ($query) => $query->whereKeyNot($promotion->id),
                    )
                    ->exists();
                if ($duplicate) {
                    $validator->errors()->add('code', 'Kode promo sudah digunakan pada scope yang sama.');
                }

                $type = $this->string('type')->toString();
                $value = $this->float('value');
                $bonus = $this->integer('bonus_duration');
                if ($type === 'percentage' && ($value <= 0 || $value > 100)) {
                    $validator->errors()->add('value', 'Promo persentase harus lebih dari 0 dan maksimal 100%.');
                }
                if ($type === 'fixed' && $value <= 0) {
                    $validator->errors()->add('value', 'Promo nominal harus memiliki nilai lebih dari 0.');
                }
                if ($type === 'bonus_duration' && $bonus <= 0) {
                    $validator->errors()->add('bonus_duration', 'Promo bonus durasi harus menambah minimal 1 unit durasi.');
                }
                if ($this->boolean('member_only') && $this->boolean('non_member_only')) {
                    $validator->errors()->add('member_only', 'Promo tidak dapat sekaligus khusus member dan non-member.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->integer('branch_id') ?: null,
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'value' => $this->input('value', 0),
            'maximum_discount' => $this->float('maximum_discount') > 0
                ? $this->float('maximum_discount')
                : null,
            'minimum_transaction' => $this->input('minimum_transaction', 0),
            'bonus_duration' => $this->input('bonus_duration', 0),
            'usage_limit' => $this->integer('usage_limit') > 0
                ? $this->integer('usage_limit')
                : null,
            'starts_at' => $this->filled('starts_at') ? $this->input('starts_at') : null,
            'ends_at' => $this->filled('ends_at') ? $this->input('ends_at') : null,
            'is_active' => $this->boolean('is_active', true),
            'member_only' => $this->boolean('member_only'),
            'non_member_only' => $this->boolean('non_member_only'),
            'allow_member_stack' => $this->boolean('allow_member_stack'),
        ]);
    }
}
