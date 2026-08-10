import type { AccessBranch, Pagination } from './access';

export type PromotionType = 'percentage' | 'fixed' | 'bonus_duration';

export type Promotion = {
    id: number;
    branch_id: number | null;
    code: string;
    name: string;
    type: PromotionType;
    value: string;
    maximum_discount: string | null;
    minimum_transaction: string;
    bonus_duration: number;
    usage_limit: number | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    rules: {
        member_only?: boolean;
        non_member_only?: boolean;
        allow_member_stack?: boolean;
    } | null;
    branch?: AccessBranch | null;
    booking_usage_count?: number;
    extension_usage_count?: number;
    can_manage?: boolean;
};

export type PromotionPagination = Pagination<Promotion>;
