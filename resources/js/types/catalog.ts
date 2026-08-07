import type { AccessBranch } from './access';

export type CatalogBrand = {
    id: number;
    company_id?: number;
    name: string;
    logo_path?: string | null;
    logo_url: string | null;
    sort_order: number;
    is_active?: boolean;
    products_count?: number;
    models_count?: number;
};

export type CatalogModel = {
    id: number;
    company_id?: number;
    catalog_brand_id: number | null;
    category_id?: number | null;
    name: string;
    is_active?: boolean;
    products_count?: number;
};

export type ProductCategory = {
    id: number;
    company_id: number;
    parent_id: number | null;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    parent?: Pick<ProductCategory, 'id' | 'code' | 'name'> | null;
    children_count?: number;
    products_count?: number;
};

export type ProductRate = {
    id: number;
    product_id: number;
    branch_id: number | null;
    rate_plan_id: number;
    amount: string;
    deposit_amount: string;
    additional_hour_amount: string;
    late_fee_amount: string;
    valid_from: string | null;
    valid_until: string | null;
    is_active: boolean;
    branch?: AccessBranch | null;
    rate_plan?: RatePlan;
};

export type BranchInventory = {
    id: number;
    branch_id: number;
    product_id: number;
    quantity_on_hand: number;
    quantity_reserved: number;
    quantity_rented: number;
    quantity_maintenance: number;
    quantity_in_transfer: number;
    reorder_level: number;
    branch?: AccessBranch;
};

export type Product = {
    id: number;
    company_id: number;
    category_id: number | null;
    sku: string;
    name: string;
    brand: string | null;
    catalog_brand_id: number | null;
    model: string | null;
    catalog_model_id: number | null;
    variant: string | null;
    enrichment_status: 'pending' | 'enriched' | 'reviewed';
    enriched_at: string | null;
    tracking_type: 'serialized' | 'bulk';
    description: string | null;
    replacement_value: string;
    is_rentable: boolean;
    is_active: boolean;
    metadata: Record<string, unknown> | null;
    category?: Pick<ProductCategory, 'id' | 'code' | 'name'> | null;
    catalog_brand?: Pick<CatalogBrand, 'id' | 'name' | 'logo_url'> | null;
    catalog_model?: Pick<
        CatalogModel,
        'id' | 'catalog_brand_id' | 'name'
    > | null;
    rates?: ProductRate[];
    branch_inventories?: BranchInventory[];
    assets_count?: number;
    available_assets_count?: number;
    rented_assets_count?: number;
    maintenance_assets_count?: number;
    in_transit_assets_count?: number;
    rates_count?: number;
    package_items_count?: number;
    quantity_on_hand?: string | number | null;
    quantity_reserved?: string | number | null;
    quantity_rented?: string | number | null;
    quantity_maintenance?: string | number | null;
    quantity_in_transfer?: string | number | null;
};

export type RatePlan = {
    id: number;
    company_id: number;
    branch_id: number | null;
    code: string;
    name: string;
    duration_unit: 'minute' | 'hour' | 'day' | 'week' | 'month';
    duration_value: number;
    grace_period_minutes: number;
    is_active: boolean;
    branch?: AccessBranch | null;
    product_rates_count?: number;
    package_rates_count?: number;
};

export type PackageItem = {
    id: number;
    package_id: number;
    product_id: number;
    quantity: number;
    is_optional: boolean;
    sort_order: number;
    product?: Pick<
        Product,
        'id' | 'category_id' | 'sku' | 'name' | 'brand' | 'model' | 'is_active'
    >;
};

export type PackageRate = {
    id: number;
    package_id: number;
    branch_id: number | null;
    rate_plan_id: number;
    amount: string;
    deposit_amount: string;
    is_active: boolean;
    branch?: AccessBranch | null;
    rate_plan?: RatePlan;
};

export type RentalPackage = {
    id: number;
    company_id: number;
    branch_id: number | null;
    code: string;
    name: string;
    description: string | null;
    valid_from: string | null;
    valid_until: string | null;
    is_active: boolean;
    branch?: AccessBranch | null;
    items?: PackageItem[];
    rates?: PackageRate[];
    items_count?: number;
    rates_count?: number;
};

export type CatalogInventorySummary = {
    assets: number;
    availableAssets: number;
    rentedAssets: number;
    maintenanceAssets: number;
    inTransitAssets: number;
    quantityOnHand: number;
    quantityReserved: number;
    quantityRented: number;
    quantityMaintenance: number;
    quantityInTransfer: number;
};

export type CatalogBranchStock = {
    branch: AccessBranch;
    assets: {
        total: number;
        available: number;
        reserved: number;
        rented: number;
        maintenance: number;
        lost: number;
        in_transit: number;
    };
    inventory: {
        quantity_on_hand: number;
        quantity_reserved: number;
        quantity_rented: number;
        quantity_maintenance: number;
        quantity_in_transfer: number;
    };
};

export type CatalogPermissions = {
    manage: boolean;
    manageGlobal: boolean;
};

export type ProductFormData = {
    category_id: number | null;
    sku: string;
    name: string;
    brand: string;
    model: string;
    tracking_type: Product['tracking_type'];
    description: string;
    replacement_value: string;
    is_rentable: boolean;
    is_active: boolean;
};

export type CategoryFormData = {
    parent_id: number | null;
    code: string;
    name: string;
    description: string;
    is_active: boolean;
    sort_order: number;
};

export type RatePlanFormData = {
    branch_id: number | null;
    code: string;
    name: string;
    duration_unit: RatePlan['duration_unit'];
    duration_value: number;
    grace_period_minutes: number;
    is_active: boolean;
};

export type RentalPackageFormData = {
    branch_id: number | null;
    code: string;
    name: string;
    description: string;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
};

export type ProductRateFormData = {
    branch_id: number | null;
    rate_plan_id: number | null;
    amount: string;
    deposit_amount: string;
    additional_hour_amount: string;
    late_fee_amount: string;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
};

export type PackageRateFormData = {
    branch_id: number | null;
    rate_plan_id: number | null;
    amount: string;
    deposit_amount: string;
    is_active: boolean;
};
