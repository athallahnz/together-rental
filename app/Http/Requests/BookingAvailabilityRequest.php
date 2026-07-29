<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Product;
use App\Models\RatePlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class BookingAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('bookings.view');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'starts_at' => ['required', 'date'],
            'rate_plan_id' => ['required', 'integer', 'exists:rate_plans,id'],
            'duration_units' => ['required', 'integer', 'min:1', 'max:365'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'ignore_booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ];
    }

    protected function passedValidation(): void
    {
        $branch = Branch::query()->findOrFail($this->integer('branch_id'));
        abort_unless($this->user()->canAccessBranch($branch), 403);
        abort_unless(Product::query()
            ->where('company_id', $this->user()->company_id)
            ->where('is_active', true)
            ->where('is_rentable', true)
            ->whereKey($this->integer('product_id'))
            ->exists(), 422);
        abort_unless(RatePlan::query()
            ->where('company_id', $this->user()->company_id)
            ->where('is_active', true)
            ->whereKey($this->integer('rate_plan_id'))
            ->where(fn (Builder $query) => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $branch->id))
            ->exists(), 422);
    }
}
