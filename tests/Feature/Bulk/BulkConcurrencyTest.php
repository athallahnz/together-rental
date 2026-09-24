<?php

namespace Tests\Feature\Bulk;

use App\Models\Branch;
use App\Models\BranchTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BulkConcurrencyTest extends TestCase
{
    use InteractsWithBulkStock;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('BULK_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Opt in using phpunit.bulk-concurrency.xml and the isolated MySQL database.');
        }
        // This guard MUST stay before migrate:fresh. Never use the UAT database.
        if (DB::connection()->getDriverName() !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'together_rental_bulk_concurrency_test') {
            $this->fail('Refusing migrations outside together_rental_bulk_concurrency_test.');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00:00'));
        $this->prepareBulkFixture();
    }

    public function test_two_parallel_bookings_cannot_both_take_two_of_three_units(): void
    {
        $results = $this->race([
            ['operation' => 'create', 'payload' => $this->bulkPayload(2)],
            ['operation' => 'create', 'payload' => $this->bulkPayload(2)],
        ]);
        $this->assertSame(['ACCEPTED', 'REJECTED'], $results);
        $this->assertSame(2, $this->inventory->fresh()->quantity_reserved);
        $this->assertDatabaseCount('bulk_reservations', 1);
    }

    public function test_parallel_edits_on_different_bookings_share_the_stock_lock(): void
    {
        $first = $this->book(1, 3);
        $second = $this->book(1, 4);
        $results = $this->race([
            ['operation' => 'update', 'booking_id' => $first->id, 'payload' => $this->bulkPayload(2)],
            ['operation' => 'update', 'booking_id' => $second->id, 'payload' => $this->bulkPayload(2)],
        ]);
        $this->assertSame(['ACCEPTED', 'REJECTED'], $results);
        $this->assertSame(2, $this->inventory->fresh()->quantity_reserved);
        $this->assertDatabaseCount('bulk_reservations', 2);
    }

    public function test_parallel_booking_and_transfer_cannot_promise_the_same_stock(): void
    {
        $transfer = BranchTransfer::query()->create([
            'company_id' => $this->branch->company_id, 'from_branch_id' => $this->branch->id,
            'to_branch_id' => Branch::query()->where('code', 'PNG')->firstOrFail()->id,
            'transfer_number' => 'CONCURRENT-BULK', 'status' => 'draft', 'planned_dispatch_at' => now(),
        ]);
        $transfer->items()->create(['line_number' => 1, 'product_id' => $this->product->id, 'quantity' => 2, 'status' => 'pending']);
        $results = $this->race([
            ['operation' => 'create', 'payload' => $this->bulkPayload(2)],
            ['operation' => 'transfer', 'transfer_id' => $transfer->id],
        ]);
        $this->assertSame(['ACCEPTED', 'REJECTED'], $results);
        $stock = $this->inventory->fresh();
        $this->assertSame(2, $stock->quantity_reserved + $stock->quantity_in_transfer);
    }

    /** @param list<array<string, mixed>> $jobs
     * @return list<string>
     */
    private function race(array $jobs): array
    {
        $barrier = sys_get_temp_dir().'/bulk-stock-'.bin2hex(random_bytes(12));
        $processes = [];
        try {
            foreach ($jobs as $job) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/bulk-stock-worker.php')], base_path());
                $process->setInput(json_encode([
                    ...$job,
                    'connection' => DB::connection()->getConfig(),
                    'actor_id' => $this->operator->id,
                    'now' => now()->toDateTimeString(),
                    'barrier' => $barrier,
                ], JSON_THROW_ON_ERROR));
                $process->setTimeout(40);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $ready = $process->waitUntil(fn (string $type, string $output): bool => str_contains($process->getOutput(), 'READY'));
                $this->assertTrue($ready, $process->getErrorOutput());
            }
            touch($barrier);
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = str_contains($process->getOutput(), 'ACCEPTED') ? 'ACCEPTED' : 'REJECTED';
            }
            sort($results);

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            if (is_file($barrier)) {
                unlink($barrier);
            }
        }
    }
}
