import type { AccessBranch, Pagination } from './access';

export type ReportKey =
    | 'operational'
    | 'finance'
    | 'receivables'
    | 'cash'
    | 'assets'
    | 'transfers'
    | 'inventory-audits';

export type ReportValue = string | number | null;

export type ReportColumn = {
    key: string;
    label: string;
    type:
        | 'text'
        | 'money'
        | 'number'
        | 'date'
        | 'datetime'
        | 'status'
        | 'direction';
    export_width: number;
};

export type ReportRow = {
    id: string;
    href: string;
    values: Record<string, ReportValue>;
};

export type ReportSummaryCard = {
    key: string;
    label: string;
    value: number;
    type: 'money' | 'number';
    note: string;
};

export type ReportTrendPoint = {
    key: string;
    label: string;
    rental_value: number;
    collections: number;
    outflows: number;
    net: number;
};

export type ReportBranchPerformance = {
    id: number;
    code: string;
    name: string;
    rental_count: number;
    rental_value: number;
    collections: number;
    outflows: number;
    net: number;
    receivables: number;
    maintenance_cost: number;
};

export type ReportFilters = {
    from: string;
    to: string;
    branch_id: number | null;
    report: ReportKey;
    status: string;
    payment_method_id: number | null;
    category_id: number | null;
    search: string;
};

export type ReportOption = {
    id: number;
    code: string;
    name: string;
    type?: string;
};

export type IntegratedReportingPageProps = {
    summary: ReportSummaryCard[];
    trend: ReportTrendPoint[];
    branchPerformance: ReportBranchPerformance[];
    columns: ReportColumn[];
    rows: Pagination<ReportRow>;
    statusOptions: Array<{ value: string; label: string }>;
    reportMeta: {
        key: ReportKey;
        label: string;
        description: string;
        row_count: number;
    };
    methodology: {
        cash_flow: string;
        receivables: string;
        rental_value: string;
        deposit: string;
        branch_scope: string;
    };
    filters: ReportFilters;
    branches: AccessBranch[];
    paymentMethods: ReportOption[];
    financialCategories: ReportOption[];
    permissions: { export: boolean };
    generatedAt: string;
    tabs: Array<{ key: ReportKey; label: string }>;
};
