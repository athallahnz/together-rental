<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\RentalPackage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PublicSitemapController extends Controller
{
    public function __invoke(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $urls = collect([
            ['loc' => $base.'/', 'lastmod' => now()->toDateString(), 'priority' => '1.0'],
            ['loc' => $base.'/rental', 'lastmod' => now()->toDateString(), 'priority' => '0.9'],
        ]);

        Branch::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereIn('id', DB::table('branch_settings')
                ->where('key', 'public_catalog_enabled')
                ->where('value', 'true')
                ->pluck('branch_id'))
            ->get(['code', 'updated_at'])
            ->each(fn (Branch $branch) => $urls->push([
                'loc' => $base.'/rental?branch='.$branch->code,
                'lastmod' => $branch->updated_at?->toDateString() ?? now()->toDateString(),
                'priority' => '0.8',
            ]));

        Product::query()
            ->where('is_active', true)
            ->where('is_rentable', true)
            ->where('is_public', true)
            ->whereNull('deleted_at')
            ->get(['slug', 'updated_at'])
            ->each(fn (Product $product) => $urls->push([
                'loc' => $base.'/rental/products/'.$product->slug,
                'lastmod' => $product->updated_at?->toDateString() ?? now()->toDateString(),
                'priority' => '0.7',
            ]));

        RentalPackage::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNull('deleted_at')
            ->get(['slug', 'updated_at'])
            ->each(fn (RentalPackage $package) => $urls->push([
                'loc' => $base.'/rental/packages/'.$package->slug,
                'lastmod' => $package->updated_at?->toDateString() ?? now()->toDateString(),
                'priority' => '0.7',
            ]));

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
