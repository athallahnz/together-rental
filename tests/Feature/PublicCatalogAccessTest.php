<?php

namespace Tests\Feature;

use App\Models\CatalogBrand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCatalogAccessTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $branchId;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->companyId = DB::table('companies')->insertGetId([
            'code' => 'TK',
            'name' => 'Together Kamera',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->branchId = DB::table('branches')->insertGetId([
            'company_id' => $this->companyId,
            'code' => 'PNG',
            'name' => 'Together Kamera Ponorogo',
            'phone' => '085784771927',
            'address' => 'Jl. Brigjend Katamso Gg. VI No. 5, Ponorogo',
            'city' => 'Ponorogo',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            'public_catalog_enabled' => true,
            'public_whatsapp' => '6285784771927',
            'public_short_address' => 'Jl. Brigjend Katamso Gg. VI No. 5, Ponorogo',
            'public_opening_hours' => '09.00–21.00 WIB',
            'public_logo_path' => '/primary-logos.png',
        ] as $key => $value) {
            DB::table('branch_settings')->insert([
                'branch_id' => $this->branchId,
                'key' => $key,
                'value_type' => is_bool($value) ? 'boolean' : 'string',
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $category = ProductCategory::query()->create([
            'company_id' => $this->companyId,
            'code' => 'CAMERA',
            'name' => 'Kamera',
            'description' => 'Kamera untuk foto dan video.',
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 1,
        ]);
        $brand = CatalogBrand::query()->create([
            'company_id' => $this->companyId,
            'name' => 'Sony',
            'normalized_name' => 'sony',
            'sort_order' => 1,
            'is_active' => true,
            'is_public' => true,
            'is_featured' => true,
        ]);
        $this->product = Product::query()->create([
            'company_id' => $this->companyId,
            'category_id' => $category->id,
            'catalog_brand_id' => $brand->id,
            'sku' => 'CAM-001',
            'name' => 'Sony A6000 Body Only',
            'brand' => 'Sony',
            'model' => 'A6000',
            'tracking_type' => 'serialized',
            'description' => 'Mirrorless ringkas untuk kebutuhan foto dan video.',
            'replacement_value' => 6000000,
            'is_rentable' => true,
            'is_active' => true,
            'is_public' => true,
            'is_featured' => true,
        ]);
        $ratePlanId = DB::table('rate_plans')->insertGetId([
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
        DB::table('product_rates')->insert([
            'product_id' => $this->product->id,
            'branch_id' => $this->branchId,
            'rate_plan_id' => $ratePlanId,
            'amount' => 120000,
            'deposit_amount' => 500000,
            'additional_hour_amount' => 15000,
            'late_fee_amount' => 15000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('assets')->insert([
            'product_id' => $this->product->id,
            'owning_branch_id' => $this->branchId,
            'current_branch_id' => $this->branchId,
            'asset_code' => 'PNG-A001',
            'serial_number' => 'SECRET-SERIAL-001',
            'status' => 'available',
            'condition' => 'good',
            'purchase_price' => 5000000,
            'replacement_value' => 6000000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_public_home_and_catalog_can_be_opened_without_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/home')
                ->where('branch.code', 'PNG')
                ->has('featuredProducts', 1)
                ->where('featuredProducts.0.name', 'Sony A6000 Body Only')
                ->missing('featuredProducts.0.replacement_value')
                ->missing('featuredProducts.0.sku'));

        $this->get('/rental?branch=PNG')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/catalog')
                ->where('products.total', 1)
                ->where('products.data.0.availability.available_units', 1));
    }

    public function test_public_product_detail_does_not_expose_internal_asset_data(): void
    {
        $this->get("/rental/products/{$this->product->slug}?branch=PNG")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/product-show')
                ->where('product.name', 'Sony A6000 Body Only')
                ->missing('product.sku')
                ->missing('product.replacement_value')
                ->missing('product.purchase_price')
                ->missing('product.asset_code')
                ->missing('product.serial_number'));
    }

    public function test_non_public_product_returns_not_found(): void
    {
        $this->product->update(['is_public' => false]);

        $this->get("/rental/products/{$this->product->slug}?branch=PNG")
            ->assertNotFound();
    }
}
