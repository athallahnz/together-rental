<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'company_id', 'code', 'name', 'type', 'requires_reference', 'is_active',
    'sort_order',
])]
class PaymentMethod extends Model
{
    protected function casts(): array
    {
        return [
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
