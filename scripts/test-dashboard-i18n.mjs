/** UAT-035B S1.2: dashboard translation and backend-key contract (no database access). */
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { log, error } from 'node:console';
import process from 'node:process';
import { Buffer } from 'node:buffer';
import ts from 'typescript';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const page = readFileSync(join(root, 'resources/js/pages/dashboard.tsx'), 'utf8');
const service = readFileSync(join(root, 'app/Domain/Dashboard/OperationalDashboardService.php'), 'utf8');
const catalog = readFileSync(join(root, 'resources/js/lib/i18n-catalog.ts'), 'utf8');
const transpiled = ts.transpileModule(catalog, {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext },
}).outputText;
const { idMessages, enMessages, idPlural, enPlural } = await import(
    `data:text/javascript;base64,${Buffer.from(transpiled).toString('base64')}`
);
const problems = [];
let checks = 0;

function assert(ok, message) {
    checks++;

    if (!ok) {
        problems.push(message);
    }
}

const idKeys = Object.keys(idMessages);
const enKeys = Object.keys(enMessages);
assert(idKeys.join('|') === enKeys.join('|'), 'ID/EN message keys must match');
assert(Object.keys(idPlural).join('|') === Object.keys(enPlural).join('|'), 'ID/EN plurals must match');

for (const key of idKeys.filter((entry) => entry.startsWith('dashboard.'))) {
    assert(idMessages[key].trim().length > 0 && enMessages[key].trim().length > 0,
        `Translation missing for ${key}`);
    const placeholders = (value) => [...value.matchAll(/\{([a-zA-Z][a-zA-Z0-9_]*)\}/g)]
        .map((match) => match[1]).sort().join(',');
    assert(placeholders(idMessages[key]) === placeholders(enMessages[key]),
        `Placeholder mismatch for ${key}`);
}

// Keep server computation, permissions, counts and URLs untouched. Client
// translates only stable keys returned by OperationalDashboardService.
const actions = service.split('private function quickActions(')[1]
    ?.split('private function attention(')[0] ?? '';
const attention = service.split('private function attention(')[1]
    ?.split('private function pushAttention(')[0] ?? '';

for (const [kind, segment] of [['action', actions], ['attention', attention]]) {
    const keys = [...segment.matchAll(/'key'\s*=>\s*'([^']+)'/g)].map((match) => match[1]);
    assert(keys.length > 0, `Could not discover backend ${kind} keys`);

    for (const key of keys) {
        for (const suffix of ['title', 'description']) {
            const messageKey = `dashboard.${kind}.${key}.${suffix}`;
            assert(Boolean(idMessages[messageKey] && enMessages[messageKey]),
                `Missing backend ${kind} key translation: ${messageKey}`);
            assert(page.includes(`'${messageKey}'`), `Dashboard not wired to ${messageKey}`);
        }
    }
}

for (const item of ['Command Center', 'Refresh\n', 'Total unit dalam scope',
    'Mulai Stock Opname', 'Maintenance aktif', 'Jadwal pickup dan return']) {
    assert(!page.includes(item), `Hardcoded dashboard copy remains: ${item}`);
}

for (const key of [
    'dashboard.commandCenter', 'dashboard.refresh', 'dashboard.metrics.bookings',
    'dashboard.attention.maintenance-open.title', 'dashboard.action.stock-opname.title',
]) {
    assert(idMessages[key] !== enMessages[key], `ID and EN unexpectedly identical: ${key}`);
}

assert(page.includes('tr(copy.title)') && page.includes('tr(copy.description)'),
    'Quick actions must render semantic translations');
assert(page.includes('attentionCopy[item.key]'),
    'Priority queue must translate by stable backend keys');
assert(page.includes('useDashboardLocale()'), 'Dashboard locale hook missing');

if (problems.length) {
    error(problems.join('\n'));
    process.exitCode = 1;
} else {
    log(`DASHBOARD I18N PASS: ${checks} checks (${idKeys.filter(k => k.startsWith('dashboard.')).length} dashboard keys, backend key contracts, hardcode scan)`);
}
