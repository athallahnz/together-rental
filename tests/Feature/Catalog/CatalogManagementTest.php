<?php

namespace Tests\Feature\Catalog;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\CatalogBrand;
use App\Models\CatalogBrandAlias;
use App\Models\CatalogModel;
use App\Models\CatalogModelAlias;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RatePlan;
use App\Models\RentalPackage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_open_catalog_and_product_detail(): void
    {
        [$user] = $this->superAdministrator();
        $category = $this->category($user);
        $product = $this->product($user, $category);

        $this->actingAs($user)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('catalog/index')
                ->where('summary.products', 1)
                ->where('products.data.0.id', $product->id)
                ->where('permissions.manage', true));

        $this->actingAs($user)
            ->get(route('catalog.products.show', $product))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('catalog/product-show')
                ->where('product.id', $product->id)
                ->where('product.category.id', $category->id));
    }

    public function test_category_and_product_can_be_created_with_unique_codes(): void
    {
        [$user] = $this->superAdministrator();

        $this->actingAs($user)
            ->post(route('catalog.categories.store'), [
                'parent_id' => null,
                'code' => 'camera',
                'name' => 'Camera',
                'description' => 'Kamera rental.',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertSessionHasNoErrors();

        $category = ProductCategory::query()->where('code', 'CAMERA')->firstOrFail();

        $this->actingAs($user)
            ->post(route('catalog.products.store'), $this->productPayload($category))
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'CAM-SONY-A7',
            'tracking_type' => 'serialized',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'event' => 'catalog.product.created',
        ]);

        $this->actingAs($user)
            ->post(route('catalog.products.store'), $this->productPayload($category))
            ->assertSessionHasErrors('sku');
    }

    public function test_branch_role_cannot_create_global_rate_plan(): void
    {
        [$administrator, $branch] = $this->superAdministrator();
        $operator = $this->catalogOperator(
            (int) $administrator->company_id,
            $branch,
        );

        $payload = [
            'branch_id' => null,
            'code' => '3D',
            'name' => 'Three Days',
            'duration_unit' => 'day',
            'duration_value' => 3,
            'grace_period_minutes' => 30,
            'is_active' => true,
        ];

        $this->actingAs($operator)
            ->post(route('catalog.rate-plans.store'), $payload)
            ->assertSessionHasErrors('branch_id');

        $this->actingAs($operator)
            ->post(route('catalog.rate-plans.store'), [
                ...$payload,
                'branch_id' => $branch->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rate_plans', [
            'company_id' => $administrator->company_id,
            'branch_id' => $branch->id,
            'code' => '3D',
        ]);
    }

    public function test_product_rate_is_unique_and_respects_branch_access(): void
    {
        [$administrator, $branch] = $this->superAdministrator();
        $category = $this->category($administrator);
        $product = $this->product($administrator, $category);
        $otherBranch = Branch::query()->create([
            'company_id' => $administrator->company_id,
            'code' => 'SBY',
            'name' => 'Together Kamera Surabaya',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $operator = $this->catalogOperator(
            (int) $administrator->company_id,
            $branch,
        );
        $ratePlan = RatePlan::query()
            ->where('company_id', $administrator->company_id)
            ->where('code', '1D')
            ->firstOrFail();
        $payload = $this->ratePayload($branch, $ratePlan);

        $this->actingAs($operator)
            ->post(route('catalog.product-rates.store', $product), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($operator)
            ->post(route('catalog.product-rates.store', $product), $payload)
            ->assertSessionHasErrors('rate_plan_id');

        $this->actingAs($operator)
            ->post(route('catalog.product-rates.store', $product), [
                ...$payload,
                'branch_id' => $otherBranch->id,
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseCount('product_rates', 1);
    }

    public function test_package_items_and_rates_are_consistent(): void
    {
        [$user] = $this->superAdministrator();
        $category = $this->category($user);
        $product = $this->product($user, $category);
        $ratePlan = RatePlan::query()
            ->where('company_id', $user->company_id)
            ->where('code', '1D')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('catalog.packages.store'), [
                'branch_id' => null,
                'code' => 'WEDDING',
                'name' => 'Wedding Documentation',
                'description' => 'Camera and lens package.',
                'valid_from' => null,
                'valid_until' => null,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $package = RentalPackage::query()->where('code', 'WEDDING')->firstOrFail();

        $this->actingAs($user)
            ->post(route('catalog.package-items.store', $package), [
                'product_id' => $product->id,
                'quantity' => 2,
                'is_optional' => false,
                'sort_order' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('catalog.package-rates.store', $package), [
                'branch_id' => null,
                'rate_plan_id' => $ratePlan->id,
                'amount' => '350000.00',
                'deposit_amount' => '100000.00',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('package_items', [
            'package_id' => $package->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('package_rates', [
            'package_id' => $package->id,
            'rate_plan_id' => $ratePlan->id,
            'amount' => '350000.00',
        ]);
    }

    public function test_product_with_active_asset_cannot_be_archived(): void
    {
        [$user, $branch] = $this->superAdministrator();
        $category = $this->category($user);
        $product = $this->product($user, $category);
        Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'asset_code' => 'PNG-CAM-0001',
            'serial_number' => 'SERIAL-0001',
            'status' => 'available',
            'condition' => 'good',
            'purchase_price' => 20000000,
            'replacement_value' => 25000000,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('catalog.products.archive', $product))
            ->assertSessionHasErrors('product');

        $this->assertNull($product->fresh()->deleted_at);
    }

    public function test_category_hierarchy_cannot_form_a_cycle(): void
    {
        [$user] = $this->superAdministrator();
        $parent = $this->category($user);
        $child = ProductCategory::query()->create([
            'company_id' => $user->company_id,
            'parent_id' => $parent->id,
            'code' => 'MIRRORLESS',
            'name' => 'Mirrorless',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->put(route('catalog.categories.update', $parent), [
                'parent_id' => $child->id,
                'code' => $parent->code,
                'name' => $parent->name,
                'description' => null,
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_catalog_branch_filter_scopes_asset_metrics_and_product_detail(): void
    {
        [$user, $ponorogo] = $this->superAdministrator();
        $madiun = Branch::query()->create([
            'company_id' => $user->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $category = $this->category($user);
        $product = $this->product($user, $category);

        Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $ponorogo->id,
            'current_branch_id' => $ponorogo->id,
            'asset_code' => 'PNG-CAM-0001',
            'serial_number' => 'PNG-SERIAL-0001',
            'status' => 'available',
            'condition' => 'good',
            'purchase_price' => 20000000,
            'replacement_value' => 25000000,
            'is_active' => true,
        ]);
        Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $ponorogo->id,
            'current_branch_id' => $madiun->id,
            'asset_code' => 'PNG-CAM-0002',
            'serial_number' => 'PNG-SERIAL-0002',
            'status' => 'rented',
            'condition' => 'good',
            'purchase_price' => 20000000,
            'replacement_value' => 25000000,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('catalog.index', ['branch_id' => $madiun->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.branch_id', $madiun->id)
                ->where('inventorySummary.assets', 1)
                ->where('inventorySummary.availableAssets', 0)
                ->where('inventorySummary.rentedAssets', 1)
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.assets_count', 1)
                ->where('products.data.0.available_assets_count', 0)
                ->where('products.data.0.rented_assets_count', 1)
                ->has('branches', 2));

        $this->actingAs($user)
            ->get(route('catalog.products.show', [
                'product' => $product,
                'branch_id' => $madiun->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.branch_id', $madiun->id)
                ->where('assetSummary.rented', 1)
                ->missing('assetSummary.available')
                ->has('branchStock', 2)
                ->where('branchStock.0.branch.id', $madiun->id)
                ->where('branchStock.0.assets.rented', 1));
    }

    public function test_branch_scoped_catalog_user_cannot_filter_foreign_branch(): void
    {
        [$administrator, $ponorogo] = $this->superAdministrator();
        $madiun = Branch::query()->create([
            'company_id' => $administrator->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $operator = $this->catalogOperator(
            (int) $administrator->company_id,
            $ponorogo,
        );
        $category = $this->category($administrator);
        $product = $this->product($administrator, $category);

        $this->actingAs($operator)
            ->get(route('catalog.index', ['branch_id' => $madiun->id]))
            ->assertForbidden();

        $this->actingAs($operator)
            ->get(route('catalog.products.show', [
                'product' => $product,
                'branch_id' => $madiun->id,
            ]))
            ->assertForbidden();
    }

    public function test_user_without_product_permission_cannot_open_catalog(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('catalog.index'))
            ->assertForbidden();
    }

    public function test_products_can_be_filtered_by_canonical_brand_and_model(): void
    {
        [$user] = $this->superAdministrator();
        $category = $this->category($user);
        $sony = CatalogBrand::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Sony')
            ->firstOrFail();
        $canon = CatalogBrand::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Canon')
            ->firstOrFail();
        $sonyModel = CatalogModel::query()->create([
            'company_id' => $user->company_id,
            'catalog_brand_id' => $sony->id,
            'category_id' => $category->id,
            'name' => 'A7 III',
            'normalized_name' => 'A7 III',
            'is_active' => true,
        ]);
        $canonModel = CatalogModel::query()->create([
            'company_id' => $user->company_id,
            'catalog_brand_id' => $canon->id,
            'category_id' => $category->id,
            'name' => 'EOS R6',
            'normalized_name' => 'EOS R6',
            'is_active' => true,
        ]);
        $sonyProduct = $this->product($user, $category);
        $sonyProduct->update([
            'catalog_brand_id' => $sony->id,
            'catalog_model_id' => $sonyModel->id,
            'enrichment_status' => 'enriched',
        ]);
        $canonProduct = Product::query()->create([
            'company_id' => $user->company_id,
            ...$this->productPayload($category),
            'sku' => 'CAM-CANON-R6',
            'name' => 'Canon EOS R6',
            'brand' => 'Canon',
            'catalog_brand_id' => $canon->id,
            'model' => 'EOS R6',
            'catalog_model_id' => $canonModel->id,
            'enrichment_status' => 'enriched',
        ]);
        CatalogBrandAlias::query()->create([
            'catalog_brand_id' => $sony->id,
            'alias' => 'Alpha System',
            'normalized_alias' => 'ALPHA SYSTEM',
        ]);
        CatalogModelAlias::query()->create([
            'catalog_model_id' => $sonyModel->id,
            'alias' => 'ILCE-7M3',
            'normalized_alias' => 'ILCE 7M3',
        ]);

        $this->actingAs($user)
            ->get(route('catalog.index', [
                'catalog_brand_id' => $sony->id,
                'catalog_model_id' => $sonyModel->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.catalog_brand_id', $sony->id)
                ->where('filters.catalog_model_id', $sonyModel->id)
                ->where('products.total', 1)
                ->where('products.data.0.id', $sonyProduct->id)
                ->where('products.data.0.catalog_brand.logo_url', null)
                ->where('models.0.id', $sonyModel->id)
                ->has('brands', 2));

        $this->actingAs($user)
            ->get(route('catalog.index', [
                'catalog_brand_id' => $sony->id,
                'catalog_model_id' => $canonModel->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.catalog_brand_id', $sony->id)
                ->where('filters.catalog_model_id', null)
                ->where('products.total', 1)
                ->where('products.data.0.id', $sonyProduct->id));

        foreach (['Alpha System', 'ILCE-7M3'] as $search) {
            $this->actingAs($user)
                ->get(route('catalog.index', ['search' => $search]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('products.total', 1)
                    ->where('products.data.0.id', $sonyProduct->id));
        }

        $this->assertNotSame($sonyProduct->id, $canonProduct->id);
    }

    /**
     * @return array{0: User, 1: Branch}
     */
    private function superAdministrator(): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $companyId = (int) DB::table('companies')->where('code', 'TK')->value('id');
        $branch = Branch::query()
            ->where('company_id', $companyId)
            ->where('code', 'PNG')
            ->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $companyId,
            'current_branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $roleId = (int) DB::table('roles')
            ->where('company_id', $companyId)
            ->where('slug', 'super-admin')
            ->value('id');
        $this->assignAccess($user, $branch, $roleId, null);

        return [$user, $branch];
    }

    private function catalogOperator(int $companyId, Branch $branch): User
    {
        $role = Role::query()->create([
            'company_id' => $companyId,
            'name' => 'Catalog Operator',
            'slug' => 'catalog-operator',
            'scope' => 'branch',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            DB::table('permissions')
                ->whereIn('slug', ['branches.switch', 'products.view', 'products.manage'])
                ->pluck('id'),
        );
        $user = User::factory()->create([
            'company_id' => $companyId,
            'current_branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $this->assignAccess($user, $branch, $role->id, $branch->id);

        return $user;
    }

    private function assignAccess(
        User $user,
        Branch $branch,
        int $roleId,
        ?int $roleBranchId,
    ): void {
        $now = now();
        DB::table('branch_user')->insert([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $user->id,
            'branch_id' => $roleBranchId,
            'assigned_by' => null,
            'assigned_at' => $now,
            'expires_at' => null,
        ]);
    }

    private function category(User $user): ProductCategory
    {
        return ProductCategory::query()->create([
            'company_id' => $user->company_id,
            'code' => 'CAMERA',
            'name' => 'Camera',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function product(
        User $user,
        ProductCategory $category,
    ): Product {
        return Product::query()->create([
            'company_id' => $user->company_id,
            ...$this->productPayload($category),
        ]);
    }

    /** @return array<string, mixed> */
    private function productPayload(ProductCategory $category): array
    {
        return [
            'category_id' => $category->id,
            'sku' => 'CAM-SONY-A7',
            'name' => 'Sony A7 III',
            'brand' => 'Sony',
            'model' => 'A7 III',
            'tracking_type' => 'serialized',
            'description' => 'Full-frame mirrorless camera.',
            'replacement_value' => '25000000.00',
            'is_rentable' => true,
            'is_active' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function ratePayload(Branch $branch, RatePlan $ratePlan): array
    {
        return [
            'branch_id' => $branch->id,
            'rate_plan_id' => $ratePlan->id,
            'amount' => '250000.00',
            'deposit_amount' => '100000.00',
            'additional_hour_amount' => '25000.00',
            'late_fee_amount' => '30000.00',
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
        ];
    }
}
