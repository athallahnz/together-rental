import { Link, usePage } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

export type NavMainGroup = {
    label: string;
    items: NavItem[];
};

function normalizePath(path: string): string {
    const pathname = path.split('?')[0].split('#')[0];

    if (pathname === '/') {
        return pathname;
    }

    return pathname.replace(/\/+$/, '');
}

function itemPath(item: NavItem): string {
    if (typeof item.href === 'string') {
        return normalizePath(item.href);
    }

    return normalizePath(item.href.url);
}

export function NavMain({ groups = [] }: { groups: NavMainGroup[] }) {
    const { url } = usePage();
    const currentPath = normalizePath(url);

    const visibleGroups = groups.filter((group) => group.items.length > 0);
    const allItems = visibleGroups.flatMap((group) => group.items);

    const exactMatch = allItems.find((item) => itemPath(item) === currentPath);

    const parentMatch =
        exactMatch ??
        allItems
            .filter((item) => {
                const path = itemPath(item);

                return path !== '/' && currentPath.startsWith(`${path}/`);
            })
            .sort(
                (first, second) =>
                    itemPath(second).length - itemPath(first).length,
            )[0];

    const activePath = parentMatch ? itemPath(parentMatch) : null;

    return (
        <>
            {visibleGroups.map((group, groupIndex) => (
                <SidebarGroup
                    className={cn(
                        'px-2 py-1 group-data-[collapsible=icon]:py-2',
                        groupIndex > 0 &&
                            'group-data-[collapsible=icon]:border-t group-data-[collapsible=icon]:border-sidebar-border/60',
                    )}
                    key={group.label}
                >
                    <SidebarGroupLabel className="px-2 text-[10px] font-semibold tracking-[0.12em] text-sidebar-foreground/50 uppercase">
                        {group.label}
                    </SidebarGroupLabel>

                    <SidebarMenu>
                        {group.items.map((item) => {
                            const path = itemPath(item);

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={path === activePath}
                                        tooltip={{ children: item.title }}
                                        className="h-9 rounded-lg px-2.5 group-data-[collapsible=icon]:mx-auto data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground data-[active=true]:shadow-sm"
                                    >
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
