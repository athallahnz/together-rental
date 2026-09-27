/** UAT-035B S2.2A.1 — regression for persisted default hero and PHP duration labels. */
import assert from 'node:assert/strict';
import { log } from 'node:console';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import ts from 'typescript';

const file = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const source = file('resources/js/lib/public-i18n.ts');
const compiled = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText;
const catalog = {
    'public.home.fallbackHero': {
        id: 'Sewa alat kreatif tanpa ribet.',
        en: 'Rent creative equipment, made simple.',
    },
    'public.home.fallbackDescription': {
        id: 'Penyewaan kamera dan perlengkapan produksi yang terawat, transparan, dan siap digunakan.',
        en: 'Well-maintained camera and production equipment rentals with transparent pricing, ready to use.',
    },
};
const module = { exports: {} };

runInNewContext(compiled, {
    module,
    exports: module.exports,
    require: (name) => {
        assert.equal(name, '@/lib/i18n');

        return { translateKey: (key, locale) => catalog[key]?.[locale] };
    },
    Intl,
});

const { publicHomeText, publicRateDurationLabel } = module.exports;
let checks = 0;

function check(actual, expected, message) {
    assert.equal(actual, expected, message);
    checks++;
}

for (const [stored, locale, expected] of [
    ['Sewa alat kreatif tanpa ribet.', 'id', 'Sewa alat kreatif tanpa ribet.'],
    ['Sewa alat kreatif tanpa ribet.', 'en', 'Rent creative equipment, made simple.'],
    [null, 'en', 'Rent creative equipment, made simple.'],
    ['', 'id', 'Sewa alat kreatif tanpa ribet.'],
    ['A personalized branch title', 'en', 'A personalized branch title'],
    ['Judul promosi khusus', 'en', 'Judul promosi khusus'],
]) {
    check(publicHomeText(stored, 'public.home.fallbackHero', locale), expected, 'Hero copy');
}

check(
    publicHomeText(catalog['public.home.fallbackDescription'].id, 'public.home.fallbackDescription', 'en'),
    catalog['public.home.fallbackDescription'].en,
    'Previously saved system default description must localize',
);
check(
    publicHomeText('Deskripsi khusus operator', 'public.home.fallbackDescription', 'en'),
    'Deskripsi khusus operator',
    'Never replace operator-authored descriptions',
);

for (const [label, locale, expected] of [
    ['12 Jam', 'id', '12 jam'],
    ['12 Jam', 'en', '12 hours'],
    ['1 Jam', 'en', '1 hour'],
    ['0 Jam', 'en', '0 hours'],
    ['30 Menit', 'en', '30 minutes'],
    ['1 Hari', 'en', '1 day'],
    ['2 Minggu', 'en', '2 weeks'],
    ['1 Bulan', 'en', '1 month'],
    ['12 sessions', 'en', '12 sessions'],
]) {
    check(publicRateDurationLabel(label, locale), expected, 'Public rate duration');
}

const home = file('resources/js/pages/public/home.tsx');
const card = file('resources/js/components/public/product-card.tsx');

assert(home.includes("'public.home.fallbackHero',") && home.includes('publicHomeText('));
checks++;
assert(card.includes('publicRateDurationLabel(product.rates[0].duration_label, locale)'));
checks++;
log(`PUBLIC S2.2A.1 PASS: ${checks} assertions (seeded hero, custom content, hours/plurals)`);
