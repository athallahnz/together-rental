<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inventory_audit_item_id
 * @property string $path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int $file_size
 * @property string $capture_source
 * @property array<string, mixed>|null $metadata
 * @property Carbon $captured_at
 */
#[Fillable([
    'inventory_audit_item_id',
    'type',
    'path',
    'original_name',
    'mime_type',
    'file_size',
    'sha256',
    'capture_source',
    'captured_at',
    'captured_by',
    'metadata',
])]
class InventoryAuditMedia extends Model
{
    /** @return BelongsTo<InventoryAuditItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryAuditItem::class, 'inventory_audit_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function capturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'captured_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
