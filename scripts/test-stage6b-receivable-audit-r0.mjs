import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const audit = readFileSync('app/Console/Commands/AuditReceivablesAsOf.php', 'utf8');
const test = readFileSync('tests/Feature/Reports/HistoricalReceivableAuditSafetyTest.php', 'utf8');
const checks = [
    /reports:audit-receivables-asof/,
    /app\(\)->environment\('uat'\)/,
    /getDriverName\(\) !== 'mysql'/,
    /getDatabaseName\(\) !== 'together_rental_uat'/,
    /FUTURE_CHECKOUT_INCLUDED/,
    /PAYMENTS_AFTER_CUTOFF/,
    /PAID_REFUNDS_AFTER_CUTOFF/,
    /VOID_AFTER_CUTOFF/,
    /FINANCIAL_ADJUSTMENT_AFTER_CUTOFF/,
    /PAST_TOTAL_NEEDS_EVENT_RECONSTRUCTION/,
    /LEGACY_WITHOUT_COMPLETE_EVENT_HISTORY/,
    /CURRENT_STATUS_CANNOT_PROVE_PAST/,
    /UAT DIAGNOSTIC COMPLETE/,
];

for (const check of checks) {
    assert.match(audit, check);
}

assert.doesNotMatch(audit, /(?:DB::table\([^\n]+\)|->)\s*(?:insert|update|upsert|delete|truncate|statement|unprepared)\s*\(/);
assert.match(test, /test_uat_historical_auditor_refuses_testing_database/);
assert.match(test, /test_uat_historical_branch_listing_refuses_testing_database/);
console.log(`STAGE 6B R0 STATIC PASS: ${checks.length} safeguards/event classes; no DB mutators; 2 UAT-environment gate tests.`);
