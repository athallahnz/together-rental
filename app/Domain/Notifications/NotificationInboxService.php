<?php

namespace App\Domain\Notifications;

use App\Models\NotificationMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class NotificationInboxService
{
    /**
     * @return array{unread_count: int, critical_count: int, recent: list<array<string, mixed>>}
     */
    public function header(User $user): array
    {
        if (! Schema::hasTable('notification_messages') || ! $user->can('notifications.view')) {
            return ['unread_count' => 0, 'critical_count' => 0, 'recent' => []];
        }

        $branchIds = $user->accessibleBranches()->pluck('id')->all();
        $query = NotificationMessage::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $scope) use ($branchIds): void {
                $scope->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->unread();
        $recent = (clone $query)
            ->with('branch:id,code,name')
            ->latest('last_triggered_at')
            ->limit(5)
            ->get()
            ->map(fn (NotificationMessage $message): array => $this->serialize($message))
            ->all();

        return [
            'unread_count' => (clone $query)->count(),
            'critical_count' => (clone $query)->where('severity', 'critical')->count(),
            'recent' => array_values($recent),
        ];
    }

    /** @return array<string, mixed> */
    public function serialize(NotificationMessage $message): array
    {
        return [
            'id' => $message->id,
            'branch_id' => $message->branch_id,
            'branch' => $message->branch === null ? null : [
                'id' => $message->branch->id,
                'code' => $message->branch->code,
                'name' => $message->branch->name,
            ],
            'rule_code' => $message->rule_code,
            'category' => $message->category,
            'severity' => $message->severity,
            'title' => $message->title,
            'body' => $message->body,
            'action_url' => $message->action_url,
            'occurrences' => $message->occurrences,
            'first_triggered_at' => $message->first_triggered_at->toIso8601String(),
            'last_triggered_at' => $message->last_triggered_at->toIso8601String(),
            'due_at' => $message->due_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'dismissed_at' => $message->dismissed_at?->toIso8601String(),
            'snoozed_until' => $message->snoozed_until?->toIso8601String(),
            'resolved_at' => $message->resolved_at?->toIso8601String(),
            'email_status' => $message->email_status,
            'metadata' => $message->metadata,
        ];
    }
}
