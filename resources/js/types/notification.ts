export type NotificationCategory =
    | 'booking'
    | 'rental'
    | 'finance'
    | 'transfer'
    | 'maintenance'
    | 'inventory'
    | 'system';

export type NotificationSeverity = 'info' | 'warning' | 'critical';

export type NotificationItem = {
    id: number;
    branch_id: number | null;
    branch: {
        id: number;
        code: string;
        name: string;
    } | null;
    rule_code: string;
    category: NotificationCategory;
    severity: NotificationSeverity;
    title: string;
    body: string;
    action_url: string | null;
    occurrences: number;
    first_triggered_at: string;
    last_triggered_at: string;
    due_at: string | null;
    read_at: string | null;
    dismissed_at: string | null;
    snoozed_until: string | null;
    resolved_at: string | null;
    email_status: string;
    metadata: Record<string, unknown> | null;
};

export type NotificationHeader = {
    unread_count: number;
    critical_count: number;
    recent: NotificationItem[];
};

export type NotificationPreference = {
    email_enabled: boolean;
    email_min_severity: NotificationSeverity;
    muted_categories: NotificationCategory[];
    quiet_hours_start: string | null;
    quiet_hours_end: string | null;
};

export type NotificationRule = {
    id: number;
    code: string;
    category: NotificationCategory;
    name: string;
    description: string;
    severity: NotificationSeverity;
    recipient_permission: string;
    is_enabled: boolean;
    lead_minutes: number;
    repeat_minutes: number;
};

export type NotificationPagination = {
    data: NotificationItem[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
