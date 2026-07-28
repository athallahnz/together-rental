<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->string('slug', 180)->nullable()->after('name');
            $table->string('icon', 80)->nullable()->after('description');
            $table->string('image_path')->nullable()->after('icon');
            $table->boolean('is_public')->default(false)->after('is_active')->index();
            $table->unique('slug', 'product_categories_slug_unique');
        });

        Schema::table('catalog_brands', function (Blueprint $table): void {
            $table->string('slug', 180)->nullable()->after('normalized_name');
            $table->boolean('is_public')->default(false)->after('sort_order')->index();
            $table->boolean('is_featured')->default(false)->after('is_public')->index();
            $table->unique('slug', 'catalog_brands_slug_unique');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('slug', 200)->nullable()->after('name');
            $table->string('short_description', 320)->nullable()->after('description');
            $table->string('primary_image_path')->nullable()->after('short_description');
            $table->json('gallery')->nullable()->after('primary_image_path');
            $table->string('seo_title', 180)->nullable()->after('gallery');
            $table->string('seo_description', 320)->nullable()->after('seo_title');
            $table->boolean('is_public')->default(false)->after('is_active')->index();
            $table->boolean('is_featured')->default(false)->after('is_public')->index();
            $table->unsignedInteger('public_sort_order')->default(0)->after('is_featured')->index();
            $table->unique('slug', 'products_slug_unique');
            $table->index(
                ['company_id', 'is_public', 'is_featured', 'public_sort_order'],
                'products_public_catalog_index',
            );
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->string('slug', 200)->nullable()->after('name');
            $table->string('primary_image_path')->nullable()->after('description');
            $table->string('seo_title', 180)->nullable()->after('primary_image_path');
            $table->string('seo_description', 320)->nullable()->after('seo_title');
            $table->boolean('is_public')->default(false)->after('is_active')->index();
            $table->boolean('is_featured')->default(false)->after('is_public')->index();
            $table->unsignedInteger('public_sort_order')->default(0)->after('is_featured')->index();
            $table->unique('slug', 'packages_slug_unique');
            $table->index(
                ['company_id', 'is_public', 'is_featured', 'public_sort_order'],
                'packages_public_catalog_index',
            );
        });

        $this->backfillSlugs('product_categories');
        $this->backfillSlugs('catalog_brands');
        $this->backfillSlugs('products');
        $this->backfillSlugs('packages');

        DB::table('product_categories')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->update(['is_public' => true]);

        DB::table('catalog_brands')
            ->where('is_active', true)
            ->update(['is_public' => true]);

        DB::table('products')
            ->where('is_active', true)
            ->where('is_rentable', true)
            ->whereNull('deleted_at')
            ->update(['is_public' => true]);

        DB::table('packages')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->update(['is_public' => true]);

        $this->seedPonorogoPublicProfile();
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropIndex('packages_public_catalog_index');
            $table->dropUnique('packages_slug_unique');
            $table->dropColumn([
                'slug',
                'primary_image_path',
                'seo_title',
                'seo_description',
                'is_public',
                'is_featured',
                'public_sort_order',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_public_catalog_index');
            $table->dropUnique('products_slug_unique');
            $table->dropColumn([
                'slug',
                'short_description',
                'primary_image_path',
                'gallery',
                'seo_title',
                'seo_description',
                'is_public',
                'is_featured',
                'public_sort_order',
            ]);
        });

        Schema::table('catalog_brands', function (Blueprint $table): void {
            $table->dropUnique('catalog_brands_slug_unique');
            $table->dropColumn(['slug', 'is_public', 'is_featured']);
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropUnique('product_categories_slug_unique');
            $table->dropColumn(['slug', 'icon', 'image_path', 'is_public']);
        });
    }

    private function backfillSlugs(string $table): void
    {
        $used = [];

        DB::table($table)
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $row) use (&$used, $table): void {
                $base = Str::slug((string) $row->name);
                $base = $base !== '' ? $base : "item-{$row->id}";
                $candidate = $base;
                $suffix = 2;

                while (isset($used[$candidate])) {
                    $candidate = "{$base}-{$suffix}";
                    $suffix++;
                }

                $used[$candidate] = true;

                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['slug' => $candidate]);
            });
    }

    private function seedPonorogoPublicProfile(): void
    {
        $branchId = DB::table('branches')
            ->where('code', 'PNG')
            ->whereNull('deleted_at')
            ->value('id');

        if ($branchId === null) {
            return;
        }

        $now = now();
        $settings = [
            'public_catalog_enabled' => ['boolean', true],
            'public_whatsapp' => ['string', '6285784771927'],
            'public_maps_url' => [
                'string',
                'https://www.google.com/maps/place/Together+Kamera+Store+%2F+Toko+Kamera+Ponrogo+(+JUAL+BELI+KAMERA+BARU+%26+BEKAS+,+SEWA+,+JUAL+BELI+,GADAI+%26+SERVIS+)/@-7.8503838,111.4929575,19.22z/data=!4m15!1m8!3m7!1s0x2e790b859cfee851:0x3027a76e352bea0!2sPonorogo+Regency,+East+Java!3b1!8m2!3d-7.8650759!4d111.4696322!16zL20vMGdjN21w!3m5!1s0x2e79a1d81bfb0687:0x36ff0cdf9787eba7!8m2!3d-7.8499244!4d111.4931516!16s%2Fg%2F11gy6371h1?hl=en&entry=ttu&g_ep=EgoyMDI2MDcyMi4wIKXMDSoASAFQAw%3D%3D',
            ],
            'public_instagram' => ['string', 'together_kamera'],
            'public_opening_hours' => ['string', '09.00–21.00 WIB'],
            'public_short_address' => [
                'string',
                'Jl. Brigjend Katamso Gg. VI No. 5, Tengah, Kadipaten, Kec. Babadan, Kabupaten Ponorogo, Jawa Timur 63491',
            ],
            'public_logo_path' => ['string', '/primary-logos.png'],
            'public_hero_title' => ['string', 'Sewa alat kreatif tanpa ribet.'],
            'public_hero_description' => [
                'string',
                'Temukan kamera, lensa, lighting, audio, dan perlengkapan produksi yang siap menemani karya Anda.',
            ],
        ];

        foreach ($settings as $key => [$type, $value]) {
            DB::table('branch_settings')->updateOrInsert(
                ['branch_id' => $branchId, 'key' => $key],
                [
                    'value_type' => $type,
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'is_public' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
};
