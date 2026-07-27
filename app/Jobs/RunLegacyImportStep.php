<?php

namespace App\Jobs;

use App\Domain\LegacyImport\LegacyImportRecorder;
use App\Domain\LegacyImport\RentalV1Executor;
use App\Domain\LegacyImport\RentalV1Previewer;
use App\Domain\LegacyImport\RentalV1Validator;
use App\Domain\LegacyImport\RentalV1Verifier;
use App\Models\LegacyImportBatch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunLegacyImportStep implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $batchId,
        public readonly int $userId,
        public readonly string $step,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->batchId}:{$this->step}";
    }

    public function handle(
        RentalV1Previewer $previewer,
        RentalV1Validator $validator,
        RentalV1Executor $executor,
        RentalV1Verifier $verifier,
    ): void {
        $batch = LegacyImportBatch::query()->findOrFail($this->batchId);

        match ($this->step) {
            'preview' => $previewer->preview($batch, $this->userId),
            'validation' => $validator->validate($batch, $this->userId),
            'execution' => $executor->execute($batch, $this->userId),
            'verification' => $verifier->verify($batch, $this->userId),
            default => throw new \RuntimeException("Legacy import step [{$this->step}] tidak dikenal."),
        };
    }

    public function failed(?Throwable $exception): void
    {
        $batch = LegacyImportBatch::query()->find($this->batchId);

        if ($batch === null || $batch->status === 'failed') {
            return;
        }

        app(LegacyImportRecorder::class)->fail(
            $batch,
            $this->step,
            $exception?->getMessage() ?? 'Queue legacy import berhenti tanpa detail error.',
            $this->userId,
        );
    }
}
