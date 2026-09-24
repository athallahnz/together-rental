<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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

    public function collateralType(): string
    {
        return match (strtolower($this->type)) {
            'ktp' => 'KTP',
            'sim' => 'SIM',
            'passport', 'paspor' => 'Paspor',
            'student_card', 'kartu mahasiswa' => 'Kartu Mahasiswa',
            'student_school_card', 'kartu pelajar' => 'Kartu Pelajar',
            'employee_card', 'kartu pegawai' => 'Kartu Pegawai',
            default => 'Lainnya',
        };
    }

    public function isExpiredAt(mixed $at): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        $moment = $at instanceof \DateTimeInterface
            ? Carbon::instance($at)
            : Carbon::parse((string) $at);

        return $this->expires_at->lt($moment->startOfDay());
    }

    /** @return array<string, mixed> */
    public function collateralSnapshot(): array
    {
        return [
            'customer_identity_id' => $this->id,
            'type' => $this->type,
            'number' => $this->number,
            'name_on_identity' => $this->name_on_identity,
            'expires_at' => $this->expires_at?->toDateString(),
            'is_primary' => $this->is_primary,
            'verified_at' => $this->verified_at?->toISOString(),
            'verified_by' => $this->verified_by,
            'document_present' => $this->document_path !== null,
            'captured_at' => now()->toISOString(),
        ];
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
