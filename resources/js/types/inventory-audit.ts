import type { Pagination } from './access';

export type InventoryAuditStatus =
    'draft' | 'in_progress' | 'submitted' | 'approved' | 'closed' | 'cancelled';

export type InventoryFindingStatus =
    | 'pending'
    | 'matched'
    | 'verified_offsite'
    | 'discrepancy'
    | 'missing'
    | 'unexpected';

export type InventoryAuditBranch = {
    id: number;
    code: string;
    name: string;
};

export type InventoryAuditMedia = {
    id: number;
    original_name: string | null;
    mime_type: string | null;
    capture_source: string;
    captured_at: string;
};

export type InventoryAuditItem = {
    id: number;
    inventory_audit_id: number;
    product_id: number;
    asset_id: number | null;
    expected_branch_id: number | null;
    tracking_type: 'serialized' | 'quantity';
    expected_status: string | null;
    expected_condition: string | null;
    expected_quantity: number;
    counted_quantity: number | null;
    finding_status: InventoryFindingStatus;
    issue_flags: string[] | null;
    observed_status: string | null;
    observed_condition: string | null;
    notes: string | null;
    resolution_action: string | null;
    resolution_notes: string | null;
    counted_at: string | null;
    resolved_at: string | null;
    product: { id: number; sku: string; name: string; tracking_type: string };
    asset: {
        id: number;
        asset_code: string;
        serial_number: string | null;
        status: string;
        condition: string;
        current_branch_id: number;
    } | null;
    expected_branch: InventoryAuditBranch | null;
    counter: { id: number; name: string } | null;
    resolver: { id: number; name: string } | null;
    media: InventoryAuditMedia[];
};

export type InventoryAudit = {
    id: number;
    company_id: number;
    branch_id: number;
    audit_number: string;
    title: string;
    status: InventoryAuditStatus;
    scheduled_at: string | null;
    notes: string | null;
    snapshot_item_count: number;
    lock_version: number;
    started_at: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    closed_at: string | null;
    cancelled_at: string | null;
    approval_notes: string | null;
    cancellation_reason: string | null;
    branch: InventoryAuditBranch;
    creator: { id: number; name: string } | null;
    starter?: { id: number; name: string } | null;
    submitter?: { id: number; name: string } | null;
    approver?: { id: number; name: string } | null;
    closer?: { id: number; name: string } | null;
    items_count?: number;
    counted_items_count?: number;
    findings_count?: number;
};

export type InventoryAuditPagination = Pagination<InventoryAudit>;
export type InventoryAuditItemPagination = Pagination<InventoryAuditItem>;

export type InventoryAuditPermissions = {
    create: boolean;
    count: boolean;
    approve: boolean;
    resolve: boolean;
    cancel: boolean;
};
