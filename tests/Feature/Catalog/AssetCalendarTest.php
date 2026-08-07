<?php

namespace Tests\Feature\Catalog;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_calendar_exposes_transaction_reference_but_public_calendar_is_anonymized(): void
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $branch->update(['city' => 'Ponorogo']);
        $this->enablePublicCatalog($branch);

        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $user->branches()->attach($branch->id, ['is_default' => true, 'is_active' => true]);
        $user->roles()->attach($role->id, ['branch_id' => null, 'assigned_at' => now()]);

        $category = ProductCategory::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'CALENDAR-CAMERA',
            'name' => 'Calendar Camera',
            'is_active' => true,
            'is_public' => true,
        ]);
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'category_id' => $category->id,
            'sku' => 'CAL-001',
            'name' => 'Camera Calendar Test',
            'tracking_type' => 'serialized',
            'replacement_value' => 5000000,
            'is_rentable' => true,
            'is_active' => true,
            'is_public' => true,
        ]);
        $asset = Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'asset_code' => 'PNG-CAL-001',
            'serial_number' => 'SECRET-SERIAL-001',
            'status' => 'reserved',
            'condition' => 'good',
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-CALENDAR',
            'name' => 'Calendar Customer',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $booking = Booking::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => 'PNG-BKG-CALENDAR',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => '2026-08-01 09:00:00',
            'starts_at' => '2026-08-10 09:00:00',
            'ends_at' => '2026-08-11 09:00:00',
        ]);
        $itemId = DB::table('booking_items')->insertGetId([
            'booking_id' => $booking->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('asset_reservations')->insert([
            'branch_id' => $branch->id,
            'booking_id' => $booking->id,
            'booking_item_id' => $itemId,
            'asset_id' => $asset->id,
            'starts_at' => '2026-08-10 09:00:00',
            'ends_at' => '2026-08-11 09:00:00',
            'status' => 'reserved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('catalog.assets.calendar', [
                'asset' => $asset,
                'month' => '2026-08',
            ]))
            ->assertOk()
            ->assertJsonPath('data.asset.asset_code', 'PNG-CAL-001')
            ->assertJsonPath('data.events.0.status', 'booked')
            ->assertJsonPath('data.events.0.reference', 'PNG-BKG-CALENDAR');

        $this->getJson(route('public.catalog.products.asset-calendar', [
            'slug' => $product->slug,
            'branch' => 'PNG',
            'month' => '2026-08',
        ]))
            ->assertOk()
            ->assertJsonPath('data.units.0.label', 'Unit 1')
            ->assertJsonPath('data.selected_unit.events.0.label', 'Terbooking')
            ->assertJsonPath('data.selected_unit.events.0.reference', null)
            ->assertJsonMissingPath('data.units.0.asset_code')
            ->assertJsonMissingPath('data.units.0.serial_number');
    }

    private function enablePublicCatalog(Branch $branch): void
    {
        foreach ([
            'public_catalog_enabled' => true,
            'public_whatsapp' => '628123456789',
        ] as $key => $value) {
            DB::table('branch_settings')->updateOrInsert(
                ['branch_id' => $branch->id, 'key' => $key],
                [
                    'value_type' => is_bool($value) ? 'boolean' : 'string',
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'is_public' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
