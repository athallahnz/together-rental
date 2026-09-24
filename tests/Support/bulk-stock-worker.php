<?php

use App\Domain\Bookings\BookingManager;
use App\Domain\Transfers\TransferInventorySynchronizer;
use App\Models\Booking;
use App\Models\BranchTransfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$connection = $input['connection'];
if (($connection['driver'] ?? null) !== 'mysql'
    || ($connection['database'] ?? null) !== 'together_rental_bulk_concurrency_test') {
    fwrite(STDERR, 'Worker requires the isolated bulk concurrency test database.');
    exit(2);
}

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
DB::purge('mysql');
CarbonImmutable::setTestNow($input['now']);
Carbon::setTestNow($input['now']);
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
$deadline = microtime(true) + 15;
while (! is_file($input['barrier'])) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, 'Concurrency barrier timed out.');
        exit(2);
    }
    usleep(10000);
    clearstatcache(true, $input['barrier']);
}

try {
    $actor = User::query()->findOrFail($input['actor_id']);
    match ($input['operation']) {
        'create' => app(BookingManager::class)->create($input['payload'], $actor),
        'update' => app(BookingManager::class)->update(Booking::query()->findOrFail($input['booking_id']), $input['payload'], $actor),
        'transfer' => DB::transaction(fn () => app(TransferInventorySynchronizer::class)->hold(
            BranchTransfer::query()->findOrFail($input['transfer_id']),
        ), 3),
        default => throw new LogicException('Unknown worker operation.'),
    };
    fwrite(STDOUT, "ACCEPTED\n");
} catch (ValidationException) {
    fwrite(STDOUT, "REJECTED\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(2);
}
