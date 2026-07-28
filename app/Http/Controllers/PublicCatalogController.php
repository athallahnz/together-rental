<?php

namespace App\Http\Controllers;

use App\Domain\PublicCatalog\PublicCatalogService;
use App\Http\Requests\PublicCatalogRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicCatalogController extends Controller
{
    public function home(Request $request, PublicCatalogService $catalog): Response
    {
        return Inertia::render(
            'public/home',
            $catalog->home($request->string('branch')->toString() ?: null),
        );
    }

    public function index(
        PublicCatalogRequest $request,
        PublicCatalogService $catalog,
    ): Response {
        return Inertia::render(
            'public/catalog',
            $catalog->catalog($request->validated()),
        );
    }

    public function product(
        Request $request,
        string $slug,
        PublicCatalogService $catalog,
    ): Response {
        return Inertia::render(
            'public/product-show',
            $catalog->product(
                $slug,
                $request->string('branch')->toString() ?: null,
            ),
        );
    }

    public function package(
        Request $request,
        string $slug,
        PublicCatalogService $catalog,
    ): Response {
        return Inertia::render(
            'public/package-show',
            $catalog->package(
                $slug,
                $request->string('branch')->toString() ?: null,
            ),
        );
    }
}
