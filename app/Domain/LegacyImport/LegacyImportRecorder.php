<?php

namespace App\Domain\LegacyImport;

use App\Models\LegacyImportBatch;

final class LegacyImportRecorder
{
    /** @param array<string, mixed> $context */
    public function event(
        LegacyImportBatch $batch,
        string $event,
        ?int $userId = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        array $context = [],
    ): void {
        $batch->events()->create([
            'user_id' => $userId,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $context */
    public function transition(
        LegacyImportBatch $batch,
        string $status,
        string $event,
        ?int $userId = null,
        array $context = [],
    ): void {
        $fromStatus = $batch->status;
        $batch->update(['status' => $status]);
        $this->event($batch, $event, $userId, $fromStatus, $status, $context);
    }

    public function fail(
        LegacyImportBatch $batch,
        string $step,
        string $message,
        ?int $userId = null,
    ): void {
        $fromStatus = $batch->status;
        $options = $batch->options ?? [];
        $options['failed_step'] = $step;

        $batch->update([
            'status' => 'failed',
            'options' => $options,
            'failure_message' => mb_substr($message, 0, 65000),
            'failed_at' => now(),
        ]);

        $this->event($batch, "{$step}_failed", $userId, $fromStatus, 'failed', [
            'message' => mb_substr($message, 0, 1000),
        ]);
    }
}
