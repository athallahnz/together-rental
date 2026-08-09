<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\FinancialCategory;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinanceMasterController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('finance.masters.view');
        $user = $request->user();

        $paymentMethods = PaymentMethod::query()
            ->where('company_id', $user->company_id)
            ->withCount(['payments', 'refunds'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = FinancialCategory::query()
            ->where('company_id', $user->company_id)
            ->withCount(['payments', 'cashTransactions', 'transferExpenses'])
            ->orderByRaw("CASE type WHEN 'income' THEN 1 WHEN 'liability' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get();

        $registerQuery = CashRegister::query()
            ->whereHas('branch', fn ($query) => $query->where('company_id', $user->company_id));

        if (! $user->hasCompanyScopedRole()) {
            $registerQuery->where('branch_id', $user->current_branch_id ?? 0);
        }

        $cashRegisters = $registerQuery
            ->with([
                'branch:id,company_id,code,name,is_active',
                'openSession.opener:id,name',
                'latestSession.closer:id,name',
            ])
            ->withCount('sessions')
            ->orderBy('branch_id')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $openSessionIds = $cashRegisters
            ->pluck('openSession.id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values();
        $sessionTotals = DB::table('cash_transactions')
            ->whereIn('cash_session_id', $openSessionIds)
            ->select('cash_session_id')
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) as incoming_total")
            ->selectRaw("SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) as outgoing_total")
            ->groupBy('cash_session_id')
            ->get()
            ->keyBy('cash_session_id');

        $cashRegisters->each(function (CashRegister $register) use ($sessionTotals): void {
            $session = $register->openSession;
            if ($session === null) {
                return;
            }

            /**
             * @var object{
             *     incoming_total: float|int|string,
             *     outgoing_total: float|int|string
             * }|null $totals
             */
            $totals = $sessionTotals->get($session->id);
            $incoming = (float) ($totals->incoming_total ?? 0);
            $outgoing = (float) ($totals->outgoing_total ?? 0);
            $session->setAttribute('incoming_total', $incoming);
            $session->setAttribute('outgoing_total', $outgoing);
            $session->setAttribute(
                'expected_balance',
                (float) $session->opening_balance + $incoming - $outgoing,
            );
        });

        $branches = Branch::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->when(
                ! $user->hasCompanyScopedRole(),
                fn ($query) => $query->whereKey($user->current_branch_id ?? 0),
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('finance/master-data/index', [
            'paymentMethods' => $paymentMethods,
            'financialCategories' => $categories,
            'cashRegisters' => $cashRegisters,
            'branches' => $branches,
            'summary' => [
                'payment_methods' => $paymentMethods->count(),
                'active_payment_methods' => $paymentMethods->where('is_active', true)->count(),
                'financial_categories' => $categories->count(),
                'active_financial_categories' => $categories->where('is_active', true)->count(),
                'cash_registers' => $cashRegisters->count(),
                'active_cash_registers' => $cashRegisters->where('is_active', true)->count(),
                'open_cash_sessions' => $cashRegisters->whereNotNull('openSession')->count(),
            ],
            'permissions' => [
                'managePaymentMethods' => $user->can('finance.payment_methods.manage'),
                'manageCategories' => $user->can('finance.categories.manage'),
                'manageCashRegisters' => $user->can('finance.cash_registers.manage'),
                'manageCashSessions' => $user->can('cash.manage'),
            ],
        ]);
    }
}
