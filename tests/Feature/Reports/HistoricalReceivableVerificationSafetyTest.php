<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;

class HistoricalReceivableVerificationSafetyTest extends TestCase
{
    public function test_r1_verifier_refuses_testing_sqlite_database(): void
    {
        $this->artisan('reports:verify-receivables-asof', [
            '--branch' => 'PNG',
            '--as-of' => '2026-09-23',
            '--expect-total' => '1285000',
        ])->expectsOutputToContain('SAFETY STOP')->assertExitCode(1);
    }
}
