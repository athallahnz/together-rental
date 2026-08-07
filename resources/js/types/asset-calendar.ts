export type AssetCalendarStatus =
    'booked' | 'rented' | 'maintenance' | 'in_transit';

export type AssetCalendarEvent = {
    id: string;
    type: 'booking' | 'rental' | 'maintenance' | 'transfer';
    status: AssetCalendarStatus;
    label: string;
    starts_at: string;
    ends_at: string;
    is_open_ended: boolean;
    is_overdue?: boolean;
    reference: string | null;
    href: string | null;
};

export type InternalAssetCalendarPayload = {
    month: string;
    asset: {
        id: number | null;
        asset_code: string | null;
        serial_number: string | null;
        status: string;
        condition: string;
        branch: {
            id: number;
            code: string;
            name: string;
            city: string | null;
        } | null;
    };
    product: {
        id: number | null;
        name: string | null;
    };
    range: {
        starts_at: string;
        ends_at: string;
    };
    events: AssetCalendarEvent[];
};

export type PublicAssetCalendarPayload = {
    month: string;
    branch: {
        code: string;
        name: string;
        city: string | null;
    };
    product: {
        slug: string;
        name: string;
    };
    units: Array<{
        key: string;
        label: string;
        current_status: string;
        condition: string;
    }>;
    selected_unit: {
        key: string;
        label: string;
        current_status: string;
        condition: string;
        events: AssetCalendarEvent[];
    } | null;
    privacy_note: string;
};
