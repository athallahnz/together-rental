import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-9 shrink-0 items-center justify-center rounded-xl bg-sidebar-primary text-sidebar-primary-foreground shadow-sm transition-[width,height,border-radius] duration-200 group-data-[collapsible=icon]:size-8 group-data-[collapsible=icon]:rounded-lg">
                <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm leading-tight transition-[opacity,transform] duration-200 group-data-[collapsible=icon]:translate-x-1 group-data-[collapsible=icon]:opacity-0">
                <span className="truncate font-semibold tracking-tight">
                    {name}
                </span>
                <span className="truncate text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                    Rental Operations
                </span>
            </div>
        </>
    );
}
