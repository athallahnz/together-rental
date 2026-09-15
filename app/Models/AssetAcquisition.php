<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'branch_id',
    'acquisition_number',
    'acquisition_date',
    'vendor_name',
    'reference_number',
    'total_amount',
    'notes',
    'created_by',
])]
class AssetAcquisition extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<AssetAcquisitionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AssetAcquisitionItem::class);
    }

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }
}
