import { Link, router, usePage } from '@inertiajs/react';
import { Bell, BellRing, CheckCheck, Inbox } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { NotificationItem } from '@/types';

const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function severityClass(item: NotificationItem) {
    if (item.severity === 'critical') {
        return 'border-destructive/50 bg-destructive/5';
    }

    if (item.severity === 'warning') {
        return 'border-amber-500/40 bg-amber-500/5';
    }

    return 'border-border bg-muted/30';
}

export function NotificationBell() {
    const { auth, notificationCenter } = usePage().props;

    if (!auth.permissions['notifications.view']) {
        return null;
    }

    const openNotification = (item: NotificationItem) => {
        router.patch(
            '/notifications/' + item.id + '/read',
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    router.visit(item.action_url ?? '/notifications', {
                        preserveScroll: true,
                    }),
            },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={
                        notificationCenter.unread_count > 0
                            ? notificationCenter.unread_count +
                              ' notifikasi belum dibaca'
                            : 'Notifikasi'
                    }
                >
                    {notificationCenter.critical_count > 0 ? (
                        <BellRing className="size-5 text-destructive" />
                    ) : (
                        <Bell className="size-5" />
                    )}
                    {notificationCenter.unread_count > 0 && (
                        <span className="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold text-white">
                            {notificationCenter.unread_count > 99
                                ? '99+'
                                : notificationCenter.unread_count}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-[min(24rem,calc(100vw-2rem))]"
            >
                <div className="flex items-center justify-between gap-3 px-2 py-1">
                    <DropdownMenuLabel className="px-0">
                        Notification Center
                    </DropdownMenuLabel>
                    {notificationCenter.unread_count > 0 && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-8 text-xs"
                            onClick={() =>
                                router.post(
                                    '/notifications/mark-all-read',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck className="size-3.5" />
                            Baca semua
                        </Button>
                    )}
                </div>
                <DropdownMenuSeparator />
                <div className="max-h-96 space-y-1 overflow-y-auto p-1">
                    {notificationCenter.recent.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 px-4 py-8 text-center text-sm text-muted-foreground">
                            <Inbox className="size-8 opacity-50" />
                            Tidak ada notifikasi baru.
                        </div>
                    ) : (
                        notificationCenter.recent.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => openNotification(item)}
                                className={
                                    'w-full rounded-md border p-3 text-left transition hover:bg-accent ' +
                                    severityClass(item)
                                }
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <p className="line-clamp-1 text-sm font-medium">
                                        {item.title}
                                    </p>
                                    <Badge
                                        variant={
                                            item.severity === 'critical'
                                                ? 'destructive'
                                                : item.severity === 'warning'
                                                  ? 'secondary'
                                                  : 'outline'
                                        }
                                        className="shrink-0"
                                    >
                                        {item.severity}
                                    </Badge>
                                </div>
                                <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                    {item.body}
                                </p>
                                <p className="mt-2 text-[11px] text-muted-foreground">
                                    {dateTime.format(
                                        new Date(item.last_triggered_at),
                                    )}
                                    {item.branch
                                        ? ' · ' + item.branch.code
                                        : ''}
                                </p>
                            </button>
                        ))
                    )}
                </div>
                <DropdownMenuSeparator />
                <Button
                    variant="ghost"
                    className="w-full justify-center"
                    asChild
                >
                    <Link href="/notifications">Lihat semua notifikasi</Link>
                </Button>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
