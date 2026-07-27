import type { AccessBranch } from './access';

export type CustomerIdentity = {
    id: number;
    customer_id: number;
    type:
        'ktp' | 'sim' | 'passport' | 'student_card' | 'employee_card' | 'other';
    number: string;
    name_on_identity: string | null;
    expires_at: string | null;
    is_primary: boolean;
    verified_at: string | null;
    verified_by: number | null;
    verifier?: { id: number; name: string } | null;
};

export type CustomerAddress = {
    id: number;
    customer_id: number;
    type: 'identity' | 'domicile' | 'work' | 'other';
    address: string;
    village: string | null;
    district: string | null;
    city: string | null;
    province: string | null;
    postal_code: string | null;
    is_primary: boolean;
};

export type LoyaltyTransaction = {
    id: number;
    loyalty_account_id: number;
    branch_id: number | null;
    type: 'earn' | 'redeem' | 'adjustment';
    points: number;
    balance_after: number;
    description: string | null;
    occurred_at: string;
    expires_at: string | null;
    branch?: AccessBranch | null;
    creator?: { id: number; name: string } | null;
};

export type LoyaltyAccount = {
    id: number;
    customer_id: number;
    points_balance: number;
    lifetime_points: number;
    tier: 'regular' | 'silver' | 'gold' | 'platinum';
    is_active: boolean;
    transactions?: LoyaltyTransaction[];
};

export type Customer = {
    id: number;
    company_id: number;
    registered_branch_id: number | null;
    customer_number: string;
    name: string;
    gender: 'male' | 'female' | null;
    phone: string | null;
    email: string | null;
    birth_place: string | null;
    birth_date: string | null;
    institution: string | null;
    is_member: boolean;
    member_number: string | null;
    member_since: string | null;
    status: 'active' | 'inactive' | 'blocked';
    risk_level: 'low' | 'normal' | 'high' | 'critical';
    notes: string | null;
    created_at: string;
    updated_at: string;
    registered_branch: AccessBranch | null;
    primary_identity?: CustomerIdentity | null;
    identities?: CustomerIdentity[];
    addresses?: CustomerAddress[];
    loyalty_account?: LoyaltyAccount | null;
    identities_count?: number;
    rentals_count?: number;
    bookings_count?: number;
    last_rental_at?: string | null;
};

export type CustomerFormData = {
    registered_branch_id: number | null;
    name: string;
    gender: Customer['gender'] | '';
    phone: string;
    email: string;
    birth_place: string;
    birth_date: string;
    institution: string;
    is_member: boolean;
    member_number: string;
    member_since: string;
    status: Customer['status'];
    risk_level: Customer['risk_level'];
    notes: string;
};
