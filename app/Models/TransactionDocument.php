<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'branch_id', 'document_number', 'document_type', 'source_type', 'source_id',
    'source_reference', 'version', 'content_hash', 'snapshot', 'issued_by', 'issuer_name_snapshot', 'issued_at',
])]
class TransactionDocument extends Model
{
    /** @var list<string> */
    protected $hidden = ['snapshot'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Transaction documents are immutable. Issue a new version instead.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Transaction documents cannot be deleted through Eloquent.');
        });
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'version' => 'integer',
            'issued_at' => 'datetime',
        ];
    }
}
