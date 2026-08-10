import type { BranchSummary } from './auth';

export type DashboardVisibility = {
    bookings: boolean;
    rentals: boolean;
    finance: boolean;
    assets: boolean;
    customers: boolean;
};

export type DashboardOverview = {
    bookings_month: number;
    bookings_change_percent: number | null;
    booking_conversion_percent: number;
    active_rentals: number;
    overdue_rentals: number;
    due_today_rentals: number;
    receivable_amount: number;
    net_revenue_month: number;
    revenue_change_percent: number | null;
    gross_collections_month: number;
    refunds_month: number;
    asset_total: number;
    asset_available: number;
    asset_rented: number;
    asset_maintenance: number;
    asset_utilization_percent: number;
    customer_total: number;
    customer_new_this_month: number;
};

export type DashboardQuickAction = {
    key:
        | 'booking'
        | 'direct-rental'
        | 'return'
        | 'payment'
        | 'transfer'
        | 'stock-opname'
        | 'maintenance'
        | 'notification';
    title: string;
    description: string;
    href: string;
};

export type DashboardAttentionTone =
    'critical' | 'warning' | 'info' | 'neutral';

export type DashboardAttentionItem = {
    key: string;
    title: string;
    description: string;
    count: number;
    tone: DashboardAttentionTone;
    href: string;
};

export type DashboardTrendPoint = {
    date: string;
    label: string;
    bookings: number;
    rentals: number;
};

export type DashboardAssetHealth = {
    key: string;
    label: string;
    count: number;
};

export type DashboardScheduleItem = {
    id: number;
    kind: 'pickup' | 'return';
    number: string;
    customer: string;
    branch: string;
    scheduled_at: string;
    status: string;
    href: string;
};

export type DashboardBranchPerformance = {
    id: number;
    code: string;
    name: string;
    bookings: number;
    active_rentals: number;
    overdue_rentals: number;
    revenue: number;
    utilization_percent: number;
};

export type DashboardRecentActivity = {
    id: number;
    kind: 'booking' | 'rental' | 'payment';
    number: string;
    customer: string | null;
    branch: string;
    amount: number;
    occurred_at: string;
    status: string;
    href: string;
};

export type OperationalDashboardPageProps = {
    visibility: DashboardVisibility;
    overview: DashboardOverview;
    quickActions: DashboardQuickAction[];
    attention: DashboardAttentionItem[];
    trend: DashboardTrendPoint[];
    assetHealth: DashboardAssetHealth[];
    todaySchedule: DashboardScheduleItem[];
    branchPerformance: DashboardBranchPerformance[];
    recentActivity: DashboardRecentActivity[];
    branches: BranchSummary[];
    filters: {
        branch_id: number | null;
    };
    scope: {
        is_company_scope: boolean;
        allow_all_branches: boolean;
        label: string;
    };
    period: {
        from: string;
        to: string;
        label: string;
        comparison_label: string;
    };
    generatedAt: string;
};
