import type { Pagination } from './access';

export type PaymentStatus = 'completed' | 'void';

export type RefundStatus =
    'requested' | 'approved' | 'rejected' | 'paid' | 'cancelled';

export type PaymentSourceContext =
    | 'booking'
    | 'rental_checkout'
    | 'rental_return'
    | 'rental_extension'
    | 'transfer_expense';

export type FinanceBranch = {
    id: number;
    company_id?: number;
    code: string;
    name: string;
};

export type FinancePaymentMethod = {
    id: number;
    code: string;
    name: string;
    type: string;
    requires_reference?: boolean;
    is_active?: boolean;
};

export type PaymentCenterPayment = {
    id: number;
    branch_id: number;
    customer_id: number | null;
    booking_id: number | null;
    rental_id: number | null;
    payment_method_id: number;
    financial_category_id: number | null;
    cash_session_id: number | null;
    payment_number: string;
    direction: 'in' | 'out';
    type: string;
    source_context: PaymentSourceContext | null;
    status: PaymentStatus;
    amount: string;
    paid_at: string;
    external_reference: string | null;
    notes: string | null;
    voided_at: string | null;
    void_reason: string | null;
    branch: FinanceBranch;
    customer: {
        id: number;
        customer_number: string;
        name: string;
        phone: string | null;
        email?: string | null;
    } | null;
    booking: {
        id: number;
        booking_number: string;
        status: string;
        total_amount?: string;
    } | null;
    rental: {
        id: number;
        rental_number: string;
        status: string;
        total_amount?: string;
        balance_due?: string;
    } | null;
    rental_extension: {
        id: number;
        rental_id: number;
        extension_number: string;
        status: string;
        extended_due_at: string;
    } | null;
    payment_method: FinancePaymentMethod;
    transfer_expense: {
        id: number;
        status: string;
        expense_type?: string;
        vendor_name?: string | null;
        transfer: {
            id: number;
            transfer_number: string;
            status: string;
        } | null;
    } | null;
};

export type PaymentPagination = Pagination<PaymentCenterPayment>;

export type PaymentCenterFilters = {
    search: string;
    branch_id: number | null;
    date_from: string;
    date_to: string;
    payment_method_id: number | null;
    status: string;
    source_context: string;
};

export type PaymentCenterSummary = {
    total_count: number;
    gross_amount: number;
    cash_amount: number;
    non_cash_amount: number;
    void_count: number;
    void_amount: number;
    net_amount: number;
};

export type RefundCenterRefund = {
    id: number;
    branch_id: number;
    payment_id: number;
    booking_id: number | null;
    rental_id: number | null;
    payment_method_id: number;
    cash_session_id: number | null;
    refund_number: string;
    refund_type: 'full' | 'partial';
    amount: string;
    status: RefundStatus;
    reason: string;
    notes: string | null;
    external_reference: string | null;
    proof_path: string | null;
    created_at: string;
    approved_at: string | null;
    rejected_at: string | null;
    processed_at: string | null;
    cancelled_at: string | null;
    branch: FinanceBranch;
    payment: {
        id: number;
        payment_number: string;
        amount: string;
        status: PaymentStatus;
        paid_at: string;
        customer: {
            id: number;
            customer_number: string;
            name: string;
            phone: string | null;
            email?: string | null;
        } | null;
    };
    payment_method: FinancePaymentMethod;
    requester: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
    processor: { id: number; name: string } | null;
};

export type RefundPagination = Pagination<RefundCenterRefund>;

export type RefundCenterFilters = {
    search: string;
    branch_id: number | null;
    date_from: string;
    date_to: string;
    payment_method_id: number | null;
    status: string;
};

export type RefundCenterSummary = {
    total_count: number;
    requested_count: number;
    approved_count: number;
    outstanding_amount: number;
    paid_count: number;
    paid_amount: number;
};

export type FinanceDashboardFilters = {
    from: string;
    to: string;
    branch_id: number | null;
};

export type FinanceDashboardSummary = {
    gross_collections: number;
    rental_collections: number;
    deposit_collections: number;
    operating_outflows: number;
    paid_refunds: number;
    net_cash_flow: number;
    completed_payment_count: number;
    inbound_payment_count: number;
    average_collection: number;
    void_count: number;
    void_amount: number;
    paid_refund_count: number;
    outstanding_refund_count: number;
    outstanding_refund_amount: number;
    receivable_count: number;
    receivable_amount: number;
    deposit_held: number;
    open_cash_session_count: number;
};

export type FinanceDashboardComparison = {
    from: string;
    to: string;
    gross_collections_percent: number | null;
    rental_collections_percent: number | null;
    paid_refunds_percent: number | null;
    net_cash_flow_percent: number | null;
};

export type FinanceDashboardTrendPoint = {
    key: string;
    label: string;
    collections: number;
    expenses: number;
    refunds: number;
    net: number;
};

export type FinanceDashboardPaymentMethod = {
    id: number;
    code: string;
    name: string;
    type: string;
    transaction_count: number;
    collections: number;
    refunds: number;
    net: number;
    share_percent: number;
};

export type FinanceDashboardSourceContext = {
    context: string;
    label: string;
    transaction_count: number;
    inflow: number;
    outflow: number;
    net: number;
};

export type FinanceDashboardBranchPerformance = {
    id: number;
    code: string;
    name: string;
    transaction_count: number;
    collections: number;
    outflows: number;
    refunds: number;
    net: number;
    receivables: number;
};

export type FinanceDashboardActivity = {
    kind: 'payment' | 'refund';
    id: number;
    number: string;
    branch_code: string;
    customer_name: string | null;
    status: string;
    amount: number;
    direction: 'in' | 'out';
    occurred_at: string;
    href: string;
};

export type FinanceDashboardAttention = {
    requested_refunds: { count: number; amount: number };
    approved_refunds: { count: number; amount: number };
    overdue_receivables: { count: number; amount: number };
    cash_differences: { count: number; amount: number };
    ledger_integrity: {
        cash_payment_without_ledger: number;
        cash_refund_without_ledger: number;
    };
};

export type FinanceDashboardPageProps = {
    summary: FinanceDashboardSummary;
    comparison: FinanceDashboardComparison;
    trend: FinanceDashboardTrendPoint[];
    paymentMethods: FinanceDashboardPaymentMethod[];
    sourceContexts: FinanceDashboardSourceContext[];
    branchPerformance: FinanceDashboardBranchPerformance[];
    recentActivity: FinanceDashboardActivity[];
    attention: FinanceDashboardAttention;
    filters: FinanceDashboardFilters;
    branches: FinanceBranch[];
    generatedAt: string;
    methodology: {
        gross_collections: string;
        net_cash_flow: string;
        deposit_held: string;
        receivables: string;
        comparison: string;
    };
};
