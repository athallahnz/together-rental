<?php

namespace App\Domain\Notifications;

use App\Jobs\SendNotificationEmail;
use App\Models\NotificationMessage;
use App\Models\NotificationPreference;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationManager
{
    public function __construct(
        private readonly NotificationRecipientResolver $recipients,
    ) {}

    /**
     * @param array{
     *     branch_id: int|null,
     *     source_type: string,
     *     source_id: int,
     *     title: string,
     *     body: string,
     *     action_url: string|null,
     *     due_at: mixed,
     *     metadata?: array<string, mixed>
     * } $alert
     * @return array{created: int, repeated: int}
     */
    public function publish(NotificationRule $rule, array $alert): array
    {
        $created = 0;
        $repeated = 0;
        $recipients = $this->recipients->forPermission(
            $rule->company_id,
            $alert['branch_id'],
            $rule->recipient_permission,
            $rule->category,
            $rule->severity,
        );

        foreach ($recipients as $recipient) {
            /** @var array{message: NotificationMessage, created: bool, repeated: bool} $result */
            $result = DB::transaction(function () use ($rule, $alert, $recipient): array {
                $dedupeKey = implode(':', [
                    $rule->code,
                    str_replace('\\', '.', $alert['source_type']),
                    (string) $alert['source_id'],
                ]);
                $message = NotificationMessage::query()
                    ->where('user_id', $recipient->id)
                    ->where('dedupe_key', $dedupeKey)
                    ->lockForUpdate()
                    ->first();
                $now = now();
                $isNew = $message === null;
                $shouldRepeat = false;

                if ($message === null) {
                    $message = new NotificationMessage([
                        'company_id' => $rule->company_id,
                        'branch_id' => $alert['branch_id'],
                        'user_id' => $recipient->id,
                        'rule_code' => $rule->code,
                        'category' => $rule->category,
                        'severity' => $rule->severity,
                        'source_type' => $alert['source_type'],
                        'source_id' => $alert['source_id'],
                        'dedupe_key' => $dedupeKey,
                        'occurrences' => 1,
                        'first_triggered_at' => $now,
                        'last_triggered_at' => $now,
                    ]);
                } else {
                    $repeatAt = $message->last_triggered_at
                        ->addMinutes(max($rule->repeat_minutes, 5));
                    $shouldRepeat = $repeatAt->lte($now)
                        && $message->dismissed_at === null
                        && ($message->snoozed_until === null || $message->snoozed_until->lte($now));

                    if ($message->resolved_at !== null) {
                        $message->resolved_at = null;
                        $message->dismissed_at = null;
                        $message->read_at = null;
                        $shouldRepeat = true;
                    }

                    if ($shouldRepeat) {
                        $message->occurrences++;
                        $message->last_triggered_at = $now;
                        $message->read_at = null;
                        $message->email_status = 'not_requested';
                        $message->email_error = null;
                    }
                }

                $message->fill([
                    'branch_id' => $alert['branch_id'],
                    'category' => $rule->category,
                    'severity' => $rule->severity,
                    'title' => $alert['title'],
                    'body' => $alert['body'],
                    'action_url' => $alert['action_url'],
                    'due_at' => $alert['due_at'],
                    'metadata' => $alert['metadata'] ?? null,
                ]);
                $message->save();

                return [
                    'message' => $message,
                    'created' => $isNew,
                    'repeated' => ! $isNew && $shouldRepeat,
                ];
            }, 3);

            /** @var NotificationMessage $message */
            $message = $result['message'];
            if ($result['created'] === true) {
                $created++;
                $this->queueEmailWhenEnabled($message, $recipient);
            } elseif ($result['repeated'] === true) {
                $repeated++;
                $this->queueEmailWhenEnabled($message, $recipient);
            }
        }

        return compact('created', 'repeated');
    }

    /**
     * @param  list<int>  $activeSourceIds
     */
    public function resolveMissing(NotificationRule $rule, array $activeSourceIds): int
    {
        return NotificationMessage::query()
            ->where('company_id', $rule->company_id)
            ->where('rule_code', $rule->code)
            ->whereNull('resolved_at')
            ->when(
                $activeSourceIds !== [],
                fn (Builder $query) => $query->whereNotIn('source_id', $activeSourceIds),
            )
            ->update(['resolved_at' => now(), 'updated_at' => now()]);
    }

    public function preference(User $user): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'email_enabled' => false,
                'email_min_severity' => 'critical',
                'muted_categories' => [],
            ],
        );
    }

    private function queueEmailWhenEnabled(NotificationMessage $message, User $recipient): void
    {
        $preference = $recipient->notificationPreference ?? $this->preference($recipient);

        if (! $preference->email_enabled || $recipient->email_verified_at === null) {
            return;
        }

        if (! $this->meetsSeverity($message->severity, $preference->email_min_severity)) {
            return;
        }

        if ($this->insideQuietHours($preference)) {
            return;
        }

        $message->forceFill(['email_status' => 'queued'])->save();
        SendNotificationEmail::dispatch($message->id)->afterCommit();
    }

    private function meetsSeverity(string $actual, string $minimum): bool
    {
        $rank = ['info' => 1, 'warning' => 2, 'critical' => 3];

        return ($rank[$actual] ?? 0) >= ($rank[$minimum] ?? 3);
    }

    private function insideQuietHours(NotificationPreference $preference): bool
    {
        if ($preference->quiet_hours_start === null || $preference->quiet_hours_end === null) {
            return false;
        }

        $now = Carbon::now()->format('H:i:s');
        $start = $preference->quiet_hours_start;
        $end = $preference->quiet_hours_end;

        return $start <= $end
            ? $now >= $start && $now < $end
            : $now >= $start || $now < $end;
    }
}
