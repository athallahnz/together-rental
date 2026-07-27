<?php

namespace Tests\Feature\Catalog;

use App\Models\Branch;
use App\Models\CatalogBrand;
use App\Models\CatalogEnrichmentCandidate;
use App\Models\CatalogEnrichmentRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CatalogIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_administrator_can_open_catalog_intelligence(): void
    {
        [$user] = $this->superAdministrator();

        $this->actingAs($user)
            ->get(route('catalog.intelligence.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('catalog/intelligence')
                ->where('run', null)
                ->where('ai.enabled', false)
                ->where('summary.total', 0));
    }

    public function test_deterministic_preview_maps_legacy_brand_model_and_variant(): void
    {
        [$user] = $this->superAdministrator();
        $product = $this->product($user, 'CANON 750D BO');

        $this->actingAs($user)
            ->post(route('catalog.intelligence.generate'), [
                'scope' => 'missing',
                'use_ai' => false,
            ])
            ->assertSessionHasNoErrors();

        $run = CatalogEnrichmentRun::query()->firstOrFail();
        $candidate = CatalogEnrichmentCandidate::query()
            ->where('run_id', $run->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertSame('Canon', $candidate->suggested_brand);
        $this->assertSame('EOS 750D', $candidate->suggested_model);
        $this->assertSame('Body Only', $candidate->suggested_variant);
        $this->assertSame('pending', $candidate->status);
        $this->assertGreaterThanOrEqual(85, (float) $candidate->confidence);
        $this->assertNull($product->fresh()->brand);
    }

    public function test_approved_candidates_execute_and_group_products_into_one_canonical_model(): void
    {
        [$user] = $this->superAdministrator();
        $first = $this->product($user, 'SONY A7II BODY ONLY', 'LEG-001');
        $second = $this->product($user, 'Sony A7II BO', 'LEG-002');

        $this->actingAs($user)->post(route('catalog.intelligence.generate'), [
            'scope' => 'missing',
            'use_ai' => false,
        ]);
        $run = CatalogEnrichmentRun::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('catalog.intelligence.approve-high-confidence', $run))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('catalog.intelligence.execute', $run))
            ->assertSessionHasNoErrors();

        $first->refresh();
        $second->refresh();
        $this->assertSame('Sony', $first->brand);
        $this->assertSame('A7II', $first->model);
        $this->assertSame($first->catalog_model_id, $second->catalog_model_id);
        $this->assertSame('enriched', $first->enrichment_status);
        $this->assertDatabaseCount('catalog_models', 1);
        $this->assertDatabaseHas('catalog_enrichment_runs', [
            'id' => $run->id,
            'status' => 'executed',
            'executed_count' => 2,
            'verified_count' => 2,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'event' => 'catalog.enrichment.executed',
        ]);
    }

    public function test_ambiguous_candidate_can_be_corrected_and_approved_manually(): void
    {
        [$user] = $this->superAdministrator();
        $product = $this->product($user, 'SL100');

        $this->actingAs($user)->post(route('catalog.intelligence.generate'), [
            'scope' => 'missing',
            'use_ai' => false,
        ]);
        $candidate = CatalogEnrichmentCandidate::query()->firstOrFail();
        $this->assertSame('needs_review', $candidate->status);

        $this->actingAs($user)
            ->patch(route('catalog.intelligence.candidates.review', $candidate), [
                'suggested_brand' => 'Godox',
                'suggested_model' => 'SL100',
                'suggested_variant' => null,
                'status' => 'approved',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('catalog.intelligence.execute', $candidate->run))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('Godox', $product->brand);
        $this->assertSame('SL100', $product->model);
        $this->assertDatabaseHas('catalog_enrichment_candidates', [
            'id' => $candidate->id,
            'source' => 'manual',
            'status' => 'executed',
        ]);
    }

    public function test_executed_run_can_be_rolled_back_safely(): void
    {
        [$user] = $this->superAdministrator();
        $product = $this->product($user, 'IPHONE 14 PROMAX');

        $this->actingAs($user)->post(route('catalog.intelligence.generate'), [
            'scope' => 'missing',
            'use_ai' => false,
        ]);
        $run = CatalogEnrichmentRun::query()->firstOrFail();
        $this->actingAs($user)->post(
            route('catalog.intelligence.approve-high-confidence', $run),
        );
        $this->actingAs($user)->post(route('catalog.intelligence.execute', $run));
        $this->assertSame('Apple', $product->fresh()->brand);

        $this->actingAs($user)
            ->post(route('catalog.intelligence.rollback', $run))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertNull($product->brand);
        $this->assertNull($product->model);
        $this->assertSame('pending', $product->enrichment_status);
        $this->assertDatabaseHas('catalog_enrichment_runs', [
            'id' => $run->id,
            'status' => 'rolled_back',
            'rollback_skipped_count' => 0,
        ]);
    }

    public function test_branch_scoped_catalog_manager_cannot_run_company_mapping(): void
    {
        [$administrator, $branch] = $this->superAdministrator();
        $operator = $this->catalogOperator(
            (int) $administrator->company_id,
            $branch,
        );

        $this->actingAs($operator)
            ->get(route('catalog.intelligence.index'))
            ->assertForbidden();
        $this->actingAs($operator)
            ->post(route('catalog.intelligence.generate'), [
                'scope' => 'missing',
                'use_ai' => false,
            ])
            ->assertForbidden();

        $brand = CatalogBrand::query()
            ->where('company_id', $administrator->company_id)
            ->firstOrFail();
        $this->actingAs($operator)
            ->post(route('catalog.brands.visual.update', $brand), [
                'sort_order' => 0,
            ])
            ->assertForbidden();
    }

    public function test_company_administrator_can_manage_brand_logo(): void
    {
        Storage::fake('public');
        [$user] = $this->superAdministrator();
        $brand = CatalogBrand::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Sony')
            ->firstOrFail();
        $logo = UploadedFile::fake()->createWithContent(
            'sony.png',
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true,
            ),
        );

        $this->actingAs($user)
            ->post(route('catalog.brands.visual.update', $brand), [
                'logo' => $logo,
                'sort_order' => 10,
            ])
            ->assertSessionHasNoErrors();

        $brand->refresh();
        $this->assertNotNull($brand->logo_path);
        $this->assertSame(10, $brand->sort_order);
        Storage::disk('public')->assertExists((string) $brand->logo_path);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'event' => 'catalog.brand.visual.updated',
            'subject_id' => $brand->id,
        ]);
        $storedPath = (string) $brand->logo_path;

        $this->actingAs($user)
            ->delete(route('catalog.brands.logo.destroy', $brand))
            ->assertSessionHasNoErrors();

        $this->assertNull($brand->fresh()->logo_path);
        Storage::disk('public')->assertMissing($storedPath);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'event' => 'catalog.brand.logo.removed',
            'subject_id' => $brand->id,
        ]);
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
            'slug' => 'catalog-operator-intelligence',
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

    private function product(
        User $user,
        string $name,
        ?string $sku = null,
    ): Product {
        $category = ProductCategory::query()->firstOrCreate(
            [
                'company_id' => $user->company_id,
                'code' => 'CAMERA',
            ],
            [
                'name' => 'Camera',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        return Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => $sku ?? 'LEG-'.str()->upper(str()->random(8)),
            'name' => $name,
            'tracking_type' => 'serialized',
            'replacement_value' => 0,
            'is_rentable' => true,
            'is_active' => true,
        ]);
    }
}
