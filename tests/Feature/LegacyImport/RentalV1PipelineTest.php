<?php

namespace Tests\Feature\LegacyImport;

use App\Domain\LegacyImport\RentalV1Executor;
use App\Domain\LegacyImport\RentalV1Mapper;
use App\Domain\LegacyImport\RentalV1Previewer;
use App\Domain\LegacyImport\RentalV1Validator;
use App\Domain\LegacyImport\RentalV1Verifier;
use App\Models\LegacyImportBatch;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RentalV1PipelineTest extends TestCase
{
    use RefreshDatabase;

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
            'uploaded_by' => $user->id,
            'source_system' => 'RentalV1',
            'source_filename' => 'minimal.sql',
            'source_path' => $path,
            'source_sha256' => hash('sha256', $sql),
            'source_size' => strlen($sql),
            'status' => 'uploaded',
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
            'sku' => 'CAM-001',
            'name' => 'Camera Test',
        ]);
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('product_rates', 3);

        $batch = app(RentalV1Verifier::class)->verify($batch, $user->id);
        $this->assertSame('verified', $batch->status);
        $this->assertTrue($batch->summary['verification']['passed']);
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
