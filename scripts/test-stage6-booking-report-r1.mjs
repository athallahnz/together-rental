import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const report = readFileSync('app/Domain/Reporting/IntegratedReportService.php', 'utf8');
const test = readFileSync('tests/Feature/Reports/BookingPaymentReportCorrectionTest.php', 'utf8');
const audit = readFileSync('app/Console/Commands/AuditBookingReportPayments.php', 'utf8');

for (const expected of [
    "->where('payments.status', 'completed')",
    "->where('payments.direction', 'in')",
    "->where('payments.type', 'rental')",
    "->where('status', 'paid')",
    "->groupBy('payment_id')",
    "->groupBy('payments.booking_id')",
    "'paid_amount' => $rentalPaid",
    "'balance_due' => max(0, round((float) $row->total_amount - $rentalPaid, 2))",
]) {
    assert.ok(report.includes(expected), `Missing reporting invariant: ${expected}`);
}

const operation = report.slice(report.indexOf('private function operationalDataset'), report.indexOf('private function financeDataset'));
assert.ok(!operation.includes("(float) $row->deposit_paid"), 'Security deposit erroneously reused as rental payment');
assert.ok(test.includes('BookingPaymentSettlement::class'), 'No comparison to canonical finance settlement');
assert.ok(test.includes("'format' => 'excel'"), 'No Excel parity test');
assert.ok(test.includes('test_live_uat_auditor_refuses_a_non_uat_database'), 'Missing non-UAT safety test');

for (const expected of ['together_rental_uat', "app()->environment('uat')", 'UAT PASS:', 'BookingPaymentSettlement']) {
    assert.ok(audit.includes(expected), `Missing UAT guard/reconciliation: ${expected}`);
}

console.log('STAGE 6A BOOKING REPORT R1 STATIC PASS: completed rental only, per-payment paid refund, zero clamp, no deposit substitution, fixture/export/branch/UAT coverage.');
