<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Finance\FinanceMasterManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveFinancialCategoryRequest;
use App\Models\FinancialCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FinancialCategoryController extends Controller
{
    public function store(
        SaveFinancialCategoryRequest $request,
        FinanceMasterManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $category = $manager->createCategory($request->validated(), $request->user());
        $recorder->record($request, 'finance.category.created', $category, null, $category->toArray());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Kategori keuangan {$category->code} berhasil ditambahkan.",
        ]);
    }

    public function update(
        SaveFinancialCategoryRequest $request,
        FinancialCategory $financialCategory,
        FinanceMasterManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $before = $financialCategory->toArray();
        $category = $manager->updateCategory(
            $financialCategory,
            $request->validated(),
            $request->user(),
        );
        $recorder->record($request, 'finance.category.updated', $category, $before, $category->toArray());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Kategori keuangan {$category->code} berhasil diperbarui.",
        ]);
    }

    public function toggleStatus(
        Request $request,
        FinancialCategory $financialCategory,
        FinanceMasterManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('finance.categories.manage');
        $before = ['is_active' => $financialCategory->is_active];
        $category = $manager->toggleCategory($financialCategory, $request->user());
        $recorder->record(
            $request,
            $category->is_active
                ? 'finance.category.activated'
                : 'finance.category.deactivated',
            $category,
            $before,
            ['is_active' => $category->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Kategori keuangan {$category->code} berhasil "
                .($category->is_active ? 'diaktifkan.' : 'dinonaktifkan.'),
        ]);
    }
}
