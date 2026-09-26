import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    BellRing,
    Building2,
    Globe2,
    History,
    Save,
    Settings2,
    ShieldCheck,
    Truck,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useAppLocale } from '@/lib/i18n';
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
    const { tr } = useAppLocale();
    const captureMode = (mode: string): string => {
        const labels: Record<string, string> = {
            camera_required: tr('settings.center.cameraRequired'),
            camera_preferred: tr('settings.center.cameraPreferred'),
            gallery_allowed: tr('settings.center.galleryAllowed'),
        };

        return labels[mode] ?? mode;
    };
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
            <Head title={tr('settings.center.head')} />

            <div className="space-y-6">
                <Card className="overflow-hidden border-border/70 bg-gradient-to-br from-card via-card to-muted/30">
                    <CardContent className="p-6 sm:p-7">
                        <header className="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                            <div className="max-w-3xl">
                                <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                    <Settings2 className="size-4" />
                                    {tr('settings.center.operation')}
                                </div>
                                <h2 className="mt-2 text-2xl font-semibold tracking-tight">
                                    {tr('settings.center.hero')}
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    {tr('settings.center.heroDescription')}
                                </p>
                            </div>

                            {branches.length > 0 &&
                                selectedBranchId !== null && (
                                    <div className="w-full space-y-2 xl:max-w-sm">
                                        <Label htmlFor="settings-branch">
                                            {tr(
                                                'settings.center.branchContext',
                                            )}
                                        </Label>
                                        <Select
                                            value={String(selectedBranchId)}
                                            onValueChange={switchBranch}
                                        >
                                            <SelectTrigger
                                                id="settings-branch"
                                                className="bg-background"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {branches.map((branch) => (
                                                    <SelectItem
                                                        key={branch.id}
                                                        value={String(
                                                            branch.id,
                                                        )}
                                                    >
                                                        {branch.code} ·{' '}
                                                        {branch.name}
                                                        {!branch.is_active
                                                            ? ` · ${tr('settings.center.inactive')}`
                                                            : ''}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                        </header>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-3">
                    <SummaryCard
                        title={tr('settings.center.company')}
                        value={company.code}
                        detail={
                            company.is_active
                                ? tr('settings.center.activeCompany')
                                : tr('settings.center.inactiveCompany')
                        }
                    />
                    <SummaryCard
                        title={tr('settings.center.branches')}
                        value={String(branches.length)}
                        detail={
                            selectedBranch
                                ? `${selectedBranch.code} · ${selectedBranch.name}`
                                : tr('settings.center.noneScope')
                        }
                    />
                    <SummaryCard
                        title={tr('settings.center.regional')}
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
                                        {tr('settings.center.identity')}
                                    </CardTitle>
                                    <CardDescription className="mt-2">
                                        {tr(
                                            'settings.center.companyDescription',
                                            {
                                                code: company.code,
                                                currency: company.currency,
                                            },
                                        )}
                                    </CardDescription>
                                </div>
                                <Badge variant="outline">
                                    {permissions.company_manage
                                        ? tr('settings.center.editable')
                                        : tr('settings.center.readonly')}
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
                                        label={tr(
                                            'settings.center.companyName',
                                        )}
                                        name="name"
                                        value={companyForm.data.name}
                                        disabled={!permissions.company_manage}
                                        error={companyForm.errors.name}
                                        onChange={(value) =>
                                            companyForm.setData('name', value)
                                        }
                                    />
                                    <Field
                                        label={tr('settings.center.legalName')}
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
                                        label={tr('settings.center.tax')}
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
                                        label={tr('settings.center.telephone')}
                                        name="phone"
                                        value={companyForm.data.phone}
                                        disabled={!permissions.company_manage}
                                        error={companyForm.errors.phone}
                                        onChange={(value) =>
                                            companyForm.setData('phone', value)
                                        }
                                    />
                                    <Field
                                        label={tr('settings.center.email')}
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
                                        label={tr('settings.center.timezone')}
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
                                        {tr('settings.center.address')}
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
                                                ? tr('settings.center.saving')
                                                : tr('settings.center.save')}
                                        </Button>
                                    </div>
                                )}
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 xl:grid-cols-3">
                    <Card className="h-full">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Globe2 className="size-5" />
                                {tr('settings.center.branchPublic')}
                            </CardTitle>
                            <CardDescription>
                                {tr('settings.center.branchDescription')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {selectedBranch === null ? (
                                <EmptyState
                                    text={tr('settings.center.branchEmpty')}
                                />
                            ) : (
                                <>
                                    <SettingStatus
                                        label={tr(
                                            'settings.center.branchActive',
                                        )}
                                        value={
                                            selectedBranch.is_active
                                                ? tr('settings.center.active')
                                                : tr('settings.center.inactive')
                                        }
                                        positive={selectedBranch.is_active}
                                    />
                                    <SettingStatus
                                        label={tr(
                                            'settings.center.publicCatalog',
                                        )}
                                        value={
                                            publicProfile?.catalog_enabled
                                                ? tr(
                                                      'settings.center.published',
                                                  )
                                                : tr(
                                                      'settings.center.notPublished',
                                                  )
                                        }
                                        positive={
                                            publicProfile?.catalog_enabled ===
                                            true
                                        }
                                    />
                                    <SettingStatus
                                        label={tr(
                                            'settings.center.profileCompleteness',
                                        )}
                                        value={`${publicProfile?.configured_fields ?? 0}/${publicProfile?.total_fields ?? 0} ${tr('settings.center.fields')}`}
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
                                                    {tr(
                                                        'settings.center.publicProfile',
                                                    )}
                                                    <ArrowUpRight className="size-4" />
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="h-full">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Truck className="size-5" />
                                {tr('settings.center.transfer')}
                            </CardTitle>
                            <CardDescription>
                                {tr('settings.center.transferDescription')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {selectedBranch === null ? (
                                <EmptyState
                                    text={tr('settings.center.chooseBranch')}
                                />
                            ) : transferPolicy === null ? (
                                <EmptyState
                                    text={tr(
                                        'settings.center.noTransferPermission',
                                    )}
                                />
                            ) : (
                                <>
                                    <SettingStatus
                                        label={tr('settings.center.dispatch')}
                                        value={`${captureMode(transferPolicy.dispatch_capture_mode)} · min. ${transferPolicy.dispatch_min_photos} ${tr('settings.center.minimumPhotos')}`}
                                    />
                                    <SettingStatus
                                        label={tr('settings.center.receiving')}
                                        value={`${captureMode(transferPolicy.receiving_capture_mode)} · min. ${transferPolicy.receiving_min_photos} ${tr('settings.center.minimumPhotos')}`}
                                    />
                                    <SettingStatus
                                        label={tr('settings.center.waybill')}
                                        value={
                                            transferPolicy.require_waybill
                                                ? tr('settings.center.required')
                                                : tr('settings.center.optional')
                                        }
                                        positive={
                                            transferPolicy.require_waybill
                                        }
                                    />
                                    <SettingStatus
                                        label={tr('settings.center.gallery')}
                                        value={
                                            transferPolicy.allow_gallery_override
                                                ? tr(
                                                      'settings.center.permitted',
                                                  )
                                                : tr('settings.center.disabled')
                                        }
                                    />

                                    <Button asChild className="mt-2">
                                        <Link
                                            href={`/transfers/settings?branch_id=${selectedBranch.id}`}
                                        >
                                            {tr(
                                                'settings.center.configureTransfer',
                                            )}
                                            <ArrowUpRight className="size-4" />
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="h-full">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BellRing className="size-5" />
                                {tr('settings.center.notifications')}
                            </CardTitle>
                            <CardDescription>
                                {tr('settings.center.notificationDescription')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {notifications === null ? (
                                <EmptyState
                                    text={tr(
                                        'settings.center.noNotificationAccess',
                                    )}
                                />
                            ) : (
                                <>
                                    <SettingStatus
                                        label={tr(
                                            'settings.center.activeRules',
                                        )}
                                        value={`${notifications.enabled_rules}/${notifications.total_rules}`}
                                        positive={
                                            notifications.enabled_rules > 0
                                        }
                                    />
                                    <SettingStatus
                                        label={tr(
                                            'settings.center.criticalRules',
                                        )}
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
                                                ? tr(
                                                      'settings.center.manageNotifications',
                                                  )
                                                : tr(
                                                      'settings.center.openNotifications',
                                                  )}
                                            <ArrowUpRight className="size-4" />
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="xl:col-span-3">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShieldCheck className="size-5" />
                                {tr('settings.center.governance')}
                            </CardTitle>
                            <CardDescription>
                                {tr('settings.center.governanceDescription')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            {permissions.roles_view && (
                                <Shortcut
                                    icon={<ShieldCheck className="size-4" />}
                                    title={tr('settings.center.roles')}
                                    href="/roles"
                                />
                            )}
                            {permissions.audit_view && (
                                <Shortcut
                                    icon={<History className="size-4" />}
                                    title={tr('settings.center.audit')}
                                    href="/audit-trail"
                                />
                            )}
                            {!permissions.roles_view &&
                                !permissions.audit_view && (
                                    <EmptyState
                                        text={tr(
                                            'settings.center.governanceEmpty',
                                        )}
                                    />
                                )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

SettingsCenter.layout = {
    breadcrumbs: [
        {
            title: 'Pusat Pengaturan',
            href: '/settings-center',
        },
    ],
};

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
