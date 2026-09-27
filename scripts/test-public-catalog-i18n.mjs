/** UAT-035B S2.2A — public home/catalog and shared cards, static regression. */
import assert from 'node:assert/strict';
import { log } from 'node:console';
import { readFileSync } from 'node:fs';
import { Buffer } from 'node:buffer';
import ts from 'typescript';

const root = new URL('../', import.meta.url);
const source = (path) => readFileSync(new URL(path, root), 'utf8');
const catalog = ts.transpileModule(source('resources/js/lib/i18n-catalog.ts'), {
    compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 },
}).outputText;
const { idMessages, enMessages } = await import(
    `data:text/javascript;base64,${Buffer.from(catalog).toString('base64')}`
);

let checks = 0;
function check(condition, message) {
    assert(condition, message);
    checks++;
}

const publicKeys = Object.keys(idMessages).filter((key) =>
    /^public\.(home|catalog|common)\./.test(key),
);
check(publicKeys.length >= 84, 'Expected at least 84 public catalog copy keys');

for (const key of publicKeys) {
    check(typeof idMessages[key] === 'string' && idMessages[key].trim().length > 0,
        `Missing Indonesian copy: ${key}`);
    check(typeof enMessages[key] === 'string' && enMessages[key].trim().length > 0,
        `Missing English copy: ${key}`);
}

for (const [key, id, en] of [
    ['public.home.ready', 'Siap disewa', 'Ready to rent'],
    ['public.catalog.search', 'Cari', 'Search'],
    ['public.common.available', 'Tersedia', 'Available'],
    ['public.common.inTransit', 'Dalam pengiriman', 'In transit'],
]) {
    check(idMessages[key] === id && enMessages[key] === en,
        `Locale switch regression: ${key}`);
}

const sources = [
    'resources/js/pages/public/home.tsx',
    'resources/js/pages/public/catalog.tsx',
    'resources/js/components/public/product-card.tsx',
    'resources/js/components/public/package-card.tsx',
];

for (const path of sources) {
    const text = source(path);
    const ast = ts.createSourceFile(path, text, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    check(ast.parseDiagnostics.length === 0, `JSX syntax error: ${path}`);
    check(text.includes('useAppLocale()'), `Locale missing: ${path}`);

    function scan(node) {
        if (ts.isJsxText(node)) {
            const value = node.getText(ast).replace(/\s+/g, ' ').trim();
            const allowed = ['', '+', '×', 'Together Kamera ·', 'Canon · Sony · Fujifilm · Nikon · DJI'];
            check(allowed.includes(value) || /^[\d\s+×–—./()]+$/.test(value),
                `${path} has untranslated JSX text: ${value}`);
        }

        ts.forEachChild(node, scan);
    }
    scan(ast);

    for (const [, key] of text.matchAll(/tr\('([^']+)'\)/g)) {
        check(Object.hasOwn(idMessages, key) && Object.hasOwn(enMessages, key),
            `Unrecognized translation ${key} in ${path}`);
    }
}

const home = source(sources[0]);
const listing = source(sources[1]);
const productCard = source(sources[2]);
const packageCard = source(sources[3]);
check(home.includes("tr('public.home.fallbackDescription')"),
    'Homepage fallback description must use locale');
check(home.includes("tr('public.home.fallbackHero')"),
    'Homepage fallback hero must use locale');
check(home.includes("tr('public.home.ogTitle')"),
    'Homepage SEO must follow locale');
check(listing.includes("tr('public.catalog.searchPlaceholder')"),
    'Catalog placeholder must follow locale');
check(listing.includes("tr('public.catalog.noResults')"),
    'Empty search state must follow locale');
check(listing.includes("tr('public.catalog.pagination')"),
    'Pagination aria-label must follow locale');
check(listing.includes("router.get('/rental', form"),
    'Catalog search URL and filter logic must be preserved');
check(productCard.includes('publicAvailabilityLabel(product.availability.status, locale)'),
    'Card status must use stable status codes');
check(productCard.includes('formatMoney(product.starting_price, locale)'),
    'Product price must use selected locale');
check(packageCard.includes('formatMoney(rentalPackage.starting_price, locale)'),
    'Package price must use selected locale');

const statuses = source('resources/js/lib/public-i18n.ts');

for (const status of ['available', 'limited', 'in_transit', 'unavailable']) {
    check(statuses.includes(`${status}: 'public.common.`),
        `Untranslated stable availability code: ${status}`);
}

log(`PUBLIC CATALOG S2.2A PASS: ${checks} checks (${publicKeys.length} keys, 4 JSX files, status and filter contracts)`);
