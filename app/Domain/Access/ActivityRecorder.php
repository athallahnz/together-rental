<?php

namespace App\Domain\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityRecorder
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        Request $request,
        string $event,
        Model $subject,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $branchId = null,
    ): void {
        /** @var User $actor */
        $actor = $request->user();

        DB::table('activity_logs')->insert([
            'company_id' => $actor->company_id,
            'branch_id' => $branchId ?? $actor->current_branch_id,
            'actor_id' => $actor->id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'description' => $this->description($subject),
            'old_values' => $oldValues === null
                ? null
                : json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => $newValues === null
                ? null
                : json_encode($newValues, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-Id') === null
                ? null
                : mb_substr((string) $request->header('X-Request-Id'), 0, 64),
            'created_at' => now(),
        ]);
    }

    private function description(Model $subject): string
    {
        $label = $subject->getAttribute('name')
            ?? $subject->getAttribute('email')
            ?? $subject->getAttribute('code')
            ?? (string) $subject->getKey();

        return mb_substr((string) $label, 0, 255);
    }
}
