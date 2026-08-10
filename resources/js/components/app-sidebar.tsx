import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CreditCard,
    CalendarDays,
    ClipboardCheck,
    Building2,
    ContactRound,
    DatabaseZap,
    Globe2,
    LayoutGrid,
    PackageSearch,
    ShieldCheck,
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
    BellRing,
    BadgePercent,
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const navGroups: NavMainGroup[] = [
        {
            label: 'Ringkasan',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                },
                ...(auth.permissions['notifications.view']
                    ? [
                          {
                              title: 'Notifikasi',
                              href: '/notifications',
                              icon: BellRing,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: 'Operasional Rental',
            items: [
                ...(auth.permissions['bookings.view']
                    ? [
                          {
                              title: 'Booking',
                              href: '/bookings',
                              icon: CalendarDays,
                          },
                      ]
                    : []),
                ...(auth.permissions['rentals.view']
                    ? [
                          {
                              title: 'Rental Control Center',
                              href: '/rentals',
                              icon: ShoppingBag,
                          },
                      ]
                    : []),
                ...(auth.permissions['rentals.create']
                    ? [
                          {
                              title: 'Rental In Store',
                              href: '/rentals/direct/create',
                              icon: ShoppingBag,
                          },
                      ]
                    : []),
                ...(auth.permissions['maintenance.view']
                    ? [
                          {
                              title: 'Maintenance',
                              href: '/maintenance',
                              icon: Wrench,
                          },
                      ]
                    : []),
            ] satisfies NavItem[],
        },
        {
            label: 'Keuangan',
            items: [
                ...(auth.permissions['finance.dashboard.view']
                    ? [
                          {
                              title: 'Finance Dashboard',
                              href: '/finance/dashboard',
                              icon: WalletCards,
                          },
                      ]
                    : []),
                ...(auth.permissions['payments.view']
                    ? [
                          {
                              title: 'Payment Center',
                              href: '/finance/payments',
                              icon: CreditCard,
                          },
                      ]
                    : []),
                ...(auth.permissions['refunds.view']
                    ? [
                          {
                              title: 'Refund Center',
                              href: '/finance/refunds',
                              icon: ReceiptText,
                          },
                      ]
                    : []),
                ...(auth.permissions['finance.masters.view']
                    ? [
                          {
                              title: 'Master Finance & Kasir',
                              href: '/finance/master-data',
                              icon: SlidersHorizontal,
                          },
                      ]
                    : []),
            ] satisfies NavItem[],
        },
        {
            label: 'Data & Inventaris',
            items: [
                ...(auth.permissions['customers.view']
                    ? [
                          {
                              title: 'Pelanggan',
                              href: '/customers',
                              icon: ContactRound,
                          },
                      ]
                    : []),
                ...(auth.permissions['products.view']
                    ? [
                          {
                              title: 'Katalog & Harga',
                              href: '/catalog',
                              icon: PackageSearch,
                          },
                          {
                              title: 'Promosi & Diskon',
                              href: '/catalog/promotions',
                              icon: BadgePercent,
                          },
                      ]
                    : []),
                ...(auth.permissions['products.manage']
                    ? [
                          {
                              title: 'Konten Katalog Publik',
                              href: '/catalog/public-content',
                              icon: Globe2,
                          },
                          {
                              title: 'Kecerdasan Katalog',
                              href: '/catalog/intelligence',
                              icon: Sparkles,
                          },
                      ]
                    : []),
                ...(auth.permissions['transfers.view']
                    ? [
                          {
                              title: 'Transfer Aset',
                              href: '/transfers',
                              icon: ArrowLeftRight,
                          },
                      ]
                    : []),
                ...(auth.permissions['inventory-audits.view']
                    ? [
                          {
                              title: 'Stock Opname',
                              href: '/inventory-audits',
                              icon: ClipboardCheck,
                          },
                      ]
                    : []),
            ] satisfies NavItem[],
        },
        {
            label: 'Laporan',
            items: [
                ...(auth.permissions['reports.view']
                    ? [
                          {
                              title: 'Reporting Center',
                              href: '/reports',
                              icon: FileSpreadsheet,
                          },
                          {
                              title: 'Analitik Aset',
                              href: '/reports/asset-analytics',
                              icon: BarChart3,
                          },
                      ]
                    : []),
            ] satisfies NavItem[],
        },
        {
            label: 'Administrasi',
            items: [
                ...(auth.permissions['branches.view']
                    ? [
                          {
                              title: 'Cabang',
                              href: '/branches',
                              icon: Building2,
                          },
                      ]
                    : []),
                ...(auth.permissions['users.view']
                    ? [
                          {
                              title: 'Karyawan',
                              href: '/employees',
                              icon: UsersRound,
                          },
                          {
                              title: 'Pengguna',
                              href: '/users',
                              icon: UserRound,
                          },
                      ]
                    : []),
                ...(auth.permissions['roles.view']
                    ? [
                          {
                              title: 'Role & Hak Akses',
                              href: '/roles',
                              icon: ShieldCheck,
                          },
                      ]
                    : []),
                ...(auth.permissions['imports.view']
                    ? [
                          {
                              title: 'Legacy Import',
                              href: '/legacy-imports',
                              icon: DatabaseZap,
                          },
                      ]
                    : []),
                ...(auth.canResetOperations
                    ? [
                          {
                              title: 'Reset Data Operasional',
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
