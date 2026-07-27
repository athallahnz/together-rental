<?php

namespace App\Domain\LegacyImport;

use App\Models\LegacyImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class RentalV1Previewer
{
    public function __construct(
        private readonly RentalV1SqlParser $parser,
        private readonly RentalV1Normalizer $normalizer,
        private readonly LegacyImportRecorder $recorder,
    ) {}

    public function preview(LegacyImportBatch $batch, ?int $userId = null): LegacyImportBatch
    {
        $this->guardStatus($batch, ['uploaded', 'previewed', 'queued_preview', 'failed'], 'preview');
        $path = $this->sourcePath($batch);

        $this->recorder->transition($batch, 'previewing', 'preview_started', $userId);

        try {
            $result = DB::transaction(function () use ($batch, $path): array {
                DB::table('legacy_import_issues')->where('batch_id', $batch->id)->delete();
                DB::table('legacy_import_mappings')->where('batch_id', $batch->id)->delete();
                DB::table('legacy_import_rows')->where('batch_id', $batch->id)->delete();
                DB::table('legacy_import_tables')->where('batch_id', $batch->id)->delete();

                $buffer = [];
                $chunkSize = max(50, (int) config('legacy-import.chunk_size', 500));
                $now = now();

                $flush = static function () use (&$buffer): void {
                    if ($buffer === []) {
                        return;
                    }

                    DB::table('legacy_import_rows')->insert($buffer);
                    $buffer = [];
                };

                $result = $this->parser->parse(
                    $path,
                    function (string $table, int $rowNumber, array $payload) use (
                        $batch,
                        &$buffer,
                        $chunkSize,
                        $now,
                        $flush,
                    ): void {
                        $payload = $this->normalizer->sanitize($table, $payload);
                        $normalized = $this->normalizer->normalize($table, $payload);
                        $definition = RentalV1Definition::table($table);

                        $buffer[] = [
                            'batch_id' => $batch->id,
                            'source_table' => $table,
                            'legacy_key' => RentalV1Definition::legacyKey($table, $payload),
                            'row_number' => $rowNumber,
                            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                            'normalized_payload' => json_encode(
                                $normalized,
                                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
                            ),
                            'fingerprint' => hash(
                                'sha256',
                                json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                            ),
                            'status' => 'pending',
                            'issue_count' => 0,
                            'target_table' => $definition['target'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        if (count($buffer) >= $chunkSize) {
                            $flush();
                        }
                    },
                );

                $flush();

                foreach ($result['tables'] as $sourceTable => $tableStats) {
                    $definition = RentalV1Definition::table($sourceTable);

                    DB::table('legacy_import_tables')->insert([
                        'batch_id' => $batch->id,
                        'source_table' => $sourceTable,
                        'target_table' => $definition['target'],
                        'status' => 'previewed',
                        'expected_rows' => $tableStats['rows'],
                        'parsed_rows' => $tableStats['rows'],
                        'summary' => json_encode([
                            'label' => $definition['label'],
                            'columns' => $tableStats['columns'],
                            'executable' => RentalV1Definition::isExecutable($sourceTable),
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                return $result;
            });

            $totalRows = (int) DB::table('legacy_import_rows')
                ->where('batch_id', $batch->id)
                ->count();

            $options = $batch->options ?? [];
            unset($options['failed_step']);

            $batch->update([
                'status' => 'previewed',
                'total_rows' => $totalRows,
                'valid_rows' => 0,
                'warning_rows' => 0,
                'error_rows' => 0,
                'imported_rows' => 0,
                'skipped_rows' => 0,
                'options' => $options,
                'summary' => [
                    'detected_tables' => count($result['tables']),
                    'ignored_tables' => $result['ignored_tables'],
                ],
                'failure_message' => null,
                'previewed_at' => now(),
                'failed_at' => null,
            ]);

            $this->recorder->event(
                $batch->fresh(),
                'preview_completed',
                $userId,
                'previewing',
                'previewed',
                ['total_rows' => $totalRows, 'tables' => count($result['tables'])],
            );

            return $batch->fresh();
        } catch (Throwable $exception) {
            $this->recorder->fail($batch->fresh(), 'preview', $exception->getMessage(), $userId);

            throw $exception;
        }
    }

    /** @param list<string> $allowed */
    private function guardStatus(LegacyImportBatch $batch, array $allowed, string $step): void
    {
        if (! in_array($batch->status, $allowed, true)) {
            throw new RuntimeException("Batch berstatus [{$batch->status}] tidak dapat menjalankan {$step}.");
        }

        if ($batch->status === 'failed' && ($batch->options['failed_step'] ?? null) !== $step) {
            throw new RuntimeException("Batch gagal pada tahap lain dan tidak dapat mengulang {$step}.");
        }
    }

    private function sourcePath(LegacyImportBatch $batch): string
    {
        if ($batch->source_path === null) {
            throw new RuntimeException('Lokasi file sumber tidak tersedia.');
        }

        return Storage::disk((string) config('legacy-import.disk'))->path($batch->source_path);
    }
}
