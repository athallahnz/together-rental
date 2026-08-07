<?php

namespace App\Domain\LegacyImport;

use App\Models\LegacyImportBatch;
use App\Models\LegacyImportRow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class RentalV1Executor
{
    private LegacyImportBatch $batch;

    private int $userId;

    private int $branchId;

    private int $companyId;

    private string $importPrefix;

    /** @var array<string, array{target_table: string, target_id: int}> */
    private array $idMaps = [];

    /** @var array<string, array{target_id: int|null, rule: array<string, mixed>}> */
    private array $mappings = [];

    /** @var array<string, string> */
    private array $locations = [];

    /** @var array<string, string> */
    private array $collateralTypes = [];

    public function __construct(private readonly LegacyImportRecorder $recorder) {}

    public function execute(LegacyImportBatch $batch, int $userId): LegacyImportBatch
    {
        $this->guard($batch);
        $this->batch = $batch;
        $this->userId = $userId;
        $this->branchId = (int) $batch->branch_id;
        $branch = DB::table('branches')
            ->where('id', $this->branchId)
            ->first(['company_id', 'code', 'city']);
        $this->companyId = (int) ($branch?->company_id ?? 0);
        $this->importPrefix = strtoupper(trim((string) $batch->import_prefix));

        if ($this->companyId < 1 || $branch === null) {
            throw new RuntimeException('Company atau cabang tujuan import tidak ditemukan.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $this->importPrefix)) {
            throw new RuntimeException('PREFIX import belum valid.');
        }

        if (trim((string) $batch->source_city) === '') {
            throw new RuntimeException('Nama kota tujuan import belum tersedia.');
        }

        $this->recorder->transition($batch, 'executing', 'execution_started', $userId);

        try {
            DB::transaction(function (): void {
                $this->loadLookups();

                foreach ($this->executionOrder() as $sourceTable) {
                    $this->executeTable($sourceTable);
                }

                $this->createLegacyReturns();
                $this->reconcileCurrentAssetStatuses();
                $this->refreshInventoryCounters();
            }, 3);

            $counts = DB::table('legacy_import_rows')
                ->where('batch_id', $batch->id)
                ->select('status', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $options = $batch->options ?? [];
            unset($options['failed_step']);

            $summary = $batch->summary ?? [];
            $summary['execution'] = [
                'imported_rows' => (int) ($counts['imported'] ?? 0),
                'skipped_rows' => (int) ($counts['skipped'] ?? 0),
                'completed_at' => now()->toIso8601String(),
            ];

            $batch->update([
                'status' => 'executed',
                'imported_rows' => (int) ($counts['imported'] ?? 0),
                'skipped_rows' => (int) ($counts['skipped'] ?? 0),
                'options' => $options,
                'summary' => $summary,
                'failure_message' => null,
                'executed_at' => now(),
                'failed_at' => null,
            ]);

            $this->recorder->event(
                $batch->fresh(),
                'execution_completed',
                $userId,
                'executing',
                'executed',
                $summary['execution'],
            );

            return $batch->fresh();
        } catch (Throwable $exception) {
            $this->recorder->fail($batch->fresh(), 'execution', $exception->getMessage(), $userId);

            throw $exception;
        }
    }

    private function guard(LegacyImportBatch $batch): void
    {
        $retry = $batch->status === 'failed'
            && ($batch->options['failed_step'] ?? null) === 'execution';

        if (! in_array($batch->status, ['mapped', 'queued_execution'], true) && ! $retry) {
            throw new RuntimeException("Batch berstatus [{$batch->status}] belum siap dieksekusi.");
        }

        if ($batch->error_rows > 0) {
            throw new RuntimeException('Execute diblokir karena masih ada baris error.');
        }

        if (! preg_match('/^[A-Z]{3}$/', (string) $batch->import_prefix)) {
            throw new RuntimeException('Execute diblokir karena PREFIX import belum valid.');
        }

        if (trim((string) $batch->source_city) === '') {
            throw new RuntimeException('Execute diblokir karena nama kota tujuan import belum tersedia.');
        }

        $unconfirmed = DB::table('legacy_import_mappings')
            ->where('batch_id', $batch->id)
            ->where('is_confirmed', false)
            ->exists();

        if ($unconfirmed || ! DB::table('legacy_import_mappings')->where('batch_id', $batch->id)->exists()) {
            throw new RuntimeException('Mapping batch belum dikonfirmasi.');
        }

        $branchMapping = DB::table('legacy_import_mappings')
            ->where('batch_id', $batch->id)
            ->where('mapping_type', 'branch')
            ->first(['target_id', 'transform_rule']);
        $branchRule = $branchMapping?->transform_rule === null
            ? []
            : json_decode((string) $branchMapping->transform_rule, true, flags: JSON_THROW_ON_ERROR);

        if (
            $branchMapping === null
            || (int) $branchMapping->target_id !== (int) $batch->branch_id
            || ($branchRule['import_prefix'] ?? null) !== $batch->import_prefix
            || ($branchRule['source_city'] ?? null) !== $batch->source_city
        ) {
            throw new RuntimeException(
                'Mapping cabang belum memakai identitas kota/PREFIX terbaru. Simpan tujuan import lalu ulangi Preview, Validasi, dan Mapping.',
            );
        }
    }

    /** @return list<string> */
    private function executionOrder(): array
    {
        return [
            'ref_category_product',
            'jabatan',
            'customer',
            'customer_identity',
            'karyawan',
            'promo',
            'rent_product',
            'rent_product_stock',
            'package_rental',
            'package_rental_detail',
            'rent_product_package',
            'point_member',
            'trx_booking',
            'trx_booking_detail',
            'trx_rental',
            'trx_rental_detail',
            'trx_extrarental',
            'trx_extrarental_detail',
            'trx_rental_jaminan',
            'point_transaction',
        ];
    }

    private function loadLookups(): void
    {
        $this->idMaps = DB::table('legacy_id_maps')
            ->where('source_system', (string) config('legacy-import.source_system', 'RentalV1'))
            ->where('branch_id', $this->branchId)
            ->get()
            ->mapWithKeys(static fn (object $map): array => [
                "{$map->source_table}|{$map->legacy_id}" => [
                    'target_table' => (string) $map->target_table,
                    'target_id' => (int) $map->target_id,
                ],
            ])
            ->all();

        $this->mappings = DB::table('legacy_import_mappings')
            ->where('batch_id', $this->batch->id)
            ->get()
            ->mapWithKeys(static fn (object $mapping): array => [
                "{$mapping->mapping_type}|{$mapping->source_value}" => [
                    'target_id' => $mapping->target_id === null ? null : (int) $mapping->target_id,
                    'rule' => $mapping->transform_rule === null
                        ? []
                        : json_decode($mapping->transform_rule, true, flags: JSON_THROW_ON_ERROR),
                ],
            ])
            ->all();

        $this->locations = $this->sourceRows('lokasi')
            ->mapWithKeys(static fn (LegacyImportRow $row): array => [
                (string) ($row->payload['idlokasi'] ?? '') => (string) ($row->payload['lokasi_nama'] ?? ''),
            ])
            ->filter()
            ->all();

        $this->collateralTypes = $this->sourceRows('ref_jaminantype_id')
            ->mapWithKeys(static fn (LegacyImportRow $row): array => [
                (string) ($row->payload['jaminantype_id'] ?? '') => (string) ($row->payload['jaminantype_name'] ?? ''),
            ])
            ->filter()
            ->all();
    }

    private function executeTable(string $sourceTable): void
    {
        LegacyImportRow::query()
            ->where('batch_id', $this->batch->id)
            ->where('source_table', $sourceTable)
            ->whereIn('status', ['valid', 'warning', 'imported'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($sourceTable): void {
                foreach ($rows as $row) {
                    $existing = $this->idMap($sourceTable, $row->legacy_key);
                    $target = $existing ?? $this->importRow($sourceTable, $row);

                    if ($target === null) {
                        $row->update(['status' => 'skipped']);

                        continue;
                    }

                    if ($existing === null) {
                        $this->rememberIdMap(
                            $sourceTable,
                            $row->legacy_key,
                            $target['target_table'],
                            $target['target_id'],
                        );
                    }

                    $row->update([
                        'status' => 'imported',
                        'target_table' => $target['target_table'],
                        'target_id' => $target['target_id'],
                        'imported_at' => now(),
                    ]);
                }
            });

        $imported = (int) DB::table('legacy_import_rows')
            ->where('batch_id', $this->batch->id)
            ->where('source_table', $sourceTable)
            ->where('status', 'imported')
            ->count();

        DB::table('legacy_import_tables')
            ->where('batch_id', $this->batch->id)
            ->where('source_table', $sourceTable)
            ->update([
                'status' => 'imported',
                'imported_rows' => $imported,
                'updated_at' => now(),
            ]);
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importRow(string $sourceTable, LegacyImportRow $row): ?array
    {
        return match ($sourceTable) {
            'customer' => $this->importCustomer($row),
            'customer_identity' => $this->importCustomerIdentity($row),
            'jabatan' => $this->importPosition($row),
            'karyawan' => $this->importEmployee($row),
            'promo' => $this->importPromotion($row),
            'ref_category_product' => $this->importCategory($row),
            'rent_product' => $this->importProduct($row),
            'rent_product_stock' => $this->importProductStock($row),
            'package_rental' => $this->importPackage($row),
            'package_rental_detail' => $this->importPackageItem($row),
            'rent_product_package' => $this->importProfilePackageItem($row),
            'point_member' => $this->importLoyaltyAccount($row),
            'point_transaction' => $this->importLoyaltyTransaction($row),
            'trx_booking' => $this->importBooking($row),
            'trx_booking_detail' => $this->importBookingItem($row),
            'trx_rental' => $this->importRental($row),
            'trx_rental_detail' => $this->importRentalItem($row),
            'trx_extrarental' => $this->importExtension($row),
            'trx_extrarental_detail' => $this->importExtensionItem($row),
            'trx_rental_jaminan' => $this->importCollateral($row),
            default => null,
        };
    }

    /** @return array{target_table: string, target_id: int} */
    private function importCustomer(LegacyImportRow $row): array
    {
        $data = $row->payload;
        $legacyId = RentalV1Value::integer($data['customer_id'] ?? null);
        $createdAt = RentalV1Value::dateTime($data['insert_timestamp'] ?? null) ?? now();

        $customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->companyId,
            'registered_branch_id' => $this->branchId,
            'customer_number' => "LEG-{$this->importPrefix}-{$legacyId}",
            'name' => RentalV1Value::string($data['customer_name'] ?? null, 150) ?? "Legacy Customer {$legacyId}",
            'gender' => RentalV1Value::gender($data['customer_jeniskelamin'] ?? null),
            'phone' => RentalV1Value::string($data['customer_nohp'] ?? null, 30),
            'email' => null,
            'birth_place' => RentalV1Value::string($data['customer_birth_place'] ?? null, 100),
            'birth_date' => RentalV1Value::date($data['customer_birth_day'] ?? null),
            'institution' => RentalV1Value::string($data['customer_instansi'] ?? null, 150),
            'is_member' => RentalV1Value::boolean($data['customer_is_member'] ?? null),
            'member_number' => RentalV1Value::string($data['customer_nomember'] ?? null, 40),
            'member_since' => RentalV1Value::date($data['customer_tgl_member'] ?? null),
            'status' => 'active',
            'risk_level' => 'normal',
            'notes' => "Imported from RentalV1 customer_id={$legacyId}",
            'created_by' => $this->userId,
            'updated_by' => $this->userId,
            'created_at' => $createdAt,
            'updated_at' => RentalV1Value::dateTime($data['update_timestamp'] ?? null) ?? $createdAt,
        ]);

        $identityNumber = RentalV1Value::string($data['customer_noid'] ?? null, 80);

        if ($identityNumber !== null) {
            DB::table('customer_identities')->insert([
                'customer_id' => $customerId,
                'type' => RentalV1Value::identityType($data['customer_type_id'] ?? null),
                'number' => $identityNumber,
                'name_on_identity' => RentalV1Value::string($data['customer_name'] ?? null, 150),
                'is_primary' => true,
                'metadata' => json_encode(['legacy_source' => 'customer'], JSON_THROW_ON_ERROR),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $this->insertCustomerAddress(
            $customerId,
            'identity',
            $data['customer_origin_address'] ?? null,
            $data['customer_origin_city_id'] ?? null,
            true,
            $createdAt,
        );
        $this->insertCustomerAddress(
            $customerId,
            'domicile',
            $data['customer_domicile_address'] ?? null,
            $data['customer_domicile_city_id'] ?? null,
            false,
            $createdAt,
        );

        return ['target_table' => 'customers', 'target_id' => $customerId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importCustomerIdentity(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $customerId = $this->mappedId('customer', $data['customer_id'] ?? null);

        if ($customerId === null) {
            return null;
        }

        $type = RentalV1Value::identityType($data['customer_identity_type'] ?? null);
        $number = RentalV1Value::string($data['customer_noid'] ?? null, 80);

        if ($number === null) {
            return null;
        }

        $existingId = DB::table('customer_identities')
            ->where('customer_id', $customerId)
            ->where('type', $type)
            ->where('number', $number)
            ->value('id');

        if ($existingId !== null) {
            return ['target_table' => 'customer_identities', 'target_id' => (int) $existingId];
        }

        $identityId = DB::table('customer_identities')->insertGetId([
            'customer_id' => $customerId,
            'type' => $type,
            'number' => $number,
            'is_primary' => RentalV1Value::boolean($data['customer_is_default'] ?? null),
            'metadata' => json_encode(['legacy_source' => 'customer_identity'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['target_table' => 'customer_identities', 'target_id' => $identityId];
    }

    /** @return array{target_table: string, target_id: int} */
    private function importPosition(LegacyImportRow $row): array
    {
        $data = $row->payload;
        $legacyId = RentalV1Value::integer($data['idjabatan'] ?? null);
        $positionId = DB::table('positions')->insertGetId([
            'company_id' => $this->companyId,
            'code' => "LEG-{$this->importPrefix}-POS-{$legacyId}",
            'name' => RentalV1Value::string($data['nama'] ?? null, 100) ?? "Legacy Position {$legacyId}",
            'is_active' => true,
            'created_at' => RentalV1Value::dateTime($data['tgledit'] ?? null) ?? now(),
            'updated_at' => now(),
        ]);

        return ['target_table' => 'positions', 'target_id' => $positionId];
    }

    /** @return array{target_table: string, target_id: int} */
    private function importEmployee(LegacyImportRow $row): array
    {
        $data = $row->payload;
        $legacyId = RentalV1Value::integer($data['idkaryawan'] ?? null);
        $employeeId = DB::table('employees')->insertGetId([
            'company_id' => $this->companyId,
            'primary_branch_id' => $this->branchId,
            'position_id' => $this->mappedId('jabatan', $data['idjabatan'] ?? null),
            'user_id' => null,
            'employee_number' => $this->uniqueEmployeeNumber(
                RentalV1Value::string($data['nik'] ?? null, 40) ?? "LEG-{$this->importPrefix}-EMP-{$legacyId}",
                $legacyId,
            ),
            'name' => RentalV1Value::string($data['nama'] ?? null, 150) ?? "Legacy Employee {$legacyId}",
            'identity_number' => RentalV1Value::string($data['noktp'] ?? null, 40),
            'gender' => RentalV1Value::gender($data['jnskelamin'] ?? null),
            'phone' => RentalV1Value::string($data['notelp'] ?? null, 30),
            'address' => RentalV1Value::string($data['alamat'] ?? null),
            'birth_place' => RentalV1Value::string($data['tptlahir'] ?? null, 100),
            'birth_date' => RentalV1Value::date($data['tgllahir'] ?? null),
            'status' => 'active',
            'metadata' => json_encode([
                'legacy_id' => $legacyId,
                'blood_type' => RentalV1Value::string($data['goldarah'] ?? null, 2),
                'legacy_password_imported' => false,
            ], JSON_THROW_ON_ERROR),
            'created_at' => RentalV1Value::dateTime($data['tgledit'] ?? null) ?? now(),
            'updated_at' => now(),
        ]);

        return ['target_table' => 'employees', 'target_id' => $employeeId];
    }

    /** @return array{target_table: string, target_id: int} */
    private function importCategory(LegacyImportRow $row): array
    {
        $data = $row->payload;
        $legacyId = RentalV1Value::integer($data['category_id'] ?? null);
        $categoryId = DB::table('product_categories')->insertGetId([
            'company_id' => $this->companyId,
            'code' => "LEG-{$this->importPrefix}-CAT-{$legacyId}",
            'name' => RentalV1Value::string($data['category_name'] ?? null, 100) ?? "Legacy Category {$legacyId}",
            'is_active' => true,
            'sort_order' => $legacyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['target_table' => 'product_categories', 'target_id' => $categoryId];
    }

    /** @return array{target_table: string, target_id: int} */
    private function importPromotion(LegacyImportRow $row): array
    {
        $data = $row->payload;
        $legacyId = RentalV1Value::integer($data['promo_id'] ?? null);
        $isDiscount = mb_strtolower((string) ($data['promo_type'] ?? '')) === 'diskon';
        $promotionId = DB::table('promotions')->insertGetId([
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'code' => "LEG-{$this->importPrefix}-PROMO-{$legacyId}",
            'name' => RentalV1Value::string($data['promo_name'] ?? null, 150) ?? "Legacy Promo {$legacyId}",
            'type' => $isDiscount ? 'percentage' : 'bonus_duration',
            'value' => $isDiscount
                ? RentalV1Value::decimal($data['promo_diskon_persen'] ?? null)
                : RentalV1Value::decimal($data['promo_bonus_hari'] ?? null),
            'minimum_transaction' => '0.00',
            'bonus_duration' => RentalV1Value::integer($data['promo_bonus_hari'] ?? null),
            'is_active' => RentalV1Value::boolean($data['promo_active'] ?? null),
            'rules' => json_encode([
                'legacy_multiple_days' => RentalV1Value::integer($data['promo_kelipatan_hari'] ?? null),
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['target_table' => 'promotions', 'target_id' => $promotionId];
    }

    /** @return array{target_table: string, target_id: int} */
    private function importProduct(LegacyImportRow $row): array
    {
        $data = $row->payload;
        $legacyId = RentalV1Value::integer($data['rentproduct_id'] ?? null);
        $sku = $this->prefixedLegacyCode(
            RentalV1Value::string($data['rentproduct_code'] ?? null, 50) ?? "LEG-PRD-{$legacyId}",
            50,
        );
        $trackingType = RentalV1Value::boolean($data['rentproduct_is_serial'] ?? null)
            ? 'serialized'
            : 'quantity';
        $purchasePrice = RentalV1Value::decimal($data['rentproduct_purchase_price'] ?? null);
        $productId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId,
            'category_id' => $this->mappedId('ref_category_product', $data['rentproduct_category_id'] ?? null),
            'sku' => $sku,
            'name' => RentalV1Value::string($data['rentproduct_name'] ?? null, 150) ?? "Legacy Product {$legacyId}",
            'tracking_type' => $trackingType,
            'replacement_value' => $purchasePrice,
            'is_rentable' => true,
            'is_active' => RentalV1Value::boolean($data['rentproduct_is_active'] ?? null),
            'metadata' => json_encode([
                'legacy_id' => $legacyId,
                'legacy_is_profile' => RentalV1Value::boolean($data['rentproduct_is_profile'] ?? null),
            ], JSON_THROW_ON_ERROR),
            'created_at' => RentalV1Value::dateTime($data['insert_timestamp'] ?? null) ?? now(),
            'updated_at' => RentalV1Value::dateTime($data['update_timestamp'] ?? null) ?? now(),
        ]);

        $assetStatus = RentalV1Value::productStatus($data['rentproduct_status'] ?? null);

        if ($trackingType === 'serialized') {
            $assetId = DB::table('assets')->insertGetId([
                'product_id' => $productId,
                'owning_branch_id' => $this->branchId,
                'current_branch_id' => $this->branchId,
                'asset_code' => $this->uniqueAssetCode($sku, $legacyId),
                'serial_number' => RentalV1Value::string($data['rentproduct_serial_number'] ?? null, 120),
                'status' => $assetStatus,
                'condition' => 'good',
                'purchase_date' => RentalV1Value::date($data['rentproduct_purchase_date'] ?? null),
                'purchase_price' => $purchasePrice,
                'replacement_value' => $purchasePrice,
                'notes' => "Imported from RentalV1 rentproduct_id={$legacyId}",
                'is_active' => RentalV1Value::boolean($data['rentproduct_is_active'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->rememberIdMap('rent_product_asset', (string) $legacyId, 'assets', $assetId);
        }

        DB::table('branch_inventories')->insert([
            'branch_id' => $this->branchId,
            'product_id' => $productId,
            'quantity_on_hand' => 1,
            'quantity_reserved' => 0,
            'quantity_rented' => $assetStatus === 'rented' ? 1 : 0,
            'quantity_maintenance' => 0,
            'reorder_level' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            '6H' => 'rentproduct_cost_6hours',
            '12H' => 'rentproduct_cost_12hours',
            '1D' => 'rentproduct_cost_1day',
        ] as $rateCode => $sourceColumn) {
            $this->insertProductRate($productId, $rateCode, $data[$sourceColumn] ?? null);
        }

        return ['target_table' => 'products', 'target_id' => $productId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importProductStock(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $productId = $this->mappedId('rent_product', $data['rentstock_rentproduct_id'] ?? null);

        if ($productId === null) {
            return null;
        }

        DB::table('branch_inventories')
            ->where('branch_id', $this->branchId)
            ->where('product_id', $productId)
            ->update([
                'quantity_on_hand' => max(RentalV1Value::integer($data['rentstock_stock'] ?? null), 0),
                'quantity_rented' => max(RentalV1Value::integer($data['rentstock_out'] ?? null), 0),
                'updated_at' => now(),
            ]);

        $inventoryId = DB::table('branch_inventories')
            ->where('branch_id', $this->branchId)
            ->where('product_id', $productId)
            ->value('id');

        return $inventoryId === null
            ? null
            : ['target_table' => 'branch_inventories', 'target_id' => (int) $inventoryId];
    }

    /** @return array{target_table: string, target_id: int} */
    private function importPackage(LegacyImportRow $row): array
    {
        $data = $row->payload;
        $legacyId = RentalV1Value::integer($data['package_id'] ?? null);
        $packageId = DB::table('packages')->insertGetId([
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'code' => $this->prefixedLegacyCode(
                RentalV1Value::string($data['package_code'] ?? null, 40) ?? "LEG-PKG-{$legacyId}",
                40,
            ),
            'name' => RentalV1Value::string($data['package_name'] ?? null, 150) ?? "Legacy Package {$legacyId}",
            'description' => RentalV1Value::string($data['package_serial_number'] ?? null),
            'is_active' => RentalV1Value::boolean($data['package_active'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            '6H' => 'package_cost_6hours',
            '12H' => 'package_cost_12hours',
            '1D' => 'package_cost_1day',
        ] as $rateCode => $sourceColumn) {
            $amount = RentalV1Value::decimal($data[$sourceColumn] ?? null);

            if ((float) $amount <= 0) {
                continue;
            }

            DB::table('package_rates')->insert([
                'package_id' => $packageId,
                'branch_id' => $this->branchId,
                'rate_plan_id' => $this->ratePlanId($rateCode),
                'amount' => $amount,
                'deposit_amount' => '0.00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ['target_table' => 'packages', 'target_id' => $packageId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importPackageItem(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $packageId = $this->mappedId('package_rental', $data['packagedet_package_id'] ?? null);
        $productId = $this->mappedId('rent_product', $data['packagedet_rentproduct_id'] ?? null);

        if ($packageId === null || $productId === null) {
            return null;
        }

        $itemId = DB::table('package_items')->insertGetId([
            'package_id' => $packageId,
            'product_id' => $productId,
            'quantity' => 1,
            'is_optional' => false,
            'sort_order' => RentalV1Value::integer($data['packagedet_sequence'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['target_table' => 'package_items', 'target_id' => $itemId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importProfilePackageItem(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $profileLegacyId = (string) ($data['package_profile_rentproduct_id'] ?? '');
        $profileProductId = $this->mappedId('rent_product', $profileLegacyId);
        $itemProductId = $this->mappedId('rent_product', $data['package_single_rentproduct_id'] ?? null);

        if ($profileProductId === null || $itemProductId === null) {
            return null;
        }

        $packageMap = $this->idMap('rent_product_profile_package', $profileLegacyId);

        if ($packageMap === null) {
            $profile = DB::table('products')->where('id', $profileProductId)->first();
            $packageId = DB::table('packages')->insertGetId([
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'code' => $this->prefixedLegacyCode('LEG-PROFILE-'.$profileLegacyId, 40),
                'name' => $profile?->name ?? "Legacy Profile Package {$profileLegacyId}",
                'description' => 'Generated from RentalV1 rent_product_package.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->rememberIdMap('rent_product_profile_package', $profileLegacyId, 'packages', $packageId);
        } else {
            $packageId = $packageMap['target_id'];
        }

        $existing = DB::table('package_items')
            ->where('package_id', $packageId)
            ->where('product_id', $itemProductId)
            ->value('id');

        $itemId = $existing === null
            ? DB::table('package_items')->insertGetId([
                'package_id' => $packageId,
                'product_id' => $itemProductId,
                'quantity' => 1,
                'is_optional' => false,
                'sort_order' => RentalV1Value::integer($data['package_sequence'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ])
            : (int) $existing;

        return ['target_table' => 'package_items', 'target_id' => $itemId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importLoyaltyAccount(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $customerId = $this->mappedId('customer', $data['point_customer_id'] ?? null);

        if ($customerId === null) {
            return null;
        }

        $balance = RentalV1Value::integer($data['point_total'] ?? null);
        DB::table('loyalty_accounts')->updateOrInsert(
            ['customer_id' => $customerId],
            [
                'company_id' => $this->companyId,
                'points_balance' => $balance,
                'lifetime_points' => max($balance, 0),
                'tier' => 'regular',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $accountId = DB::table('loyalty_accounts')->where('customer_id', $customerId)->value('id');

        return $accountId === null
            ? null
            : ['target_table' => 'loyalty_accounts', 'target_id' => (int) $accountId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importLoyaltyTransaction(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $customerId = $this->mappedId('customer', $data['pointtrx_customer_id'] ?? null);

        if ($customerId === null) {
            return null;
        }

        $accountId = DB::table('loyalty_accounts')->where('customer_id', $customerId)->value('id');

        if ($accountId === null) {
            DB::table('loyalty_accounts')->insert([
                'company_id' => $this->companyId,
                'customer_id' => $customerId,
                'points_balance' => 0,
                'lifetime_points' => 0,
                'tier' => 'regular',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $accountId = DB::table('loyalty_accounts')->where('customer_id', $customerId)->value('id');
        }

        $pointsIn = RentalV1Value::integer($data['pointtrx_in'] ?? null);
        $pointsOut = RentalV1Value::integer($data['pointtrx_out'] ?? null);
        $points = $pointsIn > 0 ? $pointsIn : -abs($pointsOut);
        $transactionId = DB::table('loyalty_transactions')->insertGetId([
            'loyalty_account_id' => (int) $accountId,
            'branch_id' => $this->branchId,
            'type' => $points >= 0 ? 'earn' : 'redeem',
            'points' => $points,
            'balance_after' => RentalV1Value::integer($data['pointtrx_saldo'] ?? null),
            'source_type' => null,
            'source_id' => null,
            'description' => 'Imported from RentalV1 point_transaction.',
            'occurred_at' => RentalV1Value::dateTime($data['pointtrx_date_rental'] ?? null) ?? now(),
            'expires_at' => RentalV1Value::dateTime($data['pointtrx_date_end'] ?? null),
            'created_by' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['target_table' => 'loyalty_transactions', 'target_id' => $transactionId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importBooking(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $customerId = $this->mappedId('customer', $data['booking_customer_id'] ?? null);

        if ($customerId === null) {
            return null;
        }

        $bookedAt = RentalV1Value::dateTime($data['booking_date'] ?? null)
            ?? RentalV1Value::dateTime($data['booking_date_start'] ?? null)
            ?? now()->format('Y-m-d H:i:s');
        $startsAt = RentalV1Value::dateTime($data['booking_date_start'] ?? null) ?? $bookedAt;
        $endsAt = RentalV1Value::dateTime($data['booking_date_end'] ?? null)
            ?? CarbonImmutable::parse($startsAt)->addDay()->format('Y-m-d H:i:s');
        $legacyId = RentalV1Value::integer($data['booking_id'] ?? null);
        $number = RentalV1Value::string($data['booking_number'] ?? null, 50) ?? "LEG-BKG-{$legacyId}";
        $status = RentalV1Value::bookingStatus($data['booking_status'] ?? null);
        $bookingId = DB::table('bookings')->insertGetId([
            'branch_id' => $this->branchId,
            'customer_id' => $customerId,
            'handled_by_employee_id' => $this->mappedId('karyawan', $data['booking_karyawan_id'] ?? null),
            'rate_plan_id' => $this->ratePlanForLegacyType($data['booking_typesewa'] ?? null),
            'promotion_id' => $this->mappedId('promo', $data['booking_promo_id'] ?? null),
            'booking_number' => $this->uniqueDocumentNumber('bookings', 'booking_number', $number, $legacyId),
            'legacy_number' => $number,
            'status' => $status,
            'source' => 'legacy_import',
            'booked_at' => $bookedAt,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'subtotal' => RentalV1Value::decimal($data['booking_subtotal'] ?? null),
            'discount_amount' => RentalV1Value::decimal(
                $data['booking_disc_value'] ?? $data['booking_disc_nominal'] ?? null,
            ),
            'tax_amount' => '0.00',
            'total_amount' => RentalV1Value::decimal($data['booking_total_akhir'] ?? null),
            'deposit_required' => '0.00',
            'deposit_paid' => RentalV1Value::decimal($data['booking_payment'] ?? null),
            'notes' => $this->legacyPackageNote($data['booking_package_id'] ?? null),
            'cancelled_at' => $status === 'cancelled'
                ? RentalV1Value::dateTime($data['booking_cancel_date'] ?? null)
                : null,
            'cancellation_reason' => $status === 'cancelled' ? 'Imported legacy cancellation.' : null,
            'created_by' => $this->userId,
            'updated_by' => $this->userId,
            'created_at' => $bookedAt,
            'updated_at' => $bookedAt,
        ]);

        return ['target_table' => 'bookings', 'target_id' => $bookingId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importBookingItem(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $bookingId = $this->mappedId('trx_booking', $data['bookingdet_booking_id'] ?? null);
        $productId = $this->mappedId('rent_product', $data['bookingdet_rentproduct_id'] ?? null);

        if ($bookingId === null || $productId === null) {
            return null;
        }

        $quantity = max(RentalV1Value::integer($data['bookingdet_qty'] ?? null, 1), 1);
        $unitRate = RentalV1Value::decimal($data['bookingdet_price'] ?? null);
        $additional = RentalV1Value::decimal($data['bookingdet_additional'] ?? null);
        $itemId = DB::table('booking_items')->insertGetId([
            'booking_id' => $bookingId,
            'product_id' => $productId,
            'package_id' => null,
            'description' => $this->productName($productId),
            'quantity' => $quantity,
            'unit_rate' => $unitRate,
            'additional_amount' => $additional,
            'discount_amount' => '0.00',
            'total_amount' => $this->lineTotal($quantity, $unitRate, $additional),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['target_table' => 'booking_items', 'target_id' => $itemId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importRental(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $customerId = $this->mappedId('customer', $data['rental_customer_id'] ?? null);

        if ($customerId === null) {
            return null;
        }

        $rawCheckedOutAt = RentalV1Value::dateTime($data['rental_date_start'] ?? null);
        $rawDueAt = RentalV1Value::dateTime($data['rental_date_end'] ?? null);
        $rawReturnedAt = RentalV1Value::dateTime($data['rental_date_kembali'] ?? null);
        $checkedOutAt = RentalV1Value::operationalDateTime($data['rental_date_start'] ?? null)
            ?? $this->reconstructRentalStart($row, $rawCheckedOutAt);
        $dueAt = RentalV1Value::operationalDateTime($data['rental_date_end'] ?? null)
            ?? $this->reconstructRelativeDateTime(
                $checkedOutAt,
                $rawCheckedOutAt,
                $rawDueAt,
                CarbonImmutable::parse($checkedOutAt)->addDay()->format('Y-m-d H:i:s'),
            );
        $status = RentalV1Value::rentalStatus($data['rental_status'] ?? null);
        $returnedAt = RentalV1Value::operationalDateTime($data['rental_date_kembali'] ?? null);

        if (
            $status === 'returned'
            && (
                $returnedAt === null
                || CarbonImmutable::parse($returnedAt)->lessThan(CarbonImmutable::parse($checkedOutAt))
            )
        ) {
            $returnedAt = $this->reconstructReturnedAt(
                $checkedOutAt,
                $dueAt,
                $rawCheckedOutAt,
                $rawReturnedAt,
            );
        }

        $dateCorrections = array_filter([
            'rental_date_start' => $this->dateCorrection(
                $data['rental_date_start'] ?? null,
                $checkedOutAt,
            ),
            'rental_date_end' => $this->dateCorrection(
                $data['rental_date_end'] ?? null,
                $dueAt,
            ),
            'rental_date_kembali' => $this->dateCorrection(
                $data['rental_date_kembali'] ?? null,
                $returnedAt,
            ),
        ]);
        $total = RentalV1Value::decimal($data['rental_total_akhir'] ?? null);
        $paid = RentalV1Value::decimal($data['rental_bayar'] ?? null);
        $legacyId = RentalV1Value::integer($data['rental_id'] ?? null);
        $number = RentalV1Value::string($data['rental_number'] ?? null, 50) ?? "LEG-RNT-{$legacyId}";
        $notes = array_filter([
            $this->legacyPackageNote($data['rental_package_id'] ?? null),
            $dateCorrections === []
                ? null
                : 'RentalV1 datetime reconstruction: '.json_encode(
                    $dateCorrections,
                    JSON_THROW_ON_ERROR,
                ),
        ]);
        $rentalId = DB::table('rentals')->insertGetId([
            'branch_id' => $this->branchId,
            'booking_id' => $this->mappedId('trx_booking', $data['rental_booking_id'] ?? null),
            'customer_id' => $customerId,
            'guarantor_customer_id' => $this->mappedId('customer', $data['rental_penjamin_customer_id'] ?? null),
            'checked_out_by_employee_id' => $this->mappedId('karyawan', $data['rental_out_karyawan_id'] ?? null),
            'checked_in_by_employee_id' => $this->mappedId('karyawan', $data['rental_in_karyawan_id'] ?? null),
            'rate_plan_id' => $this->ratePlanForLegacyType($data['rental_typesewa'] ?? null),
            'promotion_id' => $this->mappedId('promo', $data['rental_promo_id'] ?? null),
            'rental_number' => $this->uniqueDocumentNumber('rentals', 'rental_number', $number, $legacyId),
            'legacy_number' => $number,
            'status' => $status,
            'checked_out_at' => $checkedOutAt,
            'due_at' => $dueAt,
            'returned_at' => $returnedAt,
            'subtotal' => RentalV1Value::decimal($data['rental_subtotal'] ?? null),
            'booking_payment_amount' => RentalV1Value::decimal($data['rental_booking_payment'] ?? null),
            'deposit_amount' => RentalV1Value::decimal($data['rental_down_payment'] ?? null),
            'discount_amount' => RentalV1Value::decimal(
                $data['rental_disc_value'] ?? $data['rental_disc_nominal'] ?? null,
            ),
            'tax_amount' => '0.00',
            'total_amount' => $total,
            'paid_amount' => $paid,
            'balance_due' => number_format(max((float) $total - (float) $paid, 0), 2, '.', ''),
            'late_fee_amount' => RentalV1Value::decimal($data['rental_denda_terlambat'] ?? null),
            'damage_fee_amount' => RentalV1Value::decimal($data['rental_denda_kerusakan'] ?? null),
            'notes' => $notes === [] ? null : implode(PHP_EOL, $notes),
            'created_by' => $this->userId,
            'updated_by' => $this->userId,
            'created_at' => $checkedOutAt,
            'updated_at' => $returnedAt ?? $checkedOutAt,
        ]);

        return ['target_table' => 'rentals', 'target_id' => $rentalId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importRentalItem(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $rentalId = $this->mappedId('trx_rental', $data['rentaldet_rental_id'] ?? null);
        $productLegacyId = $data['rentaldet_rentproduct_id'] ?? null;
        $productId = $this->mappedId('rent_product', $productLegacyId);

        if ($rentalId === null || $productId === null) {
            return null;
        }

        $rental = DB::table('rentals')->where('id', $rentalId)->first();
        $quantity = max(RentalV1Value::integer($data['rentaldet_qty'] ?? null, 1), 1);
        $unitRate = RentalV1Value::decimal($data['rentaldet_price'] ?? null);
        $additional = RentalV1Value::decimal($data['rentaldet_additional'] ?? null);
        $returned = $rental?->status === 'returned';
        $itemId = DB::table('rental_items')->insertGetId([
            'rental_id' => $rentalId,
            'booking_item_id' => null,
            'product_id' => $productId,
            'description' => $this->productName($productId),
            'quantity' => $quantity,
            'returned_quantity' => $returned ? $quantity : 0,
            'unit_rate' => $unitRate,
            'additional_amount' => $additional,
            'discount_amount' => '0.00',
            'total_amount' => $this->lineTotal($quantity, $unitRate, $additional),
            'due_at' => $rental?->due_at,
            'status' => $returned ? 'returned' : 'out',
            'created_at' => $rental?->checked_out_at ?? now(),
            'updated_at' => $rental?->returned_at ?? now(),
        ]);

        $assetId = $this->mappedId('rent_product_asset', $productLegacyId);

        if ($assetId !== null) {
            DB::table('rental_item_assets')->insert([
                'rental_item_id' => $itemId,
                'asset_id' => $assetId,
                'checkout_condition' => 'good',
                'return_condition' => $returned ? 'good' : null,
                'checked_out_at' => $rental?->checked_out_at,
                'returned_at' => $returned ? $rental?->returned_at : null,
                'status' => $returned ? 'returned' : 'out',
                'notes' => 'Imported RentalV1 asset assignment.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ['target_table' => 'rental_items', 'target_id' => $itemId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importExtension(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $rentalId = $this->mappedId('trx_rental', $data['extrarental_rental_id'] ?? null);

        if ($rentalId === null) {
            return null;
        }

        $rental = DB::table('rentals')->where('id', $rentalId)->first();
        $previousDueAt = $rental?->due_at
            ?? RentalV1Value::dateTime($data['extrarental_date_start'] ?? null)
            ?? now()->format('Y-m-d H:i:s');
        $extendedDueAt = RentalV1Value::dateTime($data['extrarental_date_end'] ?? null)
            ?? CarbonImmutable::parse($previousDueAt)->addDay()->format('Y-m-d H:i:s');
        $legacyId = RentalV1Value::integer($data['extrarental_id'] ?? null);
        $number = RentalV1Value::string($data['extrarental_number'] ?? null, 50) ?? "LEG-EXT-{$legacyId}";
        $status = RentalV1Value::extensionStatus($data['extrarental_status'] ?? null);
        $extensionId = DB::table('rental_extensions')->insertGetId([
            'branch_id' => $this->branchId,
            'rental_id' => $rentalId,
            'extension_number' => $this->uniqueDocumentNumber(
                'rental_extensions',
                'extension_number',
                $number,
                $legacyId,
            ),
            'legacy_number' => $number,
            'previous_due_at' => $previousDueAt,
            'extended_due_at' => $extendedDueAt,
            'status' => $status,
            'subtotal' => RentalV1Value::decimal($data['extrarental_subtotal'] ?? null),
            'discount_amount' => RentalV1Value::decimal(
                $data['extrarental_disc_value'] ?? $data['extrarental_disc_nominal'] ?? null,
            ),
            'total_amount' => RentalV1Value::decimal($data['extrarental_total_akhir'] ?? null),
            'paid_amount' => RentalV1Value::decimal($data['extrarental_bayar'] ?? null),
            'notes' => $this->legacyPackageNote($data['extrarental_package_id'] ?? null),
            'created_by' => $this->userId,
            'approved_by' => $this->userId,
            'approved_at' => RentalV1Value::dateTime($data['extrarental_date_start'] ?? null) ?? now(),
            'created_at' => RentalV1Value::dateTime($data['extrarental_date_start'] ?? null) ?? now(),
            'updated_at' => RentalV1Value::dateTime($data['extrarental_date_kembali'] ?? null) ?? now(),
        ]);

        if (
            $rental !== null
            && CarbonImmutable::parse($extendedDueAt)
                ->greaterThan(CarbonImmutable::parse($rental->due_at ?? $previousDueAt))
        ) {
            DB::table('rentals')->where('id', $rentalId)->update([
                'due_at' => $extendedDueAt,
                'updated_at' => now(),
            ]);
        }

        return ['target_table' => 'rental_extensions', 'target_id' => $extensionId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importExtensionItem(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $extensionId = $this->mappedId('trx_extrarental', $data['extradet_extrarental_id'] ?? null);
        $productId = $this->mappedId('rent_product', $data['extradet_rentproduct_id'] ?? null);

        if ($extensionId === null || $productId === null) {
            return null;
        }

        $extension = DB::table('rental_extensions')->where('id', $extensionId)->first();

        if ($extension === null) {
            return null;
        }

        $rentalItemId = DB::table('rental_items')
            ->where('rental_id', $extension->rental_id)
            ->where('product_id', $productId)
            ->value('id');

        if ($rentalItemId === null) {
            return null;
        }

        $quantity = max(RentalV1Value::integer($data['extradet_qty'] ?? null, 1), 1);
        $unitRate = RentalV1Value::decimal($data['extradet_price'] ?? null);
        $additional = RentalV1Value::decimal($data['extradet_additional'] ?? null);
        $itemId = DB::table('rental_extension_items')->insertGetId([
            'rental_extension_id' => $extensionId,
            'rental_item_id' => (int) $rentalItemId,
            'quantity' => $quantity,
            'previous_due_at' => $extension->previous_due_at,
            'extended_due_at' => $extension->extended_due_at,
            'unit_rate' => $unitRate,
            'additional_amount' => $additional,
            'total_amount' => $this->lineTotal($quantity, $unitRate, $additional),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['target_table' => 'rental_extension_items', 'target_id' => $itemId];
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function importCollateral(LegacyImportRow $row): ?array
    {
        $data = $row->payload;
        $rentalLegacyId = $data['rentaljaminan_rental_id'] ?? null;
        $rentalId = $this->mappedId('trx_rental', $rentalLegacyId);

        if ($rentalId === null) {
            return null;
        }

        $rental = DB::table('rentals')->where('id', $rentalId)->first();
        $rawType = RentalV1Value::string($data['rentaljaminan_jaminantype_id'] ?? null, 40);
        $type = $this->collateralTypes[(string) $rawType] ?? $rawType ?? 'Lainnya';
        $sequence = RentalV1Value::integer($data['rentaljaminan_nourut'] ?? null);
        $number = RentalV1Value::string($data['rentaljaminan_nomor'] ?? null, 100)
            ?? "LEGACY-{$rentalLegacyId}-{$sequence}";
        $returned = $rental?->status === 'returned';
        $collateralId = DB::table('rental_collaterals')->insertGetId([
            'rental_id' => $rentalId,
            'customer_id' => $rental?->customer_id,
            'type' => mb_substr($type, 0, 40),
            'number' => $number,
            'holder_name' => null,
            'status' => $returned ? 'returned' : 'held',
            'received_at' => $rental?->checked_out_at,
            'returned_at' => $returned ? $rental?->returned_at : null,
            'returned_by' => $returned ? $this->userId : null,
            'notes' => RentalV1Value::string($data['rentaljaminan_keterangan'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['target_table' => 'rental_collaterals', 'target_id' => $collateralId];
    }

    private function createLegacyReturns(): void
    {
        LegacyImportRow::query()
            ->where('batch_id', $this->batch->id)
            ->where('source_table', 'trx_rental')
            ->where('status', 'imported')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $data = $row->payload;

                    if (($data['rental_status'] ?? null) !== 'kembali') {
                        continue;
                    }

                    $legacyId = (string) ($data['rental_id'] ?? '');

                    if ($this->idMap('trx_rental_return', $legacyId) !== null) {
                        continue;
                    }

                    $rentalId = $this->mappedId('trx_rental', $legacyId);

                    if ($rentalId === null) {
                        continue;
                    }

                    $rental = DB::table('rentals')->where('id', $rentalId)->first();

                    if ($rental === null) {
                        continue;
                    }

                    $returnedAt = $rental->returned_at ?? $rental->due_at ?? $rental->checked_out_at ?? now();
                    $returnId = DB::table('rental_returns')->insertGetId([
                        'branch_id' => $this->branchId,
                        'rental_id' => $rentalId,
                        'received_by_employee_id' => $rental->checked_in_by_employee_id,
                        'return_number' => $this->uniqueDocumentNumber(
                            'rental_returns',
                            'return_number',
                            'RET-'.$rental->rental_number,
                            (int) $legacyId,
                        ),
                        'type' => 'final',
                        'status' => 'completed',
                        'returned_at' => $returnedAt,
                        'late_fee_amount' => $rental->late_fee_amount,
                        'damage_fee_amount' => $rental->damage_fee_amount,
                        'cleaning_fee_amount' => '0.00',
                        'discount_amount' => '0.00',
                        'total_charge_amount' => number_format(
                            (float) $rental->late_fee_amount + (float) $rental->damage_fee_amount,
                            2,
                            '.',
                            '',
                        ),
                        'notes' => 'Generated from RentalV1 returned rental.',
                        'created_by' => $this->userId,
                        'created_at' => $returnedAt,
                        'updated_at' => $returnedAt,
                    ]);

                    $items = DB::table('rental_items')->where('rental_id', $rentalId)->get();

                    foreach ($items as $item) {
                        $assetId = DB::table('rental_item_assets')
                            ->where('rental_item_id', $item->id)
                            ->value('asset_id');

                        $returnItemId = DB::table('rental_return_items')->insertGetId([
                            'rental_return_id' => $returnId,
                            'rental_item_id' => $item->id,
                            'asset_id' => $assetId,
                            'quantity' => $item->quantity,
                            'condition' => 'good',
                            'status' => 'returned',
                            'late_fee_amount' => '0.00',
                            'damage_fee_amount' => '0.00',
                            'cleaning_fee_amount' => '0.00',
                            'notes' => 'Imported legacy return.',
                            'created_at' => $returnedAt,
                            'updated_at' => $returnedAt,
                        ]);

                        $this->rememberIdMap(
                            'trx_rental_return_item',
                            "{$legacyId}|{$item->id}",
                            'rental_return_items',
                            $returnItemId,
                        );
                    }

                    $this->rememberIdMap('trx_rental_return', $legacyId, 'rental_returns', $returnId);
                }
            });
    }

    private function reconcileCurrentAssetStatuses(): void
    {
        LegacyImportRow::query()
            ->where('batch_id', $this->batch->id)
            ->where('source_table', 'rent_product')
            ->where('status', 'imported')
            ->orderBy('id')
            ->chunkById(250, function ($rows): void {
                foreach ($rows as $row) {
                    $legacyId = (string) ($row->payload['rentproduct_id'] ?? '');
                    $assetId = $this->mappedId('rent_product_asset', $legacyId);

                    if ($assetId === null) {
                        continue;
                    }

                    DB::table('assets')->where('id', $assetId)->update([
                        'status' => RentalV1Value::productStatus(
                            $row->payload['rentproduct_status'] ?? null,
                        ),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function refreshInventoryCounters(): void
    {
        DB::table('branch_inventories')
            ->where('branch_id', $this->branchId)
            ->orderBy('id')
            ->chunkById(250, function (Collection $inventories): void {
                foreach ($inventories as $inventory) {
                    $serialized = DB::table('products')
                        ->where('id', $inventory->product_id)
                        ->value('tracking_type') === 'serialized';

                    if (! $serialized) {
                        continue;
                    }

                    $onHand = DB::table('assets')
                        ->where('product_id', $inventory->product_id)
                        ->where('current_branch_id', $this->branchId)
                        ->where('is_active', true)
                        ->count();
                    $rented = DB::table('assets')
                        ->where('product_id', $inventory->product_id)
                        ->where('current_branch_id', $this->branchId)
                        ->where('status', 'rented')
                        ->count();
                    $maintenance = DB::table('assets')
                        ->where('product_id', $inventory->product_id)
                        ->where('current_branch_id', $this->branchId)
                        ->where('status', 'maintenance')
                        ->count();

                    DB::table('branch_inventories')->where('id', $inventory->id)->update([
                        'quantity_on_hand' => $onHand,
                        'quantity_rented' => $rented,
                        'quantity_maintenance' => $maintenance,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /** @return Collection<int, LegacyImportRow> */
    private function sourceRows(string $sourceTable): Collection
    {
        return LegacyImportRow::query()
            ->where('batch_id', $this->batch->id)
            ->where('source_table', $sourceTable)
            ->orderBy('id')
            ->get();
    }

    private function insertCustomerAddress(
        int $customerId,
        string $type,
        mixed $address,
        mixed $cityId,
        bool $isPrimary,
        mixed $createdAt,
    ): void {
        $address = RentalV1Value::string($address);

        if ($address === null) {
            return;
        }

        DB::table('customer_addresses')->insert([
            'customer_id' => $customerId,
            'type' => $type,
            'address' => $address,
            'city' => $this->locations[(string) $cityId] ?? null,
            'is_primary' => $isPrimary,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function insertProductRate(int $productId, string $rateCode, mixed $sourceAmount): void
    {
        $amount = RentalV1Value::decimal($sourceAmount);

        if ((float) $amount <= 0) {
            return;
        }

        DB::table('product_rates')->insert([
            'product_id' => $productId,
            'branch_id' => $this->branchId,
            'rate_plan_id' => $this->ratePlanId($rateCode),
            'amount' => $amount,
            'deposit_amount' => '0.00',
            'additional_hour_amount' => '0.00',
            'late_fee_amount' => '0.00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function prefixedLegacyCode(string $candidate, int $maxLength): string
    {
        $candidate = strtoupper(trim($candidate));

        if (str_starts_with($candidate, $this->importPrefix.'-')) {
            return mb_substr($candidate, 0, $maxLength);
        }

        return mb_substr($this->importPrefix.'-'.$candidate, 0, $maxLength);
    }

    private function uniqueAssetCode(string $candidate, int $legacyId): string
    {
        $candidate = mb_substr($candidate, 0, 60);

        if (! DB::table('assets')->where('asset_code', $candidate)->exists()) {
            return $candidate;
        }

        return mb_substr("{$this->importPrefix}-LEG-ASSET-{$legacyId}", 0, 60);
    }

    private function uniqueEmployeeNumber(string $candidate, int $legacyId): string
    {
        if (! DB::table('employees')
            ->where('company_id', $this->companyId)
            ->where('employee_number', $candidate)
            ->exists()) {
            return $candidate;
        }

        $suffix = "-{$this->importPrefix}-L{$legacyId}";

        return mb_substr($candidate, 0, 40 - mb_strlen($suffix)).$suffix;
    }

    private function uniqueDocumentNumber(
        string $table,
        string $column,
        string $candidate,
        int $legacyId,
    ): string {
        $candidate = mb_substr($candidate, 0, 50);

        if (! DB::table($table)
            ->where('branch_id', $this->branchId)
            ->where($column, $candidate)
            ->exists()) {
            return $candidate;
        }

        $suffix = "-L{$legacyId}";

        return mb_substr($candidate, 0, 50 - mb_strlen($suffix)).$suffix;
    }

    private function legacyPackageNote(mixed $legacyPackageId): ?string
    {
        if ($legacyPackageId === null || $legacyPackageId === '' || (string) $legacyPackageId === '0') {
            return null;
        }

        $packageId = $this->mappedId('package_rental', $legacyPackageId);

        return $packageId === null
            ? "RentalV1 package_id={$legacyPackageId}"
            : "RentalV1 package_id={$legacyPackageId}; V2 package_id={$packageId}";
    }

    private function reconstructRentalStart(
        LegacyImportRow $row,
        ?string $rawDateTime,
    ): string {
        $previous = $this->neighboringRentalDate($row, '<', 'desc');
        $next = $this->neighboringRentalDate($row, '>', 'asc');
        $anchorDate = $previous !== null && $next !== null
            && substr($previous, 0, 10) === substr($next, 0, 10)
                ? substr($previous, 0, 10)
                : substr($previous ?? $next ?? now()->format('Y-m-d H:i:s'), 0, 10);
        $time = $rawDateTime === null
            ? '00:00:00'
            : CarbonImmutable::parse($rawDateTime)->format('H:i:s');

        return "{$anchorDate} {$time}";
    }

    private function neighboringRentalDate(
        LegacyImportRow $row,
        string $operator,
        string $direction,
    ): ?string {
        $neighbors = LegacyImportRow::query()
            ->where('batch_id', $this->batch->id)
            ->where('source_table', 'trx_rental')
            ->where('id', $operator, $row->id)
            ->orderBy('id', $direction)
            ->limit(10)
            ->get();

        foreach ($neighbors as $neighbor) {
            $dateTime = RentalV1Value::operationalDateTime(
                $neighbor->payload['rental_date_start'] ?? null,
            );

            if ($dateTime !== null) {
                return $dateTime;
            }
        }

        return null;
    }

    private function reconstructRelativeDateTime(
        string $correctedStart,
        ?string $rawStart,
        ?string $rawTarget,
        string $fallback,
    ): string {
        if ($rawStart === null || $rawTarget === null) {
            return $fallback;
        }

        $seconds = CarbonImmutable::parse($rawStart)
            ->diffInSeconds(CarbonImmutable::parse($rawTarget), false);

        if ($seconds < 0 || $seconds > 31 * 86400) {
            return $fallback;
        }

        return CarbonImmutable::parse($correctedStart)
            ->addSeconds((int) $seconds)
            ->format('Y-m-d H:i:s');
    }

    private function reconstructReturnedAt(
        string $checkedOutAt,
        string $dueAt,
        ?string $rawCheckedOutAt,
        ?string $rawReturnedAt,
    ): string {
        if (
            $rawCheckedOutAt !== null
            && $rawReturnedAt !== null
            && RentalV1Value::operationalDateTime($rawCheckedOutAt) === null
        ) {
            return $this->reconstructRelativeDateTime(
                $checkedOutAt,
                $rawCheckedOutAt,
                $rawReturnedAt,
                $dueAt,
            );
        }

        if ($rawReturnedAt === null) {
            return $dueAt;
        }

        $candidate = CarbonImmutable::parse(substr($dueAt, 0, 10).' '.
            CarbonImmutable::parse($rawReturnedAt)->format('H:i:s'));

        if ($candidate->lessThan(CarbonImmutable::parse($checkedOutAt))) {
            $candidate = $candidate->addDay();
        }

        return $candidate->format('Y-m-d H:i:s');
    }

    /** @return array{original: mixed, corrected: string|null}|null */
    private function dateCorrection(mixed $original, ?string $corrected): ?array
    {
        if (
            RentalV1Value::dateTime($original) === null
            || RentalV1Value::operationalDateTime($original) !== null
        ) {
            return null;
        }

        return [
            'original' => $original,
            'corrected' => $corrected,
        ];
    }

    private function lineTotal(int $quantity, string $unitRate, string $additional): string
    {
        return number_format(($quantity * (float) $unitRate) + (float) $additional, 2, '.', '');
    }

    private function productName(int $productId): string
    {
        return (string) (DB::table('products')->where('id', $productId)->value('name') ?? "Product {$productId}");
    }

    private function ratePlanForLegacyType(mixed $legacyType): int
    {
        $key = (string) ($legacyType ?? '2');
        $mapping = $this->mappings["rental_type|{$key}"]
            ?? $this->mappings['rental_type|2']
            ?? null;

        if ($mapping === null || $mapping['target_id'] === null) {
            throw new RuntimeException("Mapping tipe rental [{$key}] tidak tersedia.");
        }

        return $mapping['target_id'];
    }

    private function ratePlanId(string $code): int
    {
        foreach ($this->mappings as $mapping) {
            if (($mapping['rule']['code'] ?? null) === $code && $mapping['target_id'] !== null) {
                return $mapping['target_id'];
            }
        }

        $ratePlanId = DB::table('rate_plans')
            ->where('company_id', $this->companyId)
            ->where('code', $code)
            ->value('id');

        if ($ratePlanId === null) {
            throw new RuntimeException("Rate plan [{$code}] tidak ditemukan.");
        }

        return (int) $ratePlanId;
    }

    /** @return array{target_table: string, target_id: int}|null */
    private function idMap(string $sourceTable, string $legacyId): ?array
    {
        return $this->idMaps["{$sourceTable}|{$legacyId}"] ?? null;
    }

    private function mappedId(string $sourceTable, mixed $legacyId): ?int
    {
        if ($legacyId === null || $legacyId === '' || (string) $legacyId === '0') {
            return null;
        }

        return $this->idMap($sourceTable, (string) $legacyId)['target_id'] ?? null;
    }

    private function rememberIdMap(
        string $sourceTable,
        string $legacyId,
        string $targetTable,
        int $targetId,
    ): void {
        $key = "{$sourceTable}|{$legacyId}";

        if (isset($this->idMaps[$key])) {
            return;
        }

        DB::table('legacy_id_maps')->insert([
            'batch_id' => $this->batch->id,
            'branch_id' => $this->branchId,
            'source_system' => (string) config('legacy-import.source_system', 'RentalV1'),
            'source_table' => $sourceTable,
            'legacy_id' => $legacyId,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'metadata' => json_encode([
                'imported_by' => $this->userId,
                'import_prefix' => $this->importPrefix,
                'source_city' => $this->batch->source_city,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->idMaps[$key] = [
            'target_table' => $targetTable,
            'target_id' => $targetId,
        ];
    }
}
