<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $branch_id
 * @property int $user_id
 * @property string $rule_code
 * @property string $category
 * @property string $severity
 * @property string $title
 * @property string $body
 * @property string|null $action_url
 * @property string $source_type
 * @property int $source_id
 * @property string $dedupe_key
 * @property int $occurrences
 * @property CarbonImmutable $first_triggered_at
 * @property CarbonImmutable $last_triggered_at
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $dismissed_at
 * @property CarbonImmutable|null $snoozed_until
 * @property CarbonImmutable|null $resolved_at
 * @property string $email_status
 * @property CarbonImmutable|null $email_sent_at
 * @property CarbonImmutable|null $email_failed_at
 * @property string|null $email_error
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'company_id',
    'branch_id',
    'user_id',
    'rule_code',
    'category',
    'severity',
    'title',
    'body',
    'action_url',
    'source_type',
    'source_id',
    'dedupe_key',
    'occurrences',
    'first_triggered_at',
    'last_triggered_at',
    'due_at',
    'read_at',
    'dismissed_at',
    'snoozed_until',
    'resolved_at',
    'email_status',
    'email_sent_at',
    'email_failed_at',
    'email_error',
    'metadata',
])]
class NotificationMessage extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @param Builder<NotificationMessage> $query */
    public function scopeVisible(Builder $query): void
    {
        $query
            ->whereNull('resolved_at')
            ->whereNull('dismissed_at')
            ->where(function (Builder $visibility): void {
                $visibility
                    ->whereNull('snoozed_until')
                    ->orWhere('snoozed_until', '<=', now());
            });
    }

    /** @param Builder<NotificationMessage> $query */
    public function scopeUnread(Builder $query): void
    {
        $query->visible()->whereNull('read_at');
    }

    protected function casts(): array
    {
        return [
            'occurrences' => 'integer',
            'first_triggered_at' => 'datetime',
            'last_triggered_at' => 'datetime',
            'due_at' => 'datetime',
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'resolved_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'email_failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
