<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Finance\FinanceMasterManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SavePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentMethodController extends Controller
{
    public function store(
        SavePaymentMethodRequest $request,
        FinanceMasterManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $method = $manager->createPaymentMethod($request->validated(), $request->user());
        $recorder->record($request, 'finance.payment_method.created', $method, null, $method->toArray());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Metode pembayaran {$method->code} berhasil ditambahkan.",
        ]);
    }

    public function update(
        SavePaymentMethodRequest $request,
        PaymentMethod $paymentMethod,
        FinanceMasterManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $before = $paymentMethod->toArray();
        $method = $manager->updatePaymentMethod(
            $paymentMethod,
            $request->validated(),
            $request->user(),
        );
        $recorder->record($request, 'finance.payment_method.updated', $method, $before, $method->toArray());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Metode pembayaran {$method->code} berhasil diperbarui.",
        ]);
    }

    public function toggleStatus(
        Request $request,
        PaymentMethod $paymentMethod,
        FinanceMasterManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('finance.payment_methods.manage');
        $before = ['is_active' => $paymentMethod->is_active];
        $method = $manager->togglePaymentMethod($paymentMethod, $request->user());
        $recorder->record(
            $request,
            $method->is_active
                ? 'finance.payment_method.activated'
                : 'finance.payment_method.deactivated',
            $method,
            $before,
            ['is_active' => $method->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Metode pembayaran {$method->code} berhasil "
                .($method->is_active ? 'diaktifkan.' : 'dinonaktifkan.'),
        ]);
    }
}
