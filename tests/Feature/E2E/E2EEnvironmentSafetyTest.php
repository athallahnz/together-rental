<?php

namespace Tests\Feature\E2E;

use Database\Seeders\E2EUatSeeder;
use Illuminate\Console\Command;
use RuntimeException;
use Tests\TestCase;

class E2EEnvironmentSafetyTest extends TestCase
{
    public function test_prepare_command_refuses_non_e2e_environment(): void
    {
        $this->artisan('e2e:prepare', ['--force' => true])
            ->expectsOutputToContain('APP_ENV=e2e')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_uat_seeder_refuses_non_e2e_environment(): void
    {
        $this->expectException(RuntimeException::class);

        (new E2EUatSeeder)->run();
    }
}
