import type { AccessBranch, Pagination } from './access';

export type AnalyticsCategory = {
    id: number;
    code: string;
    name: string;
};

export type AnalyticsConfidence = 'high' | 'medium' | 'low';

export type AssetDataQualityIssue = {
    code:
        | 'missing_purchase_price'
        | 'suspicious_purchase_price'
        | 'invalid_intervals'
        | 'stale_active_rentals';
    label: string;
    description: string;
    severity: 'warning' | 'danger';
    count: number;
};

export type AssetDataQuality = {
    score: number;
    confidence: AnalyticsConfidence;
    confidence_label: string;
    tone: 'success' | 'warning' | 'danger';
    has_blocker: boolean;
    investment_blocked: boolean;
    usage_blocked: boolean;
    invalid_interval_ratio_percent: number;
    stale_active_ratio_percent: number;
    issue_count: number;
    issues: AssetDataQualityIssue[];
};

export type AssetRecommendation = {
    code:
        | 'decision_deferred'
        | 'service_review'
        | 'add_capacity'
        | 'review_disposal'
        | 'promote'
        | 'profitable'
        | 'monitor';
    label: string;
    tone: 'success' | 'warning' | 'danger' | 'neutral';
    description: string;
    confidence: AnalyticsConfidence;
    confidence_label: string;
};

export type AssetAnalyticsRow = {
    id: number;
    asset_code: string;
    serial_number: string | null;
    product: {
        id: number;
        sku: string;
        name: string;
        brand: string | null;
        model: string | null;
    };
    category: {
        id: number;
        name: string;
    } | null;
    branch: AccessBranch;
    status: string;
    condition: string;
    is_active: boolean;
    purchase_date: string | null;
    purchase_price: number;
    replacement_value: number;
    max_rental_rate: number;
    purchase_price_suspicious: boolean;
    lifetime_revenue: number;
    period_revenue: number;
    open_lifetime_revenue: number;
    open_period_revenue: number;
    maintenance_cost: number;
    period_maintenance_cost: number;
    net_contribution: number;
    net_profit: number;
    roi_percent: number | null;
    bep_progress_percent: number | null;
    remaining_to_bep: number;
    utilization_percent: number;
    rental_count: number;
    realized_rental_count: number;
    open_rental_count: number;
    invalid_interval_count: number;
    stale_active_rental_count: number;
    rented_hours: number;
    available_hours: number;
    monthly_net_run_rate: number;
    estimated_bep_months: number | null;
    data_quality: AssetDataQuality;
    recommendation: AssetRecommendation;
};

export type AssetAnalyticsSummary = {
    asset_count: number;
    active_asset_count: number;
    priced_asset_count: number;
    investment_coverage_percent: number;
    investment_is_complete: boolean;
    total_investment: number;
    lifetime_revenue: number;
    period_revenue: number;
    open_lifetime_revenue: number;
    open_period_revenue: number;
    maintenance_cost: number;
    period_maintenance_cost: number;
    net_contribution: number;
    net_profit: number;
    roi_percent: number | null;
    bep_progress_percent: number | null;
    bep_asset_count: number;
    not_bep_asset_count: number;
    utilization_percent: number;
    missing_purchase_price_count: number;
    suspicious_purchase_price_count: number;
    invalid_interval_count: number;
    stale_active_rental_count: number;
    maintenance_record_count: number;
    completed_maintenance_count: number;
    data_quality_issue_asset_count: number;
    data_quality_blocker_asset_count: number;
    high_quality_asset_count: number;
    business_action_count: number;
    high_confidence_action_count: number;
};

export type AssetAnalyticsTrendPoint = {
    key: string;
    label: string;
    revenue: number;
    maintenance: number;
    net: number;
};

export type BranchAssetPerformance = {
    branch: AccessBranch;
    asset_count: number;
    active_asset_count: number;
    investment: number;
    revenue: number;
    open_revenue: number;
    maintenance_cost: number;
    net_contribution: number;
    bep_progress_percent: number | null;
    utilization_percent: number;
};

export type AssetAnalyticsFilters = {
    from: string;
    to: string;
    branch_id: number | null;
    category_id: number | null;
    status: string;
    condition: string;
    search: string;
};

export type AssetAnalyticsInsights = {
    top_revenue: AssetAnalyticsRow | null;
    top_utilization: AssetAnalyticsRow | null;
    highest_maintenance: AssetAnalyticsRow | null;
    zero_revenue_count: number;
    missing_purchase_price_count: number;
    suspicious_purchase_price_count: number;
    invalid_interval_count: number;
    stale_active_rental_count: number;
    maintenance_record_count: number;
    data_quality_issue_asset_count: number;
    data_quality_blocker_asset_count: number;
    high_quality_asset_count: number;
    business_action_count: number;
    high_confidence_action_count: number;
    add_capacity_count: number;
    service_review_count: number;
    review_disposal_count: number;
    promote_count: number;
    healthy_asset_count: number;
    monitor_asset_count: number;
    deferred_decision_count: number;
};

export type AssetAnalyticsPageProps = {
    summary: AssetAnalyticsSummary;
    assets: Pagination<AssetAnalyticsRow>;
    trend: AssetAnalyticsTrendPoint[];
    branchPerformance: BranchAssetPerformance[];
    insights: AssetAnalyticsInsights;
    filters: AssetAnalyticsFilters;
    branches: AccessBranch[];
    categories: AnalyticsCategory[];
    permissions: {
        export: boolean;
    };
    methodology: {
        revenue: string;
        roi: string;
        bep: string;
        utilization: string;
        recommendation: string;
    };
};
