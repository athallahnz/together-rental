import { Link, usePage } from "@inertiajs/react";
import {
  BarChart3,
  BrainCircuit,
  CalendarDays,
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
} from "lucide-react";
import AppLogo from "@/components/app-logo";
import { BranchSwitcher } from "@/components/branch-switcher";
import { NavMain  } from "@/components/nav-main";
import type {NavMainGroup} from "@/components/nav-main";
import { NavUser } from "@/components/nav-user";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";
import { dashboard } from "@/routes";
import type { NavItem } from "@/types";

export function AppSidebar() {
  const { auth } = usePage().props;
  const navGroups: NavMainGroup[] = [
    {
      label: "Ringkasan",
      items: [
        {
          title: "Dashboard",
          href: dashboard(),
          icon: LayoutGrid,
        },
      ],
    },
    {
      label: "Operasional Rental",
      items: [
        ...(auth.permissions["bookings.view"]
          ? [
              {
                title: "Booking",
                href: "/bookings",
                icon: CalendarDays,
              },
            ]
          : []),
        ...(auth.permissions["rentals.view"]
          ? [
              {
                title: "Rental",
                href: "/rentals",
                icon: ShoppingBag,
              },
            ]
          : []),
        ...(auth.permissions["rentals.create"]
          ? [
              {
                title: "Rental Langsung",
                href: "/rentals/direct/create",
                icon: ShoppingBag,
              },
            ]
          : []),
        ...(auth.permissions["maintenance.view"]
          ? [
              {
                title: "Maintenance",
                href: "/maintenance",
                icon: Wrench,
              },
            ]
          : []),
      ] satisfies NavItem[],
    },
    {
      label: "Data & Inventaris",
      items: [
        ...(auth.permissions["customers.view"]
          ? [
              {
                title: "Pelanggan",
                href: "/customers",
                icon: ContactRound,
              },
            ]
          : []),
        ...(auth.permissions["products.view"]
          ? [
              {
                title: "Katalog & Harga",
                href: "/catalog",
                icon: PackageSearch,
              },
            ]
          : []),
        ...(auth.permissions["products.manage"]
          ? [
              {
                title: "Konten Katalog Publik",
                href: "/catalog/public-content",
                icon: Globe2,
              },
              {
                title: "Kecerdasan Katalog",
                href: "/catalog/intelligence",
                icon: BrainCircuit,
              },
            ]
          : []),
      ] satisfies NavItem[],
    },
    {
      label: "Laporan",
      items: [
        ...(auth.permissions["reports.view"]
          ? [
              {
                title: "Analitik Aset",
                href: "/reports/asset-analytics",
                icon: BarChart3,
              },
            ]
          : []),
      ] satisfies NavItem[],
    },
    {
      label: "Administrasi",
      items: [
        ...(auth.permissions["branches.view"]
          ? [
              {
                title: "Cabang",
                href: "/branches",
                icon: Building2,
              },
            ]
          : []),
        ...(auth.permissions["users.view"]
          ? [
              {
                title: "Karyawan",
                href: "/employees",
                icon: UsersRound,
              },
              {
                title: "Pengguna",
                href: "/users",
                icon: UserRound,
              },
            ]
          : []),
        ...(auth.permissions["roles.view"]
          ? [
              {
                title: "Role & Hak Akses",
                href: "/roles",
                icon: ShieldCheck,
              },
            ]
          : []),
        ...(auth.permissions["imports.view"]
          ? [
              {
                title: "Legacy Import",
                href: "/legacy-imports",
                icon: DatabaseZap,
              },
            ]
          : []),
      ] satisfies NavItem[],
    },
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
        <NavMain groups={navGroups} />
      </SidebarContent>

      <SidebarFooter>
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
