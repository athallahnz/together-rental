import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (name) => readFileSync(name, 'utf8');
const catalog = read('resources/js/lib/i18n-catalog.ts');
const pages = [
    'resources/js/pages/public/product-show.tsx',
    'resources/js/pages/public/package-show.tsx',
    'resources/js/components/public/availability-planner.tsx',
    'resources/js/components/public/public-asset-calendar.tsx',
];
let checks = 0;

for (const file of pages) {
    const source = read(file);
    assert.match(source, /useAppLocale/);
    assert.match(source, /public\.detail\./);
    checks += 2;
}

for (const key of ['public.detail.back', 'public.detail.planner.title', 'public.detail.calendar.eyebrow']) {
    assert.equal(catalog.split(`"${key}"`).length, 3, `missing ID/EN: ${key}`);
    checks++;
}

const request = read('app/Http/Requests/PublicAvailabilityRequest.php');
assert.match(request, /app\(\)->getLocale\(\) === 'en'/);
checks++;
console.log(`PUBLIC DETAIL I18N PASS: ${checks} static checks; run project TS, lint, PHP and feature tests separately`);
