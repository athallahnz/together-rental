<?php

namespace Tests\Feature\Documents;

use App\Domain\Operations\OperationalDataResetService;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\RentalCollateral;
use App\Models\RentalItem;
use App\Models\RentalItemAsset;
use App\Models\Role;
use App\Models\TransactionDocument;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TransactionDocumentCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_booking_invoice_is_idempotent_and_snapshot_is_hidden_from_index(): void
    {
        [$user, $branch, $customer, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $product);

        $payload = [
            'document_type' => 'invoice',
            'source_type' => 'booking',
            'source_reference' => $booking->booking_number,
        ];
        $this->actingAs($user)->post(route('documents.issue'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('documents.issue'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('transaction_documents', 1);
        $document = TransactionDocument::query()->firstOrFail();
        $this->assertSame(1, $document->version);
        $this->assertSame('invoice', $document->document_type);
        $this->assertSame($booking->booking_number, $document->source_reference);
        $this->assertSame(64, strlen($document->content_hash));

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('documents/index')
                ->where('documents.total', 1)
                ->missing('documents.data.0.snapshot'));
    }

    public function test_rental_invoice_creates_new_version_when_financial_snapshot_changes(): void
    {
        [$user, $branch, $customer, $product] = $this->fixture();
        $rental = $this->rental($user, $branch, $customer, $product);
        $payload = [
            'document_type' => 'invoice',
            'source_type' => 'rental',
            'source_reference' => $rental->rental_number,
        ];

        $this->actingAs($user)->post(route('documents.issue'), $payload)->assertSessionHasNoErrors();
        $first = TransactionDocument::query()->firstOrFail();
        $firstSnapshot = $this->documentSnapshot($first);
        $firstTotal = $firstSnapshot['financial']['total_amount'];

        $rental->update([
            'total_amount' => 175000,
            'balance_due' => 175000,
        ]);
        $this->actingAs($user)->post(route('documents.issue'), $payload)->assertSessionHasNoErrors();

        $documents = TransactionDocument::query()->orderBy('version')->get();
        $this->assertCount(2, $documents);
        $this->assertSame(1, $documents[0]->version);
        $this->assertSame(2, $documents[1]->version);
        $firstDocumentSnapshot = $this->documentSnapshot($documents[0]);
        $secondDocumentSnapshot = $this->documentSnapshot($documents[1]);
        $this->assertSame($firstTotal, $firstDocumentSnapshot['financial']['total_amount']);
        $this->assertSame(175000.0, (float) $secondDocumentSnapshot['financial']['total_amount']);
        $this->assertNotSame($documents[0]->content_hash, $documents[1]->content_hash);
    }

    public function test_receipt_only_accepts_valid_incoming_payment_and_old_receipt_survives_void(): void
    {
        [$user, $branch, $customer, $product] = $this->fixture();
        $rental = $this->rental($user, $branch, $customer, $product);
        $payment = $this->payment($user, $branch, $customer, $rental);
        $payload = [
            'document_type' => 'receipt',
            'source_type' => 'payment',
            'source_reference' => $payment->payment_number,
        ];

        $this->actingAs($user)->post(route('documents.issue'), $payload)->assertSessionHasNoErrors();
        $document = TransactionDocument::query()->firstOrFail();
        $receiptSnapshot = $this->documentSnapshot($document);
        $this->assertSame('completed', $receiptSnapshot['payment']['status']);

        $payment->update([
            'status' => 'void',
            'voided_at' => now(),
            'voided_by' => $user->id,
            'void_reason' => 'Test void',
        ]);
        $this->actingAs($user)
            ->post(route('documents.issue'), $payload)
            ->assertSessionHasErrors('source_reference');

        $this->assertDatabaseCount('transaction_documents', 1);
        $document->refresh();
        $receiptSnapshot = $this->documentSnapshot($document);
        $this->assertSame('completed', $receiptSnapshot['payment']['status']);
    }

    public function test_agreement_snapshots_assets_collateral_and_terms_without_private_file_path(): void
    {
        [$user, $branch, $customer, $product] = $this->fixture();
        $rental = $this->rental($user, $branch, $customer, $product, true);
        RentalCollateral::query()->create([
            'rental_id' => $rental->id,
            'customer_id' => $customer->id,
            'type' => 'ktp',
            'number' => 'COL-001',
            'holder_name' => $customer->name,
            'status' => 'held',
            'received_at' => now(),
            'received_by' => $user->id,
            'document_path' => 'private/collateral-secret.pdf',
        ]);

        $this->actingAs($user)->post(route('documents.issue'), [
            'document_type' => 'agreement',
            'source_type' => 'rental',
            'source_reference' => $rental->rental_number,
        ])->assertSessionHasNoErrors();

        $document = TransactionDocument::query()->firstOrFail();
        $snapshot = $this->documentSnapshot($document);
        $this->assertNotEmpty($snapshot['items'][0]['assets']);
        $this->assertNotEmpty($snapshot['agreement_terms']);
        $this->assertSame('COL-001', $snapshot['collaterals'][0]['number']);
        $this->assertArrayNotHasKey('document_path', $snapshot['collaterals'][0]);
    }

    public function test_pdf_is_valid_and_branch_isolation_blocks_foreign_document(): void
    {
        [$manager, $branch, $customer, $product] = $this->fixture();
        $booking = $this->booking($manager, $branch, $customer, $product);
        $this->actingAs($manager)->post(route('documents.issue'), [
            'document_type' => 'invoice',
            'source_type' => 'booking',
            'source_reference' => $booking->booking_number,
        ])->assertSessionHasNoErrors();
        $document = TransactionDocument::query()->firstOrFail();

        $pdf = $this->actingAs($manager)
            ->get(route('documents.pdf', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', (string) $pdf->getContent());

        $other = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $foreignManager = $this->userForRole('branch-manager', $other);
        $this->actingAs($foreignManager)
            ->get(route('documents.pdf', $document))
            ->assertNotFound();
    }

    public function test_inventory_staff_has_no_document_access(): void
    {
        [, $branch] = $this->fixture();
        $inventory = $this->userForRole('inventory-staff', $branch);

        $this->actingAs($inventory)->get(route('documents.index'))->assertForbidden();
        $this->actingAs($inventory)->post(route('documents.issue'), [
            'document_type' => 'invoice',
            'source_type' => 'booking',
            'source_reference' => 'ANY',
        ])->assertForbidden();
    }

    public function test_operational_reset_deletes_transaction_documents_before_sources(): void
    {
        [$user, $branch, $customer, $product] = $this->fixture('super-admin');
        $booking = $this->booking($user, $branch, $customer, $product);
        $this->actingAs($user)->post(route('documents.issue'), [
            'document_type' => 'invoice',
            'source_type' => 'booking',
            'source_reference' => $booking->booking_number,
        ])->assertSessionHasNoErrors();

        $preview = app(OperationalDataResetService::class)->preview($branch->company_id, $branch);
        $this->assertSame(1, $preview['transaction_documents']);
        app(OperationalDataResetService::class)->reset($branch->company_id, $branch, false);

        $this->assertDatabaseCount('transaction_documents', 0);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    /** @return array<string, mixed> */
    private function documentSnapshot(TransactionDocument $document): array
    {
        $snapshot = $document->getAttribute('snapshot');
        $this->assertIsArray($snapshot);

        /** @var array<string, mixed> $snapshot */
        return $snapshot;
    }

    /** @return array{0: User, 1: Branch, 2: Customer, 3: Product} */
    private function fixture(string $roleSlug = 'branch-manager'): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = $this->userForRole($roleSlug, $branch);
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'CUS-DOC-001',
            'name' => 'Pelanggan Dokumen',
            'phone' => '081234567890',
            'email' => 'dokumen@example.test',
            'status' => 'active',
            'risk_level' => 'normal',
            'created_by' => $user->id,
        ]);
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-DOC-001',
            'name' => 'Kamera Dokumen',
            'tracking_type' => 'serialized',
            'is_rentable' => true,
            'is_active' => true,
        ]);

        return [$user, $branch, $customer, $product];
    }

    private function userForRole(string $roleSlug, Branch $branch): User
    {
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role->id, [
            'branch_id' => $role->scope === 'branch' ? $branch->id : null,
            'assigned_at' => now(),
        ]);

        return $user;
    }

    private function booking(User $user, Branch $branch, Customer $customer, Product $product): Booking
    {
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $booking = Booking::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rate_plan_id' => $plan->id,
            'booking_number' => 'BKG-DOC-001',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => now(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'subtotal' => 150000,
            'total_amount' => 150000,
            'deposit_required' => 500000,
            'pricing_snapshot' => ['strategy' => 'standard'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        BookingItem::query()->create([
            'booking_id' => $booking->id,
            'product_id' => $product->id,
            'description' => 'Kamera Dokumen',
            'quantity' => 1,
            'unit_rate' => 150000,
            'total_amount' => 150000,
        ]);

        return $booking;
    }

    private function rental(
        User $user,
        Branch $branch,
        Customer $customer,
        Product $product,
        bool $withAsset = false,
    ): Rental {
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $rental = Rental::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rate_plan_id' => $plan->id,
            'rental_number' => 'RNT-DOC-001',
            'status' => 'active',
            'checked_out_at' => now(),
            'due_at' => now()->addDay(),
            'subtotal' => 150000,
            'total_amount' => 150000,
            'balance_due' => 150000,
            'deposit_amount' => 500000,
            'pricing_snapshot' => ['strategy' => 'standard'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $item = RentalItem::query()->create([
            'rental_id' => $rental->id,
            'product_id' => $product->id,
            'description' => 'Kamera Dokumen',
            'quantity' => 1,
            'unit_rate' => 150000,
            'total_amount' => 150000,
            'due_at' => $rental->due_at,
            'status' => 'out',
        ]);

        if ($withAsset) {
            $asset = Asset::query()->create([
                'product_id' => $product->id,
                'owning_branch_id' => $branch->id,
                'current_branch_id' => $branch->id,
                'asset_code' => 'CAM-DOC-ASSET-001',
                'serial_number' => 'SN-DOC-001',
                'status' => 'rented',
                'condition' => 'good',
                'is_active' => true,
            ]);
            RentalItemAsset::query()->create([
                'rental_item_id' => $item->id,
                'asset_id' => $asset->id,
                'checkout_condition' => 'good',
                'checked_out_at' => now(),
                'status' => 'out',
            ]);
        }

        return $rental;
    }

    private function payment(User $user, Branch $branch, Customer $customer, Rental $rental): Payment
    {
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();

        return Payment::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rental_id' => $rental->id,
            'payment_method_id' => $method->id,
            'payment_number' => 'PAY-DOC-001',
            'direction' => 'in',
            'type' => 'rental',
            'source_context' => 'rental_checkout',
            'status' => 'completed',
            'amount' => 150000,
            'paid_at' => now(),
            'external_reference' => 'BANK-DOC-001',
            'received_by' => $user->id,
        ]);
    }
}
