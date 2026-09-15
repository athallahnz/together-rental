<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Finance\OperationalExpenseManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PayOperationalExpenseRequest;
use App\Http\Requests\Finance\SaveOperationalExpenseRequest;
use App\Http\Requests\Finance\VoidOperationalExpenseRequest;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\CashTransaction;
use App\Models\FinancialCategory;
use App\Models\OperationalExpense;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('expenses.view');
        $user = $request->user();
        $branches = $this->branches($request);
        $branchIds = $branches->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values();

        $branchId = $request->integer('branch_id') ?: null;
        if ($branchId !== null) {
            abort_unless($branchIds->contains($branchId), 403);
        }
        $search = trim($request->string('search')->toString());
        $status = trim($request->string('status')->toString());
        $categoryId = $request->integer('financial_category_id') ?: null;
        $dateFrom = trim($request->string('date_from')->toString());
        $dateTo = trim($request->string('date_to')->toString());

        $scope = OperationalExpense::query()
            ->whereIn('branch_id', $branchIds)
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($categoryId !== null, fn (Builder $query) => $query->where('financial_category_id', $categoryId))
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('incurred_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('incurred_at', '<=', $dateTo))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('expense_number', 'like', "%{$search}%")
                        ->orWhere('vendor_name', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            });

        $expenses = (clone $scope)
            ->with([
                'branch:id,code,name',
                'financialCategory:id,code,name,type',
                'paymentMethod:id,code,name,type',
                'cashSession:id,cash_register_id,status',
                'cashSession.register:id,branch_id,code,name',
                'payment:id,payment_number,status,amount,paid_at,direction,source_context',
                'creator:id,name',
                'payer:id,name',
                'voider:id,name',
            ])
            ->latest('incurred_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $cashScope = CashTransaction::query()
            ->whereHas('session.register', fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->when($branchId !== null, fn (Builder $query) => $query->whereHas(
                'session.register',
                fn (Builder $register) => $register->where('branch_id', $branchId),
            ))
            ->when($categoryId !== null, fn (Builder $query) => $query->where('financial_category_id', $categoryId))
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('occurred_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('occurred_at', '<=', $dateTo));

        $cashTransactions = (clone $cashScope)
            ->with([
                'session:id,cash_register_id,status,opened_at,closed_at',
                'session.register:id,branch_id,code,name',
                'session.register.branch:id,code,name',
                'payment:id,payment_number,status,source_context',
                'refund:id,refund_number,status',
                'creator:id,name',
            ])
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(20, ['*'], 'cash_page')
            ->withQueryString();

        $summary = [
            'recorded_count' => (clone $scope)->where('status', 'recorded')->count(),
            'recorded_amount' => (float) (clone $scope)->where('status', 'recorded')->sum('amount'),
            'paid_count' => (clone $scope)->where('status', 'paid')->count(),
            'paid_amount' => (float) (clone $scope)->where('status', 'paid')->sum('amount'),
            'void_count' => (clone $scope)->where('status', 'void')->count(),
            'cash_in' => (float) (clone $cashScope)->where('direction', 'in')->sum('amount'),
            'cash_out' => (float) (clone $cashScope)->where('direction', 'out')->sum('amount'),
        ];

        $categories = FinancialCategory::query()
            ->where('company_id', $user->company_id)
            ->where('type', 'expense')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'is_active']);

        return Inertia::render('finance/operational-expenses/index', [
            'expenses' => $expenses,
            'cashTransactions' => $cashTransactions,
            'summary' => $summary,
            'branches' => $branches,
            'categories' => $categories,
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'requires_reference']),
            'openCashSessions' => CashSession::query()
                ->where('status', 'open')
                ->whereHas('register', fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
                ->with(['register:id,branch_id,code,name', 'register.branch:id,code,name'])
                ->orderByDesc('opened_at')
                ->get(['id', 'cash_register_id', 'status', 'opened_at', 'opening_balance']),
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
                'status' => $status,
                'financial_category_id' => $categoryId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'permissions' => [
                'manage' => $user->can('expenses.manage'),
                'pay' => $user->can('expenses.pay'),
                'void' => $user->can('expenses.void'),
            ],
            'defaultBranchId' => $user->hasCompanyScopedRole()
                ? ($branchId ?? $user->current_branch_id ?? $branches->first()?->id)
                : $user->current_branch_id,
        ]);
    }

    public function show(Request $request, OperationalExpense $expense): Response
    {
        Gate::authorize('expenses.view');
        $this->guardAccess($request, $expense);
        $expense->load([
            'branch:id,code,name',
            'financialCategory:id,code,name,type',
            'paymentMethod:id,code,name,type,requires_reference',
            'cashSession:id,cash_register_id,status,opened_at,closed_at',
            'cashSession.register:id,branch_id,code,name',
            'payment:id,payment_number,status,amount,paid_at,direction,source_context,voided_at,void_reason',
            'creator:id,name', 'updater:id,name', 'payer:id,name', 'voider:id,name',
        ]);
        $activities = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.actor_id')
            ->where('activity_logs.subject_type', OperationalExpense::class)
            ->where('activity_logs.subject_id', $expense->id)
            ->orderByDesc('activity_logs.created_at')
            ->get([
                'activity_logs.id', 'activity_logs.event', 'activity_logs.description',
                'activity_logs.old_values', 'activity_logs.new_values',
                'activity_logs.created_at', 'users.name as actor_name',
            ]);

        $user = $request->user();
        $branchIds = $user->accessibleBranches()->pluck('id');

        return Inertia::render('finance/operational-expenses/show', [
            'expense' => $expense,
            'hasProof' => is_string($expense->proof_path) && $expense->proof_path !== '',
            'activities' => $activities,
            'categories' => FinancialCategory::query()
                ->where('company_id', $user->company_id)
                ->where('type', 'expense')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'is_active']),
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'requires_reference']),
            'openCashSessions' => CashSession::query()
                ->where('status', 'open')
                ->whereHas('register', fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
                ->with(['register:id,branch_id,code,name', 'register.branch:id,code,name'])
                ->orderByDesc('opened_at')
                ->get(['id', 'cash_register_id', 'status', 'opened_at', 'opening_balance']),
            'permissions' => [
                'manage' => $user->can('expenses.manage'),
                'pay' => $user->can('expenses.pay'),
                'void' => $user->can('expenses.void'),
            ],
        ]);
    }

    public function store(
        SaveOperationalExpenseRequest $request,
        OperationalExpenseManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $expense = $manager->create($request->validated(), $request->user());
        $recorder->record($request, 'operational-expense.created', $expense, null, $this->audit($expense), $expense->branch_id);

        return redirect()->route('finance.expenses.index')->with('toast', [
            'type' => 'success',
            'message' => "Pengeluaran {$expense->expense_number} berhasil dicatat.",
        ]);
    }

    public function update(
        SaveOperationalExpenseRequest $request,
        OperationalExpense $expense,
        OperationalExpenseManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $expense);
        $before = $this->audit($expense);
        $updated = $manager->update($expense, $request->validated(), $request->user());
        $recorder->record($request, 'operational-expense.updated', $updated, $before, $this->audit($updated), $updated->branch_id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Pengeluaran {$updated->expense_number} berhasil diperbarui.",
        ]);
    }

    public function pay(
        PayOperationalExpenseRequest $request,
        OperationalExpense $expense,
        OperationalExpenseManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $expense);
        $before = $this->audit($expense);
        $paid = $manager->pay($expense, $request->validated(), $request->user());
        $recorder->record($request, 'operational-expense.paid', $paid, $before, $this->audit($paid), $paid->branch_id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Pengeluaran {$paid->expense_number} berhasil dibayar.",
        ]);
    }

    public function void(
        VoidOperationalExpenseRequest $request,
        OperationalExpense $expense,
        OperationalExpenseManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $expense);
        $before = $this->audit($expense);
        $voided = $manager->void($expense, (string) $request->validated('reason'), $request->user());
        $recorder->record($request, 'operational-expense.voided', $voided, $before, $this->audit($voided), $voided->branch_id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Pengeluaran {$voided->expense_number} berhasil di-void tanpa menghapus histori.",
        ]);
    }

    public function proof(Request $request, OperationalExpense $expense): StreamedResponse
    {
        Gate::authorize('expenses.view');
        $this->guardAccess($request, $expense);
        abort_unless(
            is_string($expense->proof_path)
                && $expense->proof_path !== ''
                && Storage::disk('local')->exists($expense->proof_path),
            404,
        );

        return Storage::disk('local')->download(
            $expense->proof_path,
            $expense->proof_original_name ?? basename($expense->proof_path),
        );
    }

    private function guardAccess(Request $request, OperationalExpense $expense): void
    {
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($expense->branch_id)->exists(),
            404,
        );
        if (! $request->user()->hasCompanyScopedRole()) {
            abort_unless($request->user()->current_branch_id === $expense->branch_id, 404);
        }
    }

    /** @return Collection<int, Branch> */
    private function branches(Request $request): Collection
    {
        $user = $request->user();

        return Branch::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->when(
                ! $user->hasCompanyScopedRole(),
                fn (Builder $query) => $query->whereKey($user->current_branch_id ?? 0),
            )
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);
    }

    /** @return array<string, mixed> */
    private function audit(OperationalExpense $expense): array
    {
        return $expense->only([
            'branch_id', 'financial_category_id', 'payment_method_id', 'cash_session_id',
            'payment_id', 'expense_number', 'status', 'amount', 'incurred_at',
            'vendor_name', 'external_reference', 'notes', 'paid_at', 'voided_at',
            'void_reason',
        ]);
    }
}
