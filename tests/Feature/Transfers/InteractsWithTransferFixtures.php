<?php

namespace Tests\Feature\Transfers;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;

trait InteractsWithTransferFixtures
{
    /**
     * @return array{
     *     origin: Branch,
     *     destination: Branch,
     *     originManager: User,
     *     destinationManager: User,
     *     inventoryStaff: User,
     *     product: Product,
     *     asset: Asset,
     *     pooledProduct: Product,
     *     pooledInventory: BranchInventory
     * }
     */
    protected function transferFixture(string $suffix = ''): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $tag = $suffix === '' ? '' : '-'.strtoupper($suffix);
        $emailTag = $suffix === '' ? '' : '.'.strtolower($suffix);

        $origin = Branch::query()->where('code', 'PNG')->firstOrFail();
        $destination = Branch::query()->firstOrCreate(
            ['company_id' => $origin->company_id, 'code' => 'MDN'],
            [
                'name' => 'Together Kamera Madiun',
                'city' => 'Madiun',
                'province' => 'Jawa Timur',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
        );

        $originManager = $this->branchUser($origin, 'branch-manager', "origin.manager{$emailTag}@example.test");
        $destinationManager = $this->branchUser($destination, 'branch-manager', "destination.manager{$emailTag}@example.test");
        $inventoryStaff = $this->branchUser($origin, 'inventory-staff', "inventory.staff{$emailTag}@example.test");

        $product = Product::query()->create([
            'company_id' => $origin->company_id,
            'sku' => "TRF-CAM-001{$tag}",
            'name' => "Kamera Transfer Test{$tag}",
            'tracking_type' => 'serialized',
            'is_rentable' => true,
            'is_active' => true,
            'is_public' => true,
        ]);
        $asset = Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $origin->id,
            'current_branch_id' => $origin->id,
            'asset_code' => "PNG-TRF-001{$tag}",
            'serial_number' => "SERIAL-TRF-001{$tag}",
            'status' => 'available',
            'condition' => 'good',
            'is_active' => true,
        ]);
        BranchInventory::query()->updateOrCreate(
            ['branch_id' => $origin->id, 'product_id' => $product->id],
            ['quantity_on_hand' => 1],
        );

        $pooledProduct = Product::query()->create([
            'company_id' => $origin->company_id,
            'sku' => "TRF-ACC-001{$tag}",
            'name' => "Aksesori Transfer Test{$tag}",
            'tracking_type' => 'quantity',
            'is_rentable' => true,
            'is_active' => true,
            'is_public' => true,
        ]);
        $pooledInventory = BranchInventory::query()->create([
            'branch_id' => $origin->id,
            'product_id' => $pooledProduct->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 2,
            'quantity_rented' => 1,
            'quantity_maintenance' => 1,
            'quantity_in_transfer' => 0,
        ]);

        return compact(
            'origin',
            'destination',
            'originManager',
            'destinationManager',
            'inventoryStaff',
            'product',
            'asset',
            'pooledProduct',
            'pooledInventory',
        );
    }

    protected function branchUser(Branch $branch, string $roleSlug, string $email): User
    {
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email' => $email,
            'email_verified_at' => now(),
        ]);
        $role = Role::query()
            ->where('company_id', $branch->company_id)
            ->where('slug', $roleSlug)
            ->firstOrFail();
        $user->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id, [
            'branch_id' => $role->scope === 'company' ? null : $branch->id,
            'assigned_at' => now(),
        ]);

        return $user;
    }

    /** @param array<string, mixed> $fixture @return array<string, mixed> */
    protected function serializedPayload(array $fixture, bool $submit = false): array
    {
        return [
            'from_branch_id' => $fixture['origin']->id,
            'to_branch_id' => $fixture['destination']->id,
            'reason' => 'Pemerataan utilisasi aset antar cabang.',
            'planned_dispatch_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'expected_arrival_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'shipping_method' => 'internal',
            'shipping_notes' => 'Dibawa oleh tim internal.',
            'items' => [[
                'product_id' => $fixture['product']->id,
                'asset_id' => $fixture['asset']->id,
                'quantity' => 1,
                'condition_before' => 'good',
                'notes' => 'Unit lengkap.',
            ]],
            'submit' => $submit,
            'approval_notes' => 'Disetujui dari cabang pengaju.',
        ];
    }
}
