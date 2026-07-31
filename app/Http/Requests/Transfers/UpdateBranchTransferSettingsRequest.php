<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchTransferSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfers.settings') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $this->user()?->company_id)],
            'dispatch_capture_mode' => ['required', Rule::in(['camera_required', 'camera_preferred', 'gallery_allowed'])],
            'receiving_capture_mode' => ['required', Rule::in(['camera_required', 'camera_preferred', 'gallery_allowed'])],
            'dispatch_min_photos' => ['required', 'integer', 'min:1', 'max:10'],
            'receiving_min_photos' => ['required', 'integer', 'min:1', 'max:10'],
            'require_waybill' => ['required', 'boolean'],
            'allow_gallery_override' => ['required', 'boolean'],
        ];
    }
}
