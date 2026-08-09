<?php

namespace Tests\Feature\Transfers;

use App\Models\BranchTransfer;
use App\Models\MaintenanceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BranchTransferDispatchReceivingTest extends TestCase
{
    use InteractsWithTransferFixtures;
    use RefreshDatabase;

    public function test_dispatch_then_good_receiving_moves_asset_atomically(): void
    {
        Storage::fake('local');
        $fixture = $this->transferFixture();
        $transfer = $this->approvedTransfer($fixture);
        $item = $transfer->items()->firstOrFail();

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.dispatch.store', $transfer), [
                'shipping_method' => 'internal',
                'courier_name' => 'Tim Operasional PNG',
                'waybill_number' => 'SJ-TRF-001',
                'shipping_notes' => 'Berangkat dalam kondisi lengkap.',
                'inspections' => [[
                    'item_id' => $item->id,
                    'condition' => 'good',
                    'checklist' => ['body' => true, 'function' => true, 'accessories' => true],
                    'notes' => 'Lolos pemeriksaan keberangkatan.',
                    'capture_source' => 'camera',
                    'photos' => [UploadedFile::fake()->image('dispatch.jpg', 1200, 800)],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('dispatched', $transfer->fresh()->status->value);
        $this->assertSame($fixture['origin']->id, $fixture['asset']->fresh()->current_branch_id);
        $this->assertDatabaseHas('asset_inspections', [
            'branch_transfer_item_id' => $item->id,
            'type' => 'transfer_dispatch',
        ]);

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.receipts.store', $transfer), [
                'receiving_notes' => 'Diterima oleh cabang Madiun.',
                'items' => [[
                    'item_id' => $item->id,
                    'receiving_result' => 'accepted_good',
                    'quantity' => 1,
                    'condition' => 'good',
                    'checklist' => ['body' => true, 'function' => true, 'accessories' => true],
                    'notes' => 'Unit dan kelengkapan sesuai.',
                    'capture_source' => 'camera',
                    'photos' => [UploadedFile::fake()->image('receiving.jpg', 1200, 800)],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $transfer->refresh();
        $asset = $fixture['asset']->fresh();

        $this->assertSame('completed', $transfer->status->value);
        $this->assertSame($fixture['destination']->id, $asset->current_branch_id);
        $this->assertSame($fixture['origin']->id, $asset->owning_branch_id);
        $this->assertSame('available', $asset->status);
        $this->assertDatabaseHas('branch_transfer_items', [
            'id' => $item->id,
            'status' => 'received',
            'receiving_result' => 'accepted_good',
            'received_quantity' => 1,
        ]);
    }

    public function test_damaged_receiving_creates_one_maintenance_order(): void
    {
        Storage::fake('local');
        $fixture = $this->transferFixture();
        $transfer = $this->approvedTransfer($fixture);
        $item = $transfer->items()->firstOrFail();
        $this->dispatch($fixture, $transfer, $item->id);

        $payload = [
            'items' => [[
                'item_id' => $item->id,
                'receiving_result' => 'accepted_damaged',
                'quantity' => 1,
                'condition' => 'damaged',
                'checklist' => ['body' => false, 'function' => true, 'accessories' => true],
                'notes' => 'Terdapat benturan pada body.',
                'capture_source' => 'camera',
                'photos' => [UploadedFile::fake()->image('damaged.jpg')],
            ]],
        ];

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.receipts.store', $transfer), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('maintenance', $fixture['asset']->fresh()->status);
        $this->assertSame($fixture['destination']->id, $fixture['asset']->fresh()->current_branch_id);
        $this->assertSame(1, MaintenanceOrder::query()->where('asset_id', $fixture['asset']->id)->count());
        $this->assertSame('completed', $transfer->fresh()->status->value);
    }

    public function test_missing_receiving_stays_unavailable_until_discrepancy_resolution(): void
    {
        Storage::fake('local');
        $fixture = $this->transferFixture();
        $transfer = $this->approvedTransfer($fixture);
        $item = $transfer->items()->firstOrFail();
        $this->dispatch($fixture, $transfer, $item->id);

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.receipts.store', $transfer), [
                'items' => [[
                    'item_id' => $item->id,
                    'receiving_result' => 'missing',
                    'quantity' => 1,
                    'condition' => 'good',
                    'notes' => 'Unit tidak ada pada paket yang diterima.',
                    'capture_source' => 'camera',
                    'photos' => [UploadedFile::fake()->image('missing.jpg')],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('discrepancy', $transfer->fresh()->status->value);
        $this->assertSame('in_transit', $fixture['asset']->fresh()->status);
        $this->assertSame($fixture['origin']->id, $fixture['asset']->fresh()->current_branch_id);

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.items.resolve', [$transfer, $item]), [
                'resolution_action' => 'accept_at_destination',
                'notes' => 'Unit ditemukan pada paket kedua dan diterima.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', $transfer->fresh()->status->value);
        $this->assertSame($fixture['destination']->id, $fixture['asset']->fresh()->current_branch_id);
        $this->assertSame('available', $fixture['asset']->fresh()->status);
    }

    public function test_return_to_origin_resolution_does_not_mark_missing_asset_as_received(): void
    {
        Storage::fake('local');
        $fixture = $this->transferFixture();
        $transfer = $this->approvedTransfer($fixture);
        $item = $transfer->items()->firstOrFail();
        $this->dispatch($fixture, $transfer, $item->id);

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.receipts.store', $transfer), [
                'items' => [[
                    'item_id' => $item->id,
                    'receiving_result' => 'missing',
                    'quantity' => 1,
                    'condition' => 'good',
                    'notes' => 'Unit tidak ditemukan pada kiriman.',
                    'capture_source' => 'camera',
                    'photos' => [UploadedFile::fake()->image('missing-return.jpg')],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.items.resolve', [$transfer, $item]), [
                'resolution_action' => 'return_to_origin',
                'notes' => 'Dokumen ditutup dan unit dikonfirmasi tetap di cabang asal.',
            ])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('resolved', $item->status->value);
        $this->assertSame(0, $item->received_quantity);
        $this->assertSame($fixture['origin']->id, $fixture['asset']->fresh()->current_branch_id);
        $this->assertSame('available', $fixture['asset']->fresh()->status);
        $this->assertSame('completed', $transfer->fresh()->status->value);
    }

    public function test_repeated_receiving_request_is_idempotent(): void
    {
        Storage::fake('local');
        $fixture = $this->transferFixture();
        $transfer = $this->approvedTransfer($fixture);
        $item = $transfer->items()->firstOrFail();
        $this->dispatch($fixture, $transfer, $item->id);

        $payload = fn (string $filename): array => [
            'receiving_notes' => 'Diterima dalam kondisi baik.',
            'items' => [[
                'item_id' => $item->id,
                'receiving_result' => 'accepted_good',
                'quantity' => 1,
                'condition' => 'good',
                'notes' => 'Unit sesuai dokumen.',
                'capture_source' => 'camera',
                'photos' => [UploadedFile::fake()->image($filename)],
            ]],
        ];

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.receipts.store', $transfer), $payload('receive-first.jpg'))
            ->assertSessionHasNoErrors();

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.receipts.store', $transfer), $payload('receive-repeat.jpg'))
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', $transfer->fresh()->status->value);
        $this->assertSame(1, $item->inspections()->where('type', 'transfer_receiving')->count());
        $this->assertSame(1, $item->inspections()->where('type', 'transfer_receiving')->firstOrFail()->media()->count());
    }

    public function test_repeated_discrepancy_resolution_is_idempotent(): void
    {
        Storage::fake('local');
        $fixture = $this->transferFixture();
        $transfer = $this->approvedTransfer($fixture);
        $item = $transfer->items()->firstOrFail();
        $this->dispatch($fixture, $transfer, $item->id);

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.receipts.store', $transfer), [
                'items' => [[
                    'item_id' => $item->id,
                    'receiving_result' => 'missing',
                    'quantity' => 1,
                    'condition' => 'good',
                    'notes' => 'Unit belum ditemukan.',
                    'capture_source' => 'camera',
                    'photos' => [UploadedFile::fake()->image('resolve-idempotent.jpg')],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $resolution = [
            'resolution_action' => 'return_to_origin',
            'notes' => 'Unit dipastikan tetap berada di cabang asal.',
        ];

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.items.resolve', [$transfer, $item]), $resolution)
            ->assertSessionHasNoErrors();
        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.items.resolve', [$transfer, $item]), $resolution)
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('resolved', $item->status->value);
        $this->assertSame('return_to_origin', $item->resolution_action);
        $this->assertSame(0, $item->received_quantity);
    }

    public function test_receiving_rejects_item_from_another_transfer(): void
    {
        Storage::fake('local');
        $fixture = $this->transferFixture();
        $transfer = $this->approvedTransfer($fixture);
        $item = $transfer->items()->firstOrFail();
        $this->dispatch($fixture, $transfer, $item->id);

        $secondFixture = $this->transferFixture('ALT');
        $otherTransfer = $this->approvedTransfer($secondFixture);
        $otherItem = $otherTransfer->items()->firstOrFail();

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.receipts.store', $transfer), [
                'items' => [[
                    'item_id' => $otherItem->id,
                    'receiving_result' => 'accepted_good',
                    'quantity' => 1,
                    'condition' => 'good',
                    'notes' => 'Payload salah transfer.',
                    'capture_source' => 'camera',
                    'photos' => [UploadedFile::fake()->image('foreign-item.jpg')],
                ]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame('dispatched', $transfer->fresh()->status->value);
        $this->assertSame('dispatched', $item->fresh()->status->value);
    }

    /** @param array<string, mixed> $fixture */
    private function approvedTransfer(array $fixture): BranchTransfer
    {
        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture, true));
        $transfer = BranchTransfer::query()->latest('id')->firstOrFail();
        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'destination',
                'decision' => 'approved',
                'notes' => 'Cabang tujuan siap menerima.',
                'revision_number' => $transfer->revision_number,
            ]);

        return $transfer->fresh(['items']);
    }

    /** @param array<string, mixed> $fixture */
    private function dispatch(array $fixture, BranchTransfer $transfer, int $itemId): void
    {
        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.dispatch.store', $transfer), [
                'shipping_method' => 'internal',
                'courier_name' => 'Tim Operasional PNG',
                'waybill_number' => 'SJ-TRF-TEST',
                'inspections' => [[
                    'item_id' => $itemId,
                    'condition' => 'good',
                    'notes' => 'Kondisi awal baik.',
                    'capture_source' => 'camera',
                    'photos' => [UploadedFile::fake()->image('dispatch.jpg')],
                ]],
            ])
            ->assertSessionHasNoErrors();
    }
}
