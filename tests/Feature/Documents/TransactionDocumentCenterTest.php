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

    public function test_historical_pdf_keeps_issuer_name_snapshot_after_user_profile_changes(): void
    {
        [$user, $branch, $customer, $product] = $this->fixture();
        $user->update(['name' => 'Original Document Operator']);
        $booking = $this->booking($user, $branch, $customer, $product);

        $this->actingAs($user)->post(route('documents.issue'), [
            'document_type' => 'invoice',
            'source_type' => 'booking',
            'source_reference' => $booking->booking_number,
        ])->assertSessionHasNoErrors();

        $document = TransactionDocument::query()->firstOrFail();
        $this->assertSame('Original Document Operator', $document->issuer_name_snapshot);

        $user->update(['name' => 'Renamed Document Operator']);

        $pdf = $this->actingAs($user)
            ->get(route('documents.preview', $document))
            ->assertOk()
            ->getContent();

        $this->assertIsString($pdf);
        $this->assertStringContainsString('Original Document Operator', $pdf);
        $this->assertStringNotContainsString('Renamed Document Operator', $pdf);
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
        $this->assertCount(10, $snapshot['agreement_rights_obligations']);
        $this->assertCount(10, $snapshot['agreement_terms']);
        $this->assertSame(
            'Penyewa berhak bertanya mengenai cara penggunaan alat yang disewakan.',
            $snapshot['agreement_rights_obligations'][0],
        );
        $this->assertSame(
            'Konfirmasi perpanjangan wajib datang ke kantor dan membayar biaya perpanjangan.',
            $snapshot['agreement_rights_obligations'][9],
        );
        $this->assertSame(
            'Biaya sewa per hari dihitung saat penerimaan barang.',
            $snapshot['agreement_terms'][0],
        );
        $this->assertStringContainsString(
            'tidak memiliki minimum 50%',
            $snapshot['agreement_terms'][3],
        );
        $this->assertStringContainsString(
            'DP rental dan security deposit dicatat terpisah',
            $snapshot['agreement_terms'][3],
        );
        $this->assertStringContainsString(
            'Mulai 6 jam, jika tarif paket 6 jam tersedia',
            $snapshot['agreement_terms'][4],
        );
        $this->assertStringContainsString(
            'Jika tarif paket 6 jam tidak tersedia, seluruh jam keterlambatan dihitung sebesar 10% dari harga sewa per jam.',
            $snapshot['agreement_terms'][4],
        );
        $this->assertSame('COL-001', $snapshot['collaterals'][0]['number']);
        $this->assertArrayNotHasKey('document_path', $snapshot['collaterals'][0]);

        $pdf = $this->actingAs($user)
            ->get(route('documents.preview', $document))
            ->assertOk()
            ->getContent();

        $this->assertIsString($pdf);
        $this->assertStringContainsString('HAK DAN KEWAJIBAN PENYEWA', $pdf);
        $this->assertStringContainsString('KETENTUAN SEWA', $pdf);
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
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

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

    public function test_source_options_preload_eligible_booking_and_prioritize_unissued_source(): void
    {
        [$user, $branch, $customer, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $product);

        $second = $booking->replicate();
        $second->booking_number = 'BKG-DOC-002';
        $second->status = 'confirmed';
        $second->save();

        $draft = $booking->replicate();
        $draft->booking_number = 'BKG-DOC-DRAFT';
        $draft->status = 'draft';
        $draft->save();

        $this->actingAs($user)
            ->post(route('documents.issue'), [
                'document_type' => 'invoice',
                'source_type' => 'booking',
                'source_reference' => $booking->booking_number,
            ])
            ->assertSessionHasNoErrors();

        $document = TransactionDocument::query()->firstOrFail();

        $this->actingAs($user)
            ->getJson(route('documents.source-options', [
                'document_type' => 'invoice',
                'source_type' => 'booking',
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.reference', 'BKG-DOC-002')
            ->assertJsonPath('data.0.has_document', false)
            ->assertJsonPath('data.0.latest_version', null)
            ->assertJsonPath('data.1.reference', 'BKG-DOC-001')
            ->assertJsonPath('data.1.has_document', true)
            ->assertJsonPath('data.1.latest_version', 1)
            ->assertJsonPath('data.1.latest_document_number', $document->document_number);
    }

    public function test_source_options_are_branch_scoped(): void
    {
        [$user, $branch, $customer, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $product);

        $other = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $foreign = $booking->replicate();
        $foreign->branch_id = $other->id;
        $foreign->booking_number = 'BKG-MDN-DOC-001';
        $foreign->save();

        $this->actingAs($user)
            ->getJson(route('documents.source-options', [
                'document_type' => 'invoice',
                'source_type' => 'booking',
                'q' => 'BKG-MDN',
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_receipt_source_options_only_show_completed_incoming_payments(): void
    {
        [$user, $branch, $customer, $product] = $this->fixture();
        $rental = $this->rental($user, $branch, $customer, $product);
        $payment = $this->payment($user, $branch, $customer, $rental);

        $void = $payment->replicate();
        $void->payment_number = 'PAY-DOC-VOID';
        $void->status = 'void';
        $void->save();

        $outgoing = $payment->replicate();
        $outgoing->payment_number = 'PAY-DOC-OUT';
        $outgoing->direction = 'out';
        $outgoing->save();

        $this->actingAs($user)
            ->getJson(route('documents.source-options', [
                'document_type' => 'receipt',
                'source_type' => 'payment',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'PAY-DOC-001')
            ->assertJsonPath('data.0.status', 'completed');
    }

    public function test_quick_issue_returns_preview_and_download_urls(): void
    {
        [$user, $branch, $customer, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $product);

        $response = $this->actingAs($user)->postJson(route('documents.quick-issue'), [
            'document_type' => 'invoice',
            'source_type' => 'booking',
            'source_reference' => $booking->booking_number,
        ])->assertOk();

        $document = TransactionDocument::query()->firstOrFail();
        $response
            ->assertJsonPath('data.document_number', $document->document_number)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.pdf_url', route('documents.pdf', $document))
            ->assertJsonPath('data.preview_url', route('documents.preview', $document));

        $this->actingAs($user)
            ->get(route('documents.preview', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="'.str($document->document_number)->lower()->replace(['/', '\\'], '-')->toString().'.pdf"');
    }

    public function test_inventory_staff_has_no_document_access(): void
    {
        [, $branch] = $this->fixture();
        $inventory = $this->userForRole('inventory-staff', $branch);

        $this->actingAs($inventory)->get(route('documents.index'))->assertForbidden();
        $this->actingAs($inventory)
            ->getJson(route('documents.source-options', [
                'document_type' => 'invoice',
                'source_type' => 'booking',
            ]))
            ->assertForbidden();
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
