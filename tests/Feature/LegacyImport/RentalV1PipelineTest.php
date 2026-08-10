<?php

namespace Tests\Feature\LegacyImport;

use App\Domain\LegacyImport\LegacyImportIssueResolver;
use App\Domain\LegacyImport\LegacyImportTargetManager;
use App\Domain\LegacyImport\RentalV1Executor;
use App\Domain\LegacyImport\RentalV1Mapper;
use App\Domain\LegacyImport\RentalV1Previewer;
use App\Domain\LegacyImport\RentalV1Validator;
use App\Domain\LegacyImport\RentalV1Verifier;
use App\Models\Branch;
use App\Models\LegacyImportBatch;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RentalV1PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_booking_reference_errors_can_be_resolved_with_audited_safe_actions(): void
    {
        $this->seed(RentalFoundationSeeder::class);
        $user = User::factory()->create();
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $batch = LegacyImportBatch::query()->create([
            'id' => '0191a000-0000-7000-8000-000000000099',
            'branch_id' => $branch->id,
            'source_city' => 'Ponorogo',
            'import_prefix' => 'PNG',
            'uploaded_by' => $user->id,
            'source_system' => 'RentalV1',
            'source_filename' => 'broken-booking.sql',
            'source_sha256' => str_repeat('9', 64),
            'source_size' => 100,
            'status' => 'validated',
            'total_rows' => 2,
            'error_rows' => 2,
        ]);
        DB::table('legacy_import_tables')->insert([
            'batch_id' => $batch->id, 'source_table' => 'trx_booking_detail', 'target_table' => 'booking_items',
            'status' => 'validated', 'parsed_rows' => 2, 'error_rows' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $orphanRow = DB::table('legacy_import_rows')->insertGetId([
            'batch_id' => $batch->id, 'source_table' => 'trx_booking_detail', 'legacy_key' => '1', 'row_number' => 1,
            'payload' => json_encode(['bookingdet_booking_id' => 404]), 'status' => 'error', 'issue_count' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productRow = DB::table('legacy_import_rows')->insertGetId([
            'batch_id' => $batch->id, 'source_table' => 'trx_booking_detail', 'legacy_key' => '2', 'row_number' => 2,
            'payload' => json_encode(['bookingdet_booking_id' => 155, 'bookingdet_rentproduct_id' => 411]), 'status' => 'error', 'issue_count' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$orphanRow, 'bookingdet_booking_id', '404'], [$productRow, 'bookingdet_rentproduct_id', '411']] as [$rowId, $field, $value]) {
            DB::table('legacy_import_issues')->insert([
                'batch_id' => $batch->id, 'legacy_import_row_id' => $rowId, 'severity' => 'error',
                'code' => 'REFERENCE_NOT_FOUND', 'field' => $field, 'message' => 'Missing reference',
                'original_value' => $value, 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $resolver = app(LegacyImportIssueResolver::class);
        $this->assertSame(1, $resolver->skipOrphanBookingDetails($batch->fresh(), $user->id, 'Booking induk tidak tersedia di dump sumber.'));
        $this->assertSame(1, $resolver->createMissingProductPlaceholder($batch->fresh(), $user->id, 'Produk sumber hilang; pertahankan histori transaksi.'));

        $this->assertDatabaseHas('legacy_import_rows', ['id' => $orphanRow, 'status' => 'skipped']);
        $this->assertDatabaseHas('legacy_import_rows', ['id' => $productRow, 'status' => 'valid']);
        $this->assertDatabaseHas('products', ['sku' => 'LEG-PNG-MISSING-411', 'is_active' => false, 'is_rentable' => false]);
        $this->assertDatabaseHas('legacy_id_maps', ['batch_id' => $batch->id, 'source_table' => 'rent_product', 'legacy_id' => '411']);
        $this->assertSame(0, $batch->fresh()->error_rows);
        $this->assertSame(1, $batch->fresh()->skipped_rows);
        $this->assertDatabaseHas('legacy_import_events', ['batch_id' => $batch->id, 'event' => 'orphan_booking_details_skipped']);
        $this->assertDatabaseHas('legacy_import_events', ['batch_id' => $batch->id, 'event' => 'missing_products_resolved']);
    }

    public function test_minimal_rental_v1_dump_completes_the_full_pipeline(): void
    {
        Storage::fake('local');
        $this->seed(RentalFoundationSeeder::class);
        $user = User::factory()->create();
        $branchId = (int) DB::table('branches')->where('code', 'PNG')->value('id');
        $path = 'legacy-imports/test-batch/source.sql';
        $sql = $this->minimalSql();
        Storage::disk('local')->put($path, $sql);

        $batch = LegacyImportBatch::query()->create([
            'id' => '0191a000-0000-7000-8000-000000000001',
            'branch_id' => $branchId,
            'source_city' => 'Ponorogo',
            'import_prefix' => 'PNG',
            'uploaded_by' => $user->id,
            'source_system' => 'RentalV1',
            'source_filename' => 'minimal.sql',
            'source_path' => $path,
            'source_sha256' => hash('sha256', $sql),
            'source_size' => strlen($sql),
            'status' => 'uploaded',
            'options' => [
                'branch_code' => 'PNG',
                'source_city' => 'Ponorogo',
                'import_prefix' => 'PNG',
            ],
        ]);

        $batch = app(RentalV1Previewer::class)->preview($batch, $user->id);
        $this->assertSame('previewed', $batch->status);
        $this->assertSame(4, $batch->total_rows);
        $this->assertDatabaseMissing('legacy_import_rows', [
            'source_table' => 'user',
            'payload' => json_encode(['pwd' => 'legacy-password']),
        ]);
        $userPayload = DB::table('legacy_import_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', 'user')
            ->value('payload');
        $this->assertStringNotContainsString('legacy-password', (string) $userPayload);
        $this->assertStringNotContainsString('legacy-token', (string) $userPayload);
        $normalizedProduct = DB::table('legacy_import_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', 'rent_product')
            ->value('normalized_payload');
        $this->assertStringContainsString('PNG-CAM-001', (string) $normalizedProduct);

        $batch = app(RentalV1Validator::class)->validate($batch, $user->id);
        $this->assertSame('validated', $batch->status);
        $this->assertSame(0, $batch->error_rows);
        $this->assertSame(1, $batch->skipped_rows);

        $batch = app(RentalV1Mapper::class)->confirmBranch($batch, $user->id);
        $this->assertSame('mapped', $batch->status);

        $batch = app(RentalV1Executor::class)->execute($batch, $user->id);
        $this->assertSame('executed', $batch->status);
        $this->assertDatabaseHas('customers', [
            'customer_number' => 'LEG-PNG-1',
            'name' => 'Pelanggan Test',
        ]);
        $this->assertDatabaseHas('products', [
            'sku' => 'PNG-CAM-001',
            'name' => 'Camera Test',
        ]);
        $this->assertDatabaseHas('assets', [
            'asset_code' => 'PNG-CAM-001',
            'current_branch_id' => $branchId,
        ]);
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('product_rates', 3);

        $batch = app(RentalV1Verifier::class)->verify($batch, $user->id);
        $this->assertSame('verified', $batch->status);
        $this->assertTrue($batch->summary['verification']['passed']);
    }

    public function test_same_legacy_ids_can_be_imported_to_two_branches_with_distinct_prefixes(): void
    {
        Storage::fake('local');
        $this->seed(RentalFoundationSeeder::class);
        $user = User::factory()->create();
        $companyId = (int) DB::table('companies')->where('code', 'TK')->value('id');
        $png = Branch::query()->where('code', 'PNG')->firstOrFail();
        $mdn = Branch::query()->create([
            'company_id' => $companyId,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'province' => 'Jawa Timur',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $pngBatch = $this->createBatch(
            $user,
            $png,
            'PNG',
            '0191a000-0000-7000-8000-000000000011',
            'legacy-imports/png/source.sql',
            $this->minimalSql(),
        );
        $mdnSql = str_replace('Pelanggan Test', 'Pelanggan Madiun', $this->minimalSql());
        $mdnBatch = $this->createBatch(
            $user,
            $mdn,
            'MDN',
            '0191a000-0000-7000-8000-000000000012',
            'legacy-imports/mdn/source.sql',
            $mdnSql,
        );

        foreach ([$pngBatch, $mdnBatch] as $batch) {
            $batch = app(RentalV1Previewer::class)->preview($batch, $user->id);
            $batch = app(RentalV1Validator::class)->validate($batch, $user->id);
            $batch = app(RentalV1Mapper::class)->confirmBranch($batch, $user->id);
            app(RentalV1Executor::class)->execute($batch, $user->id);
        }

        $this->assertDatabaseHas('customers', [
            'registered_branch_id' => $png->id,
            'customer_number' => 'LEG-PNG-1',
        ]);
        $this->assertDatabaseHas('customers', [
            'registered_branch_id' => $mdn->id,
            'customer_number' => 'LEG-MDN-1',
        ]);
        $this->assertDatabaseHas('products', ['sku' => 'PNG-CAM-001']);
        $this->assertDatabaseHas('products', ['sku' => 'MDN-CAM-001']);
        $this->assertDatabaseHas('legacy_id_maps', [
            'branch_id' => $png->id,
            'source_table' => 'customer',
            'legacy_id' => '1',
        ]);
        $this->assertDatabaseHas('legacy_id_maps', [
            'branch_id' => $mdn->id,
            'source_table' => 'customer',
            'legacy_id' => '1',
        ]);
    }

    public function test_changing_target_after_preview_resets_derived_state_without_reupload(): void
    {
        Storage::fake('local');
        $this->seed(RentalFoundationSeeder::class);
        $user = User::factory()->create();
        $companyId = (int) DB::table('companies')->where('code', 'TK')->value('id');
        $png = Branch::query()->where('code', 'PNG')->firstOrFail();
        $mdn = Branch::query()->create([
            'company_id' => $companyId,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'province' => 'Jawa Timur',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $path = 'legacy-imports/target-reset/source.sql';
        $batch = $this->createBatch(
            $user,
            $png,
            'PNG',
            '0191a000-0000-7000-8000-000000000021',
            $path,
            $this->minimalSql(),
        );
        $batch = app(RentalV1Previewer::class)->preview($batch, $user->id);
        $this->assertGreaterThan(0, $batch->rows()->count());

        $batch = app(LegacyImportTargetManager::class)->updateTarget(
            $batch,
            $mdn,
            'MDN',
            $user->id,
        );

        $this->assertSame('uploaded', $batch->status);
        $this->assertSame($mdn->id, $batch->branch_id);
        $this->assertSame('Madiun', $batch->source_city);
        $this->assertSame('MDN', $batch->import_prefix);
        $this->assertSame(0, $batch->rows()->count());
        $this->assertSame(0, $batch->tables()->count());
        $this->assertSame(0, $batch->mappings()->count());
        Storage::disk('local')->assertExists($path);
    }

    public function test_target_can_change_after_mapping_until_execution_starts(): void
    {
        Storage::fake('local');
        $this->seed(RentalFoundationSeeder::class);
        $user = User::factory()->create();
        $png = Branch::query()->where('code', 'PNG')->firstOrFail();
        $batch = $this->createBatch(
            $user,
            $png,
            'PNG',
            '0191a000-0000-7000-8000-000000000025',
            'legacy-imports/remap-before-execute/source.sql',
            $this->minimalSql(),
        );
        $batch = app(RentalV1Previewer::class)->preview($batch, $user->id);
        $batch = app(RentalV1Validator::class)->validate($batch, $user->id);
        $batch = app(RentalV1Mapper::class)->confirmBranch($batch, $user->id);
        $this->assertSame('mapped', $batch->status);

        $batch = app(LegacyImportTargetManager::class)->updateTarget(
            $batch,
            $png,
            'PNR',
            $user->id,
        );

        $this->assertSame('uploaded', $batch->status);
        $this->assertSame('PNR', $batch->import_prefix);
        $this->assertNull($batch->mapped_at);
        $this->assertSame(0, $batch->rows()->count());
        $this->assertSame(0, $batch->mappings()->count());
    }

    public function test_mapped_prefix_cannot_be_reused_by_another_branch(): void
    {
        Storage::fake('local');
        $this->seed(RentalFoundationSeeder::class);
        $user = User::factory()->create();
        $companyId = (int) DB::table('companies')->where('code', 'TK')->value('id');
        $png = Branch::query()->where('code', 'PNG')->firstOrFail();
        $mdn = Branch::query()->create([
            'company_id' => $companyId,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'province' => 'Jawa Timur',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $batch = $this->createBatch(
            $user,
            $png,
            'PNG',
            '0191a000-0000-7000-8000-000000000031',
            'legacy-imports/prefix-binding/source.sql',
            $this->minimalSql(),
        );
        $batch = app(RentalV1Previewer::class)->preview($batch, $user->id);
        $batch = app(RentalV1Validator::class)->validate($batch, $user->id);
        app(RentalV1Mapper::class)->confirmBranch($batch, $user->id);

        try {
            app(LegacyImportTargetManager::class)->assertTargetIsValid($mdn, 'PNG');
            $this->fail('PREFIX yang sudah terikat seharusnya ditolak untuk cabang lain.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('import_prefix', $exception->errors());
        }
    }

    private function createBatch(
        User $user,
        Branch $branch,
        string $prefix,
        string $id,
        string $path,
        string $sql,
    ): LegacyImportBatch {
        Storage::disk('local')->put($path, $sql);

        return LegacyImportBatch::query()->create([
            'id' => $id,
            'branch_id' => $branch->id,
            'source_city' => $branch->city,
            'import_prefix' => $prefix,
            'uploaded_by' => $user->id,
            'source_system' => 'RentalV1',
            'source_filename' => basename($path),
            'source_path' => $path,
            'source_sha256' => hash('sha256', $sql),
            'source_size' => strlen($sql),
            'status' => 'uploaded',
            'options' => [
                'branch_code' => $branch->code,
                'source_city' => $branch->city,
                'import_prefix' => $prefix,
            ],
        ]);
    }

    private function minimalSql(): string
    {
        return <<<'SQL'
CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_type_id` varchar(30) DEFAULT NULL,
  `customer_noid` varchar(30) DEFAULT NULL,
  `customer_jeniskelamin` varchar(20) DEFAULT NULL,
  `customer_nohp` varchar(20) DEFAULT NULL,
  `customer_birth_place` varchar(50) DEFAULT NULL,
  `customer_birth_day` date DEFAULT NULL,
  `customer_origin_address` text,
  `customer_origin_city_id` int(11) DEFAULT NULL,
  `customer_domicile_address` text,
  `customer_domicile_city_id` int(11) DEFAULT NULL,
  `customer_instansi` varchar(50) DEFAULT NULL,
  `customer_is_member` tinyint(1) DEFAULT NULL,
  `customer_nomember` varchar(20) DEFAULT NULL,
  `customer_tgl_member` date DEFAULT NULL,
  `insert_user_id` int(11) DEFAULT NULL,
  `insert_timestamp` datetime DEFAULT NULL,
  `update_user_id` int(11) DEFAULT NULL,
  `update_timestamp` datetime DEFAULT NULL,
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB;
INSERT INTO `customer` VALUES (1,'Pelanggan Test','KTP','35020001','Laki-laki','08123456789','Ponorogo','2000-01-01','Alamat Test',NULL,NULL,NULL,'Studio Test',1,'MEM-1','2020-01-01',NULL,'2020-01-01 08:00:00',NULL,NULL);
CREATE TABLE `ref_category_product` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB;
INSERT INTO `ref_category_product` VALUES (1,'Camera');
CREATE TABLE `rent_product` (
  `rentproduct_id` int(11) NOT NULL,
  `rentproduct_code` varchar(20) DEFAULT NULL,
  `rentproduct_name` varchar(100) DEFAULT NULL,
  `rentproduct_serial_number` varchar(100) DEFAULT NULL,
  `rentproduct_is_serial` tinyint(1) DEFAULT NULL,
  `rentproduct_category_id` int(11) DEFAULT NULL,
  `rentproduct_cost_6hours` decimal(19,4) DEFAULT NULL,
  `rentproduct_cost_12hours` decimal(19,4) DEFAULT NULL,
  `rentproduct_cost_1day` decimal(19,4) DEFAULT NULL,
  `rentproduct_is_profile` tinyint(1) DEFAULT NULL,
  `rentproduct_status` varchar(20) DEFAULT NULL,
  `rentproduct_is_active` tinyint(1) DEFAULT NULL,
  `rentproduct_purchase_date` date DEFAULT NULL,
  `rentproduct_purchase_price` decimal(19,4) DEFAULT NULL,
  `insert_user_id` int(11) DEFAULT NULL,
  `insert_timestamp` datetime DEFAULT NULL,
  `update_user_id` int(11) DEFAULT NULL,
  `update_timestamp` datetime DEFAULT NULL,
  PRIMARY KEY (`rentproduct_id`)
) ENGINE=InnoDB;
INSERT INTO `rent_product` VALUES (1,'CAM-001','Camera Test','SN-001',1,1,50000,75000,100000,1,'ada',1,'2020-01-01',5000000,NULL,'2020-01-01 08:00:00',NULL,NULL);
CREATE TABLE `user` (
  `iduser` int(11) NOT NULL,
  `uname` varchar(20) DEFAULT NULL,
  `nama` varchar(40) DEFAULT NULL,
  `idkaryawan` int(11) DEFAULT NULL,
  `pwd` varchar(50) DEFAULT NULL,
  `idlevel` int(11) DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT NULL,
  `tgledit` datetime DEFAULT NULL,
  `api_token` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_logout` datetime DEFAULT NULL,
  PRIMARY KEY (`iduser`)
) ENGINE=InnoDB;
INSERT INTO `user` VALUES (1,'operator','Operator',NULL,'legacy-password',3,1,'2020-01-01 08:00:00','legacy-token',NULL,NULL);
SQL;
    }
}
