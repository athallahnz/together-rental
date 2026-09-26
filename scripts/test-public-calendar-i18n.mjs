import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import ts from 'typescript';

const root = 'resources/js/';
const catalog = readFileSync(`${root}lib/i18n-catalog.ts`, 'utf8');
const shared = readFileSync(`${root}components/catalog/asset-smart-calendar.tsx`, 'utf8');
const publicCalendar = readFileSync(`${root}components/public/public-asset-calendar.tsx`, 'utf8');
const file = ts.createSourceFile('i18n-catalog.ts', catalog, ts.ScriptTarget.Latest, true);
const dictionaries = new Map();
let checks = 0;

for (const statement of file.statements) {
    if (!ts.isVariableStatement(statement)) {
        continue;
    }

    for (const declaration of statement.declarationList.declarations) {
        if (!['idMessages', 'enMessages'].includes(declaration.name.getText(file))) {
            continue;
        }

        const entries = new Map();
        const object = ts.isAsExpression(declaration.initializer)
            ? declaration.initializer.expression
            : declaration.initializer;

        assert.ok(ts.isObjectLiteralExpression(object));

        for (const item of object.properties) {
            assert.ok(ts.isPropertyAssignment(item));
            assert.ok(ts.isStringLiteral(item.initializer));
            entries.set(item.name.getText(file).replaceAll(/["']/g, ''), item.initializer.text);
        }

        dictionaries.set(declaration.name.getText(file), entries);
    }
}

const keys = {
    'public.detail.calendar.event.booked': ['Dipesan', 'Booked'],
    'public.detail.calendar.event.rented': ['Disewa', 'Rented'],
    'public.detail.calendar.event.maintenance': ['Perawatan', 'Maintenance'],
    'public.detail.calendar.event.inTransit': ['Dalam pengiriman', 'In transit'],
    'public.detail.calendar.available': ['Tersedia', 'Available'],
    'public.detail.calendar.condition.excellent': ['Sangat baik', 'Excellent'],
    'public.detail.calendar.condition.good': ['Baik', 'Good'],
    'public.detail.calendar.condition.fair': ['Cukup baik', 'Fair'],
    'public.detail.calendar.condition.poor': ['Kurang baik', 'Poor'],
    'public.detail.calendar.condition.damaged': ['Rusak', 'Damaged'],
    'public.detail.calendar.condition.critical': ['Kritis', 'Critical'],
    'public.detail.calendar.condition.lost': ['Hilang', 'Lost'],
    'public.detail.calendar.condition.retired': ['Dihentikan', 'Retired'],
    'public.detail.calendar.privacy': [
        'Identitas unit, nomor pemesanan, penyewaan, dan pelanggan disembunyikan pada kalender publik.',
        'Unit identities, booking and rental numbers, and customer details are hidden on the public calendar.',
    ],
    'public.detail.calendar.previous': ['Bulan sebelumnya', 'Previous month'],
    'public.detail.calendar.next': ['Bulan berikutnya', 'Next month'],
};

for (const [key, values] of Object.entries(keys)) {
    assert.equal(dictionaries.get('idMessages').get(key), values[0], `ID key ${key}`);
    checks++;
    assert.equal(dictionaries.get('enMessages').get(key), values[1], `EN key ${key}`);
    checks++;
}

for (const status of ['booked', 'rented', 'maintenance', 'in_transit']) {
    assert.match(shared, new RegExp(`${status}: 'public\\.detail\\.calendar\\.event\\.`));
    checks++;
}

for (const condition of ['good', 'fair', 'damaged', 'lost']) {
    assert.match(publicCalendar, new RegExp(`${condition}: 'public\\.detail\\.calendar\\.condition\\.`));
    checks++;
}

for (const expression of [
    'publicEventLabel(status, locale)',
    'publicEventLabel(event.status, locale)',
    "translateKey('public.detail.calendar.available', locale)",
    "translateKey('public.detail.calendar.previous', locale)",
    "translateKey('public.detail.calendar.next', locale)",
    "locale === 'en' ? 'en-US' : 'id-ID'",
    "['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']",
]) {
    assert.ok(shared.includes(expression), expression);
    checks++;
}

for (const expression of [
    'locale={locale}',
    'conditionLabel(selectedUnit.condition, locale)',
    "tr('public.detail.calendar.privacy')",
]) {
    assert.ok(publicCalendar.includes(expression), expression);
    checks++;
}

assert.ok(!publicCalendar.includes('{data.privacy_note}'));
checks++;
assert.ok(!shared.includes('{event.label}\n'));
checks++;
assert.equal(new Intl.DateTimeFormat('en-US', { month: 'long' }).format(new Date(2026, 8, 1)), 'September');
checks++;
assert.equal(new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(new Date(2026, 8, 1)), 'September');
checks++;
assert.equal(new Intl.DateTimeFormat('en-US', { month: 'long' }).format(new Date(2026, 9, 1)), 'October');
checks++;
assert.equal(new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(new Date(2026, 9, 1)), 'Oktober');
checks++;

console.log(`PUBLIC CALENDAR I18N PASS: ${checks} checks (16 bilingual keys, public status/condition, weekdays, month, privacy, internal default)`);
