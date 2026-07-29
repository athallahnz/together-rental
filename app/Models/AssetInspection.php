<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'branch_id', 'asset_id', 'rental_item_id', 'rental_return_item_id', 'type',
    'condition', 'checklist', 'notes', 'inspected_by', 'inspected_at',
])]
class AssetInspection extends Model
{
    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'inspected_at' => 'datetime',
        ];
    }
}
