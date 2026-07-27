export type User = {
    id: number;
    company_id: number | null;
    current_branch_id: number | null;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    status: string;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type BranchSummary = {
    id: number;
    code: string;
    name: string;
    city: string | null;
    is_active?: boolean;
};

export type Auth = {
    user: User;
    permissions: Record<string, boolean>;
    currentBranch: BranchSummary | null;
    branches: BranchSummary[];
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
