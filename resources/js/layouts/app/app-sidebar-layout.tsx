import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { url } = usePage();
    const pagePath = url.split('?')[0];

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="scrollbar-subtle [scrollbar-gutter:stable] overflow-x-hidden overflow-y-auto overscroll-y-contain bg-muted/15"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div key={pagePath} className="app-page-enter min-h-0 flex-1">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
