<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'customer_id',
    'type',
    'number',
    'name_on_identity',
    'expires_at',
    'is_primary',
    'verified_at',
    'verified_by',
    'document_path',
    'metadata',
])]
class CustomerIdentity extends Model
{
    use SoftDeletes;

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
