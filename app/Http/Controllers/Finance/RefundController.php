<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Finance\RefundEligibility;
use App\Domain\Finance\RefundManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ApproveRefundRequest;
use App\Http\Requests\Finance\CancelRefundRequest;
use App\Http\Requests\Finance\ProcessRefundRequest;
use App\Http\Requests\Finance\RejectRefundRequest;
use App\Http\Requests\Finance\RequestRefundRequest;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RefundController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('refunds.view');
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'payment_method_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([
                'requested', 'approved', 'rejected', 'paid', 'cancelled',
            ])],
        ]);
        $user = $request->user();
        $branches = $user->accessibleBranches()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $branchIds = $branches->pluck('id');
        $search = trim((string) ($validated['search'] ?? ''));
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $methodId = isset($validated['payment_method_id'])
            ? (int) $validated['payment_method_id']
            : null;
        $status = (string) ($validated['status'] ?? '');
        $dateFrom = (string) ($validated['date_from'] ?? '');
        $dateTo = (string) ($validated['date_to'] ?? '');

        if ($dateFrom !== '' && $dateTo !== '' && $dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
            ]);
        }
        if ($branchId !== null) {
            abort_unless($branchIds->contains($branchId), 403);
        }
        if (
            $methodId !== null
            && ! PaymentMethod::query()
                ->where('company_id', $user->company_id)
                ->whereKey($methodId)
                ->exists()
        ) {
            abort(404);
        }

        $scope = Refund::query()
            ->whereIn('branch_id', $branchIds)
            ->when($branchId !== null, fn (Builder $query) => $query
                ->where('branch_id', $branchId))
            ->when($methodId !== null, fn (Builder $query) => $query
                ->where('payment_method_id', $methodId))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($dateFrom !== '', fn (Builder $query) => $query
                ->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query
                ->whereDate('created_at', '<=', $dateTo))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->where('refund_number', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhereHas('payment', fn (Builder $payment) => $payment
                            ->where('payment_number', 'like', "%{$search}%"))
                        ->orWhereHas('payment.customer', fn (Builder $customer) => $customer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_number', 'like', "%{$search}%"));
                });
            });

        $refunds = (clone $scope)
            ->with([
                'branch:id,code,name',
                'payment:id,customer_id,payment_number,amount,status,paid_at',
                'payment.customer:id,customer_number,name,phone',
                'paymentMethod:id,code,name,type',
                'requester:id,name',
                'approver:id,name',
                'processor:id,name',
            ])
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('finance/refunds/index', [
            'refunds' => $refunds,
            'summary' => [
                'total_count' => (clone $scope)->count(),
                'requested_count' => (clone $scope)->where('status', 'requested')->count(),
                'approved_count' => (clone $scope)->where('status', 'approved')->count(),
                'outstanding_amount' => (float) (clone $scope)
                    ->whereIn('status', ['requested', 'approved'])
                    ->sum('amount'),
                'paid_count' => (clone $scope)->where('status', 'paid')->count(),
                'paid_amount' => (float) (clone $scope)->where('status', 'paid')->sum('amount'),
            ],
            'branches' => $branches,
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $user->company_id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'is_active']),
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'payment_method_id' => $methodId,
                'status' => $status,
            ],
        ]);
    }

    public function show(
        Request $request,
        Refund $refund,
        RefundEligibility $eligibility,
    ): Response {
        Gate::authorize('refunds.view');
        $this->guardAccess($request, $refund);
        $refund->load([
            'branch:id,company_id,code,name',
            'payment:id,branch_id,customer_id,booking_id,rental_id,payment_method_id,payment_number,direction,type,source_context,status,amount,paid_at,external_reference',
            'payment.customer:id,customer_number,name,phone,email',
            'payment.paymentMethod:id,code,name,type',
            'booking:id,booking_number,status,total_amount',
            'rental:id,rental_number,status,total_amount,balance_due',
            'paymentMethod:id,code,name,type,requires_reference,is_active',
            'cashSession:id,cash_register_id,status,opened_at,closed_at',
            'cashSession.register:id,branch_id,code,name',
            'requester:id,name',
            'approver:id,name',
            'rejecter:id,name',
            'processor:id,name',
            'canceller:id,name',
            'cashTransactions' => fn ($query) => $query
                ->with('creator:id,name')
                ->orderBy('occurred_at')
                ->orderBy('id'),
        ]);

        $activities = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.actor_id')
            ->where('activity_logs.subject_type', Refund::class)
            ->where('activity_logs.subject_id', $refund->id)
            ->orderByDesc('activity_logs.created_at')
            ->get([
                'activity_logs.id',
                'activity_logs.event',
                'activity_logs.description',
                'activity_logs.old_values',
                'activity_logs.new_values',
                'activity_logs.created_at',
                'users.name as actor_name',
            ]);

        $openCashSessions = CashSession::query()
            ->where('status', 'open')
            ->whereHas('register', fn (Builder $query) => $query
                ->where('branch_id', $refund->branch_id)
                ->where('is_active', true))
            ->with('register:id,branch_id,code,name')
            ->orderBy('opened_at')
            ->get(['id', 'cash_register_id', 'opened_at', 'opening_balance']);

        return Inertia::render('finance/refunds/show', [
            'refund' => $refund,
            'activities' => $activities,
            'openCashSessions' => $openCashSessions,
            'permissions' => $eligibility->actions($refund, $request->user()),
        ]);
    }

    public function store(
        RequestRefundRequest $request,
        Payment $payment,
        RefundManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardPaymentAccess($request, $payment);
        $refund = $manager->request($payment, $request->validated(), $request->user());
        $recorder->record($request, 'refund.requested', $refund, null, [
            'status' => $refund->status,
            'payment_id' => $refund->payment_id,
            'amount' => $refund->amount,
            'refund_type' => $refund->refund_type,
        ], $refund->branch_id);

        return redirect()->route('finance.refunds.show', $refund)->with('toast', [
            'type' => 'success',
            'message' => "Refund {$refund->refund_number} berhasil diajukan.",
        ]);
    }

    public function approve(
        ApproveRefundRequest $request,
        Refund $refund,
        RefundManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $refund);
        $before = $refund->only(['status']);
        $updated = $manager->approve($refund, $request->user());
        $this->recordStatus($recorder, $request, 'refund.approved', $updated, $before);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Refund {$updated->refund_number} berhasil disetujui.",
        ]);
    }

    public function reject(
        RejectRefundRequest $request,
        Refund $refund,
        RefundManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $refund);
        $before = $refund->only(['status']);
        $updated = $manager->reject(
            $refund,
            $request->string('reason')->toString(),
            $request->user(),
        );
        $this->recordStatus($recorder, $request, 'refund.rejected', $updated, $before);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Refund {$updated->refund_number} ditolak.",
        ]);
    }

    public function process(
        ProcessRefundRequest $request,
        Refund $refund,
        RefundManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $refund);
        $proof = $request->file('proof');
        if ($proof === null) {
            throw ValidationException::withMessages(['proof' => 'Bukti refund wajib diunggah.']);
        }
        $path = $proof->store("finance/refunds/{$refund->refund_number}", 'local');
        if (! is_string($path)) {
            throw ValidationException::withMessages(['proof' => 'Bukti refund gagal disimpan.']);
        }

        try {
            $data = [
                ...$request->validated(),
                'proof_path' => $path,
                'proof_original_name' => $proof->getClientOriginalName(),
                'proof_mime_type' => $proof->getMimeType(),
                'proof_size' => $proof->getSize(),
            ];
            $before = $refund->only(['status']);
            $updated = $manager->process($refund, $data, $request->user());
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        $this->recordStatus($recorder, $request, 'refund.paid', $updated, $before);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Refund {$updated->refund_number} berhasil dibayarkan.",
        ]);
    }

    public function cancel(
        CancelRefundRequest $request,
        Refund $refund,
        RefundManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $refund);
        $before = $refund->only(['status']);
        $updated = $manager->cancel(
            $refund,
            $request->string('reason')->toString(),
            $request->user(),
        );
        $this->recordStatus($recorder, $request, 'refund.cancelled', $updated, $before);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Refund {$updated->refund_number} dibatalkan.",
        ]);
    }

    public function proof(Request $request, Refund $refund): StreamedResponse
    {
        Gate::authorize('refunds.view');
        $this->guardAccess($request, $refund);
        abort_unless(
            is_string($refund->proof_path)
                && Storage::disk('local')->exists($refund->proof_path),
            404,
        );

        return Storage::disk('local')->download(
            $refund->proof_path,
            $refund->proof_original_name ?? basename($refund->proof_path),
        );
    }

    /** @param array<string, mixed> $before */
    private function recordStatus(
        ActivityRecorder $recorder,
        Request $request,
        string $event,
        Refund $refund,
        array $before,
    ): void {
        $recorder->record(
            $request,
            $event,
            $refund,
            $before,
            $refund->only([
                'status', 'approved_at', 'rejected_at', 'processed_at',
                'cancelled_at', 'external_reference',
            ]),
            $refund->branch_id,
        );
    }

    private function guardAccess(Request $request, Refund $refund): void
    {
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($refund->branch_id)->exists(),
            404,
        );
    }

    private function guardPaymentAccess(Request $request, Payment $payment): void
    {
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($payment->branch_id)->exists(),
            404,
        );
    }
}
