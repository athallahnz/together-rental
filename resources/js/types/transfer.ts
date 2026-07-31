import type { Pagination } from './access';

export type TransferStatus =
    | 'draft'
    | 'pending_approval'
    | 'approved'
    | 'dispatched'
    | 'receiving'
    | 'discrepancy'
    | 'completed'
    | 'rejected'
    | 'cancelled';

export type TransferBranch = {
    id: number;
    code: string;
    name: string;
};

export type TransferProduct = {
    id: number;
    sku: string;
    name: string;
    brand?: string | null;
    model?: string | null;
    tracking_type: 'serialized' | 'quantity';
};

export type TransferAsset = {
    id: number;
    product_id: number;
    current_branch_id: number;
    asset_code: string;
    serial_number: string | null;
    status: string;
    condition: string;
};

export type TransferMedia = {
    id: number;
    path: string;
    caption: string | null;
    capture_source: string;
    captured_at: string | null;
};

export type TransferInspection = {
    id: number;
    type: string;
    condition: string;
    checklist: Record<string, boolean> | null;
    notes: string | null;
    inspected_at: string;
    media: TransferMedia[];
};

export type TransferItem = {
    id: number;
    line_number: number;
    product_id: number;
    asset_id: number | null;
    quantity: number;
    received_quantity: number;
    condition_before: string | null;
    condition_after: string | null;
    receiving_result: string | null;
    discrepancy_type: string | null;
    discrepancy_notes: string | null;
    resolution_action: string | null;
    status: string;
    notes: string | null;
    product: TransferProduct;
    asset: TransferAsset | null;
    inspections: TransferInspection[];
};

export type TransferApproval = {
    id: number;
    revision_number: number;
    side: 'origin' | 'destination';
    decision: 'approved' | 'rejected';
    notes: string | null;
    decided_at: string;
    snapshot_hash: string;
    branch: TransferBranch;
    decider: { id: number; name: string } | null;
};

export type TransferExpense = {
    id: number;
    expense_branch_id: number;
    expense_type: string;
    status: string;
    estimated_amount: string;
    actual_amount: string;
    vendor_name: string | null;
    paid_at: string | null;
    external_reference: string | null;
    notes: string | null;
    expense_branch: TransferBranch;
    payment: {
        id: number;
        payment_number: string;
        status: string;
        amount: string;
        paid_at: string;
    } | null;
};

export type TransferDocument = {
    id: number;
    stage: string;
    document_type: string;
    original_name: string | null;
    mime_type: string | null;
    file_size: number;
    capture_source: string;
    captured_at: string | null;
    uploader: { id: number; name: string } | null;
};

export type Transfer = {
    id: number;
    company_id: number;
    from_branch_id: number;
    to_branch_id: number;
    transfer_number: string;
    status: TransferStatus;
    revision_number: number;
    lock_version: number;
    reason: string | null;
    planned_dispatch_at: string | null;
    expected_arrival_at: string | null;
    shipping_notes: string | null;
    receiving_notes: string | null;
    shipping_method: string | null;
    courier_name: string | null;
    courier_phone: string | null;
    vehicle_number: string | null;
    tracking_number: string | null;
    waybill_number: string | null;
    seal_number: string | null;
    requested_at: string | null;
    approved_at: string | null;
    shipped_at: string | null;
    received_at: string | null;
    completed_at: string | null;
    cancellation_reason: string | null;
    items_count?: number;
    origin_branch: TransferBranch;
    destination_branch: TransferBranch;
    requester?: { id: number; name: string } | null;
    approver?: { id: number; name: string } | null;
    shipper?: { id: number; name: string } | null;
    receiver?: { id: number; name: string } | null;
    items: TransferItem[];
    approvals: TransferApproval[];
    expenses: TransferExpense[];
    documents: TransferDocument[];
    status_histories?: Array<{
        id: number;
        from_status: string | null;
        to_status: string;
        reason: string | null;
        changed_at: string;
        changer: { id: number; name: string } | null;
    }>;
};

export type TransferPagination = Pagination<Transfer>;

export type TransferPermissions = {
    create: boolean;
    update: boolean;
    approve: boolean;
    cancel: boolean;
    dispatch: boolean;
    receive: boolean;
    expense: boolean;
    resolve: boolean;
    settings: boolean;
    override: boolean;
};
