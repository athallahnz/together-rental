<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $category
 * @property string $name
 * @property string $description
 * @property string $severity
 * @property string $recipient_permission
 * @property bool $is_enabled
 * @property int $lead_minutes
 * @property int $repeat_minutes
 * @property int|null $updated_by
 */
#[Fillable([
    'company_id',
    'code',
    'category',
    'name',
    'description',
    'severity',
    'recipient_permission',
    'is_enabled',
    'lead_minutes',
    'repeat_minutes',
    'updated_by',
])]
class NotificationRule extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'lead_minutes' => 'integer',
            'repeat_minutes' => 'integer',
        ];
    }
}
