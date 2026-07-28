<?php

namespace App\Models;

use App\Models\Concerns\HasPublicSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id',
    'branch_id',
    'code',
    'name',
    'slug',
    'description',
    'primary_image_path',
    'seo_title',
    'seo_description',
    'valid_from',
    'valid_until',
    'is_active',
    'is_public',
    'is_featured',
    'public_sort_order',
])]
class RentalPackage extends Model
{
    use HasPublicSlug;
    use SoftDeletes;

    protected $table = 'packages';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<PackageItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class, 'package_id');
    }

    /** @return HasMany<PackageRate, $this> */
    public function rates(): HasMany
    {
        return $this->hasMany(PackageRate::class, 'package_id');
    }

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'public_sort_order' => 'integer',
        ];
    }
}
