import { Link, usePage } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
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
            {visibleGroups.map((group) => (
                <SidebarGroup className="px-2 py-0" key={group.label}>
                    <SidebarGroupLabel>{group.label}</SidebarGroupLabel>

                    <SidebarMenu>
                        {group.items.map((item) => {
                            const path = itemPath(item);

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={path === activePath}
                                        tooltip={{ children: item.title }}
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
