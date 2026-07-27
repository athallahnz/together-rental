<?php

namespace App\Domain\Branches;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class BranchProvisioner
{
    /** @var array<string, string> */
    private const DOCUMENT_CODES = [
        'customer' => 'CUS',
        'booking' => 'BKG',
        'rental' => 'RNT',
        'extension' => 'EXT',
        'return' => 'RET',
        'payment' => 'PAY',
        'refund' => 'RFD',
        'transfer' => 'TRF',
        'maintenance' => 'MNT',
    ];

    public function provision(Branch $branch): void
    {
        $now = now();
        $settings = [
            'currency' => ['string', 'IDR', true],
            'default_timezone' => ['string', $branch->timezone, true],
            'legacy_source_system' => ['string', 'RentalV1', false],
            'require_customer_identity' => ['boolean', true, false],
            'allow_cross_branch_return' => ['boolean', false, false],
        ];

        foreach ($settings as $key => [$type, $value, $isPublic]) {
            DB::table('branch_settings')->updateOrInsert(
                ['branch_id' => $branch->id, 'key' => $key],
                [
                    'value_type' => $type,
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'is_public' => $isPublic,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        DB::table('cash_registers')->updateOrInsert(
            ['branch_id' => $branch->id, 'code' => 'MAIN'],
            [
                'name' => 'Main Cash Register',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $this->syncNumberSequences($branch);
    }

    public function syncNumberSequences(Branch $branch): void
    {
        $now = now();

        foreach (self::DOCUMENT_CODES as $documentType => $shortCode) {
            $scope = [
                'branch_id' => $branch->id,
                'document_type' => $documentType,
                'year' => (int) $now->format('Y'),
                'month' => $documentType === 'customer'
                    ? 0
                    : (int) $now->format('m'),
            ];
            $sequence = DB::table('number_sequences')->where($scope);

            if ($sequence->exists()) {
                $sequence->update([
                    'prefix' => "{$branch->code}-{$shortCode}",
                    'padding' => 6,
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('number_sequences')->insert([
                ...$scope,
                'prefix' => "{$branch->code}-{$shortCode}",
                'last_number' => 0,
                'padding' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
