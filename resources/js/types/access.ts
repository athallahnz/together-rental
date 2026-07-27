export type AccessBranch = {
    id: number;
    code: string;
    name: string;
    is_active?: boolean;
};

export type AccessRole = {
    id: number;
    name: string;
    slug: string;
    scope: 'company' | 'branch';
    is_system: boolean;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Pagination<T> = {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};
