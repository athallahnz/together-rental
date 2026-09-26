<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RentalPackage;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCatalogContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_curate_public_product_content(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->catalogManagerAndProduct();

        $this->actingAs($user)
            ->get(route('catalog.public-content.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/public-content')
                ->where('products.data.0.id', $product->id)
                ->where('products.total', 1));

        $this->actingAs($user)
            ->put(route('catalog.public-content.products.update', $product), [
                'is_public' => true,
                'is_featured' => true,
                'public_sort_order' => 2,
                'short_description' => 'Kamera ringkas untuk produksi kreatif.',
                'short_description_en' => 'Compact camera for creative productions.',
                'description' => 'Deskripsi lengkap kamera.',
                'description_en' => 'Detailed camera description.',
                'seo_title_en' => 'Rent Test Camera',
                'seo_description_en' => 'Rent a test camera.',
                'seo_title' => 'Sewa Kamera Test',
                'seo_description' => 'Sewa kamera test di Together Kamera.',
                'primary_image' => UploadedFile::fake()->image('camera.webp'),
                'gallery_images' => [UploadedFile::fake()->image('detail.webp')],
                'remove_primary_image' => false,
                'clear_gallery' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertTrue($product->is_public);
        $this->assertTrue($product->is_featured);
        $this->assertSame('Compact camera for creative productions.', $product->short_description_en);
        $this->assertSame('Detailed camera description.', $product->description_en);
        $this->assertSame('Rent Test Camera', $product->seo_title_en);
        $this->assertSame(2, $product->public_sort_order);
        $this->assertNotNull($product->primary_image_path);
        $this->assertCount(1, $product->gallery ?? []);
        Storage::disk('public')->assertExists((string) $product->primary_image_path);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'subject_id' => $product->id,
            'event' => 'catalog.product.public-content.updated',
        ]);
    }

    public function test_package_bilingual_content_is_saved_and_old_client_preserves_english(): void
    {
        [$user, $product] = $this->catalogManagerAndProduct();
        $branchId = (int) $user->current_branch_id;
        $package = RentalPackage::query()->create([
            'company_id' => $product->company_id,
            'branch_id' => $branchId,
            'code' => 'PKG-BILINGUAL',
            'name' => 'Paket Foto',
            'description' => 'Paket kamera asli',
            'is_active' => true,
            'is_public' => false,
        ]);

        $this->actingAs($user)
            ->put(route('catalog.public-content.packages.update', $package), [
                'is_public' => false,
                'is_featured' => false,
                'public_sort_order' => 0,
                'description' => 'Paket kamera lengkap',
                'description_en' => 'Complete camera package',
                'seo_title' => 'Paket Foto',
                'seo_description' => 'Paket asli',
                'seo_title_en' => 'Photo Package',
                'seo_description_en' => 'Full photo package',
                'remove_primary_image' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $package->refresh();
        $this->assertSame('Complete camera package', $package->description_en);
        $this->assertSame('Photo Package', $package->seo_title_en);
        $this->assertSame('Full photo package', $package->seo_description_en);

        // Older operator clients do not send optional English fields.
        $this->actingAs($user)
            ->put(route('catalog.public-content.packages.update', $package), [
                'is_public' => false,
                'is_featured' => false,
                'public_sort_order' => 0,
                'seo_title' => 'Paket Foto diperbarui',
                'seo_description' => 'Paket asli diperbarui',
                'remove_primary_image' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $package->refresh();
        $this->assertSame('Complete camera package', $package->description_en);
        $this->assertSame('Photo Package', $package->seo_title_en);
    }

    public function test_public_content_products_are_paginated_in_compact_batches(): void
    {
        [$user, $product] = $this->catalogManagerAndProduct();

        foreach (range(2, 17) as $number) {
            Product::query()->create([
                'company_id' => $product->company_id,
                'category_id' => $product->category_id,
                'sku' => sprintf('TEST-%03d', $number),
                'name' => sprintf('Kamera Test %02d', $number),
                'tracking_type' => 'serialized',
                'replacement_value' => 5000000,
                'is_rentable' => true,
                'is_active' => true,
                'is_public' => false,
            ]);
        }

        $this->actingAs($user)
            ->get(route('catalog.public-content.index', [
                'section' => 'products',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/public-content')
                ->has('products.data', 15)
                ->where('products.from', 1)
                ->where('products.to', 15)
                ->where('products.total', 17));
    }

    public function test_sitemap_only_contains_public_products(): void
    {
        [$user, $product] = $this->catalogManagerAndProduct();
        $product->update(['is_public' => true]);

        $hidden = Product::query()->create([
            'company_id' => $product->company_id,
            'category_id' => $product->category_id,
            'sku' => 'HIDDEN-001',
            'name' => 'Produk Rahasia',
            'tracking_type' => 'serialized',
            'replacement_value' => 1000000,
            'is_rentable' => true,
            'is_active' => true,
            'is_public' => false,
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/rental/products/'.$product->slug, false)
            ->assertDontSee('/rental/products/'.$hidden->slug, false);
    }

    /** @return array{0: User, 1: Product} */
    private function catalogManagerAndProduct(): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $companyId = (int) DB::table('companies')->where('code', 'TK')->value('id');
        $branchId = (int) DB::table('branches')->where('company_id', $companyId)->where('code', 'PNG')->value('id');
        DB::table('branch_settings')->updateOrInsert(
            ['branch_id' => $branchId, 'key' => 'public_catalog_enabled'],
            [
                'value_type' => 'boolean',
                'value' => 'true',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $user = User::factory()->create([
            'company_id' => $companyId,
            'current_branch_id' => $branchId,
        ]);
        $roleId = (int) DB::table('roles')->where('company_id', $companyId)->where('slug', 'super-admin')->value('id');
        DB::table('branch_user')->insert([
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $user->id,
            'branch_id' => null,
            'assigned_at' => now(),
        ]);

        $category = ProductCategory::query()->create([
            'company_id' => $companyId,
            'code' => 'TEST',
            'name' => 'Kategori Test',
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'company_id' => $companyId,
            'category_id' => $category->id,
            'sku' => 'TEST-001',
            'name' => 'Kamera Test',
            'tracking_type' => 'serialized',
            'replacement_value' => 5000000,
            'is_rentable' => true,
            'is_active' => true,
            'is_public' => false,
        ]);

        return [$user, $product];
    }
}
