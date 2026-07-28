<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RentalPackage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $branchId;

    private int $customerId;

    private int $ratePlanId;

    private int $productRateId;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-28 08:00:00', 'Asia/Jakarta'));

        $this->companyId = DB::table('companies')->insertGetId([
            'code' => 'TK',
            'name' => 'Together Kamera',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->branchId = $this->createPublicBranch('PNG', 'Together Kamera Ponorogo');
        $this->customerId = DB::table('customers')->insertGetId([
            'company_id' => $this->companyId,
            'registered_branch_id' => $this->branchId,
            'customer_number' => 'CUST-001',
            'name' => 'Pelanggan Test',
            'is_member' => false,
            'status' => 'active',
            'risk_level' => 'normal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $category = ProductCategory::query()->create([
            'company_id' => $this->companyId,
            'code' => 'CAMERA',
            'name' => 'Kamera',
            'is_active' => true,
            'is_public' => true,
        ]);
        $this->product = Product::query()->create([
            'company_id' => $this->companyId,
            'category_id' => $category->id,
            'sku' => 'CAM-001',
            'name' => 'Sony A6000 Body Only',
            'tracking_type' => 'serialized',
            'replacement_value' => 6000000,
            'is_rentable' => true,
            'is_active' => true,
            'is_public' => true,
        ]);
        $this->ratePlanId = DB::table('rate_plans')->insertGetId([
            'company_id' => $this->companyId,
            'branch_id' => null,
            'code' => '1D',
            'name' => '1 Hari',
            'duration_unit' => 'day',
            'duration_value' => 1,
            'grace_period_minutes' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->productRateId = DB::table('product_rates')->insertGetId([
            'product_id' => $this->product->id,
            'branch_id' => null,
            'rate_plan_id' => $this->ratePlanId,
            'amount' => 120000,
            'deposit_amount' => 500000,
            'additional_hour_amount' => 15000,
            'late_fee_amount' => 15000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createAsset($this->branchId, 'PNG-A001');
        $this->createAsset($this->branchId, 'PNG-A002');
    }

    public function test_public_product_availability_returns_capacity_estimate_and_structured_inquiry(): void
    {
        $response = $this->getJson('/rental/availability?'.http_build_query([
            'branch' => 'PNG',
            'type' => 'product',
            'slug' => $this->product->slug,
            'starts_at' => '2026-07-29T09:00',
            'ends_at' => '2026-07-30T09:00',
            'quantity' => 2,
            'rate_id' => $this->productRateId,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.availability.status', 'available')
            ->assertJsonPath('data.availability.total_units', 2)
            ->assertJsonPath('data.availability.available_units', 2)
            ->assertJsonPath('data.estimate.billing_units', 1)
            ->assertJsonPath('data.estimate.rental_amount', 240000)
            ->assertJsonPath('data.estimate.deposit_amount', 1000000)
            ->assertJsonMissingPath('data.asset_code')
            ->assertJsonMissingPath('data.serial_number');

        $inquiryUrl = (string) $response->json('data.inquiry_url');
        $message = rawurldecode((string) parse_url($inquiryUrl, PHP_URL_QUERY));

        $this->assertStringContainsString('Sony A6000 Body Only', $message);
        $this->assertStringContainsString('Together Kamera Ponorogo (PNG)', $message);
        $this->assertStringContainsString('Estimasi biaya rental', $message);
    }

    public function test_overlapping_booking_reduces_available_units_and_non_overlapping_booking_does_not(): void
    {
        $bookingId = DB::table('bookings')->insertGetId([
            'branch_id' => $this->branchId,
            'customer_id' => $this->customerId,
            'booking_number' => 'PNG-BKG-001',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => '2026-07-28 08:00:00',
            'starts_at' => '2026-07-29 08:00:00',
            'ends_at' => '2026-07-29 18:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('booking_items')->insert([
            'booking_id' => $bookingId,
            'product_id' => $this->product->id,
            'description' => $this->product->name,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/rental/availability?'.http_build_query([
            'branch' => 'PNG',
            'type' => 'product',
            'slug' => $this->product->slug,
            'starts_at' => '2026-07-29T09:00',
            'ends_at' => '2026-07-29T17:00',
            'quantity' => 2,
        ]))
            ->assertOk()
            ->assertJsonPath('data.availability.status', 'limited')
            ->assertJsonPath('data.availability.reserved_units', 1)
            ->assertJsonPath('data.availability.available_units', 1);

        $this->getJson('/rental/availability?'.http_build_query([
            'branch' => 'PNG',
            'type' => 'product',
            'slug' => $this->product->slug,
            'starts_at' => '2026-07-30T09:00',
            'ends_at' => '2026-07-30T17:00',
            'quantity' => 2,
        ]))
            ->assertOk()
            ->assertJsonPath('data.availability.status', 'available')
            ->assertJsonPath('data.availability.available_units', 2);
    }

    public function test_rental_conflicts_are_isolated_by_branch(): void
    {
        $secondBranchId = $this->createPublicBranch('MDO', 'Together Kamera Madiun');
        $this->createAsset($secondBranchId, 'MDO-A001');
        $rentalId = DB::table('rentals')->insertGetId([
            'branch_id' => $secondBranchId,
            'customer_id' => $this->customerId,
            'rental_number' => 'MDO-RNT-001',
            'status' => 'active',
            'checked_out_at' => '2026-07-29 08:00:00',
            'due_at' => '2026-07-30 18:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('rental_items')->insert([
            'rental_id' => $rentalId,
            'product_id' => $this->product->id,
            'description' => $this->product->name,
            'quantity' => 1,
            'returned_quantity' => 0,
            'status' => 'out',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parameters = [
            'type' => 'product',
            'slug' => $this->product->slug,
            'starts_at' => '2026-07-29T09:00',
            'ends_at' => '2026-07-30T09:00',
            'quantity' => 1,
        ];

        $this->getJson('/rental/availability?'.http_build_query([
            ...$parameters,
            'branch' => 'PNG',
        ]))
            ->assertOk()
            ->assertJsonPath('data.availability.available_units', 2);

        $this->getJson('/rental/availability?'.http_build_query([
            ...$parameters,
            'branch' => 'MDO',
        ]))
            ->assertOk()
            ->assertJsonPath('data.availability.rented_units', 1)
            ->assertJsonPath('data.availability.available_units', 0)
            ->assertJsonPath('data.availability.status', 'unavailable');
    }

    public function test_quantity_product_uses_period_demand_instead_of_current_counters(): void
    {
        $quantityProduct = Product::query()->create([
            'company_id' => $this->companyId,
            'category_id' => $this->product->category_id,
            'sku' => 'ACC-001',
            'name' => 'Memory Card 64 GB',
            'tracking_type' => 'quantity',
            'replacement_value' => 250000,
            'is_rentable' => true,
            'is_active' => true,
            'is_public' => true,
        ]);
        DB::table('branch_inventories')->insert([
            'branch_id' => $this->branchId,
            'product_id' => $quantityProduct->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 99,
            'quantity_rented' => 99,
            'quantity_maintenance' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bookingId = DB::table('bookings')->insertGetId([
            'branch_id' => $this->branchId,
            'customer_id' => $this->customerId,
            'booking_number' => 'PNG-BKG-QTY',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => '2026-07-28 08:00:00',
            'starts_at' => '2026-07-29 08:00:00',
            'ends_at' => '2026-07-30 18:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('booking_items')->insert([
            'booking_id' => $bookingId,
            'product_id' => $quantityProduct->id,
            'description' => $quantityProduct->name,
            'quantity' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rentalId = DB::table('rentals')->insertGetId([
            'branch_id' => $this->branchId,
            'customer_id' => $this->customerId,
            'rental_number' => 'PNG-RNT-QTY',
            'status' => 'active',
            'checked_out_at' => '2026-07-29 07:00:00',
            'due_at' => '2026-07-30 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('rental_items')->insert([
            'rental_id' => $rentalId,
            'product_id' => $quantityProduct->id,
            'description' => $quantityProduct->name,
            'quantity' => 1,
            'returned_quantity' => 0,
            'status' => 'out',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/rental/availability?'.http_build_query([
            'branch' => 'PNG',
            'type' => 'product',
            'slug' => $quantityProduct->slug,
            'starts_at' => '2026-07-29T09:00',
            'ends_at' => '2026-07-30T09:00',
            'quantity' => 2,
        ]))
            ->assertOk()
            ->assertJsonPath('data.availability.total_units', 4)
            ->assertJsonPath('data.availability.reserved_units', 2)
            ->assertJsonPath('data.availability.rented_units', 1)
            ->assertJsonPath('data.availability.available_units', 1)
            ->assertJsonPath('data.availability.status', 'limited');
    }

    public function test_package_availability_uses_the_most_limited_required_item(): void
    {
        $package = RentalPackage::query()->create([
            'company_id' => $this->companyId,
            'branch_id' => null,
            'code' => 'PKG-CAM',
            'name' => 'Paket Kamera Dua Body',
            'description' => 'Paket dua body kamera.',
            'is_active' => true,
            'is_public' => true,
        ]);
        DB::table('package_items')->insert([
            'package_id' => $package->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'is_optional' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $packageRateId = DB::table('package_rates')->insertGetId([
            'package_id' => $package->id,
            'branch_id' => null,
            'rate_plan_id' => $this->ratePlanId,
            'amount' => 200000,
            'deposit_amount' => 800000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bookingId = DB::table('bookings')->insertGetId([
            'branch_id' => $this->branchId,
            'customer_id' => $this->customerId,
            'booking_number' => 'PNG-BKG-002',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => '2026-07-28 08:00:00',
            'starts_at' => '2026-07-29 08:00:00',
            'ends_at' => '2026-07-30 18:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('booking_items')->insert([
            'booking_id' => $bookingId,
            'product_id' => $this->product->id,
            'description' => $this->product->name,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/rental/availability?'.http_build_query([
            'branch' => 'PNG',
            'type' => 'package',
            'slug' => $package->slug,
            'starts_at' => '2026-07-29T09:00',
            'ends_at' => '2026-07-30T09:00',
            'quantity' => 1,
            'rate_id' => $packageRateId,
        ]))
            ->assertOk()
            ->assertJsonPath('data.availability.status', 'unavailable')
            ->assertJsonPath('data.availability.available_units', 0)
            ->assertJsonPath('data.items.0.requested_units', 2)
            ->assertJsonPath('data.items.0.available_units', 1);
    }

    private function createPublicBranch(string $code, string $name): int
    {
        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $this->companyId,
            'code' => $code,
            'name' => $name,
            'phone' => '085784771927',
            'address' => 'Alamat cabang',
            'city' => $code === 'PNG' ? 'Ponorogo' : 'Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            'public_catalog_enabled' => true,
            'public_whatsapp' => '6285784771927',
        ] as $key => $value) {
            DB::table('branch_settings')->insert([
                'branch_id' => $branchId,
                'key' => $key,
                'value_type' => is_bool($value) ? 'boolean' : 'string',
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $branchId;
    }

    private function createAsset(int $branchId, string $assetCode): void
    {
        DB::table('assets')->insert([
            'product_id' => $this->product->id,
            'owning_branch_id' => $branchId,
            'current_branch_id' => $branchId,
            'asset_code' => $assetCode,
            'status' => 'available',
            'condition' => 'good',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
