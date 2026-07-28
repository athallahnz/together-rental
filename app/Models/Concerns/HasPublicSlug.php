<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

trait HasPublicSlug
{
    public static function bootHasPublicSlug(): void
    {
        static::saving(function (Model $model): void {
            if (filled($model->getAttribute('slug'))) {
                $model->setAttribute(
                    'slug',
                    Str::slug((string) $model->getAttribute('slug')),
                );

                return;
            }

            $base = Str::slug((string) $model->getAttribute('name'));
            $base = $base !== '' ? $base : 'item';
            $candidate = $base;
            $suffix = 2;

            while (static::slugExists($candidate, $model)) {
                $candidate = "{$base}-{$suffix}";
                $suffix++;
            }

            $model->setAttribute('slug', $candidate);
        });
    }

    private static function slugExists(string $slug, Model $model): bool
    {
        $query = static::query()->where('slug', $slug);

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        if ($model->exists) {
            $query->whereKeyNot($model->getKey());
        }

        return $query->exists();
    }
}
