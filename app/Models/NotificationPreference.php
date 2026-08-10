<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $email_enabled
 * @property string $email_min_severity
 * @property list<string>|null $muted_categories
 * @property string|null $quiet_hours_start
 * @property string|null $quiet_hours_end
 */
#[Fillable([
    'user_id',
    'email_enabled',
    'email_min_severity',
    'muted_categories',
    'quiet_hours_start',
    'quiet_hours_end',
])]
class NotificationPreference extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'muted_categories' => 'array',
        ];
    }
}
