<?php

namespace App\Console\Commands;

use Database\Seeders\E2EUatSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class PrepareE2EEnvironment extends Command
{
    /** @var string */
    protected $signature = 'e2e:prepare
                            {--force : Confirm the destructive reset of the dedicated E2E database}';

    /** @var string */
    protected $description = 'Rebuild and seed the guarded database used by Playwright internal UAT';

    public function handle(): int
    {
        if (! app()->environment('e2e')) {
            $this->components->error('Refusing reset: run this command with APP_ENV=e2e / --env=e2e.');

            return self::FAILURE;
        }

        if (config('e2e.allow_database_reset') !== true) {
            $this->components->error('Refusing reset: E2E_ALLOW_DATABASE_RESET must be true.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->components->error('Refusing reset: pass --force after verifying the dedicated E2E database.');

            return self::FAILURE;
        }

        $connection = (string) config('database.default');

        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            $this->components->error("Refusing reset: connection [{$connection}] is not mysql or mariadb.");

            return self::FAILURE;
        }

        $databaseConnection = DB::connection($connection);
        $database = (string) $databaseConnection->getConfig('database');
        $host = mb_strtolower((string) $databaseConnection->getConfig('host'));

        if (! str_ends_with(mb_strtolower($database), '_e2e')) {
            $this->components->error("Refusing reset: database [{$database}] must end with _e2e.");

            return self::FAILURE;
        }

        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->components->error("Refusing reset: database host [{$host}] is not local.");

            return self::FAILURE;
        }

        $this->components->info("Rebuilding guarded E2E database [{$database}] on [{$host}].");

        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => $connection,
            '--seed' => true,
            '--seeder' => E2EUatSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ], $this->output);

        if ($exitCode !== self::SUCCESS) {
            $this->components->error('E2E database preparation failed.');

            return self::FAILURE;
        }

        $this->components->success('Dedicated E2E database is ready.');

        return self::SUCCESS;
    }
}
