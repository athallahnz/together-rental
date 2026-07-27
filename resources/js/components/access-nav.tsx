import { Link, usePage } from '@inertiajs/react';
import { BadgeCheck, ContactRound, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const items = [
    {
        title: 'Akun pengguna',
        href: '/users',
        icon: Users,
        permission: 'users.view',
    },
    {
        title: 'Karyawan & jabatan',
        href: '/employees',
        icon: ContactRound,
        permission: 'users.view',
    },
    {
        title: 'Role & permission',
        href: '/roles',
        icon: BadgeCheck,
        permission: 'roles.view',
    },
];

export function AccessNav({ current }: { current: string }) {
    const { auth } = usePage().props;

    return (
        <nav className="flex flex-wrap gap-2">
            {items
                .filter((item) => auth.permissions[item.permission])
                .map((item) => (
                    <Button
                        key={item.href}
                        asChild
                        size="sm"
                        variant={current === item.href ? 'default' : 'outline'}
                        className={cn(
                            current === item.href &&
                                'pointer-events-none shadow-sm',
                        )}
                    >
                        <Link href={item.href}>
                            <item.icon />
                            {item.title}
                        </Link>
                    </Button>
                ))}
        </nav>
    );
}
