<?php

namespace App\Http\Requests\Notifications;

use App\Domain\Notifications\NotificationRuleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'severity' => ['required', Rule::in(NotificationRuleCatalog::severities())],
            'lead_minutes' => ['required', 'integer', 'min:0', 'max:43200'],
            'repeat_minutes' => ['required', 'integer', 'min:5', 'max:43200'],
        ];
    }
}
