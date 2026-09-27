<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Finance\CashSessionManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CloseCashSessionRequest;
use App\Http\Requests\Finance\OpenCashSessionRequest;
use App\Models\CashRegister;
use App\Models\CashSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CashSessionController extends Controller
{
    public function index(Request $request, CashRegister $cashRegister): Response
    {
        Gate::authorize('cash.view');
        $this->guardAccess($request, $cashRegister);

        $sessions = CashSession::query()
            ->where('cash_register_id', $cashRegister->id)
            ->where('status', 'closed')
            ->with([
                'opener:id,name',
                'closer:id,name',
            ])
            ->withSum([
                'transactions as incoming_total' => fn ($query) => $query->where('direction', 'in'),
            ], 'amount')
            ->withSum([
                'transactions as outgoing_total' => fn ($query) => $query->where('direction', 'out'),
            ], 'amount')
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (CashSession $session): array {
                $incoming = (float) ($session->getAttribute('incoming_total') ?? 0);
                $outgoing = (float) ($session->getAttribute('outgoing_total') ?? 0);

                return [
                    'id' => $session->id,
                    'status' => $session->status,
                    'opened_at' => $session->opened_at,
                    'closed_at' => $session->closed_at,
                    'opening_balance' => $session->opening_balance,
                    'incoming_total' => $incoming,
                    'outgoing_total' => $outgoing,
                    'expected_closing_balance' => $session->expected_closing_balance,
                    'actual_closing_balance' => $session->actual_closing_balance,
                    'difference_amount' => $session->difference_amount,
                    'opening_notes' => $session->opening_notes,
                    'closing_notes' => $session->closing_notes,
                    'opener' => $session->opener?->only(['id', 'name']),
                    'closer' => $session->closer?->only(['id', 'name']),
                ];
            });

        $cashRegister->load('branch:id,company_id,code,name,is_active');

        return Inertia::render('finance/cash-sessions/index', [
            'cashRegister' => [
                'id' => $cashRegister->id,
                'code' => $cashRegister->code,
                'name' => $cashRegister->name,
                'is_active' => $cashRegister->is_active,
                'branch' => $cashRegister->branch?->only(['id', 'code', 'name']),
            ],
            'sessions' => $sessions,
        ]);
    }

    public function show(Request $request, CashSession $cashSession): Response
    {
        Gate::authorize('cash.view');

        $cashSession->load([
            'register.branch:id,company_id,code,name,is_active',
            'opener:id,name',
            'closer:id,name',
        ]);
        $this->guardAccess($request, $cashSession->register);

        $transactions = $cashSession->transactions()
            ->with('creator:id,name')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
        $incoming = (float) $transactions->where('direction', 'in')->sum('amount');
        $outgoing = (float) $transactions->where('direction', 'out')->sum('amount');
        $ledgerExpected = (float) $cashSession->opening_balance + $incoming - $outgoing;

        return Inertia::render('finance/cash-sessions/show', [
            'cashRegister' => [
                'id' => $cashSession->register->id,
                'code' => $cashSession->register->code,
                'name' => $cashSession->register->name,
                'branch' => $cashSession->register->branch?->only(['id', 'code', 'name']),
            ],
            'session' => [
                'id' => $cashSession->id,
                'status' => $cashSession->status,
                'opened_at' => $cashSession->opened_at,
                'closed_at' => $cashSession->closed_at,
                'opening_balance' => $cashSession->opening_balance,
                'incoming_total' => $incoming,
                'outgoing_total' => $outgoing,
                'ledger_expected_balance' => $ledgerExpected,
                'expected_closing_balance' => $cashSession->expected_closing_balance,
                'actual_closing_balance' => $cashSession->actual_closing_balance,
                'difference_amount' => $cashSession->difference_amount,
                'opening_notes' => $cashSession->opening_notes,
                'closing_notes' => $cashSession->closing_notes,
                'opener' => $cashSession->opener?->only(['id', 'name']),
                'closer' => $cashSession->closer?->only(['id', 'name']),
            ],
            'transactions' => $transactions->map(fn ($transaction): array => [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'direction' => $transaction->direction,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'occurred_at' => $transaction->occurred_at,
                'description' => $transaction->description,
                'payment_id' => $transaction->payment_id,
                'refund_id' => $transaction->refund_id,
                'creator' => $transaction->creator?->only(['id', 'name']),
            ])->values(),
        ]);
    }

    public function store(
        OpenCashSessionRequest $request,
        CashRegister $cashRegister,
        CashSessionManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $session = $manager->open($cashRegister, $request->validated(), $request->user());
        $recorder->record($request, 'cash.session.opened', $session, null, [
            'cash_register_id' => $session->cash_register_id,
            'opening_balance' => $session->opening_balance,
        ], $cashRegister->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => __('uat035b_stage5.flash.cash_opened')]);
    }

    public function close(
        CloseCashSessionRequest $request,
        CashSession $cashSession,
        CashSessionManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $before = $cashSession->only(['status', 'expected_closing_balance']);
        $session = $manager->close($cashSession, $request->validated(), $request->user());
        $recorder->record($request, 'cash.session.closed', $session, $before, [
            'status' => $session->status,
            'expected_closing_balance' => $session->expected_closing_balance,
            'actual_closing_balance' => $session->actual_closing_balance,
            'difference_amount' => $session->difference_amount,
        ], $session->register->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => __('uat035b_stage5.flash.cash_closed')]);
    }

    private function guardAccess(Request $request, CashRegister $cashRegister): void
    {
        $user = $request->user();

        abort_unless(
            $user->accessibleBranches()->whereKey($cashRegister->branch_id)->exists(),
            404,
        );

        if (! $user->hasCompanyScopedRole()) {
            abort_unless($user->current_branch_id === $cashRegister->branch_id, 404);
        }
    }
}
