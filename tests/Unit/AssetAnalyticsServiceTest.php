<?php

namespace Tests\Unit;

use App\Domain\Analytics\AssetAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_asset_roi_bep_and_utilization(): void
    {
        $now = CarbonImmutable::parse('2026-07-31 23:59:59');
        CarbonImmutable::setTestNow($now);
        $companyId = DB::table('companies')->insertGetId([
            'code' => 'TK',
            'name' => 'Together Kamera',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $companyId,
            'code' => 'PNR',
            'name' => 'Ponorogo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $categoryId = DB::table('product_categories')->insertGetId([
            'company_id' => $companyId,
            'code' => 'CAM',
            'name' => 'Camera',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $productId = DB::table('products')->insertGetId([
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'sku' => 'SONY-A7',
            'name' => 'Sony A7',
            'tracking_type' => 'serialized',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $assetId = DB::table('assets')->insertGetId([
            'product_id' => $productId,
            'owning_branch_id' => $branchId,
            'current_branch_id' => $branchId,
            'asset_code' => 'PNR-SONY-A7-001',
            'status' => 'available',
            'condition' => 'good',
            'purchase_date' => '2026-01-01',
            'purchase_price' => 5_000_000,
            'replacement_value' => 5_000_000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = DB::table('customers')->insertGetId([
            'company_id' => $companyId,
            'registered_branch_id' => $branchId,
            'customer_number' => 'CUS-001',
            'name' => 'Customer Test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $rentalId = DB::table('rentals')->insertGetId([
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'rental_number' => 'RNT-001',
            'status' => 'returned',
            'checked_out_at' => '2026-07-01 10:00:00',
            'due_at' => '2026-07-03 10:00:00',
            'returned_at' => '2025-07-03 10:00:00',
            'subtotal' => 1_000_000,
            'total_amount' => 1_000_000,
            'paid_amount' => 1_000_000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $itemId = DB::table('rental_items')->insertGetId([
            'rental_id' => $rentalId,
            'product_id' => $productId,
            'description' => 'Sony A7',
            'quantity' => 1,
            'returned_quantity' => 1,
            'unit_rate' => 1_000_000,
            'total_amount' => 1_000_000,
            'status' => 'returned',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('rental_item_assets')->insert([
            'rental_item_id' => $itemId,
            'asset_id' => $assetId,
            'checkout_condition' => 'good',
            'return_condition' => 'good',
            'checked_out_at' => '2026-07-01 10:00:00',
            'returned_at' => '2025-07-03 10:00:00',
            'status' => 'returned',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $openRentalId = DB::table('rentals')->insertGetId([
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'rental_number' => 'RNT-002',
            'legacy_number' => 'SW-LEGACY-002',
            'status' => 'active',
            'checked_out_at' => '2026-07-05 10:00:00',
            'due_at' => '2026-07-06 10:00:00',
            'returned_at' => null,
            'subtotal' => 500_000,
            'total_amount' => 500_000,
            'paid_amount' => 500_000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $openItemId = DB::table('rental_items')->insertGetId([
            'rental_id' => $openRentalId,
            'product_id' => $productId,
            'description' => 'Sony A7',
            'quantity' => 1,
            'returned_quantity' => 0,
            'unit_rate' => 500_000,
            'total_amount' => 500_000,
            'status' => 'out',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('rental_item_assets')->insert([
            'rental_item_id' => $openItemId,
            'asset_id' => $assetId,
            'checkout_condition' => 'good',
            'checked_out_at' => '2026-07-05 10:00:00',
            'returned_at' => null,
            'status' => 'out',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('maintenance_orders')->insert([
            'branch_id' => $branchId,
            'asset_id' => $assetId,
            'maintenance_number' => 'MNT-001',
            'type' => 'preventive',
            'status' => 'completed',
            'problem_description' => 'Sensor cleaning',
            'actual_cost' => 100_000,
            'reported_at' => '2026-07-09 10:00:00',
            'started_at' => '2026-07-09 10:00:00',
            'completed_at' => '2026-07-10 10:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $result = app(AssetAnalyticsService::class)->analyze(
            $companyId,
            [$branchId],
            [
                'from' => CarbonImmutable::parse('2026-07-01')->startOfDay(),
                'to' => CarbonImmutable::parse('2026-07-31')->endOfDay(),
                'branch_id' => null,
                'category_id' => null,
                'status' => 'all',
                'condition' => 'all',
                'search' => '',
            ],
        );

        $asset = $result['assets'][0];

        $this->assertSame(1_000_000.0, $asset['lifetime_revenue']);
        $this->assertSame(500_000.0, $asset['open_lifetime_revenue']);
        $this->assertSame(100_000.0, $asset['maintenance_cost']);
        $this->assertSame(900_000.0, $asset['net_contribution']);
        $this->assertSame(18.0, $asset['bep_progress_percent']);
        $this->assertSame(-82.0, $asset['roi_percent']);
        $this->assertSame(4_100_000.0, $asset['remaining_to_bep']);
        $this->assertSame(72.0, $asset['rented_hours']);
        $this->assertEqualsWithDelta(10.0, $asset['utilization_percent'], 0.05);
        $this->assertSame(2, $asset['rental_count']);
        $this->assertSame(1, $asset['realized_rental_count']);
        $this->assertSame(1, $asset['open_rental_count']);
        $this->assertSame(1, $asset['invalid_interval_count']);
        $this->assertSame(1, $asset['stale_active_rental_count']);
        $this->assertSame(500_000.0, $result['summary']['open_lifetime_revenue']);
        $this->assertSame(1, $result['summary']['invalid_interval_count']);
        $this->assertSame(1, $result['summary']['stale_active_rental_count']);
        $this->assertSame(57, $asset['data_quality']['score']);
        $this->assertTrue($asset['data_quality']['usage_blocked']);
        $this->assertSame('low', $asset['data_quality']['confidence']);
        $this->assertSame('monitor', $asset['recommendation']['code']);
        $this->assertSame('low', $asset['recommendation']['confidence']);
        $this->assertSame(1, $result['summary']['data_quality_blocker_asset_count']);
        $this->assertSame(0, $result['summary']['business_action_count']);
        $this->assertSame(100.0, $result['summary']['investment_coverage_percent']);
        $this->assertTrue($result['summary']['investment_is_complete']);
        $this->assertSame(1, $result['insights']['monitor_asset_count']);
        $this->assertSame(0, $result['insights']['healthy_asset_count']);
        $this->assertSame(0, $result['insights']['deferred_decision_count']);
    }

    public function test_minor_legacy_notes_do_not_replace_business_recommendation(): void
    {
        $service = app(AssetAnalyticsService::class);
        $qualityMethod = new \ReflectionMethod($service, 'dataQuality');
        $recommendationMethod = new \ReflectionMethod($service, 'businessRecommendation');
        $quality = $qualityMethod->invoke(
            $service,
            5_000_000.0,
            false,
            3,
            8,
            283,
        );
        $recommendation = $recommendationMethod->invoke(
            $service,
            5_000_000.0,
            513.9,
            25.7,
            0.0,
            'good',
            900,
            10_115_892.0,
            $quality,
        );

        $this->assertFalse($quality['usage_blocked']);
        $this->assertSame('medium', $quality['confidence']);
        $this->assertSame('profitable', $recommendation['code']);
        $this->assertSame('medium', $recommendation['confidence']);
    }

    public function test_low_utilization_without_period_revenue_prioritizes_promotion(): void
    {
        $service = app(AssetAnalyticsService::class);
        $qualityMethod = new \ReflectionMethod($service, 'dataQuality');
        $recommendationMethod = new \ReflectionMethod($service, 'businessRecommendation');
        $quality = $qualityMethod->invoke(
            $service,
            5_000_000.0,
            false,
            0,
            0,
            20,
        );
        $recommendation = $recommendationMethod->invoke(
            $service,
            5_000_000.0,
            30.0,
            5.0,
            0.0,
            'good',
            100,
            0.0,
            $quality,
        );

        $this->assertSame('promote', $recommendation['code']);
        $this->assertSame('Promosikan aset', $recommendation['label']);
        $this->assertSame('high', $recommendation['confidence']);
    }

    public function test_empty_result_preserves_executive_summary_contract(): void
    {
        $result = app(AssetAnalyticsService::class)->analyze(
            1,
            [],
            [
                'from' => CarbonImmutable::parse('2026-07-01')->startOfDay(),
                'to' => CarbonImmutable::parse('2026-07-31')->endOfDay(),
                'branch_id' => null,
                'category_id' => null,
                'status' => 'all',
                'condition' => 'all',
                'search' => '',
            ],
        );

        $this->assertSame(0.0, $result['summary']['investment_coverage_percent']);
        $this->assertFalse($result['summary']['investment_is_complete']);
        $this->assertSame(0, $result['insights']['promote_count']);
        $this->assertSame(0, $result['insights']['healthy_asset_count']);
        $this->assertSame(0, $result['insights']['monitor_asset_count']);
        $this->assertSame(0, $result['insights']['deferred_decision_count']);
    }
}
