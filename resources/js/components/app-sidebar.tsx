import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Building2,
    ContactRound,
    DatabaseZap,
    LayoutGrid,
    PackageSearch,
    UserRoundCog,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { BranchSwitcher } from '@/components/branch-switcher';
import { NavMain } from '@/components/nav-main';
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
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(auth.permissions['branches.view']
            ? [
                  {
                      title: 'Cabang',
                      href: '/branches',
                      icon: Building2,
                  },
              ]
            : []),
        ...(auth.permissions['users.view'] || auth.permissions['roles.view']
            ? [
                  {
                      title: 'Pengguna & Akses',
                      href: auth.permissions['users.view']
                          ? '/users'
                          : '/roles',
                      icon: UserRoundCog,
                  },
              ]
            : []),
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
              ]
            : []),
        ...(auth.permissions['reports.view']
            ? [
                  {
                      title: 'Analitik Aset',
                      href: '/reports/asset-analytics',
                      icon: BarChart3,
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
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
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

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
