<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;

class HistoricalReceivableAuditSafetyTest extends TestCase
{
    public function test_uat_historical_auditor_refuses_testing_database(): void
    {
        $this->artisan('reports:audit-receivables-asof', [
            '--branch' => 'PNG',
            '--as-of' => '2026-09-15',
        ])->expectsOutputToContain('SAFETY STOP')->assertExitCode(1);
    }

    public function test_uat_historical_branch_listing_refuses_testing_database(): void
    {
        $this->artisan('reports:audit-receivables-asof', [
            '--list-branches' => true,
        ])->expectsOutputToContain('SAFETY STOP')->assertExitCode(1);
    }
}
