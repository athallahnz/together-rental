<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rental_id',
    'customer_id',
    'type',
    'number',
    'holder_name',
    'status',
    'received_at',
    'received_by',
    'returned_at',
    'returned_by',
    'document_path',
    'document_original_name',
    'document_mime_type',
    'document_size',
    'notes',
])]
class RentalCollateral extends Model
{
    /** @return BelongsTo<Rental, $this> */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'returned_at' => 'datetime',
            'document_size' => 'integer',
        ];
    }
}
