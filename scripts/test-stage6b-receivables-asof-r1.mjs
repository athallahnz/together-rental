import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const service = readFileSync('app/Domain/Reporting/IntegratedReportService.php', 'utf8');
const projector = readFileSync('app/Domain/Reporting/HistoricalReceivableProjector.php', 'utf8');
const controller = readFileSync('app/Http/Controllers/IntegratedReportController.php', 'utf8');
const verifier = readFileSync('app/Console/Commands/VerifyReceivablesAsOf.php', 'utf8');

const required = [
    [/historicalMeta|historyMeta/, service],
    [/HistoryMeta|historyMeta/, service],
    [/HistoricalReceivableProjector::class/, service],
    [/branch_totals/, service],
    [/payment->type !== 'rental'/, projector],
    [/paid_at/, projector],
    [/voided_at/, projector],
    [/processed_at/, projector],
    [/LATER_RETURN_CHARGE/, projector],
    [/LATER_EXTENSION_REQUIRES_PRICE_SNAPSHOT/, projector],
    [/LEGACY_EVENT_HISTORY/, projector],
    [/BACKDATED_PAYMENT/, projector],
    [/history_quality/, projector],
    [/HISTORIS/, controller],
    [/getDatabaseName\(\) !== 'together_rental_uat'/, verifier],
    [/R1 UAT RECONCILIATION/, verifier],
];

for (const [regex, source] of required) {
    assert.match(source, regex);
}

for (const source of [projector, verifier]) {
    assert.doesNotMatch(source, /(?:DB::table\([^\n]+\)|->)\s*(?:insert|update|upsert|delete|truncate|statement|unprepared)\s*\(/);
}

console.log(`STAGE 6B R1 STATIC PASS: ${required.length} event, guard, report, export checks; no DB mutations.`);
