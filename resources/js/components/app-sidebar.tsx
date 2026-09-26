import { Link, usePage } from '@inertiajs/react';
import {
    ArchiveRestore,
    BarChart3,
    CreditCard,
    CalendarDays,
    ClipboardCheck,
    Building2,
    ContactRound,
    DatabaseZap,
    Globe2,
    History,
    LayoutGrid,
    PackageSearch,
    ShieldCheck,
    Settings2,
    ShoppingBag,
    UserRound,
    UsersRound,
    Wrench,
    Sparkles,
    ArrowLeftRight,
    RotateCcw,
    ReceiptText,
    WalletCards,
    SlidersHorizontal,
    FileSpreadsheet,
    FileText,
    BellRing,
    BadgePercent,
    Banknote,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { BranchSwitcher } from '@/components/branch-switcher';
import { NavMain } from '@/components/nav-main';
import type { NavMainGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppLocale } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const { tr } = useAppLocale();
    const canOpenSettingsCenter =
        auth.permissions['company.view'] ||
        auth.permissions['company.manage'] ||
        auth.permissions['branches.manage'] ||
        auth.permissions['transfers.settings'] ||
        auth.permissions['notifications.manage'] ||
        auth.permissions['roles.view'];
    const navGroups: NavMainGroup[] = [
        {
            label: tr('nav.overview'),
            items: [
                {
                    title: tr('nav.dashboard'),
                    href: dashboard(),
                    icon: LayoutGrid,
                },
                ...(auth.permissions['notifications.view']
                    ? [
                          {
                              title: tr('nav.notifications'),
                              href: '/notifications',
                              icon: BellRing,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: tr('nav.rentalOperations'),
            items: [
                ...(auth.permissions['bookings.view']
                    ? [
                          {
                              title: tr('nav.bookings'),
                              href: '/bookings',
                              icon: CalendarDays,
                          },
                      ]
                    : []),
                ...(auth.permissions['rentals.view']
                    ? [
                          {
                              title: tr('nav.rentals'),
                              href: '/rentals',
                              icon: ShoppingBag,
                          },
                      ]
                    : []),
                ...(auth.permissions['rentals.create']
                    ? [
                          {
                              title: tr('nav.directRentals'),
                              href: '/rentals/direct/create',
                              icon: ShoppingBag,
                          },
                      ]
                    : []),
                ...(auth.permissions['documents.view']
                    ? [
                          {
                              title: tr('nav.documents'),
                              href: '/documents',
                              icon: FileText,
                          },
                      ]
                    : []),
                ...(auth.permissions['maintenance.view']
                    ? [
                          {
                              title: tr('nav.maintenance'),
                              href: '/maintenance',
                              icon: Wrench,
                          },
                      ]
                    : []),
            ] satisfies NavItem[],
        },
        {
            label: tr('nav.finance'),
            items: [
                ...(auth.permissions['finance.dashboard.view']
                    ? [
                          {
                              title: tr('nav.financeDashboard'),
                              href: '/finance/dashboard',
                              icon: WalletCards,
                          },
                      ]
                    : []),
                ...(auth.permissions['payments.view']
                    ? [
                          {
                              title: tr('nav.payments'),
                              href: '/finance/payments',
                              icon: CreditCard,
                          },
                      ]
                    : []),
                ...(auth.permissions['expenses.view']
                    ? [
                          {
                              title: tr('nav.expenses'),
                              href: '/finance/expenses',
                              icon: Banknote,
                          },
                      ]
                    : []),
                ...(auth.permissions['refunds.view']
                    ? [
                          {
                              title: tr('nav.refunds'),
                              href: '/finance/refunds',
                              icon: ReceiptText,
                          },
                      ]
                    : []),
                ...(auth.permissions['finance.masters.view']
                    ? [
                          {
                              title: tr('nav.financeMaster'),
                              href: '/finance/master-data',
                              icon: SlidersHorizontal,
                          },
                      ]
                    : []),
            ] satisfies NavItem[],
        },
        {
            label: tr('nav.inventory'),
            items: [
                ...(auth.permissions['customers.view']
                    ? [
                          {
                              title: tr('nav.customers'),
                              href: '/customers',
                              icon: ContactRound,
                          },
                      ]
                    : []),
                ...(auth.permissions['products.view']
                    ? [
                          {
                              title: tr('nav.catalog'),
                              href: '/catalog',
                              icon: PackageSearch,
                          },
                          {
                              title: tr('nav.promotions'),
                              href: '/catalog/promotions',
                              icon: BadgePercent,
                          },
                      ]
                    : []),
                ...(auth.permissions['assets.view']
                    ? [
                          {
                              title: tr('nav.assetLifecycle'),
                              href: '/assets/lifecycle',
                              icon: ArchiveRestore,
                          },
                      ]
                    : []),
                ...(auth.permissions['products.manage']
                    ? [
                          {
                              title: tr('nav.publicCatalogContent'),
                              href: '/catalog/public-content',
                              icon: Globe2,
                          },
                          {
                              title: tr('nav.catalogIntelligence'),
                              href: '/catalog/intelligence',
                              icon: Sparkles,
                          },
                      ]
                    : []),
                ...(auth.permissions['transfers.view']
                    ? [
                          {
                              title: tr('nav.assetTransfers'),
                              href: '/transfers',
                              icon: ArrowLeftRight,
                          },
                      ]
                    : []),
                ...(auth.permissions['inventory-audits.view']
                    ? [
                          {
                              title: tr('nav.stocktaking'),
                              href: '/inventory-audits',
                              icon: ClipboardCheck,
                          },
                      ]
                    : []),
            ] satisfies NavItem[],
        },
        {
            label: tr('nav.reports'),
            items: [
                ...(auth.permissions['reports.view']
                    ? [
                          {
                              title: tr('nav.reportingCenter'),
                              href: '/reports',
                              icon: FileSpreadsheet,
                          },
                          {
                              title: tr('nav.assetAnalytics'),
                              href: '/reports/asset-analytics',
                              icon: BarChart3,
                          },
                      ]
                    : []),
            ] satisfies NavItem[],
        },
        {
            label: tr('nav.administration'),
            items: [
                ...(canOpenSettingsCenter
                    ? [
                          {
                              title: tr('nav.settings'),
                              href: '/settings-center',
                              icon: Settings2,
                          },
                      ]
                    : []),
                {
                    title: tr('nav.language'),
                    href: '/settings/language',
                    icon: Globe2,
                },
                ...(auth.permissions['branches.view']
                    ? [
                          {
                              title: tr('nav.branches'),
                              href: '/branches',
                              icon: Building2,
                          },
                      ]
                    : []),
                ...(auth.permissions['users.view']
                    ? [
                          {
                              title: tr('nav.employees'),
                              href: '/employees',
                              icon: UsersRound,
                          },
                          {
                              title: tr('nav.users'),
                              href: '/users',
                              icon: UserRound,
                          },
                      ]
                    : []),
                ...(auth.permissions['roles.view']
                    ? [
                          {
                              title: tr('nav.roles'),
                              href: '/roles',
                              icon: ShieldCheck,
                          },
                      ]
                    : []),
                ...(auth.permissions['audit.view']
                    ? [
                          {
                              title: tr('nav.audit'),
                              href: '/audit-trail',
                              icon: History,
                          },
                      ]
                    : []),
                ...(auth.permissions['imports.view']
                    ? [
                          {
                              title: tr('nav.legacyImport'),
                              href: '/legacy-imports',
                              icon: DatabaseZap,
                          },
                      ]
                    : []),
                ...(auth.canResetOperations
                    ? [
                          {
                              title: tr('nav.operationalReset'),
                              href: '/operations/reset',
                              icon: RotateCcw,
                          },
                      ]
                    : []),
            ] satisfies NavItem[],
        },
    ];

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="[&_[data-sidebar=sidebar]]:rounded-xl [&_[data-sidebar=sidebar]]:border [&_[data-sidebar=sidebar]]:border-sidebar-border/70 [&_[data-sidebar=sidebar]]:shadow-sm"
        >
            <SidebarHeader className="gap-3 border-b border-sidebar-border/70 p-3 group-data-[collapsible=icon]:gap-2 group-data-[collapsible=icon]:p-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <BranchSwitcher />
            </SidebarHeader>

            <SidebarContent className="py-2 group-data-[collapsible=icon]:py-1">
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/70 p-3 group-data-[collapsible=icon]:p-2">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
