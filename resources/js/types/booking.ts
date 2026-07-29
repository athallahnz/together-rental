import type { AccessBranch, Pagination } from "./access";

export type BookingStatus =
  "draft" | "confirmed" | "converted" | "completed" | "cancelled" | "expired";

export type BookingItem = {
  id: number;
  product_id: number | null;
  package_id: number | null;
  description: string;
  quantity: number;
  unit_rate: string;
  total_amount: string;
  product?: { id: number; sku: string; name: string } | null;
  package?: { id: number; code: string; name: string } | null;
  reservations?: Array<{
    id: number;
    asset: {
      id: number;
      asset_code: string;
      serial_number: string | null;
      status: string;
      condition: string;
    };
  }>;
};

export type Booking = {
  id: number;
  branch_id: number;
  customer_id: number;
  rate_plan_id: number;
  booking_number: string;
  status: BookingStatus;
  source: string;
  booked_at: string;
  starts_at: string;
  ends_at: string;
  subtotal: string;
  discount_amount: string;
  tax_amount: string;
  total_amount: string;
  deposit_required: string;
  deposit_paid: string;
  notes: string | null;
  cancellation_reason: string | null;
  branch?: AccessBranch;
  customer?: {
    id: number;
    customer_number: string;
    name: string;
    phone: string | null;
    email?: string | null;
    risk_level?: string;
  };
  rate_plan?: {
    id: number;
    code: string;
    name: string;
    duration_unit: string;
    duration_value: number;
  };
  items: BookingItem[];
  items_count?: number;
  reservations_count?: number;
  status_histories?: Array<{
    id: number;
    from_status: string | null;
    to_status: string;
    reason: string | null;
    changed_at: string;
    changer?: { id: number; name: string } | null;
  }>;
};

export type BookingPagination = Pagination<Booking>;

export type BookingRatePlan = {
  id: number;
  branch_id?: number | null;
  code: string;
  name: string;
  duration_unit: "hour" | "day" | "week" | "month";
  duration_value: number;
};
