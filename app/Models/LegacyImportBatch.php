<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $options
 * @property array<string, mixed>|null $summary
 * @property-read Branch|null $branch
 */
#[Fillable([
    'id',
    'branch_id',
    'source_city',
    'import_prefix',
    'uploaded_by',
    'source_system',
    'source_filename',
    'source_path',
    'source_sha256',
    'source_size',
    'status',
    'total_rows',
    'valid_rows',
    'warning_rows',
    'error_rows',
    'imported_rows',
    'skipped_rows',
    'options',
    'summary',
    'failure_message',
    'previewed_at',
    'validated_at',
    'mapped_at',
    'executed_at',
    'verified_at',
    'failed_at',
])]
class LegacyImportBatch extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<LegacyImportTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(LegacyImportTable::class, 'batch_id');
    }

    /** @return HasMany<LegacyImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(LegacyImportRow::class, 'batch_id');
    }

    /** @return HasMany<LegacyImportMapping, $this> */
    public function mappings(): HasMany
    {
        return $this->hasMany(LegacyImportMapping::class, 'batch_id');
    }

    /** @return HasMany<LegacyImportIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(LegacyImportIssue::class, 'batch_id');
    }

    /** @return HasMany<LegacyImportEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(LegacyImportEvent::class, 'batch_id');
    }

    protected function casts(): array
    {
        return [
            'source_size' => 'integer',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'warning_rows' => 'integer',
            'error_rows' => 'integer',
            'imported_rows' => 'integer',
            'skipped_rows' => 'integer',
            'options' => 'array',
            'summary' => 'array',
            'previewed_at' => 'datetime',
            'validated_at' => 'datetime',
            'mapped_at' => 'datetime',
            'executed_at' => 'datetime',
            'verified_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
