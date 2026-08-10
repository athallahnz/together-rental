<?php

namespace App\Http\Controllers;

use App\Domain\Notifications\NotificationInboxService;
use App\Domain\Notifications\NotificationManager;
use App\Domain\Notifications\NotificationReminderGenerator;
use App\Domain\Notifications\NotificationRuleCatalog;
use App\Http\Requests\Notifications\SnoozeNotificationRequest;
use App\Http\Requests\Notifications\UpdateNotificationPreferenceRequest;
use App\Http\Requests\Notifications\UpdateNotificationRuleRequest;
use App\Models\NotificationMessage;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCenterController extends Controller
{
    public function index(
        Request $request,
        NotificationInboxService $inbox,
        NotificationManager $notifications,
        NotificationReminderGenerator $generator,
    ): Response {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->company_id !== null, 403);
        $generator->ensureRules((int) $actor->company_id);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(NotificationRuleCatalog::categories())],
            'severity' => ['nullable', Rule::in(NotificationRuleCatalog::severities())],
            'state' => ['nullable', Rule::in(['all', 'unread', 'read', 'snoozed', 'dismissed'])],
            'branch_id' => ['nullable', 'integer'],
        ]);
        $branchIds = array_values(
            $actor->accessibleBranches()
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        abort_if($branchId !== null && ! in_array($branchId, $branchIds, true), 404);
        $state = is_string($filters['state'] ?? null) ? $filters['state'] : 'all';

        $query = NotificationMessage::query()
            ->with('branch:id,code,name')
            ->where('user_id', $actor->id)
            ->where(function (Builder $scope) use ($branchIds): void {
                $scope->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->when($branchId !== null, fn (Builder $builder) => $builder->where('branch_id', $branchId))
            ->when(is_string($filters['category'] ?? null), fn (Builder $builder) => $builder
                ->where('category', $filters['category']))
            ->when(is_string($filters['severity'] ?? null), fn (Builder $builder) => $builder
                ->where('severity', $filters['severity']))
            ->when(is_string($filters['search'] ?? null) && trim($filters['search']) !== '', function (Builder $builder) use ($filters): void {
                $search = trim((string) $filters['search']);
                $builder->where(function (Builder $text) use ($search): void {
                    $text->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->when($state === 'unread', fn (Builder $builder) => $builder->unread())
            ->when($state === 'read', fn (Builder $builder) => $builder
                ->whereNotNull('read_at')
                ->whereNull('dismissed_at'))
            ->when($state === 'snoozed', fn (Builder $builder) => $builder
                ->where('snoozed_until', '>', now())
                ->whereNull('dismissed_at'))
            ->when($state === 'dismissed', fn (Builder $builder) => $builder->whereNotNull('dismissed_at'))
            ->when($state === 'all', fn (Builder $builder) => $builder
                ->whereNull('resolved_at')
                ->whereNull('dismissed_at'))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->latest('last_triggered_at');

        $messages = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (NotificationMessage $message): array => $inbox->serialize($message));
        $base = NotificationMessage::query()
            ->where('user_id', $actor->id)
            ->where(function (Builder $scope) use ($branchIds): void {
                $scope->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->whereNull('resolved_at')
            ->whereNull('dismissed_at');
        $summary = [
            'total' => (clone $base)->count(),
            'unread' => (clone $base)->unread()->count(),
            'critical' => (clone $base)->unread()->where('severity', 'critical')->count(),
            'snoozed' => (clone $base)->where('snoozed_until', '>', now())->count(),
        ];
        $preference = $notifications->preference($actor);

        return Inertia::render('notifications/index', [
            'messages' => $messages,
            'summary' => $summary,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['category'] ?? '',
                'severity' => $filters['severity'] ?? '',
                'state' => $state,
                'branch_id' => $branchId,
            ],
            'categories' => NotificationRuleCatalog::categories(),
            'severities' => NotificationRuleCatalog::severities(),
            'preference' => [
                'email_enabled' => $preference->email_enabled,
                'email_min_severity' => $preference->email_min_severity,
                'muted_categories' => $preference->muted_categories ?? [],
                'quiet_hours_start' => $preference->quiet_hours_start === null
                    ? null
                    : substr($preference->quiet_hours_start, 0, 5),
                'quiet_hours_end' => $preference->quiet_hours_end === null
                    ? null
                    : substr($preference->quiet_hours_end, 0, 5),
            ],
            'rules' => $actor->can('notifications.manage')
                ? array_values(NotificationRule::query()
                    ->where('company_id', $actor->company_id)
                    ->orderBy('category')
                    ->orderBy('name')
                    ->get()
                    ->map(static fn (NotificationRule $rule): array => [
                        'id' => $rule->id,
                        'code' => $rule->code,
                        'category' => $rule->category,
                        'name' => $rule->name,
                        'description' => $rule->description,
                        'severity' => $rule->severity,
                        'recipient_permission' => $rule->recipient_permission,
                        'is_enabled' => $rule->is_enabled,
                        'lead_minutes' => $rule->lead_minutes,
                        'repeat_minutes' => $rule->repeat_minutes,
                    ])
                    ->values()
                    ->all())
                : [],
            'permissions' => [
                'manage' => $actor->can('notifications.manage'),
            ],
        ]);
    }

    public function markRead(Request $request, NotificationMessage $notification): RedirectResponse
    {
        $actor = $this->guardMessage($request, $notification);
        $notification->forceFill(['read_at' => now(), 'snoozed_until' => null])->save();
        $this->log($actor, 'notification.read', $notification);

        return back();
    }

    public function markUnread(Request $request, NotificationMessage $notification): RedirectResponse
    {
        $actor = $this->guardMessage($request, $notification);
        $notification->forceFill(['read_at' => null, 'dismissed_at' => null])->save();
        $this->log($actor, 'notification.unread', $notification);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $branchIds = $actor->accessibleBranches()->pluck('id')->all();
        $count = NotificationMessage::query()
            ->where('user_id', $actor->id)
            ->where(function (Builder $scope) use ($branchIds): void {
                $scope->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->unread()
            ->update(['read_at' => now(), 'updated_at' => now()]);
        $this->log($actor, 'notification.all_read', null, ['count' => $count]);

        return back()->with('success', "{$count} notifikasi ditandai sudah dibaca.");
    }

    public function snooze(
        SnoozeNotificationRequest $request,
        NotificationMessage $notification,
    ): RedirectResponse {
        $actor = $this->guardMessage($request, $notification);
        $minutes = (int) $request->validated('minutes');
        $notification->forceFill([
            'read_at' => now(),
            'snoozed_until' => now()->addMinutes($minutes),
            'dismissed_at' => null,
        ])->save();
        $this->log($actor, 'notification.snoozed', $notification, ['minutes' => $minutes]);

        return back()->with('success', 'Notifikasi berhasil ditunda.');
    }

    public function dismiss(Request $request, NotificationMessage $notification): RedirectResponse
    {
        $actor = $this->guardMessage($request, $notification);
        $notification->forceFill([
            'read_at' => now(),
            'dismissed_at' => now(),
            'snoozed_until' => null,
        ])->save();
        $this->log($actor, 'notification.dismissed', $notification);

        return back()->with('success', 'Notifikasi ditutup dari inbox.');
    }

    public function updatePreferences(
        UpdateNotificationPreferenceRequest $request,
        NotificationManager $notifications,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $data = $request->validated();
        $preference = $notifications->preference($actor);
        $preference->fill($data)->save();
        $this->log($actor, 'notification.preferences_updated');

        return back()->with('success', 'Preferensi notifikasi berhasil diperbarui.');
    }

    public function updateRule(
        UpdateNotificationRuleRequest $request,
        NotificationRule $notificationRule,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless(
            $actor instanceof User
            && $actor->company_id !== null
            && $notificationRule->company_id === $actor->company_id,
            404,
        );
        $notificationRule->fill([
            ...$request->validated(),
            'updated_by' => $actor->id,
        ])->save();
        $this->log($actor, 'notification.rule_updated', null, [
            'rule_id' => $notificationRule->id,
            'rule_code' => $notificationRule->code,
        ]);

        return back()->with('success', 'Aturan reminder berhasil diperbarui.');
    }

    public function generate(
        Request $request,
        NotificationReminderGenerator $generator,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->company_id !== null, 403);
        $result = $generator->generateForCompany((int) $actor->company_id);
        $this->log($actor, 'notification.scan_completed', null, $result);

        return back()->with(
            'success',
            sprintf(
                'Pemindaian selesai: %d sumber, %d notifikasi baru, %d reminder berulang.',
                $result['sources'],
                $result['created'],
                $result['repeated'],
            ),
        );
    }

    private function guardMessage(Request $request, NotificationMessage $notification): User
    {
        $actor = $request->user();
        abort_unless(
            $actor instanceof User
            && $notification->user_id === $actor->id
            && $notification->company_id === $actor->company_id,
            404,
        );
        abort_if(
            $notification->branch_id !== null
            && ! $actor->accessibleBranches()->whereKey($notification->branch_id)->exists(),
            404,
        );

        return $actor;
    }

    /** @param array<string, mixed> $values */
    private function log(
        User $actor,
        string $event,
        ?NotificationMessage $message = null,
        array $values = [],
    ): void {
        DB::table('activity_logs')->insert([
            'company_id' => $actor->company_id,
            'branch_id' => $message->branch_id ?? $actor->current_branch_id,
            'actor_id' => $actor->id,
            'subject_type' => $message === null ? null : NotificationMessage::class,
            'subject_id' => $message?->id,
            'event' => $event,
            'description' => 'Notification & Reminder Center',
            'old_values' => null,
            'new_values' => $values === [] ? null : json_encode($values, JSON_THROW_ON_ERROR),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => request()->header('X-Request-ID'),
            'created_at' => now(),
        ]);
    }
}
