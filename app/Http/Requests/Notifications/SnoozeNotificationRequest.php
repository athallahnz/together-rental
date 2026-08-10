<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SnoozeNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.view') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'minutes' => ['required', 'integer', Rule::in([60, 1440, 10080])],
        ];
    }
}
