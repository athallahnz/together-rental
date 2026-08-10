<?php

namespace App\Http\Requests\Notifications;

use App\Domain\Notifications\NotificationRuleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.view') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email_enabled' => ['required', 'boolean'],
            'email_min_severity' => ['required', Rule::in(NotificationRuleCatalog::severities())],
            'muted_categories' => ['present', 'array'],
            'muted_categories.*' => [
                'string',
                'distinct',
                Rule::in(NotificationRuleCatalog::categories()),
            ],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $start = $this->input('quiet_hours_start');
                $end = $this->input('quiet_hours_end');

                if (($start === null) !== ($end === null)) {
                    $validator->errors()->add(
                        'quiet_hours_end',
                        'Jam mulai dan selesai waktu tenang harus diisi bersamaan.',
                    );
                }
            },
        ];
    }
}
