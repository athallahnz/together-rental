<?php

namespace App\Domain\Transfers;

use App\Models\AssetInspection;
use App\Models\AssetInspectionMedia;
use App\Models\BranchTransfer;
use App\Models\BranchTransferDocument;
use App\Models\BranchTransferExpense;
use App\Models\BranchTransferItem;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TransferMediaManager
{
    /** @var list<string> */
    private array $pendingPaths = [];

    private bool $collecting = false;

    /**
     * Keep private files consistent with their database rows. Any file written
     * before a failed database commit is removed again.
     *
     * @template TResult
     * @param Closure(): TResult $callback
     * @return TResult
     */
    public function transactional(Closure $callback): mixed
    {
        $this->pendingPaths = [];
        $this->collecting = true;

        try {
            $result = DB::transaction(
                static fn (Connection $_connection) => $callback(),
                3,
            );
            $this->pendingPaths = [];
            $this->collecting = false;

            return $result;
        } catch (Throwable $throwable) {
            Storage::disk('local')->delete($this->pendingPaths);
            $this->pendingPaths = [];
            $this->collecting = false;

            throw $throwable;
        }
    }

    /** @param list<UploadedFile> $photos */
    public function attachInspectionPhotos(
        BranchTransfer $transfer,
        AssetInspection $inspection,
        array $photos,
        string $captureSource,
        User $actor,
    ): void {
        foreach ($photos as $index => $photo) {
            $stored = $this->store(
                $transfer,
                $photo,
                $inspection->type,
            );
            AssetInspectionMedia::query()->create([
                'asset_inspection_id' => $inspection->id,
                'type' => 'photo',
                'path' => $stored['path'],
                'caption' => 'Bukti kondisi '.($index + 1),
                'capture_source' => $captureSource,
                'captured_at' => now(),
                'captured_by' => $actor->id,
                'sha256' => $stored['sha256'],
                'metadata' => [
                    'original_name' => $photo->getClientOriginalName(),
                    'mime_type' => $photo->getMimeType(),
                    'file_size' => $photo->getSize(),
                ],
            ]);
        }
    }

    public function storeDocument(
        BranchTransfer $transfer,
        UploadedFile $file,
        string $stage,
        string $documentType,
        string $captureSource,
        User $actor,
        ?BranchTransferItem $item = null,
        ?BranchTransferExpense $expense = null,
    ): BranchTransferDocument {
        $stored = $this->store($transfer, $file, $stage);

        return BranchTransferDocument::query()->create([
            'branch_transfer_id' => $transfer->id,
            'branch_transfer_item_id' => $item?->id,
            'branch_transfer_expense_id' => $expense?->id,
            'stage' => $stage,
            'document_type' => $documentType,
            'path' => $stored['path'],
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize() ?: 0,
            'sha256' => $stored['sha256'],
            'capture_source' => $captureSource,
            'captured_at' => now(),
            'uploaded_by' => $actor->id,
            'metadata' => null,
        ]);
    }

    /** @return array{path: string, sha256: string} */
    private function store(BranchTransfer $transfer, UploadedFile $file, string $stage): array
    {
        $extension = mb_strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $safeStage = Str::slug($stage, '_');
        $directory = sprintf(
            'transfers/%s/%s/%s',
            $transfer->company_id,
            $transfer->transfer_number,
            $safeStage,
        );
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, 'local');

        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan dokumen transfer.');
        }

        if ($this->collecting) {
            $this->pendingPaths[] = $path;
        }

        $absolute = Storage::disk('local')->path($path);
        $hash = hash_file('sha256', $absolute);
        if ($hash === false) {
            Storage::disk('local')->delete($path);
            throw new RuntimeException('Gagal menghitung checksum dokumen transfer.');
        }

        return ['path' => $path, 'sha256' => $hash];
    }
}
