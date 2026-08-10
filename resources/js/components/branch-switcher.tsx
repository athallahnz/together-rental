import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';

export function BranchSwitcher() {
    const { auth } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    if (
        !auth.user ||
        !auth.permissions['branches.switch'] ||
        auth.branches.length === 0
    ) {
        return null;
    }

    const current = auth.currentBranch ?? auth.branches[0];

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/35 shadow-xs data-[state=open]:bg-sidebar-accent"
                            tooltip={{ children: current.name }}
                        >
                            <div className="flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg border border-sidebar-border bg-sidebar text-sidebar-foreground">
                                <Building2 className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight transition-opacity duration-200 group-data-[collapsible=icon]:opacity-0">
                                <span className="truncate font-medium">
                                    {current.code}
                                </span>
                                <span className="truncate text-xs text-sidebar-foreground/60">
                                    {current.name}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4 transition-opacity duration-200 group-data-[collapsible=icon]:opacity-0" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-64 rounded-lg"
                        align="start"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'right'
                                  : 'bottom'
                        }
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Cabang aktif
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {auth.branches.map((branch) => {
                            const isCurrent = branch.id === current.id;

                            return (
                                <DropdownMenuItem
                                    key={branch.id}
                                    disabled={isCurrent}
                                    onSelect={() => {
                                        if (!isCurrent) {
                                            router.post(
                                                `/branches/${branch.id}/switch`,
                                                {},
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                    className="gap-3"
                                >
                                    <div className="flex size-7 items-center justify-center rounded-md border font-mono text-[10px] font-semibold">
                                        {branch.code.slice(0, 4)}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate">
                                            {branch.name}
                                        </p>
                                        {branch.city && (
                                            <p className="truncate text-xs text-muted-foreground">
                                                {branch.city}
                                            </p>
                                        )}
                                    </div>
                                    {isCurrent && <Check className="size-4" />}
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
