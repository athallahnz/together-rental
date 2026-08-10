<?php

namespace App\Domain\InventoryAudits;

use App\Models\InventoryAuditItem;
use App\Models\InventoryAuditMedia;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InventoryAuditMediaManager
{
    /** @var list<string> */
    private array $pendingPaths = [];

    private bool $collecting = false;

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
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
    public function attachPhotos(
        InventoryAuditItem $item,
        array $photos,
        User $actor,
    ): void {
        $item->loadMissing('audit');

        foreach ($photos as $photo) {
            $extension = mb_strtolower(
                $photo->guessExtension() ?: $photo->getClientOriginalExtension() ?: 'jpg',
            );
            $directory = sprintf(
                'inventory-audits/%s/%s/items/%s',
                $item->audit->company_id,
                $item->audit->audit_number,
                $item->id,
            );
            $filename = Str::uuid()->toString().'.'.$extension;
            $path = $photo->storeAs($directory, $filename, 'local');

            if ($path === false) {
                throw new RuntimeException('Gagal menyimpan bukti stock opname.');
            }

            if ($this->collecting) {
                $this->pendingPaths[] = $path;
            }

            $hash = hash_file('sha256', Storage::disk('local')->path($path));
            if ($hash === false) {
                Storage::disk('local')->delete($path);
                throw new RuntimeException('Gagal menghitung checksum bukti stock opname.');
            }

            InventoryAuditMedia::query()->create([
                'inventory_audit_item_id' => $item->id,
                'type' => 'photo',
                'path' => $path,
                'original_name' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getMimeType(),
                'file_size' => $photo->getSize() ?: 0,
                'sha256' => $hash,
                'capture_source' => 'camera',
                'captured_at' => now(),
                'captured_by' => $actor->id,
                'metadata' => [
                    'last_modified' => null,
                    'audit_stage' => 'physical_count',
                ],
            ]);
        }
    }
}
