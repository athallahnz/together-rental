import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    BellRing,
    Building2,
    Globe2,
    History,
    Palette,
    Save,
    Settings2,
    ShieldCheck,
    Truck,
    UserRound,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Company = {
    id: number;
    code: string;
    name: string;
    legal_name: string | null;
    tax_number: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    timezone: string;
    currency: string;
    is_active: boolean;
};

type Branch = {
    id: number;
    code: string;
    name: string;
    city: string | null;
    timezone: string;
    is_active: boolean;
};

type PublicProfileSummary = {
    catalog_enabled: boolean;
    configured_fields: number;
    total_fields: number;
};

type TransferPolicy = {
    dispatch_capture_mode: string;
    receiving_capture_mode: string;
    dispatch_min_photos: number;
    receiving_min_photos: number;
    require_waybill: boolean;
    allow_gallery_override: boolean;
};

type NotificationSummary = {
    total_rules: number;
    enabled_rules: number;
    critical_rules: number;
};

type Permissions = {
    company_view: boolean;
    company_manage: boolean;
    branches_view: boolean;
    branches_manage: boolean;
    transfers_settings: boolean;
    notifications_view: boolean;
    notifications_manage: boolean;
    roles_view: boolean;
    audit_view: boolean;
};

type Props = {
    company: Company;
    branches: Branch[];
    selectedBranchId: number | null;
    selectedBranch: Branch | null;
    publicProfile: PublicProfileSummary | null;
    transferPolicy: TransferPolicy | null;
    notifications: NotificationSummary | null;
    permissions: Permissions;
};

const captureModeLabels: Record<string, string> = {
    camera_required: 'Kamera wajib',
    camera_preferred: 'Kamera diutamakan',
    gallery_allowed: 'Galeri diizinkan',
};

export default function SettingsCenter({
    company,
    branches,
    selectedBranchId,
    selectedBranch,
    publicProfile,
    transferPolicy,
    notifications,
    permissions,
}: Props) {
    const companyForm = useForm({
        name: company.name,
        legal_name: company.legal_name ?? '',
        tax_number: company.tax_number ?? '',
        phone: company.phone ?? '',
        email: company.email ?? '',
        address: company.address ?? '',
        timezone: company.timezone,
    });

    const switchBranch = (value: string) => {
        router.get(
            '/settings-center',
            { branch_id: Number(value) },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const submitCompany = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        companyForm.put('/settings-center/company', {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Settings Center" />

            <div className="space-y-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Settings2 className="size-4" />
                            Konfigurasi terpusat
                        </div>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                            Settings Center
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Pusat konfigurasi Together Kamera tanpa menduplikasi
                            source-of-truth. Pengaturan cabang, transfer,
                            notifikasi, akses, dan akun tetap disimpan oleh
                            domain masing-masing.
                        </p>
                    </div>

                    {branches.length > 0 && selectedBranchId !== null && (
                        <div className="w-full max-w-sm space-y-2">
                            <Label htmlFor="settings-branch">
                                Konteks cabang
                            </Label>
                            <Select
                                value={String(selectedBranchId)}
                                onValueChange={switchBranch}
                            >
                                <SelectTrigger id="settings-branch">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {branches.map((branch) => (
                                        <SelectItem
                                            key={branch.id}
                                            value={String(branch.id)}
                                        >
                                            {branch.code} · {branch.name}
                                            {!branch.is_active
                                                ? ' · Nonaktif'
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </header>

                <div className="grid gap-4 md:grid-cols-3">
                    <SummaryCard
                        title="Perusahaan"
                        value={company.code}
                        detail={
                            company.is_active
                                ? 'Status perusahaan aktif'
                                : 'Status perusahaan nonaktif'
                        }
                    />
                    <SummaryCard
                        title="Cabang tersedia"
                        value={String(branches.length)}
                        detail={
                            selectedBranch
                                ? `${selectedBranch.code} · ${selectedBranch.name}`
                                : 'Tidak ada cabang dalam scope'
                        }
                    />
                    <SummaryCard
                        title="Regional"
                        value={company.currency}
                        detail={company.timezone}
                    />
                </div>

                {permissions.company_view && (
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="size-5" />
                                        Identitas perusahaan
                                    </CardTitle>
                                    <CardDescription className="mt-2">
                                        Source-of-truth langsung dari tabel
                                        perusahaan. Kode {company.code} dan mata
                                        uang {company.currency} tetap bersifat
                                        identitas sistem.
                                    </CardDescription>
                                </div>
                                <Badge variant="outline">
                                    {permissions.company_manage
                                        ? 'Dapat diedit'
                                        : 'Read only'}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitCompany}
                                className="space-y-5"
                            >
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        label="Nama perusahaan"
                                        name="name"
                                        value={companyForm.data.name}
                                        disabled={!permissions.company_manage}
                                        error={companyForm.errors.name}
                                        onChange={(value) =>
                                            companyForm.setData('name', value)
                                        }
                                    />
                                    <Field
                                        label="Nama legal"
                                        name="legal_name"
                                        value={companyForm.data.legal_name}
                                        disabled={!permissions.company_manage}
                                        error={companyForm.errors.legal_name}
                                        onChange={(value) =>
                                            companyForm.setData(
                                                'legal_name',
                                                value,
                                            )
                                        }
                                    />
                                    <Field
                                        label="NPWP / nomor pajak"
                                        name="tax_number"
                                        value={companyForm.data.tax_number}
                                        disabled={!permissions.company_manage}
                                        error={companyForm.errors.tax_number}
                                        onChange={(value) =>
                                            companyForm.setData(
                                                'tax_number',
                                                value,
                                            )
                                        }
                                    />
                                    <Field
                                        label="Telepon"
                                        name="phone"
                                        value={companyForm.data.phone}
                                        disabled={!permissions.company_manage}
                                        error={companyForm.errors.phone}
                                        onChange={(value) =>
                                            companyForm.setData('phone', value)
                                        }
                                    />
                                    <Field
                                        label="Email"
                                        name="email"
                                        type="email"
                                        value={companyForm.data.email}
                                        disabled={!permissions.company_manage}
                                        error={companyForm.errors.email}
                                        onChange={(value) =>
                                            companyForm.setData('email', value)
                                        }
                                    />
                                    <Field
                                        label="Timezone"
                                        name="timezone"
                                        value={companyForm.data.timezone}
                                        disabled={!permissions.company_manage}
                                        error={companyForm.errors.timezone}
                                        onChange={(value) =>
                                            companyForm.setData(
                                                'timezone',
                                                value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="company-address">
                                        Alamat perusahaan
                                    </Label>
                                    <textarea
                                        id="company-address"
                                        value={companyForm.data.address}
                                        disabled={!permissions.company_manage}
                                        onChange={(event) =>
                                            companyForm.setData(
                                                'address',
                                                event.target.value,
                                            )
                                        }
                                        rows={3}
                                        className="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError
                                        message={companyForm.errors.address}
                                    />
                                </div>

                                {permissions.company_manage && (
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            disabled={companyForm.processing}
                                        >
                                            <Save className="size-4" />
                                            {companyForm.processing
                                                ? 'Menyimpan...'
                                                : 'Simpan perusahaan'}
                                        </Button>
                                    </div>
                                )}
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Globe2 className="size-5" />
                                Cabang & katalog publik
                            </CardTitle>
                            <CardDescription>
                                Identitas cabang tetap dikelola dari Branch
                                Center; profil publik tetap menggunakan
                                branch_settings.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {selectedBranch === null ? (
                                <EmptyState text="Tidak ada cabang yang dapat dikonfigurasi." />
                            ) : (
                                <>
                                    <SettingStatus
                                        label="Cabang aktif"
                                        value={
                                            selectedBranch.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }
                                        positive={selectedBranch.is_active}
                                    />
                                    <SettingStatus
                                        label="Katalog publik"
                                        value={
                                            publicProfile?.catalog_enabled
                                                ? 'Ditayangkan'
                                                : 'Tidak ditayangkan'
                                        }
                                        positive={
                                            publicProfile?.catalog_enabled ===
                                            true
                                        }
                                    />
                                    <SettingStatus
                                        label="Kelengkapan profil"
                                        value={`${publicProfile?.configured_fields ?? 0}/${publicProfile?.total_fields ?? 0} field`}
                                    />

                                    <div className="flex flex-wrap gap-2 pt-2">
                                        {permissions.branches_view && (
                                            <Button asChild variant="outline">
                                                <Link href="/branches">
                                                    Branch Center
                                                    <ArrowUpRight className="size-4" />
                                                </Link>
                                            </Button>
                                        )}
                                        {permissions.branches_manage && (
                                            <Button asChild>
                                                <Link
                                                    href={`/branches/${selectedBranch.id}/public-profile`}
                                                >
                                                    Profil publik
                                                    <ArrowUpRight className="size-4" />
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Truck className="size-5" />
                                Kebijakan transfer
                            </CardTitle>
                            <CardDescription>
                                Nilai berasal langsung dari TransferSettings dan
                                branch_settings cabang yang dipilih.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {selectedBranch === null ? (
                                <EmptyState text="Pilih cabang untuk melihat kebijakan transfer." />
                            ) : transferPolicy === null ? (
                                <EmptyState text="Permission transfer settings belum diberikan ke akun ini." />
                            ) : (
                                <>
                                    <SettingStatus
                                        label="Dispatch"
                                        value={`${captureModeLabels[transferPolicy.dispatch_capture_mode] ?? transferPolicy.dispatch_capture_mode} · min. ${transferPolicy.dispatch_min_photos} foto`}
                                    />
                                    <SettingStatus
                                        label="Receiving"
                                        value={`${captureModeLabels[transferPolicy.receiving_capture_mode] ?? transferPolicy.receiving_capture_mode} · min. ${transferPolicy.receiving_min_photos} foto`}
                                    />
                                    <SettingStatus
                                        label="Surat jalan"
                                        value={
                                            transferPolicy.require_waybill
                                                ? 'Wajib'
                                                : 'Opsional'
                                        }
                                        positive={
                                            transferPolicy.require_waybill
                                        }
                                    />
                                    <SettingStatus
                                        label="Override galeri"
                                        value={
                                            transferPolicy.allow_gallery_override
                                                ? 'Diizinkan dengan permission'
                                                : 'Dinonaktifkan'
                                        }
                                    />

                                    <Button asChild className="mt-2">
                                        <Link
                                            href={`/transfers/settings?branch_id=${selectedBranch.id}`}
                                        >
                                            Atur kebijakan transfer
                                            <ArrowUpRight className="size-4" />
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BellRing className="size-5" />
                                Notification & Reminder
                            </CardTitle>
                            <CardDescription>
                                Rules tetap disimpan di notification_rules;
                                preferensi personal tetap berada di Notification
                                Center.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {notifications === null ? (
                                <EmptyState text="Akun ini tidak memiliki akses ke Notification Center." />
                            ) : (
                                <>
                                    <SettingStatus
                                        label="Rule aktif"
                                        value={`${notifications.enabled_rules}/${notifications.total_rules}`}
                                        positive={
                                            notifications.enabled_rules > 0
                                        }
                                    />
                                    <SettingStatus
                                        label="Rule critical aktif"
                                        value={String(
                                            notifications.critical_rules,
                                        )}
                                    />
                                    <Button
                                        asChild
                                        variant={
                                            permissions.notifications_manage
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        <Link href="/notifications">
                                            {permissions.notifications_manage
                                                ? 'Kelola reminder'
                                                : 'Buka Notification Center'}
                                            <ArrowUpRight className="size-4" />
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShieldCheck className="size-5" />
                                Governance & audit
                            </CardTitle>
                            <CardDescription>
                                Hak akses dan histori tetap memakai modul
                                otorisasi serta audit trail yang sudah ada.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            {permissions.roles_view && (
                                <Shortcut
                                    icon={<ShieldCheck className="size-4" />}
                                    title="Role & Hak Akses"
                                    href="/roles"
                                />
                            )}
                            {permissions.audit_view && (
                                <Shortcut
                                    icon={<History className="size-4" />}
                                    title="Audit Trail"
                                    href="/audit-trail"
                                />
                            )}
                            {!permissions.roles_view &&
                                !permissions.audit_view && (
                                    <EmptyState text="Tidak ada permission governance pada akun ini." />
                                )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <UserRound className="size-5" />
                            Akun & tampilan
                        </CardTitle>
                        <CardDescription>
                            Setting personal tetap terpisah dari konfigurasi
                            operasional perusahaan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-3">
                        <Shortcut
                            icon={<UserRound className="size-4" />}
                            title="Profil akun"
                            href="/settings/profile"
                        />
                        <Shortcut
                            icon={<ShieldCheck className="size-4" />}
                            title="Security, 2FA & Passkey"
                            href="/settings/security"
                        />
                        <Shortcut
                            icon={<Palette className="size-4" />}
                            title="Appearance"
                            href="/settings/appearance"
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function SummaryCard({
    title,
    value,
    detail,
}: {
    title: string;
    value: string;
    detail: string;
}) {
    return (
        <Card>
            <CardContent className="pt-6">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {title}
                </p>
                <p className="mt-2 text-2xl font-semibold">{value}</p>
                <p className="mt-1 text-xs text-muted-foreground">{detail}</p>
            </CardContent>
        </Card>
    );
}

function Field({
    label,
    name,
    value,
    type = 'text',
    disabled,
    error,
    onChange,
}: {
    label: string;
    name: string;
    value: string;
    type?: string;
    disabled: boolean;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={`company-${name}`}>{label}</Label>
            <Input
                id={`company-${name}`}
                type={type}
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
            />
            <InputError message={error} />
        </div>
    );
}

function SettingStatus({
    label,
    value,
    positive,
}: {
    label: string;
    value: string;
    positive?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
            <span className="text-sm text-muted-foreground">{label}</span>
            <Badge variant={positive === false ? 'secondary' : 'outline'}>
                {value}
            </Badge>
        </div>
    );
}

function Shortcut({
    icon,
    title,
    href,
}: {
    icon: ReactNode;
    title: string;
    href: string;
}) {
    return (
        <Button
            asChild
            variant="outline"
            className="h-auto justify-between gap-3 py-3"
        >
            <Link href={href}>
                <span className="flex items-center gap-2">
                    {icon}
                    {title}
                </span>
                <ArrowUpRight className="size-4" />
            </Link>
        </Button>
    );
}

function EmptyState({ text }: { text: string }) {
    return (
        <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
            {text}
        </p>
    );
}
