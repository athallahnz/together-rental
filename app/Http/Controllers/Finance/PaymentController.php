<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Finance\PaymentManager;
use App\Domain\Finance\PaymentVoidEligibility;
use App\Domain\Finance\RefundEligibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\VoidPaymentRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('payments.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'payment_method_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['completed', 'void'])],
            'source_context' => ['nullable', Rule::in([
                'booking',
                'rental_checkout',
                'rental_return',
                'transfer_expense',
            ])],
        ]);
        $user = $request->user();
        $branches = $user->accessibleBranches()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $branchIds = $branches->pluck('id');
        $search = trim((string) ($validated['search'] ?? ''));
        $branchId = isset($validated['branch_id'])
            ? (int) $validated['branch_id']
            : null;
        $methodId = isset($validated['payment_method_id'])
            ? (int) $validated['payment_method_id']
            : null;
        $status = (string) ($validated['status'] ?? '');
        $sourceContext = (string) ($validated['source_context'] ?? '');
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

        $scope = Payment::query()
            ->whereIn('branch_id', $branchIds)
            ->when($branchId !== null, fn (Builder $query) => $query
                ->where('branch_id', $branchId))
            ->when($methodId !== null, fn (Builder $query) => $query
                ->where('payment_method_id', $methodId))
            ->when($status !== '', fn (Builder $query) => $query
                ->where('status', $status))
            ->when($sourceContext !== '', fn (Builder $query) => $query
                ->where('source_context', $sourceContext))
            ->when($dateFrom !== '', fn (Builder $query) => $query
                ->whereDate('paid_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query
                ->whereDate('paid_at', '<=', $dateTo))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customer) => $customer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_number', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('booking', fn (Builder $booking) => $booking
                            ->where('booking_number', 'like', "%{$search}%"))
                        ->orWhereHas('rental', fn (Builder $rental) => $rental
                            ->where('rental_number', 'like', "%{$search}%"))
                        ->orWhereHas('transferExpense.transfer', fn (Builder $transfer) => $transfer
                            ->where('transfer_number', 'like', "%{$search}%"));
                });
            });

        $payments = (clone $scope)
            ->with([
                'branch:id,code,name',
                'customer:id,customer_number,name,phone',
                'booking:id,booking_number,status',
                'rental:id,rental_number,status',
                'paymentMethod:id,code,name,type',
                'transferExpense:id,branch_transfer_id,payment_id,status',
                'transferExpense.transfer:id,transfer_number,status',
            ])
            ->latest('paid_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $completed = (clone $scope)->where('status', 'completed');
        $cash = (clone $completed)->whereHas(
            'paymentMethod',
            fn (Builder $method) => $method->where('type', 'cash'),
        );
        $nonCash = (clone $completed)->whereHas(
            'paymentMethod',
            fn (Builder $method) => $method->where('type', '!=', 'cash'),
        );
        $voided = (clone $scope)->where('status', 'void');

        return Inertia::render('finance/payments/index', [
            'payments' => $payments,
            'summary' => [
                'total_count' => (clone $scope)->count(),
                'gross_amount' => (float) (clone $scope)->sum('amount'),
                'cash_amount' => $this->signedAmount($cash),
                'non_cash_amount' => $this->signedAmount($nonCash),
                'void_count' => (clone $voided)->count(),
                'void_amount' => (float) (clone $voided)->sum('amount'),
                'net_amount' => $this->signedAmount($completed),
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
                'source_context' => $sourceContext,
            ],
        ]);
    }

    public function show(
        Request $request,
        Payment $payment,
        PaymentVoidEligibility $voidEligibility,
        RefundEligibility $refundEligibility,
    ): Response {
        Gate::authorize('payments.view');
        $this->guardAccess($request, $payment);
        $payment->load([
            'branch:id,company_id,code,name',
            'customer:id,customer_number,name,phone,email',
            'booking:id,booking_number,status,total_amount',
            'rental:id,rental_number,status,total_amount,balance_due',
            'paymentMethod:id,code,name,type,requires_reference',
            'financialCategory:id,code,name,type',
            'cashSession:id,cash_register_id,status,opened_at,closed_at',
            'cashSession.register:id,branch_id,code,name',
            'receiver:id,name',
            'voider:id,name',
            'cashTransactions' => fn ($query) => $query
                ->orderBy('occurred_at')
                ->orderBy('id'),
            'refunds' => fn ($query) => $query
                ->with([
                    'paymentMethod:id,code,name,type',
                    'requester:id,name',
                    'approver:id,name',
                    'processor:id,name',
                ])
                ->latest('created_at')
                ->latest('id'),
            'transferExpense:id,branch_transfer_id,payment_id,status,expense_type,vendor_name',
            'transferExpense.transfer:id,transfer_number,status',
        ]);
        $eligibility = $voidEligibility->evaluate($payment, $request->user());
        $refund = $refundEligibility->forPayment($payment, $request->user());

        $activities = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.actor_id')
            ->where('activity_logs.subject_type', Payment::class)
            ->where('activity_logs.subject_id', $payment->id)
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

        return Inertia::render('finance/payments/show', [
            'payment' => $payment,
            'activities' => $activities,
            'voidEligibility' => $eligibility,
            'refundEligibility' => $refund,
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'requires_reference']),
            'permissions' => [
                'void' => $request->user()->can('payments.void')
                    && $eligibility['allowed'],
                'requestRefund' => $request->user()->can('refunds.request')
                    && $refund['allowed'],
            ],
        ]);
    }

    public function void(
        VoidPaymentRequest $request,
        Payment $payment,
        PaymentManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $payment);
        $alreadyVoided = $payment->status === 'void';
        $before = $payment->only(['status', 'amount', 'voided_at']);
        $voided = $manager->void(
            $payment,
            $request->string('reason')->toString(),
            $request->user(),
        );

        if (! $alreadyVoided) {
            $recorder->record(
                $request,
                'payment.voided',
                $voided,
                $before,
                $voided->only(['status', 'amount', 'voided_at', 'void_reason']),
                $voided->branch_id,
            );
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Payment {$voided->payment_number} berhasil di-void tanpa menghapus histori.",
        ]);
    }

    /** @param Builder<Payment> $query */
    private function signedAmount(Builder $query): float
    {
        return (float) $query
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as signed_total")
            ->value('signed_total');
    }

    private function guardAccess(Request $request, Payment $payment): void
    {
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($payment->branch_id)->exists(),
            404,
        );
    }
}
