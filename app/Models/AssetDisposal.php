<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'branch_id',
    'asset_id',
    'disposal_number',
    'disposal_date',
    'method',
    'sale_amount',
    'reason',
    'notes',
    'disposed_by',
])]
class AssetDisposal extends Model
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

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function disposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }

    protected function casts(): array
    {
        return [
            'disposal_date' => 'date',
            'sale_amount' => 'decimal:2',
        ];
    }
}
