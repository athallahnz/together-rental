export type PublicBranch = {
    id: number;
    company_id: number;
    code: string;
    name: string;
    city: string | null;
    address: string;
    opening_hours: string;
    whatsapp: string;
    whatsapp_url: string | null;
    maps_url: string | null;
    instagram: string;
    instagram_url: string | null;
    logo_url: string;
    hero_title: string;
    hero_description: string;
    catalog_enabled: boolean;
};

export type PublicRate = {
    id: number;
    rate_plan: string;
    duration_label: string;
    duration_minutes: number;
    amount: number;
    deposit_amount: number;
};

export type PublicAvailability = {
    available_units: number;
    total_units: number;
    in_transit_units?: number;
    status: 'available' | 'limited' | 'in_transit' | 'unavailable';
    label: string;
};

export type PublicCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string | null;
    image_url: string | null;
    products_count: number;
};

export type PublicBrand = {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    products_count: number;
};

export type PublicProduct = {
    id: number;
    slug: string;
    name: string;
    brand: string | null;
    model: string | null;
    variant: string | null;
    short_description: string | null;
    image_url: string | null;
    category: {
        name: string;
        slug: string;
    } | null;
    rates: PublicRate[];
    starting_price: number | null;
    availability: PublicAvailability;
    is_featured: boolean;
    inquiry_url: string | null;
};

export type PublicProductDetail = PublicProduct & {
    description: string | null;
    seo_title: string;
    seo_description: string | null;
    gallery: string[];
    specifications: Record<string, string>;
};

export type PublicPackageItem = {
    name: string;
    slug: string;
    quantity: number;
    is_optional: boolean;
    image_url: string | null;
    availability: PublicAvailability;
};

export type PublicPackage = {
    id: number;
    slug: string;
    name: string;
    short_description: string | null;
    image_url: string | null;
    rates: PublicRate[];
    starting_price: number | null;
    items_count: number;
    availability: Pick<
        PublicAvailability,
        'available_units' | 'status' | 'label'
    >;
    is_featured: boolean;
    inquiry_url: string | null;
};

export type PublicPackageDetail = PublicPackage & {
    description: string | null;
    seo_title: string;
    seo_description: string | null;
    items: PublicPackageItem[];
};

export type PublicPaginator<T> = {
    current_page: number;
    data: T[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

export type PublicCatalogFilters = {
    search: string;
    branch: string;
    category: string;
    brand: string;
    availability: 'all' | 'available';
    sort: 'recommended' | 'name' | 'price_low' | 'price_high';
};

export type PublicAvailabilityPeriod = {
    starts_at: string;
    ends_at: string;
    starts_label: string;
    ends_label: string;
    duration_minutes: number;
    duration_label: string;
    timezone: string;
    timezone_label: string;
};

export type PublicAvailabilityEstimate = {
    billing_units: number;
    rental_amount: number;
    deposit_amount: number;
    initial_payment_estimate: number;
    note: string;
};

export type PublicAvailabilityItem = {
    name: string;
    slug: string;
    quantity_per_package: number;
    requested_units: number;
    is_optional: boolean;
    total_units: number;
    available_units: number;
    status: PublicAvailability['status'];
    label: string;
};

export type PublicAvailabilityResult = {
    type: 'product' | 'package';
    slug: string;
    name: string;
    branch: {
        code: string;
        name: string;
        city: string | null;
        timezone: string;
    };
    period: PublicAvailabilityPeriod;
    requested_quantity: number;
    availability: PublicAvailability & {
        reserved_units: number;
        rented_units: number;
        requested_units: number;
    };
    rate: PublicRate | null;
    estimate: PublicAvailabilityEstimate | null;
    items: PublicAvailabilityItem[];
    inquiry_url: string | null;
    requires_confirmation: boolean;
};
